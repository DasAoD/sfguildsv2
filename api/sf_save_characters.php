<?php
/**
 * API: Save Selected Characters
 * Saves user's selected S&F characters for a specific account
 * 
 * POST Body:
 *   account_id - SF account to save characters for
 *   characters - Array of selected characters
 */
require_once __DIR__ . '/../includes/bootstrap_api.php';

header('Content-Type: application/json');

checkAuth();

$db = getDB();
$userId = $_SESSION['user_id'];

try {
    $input = json_decode(file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
} catch (JsonException $e) {
    jsonResponse(['success' => false, 'message' => 'Ungültige JSON-Daten'], 400);
}
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
        // Spezifischen Account speichern
        $stmt = $db->prepare("UPDATE sf_accounts SET selected_characters = ?, updated_at = datetime('now') WHERE id = ? AND user_id = ?");
        $stmt->execute([$json, $accountId, $userId]);

        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['error' => 'Account nicht gefunden']);
            exit;
        }
    } else {
        // Kein account_id → Standard-Account verwenden
        $stmt = $db->prepare("UPDATE sf_accounts SET selected_characters = ?, updated_at = datetime('now') WHERE user_id = ? AND is_default = 1");
        $stmt->execute([$json, $userId]);

        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['error' => 'Kein Standard-Account gefunden']);
            exit;
        }
    }
    
    echo json_encode([
        'success' => true,
        'count' => count($selectedCharacters)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    logError('sf_save_characters failed', ['error' => $e->getMessage()]);
    echo json_encode(['error' => 'Interner Fehler']);
}