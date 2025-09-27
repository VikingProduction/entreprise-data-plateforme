<?php
/**
 * Modèle pour les entreprises
 */

require_once __DIR__ . '/Database.php';

class Company 
{
    private $db;

    public function __construct() 
    {
        $this->db = Database::getInstance();
    }

    /**
     * Créer ou mettre à jour une entreprise depuis les données INPI
     */
    public function createOrUpdate(array $data): ?int 
    {
        $siren = $data['siren'] ?? null;
        if (!$siren) {
            return null;
        }

        // Vérifier si l'entreprise existe
        $existing = $this->findBySiren($siren);

        if ($existing) {
            return $this->update($existing['id'], $data);
        } else {
            return $this->create($data);
        }
    }

    /**
     * Créer une nouvelle entreprise
     */
    private function create(array $data): ?int 
    {
        $sql = "INSERT INTO companies (
            siren, siret_siege, denomination, denomination_usuelle, nom_commercial,
            forme_juridique, forme_juridique_code, capital_social,
            date_creation, date_immatriculation, date_radiation,
            activite_principale, code_ape, secteur_activite,
            adresse_ligne1, adresse_ligne2, code_postal, ville, cedex, pays,
            statut, etat_administratif, economie_sociale_solidaire,
            last_sync_inpi, data_quality_score
        ) VALUES (
            :siren, :siret_siege, :denomination, :denomination_usuelle, :nom_commercial,
            :forme_juridique, :forme_juridique_code, :capital_social,
            :date_creation, :date_immatriculation, :date_radiation,
            :activite_principale, :code_ape, :secteur_activite,
            :adresse_ligne1, :adresse_ligne2, :code_postal, :ville, :cedex, :pays,
            :statut, :etat_administratif, :economie_sociale_solidaire,
            NOW(), :data_quality_score
        )";

        $params = $this->prepareParams($data);

