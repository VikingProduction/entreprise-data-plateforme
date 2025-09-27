<?php
/**
 * API Surveillance des entreprises
 * Gestion des alertes, snapshots et notifications
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
require_once __DIR__ . '/../surveillance/SurveillanceSystem.php';

class SurveillanceAPI 
{
    private $user;
    private $surveillance;
    private $method;
    private $action;
    
    public function __construct() 
    {
        $this->user = new User();
        $this->surveillance = new SurveillanceSystem();
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
     * POST - Créer surveillance, test webhook
     */
    private function handlePost() 
    {
        $userId = $this->authenticateUser();
        $data = json_decode(file_get_contents('php://input'), true);
        
        switch ($this->action) {
            case 'create':
                return $this->createSurveillance($userId, $data);
            case 'test-webhook':
                return $this->testWebhook($userId, $data);
            case 'manual-check':
                return $this->manualCheck($userId, $data);
            default:
                return $this->createSurveillance($userId, $data);
        }
    }
    
    /**
     * GET - Liste, détails, changements, types
     */
    private function handleGet() 
    {
        $userId = $this->authenticateUser();
        
        switch ($this->action) {
            case 'list':
                return $this->getSurveillances($userId);
            case 'details':
                return $this->getSurveillanceDetails($userId, $_GET['id'] ?? null);
            case 'changes':
                return $this->getChanges($userId, $_GET['id'] ?? null);
            case 'types':
                return $this->getSurveillanceTypes();
            case 'stats':
                return $this->getSurveillanceStats($userId);
            default:
                return $this->getSurveillances($userId);
        }
    }
    
    /**
     * PUT - Modifier surveillance
     */
    private function handlePut() 
    {
        $userId = $this->authenticateUser();
        $data = json_decode(file_get_contents('php://input'), true);
        $surveillanceId = $_GET['id'] ?? null;
        
        if (!$surveillanceId) {
            throw new Exception('ID de surveillance requis', 400);
        }
        
        switch ($this->action) {
            case 'update':
                return $this->updateSurveillance($userId, $surveillanceId, $data);
            case 'toggle':
                return $this->toggleSurveillance($userId, $surveillanceId);
            default:
                return $this->updateSurveillance($userId, $surveillanceId, $data);
        }
    }
    
    /**
     * DELETE - Supprimer surveillance
     */
    private function handleDelete() 
    {
        $userId = $this->authenticateUser();
        $surveillanceId = $_GET['id'] ?? null;
        
        if (!$surveillanceId) {
            throw new Exception('ID de surveillance requis', 400);
        }
        
        return $this->deleteSurveillance($userId, $surveillanceId);
    }
    
    /**
     * Créer une nouvelle surveillance
     */
    private function createSurveillance(int $userId, array $data): array 
    {
        // Validation des données
        if (empty($data['siren'])) {
            throw new Exception('SIREN requis', 400);
        }
        
        if (!preg_match('/^[0-9]{9}$/', $data['siren'])) {
            throw new Exception('SIREN invalide (9 chiffres requis)', 400);
        }
        
        // Vérifier les quotas
        if (!$this->user->checkQuota($userId, 'surveillance')) {
            $user = $this->user->findById($userId);
            throw new Exception("Quota de surveillance atteint pour votre plan {$user['plan_type']}. Upgradez pour surveiller plus d'entreprises.", 402);
        }
        
        // Vérifier si l'entreprise existe
        $db = Database::getInstance();
        $company = $db->query(
            "SELECT id, siren, denomination FROM companies WHERE siren = ?",
            [$data['siren']]
        )->fetch();
        
        if (!$company) {
            throw new Exception('Entreprise non trouvée. Effectuez d\'abord une recherche pour l\'ajouter à notre base.', 404);
        }
        
        // Vérifier si surveillance n'existe pas déjà
        $existingSurveillance = $db->query(
            "SELECT id FROM surveillances WHERE user_id = ? AND siren = ? AND active = 1",
            [$userId, $data['siren']]
        )->fetch();
        
        if ($existingSurveillance) {
            throw new Exception('Vous surveillez déjà cette entreprise', 409);
        }
        
        // Configuration par défaut
        $config = [
            'siren' => $data['siren'],
            'denomination' => $company['denomination'],
            'type' => $data['type'] ?? 'complete',
            'criteres' => $data['criteres'] ?? [],
            'frequence' => $data['frequence'] ?? 'daily',
            'alertes_email' => $data['alertes_email'] ?? true,
            'webhook_url' => $data['webhook_url'] ?? null
        ];
        
        // Validation du type de surveillance
        $validTypes = ['complete', 'dirigeants', 'financier', 'juridique', 'custom'];
        if (!in_array($config['type'], $validTypes)) {
            throw new Exception('Type de surveillance invalide', 400);
        }
        
        // Validation de la fréquence
        $validFrequencies = ['hourly', 'daily', 'weekly'];
        if (!in_array($config['frequence'], $validFrequencies)) {
            throw new Exception('Fréquence invalide', 400);
        }
        
        // Validation du webhook (si fourni)
        if (!empty($config['webhook_url']) && !filter_var($config['webhook_url'], FILTER_VALIDATE_URL)) {
            throw new Exception('URL webhook invalide', 400);
        }
        
        try {
            $surveillanceId = $this->surveillance->createSurveillance($userId, $config);
            
            // Log de l'activité
            $this->logActivity($userId, 'surveillance_created', [
                'surveillance_id' => $surveillanceId,
                'siren' => $data['siren'],
                'type' => $config['type'],
                'denomination' => $company['denomination']
            ]);
            
            // Incrémenter le quota utilisé
            $this->user->incrementUsage($userId, 'surveillance');
            
            return $this->sendSuccess([
                'message' => 'Surveillance créée avec succès',
                'surveillance_id' => $surveillanceId,
                'siren' => $data['siren'],
                'denomination' => $company['denomination']
            ]);
            
        } catch (Exception $e) {
            throw new Exception('Erreur lors de la création de la surveillance: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Obtenir la liste des surveillances
     */
    private function getSurveillances(int $userId): array 
    {
        $surveillances = $this->surveillance->getUserSurveillances($userId);
        
        // Enrichir avec les dernières alertes
        foreach ($surveillances as &$surveillance) {
            $surveillance['last_changes'] = $this->getLastChanges($surveillance['id'], 5);
            $surveillance['health_score'] = $this->calculateHealthScore($surveillance);
        }
        
        return $this->sendSuccess([
            'surveillances' => $surveillances,
            'total_count' => count($surveillances)
        ]);
    }
    
    /**
     * Obtenir les détails d'une surveillance
     */
    private function getSurveillanceDetails(int $userId, ?string $surveillanceId): array 
    {
        if (!$surveillanceId) {
            throw new Exception('ID de surveillance requis', 400);
        }
        
        $db = Database::getInstance();
        
        // Vérifier que la surveillance appartient à l'utilisateur
        $surveillance = $db->query("
            SELECT s.*, c.denomination, c.forme_juridique, c.ville, c.statut
            FROM surveillances s
            JOIN companies c ON s.siren = c.siren
            WHERE s.id = ? AND s.user_id = ?
        ", [$surveillanceId, $userId])->fetch();
        
        if (!$surveillance) {
            throw new Exception('Surveillance non trouvée', 404);
        }
        
        // Statistiques des changements
        $changeStats = $db->query("
            SELECT 
                type_changement,
                importance,
                COUNT(*) as count,
                MAX(detected_at) as last_occurrence
            FROM surveillance_changes 
            WHERE surveillance_id = ?
            GROUP BY type_changement, importance
        ", [$surveillanceId])->fetchAll();
        
        // Derniers snapshots
        $snapshots = $db->query("
            SELECT id, created_at
            FROM surveillance_snapshots 
            WHERE surveillance_id = ?
            ORDER BY created_at DESC
            LIMIT 10
        ", [$surveillanceId])->fetchAll();
        
        // Derniers changements
        $recentChanges = $this->getLastChanges($surveillanceId, 20);
        
        $surveillance['criteres_surveillance'] = json_decode($surveillance['criteres_surveillance'], true);
        
        return $this->sendSuccess([
            'surveillance' => $surveillance,
            'change_stats' => $changeStats,
            'snapshots' => $snapshots,
            'recent_changes' => $recentChanges
        ]);
    }
    
    /**
     * Obtenir les changements d'une surveillance
     */
    private function getChanges(int $userId, ?string $surveillanceId): array 
    {
        if (!$surveillanceId) {
            throw new Exception('ID de surveillance requis', 400);
        }
        
        // Vérifier propriété
        $this->verifySurveillanceOwnership($userId, $surveillanceId);
        
        $page = max(1, intval($_GET['page'] ?? 1));
        $limit = min(100, max(10, intval($_GET['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;
        
        $db = Database::getInstance();
        
        // Filtres optionnels
        $whereClause = "surveillance_id = ?";
        $params = [$surveillanceId];
        
        if (!empty($_GET['type'])) {
            $whereClause .= " AND type_changement = ?";
            $params[] = $_GET['type'];
        }
        
        if (!empty($_GET['importance'])) {
            $whereClause .= " AND importance = ?";
            $params[] = $_GET['importance'];
        }
        
        if (!empty($_GET['date_from'])) {
            $whereClause .= " AND detected_at >= ?";
            $params[] = $_GET['date_from'];
        }
        
        if (!empty($_GET['date_to'])) {
            $whereClause .= " AND detected_at <= ?";
            $params[] = $_GET['date_to'];
        }
        
        // Compter le total
        $totalCount = $db->query("
            SELECT COUNT(*) as count 
            FROM surveillance_changes 
            WHERE {$whereClause}
        ", $params)->fetch()['count'];
        
        // Récupérer les changements
        $params[] = $limit;
        $params[] = $offset;
        
        $changes = $db->query("
            SELECT 
                id, type_changement, field_changed, old_value, new_value,
                importance, detected_at, notified, notification_sent_at
            FROM surveillance_changes 
            WHERE {$whereClause}
            ORDER BY detected_at DESC, importance DESC
            LIMIT ? OFFSET ?
        ", $params)->fetchAll();
        
        // Décoder les valeurs JSON
        foreach ($changes as &$change) {
            $change['old_value'] = json_decode($change['old_value'], true);
            $change['new_value'] = json_decode($change['new_value'], true);
            $change['formatted_description'] = $this->formatChangeDescription($change);
        }
        
        return $this->sendSuccess([
            'changes' => $changes,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $limit,
                'total' => $totalCount,
                'total_pages' => ceil($totalCount / $limit)
            ]
        ]);
    }
    
    /**
     * Obtenir les types de surveillance disponibles
     */
    private function getSurveillanceTypes(): array 
    {
        $types = $this->surveillance->getSurveillanceTypes();
        
        return $this->sendSuccess(['types' => $types]);
    }
    
    /**
     * Obtenir les statistiques de surveillance
     */
    private function getSurveillanceStats(int $userId): array 
    {
        $db = Database::getInstance();
        
        // Stats générales
        $generalStats = $db->query("
            SELECT 
                COUNT(*) as total_surveillances,
                COUNT(CASE WHEN active = 1 THEN 1 END) as active_surveillances,
                COUNT(CASE WHEN derniere_verification > DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 END) as recently_checked
            FROM surveillances 
            WHERE user_id = ?
        ", [$userId])->fetch();
        
        // Changements par importance (30 derniers jours)
        $changesByImportance = $db->query("
            SELECT 
                sc.importance,
                COUNT(*) as count
            FROM surveillance_changes sc
            JOIN surveillances s ON sc.surveillance_id = s.id
            WHERE s.user_id = ? AND sc.detected_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY sc.importance
        ", [$userId])->fetchAll();
        
        // Top des types de changements
        $topChangeTypes = $db->query("
            SELECT 
                sc.type_changement,
                COUNT(*) as count
            FROM surveillance_changes sc
            JOIN surveillances s ON sc.surveillance_id = s.id
            WHERE s.user_id = ? AND sc.detected_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY sc.type_changement
            ORDER BY count DESC
            LIMIT 10
        ", [$userId])->fetchAll();
        
        // Activité par jour (7 derniers jours)
        $dailyActivity = $db->query("
            SELECT 
                DATE(sc.detected_at) as date,
                COUNT(*) as changes_count
            FROM surveillance_changes sc
            JOIN surveillances s ON sc.surveillance_id = s.id
            WHERE s.user_id = ? AND sc.detected_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY DATE(sc.detected_at)
            ORDER BY date ASC
        ", [$userId])->fetchAll();
        
        return $this->sendSuccess([
            'general_stats' => $generalStats,
            'changes_by_importance' => $changesByImportance,
            'top_change_types' => $topChangeTypes,
            'daily_activity' => $dailyActivity
        ]);
    }
    
    /**
     * Modifier une surveillance
     */
    private function updateSurveillance(int $userId, string $surveillanceId, array $data): array 
    {
        // Vérifier propriété
        $this->verifySurveillanceOwnership($userId, $surveillanceId);
        
        $allowedFields = [
            'type_surveillance', 'criteres_surveillance', 'frequence_verification',
            'alertes_email', 'alertes_webhook'
        ];
        
        $updateFields = [];
        $params = [];
        
        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $updateFields[] = "$field = ?";
                $value = $data[$field];
                
                // Encoder JSON si nécessaire
                if ($field === 'criteres_surveillance' && is_array($value)) {
                    $value = json_encode($value);
                }
                
                $params[] = $value;
            }
        }
        
        if (empty($updateFields)) {
            throw new Exception('Aucune donnée à mettre à jour', 400);
        }
        
        $params[] = $surveillanceId;
        
        $db = Database::getInstance();
        $db->query("
            UPDATE surveillances 
            SET " . implode(', ', $updateFields) . ", updated_at = NOW()
            WHERE id = ?
        ", $params);
        
        $this->logActivity($userId, 'surveillance_updated', [
            'surveillance_id' => $surveillanceId,
            'updated_fields' => array_keys($data)
        ]);
        
        return $this->sendSuccess(['message' => 'Surveillance mise à jour avec succès']);
    }
    
    /**
     * Activer/désactiver une surveillance
     */
    private function toggleSurveillance(int $userId, string $surveillanceId): array 
    {
        // Vérifier propriété
        $surveillance = $this->verifySurveillanceOwnership($userId, $surveillanceId);
        
        $newStatus = $surveillance['active'] ? 0 : 1;
        
        $db = Database::getInstance();
        $db->query("
            UPDATE surveillances 
            SET active = ?, updated_at = NOW()
            WHERE id = ?
        ", [$newStatus, $surveillanceId]);
        
        $this->logActivity($userId, 'surveillance_toggled', [
            'surveillance_id' => $surveillanceId,
            'new_status' => $newStatus ? 'active' : 'inactive'
        ]);
        
        return $this->sendSuccess([
            'message' => 'Statut de surveillance modifié',
            'active' => (bool)$newStatus
        ]);
    }
    
    /**
     * Supprimer une surveillance
     */
    private function deleteSurveillance(int $userId, string $surveillanceId): array 
    {
        // Vérifier propriété
        $surveillance = $this->verifySurveillanceOwnership($userId, $surveillanceId);
        
        $success = $this->surveillance->deleteSurveillance($userId, $surveillanceId);
        
        if ($success) {
            $this->logActivity($userId, 'surveillance_deleted', [
                'surveillance_id' => $surveillanceId,
                'siren' => $surveillance['siren'],
                'denomination' => $surveillance['denomination']
            ]);
            
            return $this->sendSuccess(['message' => 'Surveillance supprimée avec succès']);
        } else {
            throw new Exception('Erreur lors de la suppression', 500);
        }
    }
    
    /**
     * Tester un webhook
     */
    private function testWebhook(int $userId, array $data): array 
    {
        if (empty($data['webhook_url'])) {
            throw new Exception('URL webhook requise', 400);
        }
        
        if (!filter_var($data['webhook_url'], FILTER_VALIDATE_URL)) {
            throw new Exception('URL webhook invalide', 400);
        }
        
        // Payload de test
        $testPayload = [
            'event' => 'webhook_test',
            'surveillance_id' => 'test',
            'siren' => '123456789',
            'denomination' => 'Entreprise de test',
            'message' => 'Ceci est un test de webhook',
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        // Envoyer le webhook
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $data['webhook_url'],
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($testPayload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'X-Webhook-Test: 1'
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        $success = ($httpCode >= 200 && $httpCode < 300);
        
        $this->logActivity($userId, 'webhook_tested', [
            'webhook_url' => $data['webhook_url'],
            'http_code' => $httpCode,
            'success' => $success
        ]);
        
        return $this->sendSuccess([
            'success' => $success,
            'http_code' => $httpCode,
            'response' => $response,
            'error' => $error ?: null
        ]);
    }
    
    /**
     * Vérification manuelle d'une surveillance
     */
    private function manualCheck(int $userId, array $data): array 
    {
        if (empty($data['surveillance_id'])) {
            throw new Exception('ID de surveillance requis', 400);
        }
        
        // Vérifier propriété
        $surveillance = $this->verifySurveillanceOwnership($userId, $data['surveillance_id']);
        
        try {
            $changes = $this->surveillance->detectChanges(
                $data['surveillance_id'],
                $surveillance['siren'],
                json_decode($surveillance['criteres_surveillance'], true)
            );
            
            if (!empty($changes)) {
                $this->surveillance->saveDetectedChanges($data['surveillance_id'], $changes);
                $this->surveillance->sendAlerts($surveillance, $changes);
                
                // Créer nouveau snapshot
                $this->surveillance->createSnapshot($data['surveillance_id'], $surveillance['siren']);
            }
            
            // Mettre à jour la dernière vérification
            $db = Database::getInstance();
            $db->query("
                UPDATE surveillances 
                SET derniere_verification = NOW()
                WHERE id = ?
            ", [$data['surveillance_id']]);
            
            $this->logActivity($userId, 'manual_check_performed', [
                'surveillance_id' => $data['surveillance_id'],
                'changes_found' => count($changes)
            ]);
            
            return $this->sendSuccess([
                'message' => 'Vérification effectuée',
                'changes_found' => count($changes),
                'changes' => array_map(function($change) {
                    return $this->formatChangeDescription($change);
                }, $changes)
            ]);
            
        } catch (Exception $e) {
            throw new Exception('Erreur lors de la vérification: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Vérifier que l'utilisateur possède la surveillance
     */
    private function verifySurveillanceOwnership(int $userId, string $surveillanceId): array 
    {
        $db = Database::getInstance();
        $surveillance = $db->query("
            SELECT * FROM surveillances 
            WHERE id = ? AND user_id = ?
        ", [$surveillanceId, $userId])->fetch();
        
        if (!$surveillance) {
            throw new Exception('Surveillance non trouvée', 404);
        }
        
        return $surveillance;
    }
    
    /**
     * Obtenir les derniers changements
     */
    private function getLastChanges(string $surveillanceId, int $limit = 10): array 
    {
        $db = Database::getInstance();
        return $db->query("
            SELECT type_changement, importance, detected_at, old_value, new_value
            FROM surveillance_changes 
            WHERE surveillance_id = ?
            ORDER BY detected_at DESC
            LIMIT ?
        ", [$surveillanceId, $limit])->fetchAll();
    }
    
    /**
     * Calculer un score de santé pour la surveillance
     */
    private function calculateHealthScore(array $surveillance): int 
    {
        $score = 100;
        
        // Pénalité si pas vérifiée récemment
        if ($surveillance['derniere_verification']) {
            $lastCheck = new DateTime($surveillance['derniere_verification']);
            $now = new DateTime();
            $hoursSinceCheck = $now->diff($lastCheck)->h + ($now->diff($lastCheck)->days * 24);
            
            if ($hoursSinceCheck > 48) $score -= 30;
            elseif ($hoursSinceCheck > 24) $score -= 15;
        } else {
            $score -= 50; // Jamais vérifiée
        }
        
        // Bonus si active
        if (!$surveillance['active']) $score -= 40;
        
        // Ajustement selon les changements récents
        if ($surveillance['changements_30j'] > 10) $score += 10; // Beaucoup de changements = entreprise active
        
        return max(0, min(100, $score));
    }
    
    /**
     * Formater la description d'un changement
     */
    private function formatChangeDescription(array $change): string 
    {
        switch ($change['type_changement']) {
            case 'denomination_changed':
                return "Dénomination modifiée : {$change['old_value']} → {$change['new_value']}";
            case 'dirigeant_added':
                $dirigeant = $change['new_value'];
                return "Nouveau dirigeant : {$dirigeant['prenom']} {$dirigeant['nom']} ({$dirigeant['fonction']})";
            case 'dirigeant_removed':
                $dirigeant = $change['old_value'];
                return "Dirigeant parti : {$dirigeant['prenom']} {$dirigeant['nom']}";
            case 'capital_changed':
                return "Capital social : {$change['old_value']}€ → {$change['new_value']}€";
            case 'document_added':
                $doc = $change['new_value'];
                return "Nouveau document : {$doc['type_document']} ({$doc['date_document']})";
            case 'jugement_added':
                $jugement = $change['new_value'];
                return "⚠️ NOUVEAU JUGEMENT : {$jugement['type_jugement']} - {$jugement['tribunal']}";
            default:
                return "Changement détecté : {$change['field_changed']}";
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
$api = new SurveillanceAPI();
$api->handleRequest();