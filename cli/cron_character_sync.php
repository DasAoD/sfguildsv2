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

/**
 * Baut aus der rohen ViewPlayer-Antwort (siehe rust_examples/character_sync.rs)
 * das kompakte JSON, das im Modal angezeigt wird. Anzeigewert je Attribut ist
 * bestätigt: attribute_basis + attribute_additions (times_bought/pet_bonus
 * NICHT mit einrechnen, siehe TODO_PUBLIC.md).
 */
function buildCharDataJson(array $raw): string {
    $attrs = [];
    foreach (['Strength', 'Dexterity', 'Intelligence', 'Constitution', 'Luck'] as $key) {
        $basis     = $raw['attribute_basis'][$key] ?? 0;
        $additions = $raw['attribute_additions'][$key] ?? 0;
        $attrs[$key] = $basis + $additions;
    }

    $equipment = [];
    foreach (($raw['equipment'] ?? []) as $slot => $item) {
        if ($item === null) continue;
        $equipment[$slot] = [
            'model_id'      => $item['model_id'] ?? null,
            'color'         => $item['color'] ?? null,
            'class'         => $item['class'] ?? null,
            'attributes'    => $item['attributes'] ?? null,
            'gem'           => $item['gem_slot']['Filled'] ?? null,
            'rune'          => $item['rune'] ?? null,
            'enchantment'   => $item['enchantment'] ?? null,
            'upgrade_count' => $item['upgrade_count'] ?? 0,
            'price'         => $item['price'] ?? 0,
        ];
    }

    return json_encode([
        'level'      => $raw['level'] ?? null,
        'class'      => $raw['class'] ?? null,
        'race'       => $raw['race'] ?? null,
        'honor'      => $raw['honor'] ?? null,
        'rank'       => $raw['rank'] ?? null,
        'armor'      => $raw['armor'] ?? null,
        'min_damage' => $raw['min_damage'] ?? null,
        'max_damage' => $raw['max_damage'] ?? null,
        'attributes' => $attrs,
        'equipment'  => $equipment,
        'potions'    => $raw['active_potions'] ?? [],
    ]);
}

$db     = getDB();
$binary = '/opt/sf-api/character_sync';

if (!file_exists($binary) || !is_executable($binary)) {
    echo "character_sync Binary nicht gefunden!\n";
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
    $foundAccount   = $match['account'];
    $foundCharacter = $match['character'];

    $sfPassword = decryptData(
        $foundAccount['sf_password_encrypted'],
        $foundAccount['sf_iv'],
        $foundAccount['sf_hmac'] ?? null
    );

    $cmd = [$binary];
    $env = array_merge($_ENV, [
        'SSO_USERNAME' => $foundAccount['sf_username'],
        'SSO_PASSWORD' => $sfPassword,
        'SERVER_HOST'  => $foundCharacter['server'],
        'CHARACTER'    => $foundCharacter['name'],
    ]);
    $spec = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];

    $proc = proc_open($cmd, $spec, $pipes, null, $env);
    if (!is_resource($proc)) {
        echo "✗ {$guild['name']}: character_sync konnte nicht gestartet werden\n";
        $errors++;
        continue;
    }
    fclose($pipes[0]);

    // Die Binary hat ein internes Zeitbudget von ~90s für die ViewPlayer-Calls;
    // hier großzügiger timeouten (Login + Gildenabruf kommen noch oben drauf).
    $out = ''; $start = time(); $stream = $pipes[1];
    stream_set_blocking($stream, false);
    while (!feof($stream)) {
        if ((time() - $start) > 150) {
            proc_terminate($proc, 9);
            break;
        }
        $r = [$stream]; $w = []; $e = [];
        if (stream_select($r, $w, $e, 1)) {
            $chunk = fread($stream, 8192);
            if ($chunk !== false) { $out .= $chunk; }
        }
    }
    fclose($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    proc_close($proc);

    if ($stderr) {
        logError('character_sync stderr', ['guild' => $guild['name'], 'stderr' => $stderr]);
    }

    $data = json_decode($out, true);
    $total++;

    if (!$data || !($data['success'] ?? false)) {
        $errors++;
        echo "✗ {$guild['name']}: " . ($data['error'] ?? 'Ungültige Ausgabe') . "\n";
        continue;
    }

    $updStmt = $db->prepare(
        "UPDATE members SET char_data_json = :json, char_fetched_at = :ts WHERE guild_id = :guild_id AND name = :name"
    );

    $written = 0;
    foreach (($data['characters'] ?? []) as $entry) {
        $updStmt->execute([
            ':json'     => buildCharDataJson($entry['data']),
            ':ts'       => gmdate('c'),
            ':guild_id' => $guild['id'],
            ':name'     => $entry['name'],
        ]);
        if ($updStmt->rowCount() > 0) { $written++; }
    }

    $skippedCount = count($data['skipped'] ?? []);
    $success++;
    echo "✓ {$guild['name']}: {$written} aktualisiert, {$skippedCount} übersprungen\n";

    logActivity('Character-Sync', [
        'Gilde'        => $guild['name'],
        'Aktualisiert' => $written,
        'Übersprungen' => $skippedCount,
    ]);
}

return ['total' => $total, 'success' => $success, 'errors' => $errors];
