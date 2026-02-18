<?php
/**
 * API: Save Selected Characters
 * Saves user's selected S&F characters for a specific account
 * 
 * POST Body:
 *   account_id - SF account to save characters for
 *   characters - Array of selected characters
 */

session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

checkAuth();

$db = getDB();
$userId = $_SESSION['user_id'];

$input = json_decode(file_get_contents('php://input'), true);
$accountId = $input['account_id'] ?? null;
$selectedCharacters = $input['characters'] ?? [];

if (!is_array($selectedCharacters)) {
    http_response_code(400);
    echo json_encode(['error' => 'Ungültige Daten']);
    exit;
}

try {
    // Validate character data
    foreach ($selectedCharacters as $char) {
        if (!isset($char['name']) || !isset($char['server'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Ungültige Charakter-Daten']);
            exit;
        }
    }
    
    $json = json_encode($selectedCharacters);
    
    if ($accountId) {
        // Save to sf_accounts table
        $stmt = $db->prepare("UPDATE sf_accounts SET selected_characters = ?, updated_at = datetime('now') WHERE id = ? AND user_id = ?");
        $stmt->execute([$json, $accountId, $userId]);
        
        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['error' => 'Account nicht gefunden']);
            exit;
        }
    } else {
        // Legacy: Save to users table (backward compatibility)
        $stmt = $db->prepare("UPDATE users SET selected_characters = ? WHERE id = ?");
        $stmt->execute([$json, $userId]);
    }
    
    echo json_encode([
        'success' => true,
        'count' => count($selectedCharacters)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
