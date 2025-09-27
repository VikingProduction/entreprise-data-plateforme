<?php
/**
 * Système de surveillance automatique des entreprises
 * Détection des changements et alertes en temps réel
 */

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/User.php';

class SurveillanceSystem 
{
    private $db;
    private $user;

    public function __construct() 
    {
        $this->db = Database::getInstance();
        $this->user = new User();
    }

    /**
     * Créer une nouvelle surveillance
     */
    public function createSurveillance(int $userId, array $config): int 
    {
        // Vérifier les quotas
        if (!$this->user->checkQuota($userId, 'surveillance')) {
            throw new Exception("Quota de surveillance atteint pour votre plan");
        }

        $sql = "INSERT INTO surveillances (
            user_id, siren, denomination, type_surveillance,
            criteres_surveillance, frequence_verification,
            alertes_email, alertes_webhook, active, created_at
        ) VALUES (
            :user_id, :siren, :denomination, :type_surveillance,
            :criteres, :frequence, :email, :webhook, 1, NOW()
        )";

        $params = [
            'user_id' => $userId,
            'siren' => $config['siren'],
            'denomination' => $config['denomination'],
            'type_surveillance' => $config['type'] ?? 'complete',
            'criteres' => json_encode($config['criteres'] ?? []),
            'frequence' => $config['frequence'] ?? 'daily',
            'email' => $config['alertes_email'] ?? true,
            'webhook' => $config['webhook_url'] ?? null
        ];

        $this->db->query($sql, $params);
        $surveillanceId = (int)$this->db->lastInsertId();

        // Créer un snapshot initial
        $this->createSnapshot($surveillanceId, $config['siren']);

