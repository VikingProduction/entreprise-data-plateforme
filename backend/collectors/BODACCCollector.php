<?php
/**
 * Collecteur BODACC pour les jugements et procédures collectives
 */

require_once __DIR__ . '/BaseCollector.php';
require_once __DIR__ . '/../models/Company.php';

class BODACCCollector extends BaseCollector 
{
    private $apiUrl = 'https://bodacc-datadila.opendatasoft.com/api/records/1.0/search';

    public function __construct() 
    {
        parent::__construct();
        $this->log("BODACC Collector initialisé");
    }

    /**
     * Importer les jugements récents
     */
    public function importRecentJudgments(int $daysBack = 7): array 
    {
        $dateFrom = date('Y-m-d', strtotime("-{$daysBack} days"));
        $results = ['success' => 0, 'errors' => 0, 'details' => []];

        $this->log("Import des jugements BODACC depuis {$dateFrom}");

        $offset = 0;
        $limit = 100;
        $total = 0;

        do {
            $params = [
                'dataset' => 'annonces-commerciales',
                'q' => '',
                'facet' => ['typeAnnonce', 'dateParution'],
                'refine.dateParution' => ">={$dateFrom}",
                'refine.typeAnnonce' => 'Procédure collective',
                'rows' => $limit,
                'start' => $offset
            ];

            $url = $this->apiUrl . '?' . http_build_query($params);
            $response = $this->makeRequest('GET', $url);

            if ($response['status'] !== 200) {
                $this->log("Erreur API BODACC: " . $response['status'], 'ERROR');
                break;
            }

            $data = json_decode($response['body'], true);
            $records = $data['records'] ?? [];

            if (empty($records)) {
                break;
            }

            foreach ($records as $record) {
                try {
                    $this->processJudgment($record);
                    $results['success']++;
                } catch (Exception $e) {
                    $results['errors']++;
                    $this->log("Erreur traitement jugement: " . $e->getMessage(), 'ERROR');
                }
            }

            $offset += $limit;
            $total += count($records);

            // Pause pour respecter les limites
            usleep(100000); // 0.1 seconde

        } while (count($records) === $limit && $total < 10000); // Limite sécurité

        $this->log("Import BODACC terminé: {$results['success']} succès, {$results['errors']} erreurs");
        return $results;
    }

    /**
     * Traiter un jugement individuel
     */
    private function processJudgment(array $record): void 
    {
        $fields = $record['fields'] ?? [];

        // Extraire le SIREN/SIRET
        $siren = $this->extractSiren($fields);
        if (!$siren || !$this->isValidSiren($siren)) {
            throw new Exception("SIREN invalide ou manquant");
        }

        // Vérifier si l'entreprise existe
        $company = new Company();
        $companyData = $company->findBySiren($siren);

        if (!$companyData) {
            // Créer une entrée basique pour l'entreprise
            $companyId = $this->createBasicCompany($siren, $fields);
        } else {
            $companyId = $companyData['id'];
        }

        if (!$companyId) {
            throw new Exception("Impossible de créer/trouver l'entreprise");
        }

        // Créer le jugement
        $this->createJudgment($companyId, $siren, $fields);
    }

    /**
     * Extraire le SIREN depuis les données BODACC
     */
    private function extractSiren(array $fields): ?string 
    {
        // Tentatives d'extraction du SIREN
        $candidates = [
            $fields['numeroIdentificationRCS'] ?? '',
            $fields['numeroImmatriculation'] ?? '',
            $fields['siren'] ?? ''
        ];

        foreach ($candidates as $candidate) {
            if (preg_match('/\b(\d{9})\b/', $candidate, $matches)) {
                return $matches[1];
            }
        }

        return null;
    }

    /**
     * Créer une entreprise basique depuis BODACC
     */
    private function createBasicCompany(string $siren, array $fields): ?int 
    {
        $sql = "INSERT INTO companies (
            siren, denomination, ville, statut, 
            data_quality_score, created_at
        ) VALUES (
            :siren, :denomination, :ville, 'ACTIF', 30, NOW()
        )";

        $params = [
            'siren' => $siren,
            'denomination' => $fields['raisonSociale'] ?? $fields['denomination'] ?? null,
            'ville' => $fields['ville'] ?? null
        ];

