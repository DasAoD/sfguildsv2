<?php
/**
 * Cron: Kampfberichte abrufen (alle Accounts)
 * Wird von cron_runner.php aufgerufen.
 */
if (PHP_SAPI !== 'cli') { exit(1); }

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/sf_helpers.php';
require_once __DIR__ . '/../includes/logger.php';

$db = getDB();

// Alle Accounts mit ausgewählten Charakteren holen
$stmt = $db->query("
    SELECT a.id, a.user_id, a.sf_username, a.sf_password_encrypted, a.sf_iv, a.sf_hmac, a.selected_characters
    FROM sf_accounts a
    JOIN users u ON u.id = a.user_id
    WHERE u.role = 'admin'
      AND a.selected_characters IS NOT NULL AND a.selected_characters != '[]'
");
$accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($accounts)) {
    echo "Keine Accounts konfiguriert.\n";
    return ['total' => 0, 'success' => 0, 'errors' => 0];
}

$total = 0; $success = 0; $errors = 0;

foreach ($accounts as $account) {
    $sfPassword = decryptData(
        $account['sf_password_encrypted'],
        $account['sf_iv'],
        $account['sf_hmac'] ?? null
    );
    $chars = json_decode($account['selected_characters'], true) ?? [];

    $jobs = [];
    foreach ($chars as $char) {
        $cmd = [
            PHP_BINDIR . '/php',
            __DIR__ . '/../api/sf_fetch_single.php',
            json_encode($char),
            $account['user_id'],
            $account['sf_username'],
        ];
        $env = array_merge($_ENV, ['SF_PASSWORD' => $sfPassword]);
        $spec = [0 => ['pipe','r'], 1 => ['pipe','w'], 2 => ['pipe','w']];
        $proc = proc_open($cmd, $spec, $pipes, null, $env);
        if (!is_resource($proc)) { $errors++; continue; }
        fclose($pipes[0]);
        $jobs[] = ['proc' => $proc, 'pipes' => $pipes, 'char' => $char['name']];
    }

    foreach ($jobs as $job) {
        $out = '';
        $start = time();
        $stream = $job['pipes'][1];
        stream_set_blocking($stream, false);
        while (!feof($stream)) {
            if ((time() - $start) > 90) { proc_terminate($job['proc'], 9); break; }
            $r = [$stream]; $w = []; $e = [];
            if (stream_select($r, $w, $e, 1)) {
                $chunk = fread($stream, 8192);
                if ($chunk !== false) $out .= $chunk;
            }
        }
        fclose($job['pipes'][1]);
        fclose($job['pipes'][2]);
        proc_close($job['proc']);

        $result = json_decode($out, true);
        $total++;
        if ($result && $result['success']) {
            $success++;
            echo "✓ {$job['char']}: {$result['count']} Berichte\n";
        } else {
            $errors++;
            $err = $result['error'] ?? 'Unbekannt';
            echo "✗ {$job['char']}: {$err}\n";
        }
    }
}

return ['total' => $total, 'success' => $success, 'errors' => $errors];
