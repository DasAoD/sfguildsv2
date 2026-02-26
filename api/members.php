<?php
/**
 * Members API Endpoint
 * Returns members of a specific guild
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/logger.php';

// Get guild ID
$guildId = get('guild_id');

if (!$guildId) {
    jsonResponse(['success' => false, 'message' => 'Guild ID erforderlich'], 400);
}

// Check if user is logged in
$isLoggedIn = isLoggedIn();

try {
    // Get guild info
    $guild = queryOne('SELECT * FROM guilds WHERE id = ?', [$guildId]);
    
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
        'SELECT *, 
         CASE 
            WHEN (fired_at IS NOT NULL AND fired_at != "") OR (left_at IS NOT NULL AND left_at != "") THEN 1 
            ELSE 0 
         END as is_departed,
         CASE 
            WHEN last_online IS NULL OR last_online = "" THEN 9999
            ELSE julianday("now") - julianday(substr(last_online, 7, 4) || "-" || substr(last_online, 4, 2) || "-" || substr(last_online, 1, 2))
         END as days_offline_calc
         FROM members 
         WHERE ' . $whereClause . ' 
         ORDER BY 
         is_departed ASC,
         CASE 
            WHEN rank = "Anführer" THEN 1
            WHEN rank = "Offizier" THEN 2
            WHEN rank = "Mitglied" THEN 3
            ELSE 4
         END,
         CASE
            WHEN last_online IS NULL OR last_online = "" THEN 9999
            WHEN julianday("now") - julianday(substr(last_online, 7, 4) || "-" || substr(last_online, 4, 2) || "-" || substr(last_online, 1, 2)) < 7 THEN 0
            ELSE julianday("now") - julianday(substr(last_online, 7, 4) || "-" || substr(last_online, 4, 2) || "-" || substr(last_online, 1, 2))
         END ASC,
         level DESC',
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
        
        // Calculate days offline
        if ($member['last_online']) {
            $lastOnline = strtotime($member['last_online']);
            $now = time();
            $diff = $now - $lastOnline;
            $member['days_offline'] = floor($diff / (60 * 60 * 24));
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
    $completedRaids = 0;
    $raidRow = queryOne(
        "SELECT CASE WHEN cnt > 50 THEN 50 WHEN cnt < 0 THEN 0 ELSE cnt END AS completed_raids
         FROM (
             SELECT COUNT(DISTINCT opponent_guild) - 1 AS cnt
             FROM sf_eval_battles
             WHERE guild_id = ? AND battle_type = 'raid'
         )",
        [$guildId]
    );
    if ($raidRow) {
        $completedRaids = (int)$raidRow['completed_raids'];
    }

    $goldschatzPct  = min(200, round(min(100, ($goldschatzTotal  / 1000) * 100) + ($completedRaids * 2), 1));
    $lehrmeisterPct = min(200, round(min(100, ($lehrmeisterTotal / 1000) * 100) + ($completedRaids * 2), 1));
    
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
            'goldschatz_total'  => $goldschatzTotal,
            'lehrmeister_total' => $lehrmeisterTotal,
            'goldschatz_pct'    => $goldschatzPct,
            'lehrmeister_pct'   => $lehrmeisterPct,
        ],
        'is_logged_in' => $isLoggedIn
    ]);
    
} catch (Exception $e) {
    logError('Members API failed', ['guild_id' => $guildId, 'error' => $e->getMessage()]);
    jsonResponse([
        'success' => false,
        'message' => 'Fehler beim Laden der Mitglieder'
    ], 500);
}