        try {
            $this->db->query($sql, $params);
            $companyId = (int)$this->db->lastInsertId();
            $this->log("Entreprise créée depuis BODACC: {$siren}");
            return $companyId;
        } catch (Exception $e) {
            $this->log("Erreur création entreprise BODACC: " . $e->getMessage(), 'ERROR');
            return null;
        }
    }

    /**
     * Créer un jugement
     */
    private function createJudgment(int $companyId, string $siren, array $fields): void 
    {
        // Vérifier si le jugement existe déjà
        $sql = "SELECT id FROM jugements 
                WHERE siren = :siren 
                  AND tribunal = :tribunal 
                  AND date_jugement = :date_jugement 
                  AND numero_rg = :numero_rg
                LIMIT 1";

        $checkParams = [
            'siren' => $siren,
            'tribunal' => $fields['tribunal'] ?? '',
            'date_jugement' => $this->parseDate($fields['dateJugement'] ?? ''),
            'numero_rg' => $fields['numeroRG'] ?? ''
        ];

        $stmt = $this->db->query($sql, $checkParams);
        if ($stmt->fetch()) {
            // Jugement déjà existant
            return;
        }

        $sql = "INSERT INTO jugements (
            company_id, siren, type_jugement, nature_jugement,
            tribunal, numero_rg, date_jugement, date_publication,
            administrateur_nom, mandataire_nom, liquidateur_nom,
            date_cessation_paiements, date_limite_declarations,
            description, source, reference_bodacc
        ) VALUES (
            :company_id, :siren, :type_jugement, :nature_jugement,
            :tribunal, :numero_rg, :date_jugement, :date_publication,
            :administrateur_nom, :mandataire_nom, :liquidateur_nom,
            :date_cessation_paiements, :date_limite_declarations,
            :description, 'BODACC', :reference_bodacc
        )";

        $params = [
            'company_id' => $companyId,
            'siren' => $siren,
            'type_jugement' => $this->mapTypeJugement($fields['natureJugement'] ?? ''),
            'nature_jugement' => $fields['natureJugement'] ?? null,
            'tribunal' => $fields['tribunal'] ?? null,
            'numero_rg' => $fields['numeroRG'] ?? null,
            'date_jugement' => $this->parseDate($fields['dateJugement'] ?? ''),
            'date_publication' => $this->parseDate($fields['dateParution'] ?? ''),
            'administrateur_nom' => $fields['administrateur'] ?? null,
            'mandataire_nom' => $fields['mandataire'] ?? null,
            'liquidateur_nom' => $fields['liquidateur'] ?? null,
            'date_cessation_paiements' => $this->parseDate($fields['dateCessationPaiements'] ?? ''),
            'date_limite_declarations' => $this->parseDate($fields['dateLimiteDeclarations'] ?? ''),
            'description' => $this->buildDescription($fields),
            'reference_bodacc' => $record['recordid'] ?? null
        ];

        try {
            $this->db->query($sql, $params);
            $this->log("Jugement créé pour SIREN {$siren}");
        } catch (Exception $e) {
            $this->log("Erreur création jugement: " . $e->getMessage(), 'ERROR');
            throw $e;
        }
    }

    /**
     * Mapper le type de jugement
     */
    private function mapTypeJugement(string $nature): string 
    {
        $mapping = [
            'redressement' => 'REDRESSEMENT',
            'liquidation' => 'LIQUIDATION',
            'sauvegarde' => 'SAUVEGARDE',
            'plan de continuation' => 'PLAN_CONTINUATION',
            'plan de cession' => 'PLAN_CONTINUATION'
        ];

        $natureLower = strtolower($nature);
        foreach ($mapping as $keyword => $type) {
            if (strpos($natureLower, $keyword) !== false) {
                return $type;
            }
        }

        return 'AUTRE';
    }

    /**
     * Construire la description depuis les champs BODACC
     */
    private function buildDescription(array $fields): string 
    {
        $description = [];

        if (!empty($fields['texteAnnonce'])) {
            $description[] = $fields['texteAnnonce'];
        }

        if (!empty($fields['complement'])) {
            $description[] = "Complément: " . $fields['complement'];
        }

        return implode("\n\n", $description);
    }

    /**
     * Parser une date BODACC
     */
    private function parseDate(string $date): ?string 
    {
        if (empty($date)) return null;

        $timestamp = strtotime($date);
        return $timestamp ? date('Y-m-d', $timestamp) : null;
    }
}
