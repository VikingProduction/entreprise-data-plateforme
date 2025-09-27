<?php
/**
 * API Gestion des paiements et abonnements Stripe
 * Checkout, webhooks, gestion des plans
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
require_once __DIR__ . '/../integrations/StripeIntegration.php';

class BillingAPI 
{
    private $user;
    private $stripe;
    private $method;
    private $action;
    
    public function __construct() 
    {
        $this->user = new User();
        $this->stripe = new StripeIntegration();
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
     * POST - Créer checkout, webhook
     */
    private function handlePost() 
    {
        switch ($this->action) {
            case 'create-checkout':
                $userId = $this->authenticateUser();
                $data = json_decode(file_get_contents('php://input'), true);
                return $this->createCheckout($userId, $data);
                
            case 'webhook':
                return $this->handleWebhook();
                
            case 'create-portal':
                $userId = $this->authenticateUser();
                return $this->createPortalSession($userId);
                
            default:
                throw new Exception('Action non valide', 400);
        }
    }
    
    /**
     * GET - Plans, historique, status abonnement
     */
    private function handleGet() 
    {
        switch ($this->action) {
            case 'plans':
                return $this->getPlans();
                
            case 'subscription':
                $userId = $this->authenticateUser();
                return $this->getSubscription($userId);
                
            case 'invoices':
                $userId = $this->authenticateUser();
                return $this->getInvoices($userId);
                
            case 'payment-methods':
                $userId = $this->authenticateUser();
                return $this->getPaymentMethods($userId);
                
            case 'usage-billing':
                $userId = $this->authenticateUser();
                return $this->getUsageBilling($userId);
                
            default:
                throw new Exception('Action non valide', 400);
        }
    }
    
    /**
     * PUT - Modifier abonnement
     */
    private function handlePut() 
    {
        $userId = $this->authenticateUser();
        $data = json_decode(file_get_contents('php://input'), true);
        
        switch ($this->action) {
            case 'change-plan':
                return $this->changePlan($userId, $data);
                
            case 'update-payment-method':
                return $this->updatePaymentMethod($userId, $data);
                
            default:
                throw new Exception('Action non valide', 400);
        }
    }
    
    /**
     * DELETE - Annuler abonnement
     */
    private function handleDelete() 
    {
        $userId = $this->authenticateUser();
        
        switch ($this->action) {
            case 'subscription':
                return $this->cancelSubscription($userId);
                
            default:
                throw new Exception('Action non valide', 400);
        }
    }
    
    /**
     * Créer session checkout Stripe
     */
    private function createCheckout(int $userId, array $data): array 
    {
        if (empty($data['plan_type'])) {
            throw new Exception('Type de plan requis', 400);
        }
        
        $validPlans = ['starter', 'business', 'enterprise'];
        if (!in_array($data['plan_type'], $validPlans)) {
            throw new Exception('Plan invalide', 400);
        }
        
        $user = $this->user->findById($userId);
        if (!$user) {
            throw new Exception('Utilisateur non trouvé', 404);
        }
        
        // Vérifier si l'utilisateur n'a pas déjà ce plan
        if ($user['plan_type'] === $data['plan_type'] && $user['plan_status'] === 'active') {
            throw new Exception('Vous avez déjà ce plan actif', 400);
        }
        
        try {
            $session = $this->stripe->createCheckoutSession(
                $userId,
                $data['plan_type'],
                $data['success_url'] ?? null,
                $data['cancel_url'] ?? null
            );
            
            // Logger l'intention d'achat
            $this->logActivity($userId, 'checkout_created', [
                'plan_type' => $data['plan_type'],
                'session_id' => $session['session_id']
            ]);
            
            return $this->sendSuccess([
                'checkout_url' => $session['url'],
                'session_id' => $session['session_id']
            ]);
            
        } catch (Exception $e) {
            throw new Exception('Erreur lors de la création du checkout: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Gérer les webhooks Stripe
     */
    private function handleWebhook(): array 
    {
        try {
            $this->stripe->handleWebhook();
            return $this->sendSuccess(['message' => 'Webhook traité']);
        } catch (Exception $e) {
            throw new Exception('Erreur webhook: ' . $e->getMessage(), 400);
        }
    }
    
    /**
     * Créer session portail client Stripe
     */
    private function createPortalSession(int $userId): array 
    {
        $user = $this->user->findById($userId);
        if (!$user || empty($user['stripe_customer_id'])) {
            throw new Exception('Client Stripe non trouvé', 404);
        }
        
        try {
            $session = \Stripe\BillingPortal\Session::create([
                'customer' => $user['stripe_customer_id'],
                'return_url' => BASE_URL . '/dashboard',
            ]);
            
            return $this->sendSuccess([
                'portal_url' => $session->url
            ]);
            
        } catch (Exception $e) {
            throw new Exception('Erreur lors de la création du portail: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Obtenir les plans disponibles
     */
    private function getPlans(): array 
    {
        $plans = [
            'free' => [
                'name' => 'Gratuit',
                'price' => 0,
                'currency' => 'EUR',
                'interval' => 'month',
                'features' => [
                    '10 recherches par jour',
                    '2 téléchargements PDF par mois',
                    'Support communautaire',
                    'Données de base'
                ],
                'limits' => [
                    'recherches_jour' => 10,
                    'documents_mois' => 2,
                    'surveillance' => 0,
                    'api_calls' => 50
                ]
            ],
            'starter' => [
                'name' => 'Starter',
                'price' => 29,
                'currency' => 'EUR',
                'interval' => 'month',
                'popular' => true,
                'features' => [
                    '100 recherches par jour',
                    '25 téléchargements PDF par mois',
                    '5 surveillances d\'entreprises',
                    '1,000 appels API par mois',
                    'Support email',
                    'Export sans watermark'
                ],
                'limits' => [
                    'recherches_jour' => 100,
                    'documents_mois' => 25,
                    'surveillance' => 5,
                    'api_calls' => 1000
                ]
            ],
            'business' => [
                'name' => 'Business',
                'price' => 99,
                'currency' => 'EUR',
                'interval' => 'month',
                'features' => [
                    '1,000 recherches par jour',
                    '200 téléchargements PDF par mois',
                    '50 surveillances d\'entreprises',
                    '10,000 appels API par mois',
                    'Support prioritaire + chat',
                    'Webhooks personnalisés',
                    'Export Excel/CSV',
                    'Rapports avancés'
                ],
                'limits' => [
                    'recherches_jour' => 1000,
                    'documents_mois' => 200,
                    'surveillance' => 50,
                    'api_calls' => 10000
                ]
            ],
            'enterprise' => [
                'name' => 'Enterprise',
                'price' => 299,
                'currency' => 'EUR',
                'interval' => 'month',
                'features' => [
                    'Recherches illimitées',
                    'Téléchargements illimités',
                    '500 surveillances d\'entreprises',
                    '100,000 appels API par mois',
                    'Support dédié + téléphone',
                    'SLA 99.9%',
                    'Intégration sur mesure',
                    'White labeling',
                    'Formation personnalisée'
                ],
                'limits' => [
                    'recherches_jour' => 10000,
                    'documents_mois' => 2000,
                    'surveillance' => 500,
                    'api_calls' => 100000
                ]
            ]
        ];
        
        return $this->sendSuccess(['plans' => $plans]);
    }
    
    /**
     * Obtenir l'abonnement actuel
     */
    private function getSubscription(int $userId): array 
    {
        $user = $this->user->findById($userId);
        if (!$user) {
            throw new Exception('Utilisateur non trouvé', 404);
        }
        
        $subscription = [
            'plan_type' => $user['plan_type'],
            'plan_status' => $user['plan_status'],
            'trial_ends_at' => $user['trial_ends_at'],
            'plan_expires_at' => $user['plan_expires_at'],
            'stripe_subscription_id' => $user['stripe_subscription_id'],
            'is_trial' => $user['plan_status'] === 'trial',
            'is_active' => in_array($user['plan_status'], ['trial', 'active']),
            'days_remaining' => null
        ];
        
        // Calculer les jours restants
        if ($user['trial_ends_at'] && $user['plan_status'] === 'trial') {
            $trialEnd = new DateTime($user['trial_ends_at']);
            $now = new DateTime();
            $subscription['days_remaining'] = max(0, $trialEnd->diff($now)->days);
        } elseif ($user['plan_expires_at'] && $user['plan_status'] === 'active') {
            $planEnd = new DateTime($user['plan_expires_at']);
            $now = new DateTime();
            $subscription['days_remaining'] = max(0, $planEnd->diff($now)->days);
        }
        
        // Prochaine facture (si Stripe)
        if (!empty($user['stripe_subscription_id'])) {
            try {
                $stripeSubscription = \Stripe\Subscription::retrieve($user['stripe_subscription_id']);
                $subscription['next_payment'] = [
                    'amount' => $stripeSubscription->items->data[0]->price->unit_amount / 100,
                    'currency' => strtoupper($stripeSubscription->items->data[0]->price->currency),
                    'date' => date('Y-m-d', $stripeSubscription->current_period_end)
                ];
            } catch (Exception $e) {
                // Ignore les erreurs Stripe pour l'instant
            }
        }
        
        return $this->sendSuccess(['subscription' => $subscription]);
    }
    
    /**
     * Obtenir l'historique des factures
     */
    private function getInvoices(int $userId): array 
    {
        $invoices = $this->stripe->getPaymentHistory($userId);
        
        return $this->sendSuccess([
            'invoices' => array_map(function($invoice) {
                return [
                    'id' => $invoice['id'],
                    'type' => $invoice['transaction_type'],
                    'amount' => $invoice['amount'] ?? 0,
                    'currency' => $invoice['currency'] ?? 'EUR',
                    'date' => $invoice['created_at'],
                    'status' => $this->getInvoiceStatus($invoice['transaction_type'])
                ];
            }, $invoices)
        ]);
    }
    
    /**
     * Changer de plan
     */
    private function changePlan(int $userId, array $data): array 
    {
        if (empty($data['new_plan_type'])) {
            throw new Exception('Nouveau plan requis', 400);
        }
        
        $validPlans = ['starter', 'business', 'enterprise'];
        if (!in_array($data['new_plan_type'], $validPlans)) {
            throw new Exception('Plan invalide', 400);
        }
        
        $user = $this->user->findById($userId);
        if (!$user) {
            throw new Exception('Utilisateur non trouvé', 404);
        }
        
        // Si pas encore d'abonnement Stripe, créer checkout
        if (empty($user['stripe_subscription_id'])) {
            return $this->createCheckout($userId, ['plan_type' => $data['new_plan_type']]);
        }
        
        // Sinon, modifier l'abonnement existant
        try {
            $success = $this->stripe->changeSubscription($userId, $data['new_plan_type']);
            
            if ($success) {
                $this->logActivity($userId, 'plan_changed', [
                    'old_plan' => $user['plan_type'],
                    'new_plan' => $data['new_plan_type']
                ]);
                
                return $this->sendSuccess([
                    'message' => 'Plan modifié avec succès',
                    'new_plan' => $data['new_plan_type']
                ]);
            } else {
                throw new Exception('Erreur lors de la modification du plan', 500);
            }
            
        } catch (Exception $e) {
            throw new Exception('Erreur lors de la modification: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Annuler l'abonnement
     */
    private function cancelSubscription(int $userId): array 
    {
        $user = $this->user->findById($userId);
        if (!$user) {
            throw new Exception('Utilisateur non trouvé', 404);
        }
        
        if (empty($user['stripe_subscription_id'])) {
            throw new Exception('Aucun abonnement à annuler', 400);
        }
        
        try {
            $success = $this->stripe->cancelSubscription($userId);
            
            if ($success) {
                $this->logActivity($userId, 'subscription_cancelled', [
                    'plan_type' => $user['plan_type']
                ]);
                
                return $this->sendSuccess([
                    'message' => 'Abonnement annulé. Il restera actif jusqu\'à la fin de la période de facturation.'
                ]);
            } else {
                throw new Exception('Erreur lors de l\'annulation', 500);
            }
            
        } catch (Exception $e) {
            throw new Exception('Erreur lors de l\'annulation: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Obtenir la facturation basée sur l'usage
     */
    private function getUsageBilling(int $userId): array 
    {
        $db = Database::getInstance();
        $user = $this->user->findById($userId);
        
        // Usage du mois en cours
        $currentUsage = $db->query("
            SELECT 
                SUM(recherches) as total_recherches,
                SUM(documents) as total_documents,
                SUM(api_calls) as total_api_calls
            FROM user_usage 
            WHERE user_id = ? AND YEAR(date_usage) = YEAR(CURDATE()) AND MONTH(date_usage) = MONTH(CURDATE())
        ", [$userId])->fetch();
        
        // Calcul des dépassements
        $planLimits = $this->user->getPlanQuotas($user['plan_type']);
        $monthlyLimits = [
            'recherches' => $planLimits['recherches'] * 30, // approximation mensuelle
            'documents' => $planLimits['documents'],
            'api_calls' => $planLimits['api_calls']
        ];
        
        $overages = [
            'recherches' => max(0, ($currentUsage['total_recherches'] ?? 0) - $monthlyLimits['recherches']),
            'documents' => max(0, ($currentUsage['total_documents'] ?? 0) - $monthlyLimits['documents']),
            'api_calls' => max(0, ($currentUsage['total_api_calls'] ?? 0) - $monthlyLimits['api_calls'])
        ];
        
        // Coûts des dépassements
        $overageCosts = [
            'recherches' => $overages['recherches'] * 0.01, // 1 centime par recherche
            'documents' => $overages['documents'] * 0.50,   // 50 centimes par document
            'api_calls' => $overages['api_calls'] * 0.001   // 0.1 centime par appel API
        ];
        
        $totalOverageCost = array_sum($overageCosts);
        
        return $this->sendSuccess([
            'current_usage' => $currentUsage,
            'monthly_limits' => $monthlyLimits,
            'overages' => $overages,
            'overage_costs' => $overageCosts,
            'total_overage_cost' => $totalOverageCost,
            'currency' => 'EUR'
        ]);
    }
    
    /**
     * Obtenir le statut d'une facture
     */
    private function getInvoiceStatus(string $transactionType): string 
    {
        switch ($transactionType) {
            case 'subscription_activated':
            case 'payment_succeeded':
                return 'paid';
            case 'payment_failed':
                return 'failed';
            case 'subscription_canceled':
                return 'cancelled';
            default:
                return 'pending';
        }
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
     * Logger une activité
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
$api = new BillingAPI();
$api->handleRequest();