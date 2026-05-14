<?php
/**
 * Admin API: Cron-Job sofort ausführen (asynchron)
 * POST { job_key: "fetch_reports" | "member_sync" }
 * Startet den Job als Hintergrundprozess und antwortet sofort.
 */
require_once __DIR__ . '/../includes/bootstrap_api.php';
require_once __DIR__ . '/../includes/logger.php';
requireAdminAPI();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Nur POST erlaubt', 405);

try {
    $input = json_decode(file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
} catch (JsonException $e) { jsonError('Ungültige JSON-Daten', 400); }

$jobKey = $input['job_key'] ?? null;
$allowed = ['fetch_reports', 'member_sync'];
if (!$jobKey || !in_array($jobKey, $allowed)) jsonError('Unbekannter Job', 400);

// Lock prüfen — läuft bereits?
$lockFile = sys_get_temp_dir() . '/sfguilds_cron_' . $jobKey . '.lock';
$lockFh = fopen($lockFile, 'c');
if (!flock($lockFh, LOCK_EX | LOCK_NB)) {
    fclose($lockFh);
    jsonError('Job läuft bereits — bitte warten', 409);
}
flock($lockFh, LOCK_UN);
fclose($lockFh);

// Hintergrundprozess starten
$cmd = PHP_BINDIR . '/php ' . escapeshellarg(__DIR__ . '/../cli/cron_runner_single.php') . ' ' . escapeshellarg($jobKey) . ' > /dev/null 2>&1 &';
exec($cmd);

logActivity('Cron manuell gestartet', ['Job' => $jobKey]);
jsonResponse(['success' => true, 'message' => 'Job gestartet — Ergebnis in wenigen Minuten im Status sichtbar.']);
