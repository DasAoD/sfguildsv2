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

// Palette: Die Indizes 2, 3, 4, 8, 9 und 11 sind an echten Wappen gemessen
// (Anzeigefarbe der Schildzone bzw. Figur). Der Rest ist weiterhin eine
// Näherung; unbekannte Indizes fallen auf ein neutrales Grau zurück
// (COA_PALETTE_FALLBACK).
const COA_PALETTE_GUESS = [
    0 => [120, 120, 120], 1 => [200, 60, 60], 2 => [179, 2, 205], 3 => [25, 25, 25],
    4 => [0, 153, 0], 5 => [230, 200, 40], 6 => [140, 80, 200], 7 => [230, 140, 30],
    8 => [127, 127, 127], 9 => [176, 0, 0], 11 => [251, 152, 4],
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
    imagepalettetotruecolor($im);
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

/**
 * Schild-Zonenfärbung: Die Zonen der Maske werden flach mit der Palettenfarbe
 * gefüllt (die Helligkeitsverläufe des Schild-Sprites zeigt das Spiel dort
 * nicht), Randpixel mischen sich über das Maskengewicht mit dem Sprite. Die
 * Maske trägt auch in transparenten Bereichen Farbwerte und wird deshalb über
 * ihren Alpha-Wert gewichtet.
 */
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
            $opacity = (127 - (($m >> 24) & 0x7F)) / 127;
            $zone1 = (($m >> 8) & 0xFF) / 255 * $opacity;
            $zone2 = ($m & 0xFF) / 255 * $opacity;
            if ($zone1 <= 0 && $zone2 <= 0) {
                imagesetpixel($out, $x, $y, imagecolorallocatealpha($out, $br, $bg, $bb, $ba));
                continue;
            }
            // An der Naht zweier Zonen haben beide nur Teilgewicht: die Farben untereinander mischen
            $weight = min(1.0, $zone1 + $zone2);
            $share = $zone1 / ($zone1 + $zone2);
            $tint = [];
            for ($c = 0; $c < 3; $c++) {
                $tint[$c] = $zone1Rgb[$c] * $share + $zone2Rgb[$c] * (1 - $share);
            }
            imagesetpixel($out, $x, $y, imagecolorallocatealpha(
                $out,
                (int) round($br * (1 - $weight) + $tint[0] * $weight),
                (int) round($bg * (1 - $weight) + $tint[1] * $weight),
                (int) round($bb * (1 - $weight) + $tint[2] * $weight),
                $ba
            ));
        }
    }
    return $out;
}

// Vergrößerung gegenüber den nativen Sprite-Pixeln
const COA_RENDER_SCALE = 2;

/**
 * Pivot-Offsets aller Wappen-Sprites aus den Spiel-Assets (m_Offset): alle
 * Ebenen teilen denselben Nullpunkt, der Offset ist seine Lage relativ zur
 * Sprite-Mitte (y nach oben, in Pixeln). Siehe public/assets/data/coa_offsets.json.
 */
function coaOffsets(): array {
    static $map = null;
    if ($map === null) {
        $file = __DIR__ . '/../public/assets/data/coa_offsets.json';
        if (!is_file($file)) {
            throw new RuntimeException('coa_offsets.json fehlt');
        }
        $map = json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
    }
    return $map;
}

/** Linke obere Ecke eines Sprites relativ zum gemeinsamen Nullpunkt (y nach unten). */
function coaTopLeft(string $sprite, int $w, int $h): ?array {
    $off = coaOffsets()[$sprite] ?? null;
    return is_array($off) && count($off) === 2 ? [-$off[0] - $w / 2, $off[1] - $h / 2] : null;
}

function coaPasteAt(\GdImage $canvas, \GdImage $layer, int $x, int $y, int $w, int $h): void {
    imagealphablending($canvas, true);
    imagecopyresampled($canvas, $layer, $x, $y, 0, 0, $w, $h, imagesx($layer), imagesy($layer));
    imagealphablending($canvas, false);
}

/**
 * Rendert das komplette Wappen-Bild. Gibt null zurück, wenn Assets oder
 * Offsets fehlen oder der Code ungültig ist (Aufrufer soll dann auf
 * Platzhalter zurückfallen).
 */
