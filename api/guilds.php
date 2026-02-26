<?php
/**
 * Guilds API Endpoint
 * Returns all guilds with statistics
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/logger.php';

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

    // --- Batch Query 4: Abgeschlossene Raids pro Gilde ---
    // DISTINCT opponent_guild zählt jeden Raid nur einmal (mehrere Mitglieder = mehrere Berichte).
    // -1 schließt den aktuell laufenden/letzten Raid aus – nur sicher abgeschlossene zählen.
    // Maximum 50, da ab Raid 50 der volle Bonus (100%) erreicht ist.
    $raidStats = query(
        "SELECT guild_id,
                CASE WHEN cnt > 50 THEN 50 WHEN cnt < 0 THEN 0 ELSE cnt END AS completed_raids
         FROM (
             SELECT guild_id,
                    COUNT(DISTINCT CAST(opponent_guild AS INTEGER)) - 1 AS cnt
             FROM sf_eval_battles
             WHERE battle_type = 'raid'
             GROUP BY guild_id
         )"
    );
    $raidStatsMap = array_column($raidStats, null, 'guild_id');

    // --- Assemble ---
    foreach ($guilds as &$guild) {
        $id = $guild['id'];

        $members  = $memberStatsMap[$id]  ?? [];
        $battles  = $battleStatsMap[$id]  ?? [];
        $partRow  = $participationMap[$id] ?? [];
        $raids    = $raidStatsMap[$id]    ?? [];

        $activeMembersCount  = (int)($members['active_members']  ?? 0);
        $totalBattlesCount   = (int)($battles['total_battles']   ?? 0);
        $participated        = (int)($partRow['participated']    ?? 0);
        $possible            = (int)($partRow['possible']        ?? 0);
        $goldschatzTotal     = (int)($members['goldschatz_total']  ?? 0);
        $lehrmeisterTotal    = (int)($members['lehrmeister_total'] ?? 0);
        $completedRaids      = (int)($raids['completed_raids']   ?? 0);

        if ($possible > 0) {
            $participationQuote = min(100, round(($participated / $possible) * 100));
        } else {
            $participationQuote = 0;
        }

        // Prozent-Bonus: Skills (max 100%) + Raids (je 2%, max 100%) = max 200%
        $goldschatzPct  = min(200, round(min(100, ($goldschatzTotal  / 1000) * 100) + ($completedRaids * 2), 1));
        $lehrmeisterPct = min(200, round(min(100, ($lehrmeisterTotal / 1000) * 100) + ($completedRaids * 2), 1));

        $guild['active_members']     = $activeMembersCount;
        $guild['avg_level']          = (int)($members['avg_level']       ?? 0);
        $guild['knight_hall_total']  = (int)($members['knight_hall_total'] ?? 0);
        $guild['goldschatz_total']   = $goldschatzTotal;
        $guild['lehrmeister_total']  = $lehrmeisterTotal;
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