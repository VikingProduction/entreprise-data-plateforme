<?php
/**
 * Configuration principale de l'application
 */

// Configuration base de données
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'entreprise_data');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');

// Configuration INPI
define('INPI_USERNAME', getenv('INPI_USERNAME') ?: ''); // A remplir
define('INPI_PASSWORD', getenv('INPI_PASSWORD') ?: ''); // A remplir

// Configuration application
define('DOWNLOAD_DOCUMENTS', getenv('DOWNLOAD_DOCUMENTS') === 'true');
define('MAX_SEARCH_RESULTS', (int)(getenv('MAX_SEARCH_RESULTS') ?: 100));
define('CACHE_SEARCH_DURATION', (int)(getenv('CACHE_SEARCH_DURATION') ?: 3600)); // 1 heure

// Chemins
define('BASE_PATH', __DIR__ . '/../..');
define('STORAGE_PATH', BASE_PATH . '/storage');
define('DOCUMENTS_PATH', STORAGE_PATH . '/documents');
define('LOGS_PATH', STORAGE_PATH . '/logs');

// API
define('API_VERSION', '1.0');
define('API_RATE_LIMIT', (int)(getenv('API_RATE_LIMIT') ?: 100)); // Requêtes par heure

// URLs
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
define('BASE_URL', $protocol . '://' . $host);

// Créer les dossiers nécessaires
$dirs = [STORAGE_PATH, DOCUMENTS_PATH, LOGS_PATH];
foreach ($dirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}