        try {
            $this->db->query($sql, $params);
            return (int)$this->db->lastInsertId();
        } catch (Exception $e) {
            error_log("Erreur création entreprise: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Mettre à jour une entreprise existante
     */
    private function update(int $id, array $data): ?int 
    {
        $sql = "UPDATE companies SET 
            denomination = :denomination,
            denomination_usuelle = :denomination_usuelle,
            nom_commercial = :nom_commercial,
            forme_juridique = :forme_juridique,
            forme_juridique_code = :forme_juridique_code,
            capital_social = :capital_social,
            date_creation = :date_creation,
            date_immatriculation = :date_immatriculation,
            date_radiation = :date_radiation,
            activite_principale = :activite_principale,
            code_ape = :code_ape,
            secteur_activite = :secteur_activite,
            adresse_ligne1 = :adresse_ligne1,
            adresse_ligne2 = :adresse_ligne2,
            code_postal = :code_postal,
            ville = :ville,
            cedex = :cedex,
            pays = :pays,
            statut = :statut,
            etat_administratif = :etat_administratif,
            economie_sociale_solidaire = :economie_sociale_solidaire,
            last_sync_inpi = NOW(),
            data_quality_score = :data_quality_score,
            updated_at = NOW()
        WHERE id = :id";

        $params = $this->prepareParams($data);
        $params['id'] = $id;

        try {
            $this->db->query($sql, $params);
            return $id;
        } catch (Exception $e) {
            error_log("Erreur mise à jour entreprise: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Préparer les paramètres depuis les données INPI
     */
    private function prepareParams(array $data): array 
    {
        return [
            'siren' => $data['siren'] ?? null,
            'siret_siege' => $data['siretSiege'] ?? null,
            'denomination' => $data['denomination'] ?? null,
            'denomination_usuelle' => $data['denominationUsuelle'] ?? null,
            'nom_commercial' => $data['nomCommercial'] ?? null,
            'forme_juridique' => $data['formeJuridique'] ?? null,
            'forme_juridique_code' => $data['formeJuridiqueCode'] ?? null,
            'capital_social' => $this->parseCapital($data['capitalSocial'] ?? null),
            'date_creation' => $this->parseDate($data['dateCreation'] ?? null),
            'date_immatriculation' => $this->parseDate($data['dateImmatriculation'] ?? null),
            'date_radiation' => $this->parseDate($data['dateRadiation'] ?? null),
            'activite_principale' => $data['activitePrincipale'] ?? null,
            'code_ape' => $data['codeAPE'] ?? null,
            'secteur_activite' => $this->determineSecteur($data['codeAPE'] ?? null),
            'adresse_ligne1' => $data['adresse']['ligne1'] ?? null,
            'adresse_ligne2' => $data['adresse']['ligne2'] ?? null,
            'code_postal' => $data['adresse']['codePostal'] ?? null,
            'ville' => $data['adresse']['ville'] ?? null,
            'cedex' => $data['adresse']['cedex'] ?? null,
            'pays' => $data['adresse']['pays'] ?? 'FRANCE',
            'statut' => $this->determineStatut($data),
            'etat_administratif' => $data['etatAdministratif'] ?? 'A',
            'economie_sociale_solidaire' => $data['ess'] ?? false,
            'data_quality_score' => $this->calculateQualityScore($data)
        ];
    }

    /**
     * Parser le capital social
     */
    private function parseCapital($capital): ?float 
    {
        if (!$capital) return null;

        // Nettoyer et convertir en nombre
        $capital = preg_replace('/[^0-9.,]/', '', $capital);
        $capital = str_replace(',', '.', $capital);

        return is_numeric($capital) ? (float)$capital : null;
    }

    /**
     * Parser une date
     */
    private function parseDate($date): ?string 
    {
        if (!$date) return null;

        $timestamp = strtotime($date);
        return $timestamp ? date('Y-m-d', $timestamp) : null;
    }

    /**
     * Déterminer le secteur d'activité depuis le code APE
     */
    private function determineSecteur(?string $codeAPE): ?string 
    {
        if (!$codeAPE) return null;

        $secteurs = [
            'A' => 'Agriculture, sylviculture et pêche',
            'B' => 'Industries extractives',
            'C' => 'Industrie manufacturière',
            'D' => 'Production et distribution d'électricité, de gaz, de vapeur et d'air conditionné',
            'E' => 'Production et distribution d'eau',
            'F' => 'Construction',
            'G' => 'Commerce',
            'H' => 'Transports et entreposage',
            'I' => 'Hébergement et restauration',
            'J' => 'Information et communication',
            'K' => 'Activités financières et d'assurance',
            'L' => 'Activités immobilières',
            'M' => 'Activités spécialisées, scientifiques et techniques',
            'N' => 'Activités de services administratifs et de soutien',
            'O' => 'Administration publique',
            'P' => 'Enseignement',
            'Q' => 'Santé humaine et action sociale',
            'R' => 'Arts, spectacles et activités récréatives',
            'S' => 'Autres activités de services',
            'T' => 'Activités des ménages en tant qu'employeurs',
            'U' => 'Activités extra-territoriales'
        ];

        $section = substr($codeAPE, 0, 1);
        return $secteurs[$section] ?? 'Autre';
    }

    /**
     * Déterminer le statut de l'entreprise
     */
    private function determineStatut(array $data): string 
    {
        if (!empty($data['dateRadiation'])) {
            return 'RADIATION';
        }

        if (!empty($data['dateCessation'])) {
            return 'CESSE';
        }

        return 'ACTIF';
    }

    /**
     * Calculer un score de qualité des données
     */
    private function calculateQualityScore(array $data): int 
    {
        $score = 0;
        $fields = [
            'siren' => 20,
            'denomination' => 15,
            'formeJuridique' => 10,
            'dateCreation' => 10,
            'activitePrincipale' => 10,
            'adresse' => 15,
            'dirigeants' => 10,
            'capitalSocial' => 10
        ];

        foreach ($fields as $field => $points) {
            if (!empty($data[$field])) {
                $score += $points;
            }
        }

        return min(100, $score);
    }

    /**
     * Rechercher une entreprise par SIREN
     */
    public function findBySiren(string $siren): ?array 
    {
        $sql = "SELECT * FROM companies WHERE siren = :siren LIMIT 1";
        $stmt = $this->db->query($sql, ['siren' => $siren]);
        $result = $stmt->fetch();

        return $result ?: null;
    }

    /**
     * Recherche d'entreprises
     */
    public function search(string $query, int $limit = 20, int $offset = 0): array 
    {
        // Recherche par SIREN si c'est un nombre de 9 chiffres
        if (preg_match('/^\d{9}$/', $query)) {
            $company = $this->findBySiren($query);
            return $company ? [$company] : [];
        }

        // Recherche textuelle
        $sql = "SELECT c.*, 
                   MATCH(c.denomination, c.denomination_usuelle, c.nom_commercial) 
                   AGAINST(:query IN NATURAL LANGUAGE MODE) as relevance
                FROM companies c 
                WHERE MATCH(c.denomination, c.denomination_usuelle, c.nom_commercial) 
                      AGAINST(:query IN NATURAL LANGUAGE MODE)
                   OR c.denomination LIKE :like_query
                   OR c.denomination_usuelle LIKE :like_query
                ORDER BY relevance DESC, c.denomination ASC
                LIMIT :limit OFFSET :offset";

        $params = [
            'query' => $query,
            'like_query' => '%' . $query . '%',
            'limit' => $limit,
            'offset' => $offset
        ];

        $stmt = $this->db->query($sql, $params);
        return $stmt->fetchAll();
    }

    /**
     * Obtenir les statistiques générales
     */
    public function getStats(): array 
    {
        $sql = "SELECT 
                    COUNT(*) as total_entreprises,
                    COUNT(CASE WHEN statut = 'ACTIF' THEN 1 END) as actives,
                    COUNT(CASE WHEN statut = 'CESSE' THEN 1 END) as cessees,
                    COUNT(CASE WHEN last_sync_inpi IS NOT NULL THEN 1 END) as synchronisees,
                    AVG(data_quality_score) as score_qualite_moyen
                FROM companies";

        $stmt = $this->db->query($sql);
        return $stmt->fetch();
    }

    /**
     * Obtenir une entreprise avec ses relations
     */
    public function getCompanyWithRelations(int $id): ?array 
    {
        // Données de base
        $sql = "SELECT * FROM companies WHERE id = :id";
        $stmt = $this->db->query($sql, ['id' => $id]);
        $company = $stmt->fetch();

        if (!$company) {
            return null;
        }

        // Dirigeants
        $sql = "SELECT * FROM dirigeants WHERE company_id = :id AND actif = 1 ORDER BY fonction";
        $stmt = $this->db->query($sql, ['id' => $id]);
        $company['dirigeants'] = $stmt->fetchAll();

        // Établissements
        $sql = "SELECT * FROM etablissements WHERE company_id = :id ORDER BY etablissement_siege DESC";
        $stmt = $this->db->query($sql, ['id' => $id]);
        $company['etablissements'] = $stmt->fetchAll();

        // Documents récents
        $sql = "SELECT * FROM documents WHERE company_id = :id ORDER BY date_document DESC LIMIT 10";
        $stmt = $this->db->query($sql, ['id' => $id]);
        $company['documents'] = $stmt->fetchAll();

        // Dernières données financières
        $sql = "SELECT * FROM donnees_financieres WHERE company_id = :id ORDER BY exercice_fin DESC LIMIT 1";
        $stmt = $this->db->query($sql, ['id' => $id]);
        $finance = $stmt->fetch();
        if ($finance) {
            $company['finance'] = $finance;
        }

        // Jugements
        $sql = "SELECT * FROM jugements WHERE company_id = :id ORDER BY date_jugement DESC";
        $stmt = $this->db->query($sql, ['id' => $id]);
        $company['jugements'] = $stmt->fetchAll();

        return $company;
    }
}
