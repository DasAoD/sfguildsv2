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

    // --- Assemble ---
    foreach ($guilds as &$guild) {
        $id = $guild['id'];

        $members  = $memberStatsMap[$id]  ?? [];
        $battles  = $battleStatsMap[$id]  ?? [];
        $partRow  = $participationMap[$id] ?? [];

        $activeMembersCount  = (int)($members['active_members']  ?? 0);
        $totalBattlesCount   = (int)($battles['total_battles']   ?? 0);
        $participated        = (int)($partRow['participated']    ?? 0);
        $possible            = (int)($partRow['possible']        ?? 0);

        if ($possible > 0) {
            $participationQuote = min(100, round(($participated / $possible) * 100));
        } else {
            $participationQuote = 0;
        }

        $guild['active_members']     = $activeMembersCount;
        $guild['avg_level']          = (int)($members['avg_level']       ?? 0);
        $guild['knight_hall_total']  = (int)($members['knight_hall_total'] ?? 0);
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
