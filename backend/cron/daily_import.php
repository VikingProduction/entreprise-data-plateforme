#!/usr/bin/env php
<?php
/**
 * Script de collecte quotidienne
 * A lancer via cron : 0 2 * * * /usr/bin/php /path/to/daily_import.php
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../collectors/INPICollector.php';
require_once __DIR__ . '/../collectors/BODACCCollector.php';

echo "🔄 Début de la collecte quotidienne - " . date('Y-m-d H:i:s') . "
";

try {
    // Log de début
    $db = Database::getInstance();
    $db->query("INSERT INTO import_logs (source, type_import, parametres) VALUES ('DAILY', 'INCREMENTAL', ?)", 
               [json_encode(['date' => date('Y-m-d')])]);
    $importId = $db->lastInsertId();

    $totalSuccess = 0;
    $totalErrors = 0;

    // 1. Collecte INPI des mises à jour
    echo "📊 Collecte INPI...
";

    $inpiCollector = new INPICollector(INPI_USERNAME, INPI_PASSWORD);

    // Récupérer la liste des entreprises à mettre à jour (modifiées récemment)
    $sql = "SELECT siren FROM companies 
            WHERE last_sync_inpi < DATE_SUB(NOW(), INTERVAL 7 DAY) 
               OR last_sync_inpi IS NULL 
            ORDER BY last_sync_inpi ASC 
            LIMIT 1000"; // Limiter pour ne pas surcharger

    $stmt = $db->query($sql);
    $sirenList = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($sirenList)) {
        echo "🔄 Mise à jour de " . count($sirenList) . " entreprises
";
        $results = $inpiCollector->batchImport($sirenList);
        $totalSuccess += $results['success'];
        $totalErrors += $results['errors'];
    }

    // 2. Collecte BODACC des nouveaux jugements
    echo "⚖️  Collecte BODACC...
";

    $bodaccCollector = new BODACCCollector();
    $bodaccResults = $bodaccCollector->importRecentJudgments(7); // 7 derniers jours
    $totalSuccess += $bodaccResults['success'] ?? 0;
    $totalErrors += $bodaccResults['errors'] ?? 0;

    // 3. Nettoyage du cache
    echo "🧹 Nettoyage du cache...
";
    $cacheDir = __DIR__ . '/../../storage/cache';
    if (is_dir($cacheDir)) {
        $files = glob($cacheDir . '/*');
        $deleted = 0;
        foreach ($files as $file) {
            if (is_file($file) && (time() - filemtime($file)) > 3600) { // > 1 heure
                unlink($file);
                $deleted++;
            }
        }
        echo "🗑️  {$deleted} fichiers de cache supprimés
";
    }

    // 4. Calcul des scores
    echo "📈 Calcul des scores de qualité...
";
    $sql = "UPDATE companies SET data_quality_score = CASE
                WHEN denomination IS NOT NULL THEN 20 ELSE 0 END +
                CASE WHEN forme_juridique IS NOT NULL THEN 15 ELSE 0 END +
                CASE WHEN date_creation IS NOT NULL THEN 10 ELSE 0 END +
                CASE WHEN activite_principale IS NOT NULL THEN 10 ELSE 0 END +
                CASE WHEN adresse_ligne1 IS NOT NULL THEN 15 ELSE 0 END +
                CASE WHEN capital_social IS NOT NULL THEN 10 ELSE 0 END +
                CASE WHEN last_sync_inpi IS NOT NULL THEN 20 ELSE 0 END
            WHERE updated_at > DATE_SUB(NOW(), INTERVAL 1 DAY)";
    $stmt = $db->query($sql);
    echo "📊 Scores recalculés pour " . $stmt->rowCount() . " entreprises
";

    // Mise à jour du log d'import
    $db->query("UPDATE import_logs SET 
                   date_fin = NOW(), 
                   statut = 'SUCCESS',
                   nb_traites = ?,
                   nb_crees = ?,
                   nb_erreurs = ?
                WHERE id = ?", 
               [count($sirenList), $totalSuccess, $totalErrors, $importId]);

    echo "✅ Collecte terminée avec succès
";
    echo "📊 Résultats: {$totalSuccess} succès, {$totalErrors} erreurs
";

} catch (Exception $e) {
    echo "❌ Erreur durant la collecte: " . $e->getMessage() . "
";

    // Log de l'erreur
    if (isset($importId)) {
        $db->query("UPDATE import_logs SET 
                       date_fin = NOW(), 
                       statut = 'ERROR',
                       message_erreur = ?
                    WHERE id = ?", 
                   [$e->getMessage(), $importId]);
    }

    exit(1);
}

echo "🔚 Fin de la collecte - " . date('Y-m-d H:i:s') . "
";
