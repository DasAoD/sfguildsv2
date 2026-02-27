<?php
/**
 * API: Fetch Battle Reports (Multi-Account, Parallel)
 * Fetches reports from selected characters across all SF accounts
 * 
 * POST Body (optional):
 *   account_ids - Array of specific account IDs to fetch from (empty = all)
 */
require_once __DIR__ . '/../includes/bootstrap_api.php';

set_time_limit(300);
ini_set('max_execution_time', '300');

require_once __DIR__ . '/../includes/sf_helpers.php';

header('Content-Type: application/json');

requireModeratorAPI();

$db = getDB();
$userId = $_SESSION['user_id'];

// Lock: verhindert parallele Fetches desselben Users
// flock() ist atomar – kein Race-Condition zwischen check und set
// Lock-Datei enthält PID + Timestamp für Stale-Lock-Erkennung
$lockDir  = __DIR__ . '/../storage/locks';
$lockFile = $lockDir . '/fetch_user_' . $userId . '.lock';
if (!is_dir($lockDir)) {
    mkdir($lockDir, 0775, true);
}
$lockFh = fopen($lockFile, 'c');
if (!$lockFh) {
    http_response_code(500);
    echo json_encode(['error' => 'Lock-Datei konnte nicht geöffnet werden']);
    exit;
}
if (!flock($lockFh, LOCK_EX | LOCK_NB)) {
    // Lock gehalten – prüfen ob Prozess noch lebt (Stale-Lock nach 180s)
    $meta = json_decode(fread($lockFh, 256), true);
    $stale = !isset($meta['pid']) || !isset($meta['ts'])
        || (time() - $meta['ts']) > 180
        || !file_exists('/proc/' . $meta['pid']);
    if (!$stale) {
        fclose($lockFh);
        http_response_code(429);
        echo json_encode(['error' => 'Fetch läuft bereits – bitte warten']);
        exit;
    }
    // Stale Lock: überschreiben
    flock($lockFh, LOCK_EX);
}
// Lock gehalten – PID + Timestamp reinschreiben
ftruncate($lockFh, 0);
rewind($lockFh);
fwrite($lockFh, json_encode(['pid' => getmypid(), 'ts' => time()]));
fflush($lockFh);
register_shutdown_function(function() use ($lockFh, $lockFile) {
    flock($lockFh, LOCK_UN);
    fclose($lockFh);
    @unlink($lockFile);
});

$input = json_decode(file_get_contents('php://input'), true);
$requestedAccountIds = $input['account_ids'] ?? [];
$singleServer = $input['server'] ?? null;
$singleCharacter = $input['character'] ?? null;

