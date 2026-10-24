<?php
/**
 * Legt geänderte Item-Bundles des Spiels in storage/game_assets/staging/ ab
 * (siehe install/GAME_ASSETS_CHECK.md). Die Bundles werden nur geladen, nie
 * entpackt oder ausgeführt.
 *
 * Der Asset-Pfad (sfprodX.Y.Z) steht nur in der data-Datei des Client-Builds,
 * die deshalb nur bei einem neuen Build geladen wird. Der erste Lauf speichert
 * nur den Ausgangszustand, weil die Icons im Repo schon vorhanden sind.
 *
 * Usage: php cli/check_game_assets.php [--download] [--test-notify]
 */
if (PHP_SAPI !== 'cli') { exit(1); }

require_once __DIR__ . '/../includes/logger.php';

const BUILD_JSON_URL = 'https://sfgame.net/js/build.json';
const CDN_ASSETS_URL = 'https://cdn.sfgame.net/res/sfgame3/assets';
const DATA_URL_PATTERN = '#^https://cdn\.sfgame\.net/res/sfgame3/Build/[0-9.]+/[0-9a-f]{32}\.data(\.gz)?$#';
const MAX_DATA_BYTES = 200 * 1024 * 1024;
const MAX_BUNDLE_BYTES = 20 * 1024 * 1024;

const BUNDLES = [
    'warriorweapons_sd', 'mageweapons_sd', 'scoutweapons_sd',
    'warriorarmor_sd', 'magearmor_sd', 'scoutarmor_sd',
    'warriorhelmets_sd', 'magehelmets_sd', 'scouthelmets_sd',
    'warriorgloves_sd', 'magegloves_sd', 'scoutgloves_sd',
    'warriorshoes_sd', 'mageshoes_sd', 'scoutshoes_sd',
    'warriorbelts_sd', 'magebelts_sd', 'scoutbelts_sd',
    'shields_sd', 'necklaces_sd', 'rings_sd', 'talismans_sd', 'miscitems_sd',
    'magehits', 'scoutshots',
];

// Zugangsdaten für Telegram und Mail liegen nur auf dem Server (wie bei technitium-update-check)
const NOTIFY_CONFIG = '/usr/local/bin/notify.sh';

$storageDir = __DIR__ . '/../storage/game_assets';
$stateFile  = $storageDir . '/state.json';
$downloadAll = in_array('--download', $argv, true);

function notify(string $subject, string $body, string $short): void
{
    if (!is_readable(NOTIFY_CONFIG)) {
        return;
    }
    $cfg = file_get_contents(NOTIFY_CONFIG);
    preg_match('/^TELEGRAM_TOKEN="?(\d+:[A-Za-z0-9_-]+)"?/m', $cfg, $token);
    preg_match('/^TELEGRAM_CHATID="?(-?\d+)"?/m', $cfg, $chat);
    preg_match('/^MAIL_TO="?([^"\s@]+@[^"\s@]+)"?/m', $cfg, $to);

    if (!$token || !$chat || !$to) {
        logError('Spiel-Assets: Zugangsdaten in notify.sh unvollständig');
    }
    if ($token && $chat) {
        $ctx = stream_context_create(['http' => [
            'method'  => 'POST',
            'header'  => 'Content-Type: application/x-www-form-urlencoded',
            'content' => http_build_query(['chat_id' => $chat[1], 'text' => "{$subject}\n{$short}", 'disable_web_page_preview' => 1]),
            'timeout' => 10,
        ]]);
        if (@file_get_contents("https://api.telegram.org/bot{$token[1]}/sendMessage", false, $ctx) === false) {
            logError('Spiel-Assets: Telegram-Versand fehlgeschlagen');
        }
    }
    if ($to) {
        $proc = @proc_open(['mail', '-s', $subject, '--', $to[1]], [0 => ['pipe', 'r']], $pipes);
        if (is_resource($proc)) {
            @fwrite($pipes[0], $body);
            fclose($pipes[0]);
            if (proc_close($proc) !== 0) {
                logError('Spiel-Assets: Mailversand fehlgeschlagen');
            }
        } else {
            logError('Spiel-Assets: Mailversand fehlgeschlagen');
        }
    }
}

if (in_array('--test-notify', $argv, true)) {
    notify('sfguilds: Testnachricht Spiel-Assets', 'Testnachricht von check_game_assets.php.', 'Testnachricht von check_game_assets.php.');
    exit(0);
}

function fetchUrl(string $url, int $maxBytes): string
{
    $ctx = stream_context_create(['http' => [
        'timeout'         => 60,
        'follow_location' => 0,
        'user_agent'      => 'sfguildsv2-asset-check',
    ]]);
    $body = @file_get_contents($url, false, $ctx, 0, $maxBytes + 1);
    if ($body === false) {
        throw new RuntimeException("Abruf fehlgeschlagen: {$url}");
    }
    if (strlen($body) > $maxBytes) {
        throw new RuntimeException("Antwort zu groß: {$url}");
    }
    return $body;
}

function writeAtomic(string $path, string $content): void
{
    $tmp = $path . '.tmp';
    if (file_put_contents($tmp, $content) === false || !rename($tmp, $path)) {
        throw new RuntimeException("Schreiben fehlgeschlagen: {$path}");
    }
}

$lock = @fopen($storageDir . '/.lock', 'c');
if (!$lock) {
    fwrite(STDERR, "storage/game_assets nicht beschreibbar\n");
    exit(1);
}
if (!flock($lock, LOCK_EX | LOCK_NB)) {
    echo "Bereits in Ausführung, übersprungen.\n";
    exit(0);
}

$errors = [];
$staged = [];
$newBuild = false;
$productVersion = '';
$assetPath = null;
$lastError = null;

