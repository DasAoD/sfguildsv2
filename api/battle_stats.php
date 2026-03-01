<?php
require_once __DIR__ . '/../includes/bootstrap_api.php';

checkAuthAPI();

$db = getDB();

$guildId = isset($_GET['guild_id']) ? (int)$_GET['guild_id'] : 0;

if ($guildId <= 0) {
    jsonError('Guild ID required', 400);
}

try {
    // Get guild info
    $stmt = $db->prepare("SELECT id, name, server, crest_file FROM guilds WHERE id = ?");
    $stmt->execute([$guildId]);
    $guild = $stmt->fetch();
    
    if (!$guild) {
        jsonError('Guild not found', 404);
    }
    
    // Get battle counts
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total_battles,
            SUM(CASE WHEN battle_type = 'attack' THEN 1 ELSE 0 END) as attacks,
            SUM(CASE WHEN battle_type = 'defense' THEN 1 ELSE 0 END) as defenses,
            SUM(CASE WHEN battle_type = 'raid' THEN 1 ELSE 0 END) as raids
        FROM sf_eval_battles
        WHERE guild_id = ?
    ");
    $stmt->execute([$guildId]);
    $battleCounts = $stmt->fetch();
    
    // Get player statistics
    // JOIN: Match participant to member even when one has server tag and other doesn't
    // GROUP BY: player_name_norm (without server tag) merges both variants
    // Display: Prefer member name (has server tag + is canonical)
    $stmt = $db->prepare("
        SELECT
            COALESCE(MAX(m.name), MAX(p.player_name)) as player_name,
            MAX(m.level) as current_level,
            MAX(m.rank) as current_rank,
            MAX(m.fired_at) as fired_at,
            MAX(m.left_at) as left_at,
            COUNT(*) as total_fights,
            SUM(CASE WHEN b.battle_type = 'attack' THEN 1 ELSE 0 END) as total_attacks,
            SUM(CASE WHEN b.battle_type = 'defense' THEN 1 ELSE 0 END) as total_defenses,
            SUM(CASE WHEN b.battle_type = 'raid' THEN 1 ELSE 0 END) as total_raids,
            SUM(CASE WHEN b.battle_type = 'attack' AND p.participated = 1 THEN 1 ELSE 0 END) as attacks,
            SUM(CASE WHEN b.battle_type = 'defense' AND p.participated = 1 THEN 1 ELSE 0 END) as defenses,
            SUM(CASE WHEN b.battle_type = 'raid' AND p.participated = 1 THEN 1 ELSE 0 END) as raids_participated,
            SUM(p.participated) as participated,
            SUM(1 - p.participated) as missed,
            ROUND(100.0 * SUM(p.participated) / COUNT(*), 1) as participation_pct
        FROM sf_eval_participants p
        JOIN sf_eval_battles b ON b.id = p.battle_id
        LEFT JOIN members m ON m.guild_id = b.guild_id 
            AND (LOWER(m.name) = LOWER(p.player_name) 
              OR (p.player_server_tag IS NOT NULL AND p.player_server_tag != '' 
                  AND LOWER(m.name) = LOWER(p.player_name || ' (' || p.player_server_tag || ')')))
        WHERE b.guild_id = ?
        GROUP BY p.player_name_norm
        HAVING (MAX(m.fired_at) IS NULL OR MAX(m.fired_at) = '')
            AND (MAX(m.left_at) IS NULL OR MAX(m.left_at) = '')
        ORDER BY 
            CASE 
                WHEN MAX(m.rank) = 'Anführer' THEN 1
                WHEN MAX(m.rank) = 'Offizier' THEN 2
                WHEN MAX(m.rank) = 'Mitglied' THEN 3
                ELSE 4
            END,
            participation_pct DESC,
            total_fights DESC,
            p.player_name
    ");
    $stmt->execute([$guildId]);
    $players = $stmt->fetchAll();
    
    // Calculate stats
    $totalBattles = (int)($battleCounts['total_battles'] ?? 0);
    $attacks = (int)($battleCounts['attacks'] ?? 0);
    $defenses = (int)($battleCounts['defenses'] ?? 0);
    $raids = (int)($battleCounts['raids'] ?? 0);
    
    // Calculate average participation
    $avgParticipation = 0;
    if (count($players) > 0) {
        $totalPct = array_sum(array_column($players, 'participation_pct'));
        $avgParticipation = round($totalPct / count($players), 1);
    }
    
    // Get top missing (players with most missed fights) - Top 5
    $topMissingFiltered = array_filter($players, fn($p) => $p['missed'] > 0);
    usort($topMissingFiltered, fn($a, $b) => $b['missed'] <=> $a['missed']);
    $topMissing = array_slice($topMissingFiltered, 0, 5);
    
    // Distribution (by participation percentage) - Option 4
    $excellent = count(array_filter($players, fn($p) => $p['participation_pct'] >= 95));
    $good = count(array_filter($players, fn($p) => $p['participation_pct'] >= 85 && $p['participation_pct'] < 95));
    $acceptable = count(array_filter($players, fn($p) => $p['participation_pct'] >= 75 && $p['participation_pct'] < 85));
    $poor = count(array_filter($players, fn($p) => $p['participation_pct'] < 75));
    
    // Inactive players - calculate days_offline LIVE
    $stmt = $db->prepare("
        SELECT 
            name, 
            level,
            last_online,
            CASE 
                WHEN last_online IS NULL OR last_online = '' THEN 9999
                ELSE julianday('now') - julianday(substr(last_online, 7, 4) || '-' || substr(last_online, 4, 2) || '-' || substr(last_online, 1, 2))
            END as days_offline
        FROM members
        WHERE guild_id = ? 
            AND fired_at IS NULL 
            AND left_at IS NULL
            AND (
                last_online IS NULL 
                OR last_online = ''
                OR julianday('now') - julianday(substr(last_online, 7, 4) || '-' || substr(last_online, 4, 2) || '-' || substr(last_online, 1, 2)) >= 7
            )
        ORDER BY days_offline DESC
        LIMIT 12
    ");
    $stmt->execute([$guildId]);
    $inactive = $stmt->fetchAll();
    
    jsonResponse([
        'success' => true,
        'guild' => $guild,
        'stats' => [
            'total_battles' => $totalBattles,
            'attacks' => $attacks,
            'defenses' => $defenses,
            'raids' => $raids,
            'avg_participation' => $avgParticipation,
            'player_count' => count($players)
        ],
        'players' => $players,
        'insights' => [
            'top_missing' => $topMissing,
            'distribution' => [
                'excellent' => $excellent,   // 95-100%
                'good' => $good,            // 85-94%
                'acceptable' => $acceptable, // 75-84%
                'poor' => $poor             // < 75%
            ],
            'inactive' => $inactive
        ]
    ]);
    
} catch (Throwable $e) {
    logError('Battle stats failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
    jsonError('Fehler beim Laden der Statistiken', 500);
}