function renderGuildCrestImage(string $coaCode, string $assetsDir): ?\GdImage {
    $d = decodeCoaCode($coaCode);
    if ($d === null) {
        return null;
    }

    $layers = [];
    foreach (['helm', 'supporter', 'shield', 'emblem', 'helmet', 'banner', 'order'] as $cat) {
        $img = coaLoadLayer($assetsDir, $cat, $d[$cat]);
        if (!$img) return null;
        $layers[$cat] = $img;
    }
    $mask = coaLoadLayer($assetsDir, 'shield', $d['shield'], '_color');
    if (!$mask) return null;

    $names = [];
    foreach (COA_CATEGORY_NUM as $cat => $num) {
        $names[$cat] = "coa_{$num}_{$d[$cat]}";
    }
    $pos = [];
    foreach ($layers as $cat => $img) {
        $pos[$cat] = coaTopLeft($names[$cat], imagesx($img), imagesy($img));
        if ($pos[$cat] === null) return null;
    }
    $maskPos = coaTopLeft($names['shield'] . '_color', imagesx($mask), imagesy($mask));
    if ($maskPos === null) return null;

    // Die Zonen-Maske hat eine eigene Größe und Lage: im Schild-Raster ausrichten
    $maskAligned = coaNewCanvas(imagesx($layers['shield']), imagesy($layers['shield']));
    imagecopy($maskAligned, $mask, (int) round($maskPos[0] - $pos['shield'][0]), (int) round($maskPos[1] - $pos['shield'][1]), 0, 0, imagesx($mask), imagesy($mask));
    $zone1Rgb = COA_PALETTE_GUESS[$d['zone1']] ?? COA_PALETTE_FALLBACK;
    $zone2Rgb = COA_PALETTE_GUESS[$d['zone2']] ?? COA_PALETTE_FALLBACK;
    $layers['shield'] = coaApplyShieldZones($layers['shield'], $maskAligned, $zone1Rgb, $zone2Rgb);
    $layers['emblem'] = coaTintColorReplace($layers['emblem'], COA_PALETTE_GUESS[$d['figure_color']] ?? COA_PALETTE_FALLBACK);

    $margin = 3;
    $minX = $minY = PHP_INT_MAX;
    $maxX = $maxY = PHP_INT_MIN;
    foreach ($layers as $cat => $img) {
        $minX = min($minX, $pos[$cat][0]);
        $minY = min($minY, $pos[$cat][1]);
        $maxX = max($maxX, $pos[$cat][0] + imagesx($img));
        $maxY = max($maxY, $pos[$cat][1] + imagesy($img));
    }
    $s = COA_RENDER_SCALE;
    $canvas = coaNewCanvas((int) ceil(($maxX - $minX + 2 * $margin) * $s), (int) ceil(($maxY - $minY + 2 * $margin) * $s));
    foreach ($layers as $cat => $img) {
        coaPasteAt(
            $canvas, $img,
            (int) round(($pos[$cat][0] - $minX + $margin) * $s), (int) round(($pos[$cat][1] - $minY + $margin) * $s),
            (int) round(imagesx($img) * $s), (int) round(imagesy($img) * $s)
        );
    }

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

/**
 * Verkleinert ein Wappen auf maximal $max Pixel Kantenlänge (Seitenverhältnis
 * bleibt, Transparenz bleibt). Angezeigt wird es nur mit rund 60 Pixeln; die
 * Originale mit 2048 px waren der größte Posten beim Dashboard-Laden.
 */
function limitCrestSize(GdImage $image, int $max = 512): GdImage {
    $w = imagesx($image);
    $h = imagesy($image);
    if ($w <= $max && $h <= $max) {
        return $image;
    }
    $scale = $max / max($w, $h);
    $nw = max(1, (int) round($w * $scale));
    $nh = max(1, (int) round($h * $scale));
    $out = imagecreatetruecolor($nw, $nh);
    imagealphablending($out, false);
    imagesavealpha($out, true);
    imagefill($out, 0, 0, imagecolorallocatealpha($out, 0, 0, 0, 127));
    imagecopyresampled($out, $image, 0, 0, 0, 0, $nw, $nh, $w, $h);
    imagedestroy($image);
    return $out;
}
