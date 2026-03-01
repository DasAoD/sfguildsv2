<?php
/**
 * API: Preview Battle Report from Inbox
 * Returns file content for preview
 */
require_once __DIR__ . '/../includes/bootstrap_api.php';

require_once __DIR__ . '/../includes/sf_helpers.php';

header('Content-Type: application/json');

requireModeratorAPI();

$db = getDB();
$userId = $_SESSION['user_id'];

// Get report ID
$reportId = $_GET['id'] ?? null;

if (!$reportId) {
    jsonError('Report ID erforderlich', 400);
}

try {
    // Get report (ensure it belongs to this user)
    $stmt = $db->prepare("
        SELECT file_path 
        FROM battle_inbox 
        WHERE id = ? AND user_id = ?
    ");
    
    $stmt->execute([$reportId, $userId]);
    $report = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$report) {
        jsonError('Report nicht gefunden', 404);
    }
    
    // Read file content
    if (!file_exists($report['file_path'])) {
        throw new Exception('Report-Datei nicht gefunden');
    }
    
    $content = file_get_contents($report['file_path']);
    
    jsonResponse(['success' => true, 'content' => $content]);
    
} catch (Throwable $e) {
    logError('inbox_preview failed', ['error' => $e->getMessage()]);
    jsonError('Interner Fehler', 500);
}
