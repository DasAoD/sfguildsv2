<?php
/**
 * API: Charakter-Details (Attribute + Ausrüstung) für das Modal
 * GET Parameter:
 *   guild_id    — ID der Gilde (Pflicht)
 *   player_name — Name des Mitglieds (Pflicht)
 *
 * Liest ausschließlich vorab gespeicherte Daten aus members.char_data_json
 * (befüllt vom character_sync-Cronjob) — kein Live-Aufruf ans Spiel.
 */
require_once __DIR__ . '/../includes/bootstrap_api.php';

header('Content-Type: application/json');
checkAuthAPI();

$guildId    = isset($_GET['guild_id']) ? (int)$_GET['guild_id'] : null;
$playerName = $_GET['player_name'] ?? null;

if (!$guildId || !$playerName) {
    jsonError('guild_id und player_name erforderlich', 400);
}

$member = queryOne(
    'SELECT char_data_json, char_fetched_at FROM members WHERE guild_id = ? AND name = ?',
    [$guildId, $playerName]
);

if (!$member) {
    jsonError('Mitglied nicht gefunden', 404);
}

jsonResponse([
    'success'    => true,
    'available'  => $member['char_data_json'] !== null,
    'data'       => $member['char_data_json'] ? json_decode($member['char_data_json'], true) : null,
    'fetched_at' => $member['char_fetched_at'],
]);
