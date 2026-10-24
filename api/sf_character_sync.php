<?php
/**
 * API: Charakterdaten einer Gilde aktualisieren (Button auf der Gildenseite)
 * Der Sync dauert länger als das nginx-Zeitlimit und läuft deshalb im
 * Hintergrund (cli/sync_guild_characters.php).
 *
 * POST { guild_id } — startet den Sync mit einem eigenen Account des Users
 * GET  ?guild_id=   — { running, result } (result: ok, written, skipped, error, finished_at)
 */
require_once __DIR__ . '/../includes/bootstrap_api.php';
require_once __DIR__ . '/../includes/sf_helpers.php';

header('Content-Type: application/json');
requireModeratorAPI();

$lockPath   = fn(int $id) => sys_get_temp_dir() . "/sfguilds_charsync_{$id}.lock";
$resultPath = fn(int $id) => sys_get_temp_dir() . "/sfguilds_charsync_{$id}.json";

function charSyncRunning(string $lockFile): bool {
    $fh = fopen($lockFile, 'c');
    if (!$fh) {
        jsonError('Sperrdatei nicht beschreibbar', 500);
    }
    if (!flock($fh, LOCK_EX | LOCK_NB)) {
        fclose($fh);
        return true;
    }
    flock($fh, LOCK_UN);
    fclose($fh);
    return false;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $guildId = (int)($_GET['guild_id'] ?? 0);
    if ($guildId <= 0) {
        jsonError('guild_id erforderlich', 400);
    }
    $result = is_file($resultPath($guildId)) ? json_decode(file_get_contents($resultPath($guildId)), true) : null;
    jsonResponse(['success' => true, 'running' => charSyncRunning($lockPath($guildId)), 'result' => $result]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Nur GET und POST erlaubt', 405);
}

try {
    $input = json_decode(file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
} catch (JsonException $e) {
    jsonError('Ungültige JSON-Daten', 400);
}

$guildId = (int)($input['guild_id'] ?? 0);
if ($guildId <= 0) {
    jsonError('guild_id erforderlich', 400);
}

$guild = queryOne('SELECT id, name FROM guilds WHERE id = ?', [$guildId]);
if (!$guild) {
    jsonError('Gilde nicht gefunden', 404);
}

$userId = (int)$_SESSION['user_id'];
if (!findSfAccountForGuild(getDB(), $userId, $guild['name'])) {
    jsonError('Kein Charakter für diese Gilde gefunden. Bitte Kontoeinstellungen prüfen.', 404);
}

if (charSyncRunning($lockPath($guildId))) {
    jsonError('Sync für diese Gilde läuft bereits', 409);
}

@unlink($resultPath($guildId));
$cmd = PHP_BINDIR . '/php ' . escapeshellarg(__DIR__ . '/../cli/sync_guild_characters.php')
    . ' ' . $guildId . ' ' . $userId . ' > /dev/null 2>&1 &';
exec($cmd);

logActivity('Character-Sync gestartet', ['Gilde' => $guild['name']]);
jsonResponse(['success' => true]);
