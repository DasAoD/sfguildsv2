<?php
/**
 * Members API Endpoint
 * Returns members of a specific guild
 */
require_once __DIR__ . '/../includes/bootstrap_api.php';

require_once __DIR__ . '/../includes/raid_names.php';

// Get guild ID
$guildId = get('guild_id');

if (!$guildId) {
    jsonResponse(['success' => false, 'message' => 'Guild ID erforderlich'], 400);
}

// Check if user is logged in
$isLoggedIn = isLoggedIn();

try {
    // Get guild info
    $guild = queryOne('SELECT id, name, server, notes, tag, crest_file, last_import_at, created_at, updated_at FROM guilds WHERE id = ?', [$guildId]);
    
    if (!$guild) {
        jsonResponse(['success' => false, 'message' => 'Gilde nicht gefunden'], 404);
    }
    
    // Get all members with complex sorting
    // Filter fired/left members if not logged in
    $whereClause = 'guild_id = ?';
    if (!$isLoggedIn) {
        $whereClause .= ' AND fired_at IS NULL AND left_at IS NULL';
    }
    
    $members = query(
        'SELECT id, name, level, last_online, joined_at, gold, mentor, knight_hall, guild_pet, days_offline, notes, fired_at, left_at, rank, 
         CASE 
            WHEN (fired_at IS NOT NULL AND fired_at != "") OR (left_at IS NOT NULL AND left_at != "") THEN 1 
            ELSE 0 
         END as is_departed,
         CASE 
            WHEN last_online IS NULL OR last_online = "" THEN 9999
            ELSE julianday(date("now")) - julianday(date(last_online))
         END as days_offline_calc
         FROM members 
         WHERE ' . $whereClause . ' 
         ORDER BY 
         is_departed ASC,
         -- Aktive mit >=7 Tagen offline kommen als Block ans Ende der Aktiven-Liste
         CASE
            WHEN (fired_at IS NOT NULL AND fired_at != "") OR (left_at IS NOT NULL AND left_at != "") THEN 0
            WHEN last_online IS NULL OR last_online = "" THEN 1
            WHEN days_offline_calc >= 7 THEN 1
            ELSE 0
         END ASC,
         -- Rang nur für kurzfristig Aktive (<7 Tage) und Entlassene; lang-Offline ranglos (99)
         CASE
            WHEN (fired_at IS NULL OR fired_at = "") AND (left_at IS NULL OR left_at = "")
                 AND (last_online IS NULL OR last_online = "" OR days_offline_calc >= 7)
            THEN 99
            WHEN rank = "Anführer" THEN 1
            WHEN rank = "Offizier" THEN 2
            WHEN rank = "Mitglied" THEN 3
            ELSE 4
         END ASC,
         -- Aktive/Entlassene: nach Level DESC (negiert); lang-Offline: nach Tagen offline ASC
         CASE
            WHEN (fired_at IS NULL OR fired_at = "") AND (left_at IS NULL OR left_at = "")
                 AND (last_online IS NULL OR last_online = "" OR days_offline_calc >= 7)
            THEN days_offline_calc
            ELSE CAST(-level AS REAL)
         END ASC',
        [$guildId]
    );
    
    // Process members data
    foreach ($members as &$member) {
        // Convert German date format (dd.mm.yyyy) to ISO (yyyy-mm-dd) for JavaScript
        if ($member['last_online'] && preg_match('/^(\d{2})\.(\d{2})\.(\d{4})$/', $member['last_online'], $matches)) {
            $member['last_online'] = $matches[3] . '-' . $matches[2] . '-' . $matches[1];
        }
        if ($member['joined_at'] && preg_match('/^(\d{2})\.(\d{2})\.(\d{4})$/', $member['joined_at'], $matches)) {
            $member['joined_at'] = $matches[3] . '-' . $matches[2] . '-' . $matches[1];
        }
        if ($member['fired_at'] && preg_match('/^(\d{2})\.(\d{2})\.(\d{4})$/', $member['fired_at'], $matches)) {
            $member['fired_at'] = $matches[3] . '-' . $matches[2] . '-' . $matches[1];
        }
        if ($member['left_at'] && preg_match('/^(\d{2})\.(\d{2})\.(\d{4})$/', $member['left_at'], $matches)) {
            $member['left_at'] = $matches[3] . '-' . $matches[2] . '-' . $matches[1];
        }
        
        // Calculate days offline (date-only, UTC — last_online is stored as UTC ISO 8601)
        // gmdate() statt date() damit CEST-Serverzeit den UTC-Timestamp nicht verfälscht
        if ($member['last_online']) {
            $lastOnlineDate = gmdate('Y-m-d', strtotime($member['last_online']));
            $todayDate = gmdate('Y-m-d');
            $member['days_offline'] = (int)((strtotime($todayDate) - strtotime($lastOnlineDate)) / 86400);
        } else {
            $member['days_offline'] = 0;
        }
        
        // Determine member status
        if ($member['fired_at'] && $member['fired_at'] !== '') {
            $member['status'] = 'fired';
        } elseif ($member['left_at'] && $member['left_at'] !== '') {
            $member['status'] = 'left';
        } elseif ($member['days_offline'] >= 14) {
            $member['status'] = 'inactive';
        } else {
            $member['status'] = 'active';
        }
    }
    unset($member); // CRITICAL: Break the reference to avoid bugs in subsequent loops
    
    // Calculate stats BEFORE removing sensitive data (only active members, not fired/left)
    $activeMembers = 0;
    $avgLevel = 0;
    $totalLevel = 0;
    $knightHallTotal = 0;
    $goldschatzTotal = 0;
    $lehrmeisterTotal = 0;
    
    foreach ($members as $member) {
        // Only count active members (not fired, not left)
        if ((!$member['fired_at'] || $member['fired_at'] === '') && (!$member['left_at'] || $member['left_at'] === '')) {
            $activeMembers++;
            $totalLevel += $member['level'];
            $knightHallTotal  += $member['knight_hall'] ?? 0;
            $goldschatzTotal  += $member['gold']   ?? 0;
            $lehrmeisterTotal += $member['mentor'] ?? 0;
        }
    }
    
    if ($activeMembers > 0) {
        $avgLevel = round($totalLevel / $activeMembers);
    }

    // Abgeschlossene Raids für Prozent-Berechnung
    // Höchsten bekannten Raid-Namen holen → in ID umwandeln → max_id - 1 = abgeschlossene Raids
    $completedRaids = 0;
    $raidRows = query(
        "SELECT DISTINCT opponent_guild
         FROM sf_eval_battles
         WHERE guild_id = ? AND battle_type = 'raid'",
        [$guildId]
    );
    if ($raidRows) {
        $maxId = 0;
        foreach ($raidRows as $row) {
            $id = resolveRaidId($row['opponent_guild']);
            if ($id > $maxId) $maxId = $id;
        }
        $completedRaids = min(50, max(0, $maxId - 1));
    }

    // Korrekte Formel laut Helpshift:
    // Gruppenskill = Mitglieder-Skills + min(50, abgeschl. Raids) × 10, gedeckelt auf 1000
    // Bonus% = Gruppenskill ÷ 5
    $raidPoints     = min(50, $completedRaids) * 10;
    $gsGruppenskill = min(1000, $goldschatzTotal  + $raidPoints);
    $lmGruppenskill = min(1000, $lehrmeisterTotal + $raidPoints);
    $goldschatzPct  = round($gsGruppenskill / 5, 1);
    $lehrmeisterPct = round($lmGruppenskill / 5, 1);
    
    // Now remove sensitive data for public view
    if (!$isLoggedIn) {
        foreach ($members as &$member) {
            unset($member['notes']);
            unset($member['fired_at']);
            unset($member['left_at']);
            unset($member['days_offline']);
            unset($member['last_online']);
            unset($member['joined_at']);
            $member['status'] = 'public'; // Override status for public view
        }
        unset($member); // Break the reference
    }
    
    // Guild-Daten für Public-View whitelisten – nie SELECT * ungefiltert zurückgeben
    $guildPublic = $isLoggedIn ? $guild : [
        'id'             => $guild['id'],
        'name'           => $guild['name'],
        'server'         => $guild['server'],
        'crest_file'     => $guild['crest_file'],
        'last_import_at' => $guild['last_import_at'] ?? null,
        'notes'          => $guild['notes'] ?? null,
    ];

    jsonResponse([
        'success' => true,
        'guild' => $guildPublic,
        'members' => $members,
        'stats' => [
            'active_members'   => $activeMembers,
            'avg_level'        => $avgLevel,
            'knight_hall_total' => $knightHallTotal,
            'goldschatz_total'  => $goldschatzTotal + $raidPoints,
            'lehrmeister_total' => $lehrmeisterTotal + $raidPoints,
            'goldschatz_pct'    => $goldschatzPct,
            'lehrmeister_pct'   => $lehrmeisterPct,
        ],
        'is_logged_in' => $isLoggedIn
    ]);
    
} catch (Throwable $e) {
    logError('Members API failed', ['guild_id' => $guildId, 'error' => $e->getMessage()]);
    jsonResponse([
        'success' => false,
        'message' => 'Fehler beim Laden der Mitglieder'
    ], 500);
}