try {
    $state = is_file($stateFile) ? json_decode(file_get_contents($stateFile), true, 512, JSON_THROW_ON_ERROR) : [];
    $firstRun = empty($state['initialized']);
    $lastError = $state['last_error'] ?? null;

    $build = json_decode(fetchUrl(BUILD_JSON_URL, 64 * 1024), true, 512, JSON_THROW_ON_ERROR);
    $dataUrl = $build['dataUrl'] ?? '';
    if (!preg_match(DATA_URL_PATTERN, $dataUrl)) {
        throw new RuntimeException('Unerwartete dataUrl in build.json');
    }
    $productVersion = (string)($build['productVersion'] ?? '');

    $assetPath = $state['asset_path'] ?? null;
    if ($assetPath === null || ($state['data_url'] ?? null) !== $dataUrl) {
        $data = fetchUrl($dataUrl, MAX_DATA_BYTES);
        if (str_starts_with($data, "\x1f\x8b")) {
            $data = gzdecode($data, MAX_DATA_BYTES);
        }
        if (!is_string($data) || !preg_match('#res/sfgame3/assets/(sfprod[0-9]+\.[0-9]+\.[0-9]+)/#', $data, $m)) {
            throw new RuntimeException('Asset-Pfad in der data-Datei nicht gefunden');
        }
        unset($data);
        $assetPath = $m[1];
        $newBuild = !$firstRun;
        echo "Client-Build {$productVersion}, Asset-Pfad {$assetPath}\n";
    }

    $hashes = $state['hashes'] ?? [];
    foreach (BUNDLES as $name) {
        try {
            $manifest = fetchUrl(CDN_ASSETS_URL . "/{$assetPath}/WebGL/items/{$name}.manifest", 256 * 1024);
            if (!preg_match('/AssetFileHash:\s+serializedVersion: \d+\s+Hash: ([0-9a-f]{32})/', $manifest, $m)) {
                throw new RuntimeException("Kein Hash im Manifest: {$name}");
            }
            $hash = $m[1];
            if (($hashes[$name] ?? null) === $hash) {
                continue;
            }
            if (!$firstRun || $downloadAll) {
                $bundle = fetchUrl(CDN_ASSETS_URL . "/{$assetPath}/WebGL/items/{$name}?h={$hash}", MAX_BUNDLE_BYTES);
                if (!str_starts_with($bundle, 'UnityFS')) {
                    throw new RuntimeException("Kein Unity-Bundle: {$name}");
                }
                $dir = "{$storageDir}/staging/{$assetPath}";
                if (!is_dir($dir) && !mkdir($dir, 0775, true)) {
                    throw new RuntimeException("Ordner nicht anlegbar: {$dir}");
                }
                writeAtomic("{$dir}/{$name}", $bundle);
                $staged[] = $name;
            }
            $hashes[$name] = $hash;
        } catch (RuntimeException $e) {
            $errors[] = $e->getMessage();
        }
    }

    $state = [
        'data_url'        => $dataUrl,
        'product_version' => $productVersion,
        'asset_path'      => $assetPath,
        'hashes'          => $hashes,
        'initialized'     => !$errors,
        'last_error'      => $lastError,
        'checked_at'      => gmdate('c'),
        'last_change_at'  => $staged ? gmdate('c') : ($state['last_change_at'] ?? null),
    ];
    writeAtomic($stateFile, json_encode($state, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
} catch (Throwable $e) {
    $errors[] = $e->getMessage();
}

$stagingDir = "storage/game_assets/staging/{$assetPath}";
if ($staged) {
    logActivity('Spiel-Assets geändert', ['Bundles' => implode(', ', $staged), 'Pfad' => $stagingDir]);
    echo "Neu in {$stagingDir}: " . implode(', ', $staged) . "\n";
    notify(
        'sfguilds: Spiel-Assets geändert',
        count($staged) . " Bundle(s) geändert (Client-Build {$productVersion}):\n" . implode("\n", $staged)
            . "\n\nAbgelegt in {$stagingDir} auf dns1. Weiter mit AssetRipper, siehe install/GAME_ASSETS_CHECK.md.",
        count($staged) . ' Bundle(s) geändert (' . $productVersion . '): ' . implode(', ', array_slice($staged, 0, 6)) . (count($staged) > 6 ? ' …' : '')
    );
} elseif ($newBuild && !$errors) {
    logActivity('Neuer Spiel-Client-Build', ['Version' => $productVersion, 'Pfad' => $assetPath]);
    notify(
        'sfguilds: Neuer Spiel-Client-Build',
        "Client-Build {$productVersion}, Asset-Pfad {$assetPath}.\nKeine Bundle-Änderungen bei den beobachteten Item-Bundles.",
        "Client-Build {$productVersion}, keine Bundle-Änderungen."
    );
} elseif (!$errors) {
    echo $firstRun ? "Ausgangszustand gespeichert.\n" : "Keine Änderungen.\n";
}

$errorKey = $errors ? md5(implode("\n", $errors)) : null;
if ($errorKey !== $lastError) {
    $saved = is_file($stateFile) ? json_decode(file_get_contents($stateFile), true, 512, JSON_THROW_ON_ERROR) : [];
    $saved['last_error'] = $errorKey;
    writeAtomic($stateFile, json_encode($saved, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
}
if ($errors) {
    logError('Spiel-Assets prüfen fehlgeschlagen', ['Fehler' => $errors]);
    fwrite(STDERR, implode("\n", $errors) . "\n");
    if ($errorKey !== $lastError) {
        $text = implode("\n", $errors);
        notify('sfguilds: Spiel-Assets prüfen fehlgeschlagen', $text . "\n\nWeitere Details im Fehlerlog auf dns1.", mb_substr($text, 0, 300));
    }
    exit(1);
}
