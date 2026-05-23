<?php
/**
 * API: Guild Member Sync via sf-api
 * Syncs guild members for a specific guild using the Rust binary.
 *
 * POST Body:
 *   guild_id — ID der zu synchronisierenden Gilde (Pflicht)
 */
require_once __DIR__ . '/../includes/bootstrap_api.php';
require_once __DIR__ . '/../includes/sf_helpers.php';

set_time_limit(120);
ini_set('max_execution_time', '120');

header('Content-Type: application/json');
requireModeratorAPI();

$db     = getDB();
$userId = $_SESSION['user_id'];

try {
    $input = json_decode(file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
} catch (JsonException $e) {
    jsonError('Ungültige JSON-Daten', 400);
}

$guildId = isset($input['guild_id']) ? (int)$input['guild_id'] : null;
if (!$guildId) {
    jsonError('guild_id erforderlich', 400);
}

// Guild laden
$guild = queryOne('SELECT id, name, server FROM guilds WHERE id = ?', [$guildId]);
if (!$guild) {
    jsonError('Gilde nicht gefunden', 404);
}

// Passenden Charakter aus sf_accounts suchen
// selected_characters enthält: [{"name":"...","server":"f25.sfgame.net","guild":"Blutzirkel",...}]
// guild.server ist z.B. "F25" → normalisieren für Vergleich
$stmt = $db->prepare("
    SELECT id, sf_username, sf_password_encrypted, sf_iv, sf_hmac, selected_characters
    FROM sf_accounts
    WHERE user_id = ? AND selected_characters IS NOT NULL AND selected_characters != '[]'
");
$stmt->execute([$userId]);
$accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

$foundCharacter = null;
$foundAccount   = null;

foreach ($accounts as $account) {
    $chars = json_decode($account['selected_characters'], true) ?? [];
    foreach ($chars as $char) {
        // Gildenname direkt vergleichen
        if (($char['guild'] ?? '') === $guild['name']) {
            $foundCharacter = $char;
            $foundAccount   = $account;
            break 2;
        }
    }
}

if (!$foundCharacter || !$foundAccount) {
    jsonError('Kein Charakter für diese Gilde gefunden. Bitte Kontoeinstellungen prüfen.', 404);
}

$sfPassword = decryptData(
    $foundAccount['sf_password_encrypted'],
    $foundAccount['sf_iv'],
    $foundAccount['sf_hmac'] ?? null
);

// Rust Binary aufrufen
$binary = '/opt/sf-api/member_sync';
if (!file_exists($binary) || !is_executable($binary)) {
    jsonError('member_sync Binary nicht gefunden oder nicht ausführbar', 500);
}

$cmd = [$binary];
$env = array_merge($_ENV, [
    'SSO_USERNAME' => $foundAccount['sf_username'],
    'SSO_PASSWORD' => $sfPassword,
    'SERVER_HOST'  => $foundCharacter['server'],
    'CHARACTER'    => $foundCharacter['name'],
]);

$descriptorspec = [
    0 => ['pipe', 'r'],
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w'],
];

$process = proc_open($cmd, $descriptorspec, $pipes, null, $env);
if (!is_resource($process)) {
    jsonError('Konnte member_sync nicht starten', 500);
}
fclose($pipes[0]);

// Output lesen (Timeout: 90s)
$output    = '';
$startTime = time();
$stream    = $pipes[1];
stream_set_blocking($stream, false);

while (!feof($stream)) {
    if ((time() - $startTime) > 90) {
        proc_terminate($process, 9);
        jsonError('member_sync Timeout (>90s)', 500);
    }
    $r = [$stream]; $w = []; $e = [];
    if (stream_select($r, $w, $e, 1)) {
        $chunk = fread($stream, 8192);
        if ($chunk !== false) { $output .= $chunk; }
    }
}
fclose($pipes[1]);

$stderr = stream_get_contents($pipes[2]);
fclose($pipes[2]);
proc_close($process);

if ($stderr) {
    logError('member_sync stderr', ['guild' => $guild['name'], 'stderr' => $stderr]);
}

$data = json_decode($output, true);
if (!$data || !isset($data['success'])) {
    jsonError('Ungültige Ausgabe vom member_sync Binary', 500);
}
if (!$data['success']) {
    jsonError('member_sync Fehler: ' . ($data['error'] ?? 'Unbekannt'), 500);
}

// DB-Update
$members    = $data['members'] ?? [];
$inserted   = 0;
$updated    = 0;
$rejoined   = 0;
$today      = gmdate('Y-m-d');

$selStmt = $db->prepare(
    "SELECT id, fired_at, left_at, joined_at FROM members WHERE guild_id = ? AND name = ? LIMIT 1"
);

$updStmt = $db->prepare("
    UPDATE members
    SET level       = :level,
        rank        = :rank,
        last_online = :last_online,
        gold        = :gold,
        mentor      = :mentor,
        knight_hall = :knight_hall,
        guild_pet   = :guild_pet,
        updated_at  = :updated_at
    WHERE id = :id
");

$rejoinStmt = $db->prepare("
    UPDATE members
    SET level       = :level,
        rank        = :rank,
        last_online = :last_online,
        gold        = :gold,
        mentor      = :mentor,
        knight_hall = :knight_hall,
        guild_pet   = :guild_pet,
        fired_at    = NULL,
        left_at     = NULL,
        joined_at   = :joined_at,
        updated_at  = :updated_at
    WHERE id = :id
");

$insStmt = $db->prepare("
    INSERT INTO members
        (guild_id, name, level, rank, last_online, gold, mentor, knight_hall, guild_pet, joined_at, updated_at)
    VALUES
        (:guild_id, :name, :level, :rank, :last_online, :gold, :mentor, :knight_hall, :guild_pet, :joined_at, :updated_at)
");

$db->beginTransaction();
try {
    foreach ($members as $m) {
        $selStmt->execute([$guildId, $m['name']]);
        $existing = $selStmt->fetch(PDO::FETCH_ASSOC);

        $params = [
            ':level'       => $m['level'],
            ':rank'        => $m['rank'],
            ':last_online' => $m['last_online'] ?? null,
            ':gold'        => $m['gold'],
            ':mentor'      => $m['mentor'],
            ':knight_hall' => $m['knight_hall'],
            ':guild_pet'   => $m['guild_pet'],
            ':updated_at'  => gmdate('c'),
        ];

        if ($existing) {
            $wasGone = !empty($existing['fired_at']) || !empty($existing['left_at']);
            if ($wasGone) {
                // Mitglied war entlassen/verlassen und ist wieder dabei
                $rejoinStmt->execute(array_merge($params, [
                    ':joined_at' => $today,
                    ':id'        => $existing['id'],
                ]));
                $rejoined++;
            } else {
                $updStmt->execute(array_merge($params, [':id' => $existing['id']]));
                $updated++;
            }
        } else {
            $insStmt->execute(array_merge($params, [
                ':guild_id'  => $guildId,
                ':name'      => $m['name'],
                ':joined_at' => $today,
            ]));
            $inserted++;
        }
    }

    // last_import_at der Gilde aktualisieren
    execute(
        "UPDATE guilds SET last_import_at = :ts, updated_at = datetime('now') WHERE id = :id",
        [':ts' => gmdate('c'), ':id' => $guildId]
    );

    $db->commit();
} catch (Throwable $e) {
    $db->rollBack();
    logError('member_sync DB-Fehler', ['guild_id' => $guildId, 'error' => $e->getMessage()]);
    jsonError('Datenbankfehler beim Sync', 500);
}

logActivity('Member-Sync', [
    'Gilde'      => $guild['name'],
    'Neu'        => $inserted,
    'Aktualisiert' => $updated,
    'Wiederbeigetreten' => $rejoined,
]);

jsonResponse([
    'success'   => true,
    'guild'     => $guild['name'],
    'inserted'  => $inserted,
    'updated'   => $updated,
    'rejoined'  => $rejoined,
    'total'     => count($members),
]);
