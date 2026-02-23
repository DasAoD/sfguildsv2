<?php
// Force JSON response for all errors
header('Content-Type: application/json');

require_once '../config/database.php';
require_once '../includes/auth.php';

// Check authentication
requireAdminAPI();

// Get database connection
$db = getDB();

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);
$guildId = $input['guild_id'] ?? null;
$date = $input['date'] ?? null;
$time = $input['time'] ?? null;
$text = $input['text'] ?? null;

if (!$guildId || !$date || !$time || !$text) {
    http_response_code(400);
    echo json_encode(['error' => 'Alle Felder sind erforderlich']);
    exit;
}

try {
    // Clean the battle report text
    $cleanedText = cleanBattleReport($text);
    
    // Parse battle report
    $parsed = parseBattleReport($cleanedText);
    
    if (!$parsed) {
        throw new Exception('Kampfbericht konnte nicht geparst werden');
    }
    
    // Create raw_hash for deduplication from cleaned text
    $rawHash = md5($cleanedText);
    
    // Check if battle already exists (by hash)
    $stmt = $db->prepare("SELECT id FROM sf_eval_battles WHERE raw_hash = ?");
    $stmt->execute([$rawHash]);
    if ($stmt->fetch()) {
        http_response_code(400);
        echo json_encode(['error' => 'Dieser Kampfbericht wurde bereits importiert (Duplikat erkannt)']);
        exit;
    }
    
    // Additional check: same guild, date, time, type, and opponent
    $stmt = $db->prepare("
        SELECT id FROM sf_eval_battles 
        WHERE guild_id = ? AND battle_date = ? AND battle_time = ? AND battle_type = ? AND opponent_guild = ?
    ");
    $stmt->execute([$guildId, $date, $time, $parsed['type'], $parsed['opponent']]);
    if ($stmt->fetch()) {
        http_response_code(400);
        echo json_encode(['error' => 'Ein Kampf mit diesem Datum, Uhrzeit, Typ und Gegner existiert bereits']);
        exit;
    }
    
    // Insert battle
    $stmt = $db->prepare("
        INSERT INTO sf_eval_battles (guild_id, battle_type, opponent_guild, battle_date, battle_time, raw_text, raw_hash)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $guildId,
        $parsed['type'],
        $parsed['opponent'],
        $date,
        $time,
        $text,
        $rawHash
    ]);
    
    $battleId = $db->lastInsertId();
    
    // Insert participants
    $stmt = $db->prepare("
        INSERT INTO sf_eval_participants (battle_id, player_name, player_name_norm, player_level, player_server_tag, participated)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    foreach ($parsed['participants'] as $participant) {
        $stmt->execute([
            $battleId,
            $participant['name'],
            normalizePlayerName($participant['name']),
            $participant['level'],
            $participant['server_tag'],
            $participant['participated']
        ]);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Kampfbericht erfolgreich importiert',
        'battle_id' => $battleId,
        'participants_count' => count($parsed['participants'])
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    logError('import_battle failed', ['error' => $e->getMessage()]);
    echo json_encode(['error' => 'Interner Fehler']);
}

/**
 * Clean battle report text by removing Unity formatting
 */
function cleanBattleReport($text) {
    // Remove voffset tags
    $text = preg_replace('/<voffset=[\d.]+em>/', '', $text);
    $text = str_replace('</voffset>', '', $text);
    
    // Remove sprite/class icons
    $text = preg_replace('/<sprite="class_icons" name="[^"]+">/', '', $text);
    
    // Remove color tags
    $text = preg_replace('/<color=#[A-F0-9]+>/', '', $text);
    $text = str_replace('</color>', '', $text);
    
    // Remove empty lines
    $text = preg_replace('/^\s*[\r\n]/m', '', $text);
    
    return trim($text);
}

/**
 * Parse battle report into structured data
 */
function parseBattleReport($text) {
    $lines = explode("\n", $text);
    $lines = array_map('trim', $lines);
    $lines = array_filter($lines); // Remove empty lines
    
    $result = [
        'type' => null,
        'opponent' => null,
        'participants' => []
    ];
    
    // Parse first line to get type and opponent
    $firstLine = $lines[0];
    if (preg_match('/^Angriff auf (.+)$/u', $firstLine, $matches)) {
        $result['type'] = 'attack';
        $result['opponent'] = trim($matches[1]);
    } elseif (preg_match('/^Verteidigung gegen Angreifer: (.+)$/u', $firstLine, $matches)) {
        $result['type'] = 'defense';
        $result['opponent'] = trim($matches[1]);
    } elseif (preg_match('/^Raid\s+"?(.+?)"?\s*$/u', $firstLine, $matches)) {
        $result['type'] = 'raid';
        $result['opponent'] = trim($matches[1]);
    } else {
        return null; // Invalid format
    }
    
    // Parse participants
    $currentSection = null;
    foreach ($lines as $line) {
        if (strpos($line, 'nicht teilgenommen haben') !== false) {
            $currentSection = 'not_participated';
            continue;
        }
        if (strpos($line, 'teilgenommen haben') !== false) {
            $currentSection = 'participated';
            continue;
        }
        
        // Skip header line
        if (strpos($line, 'Angriff') === 0 || strpos($line, 'Verteidigung') === 0) {
            continue;
        }
        if (strpos($line, 'Mitglieder') === 0) {
            continue;
        }
        
        // Parse player line: Name (Stufe Level) or Name (ServerTag) (Stufe Level)
        // Format: Name (s37de) (Stufe 283) oder Name (Stufe 283)
        if (!preg_match('/^(.*?)\s*\(Stufe\s*(\d+)\)\s*$/ui', $line, $m)) {
            continue;
        }

        $namePart = trim($m[1]);
        $level = (int)$m[2];

        $serverTag = null;
        $fullName = $namePart; // Keep full name with server tag
        
        // Extract server tag for reference (but keep in name)
        // Flexible pattern: (letter)(numbers)(letters/numbers) e.g. s43de, w51net, f25eu
        if (preg_match('/\(([a-z][0-9]+[a-z0-9]+)\)/ui', $namePart, $mm)) {
            $serverTag = strtolower($mm[1]);
        }

        $result['participants'][] = [
            'name' => $fullName, // Keep server tag in name: "Głowa (w51net)"
            'level' => $level,
            'server_tag' => $serverTag, // Still store for reference/validation
            'participated' => ($currentSection === 'participated') ? 1 : 0
        ];
    }
    
    return $result;
}

/**
 * Normalize player name for comparison
 * Keep server tags in name for consistency with members table
 */
function normalizePlayerName($name) {
    $name = mb_strtolower(trim($name), 'UTF-8');
    // Server-Tag entfernen für Gruppierung: (s37de), (s3eu), (w51net), etc.
    $name = preg_replace('/\s*\([sw]\d+\w*\)/u', '', $name);
    return trim($name);
}
