<?php
/**
 * API de recherche d'entreprises
 * Endpoint: /api/search.php
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Gérer les requêtes OPTIONS (CORS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Company.php';

try {
    $company = new Company();

    // Récupérer les paramètres
    $query = $_GET['q'] ?? '';
    $limit = min((int)($_GET['limit'] ?? 20), MAX_SEARCH_RESULTS);
    $offset = max(0, (int)($_GET['offset'] ?? 0));
    $format = $_GET['format'] ?? 'full'; // full, summary

    if (empty($query)) {
        http_response_code(400);
        echo json_encode([
            'error' => 'Paramètre de recherche manquant',
            'message' => 'Le paramètre "q" est requis'
        ]);
        exit;
    }

    // Vérifier le cache
    $cacheKey = md5($query . $limit . $offset . $format);
    $cacheFile = STORAGE_PATH . "/cache/search_{$cacheKey}.json";

    if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < CACHE_SEARCH_DURATION) {
        echo file_get_contents($cacheFile);
        exit;
    }

    // Effectuer la recherche
    $results = $company->search($query, $limit, $offset);

    // Formater les résultats
    if ($format === 'summary') {
        $results = array_map(function($company) {
            return [
                'siren' => $company['siren'],
                'denomination' => $company['denomination'],
                'forme_juridique' => $company['forme_juridique'],
                'ville' => $company['ville'],
                'statut' => $company['statut'],
                'date_creation' => $company['date_creation']
            ];
        }, $results);
    }

    $response = [
        'success' => true,
        'query' => $query,
        'total_found' => count($results),
        'limit' => $limit,
        'offset' => $offset,
        'results' => $results,
        'timestamp' => date('c')
    ];

    // Sauvegarder en cache
    $cacheDir = dirname($cacheFile);
    if (!is_dir($cacheDir)) {
        mkdir($cacheDir, 0755, true);
    }
    file_put_contents($cacheFile, json_encode($response));

    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erreur interne du serveur',
        'message' => $e->getMessage()
    ]);
}
