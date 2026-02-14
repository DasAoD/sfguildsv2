<?php
/**
 * API: Save Selected Characters
 * Saves user's selected S&F characters for report fetching
 */

session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

checkAuth();

$db = getDB();
$userId = $_SESSION['user_id'];

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);
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
    
    // Save as JSON
    $stmt = $db->prepare("UPDATE users SET selected_characters = ? WHERE id = ?");
    $stmt->execute([json_encode($selectedCharacters), $userId]);
    
    echo json_encode([
        'success' => true,
        'count' => count($selectedCharacters)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}