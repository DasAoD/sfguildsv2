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
    
    // Get statistics for each guild
    foreach ($guilds as &$guild) {
        $guildId = $guild['id'];
        
        // Count total members (active = not fired, not left)
        $totalMembers = queryOne(
            "SELECT COUNT(*) as count FROM members 
             WHERE guild_id = ? 
             AND fired_at IS NULL 
             AND left_at IS NULL",
            [$guildId]
        );
        
        // Calculate average level of active members
        $avgLevel = queryOne(
            "SELECT AVG(level) as avg_level FROM members 
             WHERE guild_id = ? 
             AND fired_at IS NULL 
             AND left_at IS NULL",
            [$guildId]
        );
        
        // Get total battles (all time)
        $battles = queryOne(
            "SELECT COUNT(*) as total_battles FROM sf_eval_battles 
             WHERE guild_id = ?",
            [$guildId]
        );
        
        $totalBattlesCount = $battles['total_battles'] ?? 0;
        $activeMembersCount = $totalMembers['count'] ?? 0;
        
        // Calculate participation quote from sf_eval_participants
        if ($activeMembersCount > 0 && $totalBattlesCount > 0) {
            // Get total possible participations (members * battles)
            $possibleParticipations = $activeMembersCount * $totalBattlesCount;
            
            // Get actual participations from last 30 days
            $actualParticipations = queryOne(
                "SELECT COUNT(*) as count
                 FROM sf_eval_participants p
                 JOIN sf_eval_battles b ON p.battle_id = b.id
                 WHERE b.guild_id = ?
                 AND date(b.battle_date) >= date('now', '-30 days')
                 AND p.participated = 1",
                [$guildId]
            );
            
            $participated = $actualParticipations['count'] ?? 0;
            $participationQuote = round(($participated / $possibleParticipations) * 100);
        } else {
            $participationQuote = 0;
        }
        
        // Calculate knight hall total
        $knightHall = queryOne(
            "SELECT SUM(knight_hall) as total FROM members 
             WHERE guild_id = ? 
             AND fired_at IS NULL 
             AND left_at IS NULL",
            [$guildId]
        );
        
        // Add statistics to guild object
        $guild['active_members'] = $activeMembersCount;
        $guild['avg_level'] = round($avgLevel['avg_level'] ?? 0);
        $guild['participation_quote'] = $participationQuote;
        $guild['total_battles'] = $totalBattlesCount;
        
        // Calculate knight hall total for active members
        $knightHall = queryOne(
            "SELECT SUM(knight_hall) as total FROM members 
             WHERE guild_id = ? 
             AND fired_at IS NULL 
             AND left_at IS NULL",
            [$guildId]
        );
        $guild['knight_hall_total'] = (int)($knightHall['total'] ?? 0);
        $guild['knight_hall_total'] = $knightHall['total'] ?? 0;
        
        // Get last update from members table
        $lastUpdate = queryOne(
            "SELECT MAX(updated_at) as last_update FROM members WHERE guild_id = ?",
            [$guildId]
        );
        $guild['last_update'] = $lastUpdate['last_update'] ?? null;
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
