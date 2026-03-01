<?php
require_once __DIR__ . '/../includes/bootstrap_api.php';

// Check authentication
checkAuth();

// Get database connection
$db = getDB();

// Get battle_id from query string
$battleId = $_GET['battle_id'] ?? null;

if (!$battleId) {
    jsonError('Battle ID required', 400);
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
    
    jsonResponse(['participated' => $participated, 'not_participated' => $notParticipated]);
    
} catch (PDOException $e) {
    logError('get_participants failed', ['error' => $e->getMessage()]);
    jsonError('Database error', 500);
}