try {
    // Get accounts to fetch from
    if ($singleServer && $singleCharacter) {
        // Legacy single-character mode - find the account
        $accounts = getDefaultAccounts($db, $userId);
    } elseif (!empty($requestedAccountIds)) {
        // Specific accounts requested
        $placeholders = implode(',', array_fill(0, count($requestedAccountIds), '?'));
        $params = array_merge($requestedAccountIds, [$userId]);
        $stmt = $db->prepare("
            SELECT id, account_name, sf_username, sf_password_encrypted, sf_iv, sf_hmac, selected_characters
            FROM sf_accounts 
            WHERE id IN ($placeholders) AND user_id = ?
        ");
        $stmt->execute($params);
        $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // All accounts with selected characters
        $stmt = $db->prepare("
            SELECT id, account_name, sf_username, sf_password_encrypted, sf_iv, sf_hmac, selected_characters
            FROM sf_accounts 
            WHERE user_id = ? AND selected_characters IS NOT NULL AND selected_characters != '[]'
        ");
        $stmt->execute([$userId]);
        $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    if (empty($accounts)) {
        throw new Exception('Keine Accounts mit ausgewählten Charakteren gefunden');
    }
    
    // Collect all characters to fetch across all accounts
    $allResults = [];
    $totalCount = 0;
    
    foreach ($accounts as $account) {
        $sfPassword = decryptData($account['sf_password_encrypted'], $account['sf_iv'], $account['sf_hmac'] ?? null);
        
        if ($singleServer && $singleCharacter) {
            $charactersToFetch = [['name' => $singleCharacter, 'server' => $singleServer, 'guild' => 'Unbekannt']];
        } else {
            $charactersToFetch = json_decode($account['selected_characters'], true);
            if (empty($charactersToFetch)) {
                continue;
            }
        }
        
        // Start parallel processes for this account's characters
        $processes = [];
        $pipes = [];
        
        foreach ($charactersToFetch as $char) {
            $charJson = json_encode($char);
            
            // Array-Command: keine Shell involviert, kein Escaping nötig
            // Passwort über env-Array übergeben (nicht in argv – sonst in ps aux sichtbar)
            // PHP_BINDIR statt PHP_BINARY: im FPM-Kontext zeigt PHP_BINARY auf php-fpm,
            // der Subprocess braucht aber den CLI-Binary
            $cmd = [
                PHP_BINDIR . '/php',
                __DIR__ . '/sf_fetch_single.php',
                $charJson,
                $userId,
                $account['sf_username'],
            ];

            $procEnv = array_merge($_ENV, ['SF_PASSWORD' => $sfPassword]);

            $descriptorspec = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w']
            ];

            $process = proc_open($cmd, $descriptorspec, $procPipes, null, $procEnv);
            if (!is_resource($process)) {
                logError('proc_open failed for character', ['char' => $char['name']]);
                continue;
            }
            $processes[] = $process;
            $pipes[] = $procPipes;
            fclose($procPipes[0]);
        }
        
        // Wait for all processes and collect results (Hard-Timeout: 90s pro Prozess)
        $accountResults = [];
        $fetchTimeout = 90;
        
        foreach ($processes as $i => $process) {
            $startTime = time();
            $output = '';
            $stream = $pipes[$i][1];
            stream_set_blocking($stream, false);

            while (!feof($stream)) {
                if ((time() - $startTime) > $fetchTimeout) {
                    proc_terminate($process, 9);
                    logError('Fetch-Timeout: Prozess abgebrochen', [
                        'char' => $charactersToFetch[$i]['name'] ?? 'Unbekannt',
                        'timeout' => $fetchTimeout
                    ]);
                    break;
                }
                $r = [$stream]; $w = []; $e = []; $ready = stream_select($r, $w, $e, 1);
                if ($ready) {
                    $chunk = fread($stream, 8192);
                    if ($chunk !== false) { $output .= $chunk; }
                }
            }
            fclose($pipes[$i][1]);

            $errors = stream_get_contents($pipes[$i][2]);
            fclose($pipes[$i][2]);
            
            proc_close($process);
            
            $result = json_decode($output, true);
            
            if ($result) {
                $accountResults[] = $result;
                if ($result['success']) {
                    $totalCount += $result['count'];
                }
            } else {
                $accountResults[] = [
                    'success' => false,
                    'character' => $charactersToFetch[$i]['name'] ?? 'Unbekannt',
                    'server' => $charactersToFetch[$i]['server'] ?? '',
                    'guild' => $charactersToFetch[$i]['guild'] ?? 'Unbekannt',
                    'count' => 0,
                    'error' => $errors ?: 'Unbekannter Fehler'
                ];
            }
        }
        
        $allResults[] = [
            'account_name' => $account['account_name'] ?? $account['sf_username'],
            'account_id' => $account['id'],
            'results' => $accountResults
        ];
    }
    
    echo json_encode([
        'success' => true,
        'total' => $totalCount,
        'accounts' => $allResults,
        // Flattened results for backward compatibility
        'results' => array_merge(...array_column($allResults, 'results'))
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    logError('sf_fetch_reports failed', ['error' => $e->getMessage()]);
    echo json_encode(['error' => 'Interner Fehler']);
}

/**
 * Get legacy account from users table (backward compatibility)
 */
function getDefaultAccounts($db, $userId) {
    $stmt = $db->prepare("
        SELECT id, account_name, sf_username, sf_password_encrypted, sf_iv, sf_hmac, selected_characters
        FROM sf_accounts WHERE user_id = ? AND is_default = 1
    ");
    $stmt->execute([$userId]);
    $account = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$account || !$account['sf_password_encrypted']) {
        throw new Exception('Keine S&F Credentials gefunden');
    }

    return [$account];
}