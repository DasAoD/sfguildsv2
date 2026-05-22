<?php
/**
 * Cron Runner — wird jede Minute via crontab aufgerufen.
 * Liest konfigurierte Jobs aus der DB und führt fällige aus.
 *
 * Usage: php /var/www/sfguildsv2/cli/cron_runner.php
 */
if (PHP_SAPI !== 'cli') { exit(1); }

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/logger.php';

$db = getDB();
$now = new DateTimeImmutable('now', new DateTimeZone('Europe/Berlin'));
$currentTime = $now->format('H:i');

$jobs = $db->query("SELECT * FROM cron_jobs WHERE enabled = 1")->fetchAll(PDO::FETCH_ASSOC);

foreach ($jobs as $job) {
    $times = json_decode($job['times'], true) ?? [];
    if (!in_array($currentTime, $times, true)) continue;

    // Lock: verhindert Doppelausführung bei langem Vorjob
    $lockFile = sys_get_temp_dir() . '/sfguilds_cron_' . $job['job_key'] . '.lock';
    $lockFh = fopen($lockFile, 'c');
    if (!flock($lockFh, LOCK_EX | LOCK_NB)) {
        echo "[{$job['job_key']}] Bereits in Ausführung, übersprungen.\n";
        fclose($lockFh);
        continue;
    }

    // Werte vor require sichern — cron_fetch_reports.php überschreibt
    // $job und $jobs im selben Scope (foreach ($jobs as $job))
    $jobKey   = $job['job_key'];
    $jobLabel = $job['label'];

    echo "[{$currentTime}] Starte Job: {$jobLabel}\n";
    $start = microtime(true);

    $scriptMap = [
        'fetch_reports' => __DIR__ . '/cron_fetch_reports.php',
        'member_sync'   => __DIR__ . '/cron_member_sync.php',
    ];

    $script = $scriptMap[$jobKey] ?? null;
    $status = 'error';
    $message = 'Unbekannter Job';

    if ($script && file_exists($script)) {
        ob_start();
        $result = require $script;
        $output = ob_get_clean();
        echo $output;

        if (is_array($result)) {
            $status  = $result['errors'] === 0 ? 'success' : ($result['success'] > 0 ? 'partial' : 'error');
            $message = "{$result['success']}/{$result['total']} erfolgreich";
            if ($result['errors'] > 0) $message .= ", {$result['errors']} Fehler";
        } else {
            $status  = 'success';
            $message = 'Abgeschlossen';
        }
    } else {
        echo "Script nicht gefunden: {$script}\n";
    }

    $duration = round(microtime(true) - $start, 1);
    echo "[{$jobKey}] Fertig in {$duration}s — {$message}\n";

    // Status in DB schreiben
    $db->prepare("
        UPDATE cron_jobs
        SET last_run_at = :ts, last_run_status = :status, last_run_message = :msg, updated_at = datetime('now')
        WHERE job_key = :key
    ")->execute([
        ':ts'     => gmdate('c'),
        ':status' => $status,
        ':msg'    => $message,
        ':key'    => $jobKey,
    ]);

    logActivity('Cron-Job', ['Job' => $jobLabel, 'Status' => $status, 'Ergebnis' => $message]);

    flock($lockFh, LOCK_UN);
    fclose($lockFh);
    @unlink($lockFile);
}
