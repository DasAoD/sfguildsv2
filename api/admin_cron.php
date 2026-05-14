<?php
/**
 * Admin API: Cron-Job Konfiguration
 * GET  → alle Jobs lesen
 * POST → Job aktualisieren (enabled, times)
 */
require_once __DIR__ . '/../includes/bootstrap_api.php';
requireAdminAPI();

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $jobs = $db->query("SELECT * FROM cron_jobs ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($jobs as &$job) {
        $job['times'] = json_decode($job['times'], true) ?? [];
    }
    jsonResponse(['success' => true, 'jobs' => $jobs]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $input = json_decode(file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        jsonError('Ungültige JSON-Daten', 400);
    }

    $jobKey = $input['job_key'] ?? null;
    if (!$jobKey) jsonError('job_key erforderlich', 400);

    $job = queryOne("SELECT id FROM cron_jobs WHERE job_key = ?", [$jobKey]);
    if (!$job) jsonError('Job nicht gefunden', 404);

    $fields = [];
    $params = [];

    if (isset($input['enabled'])) {
        $fields[] = 'enabled = ?';
        $params[] = $input['enabled'] ? 1 : 0;
    }

    if (isset($input['times'])) {
        $times = array_values(array_filter(array_map('trim', (array)$input['times'])));
        // Validierung: HH:MM Format
        foreach ($times as $t) {
            if (!preg_match('/^\d{2}:\d{2}$/', $t)) jsonError("Ungültige Zeit: {$t}", 400);
        }
        sort($times);
        $fields[] = 'times = ?';
        $params[] = json_encode($times);
    }

    if (empty($fields)) jsonError('Nichts zu aktualisieren', 400);

    $fields[] = "updated_at = datetime('now')";
    $params[] = $jobKey;

    execute("UPDATE cron_jobs SET " . implode(', ', $fields) . " WHERE job_key = ?", $params);

    logActivity('Cron konfiguriert', ['Job' => $jobKey, 'Änderung' => json_encode($input)]);
    jsonResponse(['success' => true]);
}

jsonError('Methode nicht erlaubt', 405);
