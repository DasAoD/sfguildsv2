<?php
/**
 * Admin API: Import-Log
 * Gibt Gilden-Importstatus + gefilterte Activity-Log-Einträge zurück.
 */
require_once __DIR__ . '/../includes/bootstrap_api.php';
requireAdminAPI();

$db = getDB();

// Letzter Import pro Gilde
$guilds = $db->query("
    SELECT id, name, server, last_import_at
    FROM guilds
    ORDER BY name
")->fetchAll(PDO::FETCH_ASSOC);

// Activity-Log nach Import-Einträgen filtern
$logFile = __DIR__ . '/../data/logs/activity.log';
$entries = [];
if (file_exists($logFile)) {
    $keywords = ['Member-Sync', 'Mitglieder importiert', 'Cron-Job'];
    $lines = array_reverse(file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
    foreach ($lines as $line) {
        foreach ($keywords as $kw) {
            if (str_contains($line, $kw)) {
                // Cron-Job-Einträge nur für member_sync und fetch_reports
                if ($kw === 'Cron-Job' && !str_contains($line, 'member_sync') && !str_contains($line, 'fetch_reports')) {
                    break;
                }
                $entries[] = $line;
                break;
            }
        }
        if (count($entries) >= 50) break;
    }
}

jsonResponse([
    'success' => true,
    'guilds'  => $guilds,
    'entries' => $entries,
]);
