<?php
/**
 * Player Details API
 * Returns detailed battle history for a specific player
 */
require_once __DIR__ . '/../includes/bootstrap_api.php';

// Check authentication (API style - returns JSON on failure)
checkAuthAPI();

$guildId = get('guild_id');
$playerName = get('player_name');

if (!$guildId || !$playerName) {
    jsonResponse(['success' => false, 'message' => 'Guild ID und Player Name erforderlich'], 400);
}

try {
    $db = getDB();
    
    // Compute normalized name (strip server tag for matching)
    $playerNameNorm = mb_strtolower(trim($playerName), 'UTF-8');
    $playerNameNorm = preg_replace('/\s*\([sw]\d+\w*\)/u', '', $playerNameNorm);
    $playerNameNorm = trim($playerNameNorm);
    
    // Get player info from members table (try exact match, then without tag)
    $stmt = $db->prepare("
        SELECT name, level, rank, joined_at, last_online, days_offline
        FROM members
        WHERE guild_id = ? AND (LOWER(name) = LOWER(?) OR LOWER(name) LIKE LOWER(? || ' (%'))
        LIMIT 1
    ");
    $stmt->execute([$guildId, $playerName, $playerNameNorm]);
    $playerInfo = $stmt->fetch();
    
    // Get battle history using player_name_norm for consistent matching
    $stmt = $db->prepare("
        SELECT 
            b.id as battle_id,
            b.battle_date,
            b.battle_time,
            b.battle_type,
            b.opponent_guild,
            p.participated,
            p.player_level
        FROM sf_eval_participants p
        JOIN sf_eval_battles b ON b.id = p.battle_id
        WHERE b.guild_id = ? 
            AND p.player_name_norm = ?
        ORDER BY b.battle_date DESC, b.battle_time DESC
    ");
    $stmt->execute([$guildId, $playerNameNorm]);
    $battles = $stmt->fetchAll();
    
    // Calculate stats
    $totalBattles = count($battles);
    $participated = count(array_filter($battles, fn($b) => $b['participated'] == 1));
    $missed = $totalBattles - $participated;
    $participationPct = $totalBattles > 0 ? round(100.0 * $participated / $totalBattles, 1) : 0;
    
    $attacks = count(array_filter($battles, fn($b) => $b['battle_type'] === 'attack'));
    $defenses = count(array_filter($battles, fn($b) => $b['battle_type'] === 'defense'));
    $raids = count(array_filter($battles, fn($b) => $b['battle_type'] === 'raid'));
    $attacksParticipated = count(array_filter($battles, fn($b) => $b['battle_type'] === 'attack' && $b['participated'] == 1));
    $defensesParticipated = count(array_filter($battles, fn($b) => $b['battle_type'] === 'defense' && $b['participated'] == 1));
    $raidsParticipated = count(array_filter($battles, fn($b) => $b['battle_type'] === 'raid' && $b['participated'] == 1));
    
    // Monthly distribution (for chart)
    $monthlyData = [];
    foreach ($battles as $battle) {
        $month = substr($battle['battle_date'], 0, 7); // YYYY-MM
        if (!isset($monthlyData[$month])) {
            $monthlyData[$month] = ['total' => 0, 'participated' => 0];
        }
        $monthlyData[$month]['total']++;
        if ($battle['participated'] == 1) {
            $monthlyData[$month]['participated']++;
        }
    }
    
    jsonResponse([
        'success' => true,
        'player' => [
            'name' => $playerInfo['name'] ?? $playerName,
            'level' => $playerInfo['level'] ?? null,
            'rank' => $playerInfo['rank'] ?? null,
            'joined_at' => $playerInfo['joined_at'] ?? null,
            'last_online' => $playerInfo['last_online'] ?? null,
            'days_offline' => $playerInfo['days_offline'] ?? null
        ],
        'stats' => [
            'total_fights' => $totalBattles,
            'participated' => $participated,
            'missed' => $missed,
            'participation_pct' => $participationPct,
            'attacks' => $attacks,
            'defenses' => $defenses,
            'raids' => $raids,
            'attacks_participated' => $attacksParticipated,
            'defenses_participated' => $defensesParticipated,
            'raids_participated' => $raidsParticipated
        ],
        'battles' => $battles,
        'monthly' => $monthlyData
    ]);
    
} catch (Throwable $e) {
    logError('Player details failed', ['error' => $e->getMessage()]);
    jsonResponse(['success' => false, 'message' => 'Fehler beim Laden der Spieler-Details'], 500);
}
