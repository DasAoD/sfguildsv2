<?php
/**
 * Guilds API Endpoint
 * Returns all guilds with statistics
 */
require_once __DIR__ . '/../includes/bootstrap_api.php';

require_once __DIR__ . '/../includes/raid_names.php';

// Guilds API is publicly accessible (no login required)

try {
    // Get all guilds
    $guilds = query('SELECT * FROM guilds ORDER BY name');

    if (empty($guilds)) {
        jsonResponse(['success' => true, 'guilds' => []]);
    }

    // --- Batch Query 1: Member stats per guild ---
    $memberStats = query(
        "SELECT
            guild_id,
            COUNT(*)         AS active_members,
            ROUND(AVG(level)) AS avg_level,
            SUM(knight_hall)  AS knight_hall_total,
            SUM(gold)         AS goldschatz_total,
            SUM(mentor)       AS lehrmeister_total,
            MAX(updated_at)   AS last_update
         FROM members
         WHERE fired_at IS NULL AND left_at IS NULL
         GROUP BY guild_id"
    );
    $memberStatsMap = array_column($memberStats, null, 'guild_id');

    // --- Batch Query 2: Battle counts per guild (total, for display) ---
    $battleStats = query(
        "SELECT guild_id, COUNT(*) AS total_battles
         FROM sf_eval_battles
         GROUP BY guild_id"
    );
    $battleStatsMap = array_column($battleStats, null, 'guild_id');

    // --- Batch Query 3: Participation (last 30 days) per guild ---
    // Beide Werte (participated + possible) auf denselben 30-Tage-Zeitraum beschränken,
    // damit die Quote konsistent ist (nicht "Teilnahmen aus 30 Tagen" / "alle jemals")
    $participationStats = query(
        "SELECT b.guild_id,
                SUM(p.participated) AS participated,
                COUNT(*) AS possible
         FROM sf_eval_participants p
         JOIN sf_eval_battles b ON p.battle_id = b.id
         WHERE b.battle_date >= date('now', '-30 days')
         GROUP BY b.guild_id"
    );
    $participationMap = array_column($participationStats, null, 'guild_id');

    // --- Batch Query 4: Raid-Namen pro Gilde für Max-ID-Berechnung ---
    // Holt alle verschiedenen Raid-Namen pro Gilde.
    // In PHP wird der höchste Name in eine Raid-ID aufgelöst → max_id - 1 = abgeschlossene Raids.
    $raidNames = query(
        "SELECT guild_id, opponent_guild
         FROM sf_eval_battles
         WHERE battle_type = 'raid'
         GROUP BY guild_id, opponent_guild"
    );
    // Gruppieren nach guild_id
    $raidNamesMap = [];
    foreach ($raidNames as $row) {
        $raidNamesMap[$row['guild_id']][] = $row['opponent_guild'];
    }
    // Pro Gilde: höchste Raid-ID bestimmen → abgeschlossene = max_id - 1, cap 50
    $completedRaidsMap = [];
    foreach ($raidNamesMap as $gid => $names) {
        $maxId = 0;
        foreach ($names as $name) {
            $id = resolveRaidId($name);
            if ($id > $maxId) $maxId = $id;
        }
        $completedRaidsMap[$gid] = min(50, max(0, $maxId - 1));
    }

    // --- Assemble ---
    foreach ($guilds as &$guild) {
        $id = $guild['id'];

        $members  = $memberStatsMap[$id]  ?? [];
        $battles  = $battleStatsMap[$id]  ?? [];
        $partRow  = $participationMap[$id] ?? [];
        $raids    = $completedRaidsMap[$id] ?? 0;

        $activeMembersCount  = (int)($members['active_members']  ?? 0);
        $totalBattlesCount   = (int)($battles['total_battles']   ?? 0);
        $participated        = (int)($partRow['participated']    ?? 0);
        $possible            = (int)($partRow['possible']        ?? 0);
        $goldschatzTotal     = (int)($members['goldschatz_total']  ?? 0);
        $lehrmeisterTotal    = (int)($members['lehrmeister_total'] ?? 0);
        $completedRaids      = (int)$raids;

        if ($possible > 0) {
            $participationQuote = min(100, round(($participated / $possible) * 100));
        } else {
            $participationQuote = 0;
        }

        // Korrekte Formel laut Helpshift:
        // Gruppenskill = Mitglieder-Skills + min(50, abgeschl. Raids) × 10, gedeckelt auf 1000
        // Bonus% = Gruppenskill ÷ 5
        $raidPoints      = min(50, $completedRaids) * 10;
        $gsGruppenskill  = min(1000, $goldschatzTotal  + $raidPoints);
        $lmGruppenskill  = min(1000, $lehrmeisterTotal + $raidPoints);
        $goldschatzPct   = round($gsGruppenskill  / 5, 1);
        $lehrmeisterPct  = round($lmGruppenskill  / 5, 1);

        $guild['active_members']     = $activeMembersCount;
        $guild['avg_level']          = (int)($members['avg_level']       ?? 0);
        $guild['knight_hall_total']  = (int)($members['knight_hall_total'] ?? 0);
        $guild['goldschatz_total']   = $goldschatzTotal + $raidPoints;
        $guild['lehrmeister_total']  = $lehrmeisterTotal + $raidPoints;
        $guild['goldschatz_pct']     = $goldschatzPct;
        $guild['lehrmeister_pct']    = $lehrmeisterPct;
        $guild['last_update']        = $members['last_update']            ?? null;
        $guild['total_battles']      = $totalBattlesCount;
        $guild['participation_quote'] = $participationQuote;
    }
    
    jsonResponse([
        'success' => true,
        'guilds' => $guilds
    ]);
    
} catch (Exception $e) {
    logError('Guilds API failed', ['error' => $e->getMessage()]);
    jsonResponse([
        'success' => false,
        'message' => 'Fehler beim Laden der Gilden'
    ], 500);
}