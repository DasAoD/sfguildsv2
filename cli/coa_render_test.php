#!/usr/bin/env php
<?php
/**
 * coa_render_test.php — Testet den Gildenwappen-Compositor isoliert.
 *
 * Rendert ein Wappen direkt aus einem /coa-Hex-Code in eine lokale PNG-Datei
 * -- OHNE die Datenbank oder die Live-Seite anzufassen. Zum Verfeinern von
 * Palette (COA_PALETTE_GUESS) und Layout (COA_TARGETS) in
 * includes/guild_crest.php, ohne jedes Mal einen echten Upload/Sync/Cache-
 * Zyklus durchlaufen zu müssen.
 *
 * Aufruf:
 *   php cli/coa_render_test.php <hex-code> [output.png] [assets-dir]
 *
 * Beispiel (Avadhuta Gita):
 *   php cli/coa_render_test.php 12030A040D0F4109090308 /tmp/test.png
 *
 * <assets-dir> Default: data/guildcrests (wo die echten Assets auf dns1
 * schon liegen, siehe install/GUILD_CREST_AUTO_INSTALL.md).
 */

if (PHP_SAPI !== 'cli') {
    exit(1);
}

require_once __DIR__ . '/../includes/guild_crest.php';

$code = $argv[1] ?? null;
if (!$code) {
    fwrite(STDERR, "Usage: php cli/coa_render_test.php <hex-code> [output.png] [assets-dir]\n");
    exit(2);
}

$outPath   = $argv[2] ?? (__DIR__ . '/../coa_test_output.png');
$assetsDir = $argv[3] ?? (__DIR__ . '/../data/guildcrests');

$decoded = decodeCoaCode($code);
if ($decoded === null) {
    fwrite(STDERR, "Ungültiger Code: $code (erwartet 22 Hex-Zeichen)\n");
    exit(1);
}
fwrite(STDERR, "Decodiert: " . json_encode($decoded) . "\n");

$canvas = renderGuildCrestImage($code, $assetsDir);
if (!$canvas) {
    fwrite(STDERR, "Render fehlgeschlagen -- Assets fehlen unter $assetsDir?\n");
    exit(1);
}

imagepng($canvas, $outPath);
imagedestroy($canvas);
fwrite(STDERR, "Gespeichert: $outPath\n");
