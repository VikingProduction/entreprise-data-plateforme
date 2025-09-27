<?php
/**
 * Collecteur de données INPI pour la plateforme d'entreprises
 * 
 * Ce collecteur récupère automatiquement :
 * - Données de base des entreprises
 * - Comptes annuels et bilans
 * - Actes et statuts
 * - Documents PDF
 * 
 * @author Assistant IA
 * @version 1.0
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Company.php';
require_once __DIR__ . '/../models/Document.php';
require_once __DIR__ . '/BaseCollector.php';

class INPICollector extends BaseCollector 
{
    private $apiBaseUrl = 'https://registre-national-entreprises.inpi.fr/api';
    private $apiUsername;
    private $apiPassword;
    private $authToken;
    private $tokenExpiry;

    public function __construct($username, $password) 
    {
        parent::__construct();
        $this->apiUsername = $username;
        $this->apiPassword = $password;
        $this->log("INPI Collector initialisé");
    }

    /**
     * Authentification auprès de l'API INPI
     */
    public function authenticate(): bool 
    {
        if ($this->isTokenValid()) {
            return true;
        }

        $url = $this->apiBaseUrl . '/sso/login';
        $data = [
            'username' => $this->apiUsername,
            'password' => $this->apiPassword
        ];

        $response = $this->makeRequest('POST', $url, [
            'Content-Type: application/json'
        ], json_encode($data));

        if ($response['status'] === 200) {
            $body = json_decode($response['body'], true);
            $this->authToken = $body['token'];
            $this->tokenExpiry = time() + (60 * 60); // 1 heure
            $this->log("Authentification INPI réussie");
            return true;
        }

        $this->log("Erreur authentification INPI: " . $response['status'], 'ERROR');
        return false;
    }

    /**
     * Vérifier si le token est encore valide
     */
    private function isTokenValid(): bool 
    {
        return !empty($this->authToken) && time() < $this->tokenExpiry;
    }

    /**
     * Récupérer les données complètes d'une entreprise
     */
    public function getCompanyData(string $siren): ?array 
    {
        if (!$this->authenticate()) {
            return null;
        }

        $url = $this->apiBaseUrl . "/companies/{$siren}";
        $headers = [
            'Authorization: Bearer ' . $this->authToken,
            'Content-Type: application/json'
        ];

        $response = $this->makeRequest('GET', $url, $headers);

        if ($response['status'] === 200) {
            $companyData = json_decode($response['body'], true);

            // Récupérer aussi les documents
            $documents = $this->getCompanyDocuments($siren);
            $companyData['documents'] = $documents;

            $this->log("Données récupérées pour SIREN {$siren}");
            return $companyData;
        }

        $this->log("Entreprise non trouvée: {$siren} (Status: {$response['status']})", 'WARNING');
        return null;
    }

    /**
     * Récupérer la liste des documents d'une entreprise
     */
    public function getCompanyDocuments(string $siren): array 
    {
        if (!$this->authenticate()) {
            return [];
        }

        $url = $this->apiBaseUrl . "/companies/{$siren}/attachments";
        $headers = ['Authorization: Bearer ' . $this->authToken];

        $response = $this->makeRequest('GET', $url, $headers);

        if ($response['status'] === 200) {
            $documents = json_decode($response['body'], true);
            $this->log("Documents trouvés pour {$siren}: " . count($documents));
            return $documents;
        }

        return [];
    }

    /**
     * Télécharger un document PDF
     */
    public function downloadDocument(string $docType, string $docId, string $siren, string $filename): bool 
    {
        if (!$this->authenticate()) {
            return false;
        }

        $url = $this->apiBaseUrl . "/{$docType}/{$docId}/download";
        $headers = ['Authorization: Bearer ' . $this->authToken];

        // Créer le dossier de destination
        $docDir = __DIR__ . "/../../storage/documents/{$siren}";
        if (!is_dir($docDir)) {
            mkdir($docDir, 0755, true);
        }

        $filepath = $docDir . '/' . $filename;

        // Vérifier si le fichier existe déjà
        if (file_exists($filepath)) {
            $this->log("Document {$filename} existe déjà");
            return true;
        }

        $response = $this->makeRequest('GET', $url, $headers);

        if ($response['status'] === 200) {
            file_put_contents($filepath, $response['body']);
            $this->log("Document téléchargé: {$filename}");
            return true;
        }

        $this->log("Échec téléchargement {$filename}: Status {$response['status']}", 'ERROR');
        return false;
    }

    /**
     * Traitement complet d'une entreprise (données + documents)
     */
    public function processCompany(string $siren): bool 
    {
        $this->log("Début traitement entreprise {$siren}");

        // 1. Récupérer les données de l'entreprise
        $companyData = $this->getCompanyData($siren);
        if (!$companyData) {
            return false;
        }

        // 2. Sauvegarder en base de données
        $company = new Company();
        $companyId = $company->createOrUpdate($companyData);

        if (!$companyId) {
            $this->log("Erreur sauvegarde entreprise {$siren}", 'ERROR');
            return false;
        }

        // 3. Traiter les documents
        $this->processDocuments($companyId, $siren, $companyData['documents'] ?? []);

        $this->log("Traitement terminé pour {$siren}");
        return true;
    }

    /**
     * Traiter les documents d'une entreprise
     */
    private function processDocuments(int $companyId, string $siren, array $documents): void 
    {
        $documentModel = new Document();

        foreach (['bilans', 'actes'] as $docType) {
            if (!isset($documents[$docType])) continue;

            foreach ($documents[$docType] as $doc) {
                // Sauvegarder l'info du document en BDD
                $documentId = $documentModel->createFromINPI($companyId, $siren, $docType, $doc);

                // Planifier le téléchargement si configuré
                if (DOWNLOAD_DOCUMENTS && !empty($doc['id'])) {
                    $filename = $this->generateFilename($siren, $docType, $doc);

                    if ($this->downloadDocument($docType, $doc['id'], $siren, $filename)) {
                        $documentModel->markAsDownloaded($documentId, $filename);
                    }
                }
            }
        }
    }

    /**
     * Générer un nom de fichier standardisé
     */
    private function generateFilename(string $siren, string $docType, array $doc): string 
    {
        $date = $doc['dateDepot'] ?? $doc['dateDocument'] ?? date('Y-m-d');
        $id = $doc['id'] ?? uniqid();
        $extension = 'pdf';

        return "{$docType}_{$siren}_{$date}_{$id}.{$extension}";
    }

    /**
     * Import en masse depuis une liste de SIREN
     */
    public function batchImport(array $sirenList): array 
    {
        $results = [
            'total' => count($sirenList),
            'success' => 0,
            'errors' => 0,
            'details' => []
        ];

        $this->log("Début import en masse: " . count($sirenList) . " entreprises");

        foreach ($sirenList as $siren) {
            try {
                if ($this->processCompany($siren)) {
                    $results['success']++;
                    $results['details'][$siren] = 'SUCCESS';
                } else {
                    $results['errors']++;
                    $results['details'][$siren] = 'ERROR';
                }

                // Respecter les limites de débit
                usleep(500000); // 0.5 seconde

            } catch (Exception $e) {
                $results['errors']++;
                $results['details'][$siren] = 'EXCEPTION: ' . $e->getMessage();
                $this->log("Exception pour {$siren}: " . $e->getMessage(), 'ERROR');
            }
        }

        $this->log("Import terminé: {$results['success']} succès, {$results['errors']} erreurs");
        return $results;
    }
}
