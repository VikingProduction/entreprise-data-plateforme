<?php
/**
 * Intégration Stripe pour les abonnements SaaS
 * Gestion des paiements automatiques et webhooks
 */

require_once __DIR__ . '/../vendor/autoload.php'; // Stripe SDK
require_once __DIR__ . '/User.php';

class StripeIntegration 
{
    private $stripeSecretKey;
    private $stripeWebhookSecret;
    private $user;

    public function __construct() 
    {
        $this->stripeSecretKey = getenv('STRIPE_SECRET_KEY');
        $this->stripeWebhookSecret = getenv('STRIPE_WEBHOOK_SECRET');
        $this->user = new User();

        \Stripe\Stripe::setApiKey($this->stripeSecretKey);
    }

    /**
     * Plans de tarification Stripe
     */
    private function getStripePlans(): array 
    {
        return [
            'starter' => [
                'price_id' => 'price_starter_monthly', // ID depuis Stripe Dashboard
                'amount' => 2900, // 29€
                'currency' => 'eur',
                'interval' => 'month',
                'name' => 'Starter',
                'description' => '100 recherches/jour + surveillance'
            ],
            'business' => [
                'price_id' => 'price_business_monthly',
                'amount' => 9900, // 99€
                'currency' => 'eur',
                'interval' => 'month',
                'name' => 'Business',
                'description' => '1000 recherches/jour + API'
            ],
            'enterprise' => [
                'price_id' => 'price_enterprise_monthly',
                'amount' => 29900, // 299€
                'currency' => 'eur',
                'interval' => 'month',
                'name' => 'Enterprise',
                'description' => 'Illimité + support dédié'
            ]
        ];
    }

    /**
     * Créer un client Stripe
     */
    public function createCustomer(int $userId, array $customerData): string 
    {
        $customer = \Stripe\Customer::create([
            'email' => $customerData['email'],
            'name' => ($customerData['prenom'] ?? '') . ' ' . ($customerData['nom'] ?? ''),
            'metadata' => [
                'user_id' => $userId,
                'entreprise' => $customerData['entreprise'] ?? ''
            ]
        ]);

        // Sauvegarder l'ID client Stripe
        $db = Database::getInstance();
        $db->query(
            "UPDATE users SET stripe_customer_id = ? WHERE id = ?",
            [$customer->id, $userId]
        );

        return $customer->id;
    }

    /**
     * Créer une session de checkout
     */
    public function createCheckoutSession(int $userId, string $planType, ?string $successUrl = null, ?string $cancelUrl = null): array 
    {
        $user = $this->user->findById($userId);
        if (!$user) {
            throw new Exception("Utilisateur non trouvé");
        }

        $plans = $this->getStripePlans();
        if (!isset($plans[$planType])) {
            throw new Exception("Plan non valide");
        }

        $plan = $plans[$planType];

        // Créer client Stripe si nécessaire
        if (empty($user['stripe_customer_id'])) {
            $customerId = $this->createCustomer($userId, $user);
        } else {
            $customerId = $user['stripe_customer_id'];
        }

        $session = \Stripe\Checkout\Session::create([
            'customer' => $customerId,
            'payment_method_types' => ['card', 'sepa_debit'],
            'line_items' => [[
                'price' => $plan['price_id'],
                'quantity' => 1,
            ]],
            'mode' => 'subscription',
            'success_url' => $successUrl ?: (BASE_URL . '/dashboard?session_id={CHECKOUT_SESSION_ID}'),
            'cancel_url' => $cancelUrl ?: (BASE_URL . '/pricing'),
            'metadata' => [
                'user_id' => $userId,
                'plan_type' => $planType
            ],
            'subscription_data' => [
                'metadata' => [
                    'user_id' => $userId,
                    'plan_type' => $planType
                ]
            ],
            'customer_update' => [
                'address' => 'auto'
            ],
            'tax_id_collection' => [
                'enabled' => true
            ],
            'automatic_tax' => [
                'enabled' => true
            ]
        ]);

        return [
            'session_id' => $session->id,
            'url' => $session->url
        ];
    }

    /**
     * Gérer les webhooks Stripe
     */
    public function handleWebhook(): void 
    {
        $payload = @file_get_contents('php://input');
        $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'];

        try {
            $event = \Stripe\Webhook::constructEvent(
                $payload, $sig_header, $this->stripeWebhookSecret
            );
        } catch(\UnexpectedValueException $e) {
            http_response_code(400);
            exit('Invalid payload');
        } catch(\Stripe\Exception\SignatureVerificationException $e) {
            http_response_code(400);
            exit('Invalid signature');
        }

        // Traitement des événements
        switch ($event['type']) {
            case 'checkout.session.completed':
                $this->handleCheckoutCompleted($event['data']['object']);
                break;

            case 'invoice.payment_succeeded':
                $this->handlePaymentSucceeded($event['data']['object']);
                break;

            case 'invoice.payment_failed':
                $this->handlePaymentFailed($event['data']['object']);
                break;

            case 'customer.subscription.updated':
                $this->handleSubscriptionUpdated($event['data']['object']);
                break;

            case 'customer.subscription.deleted':
                $this->handleSubscriptionCanceled($event['data']['object']);
                break;

            default:
                error_log('Webhook non géré: ' . $event['type']);
        }

        http_response_code(200);
    }

