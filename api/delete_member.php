<?php
/**
 * Delete Member API Endpoint
 * Deletes a single member from a guild
 */
require_once __DIR__ . '/../includes/bootstrap_api.php';

// Must be logged in
requireAdminAPI();

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Nur POST erlaubt'], 405);
}

// Get input
try {
    $input = json_decode(file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
} catch (JsonException $e) {
    jsonError('Ungültige JSON-Daten', 400);
}
$memberId = isset($input['member_id']) ? (int)$input['member_id'] : 0;

if (!$memberId) {
    jsonResponse(['success' => false, 'message' => 'Member ID erforderlich'], 400);
}

try {
    // Verify member exists and get name + guild for logging
    $member = queryOne(
        'SELECT m.id, m.name, m.guild_id, g.name as guild_name 
         FROM members m LEFT JOIN guilds g ON g.id = m.guild_id 
         WHERE m.id = ?', 
        [$memberId]
    );
    
    if (!$member) {
        jsonResponse(['success' => false, 'message' => 'Mitglied nicht gefunden'], 404);
    }
    
    $normalize = fn($name) => trim(preg_replace('/\s*\([sw]\d+\w*\)/u', '', mb_strtolower(trim($name), 'UTF-8')));
    $norm = $normalize($member['name']);

    $db = getDB();
    $db->beginTransaction();
    execute('DELETE FROM members WHERE id = ?', [$memberId]);

    // Participants are linked by normalized name only. Without this cleanup the
    // player shows up in the reports as an orphan once the member row is gone.
    // Skipped if another member of the guild still normalizes to the same name.
    $dupes = array_filter(
        query('SELECT name FROM members WHERE guild_id = ?', [$member['guild_id']]),
        fn($m) => $normalize($m['name']) === $norm
    );
    if (!$dupes) {
        execute(
            'DELETE FROM sf_eval_participants WHERE player_name_norm = ?
             AND battle_id IN (SELECT id FROM sf_eval_battles WHERE guild_id = ?)',
            [$norm, $member['guild_id']]
        );
    }
    $db->commit();

    logActivity('Mitglied gelöscht', [
        'Name'     => $member['name'],
        'ID'       => $memberId,
        'Guild-ID' => $member['guild_id'],
        'Gilde'    => $member['guild_name'] ?? '?'
    ]);
    
    jsonResponse([
        'success' => true,
        'message' => 'Mitglied "' . $member['name'] . '" wurde gelöscht'
    ]);
    
} catch (Throwable $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    logError('Delete member failed', ['id' => $memberId, 'error' => $e->getMessage()]);
    jsonResponse([
        'success' => false,
        'message' => 'Fehler beim Löschen'
    ], 500);
}
