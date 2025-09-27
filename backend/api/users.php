<?php
/**
 * API Gestion des utilisateurs
 * Inscription, login, gestion des plans et profils
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/User.php';

class UsersAPI 
{
    private $user;
    private $method;
    private $action;
    
    public function __construct() 
    {
        $this->user = new User();
        $this->method = $_SERVER['REQUEST_METHOD'];
        $this->action = $_GET['action'] ?? '';
    }
    
    public function handleRequest() 
    {
        try {
            switch ($this->method) {
                case 'POST':
                    return $this->handlePost();
                case 'GET':
                    return $this->handleGet();
                case 'PUT':
                    return $this->handlePut();
                case 'DELETE':
                    return $this->handleDelete();
                default:
                    throw new Exception('Méthode non autorisée', 405);
            }
        } catch (Exception $e) {
            $this->sendError($e->getMessage(), $e->getCode() ?: 400);
        }
    }
    
    /**
     * POST - Créer compte, login, logout
     */
    private function handlePost() 
    {
        $data = json_decode(file_get_contents('php://input'), true);
        
        switch ($this->action) {
            case 'register':
                return $this->register($data);
            case 'login':
                return $this->login($data);
            case 'logout':
                return $this->logout($data);
            case 'verify-email':
                return $this->verifyEmail($data);
            case 'reset-password':
                return $this->resetPassword($data);
            case 'change-password':
                return $this->changePassword($data);
            default:
                throw new Exception('Action non valide', 400);
        }
    }
    
    /**
     * GET - Profil, stats, usage
     */
    private function handleGet() 
    {
        $userId = $this->authenticateUser();
        
        switch ($this->action) {
            case 'profile':
                return $this->getProfile($userId);
            case 'stats':
                return $this->getStats($userId);
            case 'usage':
                return $this->getUsage($userId);
            case 'quotas':
                return $this->getQuotas($userId);
            case 'referrals':
                return $this->getReferrals($userId);
            default:
                return $this->getProfile($userId);
        }
    }
    
    /**
     * PUT - Mettre à jour profil
     */
    private function handlePut() 
    {
        $userId = $this->authenticateUser();
        $data = json_decode(file_get_contents('php://input'), true);
        
        switch ($this->action) {
            case 'profile':
                return $this->updateProfile($userId, $data);
            case 'preferences':
                return $this->updatePreferences($userId, $data);
            default:
                return $this->updateProfile($userId, $data);
        }
    }
    
    /**
     * DELETE - Supprimer compte
     */
    private function handleDelete() 
    {
        $userId = $this->authenticateUser();
        
        switch ($this->action) {
            case 'account':
                return $this->deleteAccount($userId);
            default:
                throw new Exception('Action non valide', 400);
        }
    }
    
    /**
     * Inscription utilisateur
     */
    private function register(array $data): array 
    {
        // Validation
        if (empty($data['email']) || empty($data['password'])) {
            throw new Exception('Email et mot de passe requis', 400);
        }
        
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Email invalide', 400);
        }
        
        if (strlen($data['password']) < 8) {
            throw new Exception('Mot de passe trop court (minimum 8 caractères)', 400);
        }
        
        // Rate limiting inscription
        $this->checkRegistrationRateLimit($_SERVER['REMOTE_ADDR']);
        
        try {
            $userId = $this->user->createAccount([
                'email' => $data['email'],
                'password' => $data['password'],
                'nom' => $data['nom'] ?? null,
                'prenom' => $data['prenom'] ?? null,
                'entreprise' => $data['entreprise'] ?? null,
                'phone' => $data['phone'] ?? null,
                'plan_type' => $data['plan_type'] ?? 'starter',
                'referral_code' => $data['referral_code'] ?? null
            ]);
            
            // Créer session
            $sessionToken = $this->user->createSession(
                $userId,
                $_SERVER['REMOTE_ADDR'],
                $_SERVER['HTTP_USER_AGENT'] ?? ''
            );
            
            // Log activité
            $this->logActivity($userId, 'user_registered', [
                'plan_type' => $data['plan_type'] ?? 'starter',
                'has_referral' => !empty($data['referral_code'])
            ]);
            
            return $this->sendSuccess([
                'message' => 'Compte créé avec succès',
                'user_id' => $userId,
                'session_token' => $sessionToken,
                'trial_ends_at' => date('Y-m-d', strtotime('+14 days'))
            ]);
            
        } catch (Exception $e) {
            throw new Exception('Erreur lors de la création du compte: ' . $e->getMessage(), 400);
        }
    }
    
    /**
     * Connexion utilisateur
     */
    private function login(array $data): array 
    {
        if (empty($data['email']) || empty($data['password'])) {
            throw new Exception('Email et mot de passe requis', 400);
        }
        
        // Rate limiting login
        $this->checkLoginRateLimit($data['email'], $_SERVER['REMOTE_ADDR']);
        
        $user = $this->user->authenticate($data['email'], $data['password']);
        
        if (!$user) {
            // Log tentative échouée
            $this->logFailedLogin($data['email'], $_SERVER['REMOTE_ADDR']);
            throw new Exception('Email ou mot de passe incorrect', 401);
        }
        
        // Créer session
        $sessionToken = $this->user->createSession(
            $user['id'],
            $_SERVER['REMOTE_ADDR'],
            $_SERVER['HTTP_USER_AGENT'] ?? ''
        );
        
        // Stats utilisateur
        $stats = $this->user->getUserStats($user['id']);
        
        // Log activité
        $this->logActivity($user['id'], 'user_login', [
            'ip_address' => $_SERVER['REMOTE_ADDR']
        ]);
        
        return $this->sendSuccess([
            'message' => 'Connexion réussie',
            'session_token' => $sessionToken,
            'user' => [
                'id' => $user['id'],
                'email' => $user['email'],
                'nom' => $user['nom'],
                'prenom' => $user['prenom'],
                'entreprise' => $user['entreprise'],
                'plan_type' => $user['plan_type'],
                'plan_status' => $user['plan_status'],
                'trial_ends_at' => $user['trial_ends_at'],
                'plan_expires_at' => $user['plan_expires_at']
            ],
            'quotas' => [
                'recherches_jour' => $user['quota_recherches_jour'],
                'documents_jour' => $user['quota_documents_jour'],
                'surveillance' => $user['quota_surveillance'],
                'recherches_utilisees' => $stats['recherches_aujourd_hui'],
                'documents_utilises' => $stats['documents_aujourd_hui']
            ]
        ]);
    }
    
    /**
     * Déconnexion utilisateur
     */
    private function logout(array $data): array 
    {
        if (empty($data['session_token'])) {
            throw new Exception('Token de session requis', 400);
        }
        
        $db = Database::getInstance();
        $db->query(
            "DELETE FROM user_sessions WHERE session_token = ?",
            [$data['session_token']]
        );
        
        return $this->sendSuccess(['message' => 'Déconnexion réussie']);
    }
    
    /**
     * Obtenir le profil utilisateur
     */
    private function getProfile(int $userId): array 
    {
        $user = $this->user->findById($userId);
        if (!$user) {
            throw new Exception('Utilisateur non trouvé', 404);
        }
        
        $stats = $this->user->getUserStats($userId);
        
        return $this->sendSuccess([
            'user' => [
                'id' => $user['id'],
                'email' => $user['email'],
                'nom' => $user['nom'],
                'prenom' => $user['prenom'],
                'entreprise' => $user['entreprise'],
                'phone' => $user['phone'],
                'avatar' => $user['avatar'],
                'plan_type' => $user['plan_type'],
                'plan_status' => $user['plan_status'],
                'trial_ends_at' => $user['trial_ends_at'],
                'plan_expires_at' => $user['plan_expires_at'],
                'referral_code' => $user['referral_code'],
                'created_at' => $user['created_at'],
                'derniere_connexion' => $user['derniere_connexion']
            ],
            'quotas' => [
                'recherches_jour' => $user['quota_recherches_jour'],
                'documents_jour' => $user['quota_documents_jour'],
                'surveillance' => $user['quota_surveillance'],
                'recherches_utilisees' => $stats['recherches_aujourd_hui'],
                'documents_utilises' => $stats['documents_aujourd_hui'],
                'surveillances_actives' => $stats['surveillances_actives']
            ]
        ]);
    }
    
    /**
     * Obtenir les statistiques d'usage
     */
    private function getStats(int $userId): array 
    {
        $db = Database::getInstance();
        
        // Stats des 30 derniers jours
        $sql = "SELECT 
                    DATE(date_usage) as date,
                    recherches,
                    documents,
                    api_calls
                FROM user_usage 
                WHERE user_id = ? AND date_usage >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                ORDER BY date_usage ASC";
        
        $dailyStats = $db->query($sql, [$userId])->fetchAll();
        
        // Stats totales
        $totalStats = $db->query("
            SELECT 
                SUM(recherches) as total_recherches,
                SUM(documents) as total_documents,
                SUM(api_calls) as total_api_calls,
                COUNT(DISTINCT date_usage) as jours_actifs
            FROM user_usage 
            WHERE user_id = ?
        ", [$userId])->fetch();
        
        // Top recherches
        $topSearches = $db->query("
            SELECT query, COUNT(*) as count
            FROM activity_logs 
            WHERE user_id = ? AND action = 'company_search'
            GROUP BY metadata->>'$.query'
            ORDER BY count DESC
            LIMIT 10
        ", [$userId])->fetchAll();
        
        return $this->sendSuccess([
            'daily_stats' => $dailyStats,
            'total_stats' => $totalStats,
            'top_searches' => $topSearches
        ]);
    }
    
    /**
     * Obtenir l'usage actuel
     */
    private function getUsage(int $userId): array 
    {
        $stats = $this->user->getUserStats($userId);
        $quotas = $this->user->getPlanQuotas($stats['plan_type']);
        
        return $this->sendSuccess([
            'today' => [
                'recherches' => [
                    'used' => $stats['recherches_aujourd_hui'],
                    'limit' => $stats['quota_recherches_jour'],
                    'remaining' => max(0, $stats['quota_recherches_jour'] - $stats['recherches_aujourd_hui'])
                ],
                'documents' => [
                    'used' => $stats['documents_aujourd_hui'],
                    'limit' => $stats['quota_documents_jour'],
                    'remaining' => max(0, $stats['quota_documents_jour'] - $stats['documents_aujourd_hui'])
                ],
                'api_calls' => [
                    'used' => $stats['api_calls_aujourd_hui'],
                    'limit' => $quotas['api_calls'],
                    'remaining' => max(0, $quotas['api_calls'] - $stats['api_calls_aujourd_hui'])
                ]
            ],
            'surveillance' => [
                'used' => $stats['surveillances_actives'],
                'limit' => $stats['quota_surveillance'],
                'remaining' => max(0, $stats['quota_surveillance'] - $stats['surveillances_actives'])
            ]
        ]);
    }
    
    /**
     * Obtenir les parrainages
     */
    private function getReferrals(int $userId): array 
    {
        $db = Database::getInstance();
        
        $referrals = $db->query("
            SELECT 
                u.email,
                u.plan_type,
                u.created_at,
                rl.referrer_bonus_amount,
                rl.bonus_applied_at
            FROM referral_logs rl
            JOIN users u ON rl.referred_id = u.id
            WHERE rl.referrer_id = ?
            ORDER BY rl.bonus_applied_at DESC
        ", [$userId])->fetchAll();
        
        $totalBonus = $db->query("
            SELECT SUM(referrer_bonus_amount) as total
            FROM referral_logs 
            WHERE referrer_id = ?
        ", [$userId])->fetch()['total'] ?? 0;
        
        return $this->sendSuccess([
            'referrals' => $referrals,
            'total_referrals' => count($referrals),
            'total_bonus' => $totalBonus,
            'referral_url' => BASE_URL . '?ref=' . $this->user->findById($userId)['referral_code']
        ]);
    }
    
    /**
     * Mettre à jour le profil
     */
    private function updateProfile(int $userId, array $data): array 
    {
        $allowedFields = ['nom', 'prenom', 'entreprise', 'phone'];
        $updateFields = [];
        $params = [];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updateFields[] = "$field = ?";
                $params[] = $data[$field];
            }
        }
        
        if (empty($updateFields)) {
            throw new Exception('Aucune donnée à mettre à jour', 400);
        }
        
        $params[] = $userId;
        
        $db = Database::getInstance();
        $db->query("
            UPDATE users SET " . implode(', ', $updateFields) . ", updated_at = NOW()
            WHERE id = ?
        ", $params);
        
        $this->logActivity($userId, 'profile_updated', $data);
        
        return $this->sendSuccess(['message' => 'Profil mis à jour avec succès']);
    }
    
    /**
     * Authentifier l'utilisateur via token
     */
    private function authenticateUser(): int 
    {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (!preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            throw new Exception('Token d\'authentification requis', 401);
        }
        
        $sessionToken = $matches[1];
        $userId = $this->user->validateSession($sessionToken);
        
        if (!$userId) {
            throw new Exception('Session invalide ou expirée', 401);
        }
        
        return $userId;
    }
    
    /**
     * Vérifier le rate limiting pour inscription
     */
    private function checkRegistrationRateLimit(string $ip): void 
    {
        $db = Database::getInstance();
        $count = $db->query("
            SELECT COUNT(*) as count
            FROM users 
            WHERE last_login_ip = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ", [$ip])->fetch()['count'];
        
        if ($count >= 5) { // Max 5 inscriptions par heure par IP
            throw new Exception('Trop de tentatives d\'inscription. Réessayez plus tard.', 429);
        }
    }
    
    /**
     * Vérifier le rate limiting pour connexion
     */
    private function checkLoginRateLimit(string $email, string $ip): void 
    {
        $db = Database::getInstance();
        $count = $db->query("
            SELECT COUNT(*) as count
            FROM activity_logs 
            WHERE (metadata->>'$.email' = ? OR ip_address = ?)
              AND action = 'login_failed'
              AND created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
        ", [$email, $ip])->fetch()['count'];
        
        if ($count >= 5) { // Max 5 tentatives par 15 min
            throw new Exception('Trop de tentatives de connexion. Réessayez plus tard.', 429);
        }
    }
    
    /**
     * Logger une activité utilisateur
     */
    private function logActivity(int $userId, string $action, array $metadata = []): void 
    {
        $db = Database::getInstance();
        $db->query("
            INSERT INTO activity_logs (user_id, action, metadata, ip_address, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ", [
            $userId,
            $action,
            json_encode($metadata),
            $_SERVER['REMOTE_ADDR']
        ]);
    }
    
    /**
     * Logger une tentative de connexion échouée
     */
    private function logFailedLogin(string $email, string $ip): void 
    {
        $db = Database::getInstance();
        $db->query("
            INSERT INTO activity_logs (action, metadata, ip_address, created_at)
            VALUES ('login_failed', ?, ?, NOW())
        ", [
            json_encode(['email' => $email]),
            $ip
        ]);
    }
    
    /**
     * Envoyer une réponse de succès
     */
    private function sendSuccess(array $data): array 
    {
        http_response_code(200);
        $response = array_merge(['success' => true], $data);
        echo json_encode($response);
        return $response;
    }
    
    /**
     * Envoyer une erreur
     */
    private function sendError(string $message, int $code = 400): void 
    {
        http_response_code($code);
        echo json_encode([
            'success' => false,
            'error' => $message,
            'code' => $code
        ]);
        exit;
    }
}

// Exécution de l'API
$api = new UsersAPI();
$api->handleRequest();