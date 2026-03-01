<?php
/**
 * API: Reject Battle Reports from Inbox
 * Marks reports as rejected (won't be imported)
 */
require_once __DIR__ . '/../includes/bootstrap_api.php';

header('Content-Type: application/json');

requireModeratorAPI();

$db = getDB();
$userId = $_SESSION['user_id'];

// Get POST data
try {
    $input = json_decode(file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
} catch (JsonException $e) {
    jsonResponse(['success' => false, 'message' => 'Ungültige JSON-Daten'], 400);
}
$reportIds = $input['report_ids'] ?? [];

if (empty($reportIds) || !is_array($reportIds)) {
    jsonError('Keine Reports ausgewählt', 400);
}

try {
    $placeholders = implode(',', array_fill(0, count($reportIds), '?'));
    
    $stmt = $db->prepare("
        UPDATE battle_inbox 
        SET status = 'rejected',
            reviewed_at = datetime('now')
        WHERE id IN ($placeholders) 
        AND user_id = ?
        AND status = 'pending'
    ");
    
    $params = array_merge($reportIds, [$userId]);
    $stmt->execute($params);
    
    $affected = $stmt->rowCount();
    
    jsonResponse(['success' => true, 'rejected' => $affected, 'message' => "$affected Berichte abgelehnt"]);
    
} catch (Exception $e) {
    logError('inbox_reject failed', ['error' => $e->getMessage()]);
    jsonError('Interner Fehler', 500);
}
