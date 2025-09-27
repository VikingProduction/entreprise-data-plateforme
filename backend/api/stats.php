<?php
/**
 * API pour les statistiques de la plateforme
 * Endpoint: /api/stats.php
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Company.php';
require_once __DIR__ . '/../models/Document.php';

try {
    $company = new Company();
    $document = new Document();

    $companyStats = $company->getStats();
    $documentStats = $document->getStats();

    $response = [
        'success' => true,
        'companies' => [
            'total' => (int)$companyStats['total_entreprises'],
            'active' => (int)$companyStats['actives'],
            'closed' => (int)$companyStats['cessees'],
            'synchronized' => (int)$companyStats['synchronisees'],
            'quality_score_avg' => round((float)$companyStats['score_qualite_moyen'], 1)
        ],
        'documents' => $documentStats,
        'timestamp' => date('c')
    ];

    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erreur interne du serveur',
        'message' => $e->getMessage()
    ]);
}
