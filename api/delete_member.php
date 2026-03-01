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
    
    // Delete the member
    execute('DELETE FROM members WHERE id = ?', [$memberId]);
    
    // Also clean up any participant references (optional, keeps data clean)
    // Note: sf_eval_participants uses player_name_norm, not member_id,
    // so no foreign key cleanup needed there.
    
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
    logError('Delete member failed', ['id' => $memberId, 'error' => $e->getMessage()]);
    jsonResponse([
        'success' => false,
        'message' => 'Fehler beim Löschen'
    ], 500);
}
