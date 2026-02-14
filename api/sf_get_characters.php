<?php
/**
 * API: Get S&F Characters
 * GET: Returns saved selected characters
 * POST: Fetches all characters from S&F (for test connection)
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/encryption.php';

header('Content-Type: application/json');

checkAuth();

$db = getDB();
$userId = $_SESSION['user_id'];

try {
    // POST: Test connection and fetch all characters
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $username = $input['username'] ?? '';
        $password = $input['password'] ?? '';
        
        if (empty($username) || empty($password)) {
            http_response_code(400);
            echo json_encode(['error' => 'Benutzername und Passwort erforderlich']);
            exit;
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
        
        // Parse output
        $characters = parseCharacterList($output);
		        
        // Enrich with guild names from database (only if not set by Rust tool)
        foreach ($characters as &$char) {
            // Skip if guild already set by Rust tool
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
            // If guild not in DB and not from Rust tool, it stays empty
        }
        
        echo json_encode([
            'success' => true,
            'characters' => $characters
        ]);
        exit;
    }
    
    // GET: Return saved selected characters
    $stmt = $db->prepare("SELECT sf_username, sf_password_encrypted, sf_iv, selected_characters FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user || !$user['sf_username'] || !$user['sf_password_encrypted']) {
        http_response_code(400);
        echo json_encode(['error' => 'Kein S&F Account verknüpft']);
        exit;
    }
    
    // Return selected characters if available
    /* DEAKTIVIERT - Lade immer alle vom Server
    if (!empty($user['selected_characters'])) {
        $selectedCharacters = json_decode($user['selected_characters'], true);
        
        echo json_encode([
            'success' => true,
            'characters' => $selectedCharacters,
            'from_selection' => true
        ]);
        exit;
    }
    */
    // Fallback: Fetch all characters if no selection saved
    $sfPassword = decryptData($user['sf_password_encrypted'], $user['sf_iv']);
    
    $cmd = sprintf(
        'env SSO_USERNAME=%s PASSWORD=%s /opt/sf-api/target/release/examples/list_chars 2>&1',
        escapeshellarg($user['sf_username']),
        escapeshellarg($sfPassword)
    );
    
    exec($cmd, $output, $returnCode);
    
    if ($returnCode !== 0) {
        throw new Exception('Fehler beim Abrufen der Charaktere: ' . implode("\n", $output));
    }
    
    // Parse output
    $characters = parseCharacterList($output);
    
    // Enrich with guild names from database (only if not set by Rust tool)
        foreach ($characters as &$char) {
            // Skip if guild already set by Rust tool
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
            // If guild not in DB and not from Rust tool, it stays empty
        }
    
    echo json_encode([
        'success' => true,
        'characters' => $characters,
        'from_selection' => false
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

/**
 * Parse character list from Rust output
 */
function parseCharacterList($output) {
    $characters = [];
    $currentChar = null;
    
    foreach ($output as $line) {
        $line = trim($line);
        
        // Stop parsing at mail section (not at header!)
        if (preg_match('/^=== Mail/', $line)) {
            break;
        }
        
        // Character #N startet einen neuen Character
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
    
    // Add last character
    if ($currentChar && isset($currentChar['name'])) {
        $characters[] = $currentChar;
    }
    
    // Filter out incomplete entries
    $characters = array_filter($characters, function($char) {
        return isset($char['name']) && isset($char['server']);
    });
    
    return array_values($characters);
}