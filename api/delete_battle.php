<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';

// Check authentication
requireAdminAPI();

// Get database connection
$db = getDB();

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);
$battleId = $input['battle_id'] ?? null;

if (!$battleId) {
    http_response_code(400);
    echo json_encode(['error' => 'Battle ID required']);
    exit;
}

try {
    // Delete battle (participants will be deleted automatically due to CASCADE)
    $stmt = $db->prepare("DELETE FROM sf_eval_battles WHERE id = ?");
    $stmt->execute([$battleId]);
    
    echo json_encode(['success' => true]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
