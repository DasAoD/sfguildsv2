<?php
/**
 * Guild Crest Auto-Render
 *
 * Baut ein Gildenwappen-Bild automatisch aus dem /coa-Code, den der S&F-Server
 * in `owngroupsave.groupSave` mitschickt (siehe rust_examples/member_sync.rs),
 * statt einen manuellen Wappen-Upload zu verlangen.
 *
 * Manueller Upload (guilds.crest_file) hat immer Vorrang und bleibt
 * unverändert bestehen -- diese Funktion wird nur aufgerufen, wenn kein
 * crest_file gesetzt ist (Fallback/Backup bleibt manueller Upload).
 *
 * Die rohen Wappen-Grafiken (aus den Spiel-Assets extrahiert) liegen NICHT
 * im Git-Repo, sondern nur lokal unter DATA_PATH . '/guildcrests/' auf dem
 * Server (siehe install/GUILD_CREST_AUTO_INSTALL.md für Deployment).
 */

const COA_CATEGORY_NUM = [
    'helm' => 1, 'supporter' => 2, 'shield' => 3, 'banner' => 4,
    'helmet' => 5, 'order' => 6, 'emblem' => 7,
];

// Palette -- Indizes 4 (#009900, Gurkistan-Screenshot), 8 (#7f7f7f) und
// 9 (#b00000, beide Avadhuta-Gita-Screenshot) sind an echten Screenshots
// verifiziert. Der Rest ist weiterhin eine Näherung und muss verfeinert
// werden, sobald mehr reale Vergleichsdaten vorliegen (siehe Memory
// sfguildsv2-guild-crest-reverse-engineering). Unbekannte Indizes fallen
// auf ein neutrales Grau zurück (COA_PALETTE_FALLBACK), nicht mehr auf
// Grün -- das hatte Index 8/9 vor der Verifizierung falsch grün eingefärbt.
// Index 3 war ursprünglich blind von Index 4 übernommen (beide "grünlich"
// geraten) -- an Avadhuta Gitas Schild (wo Index 3 die dominante Zone-2-
// Fläche ist, nicht nur ungetestet wie bei Gurkistan) als ~#191919 (fast
// schwarz) verifiziert.
const COA_PALETTE_GUESS = [
    0 => [120, 120, 120], 1 => [200, 60, 60], 2 => [60, 100, 200], 3 => [25, 25, 25],
    4 => [0, 153, 0], 5 => [230, 200, 40], 6 => [140, 80, 200], 7 => [230, 140, 30],
    8 => [127, 127, 127], 9 => [176, 0, 0],
];
const COA_PALETTE_FALLBACK = [150, 150, 150];

/**
 * Zerlegt den 11-Byte /coa-Hex-Code in Kategorie-Varianten + Farbindizes.
 * @return array{helm:int,supporter:int,shield:int,banner:int,helmet:int,
 *   order:int,emblem:int,zone1:int,zone1b:int,zone2:int,figure_color:int}|null
 */
function decodeCoaCode(string $hex): ?array {
    $hex = trim($hex);
    if (!preg_match('/^[0-9A-Fa-f]{22}$/', $hex)) {
        return null;
    }
    $bytes = array_values(unpack('C*', hex2bin($hex)));
    if (count($bytes) !== 11) {
        return null;
    }
    return [
        'helm' => $bytes[0] + 1, 'supporter' => $bytes[1] + 1, 'shield' => $bytes[2] + 1,
        'banner' => $bytes[3] + 1, 'helmet' => $bytes[4] + 1, 'order' => $bytes[5] + 1,
        'emblem' => $bytes[6] + 1, 'zone1' => $bytes[7], 'zone1b' => $bytes[8],
        'zone2' => $bytes[9], 'figure_color' => $bytes[10],
    ];
}

function coaLoadLayer(string $assetsDir, string $cat, int $variant, string $suffix = ''): ?\GdImage {
    $num = COA_CATEGORY_NUM[$cat];
    $path = "$assetsDir/$cat/coa_{$num}_{$variant}{$suffix}.png";
    if (!is_file($path)) {
        return null;
    }
    $im = @imagecreatefrompng($path);
    if (!$im) {
        return null;
    }
    imagealphablending($im, false);
    imagesavealpha($im, true);
    return $im;
}

function coaNewCanvas(int $w, int $h): \GdImage {
    $im = imagecreatetruecolor($w, $h);
    imagealphablending($im, false);
    imagesavealpha($im, true);
    $transparent = imagecolorallocatealpha($im, 0, 0, 0, 127);
    imagefilledrectangle($im, 0, 0, $w, $h, $transparent);
    return $im;
}

