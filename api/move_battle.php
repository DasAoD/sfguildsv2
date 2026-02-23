<?php
require_once '../config/database.php';
require_once '../includes/auth.php';

// Check authentication
requireAdminAPI();

// Get database connection
$db = getDB();

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);
$battleId = $input['battle_id'] ?? null;
$targetGuildId = $input['target_guild_id'] ?? null;

if (!$battleId || !$targetGuildId) {
    http_response_code(400);
    echo json_encode(['error' => 'Battle ID and target guild ID required']);
    exit;
}

try {
    // Update battle guild_id
    $stmt = $db->prepare("UPDATE sf_eval_battles SET guild_id = ? WHERE id = ?");
    $stmt->execute([$targetGuildId, $battleId]);
    
    echo json_encode(['success' => true]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
