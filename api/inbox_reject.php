<?php
/**
 * API: Reject Battle Reports from Inbox
 * Marks reports as rejected (won't be imported)
 */

session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

requireModeratorAPI();

$db = getDB();
$userId = $_SESSION['user_id'];

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);
$reportIds = $input['report_ids'] ?? [];

if (empty($reportIds) || !is_array($reportIds)) {
    http_response_code(400);
    echo json_encode(['error' => 'Keine Reports ausgewählt']);
    exit;
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
    
    echo json_encode([
        'success' => true,
        'rejected' => $affected,
        'message' => "$affected Berichte abgelehnt"
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    logError('inbox_reject failed', ['error' => $e->getMessage()]);
    echo json_encode(['error' => 'Interner Fehler']);
}