        return $surveillanceId;
    }

    /**
     * Types de surveillance disponibles
     */
    public function getSurveillanceTypes(): array 
    {
        return [
            'complete' => [
                'name' => 'Surveillance complète',
                'description' => 'Tous les changements de l'entreprise',
                'criteres' => [
                    'denomination', 'forme_juridique', 'capital_social',
                    'adresse', 'dirigeants', 'activite_principale',
                    'statut', 'documents', 'jugements'
                ]
            ],
            'dirigeants' => [
                'name' => 'Dirigeants uniquement',
                'description' => 'Changements dans l'équipe dirigeante',
                'criteres' => ['dirigeants']
            ],
            'financier' => [
                'name' => 'Surveillance financière',
                'description' => 'Bilans, résultats, capital',
                'criteres' => ['capital_social', 'documents', 'donnees_financieres']
            ],
            'juridique' => [
                'name' => 'Surveillance juridique',
                'description' => 'Jugements, procédures collectives',
                'criteres' => ['jugements', 'statut', 'procedures']
            ],
            'custom' => [
                'name' => 'Surveillance personnalisée',
                'description' => 'Critères définis par l'utilisateur',
                'criteres' => [] // Défini par l'utilisateur
            ]
        ];
    }

    /**
     * Créer un snapshot de l'état actuel
     */
    private function createSnapshot(int $surveillanceId, string $siren): void 
    {
        // Récupérer les données complètes de l'entreprise
        $company = new Company();
        $companyData = $company->getCompanyWithRelations($company->findBySiren($siren)['id']);

        if ($companyData) {
            $sql = "INSERT INTO surveillance_snapshots (
                surveillance_id, siren, snapshot_data, created_at
            ) VALUES (?, ?, ?, NOW())";

            $this->db->query($sql, [
                $surveillanceId,
                $siren,
                json_encode($companyData)
            ]);
        }
    }

    /**
     * Vérifier les changements pour toutes les surveillances actives
     */
    public function checkAllSurveillances(): array 
    {
        $results = [
            'checked' => 0,
            'changes_detected' => 0,
            'alerts_sent' => 0,
            'errors' => 0
        ];

        // Récupérer les surveillances à vérifier selon leur fréquence
        $surveillances = $this->getSurveillancesToCheck();

        foreach ($surveillances as $surveillance) {
            try {
                $results['checked']++;

                $changes = $this->detectChanges(
                    $surveillance['id'],
                    $surveillance['siren'],
                    json_decode($surveillance['criteres_surveillance'], true)
                );

                if (!empty($changes)) {
                    $results['changes_detected']++;

                    // Sauvegarder les changements détectés
                    $this->saveDetectedChanges($surveillance['id'], $changes);

                    // Envoyer les alertes
                    if ($this->sendAlerts($surveillance, $changes)) {
                        $results['alerts_sent']++;
                    }

                    // Créer un nouveau snapshot
                    $this->createSnapshot($surveillance['id'], $surveillance['siren']);
                }

                // Mettre à jour la dernière vérification
                $this->db->query(
                    "UPDATE surveillances SET derniere_verification = NOW() WHERE id = ?",
                    [$surveillance['id']]
                );

            } catch (Exception $e) {
                $results['errors']++;
                error_log("Erreur surveillance {$surveillance['id']}: " . $e->getMessage());
            }
        }

        return $results;
    }

    /**
     * Récupérer les surveillances à vérifier
     */
    private function getSurveillancesToCheck(): array 
    {
        $sql = "SELECT s.*, u.email, u.plan_type 
                FROM surveillances s
                JOIN users u ON s.user_id = u.id
                WHERE s.active = 1 
                  AND u.plan_status IN ('active', 'trial')
                  AND (
                    s.derniere_verification IS NULL
                    OR (s.frequence_verification = 'hourly' AND s.derniere_verification < DATE_SUB(NOW(), INTERVAL 1 HOUR))
                    OR (s.frequence_verification = 'daily' AND s.derniere_verification < DATE_SUB(NOW(), INTERVAL 1 DAY))
                    OR (s.frequence_verification = 'weekly' AND s.derniere_verification < DATE_SUB(NOW(), INTERVAL 7 DAY))
                  )
                ORDER BY s.derniere_verification ASC";

        return $this->db->query($sql)->fetchAll();
    }

    /**
     * Détecter les changements
     */
    private function detectChanges(int $surveillanceId, string $siren, array $criteres): array 
    {
        // Récupérer le dernier snapshot
        $lastSnapshot = $this->db->query(
            "SELECT snapshot_data FROM surveillance_snapshots 
             WHERE surveillance_id = ? 
             ORDER BY created_at DESC LIMIT 1",
            [$surveillanceId]
        )->fetch();

        if (!$lastSnapshot) {
            return []; // Pas de snapshot précédent, c'est normal pour la première fois
        }

        // Données actuelles
        $company = new Company();
        $currentData = $company->getCompanyWithRelations($company->findBySiren($siren)['id']);

        $oldData = json_decode($lastSnapshot['snapshot_data'], true);
        $changes = [];

        // Comparer selon les critères
        foreach ($criteres as $critere) {
            $detected = $this->compareField($critere, $oldData, $currentData);
            if ($detected) {
                $changes = array_merge($changes, $detected);
            }
        }

        return $changes;
    }

    /**
     * Comparer un champ spécifique
     */
    private function compareField(string $field, array $oldData, array $newData): array 
    {
        $changes = [];

        switch ($field) {
            case 'denomination':
                if ($oldData['denomination'] !== $newData['denomination']) {
                    $changes[] = [
                        'type' => 'denomination_changed',
                        'field' => 'denomination',
                        'old_value' => $oldData['denomination'],
                        'new_value' => $newData['denomination'],
                        'importance' => 'high'
                    ];
                }
                break;

            case 'dirigeants':
                $dirigeantsChanges = $this->compareDirigeants(
                    $oldData['dirigeants'] ?? [],
                    $newData['dirigeants'] ?? []
                );
                $changes = array_merge($changes, $dirigeantsChanges);
                break;

            case 'capital_social':
                if ($oldData['capital_social'] != $newData['capital_social']) {
                    $changes[] = [
                        'type' => 'capital_changed',
                        'field' => 'capital_social',
                        'old_value' => $oldData['capital_social'],
                        'new_value' => $newData['capital_social'],
                        'importance' => 'medium'
                    ];
                }
                break;

            case 'adresse':
                $adresseFields = ['adresse_ligne1', 'code_postal', 'ville'];
                foreach ($adresseFields as $adresseField) {
                    if ($oldData[$adresseField] !== $newData[$adresseField]) {
                        $changes[] = [
                            'type' => 'address_changed',
                            'field' => $adresseField,
                            'old_value' => $oldData[$adresseField],
                            'new_value' => $newData[$adresseField],
                            'importance' => 'medium'
                        ];
                    }
                }
                break;

            case 'documents':
                $documentsChanges = $this->compareDocuments(
                    $oldData['documents'] ?? [],
                    $newData['documents'] ?? []
                );
                $changes = array_merge($changes, $documentsChanges);
                break;

            case 'jugements':
                $jugementsChanges = $this->compareJugements(
                    $oldData['jugements'] ?? [],
                    $newData['jugements'] ?? []
                );
                $changes = array_merge($changes, $jugementsChanges);
                break;
        }

        return $changes;
    }

    /**
     * Comparer les dirigeants
     */
    private function compareDirigeants(array $oldDirigeants, array $newDirigeants): array 
    {
        $changes = [];

        // Index par nom complet pour comparaison
        $oldIndex = [];
        $newIndex = [];

        foreach ($oldDirigeants as $dirigeant) {
            $key = $dirigeant['nom'] . '_' . $dirigeant['prenom'];
            $oldIndex[$key] = $dirigeant;
        }

        foreach ($newDirigeants as $dirigeant) {
            $key = $dirigeant['nom'] . '_' . $dirigeant['prenom'];
            $newIndex[$key] = $dirigeant;
        }

        // Détecter les nouveaux dirigeants
        foreach ($newIndex as $key => $dirigeant) {
            if (!isset($oldIndex[$key])) {
                $changes[] = [
                    'type' => 'dirigeant_added',
                    'field' => 'dirigeants',
                    'dirigeant' => $dirigeant,
                    'importance' => 'high'
                ];
            }
        }

        // Détecter les dirigeants partis
        foreach ($oldIndex as $key => $dirigeant) {
            if (!isset($newIndex[$key])) {
                $changes[] = [
                    'type' => 'dirigeant_removed',
                    'field' => 'dirigeants',
                    'dirigeant' => $dirigeant,
                    'importance' => 'high'
                ];
            }
        }

        // Détecter les changements de fonction
        foreach ($newIndex as $key => $newDirigeant) {
            if (isset($oldIndex[$key])) {
                $oldDirigeant = $oldIndex[$key];
                if ($oldDirigeant['fonction'] !== $newDirigeant['fonction']) {
                    $changes[] = [
                        'type' => 'dirigeant_function_changed',
                        'field' => 'dirigeants',
                        'dirigeant' => $newDirigeant,
                        'old_function' => $oldDirigeant['fonction'],
                        'new_function' => $newDirigeant['fonction'],
                        'importance' => 'medium'
                    ];
                }
            }
        }

        return $changes;
    }

    /**
     * Comparer les documents
     */
    private function compareDocuments(array $oldDocs, array $newDocs): array 
    {
        $changes = [];

        // Compter les nouveaux documents
        $oldCount = count($oldDocs);
        $newCount = count($newDocs);

        if ($newCount > $oldCount) {
            // Identifier les nouveaux documents
            $oldIds = array_column($oldDocs, 'id');
            foreach ($newDocs as $doc) {
                if (!in_array($doc['id'], $oldIds)) {
                    $changes[] = [
                        'type' => 'document_added',
                        'field' => 'documents',
                        'document' => $doc,
                        'importance' => 'medium'
                    ];
                }
            }
        }

        return $changes;
    }

    /**
     * Comparer les jugements
     */
    private function compareJugements(array $oldJugements, array $newJugements): array 
    {
        $changes = [];

        $oldCount = count($oldJugements);
        $newCount = count($newJugements);

        if ($newCount > $oldCount) {
            $oldIds = array_column($oldJugements, 'id');
            foreach ($newJugements as $jugement) {
                if (!in_array($jugement['id'], $oldIds)) {
                    $changes[] = [
                        'type' => 'jugement_added',
                        'field' => 'jugements',
                        'jugement' => $jugement,
                        'importance' => 'critical'
                    ];
                }
            }
        }

        return $changes;
    }

    /**
     * Sauvegarder les changements détectés
     */
    private function saveDetectedChanges(int $surveillanceId, array $changes): void 
    {
        foreach ($changes as $change) {
            $this->db->query("
                INSERT INTO surveillance_changes (
                    surveillance_id, type_changement, field_changed, 
                    old_value, new_value, importance, detected_at
                ) VALUES (?, ?, ?, ?, ?, ?, NOW())
            ", [
                $surveillanceId,
                $change['type'],
                $change['field'],
                json_encode($change['old_value'] ?? null),
                json_encode($change['new_value'] ?? $change),
                $change['importance']
            ]);
        }
    }

    /**
     * Envoyer les alertes
     */
    private function sendAlerts(array $surveillance, array $changes): bool 
    {
        $alertsSent = false;

        // Email
        if ($surveillance['alertes_email']) {
            $this->sendEmailAlert($surveillance, $changes);
            $alertsSent = true;
        }

        // Webhook
        if (!empty($surveillance['alertes_webhook'])) {
            $this->sendWebhookAlert($surveillance, $changes);
            $alertsSent = true;
        }

        return $alertsSent;
    }

    /**
     * Envoyer alerte par email
     */
    private function sendEmailAlert(array $surveillance, array $changes): void 
    {
        $subject = "[Surveillance] Changements détectés - " . $surveillance['denomination'];

        $body = "Des changements ont été détectés pour l'entreprise " . $surveillance['denomination'] . " (SIREN: " . $surveillance['siren'] . "):\n\n";

        foreach ($changes as $change) {
            $body .= "• " . $this->formatChangeForEmail($change) . "\n";
        }

        $body .= "\n\nVoir plus de détails sur votre dashboard.";

        // TODO: Intégrer service email
        error_log("Email alert for surveillance {$surveillance['id']}: $subject");
    }

    /**
     * Envoyer alerte par webhook
     */
    private function sendWebhookAlert(array $surveillance, array $changes): void 
    {
        $payload = [
            'surveillance_id' => $surveillance['id'],
            'siren' => $surveillance['siren'],
            'denomination' => $surveillance['denomination'],
            'changes' => $changes,
            'detected_at' => date('Y-m-d H:i:s')
        ];

        // Envoyer webhook HTTP POST
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $surveillance['alertes_webhook'],
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) {
            error_log("Webhook sent successfully for surveillance {$surveillance['id']}");
        } else {
            error_log("Webhook failed for surveillance {$surveillance['id']}: HTTP $httpCode");
        }
    }

    /**
     * Formatter le changement pour email
     */
    private function formatChangeForEmail(array $change): string 
    {
        switch ($change['type']) {
            case 'denomination_changed':
                return "Dénomination changée: {$change['old_value']} → {$change['new_value']}";

            case 'dirigeant_added':
                return "Nouveau dirigeant: {$change['dirigeant']['prenom']} {$change['dirigeant']['nom']} ({$change['dirigeant']['fonction']})";

            case 'dirigeant_removed':
                return "Dirigeant parti: {$change['dirigeant']['prenom']} {$change['dirigeant']['nom']}";

            case 'capital_changed':
                return "Capital social modifié: {$change['old_value']}€ → {$change['new_value']}€";

            case 'document_added':
                return "Nouveau document: {$change['document']['type_document']} ({$change['document']['date_document']})";

            case 'jugement_added':
                return "⚠️ NOUVEAU JUGEMENT: {$change['jugement']['type_jugement']} - {$change['jugement']['tribunal']}";

            default:
                return "Changement détecté dans le champ: {$change['field']}";
        }
    }

    /**
     * Obtenir les surveillances d'un utilisateur
     */
    public function getUserSurveillances(int $userId): array 
    {
        return $this->db->query("
            SELECT s.*, 
                   (SELECT COUNT(*) FROM surveillance_changes sc 
                    WHERE sc.surveillance_id = s.id AND sc.detected_at > DATE_SUB(NOW(), INTERVAL 30 DAY)) as changements_30j
            FROM surveillances s
            WHERE s.user_id = ?
            ORDER BY s.created_at DESC
        ", [$userId])->fetchAll();
    }

    /**
     * Supprimer une surveillance
     */
    public function deleteSurveillance(int $userId, int $surveillanceId): bool 
    {
        $result = $this->db->query("
            DELETE FROM surveillances 
            WHERE id = ? AND user_id = ?
        ", [$surveillanceId, $userId]);

        return $result->rowCount() > 0;
    }
}
