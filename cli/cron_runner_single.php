<?php
/**
 * Führt einen einzelnen Cron-Job aus (als Hintergrundprozess).
 * Usage: php cron_runner_single.php fetch_reports
 */
if (PHP_SAPI !== 'cli') { exit(1); }

$jobKey = $argv[1] ?? null;
$scriptMap = [
    'fetch_reports' => __DIR__ . '/cron_fetch_reports.php',
    'member_sync'   => __DIR__ . '/cron_member_sync.php',
];
if (!$jobKey || !isset($scriptMap[$jobKey])) { exit(1); }

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/sf_helpers.php';
require_once __DIR__ . '/../includes/logger.php';

$db = getDB();

// Lock setzen
$lockFile = sys_get_temp_dir() . '/sfguilds_cron_' . $jobKey . '.lock';
$lockFh = fopen($lockFile, 'c');
if (!flock($lockFh, LOCK_EX | LOCK_NB)) { exit(0); } // bereits in Ausführung

ob_start();
$result = require $scriptMap[$jobKey];
ob_end_clean();

if (is_array($result)) {
    $status  = $result['errors'] === 0 ? 'success' : ($result['success'] > 0 ? 'partial' : 'error');
    $message = "{$result['success']}/{$result['total']} erfolgreich";
    if ($result['errors'] > 0) $message .= ", {$result['errors']} Fehler";
} else {
    $status = 'success'; $message = 'Abgeschlossen';
}

$db->prepare("UPDATE cron_jobs SET last_run_at=:ts, last_run_status=:status, last_run_message=:msg, updated_at=datetime('now') WHERE job_key=:key")
   ->execute([':ts'=>gmdate('c'), ':status'=>$status, ':msg'=>$message, ':key'=>$jobKey]);

logActivity('Cron-Job', ['Job' => $jobKey, 'Status' => $status, 'Ergebnis' => $message]);

flock($lockFh, LOCK_UN);
fclose($lockFh);
@unlink($lockFile);
