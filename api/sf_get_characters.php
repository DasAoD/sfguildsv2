<?php
/**
 * API: Get S&F Characters
 * GET: Returns characters for a specific account (or all accounts)
 * POST: Fetches all characters from S&F (test connection with new credentials)
 * 
 * GET Parameters:
 *   account_id - (optional) Specific account to load characters for
 * 
 * POST Body:
 *   username, password - Fresh credentials to test
 *   account_id - (optional) Existing account to refresh characters for
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/encryption.php';

header('Content-Type: application/json');

checkAuth();

$db = getDB();
$userId = $_SESSION['user_id'];

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        handlePost($db, $userId);
    } else {
        handleGet($db, $userId);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

/**
 * POST: Test connection and fetch all characters
 */
function handlePost($db, $userId) {
    $input = json_decode(file_get_contents('php://input'), true);
    $username = $input['username'] ?? '';
    $password = $input['password'] ?? '';
    $accountId = $input['account_id'] ?? null;
    
    // If account_id provided but no credentials, use stored ones
    if ($accountId && (empty($username) || empty($password))) {
        $stmt = $db->prepare("SELECT sf_username, sf_password_encrypted, sf_iv FROM sf_accounts WHERE id = ? AND user_id = ?");
        $stmt->execute([$accountId, $userId]);
        $account = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$account) {
            http_response_code(404);
            echo json_encode(['error' => 'Account nicht gefunden']);
            return;
        }
        
        $username = $account['sf_username'];
        $password = decryptData($account['sf_password_encrypted'], $account['sf_iv']);
    }
    
    if (empty($username) || empty($password)) {
        http_response_code(400);
        echo json_encode(['error' => 'Benutzername und Passwort erforderlich']);
        return;
    }
    
    // Run Rust list_chars script
    $cmd = sprintf(
        'env SSO_USERNAME=%s PASSWORD=%s /opt/sf-api/target/release/examples/list_chars 2>&1',
        escapeshellarg($username),
        escapeshellarg($password)
    );
    
    exec($cmd, $output, $returnCode);
    
    if ($returnCode !== 0) {
        throw new Exception('Fehler beim Abrufen der Charaktere: ' . implode("\n", $output));
    }
    
    $characters = parseCharacterList($output);
    
    // Enrich with guild names from database
    enrichWithGuildNames($characters, $db, $userId);
    
    echo json_encode([
        'success' => true,
        'characters' => $characters
    ]);
}

/**
 * GET: Return characters for stored account(s)
 */
function handleGet($db, $userId) {
    $accountId = $_GET['account_id'] ?? null;
    
    if ($accountId) {
        // Specific account
        $stmt = $db->prepare("SELECT sf_username, sf_password_encrypted, sf_iv, selected_characters FROM sf_accounts WHERE id = ? AND user_id = ?");
        $stmt->execute([$accountId, $userId]);
        $account = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$account) {
            http_response_code(404);
            echo json_encode(['error' => 'Account nicht gefunden']);
            return;
        }
    } else {
        // Fallback: Try legacy users table, then default account
        $stmt = $db->prepare("SELECT sf_username, sf_password_encrypted, sf_iv, selected_characters FROM sf_accounts WHERE user_id = ? AND is_default = 1");
        $stmt->execute([$userId]);
        $account = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$account) {
            // Legacy fallback
            $stmt = $db->prepare("SELECT sf_username, sf_password_encrypted, sf_iv, selected_characters FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $account = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        if (!$account || !$account['sf_username'] || !$account['sf_password_encrypted']) {
            http_response_code(400);
            echo json_encode(['error' => 'Kein S&F Account verknüpft']);
            return;
        }
    }
    
    // Decrypt and fetch characters from S&F
    $sfPassword = decryptData($account['sf_password_encrypted'], $account['sf_iv']);
    
    $cmd = sprintf(
        'env SSO_USERNAME=%s PASSWORD=%s /opt/sf-api/target/release/examples/list_chars 2>&1',
        escapeshellarg($account['sf_username']),
        escapeshellarg($sfPassword)
    );
    
    exec($cmd, $output, $returnCode);
    
    if ($returnCode !== 0) {
        throw new Exception('Fehler beim Abrufen der Charaktere: ' . implode("\n", $output));
    }
    
    $characters = parseCharacterList($output);
    enrichWithGuildNames($characters, $db, $userId);
    
    echo json_encode([
        'success' => true,
        'characters' => $characters,
        'from_selection' => false
    ]);
}

/**
 * Enrich characters with guild names from database
 */
function enrichWithGuildNames(&$characters, $db, $userId) {
    foreach ($characters as &$char) {
        if (!empty($char['guild'])) {
            continue;
        }
        
        $stmt = $db->prepare("
            SELECT DISTINCT g.name
            FROM battle_inbox bi
            JOIN guilds g ON bi.guild_id = g.id
            WHERE bi.character_name = ? AND bi.user_id = ?
            LIMIT 1
        ");
        $stmt->execute([$char['name'], $userId]);
        $guild = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($guild) {
            $char['guild'] = $guild['name'];
        }
    }
}

/**
 * Parse character list from Rust output
 */
function parseCharacterList($output) {
    $characters = [];
    $currentChar = null;
    
    foreach ($output as $line) {
        $line = trim($line);
        
        if (preg_match('/^=== Mail/', $line)) {
            break;
        }
        
        if (preg_match('/^Character #\d+$/i', $line)) {
            if ($currentChar && isset($currentChar['name'])) {
                $characters[] = $currentChar;
            }
            $currentChar = [];
            continue;
        }
        
        if (preg_match('/^Server:\s*https?:\/\/([^\/]+)/i', $line, $m)) {
            if ($currentChar !== null) {
                $currentChar['server'] = $m[1];
            }
        } elseif (preg_match('/^Name:\s*(.+)$/i', $line, $m)) {
            if ($currentChar !== null) {
                $currentChar['name'] = trim($m[1]);
            }
        } elseif (preg_match('/^Level:\s*(\d+)$/i', $line, $m)) {
            if ($currentChar !== null) {
                $currentChar['level'] = (int)$m[1];
            }
        } elseif (preg_match('/^\s*Guild:\s*(.+)$/i', $line, $m)) {
            if ($currentChar !== null) {
                $currentChar['guild'] = trim($m[1]);
            }
        }
    }
    
    if ($currentChar && isset($currentChar['name'])) {
        $characters[] = $currentChar;
    }
    
    $characters = array_filter($characters, function($char) {
        return isset($char['name']) && isset($char['server']);
    });
    
    return array_values($characters);
}
