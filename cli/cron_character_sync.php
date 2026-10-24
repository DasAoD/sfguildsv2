<?php
/**
 * Cron: Charakterdaten synchronisieren (Attribute + Ausrüstung, alle Gilden)
 * Wird von cron_runner.php aufgerufen. Unabhängig von member_sync planbar,
 * da ViewPlayer pro Mitglied deutlich mehr S&F-Anfragen erzeugt als der
 * reine Roster-Abruf.
 */
if (PHP_SAPI !== 'cli') { exit(1); }

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/sf_helpers.php';
require_once __DIR__ . '/../includes/logger.php';
require_once __DIR__ . '/../includes/character_sync.php';

$db = getDB();

if (!file_exists(CHARACTER_SYNC_BINARY) || !is_executable(CHARACTER_SYNC_BINARY)) {
    echo "character_sync Binary nicht gefunden!" . PHP_EOL;
    return ['total' => 0, 'success' => 0, 'errors' => 1];
}

$guilds = $db->query("SELECT id, name, server FROM guilds")->fetchAll(PDO::FETCH_ASSOC);

$total = 0; $success = 0; $errors = 0;

foreach ($guilds as $guild) {
    $match = findSfAccountForGuild($db, null, $guild['name']);
    if (!$match) {
        echo "⚠ Kein Charakter für Gilde '{$guild['name']}' gefunden, übersprungen.\n";
        continue;
    }

    $res = syncGuildCharacters($db, $guild, $match['account'], $match['character']);
    $total++;

    if (!$res['ok']) {
        $errors++;
        echo "✗ {$guild['name']}: {$res['error']}\n";
        continue;
    }

    $success++;
    echo "✓ {$guild['name']}: {$res['written']} aktualisiert, {$res['skipped']} übersprungen\n";
}

return ['total' => $total, 'success' => $success, 'errors' => $errors];