function coaResize(\GdImage $src, int $w, int $h): \GdImage {
    $dst = coaNewCanvas($w, $h);
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $w, $h, imagesx($src), imagesy($src));
    return $dst;
}

function coaPasteCentered(\GdImage $canvas, \GdImage $layer, float $cx, float $cy, float $scale = 1.0): void {
    if ($scale !== 1.0) {
        $layer = coaResize($layer, max(1, (int) round(imagesx($layer) * $scale)), max(1, (int) round(imagesy($layer) * $scale)));
    }
    $w = imagesx($layer);
    $h = imagesy($layer);
    $x = (int) round($cx - $w / 2);
    $y = (int) round($cy - $h / 2);
    imagealphablending($canvas, true);
    imagecopy($canvas, $layer, $x, $y, 0, 0, $w, $h);
    imagealphablending($canvas, false);
}

/**
 * Wappenbild-Tönung: die coa_7_*-Sprites sind alle eine reine Flach-Schablone
 * in (255,0,0) mit variierendem Alpha (verifiziert: Apfel- und Drachen-Sprite
 * beide exakt so) -- die Zielfarbe wird direkt eingesetzt, Alpha bleibt
 * erhalten, der ursprüngliche Rot-Kanal wird komplett ignoriert. Eine
 * Graustufen-Multiply-Tönung (frühere Version) ergab aus reinem Rot nur
 * ~30% Luminanz und damit einen viel zu dunklen Ton.
 */
function coaTintColorReplace(\GdImage $img, array $rgb): \GdImage {
    $w = imagesx($img);
    $h = imagesy($img);
    $out = coaNewCanvas($w, $h);
    $col = imagecolorallocatealpha($out, $rgb[0], $rgb[1], $rgb[2], 0);
    for ($y = 0; $y < $h; $y++) {
        for ($x = 0; $x < $w; $x++) {
            $rgba = imagecolorat($img, $x, $y);
            $a = ($rgba >> 24) & 0x7F;
            if ($a === 127) {
                continue; // Canvas ist schon transparent initialisiert
            }
            if ($a === 0) {
                imagesetpixel($out, $x, $y, $col);
            } else {
                imagesetpixel($out, $x, $y, imagecolorallocatealpha($out, $rgb[0], $rgb[1], $rgb[2], $a));
            }
        }
    }
    return $out;
}

/** Schild-Zonenfärbung: Multiply-Blend, gewichtet über G/B-Kanal der _color-Maske. */
function coaApplyShieldZones(\GdImage $shield, \GdImage $mask, array $zone1Rgb, array $zone2Rgb): \GdImage {
    $w = imagesx($shield);
    $h = imagesy($shield);
    if (imagesx($mask) !== $w || imagesy($mask) !== $h) {
        $mask = coaResize($mask, $w, $h);
    }
    $out = coaNewCanvas($w, $h);
    for ($y = 0; $y < $h; $y++) {
        for ($x = 0; $x < $w; $x++) {
            $base = imagecolorat($shield, $x, $y);
            $ba = ($base >> 24) & 0x7F;
            if ($ba === 127) {
                continue;
            }
            $br = ($base >> 16) & 0xFF;
            $bg = ($base >> 8) & 0xFF;
            $bb = $base & 0xFF;

            $m = imagecolorat($mask, $x, $y);
            $mg = ($m >> 8) & 0xFF;
            $mb = $m & 0xFF;

            if ($mg === 0 && $mb === 0) {
                imagesetpixel($out, $x, $y, imagecolorallocatealpha($out, $br, $bg, $bb, $ba));
                continue;
            }
            if ($mg >= $mb) {
                $weight = $mg / 255;
                $tint = $zone1Rgb;
            } else {
                $weight = $mb / 255;
                $tint = $zone2Rgb;
            }
            $mr = intdiv($br * $tint[0], 255);
            $mgc = intdiv($bg * $tint[1], 255);
            $mbc = intdiv($bb * $tint[2], 255);
            $nr = (int) round($br * (1 - $weight) + $mr * $weight);
            $ng = (int) round($bg * (1 - $weight) + $mgc * $weight);
            $nb = (int) round($bb * (1 - $weight) + $mbc * $weight);
            imagesetpixel($out, $x, $y, imagecolorallocatealpha($out, $nr, $ng, $nb, $ba));
        }
    }
    return $out;
}

/**
 * Rendert das komplette Wappen-Bild. Gibt null zurück, wenn Assets fehlen
 * oder der Code ungültig ist (Aufrufer soll dann auf Platzhalter zurückfallen).
 */
