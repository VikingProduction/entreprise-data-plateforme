<?php
/**
 * Modèle User pour plateforme SaaS
 * Gestion des comptes, abonnements et permissions
 */

require_once __DIR__ . '/Database.php';

class User 
{
    private $db;

    public function __construct() 
    {
        $this->db = Database::getInstance();
    }

    /**
     * Créer un nouveau compte utilisateur
     */
    public function createAccount(array $data): ?int 
    {
        // Vérifier que l'email n'existe pas déjà
        if ($this->findByEmail($data['email'])) {
            throw new Exception("Un compte avec cet email existe déjà");
        }

        $sql = "INSERT INTO users (
            email, password_hash, nom, prenom, entreprise, phone,
            plan_type, plan_status, trial_ends_at, quota_recherches_jour,
            quota_documents_jour, quota_surveillance, email_verified_at,
            referral_code, referred_by_code
        ) VALUES (
            :email, :password_hash, :nom, :prenom, :entreprise, :phone,
            :plan_type, 'trial', DATE_ADD(NOW(), INTERVAL 14 DAY),
            :quota_recherches, :quota_documents, :quota_surveillance,
            NULL, :referral_code, :referred_by_code
        )";

        $referralCode = $this->generateReferralCode();
        $planQuotas = $this->getPlanQuotas($data['plan_type'] ?? 'starter');

        $params = [
            'email' => $data['email'],
            'password_hash' => password_hash($data['password'], PASSWORD_ARGON2ID),
            'nom' => $data['nom'] ?? null,
            'prenom' => $data['prenom'] ?? null,
            'entreprise' => $data['entreprise'] ?? null,
            'phone' => $data['phone'] ?? null,
            'plan_type' => $data['plan_type'] ?? 'starter',
            'quota_recherches' => $planQuotas['recherches'],
            'quota_documents' => $planQuotas['documents'],
            'quota_surveillance' => $planQuotas['surveillance'],
            'referral_code' => $referralCode,
            'referred_by_code' => $data['referral_code'] ?? null
        ];

