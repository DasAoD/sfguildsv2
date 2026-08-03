<?php
/**
 * Auflösung von Ausrüstungsgegenständen (typ + model_id + color + class) zu
 * einer Icon-Datei unter public/assets/images/items/.
 *
 * Herkunft der Grafiken: aus den Unity-Asset-Bundles des Spiels geripped.
 * Waffen/Rüstung/Handschuhe/Schuhe/Helme/Gürtel sind je nach Klassen-
 * Beschränkung des Items (item.class aus der sf-api: nur Warrior/Mage/Scout
 * möglich) in eigenen Bundles, Ring/Amulett/Talisman/Schild sind klassen-
 * unabhängig (ein gemeinsames Bundle für alle Klassen).
 *
 * Wichtig: Der Item-Typ wird aus item.typ bestimmt, NICHT aus dem
 * Ausrüstungs-Slotnamen. Die Ausrüstungsliste der sf-api ist positions-
 * basiert (EnumMap<EquipmentSlot, Option<Item>>) — bei der Assassine liegt
 * die zweite Waffe technisch im "Shield"-Slot, ist aber ein echtes
 * Waffen-Item. Slot und tatsächlicher Item-Typ können also auseinanderfallen.
 *
 * Zuordnung Item-Typ-ID (Bestandteil des Sprite-Namens "itmX_...") gemäß
 * sf-api ItemType::parse (github.com/the-marenga/sf-api).
 */

// Item-Typ-ID (aus item.typ) -> Namensbestandteil des Bundle-Ordners
// für klassen-spezifische Typen
const ITEM_TYPE_BUNDLE_SUFFIX = [
    1 => 'weapons',     // Weapon
    3 => 'armor',       // BreastPlate
    4 => 'shoes',       // FootWear
    5 => 'gloves',      // Gloves
    6 => 'helmets',     // Hat
    7 => 'belts',       // Belt
];

// Item-Typ-ID -> Bundle für klassenunabhängige Typen (ein Bundle für alle Klassen)
const ITEM_TYPE_SHARED_BUNDLE = [
    2  => 'shields_sd',    // Shield
    8  => 'necklaces_sd',  // Amulet
    9  => 'rings_sd',      // Ring
    10 => 'talismans_sd',  // Talisman
];

// Fallback nur für alte, vor Einführung des typ-Felds synchronisierte
// Datensätze (kein "typ" in char_data_json) — Slotname als Notlösung.
// Liefert bei der Assassinen-Zweitwaffe (Slot "Shield", tatsächlich Weapon)
// bis zum nächsten character_sync-Lauf ein falsches Icon.
const ITEM_SLOT_TYPE_ID_FALLBACK = [
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

/**
 * Bestimmt die Item-Typ-ID aus dem rohen sf-api typ-Feld. Unit-Varianten
 * (z.B. "Hat") kommen per JSON als String, Varianten mit Daten
 * (z.B. Weapon{min_dmg,max_dmg}) als Objekt mit einem Schlüssel
 * (z.B. {"Weapon": {...}}) — Standard-serde-Serialisierung von Rust-Enums.
 */
function itemTypeIdFromRaw($rawTyp): ?int
{
    if (is_string($rawTyp)) {
        $unitTypes = ['Hat' => 6, 'BreastPlate' => 3, 'FootWear' => 4, 'Gloves' => 5,
            'Amulet' => 8, 'Belt' => 7, 'Ring' => 9, 'Talisman' => 10];
        return $unitTypes[$rawTyp] ?? null;
    }
    if (is_array($rawTyp)) {
        if (array_key_exists('Weapon', $rawTyp)) return 1;
        if (array_key_exists('Shield', $rawTyp)) return 2;
    }
    return null;
}

/**
 * Liefert den Web-Pfad zum Icon eines Ausrüstungsgegenstands, oder null,
 * wenn Typ/Item nicht auflösbar sind oder keine passende Datei existiert.
 *
 * Die Sprite-Dateinamen enthalten neben Typ/model_id/color teils noch ein
 * weiteres Suffix (z.B. Klassen-Markierung bei class-spezifischen Typen),
 * dessen genaue Bedeutung nicht für jeden Typ eindeutig geklärt ist. Daher
 * wird per Glob nach "itm{typ}_{model_id}_{color}*.png" gesucht statt den
 * Dateinamen exakt zu konstruieren — das ist robust gegenüber diesen
 * Suffix-Varianten, weil Typ/model_id/color durch die Unterstriche im Muster
 * eindeutig von einem eventuellen weiteren Suffix abgegrenzt sind.
 *
 * @param string $slot Ausrüstungs-Slotname, nur als Fallback für Alt-Daten
 *                      ohne "typ"-Feld verwendet (siehe ITEM_SLOT_TYPE_ID_FALLBACK)
 * @param array  $item Ausrüstungs-Item wie in members.char_data_json gespeichert
 *                      (erwartet: typ, model_id, color, class)
 */
function resolveItemIconPath(string $slot, array $item): ?string
{
    $typeId = itemTypeIdFromRaw($item['typ'] ?? null) ?? (ITEM_SLOT_TYPE_ID_FALLBACK[$slot] ?? null);
    $modelId = $item['model_id'] ?? null;
    if ($typeId === null || $modelId === null) {
        return null;
    }
    $color = $item['color'] ?? 1;

    if (isset(ITEM_TYPE_SHARED_BUNDLE[$typeId])) {
        $bundle = ITEM_TYPE_SHARED_BUNDLE[$typeId];
    } else {
        $bundleSuffix = ITEM_TYPE_BUNDLE_SUFFIX[$typeId] ?? null;
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
