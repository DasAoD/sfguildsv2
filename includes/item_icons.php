<?php
/**
 * Auflösung von Ausrüstungsgegenständen (Slot + model_id + color + class) zu
 * einer Icon-Datei unter public/assets/images/items/.
 *
 * Herkunft der Grafiken: aus den Unity-Asset-Bundles des Spiels geripped.
 * Waffen/Rüstung/Handschuhe/Schuhe/Helme/Gürtel sind je nach Klassen-
 * Beschränkung des Items (item.class aus der sf-api: nur Warrior/Mage/Scout
 * möglich) in eigenen Bundles, Ring/Amulett/Talisman/Schild sind klassen-
 * unabhängig (ein gemeinsames Bundle für alle Klassen).
 *
 * Zuordnung Item-Typ-ID (Bestandteil des Sprite-Namens "itmX_...") gemäß
 * sf-api ItemType::parse (github.com/the-marenga/sf-api).
 */

const ITEM_SLOT_TYPE_ID = [
    'Weapon'      => 1,
    'Shield'      => 2,
    'BreastPlate' => 3,
    'FootWear'    => 4,
    'Gloves'      => 5,
    'Hat'         => 6,
    'Belt'        => 7,
    'Amulet'      => 8,
    'Ring'        => 9,
    'Talisman'    => 10,
];

// Slot -> Namensbestandteil des Bundle-Ordners für klassen-spezifische Slots
const ITEM_SLOT_BUNDLE_SUFFIX = [
    'Hat'         => 'helmets',
    'BreastPlate' => 'armor',
    'Gloves'      => 'gloves',
    'FootWear'    => 'shoes',
    'Belt'        => 'belts',
    'Weapon'      => 'weapons',
];

// Slots, die klassenunabhängig sind (ein einziges Bundle für alle Klassen)
const ITEM_SHARED_BUNDLE = [
    'Amulet'   => 'necklaces_sd',
    'Ring'     => 'rings_sd',
    'Talisman' => 'talismans_sd',
    'Shield'   => 'shields_sd',
];

/**
 * Liefert den Web-Pfad zum Icon eines Ausrüstungsgegenstands, oder null,
 * wenn Slot/Item nicht auflösbar sind oder keine passende Datei existiert.
 *
 * Die Sprite-Dateinamen enthalten neben Typ/model_id/color teils noch ein
 * weiteres Suffix (z.B. Klassen-Markierung bei class-spezifischen Slots),
 * dessen genaue Bedeutung nicht für jeden Slot-Typ eindeutig geklärt ist.
 * Daher wird per Glob nach "itm{typ}_{model_id}_{color}*.png" gesucht statt
 * den Dateinamen exakt zu konstruieren — das ist robust gegenüber diesen
 * Suffix-Varianten, weil Typ/model_id/color durch die Unterstriche im Muster
 * eindeutig von einem eventuellen weiteren Suffix abgegrenzt sind.
 *
 * @param string $slot Einer der Schlüssel aus ITEM_SLOT_TYPE_ID
 * @param array  $item Ausrüstungs-Item wie in members.char_data_json gespeichert
 *                      (erwartet: model_id, color, class)
 */
function resolveItemIconPath(string $slot, array $item): ?string
{
    $typeId = ITEM_SLOT_TYPE_ID[$slot] ?? null;
    $modelId = $item['model_id'] ?? null;
    if ($typeId === null || $modelId === null) {
        return null;
    }
    $color = $item['color'] ?? 1;

    if (isset(ITEM_SHARED_BUNDLE[$slot])) {
        $bundle = ITEM_SHARED_BUNDLE[$slot];
    } else {
        $bundleSuffix = ITEM_SLOT_BUNDLE_SUFFIX[$slot] ?? null;
        if ($bundleSuffix === null) {
            return null;
        }
        $class = $item['class'] ?? 'Warrior';
        $bundle = strtolower($class) . $bundleSuffix . '_sd';
    }

    $dir = __DIR__ . '/../public/assets/images/items/' . $bundle;
    $matches = glob($dir . "/itm{$typeId}_{$modelId}_{$color}*.png");
    if (empty($matches)) {
        return null;
    }

    return "/assets/images/items/{$bundle}/" . basename($matches[0]);
}
