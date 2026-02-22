<?php
/**
 * API: Preview Battle Report from Inbox
 * Returns file content for preview
 */

session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

requireModeratorAPI();

$db = getDB();
$userId = $_SESSION['user_id'];

// Get report ID
$reportId = $_GET['id'] ?? null;

if (!$reportId) {
    http_response_code(400);
    echo json_encode(['error' => 'Report ID erforderlich']);
    exit;
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
        http_response_code(404);
        echo json_encode(['error' => 'Report nicht gefunden']);
        exit;
    }
    
    // Read file content
    if (!file_exists($report['file_path'])) {
        throw new Exception('Report-Datei nicht gefunden');
    }
    
    $content = file_get_contents($report['file_path']);
    
    echo json_encode([
        'success' => true,
        'content' => $content
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
