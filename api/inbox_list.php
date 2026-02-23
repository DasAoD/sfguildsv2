<?php
/**
 * API: List Battle Inbox
 * Returns pending battle reports for review
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

requireModeratorAPI();

$db = getDB();
$userId = $_SESSION['user_id'];

try {
    // Get status filter (default: pending)
    $status = $_GET['status'] ?? 'pending';
    
    $stmt = $db->prepare("
        SELECT 
            i.id,
            i.message_id,
            i.opponent_guild,
            i.battle_type,
            i.battle_date,
            i.battle_time,
            i.server,
            i.character_name,
            i.status,
            i.created_at,
            g.id as guild_id,
            g.name as guild_name
        FROM battle_inbox i
        JOIN guilds g ON i.guild_id = g.id
        WHERE i.user_id = ? 
        AND i.status = ?
        AND NOT EXISTS (
            SELECT 1 FROM sf_eval_battles b
            WHERE b.message_id = i.message_id 
            OR (
                b.guild_id = i.guild_id 
                AND b.battle_date = i.battle_date 
                AND b.battle_time = i.battle_time 
                AND b.opponent_guild = i.opponent_guild
            )
        )
        ORDER BY i.battle_date DESC, i.battle_time DESC
    ");
    
    $stmt->execute([$userId, $status]);
    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'reports' => $reports,
        'count' => count($reports)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    logError('inbox_list failed', ['error' => $e->getMessage()]);
    echo json_encode(['error' => 'Interner Fehler']);
}
