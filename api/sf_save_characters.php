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
    jsonError('Ungültige Daten', 400);



}

try {
    // Validate character data
    foreach ($selectedCharacters as $char) {
        if (!isset($char['name']) || !isset($char['server'])) {
            jsonError('Ungültige Charakter-Daten', 400);
        }
    }
    
    $json = json_encode($selectedCharacters);
    
    if ($accountId) {
        // Spezifischen Account speichern
        $stmt = $db->prepare("UPDATE sf_accounts SET selected_characters = ?, updated_at = datetime('now') WHERE id = ? AND user_id = ?");
        $stmt->execute([$json, $accountId, $userId]);

        if ($stmt->rowCount() === 0) {
            jsonError('Account nicht gefunden', 404);
        }
    } else {
        // Kein account_id → Standard-Account verwenden
        $stmt = $db->prepare("UPDATE sf_accounts SET selected_characters = ?, updated_at = datetime('now') WHERE user_id = ? AND is_default = 1");
        $stmt->execute([$json, $userId]);

        if ($stmt->rowCount() === 0) {
            jsonError('Kein Standard-Account gefunden', 404);
        }
    }
    
    jsonResponse(['success' => true, 'count' => count($selectedCharacters)]);
    
} catch (Throwable $e) {
    logError('sf_save_characters failed', ['error' => $e->getMessage()]);
    jsonError('Interner Fehler', 500);
}