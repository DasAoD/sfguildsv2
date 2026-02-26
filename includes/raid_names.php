<?php
/**
 * S&F Gildenraid Name Mapping
 * 
 * 50 Raid-Etagen, ab Etage 51 wiederholen sich die Namen
 * mit aufsteigender römischer Nummerierung: (II), (III), etc.
 * 
 * Raid-ID aus der API → Etage + Durchlauf berechnen:
 *   Etage    = ((id - 1) % 50) + 1
 *   Durchlauf = floor((id - 1) / 50) + 1
 */

const RAID_NAMES = [
    1  => 'Das Planschbecken',
    2  => '6 1/2 Weltmeer',
    3  => 'Untiefen der Tiefsee',
    4  => 'Kleingartenkolonie',
    5  => 'Vorschule des Grauens',
    6  => 'Reich der Dunkelzwerge',
    7  => 'Das gläserne Schloss',
    8  => 'Downtown Brooklyn',
    9  => 'Die Fledermausgrotte',
    10 => 'Das Gobboterland',
    11 => 'Wunderlampenreich',
    12 => 'Der Ostpol',
    13 => 'Reich der Titanen',
    14 => 'Absurdistan',
    15 => 'Das Knochenschloss',
    16 => 'Mythen und Mysterien',
    17 => 'Ancawatridromedar',
    18 => 'Barbarien',
    19 => 'Extraterra IV',
    20 => 'Pfad zur Hölle',
    21 => 'Höllische Hölle',
    22 => 'Streichelzoo des Todes',
    23 => 'Im Drachenbau',
    24 => 'Schwarzwassermoor',
    25 => 'Monsterkindergarten',
    26 => 'Das Gruselkabinett',
    27 => 'Wilde Monsterparty',
    28 => 'Grabräuberhöhle',
    29 => 'Wiedergängergruft',
    30 => 'Kaisers Knüppelgarde',
    31 => 'Die Verkehrte Welt',
    32 => 'Vorboten der Toten',
    33 => 'Fest der Fressfeinde',
    34 => 'Das Affentheater',
    35 => 'Die Popelpopulation',
    36 => 'Das Klingenspringen',
    37 => 'Im Dunkel der Nacht',
    38 => 'Asozialer Brennpunkt',
    39 => 'Der Alte Friedhof',
    40 => 'Urzeitviecher',
    41 => 'Der Schadzauberberg',
    42 => 'Gragosh\'s Grauen',
    43 => 'Ragorth der Räuber',
    44 => 'Schlabba the Mudd',
    45 => 'Xanthippopothamien',
    46 => 'Im Gemüsegarten',
    47 => 'Das Vorzeitige Ende',
    48 => 'Entkäferung',
    49 => 'Fehler im System',
    50 => 'Beim großen Boss',
];

/**
 * Römische Zahlen (für Raid-Durchläufe)
 */
function toRoman(int $num): string {
    $map = [
        1000 => 'M', 900 => 'CM', 500 => 'D', 400 => 'CD',
        100  => 'C', 90  => 'XC', 50  => 'L', 40  => 'XL',
        10   => 'X', 9   => 'IX', 5   => 'V', 4   => 'IV',
        1    => 'I'
    ];
    $result = '';
    foreach ($map as $value => $numeral) {
        while ($num >= $value) {
            $result .= $numeral;
            $num -= $value;
        }
    }
    return $result;
}

/**
 * Raid-Name zurück in eine Raid-ID auflösen (Umkehrfunktion zu resolveRaidName)
 *
 * @param string $name  z.B. "Im Dunkel der Nacht (II)" oder "Das Planschbecken"
 * @return int          Raid-ID (1-basiert), oder 0 wenn nicht erkannt
 */
function resolveRaidId(string $name): int {
    // Zyklus aus optionalem Suffix "(II)", "(III)", ... extrahieren
    $cycle = 1;
    if (preg_match('/^(.+?)\s*\(([IVXLCDM]+)\)\s*$/u', $name, $m)) {
        $baseName = trim($m[1]);
        $cycle    = fromRoman($m[2]);
    } else {
        $baseName = trim($name);
    }

    // Etagen-Nummer per Reverse-Lookup suchen
    $stage = array_search($baseName, RAID_NAMES, true);
    if ($stage === false) {
        return 0; // Unbekannter Name
    }

    return ($cycle - 1) * 50 + (int)$stage;
}

/**
 * Römische Zahl in Integer umwandeln
 */
function fromRoman(string $roman): int {
    $map = ['M'=>1000,'CM'=>900,'D'=>500,'CD'=>400,'C'=>100,'XC'=>90,
            'L'=>50,'XL'=>40,'X'=>10,'IX'=>9,'V'=>5,'IV'=>4,'I'=>1];
    $result = 0;
    $i = 0;
    $roman = strtoupper($roman);
    while ($i < strlen($roman)) {
        // Zweizeichentoken zuerst prüfen
        if ($i + 1 < strlen($roman) && isset($map[substr($roman, $i, 2)])) {
            $result += $map[substr($roman, $i, 2)];
            $i += 2;
        } elseif (isset($map[$roman[$i]])) {
            $result += $map[$roman[$i]];
            $i++;
        } else {
            break;
        }
    }
    return $result ?: 1;
}

/**
 * Raid-ID aus der API in einen lesbaren Namen auflösen
 * @param string|int $raidId  Die Gegner-ID aus dem Kampfbericht
 * @return string             z.B. "Fest der Fressfeinde (II)"
 */
function resolveRaidName($raidId): string {
    $id = (int)$raidId;
    
    if ($id <= 0) {
        return 'Gildenraid';
    }
    
    $stage = (($id - 1) % 50) + 1;
    $cycle = intdiv($id - 1, 50) + 1;
    
    $name = RAID_NAMES[$stage] ?? "Raid-Etage $stage";
    
    if ($cycle > 1) {
        $name .= ' (' . toRoman($cycle) . ')';
    }
    
    return $name;
}