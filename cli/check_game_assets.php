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
 * Usage: php cli/check_game_assets.php [--download]
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

$storageDir = __DIR__ . '/../storage/game_assets';
$stateFile  = $storageDir . '/state.json';
$downloadAll = in_array('--download', $argv, true);

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

try {
    $state = is_file($stateFile) ? json_decode(file_get_contents($stateFile), true, 512, JSON_THROW_ON_ERROR) : [];
    $firstRun = empty($state['initialized']);

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
        'checked_at'      => gmdate('c'),
        'last_change_at'  => $staged ? gmdate('c') : ($state['last_change_at'] ?? null),
    ];
    writeAtomic($stateFile, json_encode($state, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
} catch (Throwable $e) {
    $errors[] = $e->getMessage();
}

if ($staged) {
    logActivity('Spiel-Assets geändert', ['Bundles' => implode(', ', $staged), 'Pfad' => "storage/game_assets/staging/{$assetPath}"]);
    echo 'Neu in storage/game_assets/staging/' . $assetPath . ': ' . implode(', ', $staged) . "\n";
} elseif (!$errors) {
    echo $firstRun ? "Ausgangszustand gespeichert.\n" : "Keine Änderungen.\n";
}
if ($errors) {
    logError('Spiel-Assets prüfen fehlgeschlagen', ['Fehler' => $errors]);
    fwrite(STDERR, implode("\n", $errors) . "\n");
    exit(1);
}
