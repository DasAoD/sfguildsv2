<?php
/**
 * Einmalig: verkleinert bestehende hochgeladene Wappen (guild_<id>.webp) auf 512 px.
 */
if (PHP_SAPI !== 'cli') {
    exit('CLI only');
}

require_once __DIR__ . '/../includes/guild_crest.php';

foreach (glob(__DIR__ . '/../public/assets/images/guild_[0-9]*.webp') as $file) {
    $image = imagecreatefromwebp($file);
    if (!$image) {
        echo "skip $file\n";
        continue;
    }
    $before = filesize($file);
    $small = limitCrestSize($image);
    if ($small === $image) {
        imagedestroy($image);
        continue;
    }
    if (imagewebp($small, $file, 90) && filesize($file) > 0) {
        clearstatcache(true, $file);
        echo basename($file) . ": $before -> " . filesize($file) . " bytes\n";
    }
    imagedestroy($small);
}
