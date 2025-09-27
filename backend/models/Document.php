<?php
/**
 * Modèle pour les documents
 */

require_once __DIR__ . '/Database.php';

class Document 
{
    private $db;

    public function __construct() 
    {
        $this->db = Database::getInstance();
    }

    /**
     * Créer un document depuis les données INPI
     */
    public function createFromINPI(int $companyId, string $siren, string $docType, array $docData): ?int 
    {
        $sql = "INSERT INTO documents (
            company_id, siren, type_document, sous_type, titre, description,
            date_document, date_depot, exercice_debut, exercice_fin,
            source, reference_externe, disponible
        ) VALUES (
            :company_id, :siren, :type_document, :sous_type, :titre, :description,
            :date_document, :date_depot, :exercice_debut, :exercice_fin,
            'INPI', :reference_externe, 1
        )";

        $params = [
            'company_id' => $companyId,
            'siren' => $siren,
            'type_document' => $this->mapDocumentType($docType),
            'sous_type' => $docData['type'] ?? null,
            'titre' => $docData['titre'] ?? $docData['libelle'] ?? null,
            'description' => $docData['description'] ?? null,
            'date_document' => $this->parseDate($docData['dateDocument'] ?? null),
            'date_depot' => $this->parseDate($docData['dateDepot'] ?? null),
            'exercice_debut' => $this->parseDate($docData['exerciceDebut'] ?? null),
            'exercice_fin' => $this->parseDate($docData['exerciceFin'] ?? null),
            'reference_externe' => $docData['id'] ?? null
        ];

        try {
            $this->db->query($sql, $params);
            return (int)$this->db->lastInsertId();
        } catch (Exception $e) {
            error_log("Erreur création document: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Marquer un document comme téléchargé
     */
    public function markAsDownloaded(int $documentId, string $filename): bool 
    {
        $sql = "UPDATE documents SET 
                   telecharge = 1, 
                   date_telechargement = NOW(),
                   nom_fichier = :filename,
                   chemin_fichier = :filepath
                WHERE id = :id";

        $params = [
            'id' => $documentId,
            'filename' => $filename,
            'filepath' => '/storage/documents/' . $filename
        ];

        try {
            $this->db->query($sql, $params);
            return true;
        } catch (Exception $e) {
            error_log("Erreur marquage téléchargement: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Mapper le type de document INPI vers notre typologie
     */
    private function mapDocumentType(string $inpiType): string 
    {
        $mapping = [
            'bilans' => 'BILAN',
            'actes' => 'ACTE',
            'statuts' => 'STATUTS'
        ];

        return $mapping[$inpiType] ?? 'AUTRE';
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
     * Rechercher des documents
     */
    public function searchByCompany(int $companyId, ?string $type = null): array 
    {
        $sql = "SELECT * FROM documents WHERE company_id = :company_id";
        $params = ['company_id' => $companyId];

        if ($type) {
            $sql .= " AND type_document = :type";
            $params['type'] = $type;
        }

        $sql .= " ORDER BY date_document DESC";

        $stmt = $this->db->query($sql, $params);
        return $stmt->fetchAll();
    }

    /**
     * Obtenir les statistiques des documents
     */
    public function getStats(): array 
    {
        $sql = "SELECT 
                    type_document,
                    COUNT(*) as nb_documents,
                    COUNT(CASE WHEN telecharge = 1 THEN 1 END) as nb_telecharges,
                    COUNT(CASE WHEN disponible = 1 THEN 1 END) as nb_disponibles
                FROM documents 
                GROUP BY type_document";

        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }
}
