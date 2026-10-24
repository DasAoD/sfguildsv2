<?php
/**
 * Charakterdaten-Sync einer Gilde: ruft die character_sync-Binary auf und
 * schreibt das Ergebnis nach members.char_data_json. Gemeinsam genutzt vom
 * Cron-Job (alle Gilden, Admin-Accounts) und vom Button auf der Gildenseite
 * (eine Gilde, eigener Account des eingeloggten Users).
 */
require_once __DIR__ . '/logger.php';

const CHARACTER_SYNC_BINARY = '/opt/sf-api/character_sync';

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
            // Rohes typ-Feld mitspeichern statt dem Slotnamen zu vertrauen:
            // die Ausrüstungsliste ist positionsbasiert, nicht typbasiert —
            // z.B. liegt die zweite Waffe der Assassine technisch im
            // "Shield"-Slot, ist aber ein echtes Waffen-Item (typ.Weapon).
            'typ'           => $item['typ'] ?? null,
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

    // Portrait: alle rein kosmetischen Aussehen-Felder (siehe sf-api
    // gamestate/character.rs Portrait-Struct), für das echte Charakter-
    // Portrait im Modal statt des Buchstaben-Platzhalters.
    $portrait = $raw['portrait'] ?? [];

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
        'portrait'   => [
            'gender'           => $portrait['gender'] ?? null,
            'hair_color'       => $portrait['hair_color'] ?? null,
            'hair'             => $portrait['hair'] ?? null,
            'mouth'            => $portrait['mouth'] ?? null,
            'brows'            => $portrait['brows'] ?? null,
            'eyes'             => $portrait['eyes'] ?? null,
            'beards'           => $portrait['beards'] ?? null,
            'nose'             => $portrait['nose'] ?? null,
            'ears'             => $portrait['ears'] ?? null,
            'extra'            => $portrait['extra'] ?? null,
            'horns'            => $portrait['horns'] ?? null,
            'special_portrait' => $portrait['special_portrait'] ?? 0,
        ],
    ]);
}

/**
 * @return array{ok:bool,written:int,skipped:int,error:?string}
 */
function syncGuildCharacters(PDO $db, array $guild, array $account, array $character): array {
    $binary = CHARACTER_SYNC_BINARY;
    if (!file_exists($binary) || !is_executable($binary)) {
        return ['ok' => false, 'written' => 0, 'skipped' => 0, 'error' => 'character_sync Binary nicht gefunden'];
    }

    $sfPassword = decryptData(
        $account['sf_password_encrypted'],
        $account['sf_iv'],
        $account['sf_hmac'] ?? null
    );

    $env = array_merge($_ENV, [
        'SSO_USERNAME' => $account['sf_username'],
        'SSO_PASSWORD' => $sfPassword,
        'SERVER_HOST'  => $character['server'],
        'CHARACTER'    => $character['name'],
    ]);
    $spec = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];

    $proc = proc_open([$binary], $spec, $pipes, null, $env);
    if (!is_resource($proc)) {
        return ['ok' => false, 'written' => 0, 'skipped' => 0, 'error' => 'character_sync konnte nicht gestartet werden'];
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
    if (!$data || !($data['success'] ?? false)) {
        return ['ok' => false, 'written' => 0, 'skipped' => 0, 'error' => $data['error'] ?? 'Ungültige Ausgabe'];
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

    $skipped = count($data['skipped'] ?? []);
    logActivity('Character-Sync', [
        'Gilde'        => $guild['name'],
        'Aktualisiert' => $written,
        'Übersprungen' => $skipped,
    ]);

    return ['ok' => true, 'written' => $written, 'skipped' => $skipped, 'error' => null];
}
