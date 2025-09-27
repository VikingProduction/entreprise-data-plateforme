<?php
/**
 * API pour récupérer les informations complètes d'une entreprise
 * Endpoint: /api/companies.php
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Company.php';

try {
    $company = new Company();

    // Récupérer les paramètres
    $siren = $_GET['siren'] ?? '';
    $id = $_GET['id'] ?? '';
    $include = $_GET['include'] ?? 'all'; // all, basic, documents, finance, dirigeants

    if (empty($siren) && empty($id)) {
        http_response_code(400);
        echo json_encode([
            'error' => 'Paramètre manquant',
            'message' => 'Le paramètre "siren" ou "id" est requis'
        ]);
        exit;
    }

    // Récupérer l'entreprise
    if ($siren) {
        $companyData = $company->findBySiren($siren);
    } else {
        $companyData = $company->getCompanyWithRelations((int)$id);
    }

    if (!$companyData) {
        http_response_code(404);
        echo json_encode([
            'error' => 'Entreprise non trouvée',
            'message' => 'Aucune entreprise trouvée avec ce SIREN/ID'
        ]);
        exit;
    }

    // Si relations complètes pas encore chargées
    if ($include === 'all' && !isset($companyData['dirigeants'])) {
        $companyData = $company->getCompanyWithRelations($companyData['id']);
    }

    // Filtrer selon le paramètre include
    $response = ['success' => true, 'data' => $companyData];

    if ($include !== 'all') {
        $filtered = [
            'id' => $companyData['id'],
            'siren' => $companyData['siren'],
            'denomination' => $companyData['denomination'],
            'forme_juridique' => $companyData['forme_juridique'],
            'statut' => $companyData['statut']
        ];

        switch ($include) {
            case 'documents':
                $filtered['documents'] = $companyData['documents'] ?? [];
                break;
            case 'finance':
                $filtered['finance'] = $companyData['finance'] ?? null;
                break;
            case 'dirigeants':
                $filtered['dirigeants'] = $companyData['dirigeants'] ?? [];
                break;
        }

        $response['data'] = $filtered;
    }

    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erreur interne du serveur',
        'message' => $e->getMessage()
    ]);
}
