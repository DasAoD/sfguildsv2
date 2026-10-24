<?php
/**
 * Hintergrundprozess für den Button "Charakterdaten aktualisieren" auf der
 * Gildenseite (gestartet von api/sf_character_sync.php). Schreibt das Ergebnis
 * als JSON in eine Temp-Datei, die die API beim Abfragen ausliest.
 *
 * Usage: php sync_guild_characters.php <guild_id> <user_id>
 */
if (PHP_SAPI !== 'cli') { exit(1); }

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/sf_helpers.php';
require_once __DIR__ . '/../includes/logger.php';
require_once __DIR__ . '/../includes/character_sync.php';

$guildId = (int)($argv[1] ?? 0);
$userId  = (int)($argv[2] ?? 0);
if ($guildId <= 0 || $userId <= 0) { exit(1); }

$lockFh = fopen(sys_get_temp_dir() . "/sfguilds_charsync_{$guildId}.lock", 'c');
if (!flock($lockFh, LOCK_EX | LOCK_NB)) { exit(0); }

$resultFile = sys_get_temp_dir() . "/sfguilds_charsync_{$guildId}.json";
try {
    $db = getDB();
    $guild = queryOne('SELECT id, name, server FROM guilds WHERE id = ?', [$guildId]);
    $match = $guild ? findSfAccountForGuild($db, $userId, $guild['name']) : null;

    $res = $match
        ? syncGuildCharacters($db, $guild, $match['account'], $match['character'])
        : ['ok' => false, 'written' => 0, 'skipped' => 0, 'error' => 'Kein Charakter für diese Gilde gefunden'];
} catch (Throwable $e) {
    logError('character_sync (Gilde) fehlgeschlagen', ['guild_id' => $guildId, 'error' => $e->getMessage()]);
    $res = ['ok' => false, 'written' => 0, 'skipped' => 0, 'error' => 'Interner Fehler beim Sync'];
}

file_put_contents($resultFile, json_encode($res + ['finished_at' => time()]));
