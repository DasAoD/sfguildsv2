<?php
/**
 * Admin API: Cron-Job sofort ausführen
 * POST { job_key: "fetch_reports" | "member_sync" }
 */
require_once __DIR__ . '/../includes/bootstrap_api.php';
require_once __DIR__ . '/../includes/logger.php';
requireAdminAPI();

set_time_limit(120);
ini_set('max_execution_time', '120');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Nur POST erlaubt', 405);

try {
    $input = json_decode(file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
} catch (JsonException $e) { jsonError('Ungültige JSON-Daten', 400); }

$jobKey = $input['job_key'] ?? null;
$scriptMap = [
    'fetch_reports' => __DIR__ . '/../cli/cron_fetch_reports.php',
    'member_sync'   => __DIR__ . '/../cli/cron_member_sync.php',
];

if (!$jobKey || !isset($scriptMap[$jobKey])) jsonError('Unbekannter Job', 400);

$script = $scriptMap[$jobKey];
ob_start();
$result = require $script;
ob_end_clean();

$db = getDB();
if (is_array($result)) {
    $status  = $result['errors'] === 0 ? 'success' : ($result['success'] > 0 ? 'partial' : 'error');
    $message = "{$result['success']}/{$result['total']} erfolgreich";
    if ($result['errors'] > 0) $message .= ", {$result['errors']} Fehler";
} else {
    $status = 'success'; $message = 'Abgeschlossen';
}

$db->prepare("UPDATE cron_jobs SET last_run_at=:ts, last_run_status=:status, last_run_message=:msg, updated_at=datetime('now') WHERE job_key=:key")
   ->execute([':ts'=>gmdate('c'), ':status'=>$status, ':msg'=>$message, ':key'=>$jobKey]);

logActivity('Cron manuell gestartet', ['Job' => $jobKey, 'Status' => $status, 'Ergebnis' => $message]);

jsonResponse(['success' => true, 'message' => $message]);