function renderGuildCrestImage(string $coaCode, string $assetsDir, int $canvasSize = 440): ?\GdImage {
    $d = decodeCoaCode($coaCode);
    if ($d === null) {
        return null;
    }

    $C = $canvasSize;
    $canvas = coaNewCanvas($C, $C);
    $cx = intdiv($C, 2);

    $helmdecke = coaLoadLayer($assetsDir, 'helm', $d['helm']);
    if (!$helmdecke) return null;
    coaPasteCentered($canvas, $helmdecke, $cx, (int) round($C * 0.50), 1.6);

    $shield = coaLoadLayer($assetsDir, 'shield', $d['shield']);
    $mask = coaLoadLayer($assetsDir, 'shield', $d['shield'], '_color');
    if (!$shield || !$mask) return null;
    $zone1Rgb = COA_PALETTE_GUESS[$d['zone1']] ?? COA_PALETTE_FALLBACK;
    $zone2Rgb = COA_PALETTE_GUESS[$d['zone2']] ?? COA_PALETTE_FALLBACK;
    $shieldTinted = coaApplyShieldZones($shield, $mask, $zone1Rgb, $zone2Rgb);
    $shieldCx = $cx;
    $shieldCy = (int) round($C * 0.52);
    coaPasteCentered($canvas, $shieldTinted, $shieldCx, $shieldCy, 1.6);

    $emblem = coaLoadLayer($assetsDir, 'emblem', $d['emblem']);
    if (!$emblem) return null;
    $figureRgb = COA_PALETTE_GUESS[$d['figure_color']] ?? COA_PALETTE_FALLBACK;
    $emblemTinted = coaTintColorReplace($emblem, $figureRgb);
    coaPasteCentered($canvas, $emblemTinted, $shieldCx, $shieldCy - (int) round($C * 0.02), 1.6);

    $supporter = coaLoadLayer($assetsDir, 'supporter', $d['supporter']);
    if (!$supporter) return null;
    coaPasteCentered($canvas, $supporter, $cx, (int) round($C * 0.72), 1.6);

    $helmet = coaLoadLayer($assetsDir, 'helmet', $d['helmet']);
    if (!$helmet) return null;
    coaPasteCentered($canvas, $helmet, $cx, (int) round($C * 0.24), 1.6);

    $order = coaLoadLayer($assetsDir, 'order', $d['order']);
    if (!$order) return null;
    coaPasteCentered($canvas, $order, $cx, (int) round($C * 0.80), 1.6);

    $banner = coaLoadLayer($assetsDir, 'banner', $d['banner']);
    if (!$banner) return null;
    coaPasteCentered($canvas, $banner, $cx, (int) round($C * 0.90), 1.6);

    return $canvas;
}

/**
 * Liefert den web-relativen Pfad (z.B. "guild_coa_5_a1b2c3.webp") zum
 * gecachten Auto-Wappen für diese Gilde, rendert bei Bedarf neu (wenn sich
 * $coaCode geändert hat oder noch keine Datei existiert). Gibt null zurück,
 * wenn kein coa_code vorhanden ist oder das Rendern fehlschlägt (Aufrufer
 * soll dann den Platzhalter-Icon zeigen).
 *
 * Cache-Invalidierung über den Hash im Dateinamen: ändert sich der Code,
 * entsteht automatisch ein neuer Dateiname; alte Datei bleibt liegen (wird
 * nicht aktiv aufgeräumt -- analog zu den seltenen manuellen Crest-Uploads).
 */
function resolveGuildCrestImage(int $guildId, ?string $coaCode): ?string {
    if (empty($coaCode)) {
        return null;
    }
    $hash = substr(md5($coaCode), 0, 8);
    $filename = "guild_coa_{$guildId}_{$hash}.webp";
    $outputPath = __DIR__ . '/../public/assets/images/' . $filename;

    if (is_file($outputPath)) {
        return $filename;
    }

    $assetsDir = defined('DATA_PATH') ? DATA_PATH . '/guildcrests' : __DIR__ . '/../data/guildcrests';
    if (!is_dir($assetsDir)) {
        return null; // Assets noch nicht deployed
    }

    try {
        $canvas = renderGuildCrestImage($coaCode, $assetsDir);
        if (!$canvas) {
            return null;
        }
        $ok = imagewebp($canvas, $outputPath, 90);
        imagedestroy($canvas);
        return $ok ? $filename : null;
    } catch (Throwable $e) {
        if (function_exists('logError')) {
            logError('Guild crest auto-render failed', ['guild_id' => $guildId, 'error' => $e->getMessage()]);
        }
        return null;
    }
}
