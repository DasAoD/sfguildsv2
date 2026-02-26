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
