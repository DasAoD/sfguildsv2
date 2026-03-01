<?php
/**
 * Admin Player Rename API
 * 
 * GET ?action=orphans       → Spieler in Reports ohne aktives Gildenmitglied
 * GET ?action=suggestions   → Mögliche Zuordnungen für einen verwaisten Spieler
 * POST                      → Spieler umbenennen (members + participants)
 */
require_once __DIR__ . '/../includes/bootstrap_api.php';

requireAdminAPI();

function normalizeName(string $name): string {
    $name = mb_strtolower(trim($name), 'UTF-8');
    $name = preg_replace('/\s*\([sw]\d+\w*\)/u', '', $name);
    return trim($name);
}

function extractTag(string $name): ?string {
    if (preg_match('/\(([sw]\d+\w*)\)/ui', $name, $m)) {
        return strtolower($m[1]);
    }
    return null;
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    $db = getDB();

    if ($method === 'GET') {
        $action = $_GET['action'] ?? 'orphans';
        
        if ($action === 'orphans') {
            $orphans = [];
            $guilds = $db->query("SELECT id, name FROM guilds ORDER BY name")->fetchAll();
            
            foreach ($guilds as $guild) {
                $stmt = $db->prepare("
                    SELECT name FROM members 
                    WHERE guild_id = ? 
                    AND (fired_at IS NULL OR fired_at = '') 
                    AND (left_at IS NULL OR left_at = '')
                ");
                $stmt->execute([$guild['id']]);
                
                $memberNorms = [];
                foreach ($stmt->fetchAll() as $m) {
                    $memberNorms[normalizeName($m['name'])] = true;
                }
                
                $stmt = $db->prepare("
                    SELECT 
                        p.player_name_norm,
                        MAX(p.player_name) as display_name,
                        COUNT(*) as battle_count,
                        MAX(b.battle_date) as last_seen
                    FROM sf_eval_participants p
                    JOIN sf_eval_battles b ON b.id = p.battle_id
                    WHERE b.guild_id = ?
                    GROUP BY p.player_name_norm
                    ORDER BY MAX(b.battle_date) DESC
                ");
                $stmt->execute([$guild['id']]);
                
                $guildOrphans = [];
                foreach ($stmt->fetchAll() as $p) {
                    if (!isset($memberNorms[$p['player_name_norm']])) {
                        $guildOrphans[] = $p;
                    }
                }
                
                if ($guildOrphans) {
                    $orphans[] = [
                        'guild_id' => (int)$guild['id'],
                        'guild_name' => $guild['name'],
                        'players' => $guildOrphans
                    ];
                }
            }
            
            jsonResponse(['success' => true, 'orphans' => $orphans]);
            
        } elseif ($action === 'members') {
            $guildId = $_GET['guild_id'] ?? null;
            if (!$guildId) {
                jsonResponse(['success' => false, 'message' => 'guild_id erforderlich'], 400);
            }
            
            $stmt = $db->prepare("
                SELECT id, name, level FROM members 
                WHERE guild_id = ? 
                AND (fired_at IS NULL OR fired_at = '') 
                AND (left_at IS NULL OR left_at = '')
                ORDER BY name COLLATE NOCASE
            ");
            $stmt->execute([$guildId]);
            
            jsonResponse(['success' => true, 'members' => $stmt->fetchAll()]);
        }
        
    } elseif ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
        
        $guildId = $input['guild_id'] ?? null;
        $oldName = trim($input['old_name'] ?? '');
        $newName = trim($input['new_name'] ?? '');
        
        if (!$guildId || !$oldName || !$newName) {
            jsonResponse(['success' => false, 'message' => 'guild_id, old_name und new_name erforderlich'], 400);
        }
        
        $oldNorm = normalizeName($oldName);
        $newNorm = normalizeName($newName);
        $newTag = extractTag($newName);
        
        $updatedParticipants = 0;
        $updatedMembers = 0;
        $deletedMembers = 0;
        
        // 1. Update participants: all entries matching old norm → new name
        $stmt = $db->prepare("
            SELECT COUNT(*) FROM sf_eval_participants p
            JOIN sf_eval_battles b ON b.id = p.battle_id
            WHERE b.guild_id = ? AND p.player_name_norm = ?
        ");
        $stmt->execute([$guildId, $oldNorm]);
        $updatedParticipants = (int)$stmt->fetchColumn();
        
        if ($updatedParticipants > 0) {
            $stmt = $db->prepare("
                UPDATE sf_eval_participants
                SET player_name = ?, player_name_norm = ?, player_server_tag = ?
                WHERE id IN (
                    SELECT p.id FROM sf_eval_participants p
                    JOIN sf_eval_battles b ON b.id = p.battle_id
                    WHERE b.guild_id = ? AND p.player_name_norm = ?
                )
            ");
            $stmt->execute([$newName, $newNorm, $newTag, $guildId, $oldNorm]);
        }
        
        // 2. Check if member with new name already exists
        $stmt = $db->prepare("
            SELECT id FROM members 
            WHERE guild_id = ? AND LOWER(name) = LOWER(?)
        ");
        $stmt->execute([$guildId, $newName]);
        $newMemberExists = $stmt->fetch();
        
        // 3. Rename or delete old member entry
        $stmt = $db->prepare("SELECT id, name FROM members WHERE guild_id = ?");
        $stmt->execute([$guildId]);
        foreach ($stmt->fetchAll() as $member) {
            if (normalizeName($member['name']) === $oldNorm) {
                if ($newMemberExists) {
                    // New name already exists as member → delete old entry
                    $del = $db->prepare("DELETE FROM members WHERE id = ?");
                    $del->execute([$member['id']]);
                    $deletedMembers++;
                } else {
                    // Rename the member entry
                    $upd = $db->prepare("UPDATE members SET name = ?, updated_at = datetime('now') WHERE id = ?");
                    $upd->execute([$newName, $member['id']]);
                    $updatedMembers++;
                    $newMemberExists = true; // prevent duplicates if multiple old entries
                }
            }
        }
        
        // Log
        $details = [];
        if ($updatedParticipants) $details[] = "{$updatedParticipants} Kampfeinträge";
        if ($updatedMembers) $details[] = "{$updatedMembers} Mitglieder-Eintrag umbenannt";
        if ($deletedMembers) $details[] = "{$deletedMembers} doppelte Mitglieder-Einträge entfernt";
        $detailStr = implode(', ', $details);
        
        logActivity("Spieler umbenannt: {$oldName} → {$newName} ({$detailStr})", [
            'guild_id' => $guildId,
            'old_name' => $oldName,
            'new_name' => $newName,
            'updated_participants' => $updatedParticipants,
            'updated_members' => $updatedMembers,
            'deleted_members' => $deletedMembers
        ]);
        
        jsonResponse([
            'success' => true, 
            'message' => "Umbenennung abgeschlossen: {$detailStr}",
            'updated_participants' => $updatedParticipants,
            'updated_members' => $updatedMembers,
            'deleted_members' => $deletedMembers
        ]);
    }
    
} catch (Throwable $e) {
    logError('admin_player_merge failed', ['error' => $e->getMessage()]);
    jsonResponse(['success' => false, 'message' => 'Interner Fehler'], 500);
}
