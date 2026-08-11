<?php
/**
 * API: Angriffszeit einer beliebigen Gilde abfragen
 * Fragt per sf-api-Binary (guild_battle_time) live beim Spiel ab, wann eine
 * Gilde angegriffen wird. Für alle eingeloggten Nutzer verfügbar — nicht auf
 * eigene Gilden beschränkt, siehe includes/sf_helpers.php findAnySfAccountForServer().
 *
 * POST Body:
 *   server        — Server-Hostname, z.B. "f25.sfgame.net" (Pflicht)
 *   target_guild  — Name der abzufragenden Gilde (Pflicht)
 */
require_once __DIR__ . '/../includes/bootstrap_api.php';
require_once __DIR__ . '/../includes/sf_helpers.php';

set_time_limit(30);
ini_set('max_execution_time', '30');

header('Content-Type: application/json');
checkAuthAPI();

$db     = getDB();
$userId = $_SESSION['user_id'];

try {
    $input = json_decode(file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
} catch (JsonException $e) {
    jsonError('Ungültige JSON-Daten', 400);
}

$server      = trim((string)($input['server'] ?? ''));
$targetGuild = trim((string)($input['target_guild'] ?? ''));

if ($server === '' || $targetGuild === '') {
    jsonError('server und target_guild erforderlich', 400);
}
if (mb_strlen($targetGuild) > 64) {
    jsonError('Gildenname zu lang', 400);
}

// Einfaches Rate-Limit pro Session: verhindert, dass schnelles Mehrfachklicken
// den Spiele-Server mit Login-Versuchen spammt. Ergebnisse werden bewusst
// nicht zwischengespeichert (immer Live-Abfrage), daher kein Cache-Hit hier.
$cooldownSeconds = 10;
$lastCall = $_SESSION['guild_attack_time_last_call'] ?? 0;
if (time() - $lastCall < $cooldownSeconds) {
    jsonError('Bitte kurz warten, bevor du erneut abfragst (max. 1 Abfrage alle ' . $cooldownSeconds . 's).', 429);
}
$_SESSION['guild_attack_time_last_call'] = time();

$match = findAnySfAccountForServer($db, $userId, $server);
if (!$match) {
    jsonError('Kein Charakter für Server "' . $server . '" gefunden. Bitte in den Kontoeinstellungen einen Charakter auf diesem Server hinterlegen.', 404);
}
$foundAccount   = $match['account'];
$foundCharacter = $match['character'];

$sfPassword = decryptData(
    $foundAccount['sf_password_encrypted'],
    $foundAccount['sf_iv'],
    $foundAccount['sf_hmac'] ?? null
);

$binary = '/opt/sf-api/guild_battle_time';
if (!file_exists($binary) || !is_executable($binary)) {
    jsonError('guild_battle_time Binary nicht gefunden oder nicht ausführbar', 500);
}

$cmd = [$binary];
$env = array_merge($_ENV, [
    'SSO_USERNAME' => $foundAccount['sf_username'],
    'SSO_PASSWORD' => $sfPassword,
    'SERVER_HOST'  => $foundCharacter['server'],
    'CHARACTER'    => $foundCharacter['name'],
    'TARGET_GUILD' => $targetGuild,
]);
$spec = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];

$proc = proc_open($cmd, $spec, $pipes, null, $env);
if (!is_resource($proc)) {
    jsonError('Konnte guild_battle_time nicht starten', 500);
}
fclose($pipes[0]);
$stdout = stream_get_contents($pipes[1]);
$stderr = stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);
$exitCode = proc_close($proc);

$result = json_decode($stdout, true);
if (!is_array($result)) {
    logError('guild_attack_time: ungültige Binary-Ausgabe', [
        'exit_code' => $exitCode,
        'output'    => substr($stdout . $stderr, 0, 500),
    ]);
    jsonError('Unerwartete Antwort vom Spiel-Server. Bitte später erneut versuchen.', 502);
}
if (empty($result['success'])) {
    jsonError($result['error'] ?? 'Abfrage fehlgeschlagen', 502);
}

jsonResponse([
    'success'      => true,
    'target_guild' => $result['target_guild'],
    'server'       => $result['server'],
    'queried_with' => $foundCharacter['name'], // welcher Charakter für die Abfrage genutzt wurde
    'attacked_at'  => $result['attacked_at'],  // Unix-Timestamp (Sekunden, UTC) oder null
    'attacked_by'  => $result['attacked_by'],
]);
