<?php
/**
 * API: Import Battle Reports from Inbox
 * Imports selected reports from inbox to sf_eval_battles
 */

session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/sf_helpers.php';

header('Content-Type: application/json');

checkAuth();

$db = getDB();
$userId = $_SESSION['user_id'];

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);
$reportIds = $input['report_ids'] ?? [];

if (empty($reportIds) || !is_array($reportIds)) {
    http_response_code(400);
    echo json_encode(['error' => 'Keine Reports ausgewählt']);
    exit;
}

try {
    $db->beginTransaction();
    
    $imported = 0;
    $errors = [];
    
    foreach ($reportIds as $reportId) {
        try {
            // Get inbox report
            $stmt = $db->prepare("
                SELECT id, user_id, guild_id, message_id, file_path, status,
                       battle_date, battle_time, battle_type, opponent_guild FROM battle_inbox 
                WHERE id = ? AND user_id = ? AND status = 'pending'
            ");
            $stmt->execute([$reportId, $userId]);
            $inboxReport = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$inboxReport) {
                $errors[] = "Report ID $reportId nicht gefunden oder bereits verarbeitet";
                continue;
            }
            
            // Read and parse file
            $content = file_get_contents($inboxReport['file_path']);
            $parsed = parseRustBattleReport($content);
            
            if (!$parsed) {
                $errors[] = "Report ID $reportId: Parsing fehlgeschlagen";
                continue;
            }
            
            // Check if battle already exists by message_id
            $stmt = $db->prepare("SELECT id FROM sf_eval_battles WHERE message_id = ?");
            $stmt->execute([$inboxReport['message_id']]);
            if ($stmt->fetch()) {
                $errors[] = "Report ID $reportId: Bereits importiert (message_id)";
                continue;
            }
            
            // ERWEITERT: Check by date+time+opponent (für alte Imports ohne message_id)
            $stmt = $db->prepare("
                SELECT id FROM sf_eval_battles 
                WHERE guild_id = ? 
                AND battle_date = ? 
                AND battle_time = ? 
                AND opponent_guild = ?
            ");
            $stmt->execute([
                $inboxReport['guild_id'],
                $inboxReport['battle_date'],
                $inboxReport['battle_time'],
                $inboxReport['opponent_guild']
            ]);
            if ($stmt->fetch()) {
                $errors[] = "Report ID $reportId: Bereits importiert (Datum/Zeit/Gegner)";
                continue;
            }
            
            // Insert battle
            $stmt = $db->prepare("
                INSERT INTO sf_eval_battles 
                (guild_id, battle_type, opponent_guild, battle_date, battle_time, 
                 raw_text, raw_hash, message_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $rawHash = md5($content);
            
            $stmt->execute([
                $inboxReport['guild_id'],
                $inboxReport['battle_type'],
                $inboxReport['opponent_guild'],
                $inboxReport['battle_date'],
                $inboxReport['battle_time'],
                $content,
                $rawHash,
                $inboxReport['message_id']
            ]);
            
            $battleId = $db->lastInsertId();
            
            // Insert participants
            $stmt = $db->prepare("
                INSERT INTO sf_eval_participants 
                (battle_id, player_name, player_name_norm, player_level, player_server_tag, participated)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            foreach ($parsed['participants'] as $participant) {
                $stmt->execute([
                    $battleId,
                    $participant['name'],
                    normalizePlayerName($participant['name']),
                    $participant['level'],
                    $participant['server_tag'],
                    $participant['participated']
                ]);
            }
            
            // Update inbox status
            $stmt = $db->prepare("
                UPDATE battle_inbox 
                SET status = 'imported',
                    imported_to_battle_id = ?,
                    reviewed_at = datetime('now')
                WHERE id = ?
            ");
            $stmt->execute([$battleId, $reportId]);
            
            $imported++;
            
        } catch (Exception $e) {
            $errors[] = "Report ID $reportId: " . $e->getMessage();
        }
    }
    
    $db->commit();
    
    // Build response message
    $message = [];
    if ($imported > 0) {
        $message[] = $imported . ' Bericht(e) erfolgreich importiert!';
    }
    if (count($errors) > 0) {
        $message[] = count($errors) . ' übersprungen:';
        foreach ($errors as $error) {
            $message[] = '  • ' . $error;
        }
    }
    
    echo json_encode([
        'success' => true,
        'count' => $imported,
        'imported' => $imported,
        'skipped' => count($errors),
        'message' => implode("\n", $message)
    ]);
    
} catch (Exception $e) {
    $db->rollBack();
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

/**
 * Normalize player name for comparison
 */
function normalizePlayerName($name) {
    $name = mb_strtolower(trim($name), 'UTF-8');
    // Server-Tag entfernen für Gruppierung: (s37de), (s3eu), (w51net), etc.
    $name = preg_replace('/\s*\([sw]\d+\w*\)/u', '', $name);
    return trim($name);
}
