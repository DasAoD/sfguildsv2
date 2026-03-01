<?php
require_once __DIR__ . '/../includes/bootstrap_api.php';

// Check authentication
requireAdminAPI();

// Get database connection
$db = getDB();

// Get POST data
try {
    $input = json_decode(file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
} catch (JsonException $e) {
    jsonResponse(['success' => false, 'message' => 'Ungültige JSON-Daten'], 400);
}
$battleId = $input['battle_id'] ?? null;
$targetGuildId = $input['target_guild_id'] ?? null;

if (!$battleId || !$targetGuildId) {
    jsonError('Battle ID and target guild ID required', 400);
}

try {
    // Update battle guild_id
    $stmt = $db->prepare("UPDATE sf_eval_battles SET guild_id = ? WHERE id = ?");
    $stmt->execute([$targetGuildId, $battleId]);
    
    jsonResponse(['success' => true]);
    
} catch (PDOException $e) {
    logError('move_battle failed', ['error' => $e->getMessage()]);
    jsonError('Database error', 500);
}
