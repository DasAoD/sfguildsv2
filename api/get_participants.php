<?php
require_once '../config/database.php';
require_once '../includes/auth.php';

// Check authentication
checkAuth();

// Get database connection
$db = getDB();

// Get battle_id from query string
$battleId = $_GET['battle_id'] ?? null;

if (!$battleId) {
    http_response_code(400);
    echo json_encode(['error' => 'Battle ID required']);
    exit;
}

try {
    // Get participants
    $stmt = $db->prepare("
        SELECT 
            player_name,
            player_level,
            player_server_tag,
            participated
        FROM sf_eval_participants
        WHERE battle_id = ?
        ORDER BY player_name ASC
    ");
    $stmt->execute([$battleId]);
    $participants = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Split into participated and not participated
    $participated = [];
    $notParticipated = [];
    
    foreach ($participants as $participant) {
        if ($participant['participated'] == 1) {
            $participated[] = $participant;
        } else {
            $notParticipated[] = $participant;
        }
    }
    
    echo json_encode([
        'participated' => $participated,
        'not_participated' => $notParticipated
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