    /**
     * Traiter la finalisation du checkout
     */
    private function handleCheckoutCompleted($session): void 
    {
        $userId = (int)$session['metadata']['user_id'];
        $planType = $session['metadata']['plan_type'];

        $db = Database::getInstance();

        // Récupérer la subscription
        $subscription = \Stripe\Subscription::retrieve($session['subscription']);

        // Mettre à jour l'utilisateur
        $db->query("
            UPDATE users SET 
                plan_type = ?,
                plan_status = 'active',
                plan_expires_at = FROM_UNIXTIME(?),
                stripe_subscription_id = ?,
                updated_at = NOW()
            WHERE id = ?
        ", [
            $planType,
            $subscription->current_period_end,
            $subscription->id,
            $userId
        ]);

        // Mettre à jour les quotas
        $quotas = (new User())->getPlanQuotas($planType);
        $db->query("
            UPDATE users SET 
                quota_recherches_jour = ?,
                quota_documents_jour = ?,
                quota_surveillance = ?
            WHERE id = ?
        ", [
            $quotas['recherches'],
            $quotas['documents'],
            $quotas['surveillance'],
            $userId
        ]);

        // Log de transaction
        $this->logTransaction($userId, 'subscription_activated', [
            'plan_type' => $planType,
            'stripe_session_id' => $session['id'],
            'amount' => $session['amount_total'] / 100,
            'currency' => $session['currency']
        ]);

        // Email de confirmation
        $this->sendSubscriptionConfirmationEmail($userId, $planType);
    }

    /**
     * Traiter le paiement réussi (renouvellement)
     */
    private function handlePaymentSucceeded($invoice): void 
    {
        if ($invoice['billing_reason'] === 'subscription_cycle') {
            $subscription = \Stripe\Subscription::retrieve($invoice['subscription']);
            $userId = (int)$subscription->metadata['user_id'];

            $db = Database::getInstance();

            // Prolonger l'abonnement
            $db->query("
                UPDATE users SET 
                    plan_expires_at = FROM_UNIXTIME(?),
                    plan_status = 'active'
                WHERE id = ?
            ", [$subscription->current_period_end, $userId]);

            // Reset des quotas quotidiens si nouveau mois
            $this->resetMonthlyQuotas($userId);

            $this->logTransaction($userId, 'payment_succeeded', [
                'amount' => $invoice['amount_paid'] / 100,
                'currency' => $invoice['currency'],
                'invoice_id' => $invoice['id']
            ]);
        }
    }

    /**
     * Traiter l'échec de paiement
     */
    private function handlePaymentFailed($invoice): void 
    {
        $subscription = \Stripe\Subscription::retrieve($invoice['subscription']);
        $userId = (int)$subscription->metadata['user_id'];

        $db = Database::getInstance();
        $db->query("
            UPDATE users SET plan_status = 'past_due' WHERE id = ?
        ", [$userId]);

        $this->logTransaction($userId, 'payment_failed', [
            'amount' => $invoice['amount_due'] / 100,
            'currency' => $invoice['currency'],
            'attempt_count' => $invoice['attempt_count']
        ]);

        // Email d'alerte
        $this->sendPaymentFailedEmail($userId);
    }

    /**
     * Traiter l'annulation d'abonnement
     */
    private function handleSubscriptionCanceled($subscription): void 
    {
        $userId = (int)$subscription->metadata['user_id'];

        $db = Database::getInstance();
        $db->query("
            UPDATE users SET 
                plan_status = 'canceled',
                plan_type = 'free',
                stripe_subscription_id = NULL
            WHERE id = ?
        ", [$userId]);

        $this->logTransaction($userId, 'subscription_canceled', [
            'canceled_at' => date('Y-m-d H:i:s', $subscription->canceled_at),
            'cancellation_reason' => $subscription->cancellation_details->reason ?? 'user_request'
        ]);
    }

    /**
     * Annuler un abonnement
     */
    public function cancelSubscription(int $userId): bool 
    {
        $user = $this->user->findById($userId);
        if (empty($user['stripe_subscription_id'])) {
            return false;
        }

        try {
            \Stripe\Subscription::update($user['stripe_subscription_id'], [
                'cancel_at_period_end' => true
            ]);

            $db = Database::getInstance();
            $db->query("
                UPDATE users SET plan_status = 'canceling' WHERE id = ?
            ", [$userId]);

            return true;
        } catch (\Stripe\Exception\ApiErrorException $e) {
            error_log("Erreur annulation Stripe: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Logger les transactions
     */
    private function logTransaction(int $userId, string $type, array $data): void 
    {
        $db = Database::getInstance();
        $db->query("
            INSERT INTO payment_logs (user_id, transaction_type, data, created_at)
            VALUES (?, ?, ?, NOW())
        ", [$userId, $type, json_encode($data)]);
    }

    private function resetMonthlyQuotas(int $userId): void 
    {
        // Reset compteurs si début de mois
        if (date('d') === '01') {
            $db = Database::getInstance();
            $db->query("
                UPDATE users SET 
                    nb_recherches_ce_mois = 0,
                    nb_documents_ce_mois = 0 
                WHERE id = ?
            ", [$userId]);
        }
    }

    private function sendSubscriptionConfirmationEmail(int $userId, string $planType): void 
    {
        // TODO: Email service integration
        error_log("Send subscription confirmation for user $userId, plan $planType");
    }

    private function sendPaymentFailedEmail(int $userId): void 
    {
        // TODO: Email service integration
        error_log("Send payment failed notification for user $userId");
    }
}
