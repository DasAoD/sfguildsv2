<?php
/**
 * API: Fetch Battle Reports (Multi-Account, Parallel)
 * Fetches reports from selected characters across all SF accounts
 * 
 * POST Body (optional):
 *   account_ids - Array of specific account IDs to fetch from (empty = all)
 */

set_time_limit(300);
ini_set('max_execution_time', '300');

session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/sf_helpers.php';

header('Content-Type: application/json');

checkAuth();

$db = getDB();
$userId = $_SESSION['user_id'];

$input = json_decode(file_get_contents('php://input'), true);
$requestedAccountIds = $input['account_ids'] ?? [];
$singleServer = $input['server'] ?? null;
$singleCharacter = $input['character'] ?? null;

try {
    // Get accounts to fetch from
    if ($singleServer && $singleCharacter) {
        // Legacy single-character mode - find the account
        $accounts = getLegacyAccount($db, $userId);
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
            
            $cmd = sprintf(
                'php %s/sf_fetch_single.php %s %s %s %s 2>&1',
                escapeshellarg(__DIR__),
                escapeshellarg($charJson),
                escapeshellarg($userId),
                escapeshellarg($account['sf_username']),
                escapeshellarg($sfPassword)
            );
            
            $descriptorspec = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w']
            ];
            
            $process = proc_open($cmd, $descriptorspec, $procPipes);
            
            if (is_resource($process)) {
                $processes[] = $process;
                $pipes[] = $procPipes;
                fclose($procPipes[0]);
            }
        }
        
        // Wait for all processes and collect results
        $accountResults = [];
        
        foreach ($processes as $i => $process) {
            $output = stream_get_contents($pipes[$i][1]);
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
    echo json_encode(['error' => $e->getMessage()]);
}

/**
 * Get legacy account from users table (backward compatibility)
 */
function getLegacyAccount($db, $userId) {
    // Try sf_accounts first
    $stmt = $db->prepare("
        SELECT id, account_name, sf_username, sf_password_encrypted, sf_iv, sf_hmac, selected_characters
        FROM sf_accounts WHERE user_id = ? AND is_default = 1
    ");
    $stmt->execute([$userId]);
    $account = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($account) {
        return [$account];
    }
    
    // Fallback to users table
    $stmt = $db->prepare("SELECT sf_username, sf_password_encrypted, sf_iv, sf_hmac, selected_characters FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user || !$user['sf_password_encrypted']) {
        throw new Exception('Keine S&F Credentials gefunden');
    }
    
    return [[
        'id' => 0,
        'account_name' => 'Legacy',
        'sf_username' => $user['sf_username'],
        'sf_password_encrypted' => $user['sf_password_encrypted'],
        'sf_iv' => $user['sf_iv'],
            'sf_hmac' => $user['sf_hmac'] ?? null,
        'selected_characters' => $user['selected_characters']
    ]];
}