        try {
            $this->db->beginTransaction();

            $this->db->query($sql, $params);
            $userId = (int)$this->db->lastInsertId();

            // Envoyer email de vérification
            $this->sendVerificationEmail($userId, $data['email']);

            // Bonus parrainage
            if (!empty($data['referral_code'])) {
                $this->processReferralBonus($data['referral_code'], $userId);
            }

            $this->db->commit();
            return $userId;

        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * Authentifier un utilisateur
     */
    public function authenticate(string $email, string $password): ?array 
    {
        $user = $this->findByEmail($email);
        if (!$user || !password_verify($password, $user['password_hash'])) {
            return null;
        }

        // Mettre à jour la dernière connexion
        $this->db->query(
            "UPDATE users SET derniere_connexion = NOW() WHERE id = ?",
            [$user['id']]
        );

        // Vérifier si l'abonnement est encore valide
        $user = $this->checkSubscriptionStatus($user);

        return $user;
    }

    /**
     * Quotas par plan
     */
    private function getPlanQuotas(string $planType): array 
    {
        $quotas = [
            'free' => [
                'recherches' => 10,
                'documents' => 2,
                'surveillance' => 0,
                'api_calls' => 50,
                'export_pdf' => false,
                'support' => 'community'
            ],
            'starter' => [
                'recherches' => 100,
                'documents' => 25,
                'surveillance' => 5,
                'api_calls' => 1000,
                'export_pdf' => true,
                'support' => 'email'
            ],
            'business' => [
                'recherches' => 1000,
                'documents' => 200,
                'surveillance' => 50,
                'api_calls' => 10000,
                'export_pdf' => true,
                'support' => 'priority'
            ],
            'enterprise' => [
                'recherches' => 10000,
                'documents' => 2000,
                'surveillance' => 500,
                'api_calls' => 100000,
                'export_pdf' => true,
                'support' => 'dedicated'
            ]
        ];

        return $quotas[$planType] ?? $quotas['free'];
    }

    /**
     * Vérifier les quotas utilisateur
     */
    public function checkQuota(int $userId, string $type): bool 
    {
        $user = $this->findById($userId);
        if (!$user) return false;

        $today = date('Y-m-d');
        $currentUsage = $this->getCurrentUsage($userId, $today);

        switch ($type) {
            case 'recherche':
                return $currentUsage['recherches'] < $user['quota_recherches_jour'];
            case 'document':
                return $currentUsage['documents'] < $user['quota_documents_jour'];
            case 'surveillance':
                return $this->getActiveSurveillanceCount($userId) < $user['quota_surveillance'];
            case 'api':
                return $currentUsage['api_calls'] < $this->getPlanQuotas($user['plan_type'])['api_calls'];
        }

        return false;
    }

    /**
     * Incrémenter l'usage
     */
    public function incrementUsage(int $userId, string $type): void 
    {
        $today = date('Y-m-d');

        $sql = "INSERT INTO user_usage (user_id, date_usage, {$type}) 
                VALUES (?, ?, 1)
                ON DUPLICATE KEY UPDATE {$type} = {$type} + 1";

        $this->db->query($sql, [$userId, $today]);
    }

    /**
     * Code de parrainage aléatoire
     */
    private function generateReferralCode(): string 
    {
        return strtoupper(substr(md5(uniqid(rand(), true)), 0, 8));
    }

    /**
     * Traitement bonus parrainage
     */
    private function processReferralBonus(string $referralCode, int $newUserId): void 
    {
        $referrer = $this->db->query(
            "SELECT id FROM users WHERE referral_code = ?", 
            [$referralCode]
        )->fetch();

        if ($referrer) {
            // Bonus pour le parrain : 1 mois gratuit
            $this->db->query(
                "UPDATE users SET 
                    plan_expires_at = DATE_ADD(COALESCE(plan_expires_at, NOW()), INTERVAL 30 DAY),
                    referral_bonus_credits = referral_bonus_credits + 100
                 WHERE id = ?",
                [$referrer['id']]
            );

            // Bonus pour le nouveau : 7 jours trial supplémentaires
            $this->db->query(
                "UPDATE users SET trial_ends_at = DATE_ADD(trial_ends_at, INTERVAL 7 DAY) 
                 WHERE id = ?",
                [$newUserId]
            );

            // Log de parrainage
            $this->db->query(
                "INSERT INTO referral_logs (referrer_id, referred_id, bonus_applied_at) 
                 VALUES (?, ?, NOW())",
                [$referrer['id'], $newUserId]
            );
        }
    }

    /**
     * Vérifier le statut de l'abonnement
     */
    private function checkSubscriptionStatus(array $user): array 
    {
        $now = new DateTime();

        // Vérifier trial
        if ($user['plan_status'] === 'trial' && $user['trial_ends_at']) {
            $trialEnd = new DateTime($user['trial_ends_at']);
            if ($now > $trialEnd) {
                // Trial expiré - passer en mode gratuit
                $this->db->query(
                    "UPDATE users SET plan_status = 'expired', plan_type = 'free' WHERE id = ?",
                    [$user['id']]
                );
                $user['plan_status'] = 'expired';
                $user['plan_type'] = 'free';
            }
        }

        // Vérifier abonnement payant
        if ($user['plan_status'] === 'active' && $user['plan_expires_at']) {
            $planEnd = new DateTime($user['plan_expires_at']);
            if ($now > $planEnd) {
                // Abonnement expiré
                $this->db->query(
                    "UPDATE users SET plan_status = 'expired' WHERE id = ?",
                    [$user['id']]
                );
                $user['plan_status'] = 'expired';
            }
        }

        return $user;
    }

    public function findByEmail(string $email): ?array 
    {
        $stmt = $this->db->query("SELECT * FROM users WHERE email = ?", [$email]);
        return $stmt->fetch() ?: null;
    }

    public function findById(int $id): ?array 
    {
        $stmt = $this->db->query("SELECT * FROM users WHERE id = ?", [$id]);
        return $stmt->fetch() ?: null;
    }

    private function getCurrentUsage(int $userId, string $date): array 
    {
        $stmt = $this->db->query(
            "SELECT * FROM user_usage WHERE user_id = ? AND date_usage = ?",
            [$userId, $date]
        );

        $usage = $stmt->fetch();
        return [
            'recherches' => $usage['recherches'] ?? 0,
            'documents' => $usage['documents'] ?? 0,
            'api_calls' => $usage['api_calls'] ?? 0
        ];
    }

    private function getActiveSurveillanceCount(int $userId): int 
    {
        $stmt = $this->db->query(
            "SELECT COUNT(*) as count FROM surveillances WHERE user_id = ? AND active = 1",
            [$userId]
        );
        return (int)$stmt->fetch()['count'];
    }

    private function sendVerificationEmail(int $userId, string $email): void 
    {
        // TODO: Intégrer avec service email (SendGrid, Mailgun, etc.)
        // Pour l'instant, log local
        error_log("Email verification needed for user $userId: $email");
    }
}
