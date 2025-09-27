#!/usr/bin/env php
<?php
/**
 * Script de nettoyage et maintenance
 */

require_once __DIR__ . '/../config/database.php';

echo "🧹 Script de nettoyage - " . date('Y-m-d H:i:s') . "
";

try {
    $db = Database::getInstance();

    // 1. Nettoyer les anciens logs d'import
    echo "📋 Nettoyage des anciens logs...
";
    $sql = "DELETE FROM import_logs WHERE date_debut < DATE_SUB(NOW(), INTERVAL 90 DAY)";
    $stmt = $db->query($sql);
    echo "   Supprimé: " . $stmt->rowCount() . " logs
";

    // 2. Nettoyer le cache de recherche
    echo "🔍 Nettoyage du cache de recherche...
";
    $sql = "DELETE FROM search_cache WHERE expires_at < NOW()";
    $stmt = $db->query($sql);
    echo "   Supprimé: " . $stmt->rowCount() . " entrées de cache
";

    // 3. Nettoyer les fichiers temporaires
    echo "📁 Nettoyage des fichiers temporaires...
";
    $tempDirs = [
        __DIR__ . '/../../storage/cache',
        __DIR__ . '/../../storage/temp'
    ];

    $totalDeleted = 0;
    foreach ($tempDirs as $dir) {
        if (is_dir($dir)) {
            $files = glob($dir . '/*');
            foreach ($files as $file) {
                if (is_file($file) && (time() - filemtime($file)) > 86400) { // > 24h
                    unlink($file);
                    $totalDeleted++;
                }
            }
        }
    }
    echo "   Supprimé: {$totalDeleted} fichiers temporaires
";

    // 4. Optimiser les tables
    echo "⚡ Optimisation des tables...
";
    $tables = ['companies', 'documents', 'jugements', 'dirigeants', 'etablissements'];
    foreach ($tables as $table) {
        $db->query("OPTIMIZE TABLE {$table}");
        echo "   Optimisé: {$table}
";
    }

    // 5. Statistiques finales
    echo "📊 Statistiques après nettoyage:
";
    $stats = $db->query("SELECT 
        (SELECT COUNT(*) FROM companies) as total_companies,
        (SELECT COUNT(*) FROM documents) as total_documents,
        (SELECT COUNT(*) FROM jugements) as total_judgments
    ")->fetch();

    echo "   Entreprises: " . number_format($stats['total_companies']) . "
";
    echo "   Documents: " . number_format($stats['total_documents']) . "
";
    echo "   Jugements: " . number_format($stats['total_judgments']) . "
";

    echo "✅ Nettoyage terminé avec succès
";

} catch (Exception $e) {
    echo "❌ Erreur durant le nettoyage: " . $e->getMessage() . "
";
    exit(1);
}
