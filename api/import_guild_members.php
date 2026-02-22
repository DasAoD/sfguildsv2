<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/logger.php';

// Check authentication
requireAdminAPI();

// Get database connection
$db = getDB();

// Get POST data
$guildId = isset($_POST['guild_id']) ? (int)$_POST['guild_id'] : 0;

if ($guildId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Guild ID required']);
    exit;
}

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'File upload failed']);
    exit;
}

$tmpFile = $_FILES['file']['tmp_name'];

try {
    $result = importMembersCsv($db, $guildId, $tmpFile);
    
    // Update guild last_import_at
    $stmt = $db->prepare("UPDATE guilds SET updated_at = datetime('now') WHERE id = ?");
    $stmt->execute([$guildId]);
    
    // Get guild name for logging
    $guildInfo = $db->prepare('SELECT name FROM guilds WHERE id = ?');
    $guildInfo->execute([$guildId]);
    $guildRow = $guildInfo->fetch(PDO::FETCH_ASSOC);
    
    logActivity('Mitglieder importiert', [
        'Guild-ID'     => $guildId,
        'Gilde'        => $guildRow['name'] ?? '?',
        'Eingefügt'    => $result['inserted'],
        'Aktualisiert' => $result['updated'],
        'Übersprungen' => $result['skipped']
    ]);
    
    echo json_encode([
        'success' => true,
        'inserted' => $result['inserted'],
        'updated' => $result['updated'],
        'skipped' => $result['skipped']
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

/**
 * Import members from CSV (ported from import_sftools.php)
 */
function importMembersCsv($db, $guildId, $csvPath) {
    if (!is_file($csvPath) || !is_readable($csvPath)) {
        throw new RuntimeException("CSV nicht lesbar");
    }
    
    $fh = fopen($csvPath, 'rb');
    if (!$fh) {
        throw new RuntimeException("Konnte CSV nicht öffnen");
    }
    
    // Detect delimiter
    $firstLine = fgets($fh);
    if ($firstLine === false) {
        fclose($fh);
        throw new RuntimeException("CSV ist leer");
    }
    $delimiter = detectCsvDelimiter($firstLine);
    rewind($fh);
    
    // Read header
    $header = fgetcsv($fh, 0, $delimiter);
    if (!$header || count($header) < 2) {
        fclose($fh);
        throw new RuntimeException("CSV-Header konnte nicht gelesen werden");
    }
    
    // Map columns
    $map = [];
    foreach ($header as $i => $col) {
        $col = toUtf8((string)$col);
        $k = normalizeHeader($col);
        
        if ($k === '' || str_contains($k, 'mitglieder')) continue;
        
        if ($k === 'name') $map['name'] = $i;
        elseif ($k === 'rang' || $k === 'rank') $map['rank'] = $i;
        elseif ($k === 'level') $map['level'] = $i;
        elseif ($k === 'zulonline') $map['last_online'] = $i;
        elseif ($k === 'gildenbeitritt' || $k === 'joinedat') $map['joined_at'] = $i;
        elseif ($k === 'goldschatz' || $k === 'gold') $map['gold'] = $i;
        elseif ($k === 'lehrmeister' || $k === 'mentor') $map['mentor'] = $i;
        elseif ($k === 'ritterhalle' || $k === 'knighthall' || $k === 'knight_hall') $map['knight_hall'] = $i;
        elseif ($k === 'gildenpet' || $k === 'guild_pet' || $k === 'guildpet') $map['guild_pet'] = $i;
        elseif ($k === 'tageoffline' || $k === 'days_offline' || $k === 'daysoffline') $map['days_offline'] = $i;
        elseif ($k === 'entlassen' || $k === 'fired_at') $map['fired_at'] = $i;
        elseif ($k === 'verlassen' || $k === 'left_at') $map['left_at'] = $i;
        elseif ($k === 'sonstigenotizen' || $k === 'notes') $map['notes'] = $i;
    }
    
    if (!isset($map['name'])) {
        fclose($fh);
        throw new RuntimeException('CSV enthält keine Spalte "Name"');
    }
    
    // Prepare statements
    $sel = $db->prepare("SELECT id, fired_at, left_at, notes, rank FROM members WHERE guild_id = ? AND name = ? LIMIT 1");
    
    $upd = $db->prepare("
        UPDATE members
        SET level = ?, rank = ?, last_online = ?, joined_at = ?, gold = ?, mentor = ?, 
            knight_hall = ?, guild_pet = ?, days_offline = ?, fired_at = ?, left_at = ?, notes = ?, updated_at = datetime('now')
        WHERE id = ?
    ");
    
    $ins = $db->prepare("
        INSERT INTO members (
            guild_id, name, level, last_online, joined_at, gold, mentor, knight_hall, guild_pet, 
            days_offline, rank, fired_at, left_at, notes, updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, datetime('now'))
    ");
    
    $inserted = 0;
    $updated = 0;
    $skipped = 0;
    $seen = [];
    
    $db->beginTransaction();
    try {
        while (($row = fgetcsv($fh, 0, $delimiter)) !== false) {
            $name = cleanName((string)csvGet($row, $map, 'name', ''));
            if ($name === '') {
                $skipped++;
                continue;
            }
            
            // Skip duplicates in same CSV
            $nameKey = mb_strtolower($name, 'UTF-8');
            if (isset($seen[$nameKey])) {
                $skipped++;
                continue;
            }
            $seen[$nameKey] = true;
            
            // Extract values
            $rankRaw = trim(toUtf8((string)csvGet($row, $map, 'rank', '')));
            $lastOnline = trim(toUtf8((string)csvGet($row, $map, 'last_online', '')));
            $joinedAt = trim(toUtf8((string)csvGet($row, $map, 'joined_at', '')));
            $notesRaw = trim(toUtf8((string)csvGet($row, $map, 'notes', '')));
            $firedRaw = trim(toUtf8((string)csvGet($row, $map, 'fired_at', '')));
            $leftRaw = trim(toUtf8((string)csvGet($row, $map, 'left_at', '')));
            
            $level = csvGet($row, $map, 'level', '') === '' ? null : (int)csvGet($row, $map, 'level', 0);
            $gold = csvGet($row, $map, 'gold', '') === '' ? null : (int)csvGet($row, $map, 'gold', 0);
            $mentor = csvGet($row, $map, 'mentor', '') === '' ? null : (int)csvGet($row, $map, 'mentor', 0);
            $knightHall = csvGet($row, $map, 'knight_hall', '') === '' ? null : (int)csvGet($row, $map, 'knight_hall', 0);
            $guildPet = csvGet($row, $map, 'guild_pet', '') === '' ? null : (int)csvGet($row, $map, 'guild_pet', 0);
            $daysOffline = null; // calculated from last_online
            
            $fired = $firedRaw !== '' ? normalizeDateDE($firedRaw) : null;
            $left = $leftRaw !== '' ? normalizeDateDE($leftRaw) : null;
            
            // fired wins over left
            if (!empty($fired)) {
                $left = null;
            }
            
            // Check if member exists
            $sel->execute([$guildId, $name]);
            $existing = $sel->fetch();
            
            if ($existing) {
                // Update existing member
                $newFired = $firedRaw !== '' ? $fired : ($existing['fired_at'] ?? null);
                $newLeft = $leftRaw !== '' ? $left : ($existing['left_at'] ?? null);
                if ($firedRaw !== '' && !empty($newFired)) {
                    $newLeft = null;
                }
                
                $newNotes = $notesRaw !== '' ? $notesRaw : ($existing['notes'] ?? null);
                $newRank = $rankRaw !== '' ? $rankRaw : ($existing['rank'] ?? null);
                
                $upd->execute([
                    $level,
                    $newRank === '' ? null : $newRank,
                    $lastOnline === '' ? null : $lastOnline,
                    $joinedAt === '' ? null : $joinedAt,
                    $gold,
                    $mentor,
                    $knightHall,
                    $guildPet,
                    $daysOffline,
                    empty($newFired) ? null : $newFired,
                    empty($newLeft) ? null : $newLeft,
                    $newNotes === '' ? null : $newNotes,
                    (int)$existing['id']
                ]);
                $updated++;
            } else {
                // Insert new member
                $ins->execute([
                    $guildId,
                    $name,
                    $level,
                    $lastOnline === '' ? null : $lastOnline,
                    $joinedAt === '' ? null : $joinedAt,
                    $gold,
                    $mentor,
                    $knightHall,
                    $guildPet,
                    $daysOffline,
                    $rankRaw === '' ? null : $rankRaw,
                    empty($fired) ? null : $fired,
                    empty($left) ? null : $left,
                    $notesRaw === '' ? null : $notesRaw
                ]);
                $inserted++;
            }
        }
        
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        fclose($fh);
        throw $e;
    }
    
    fclose($fh);
    
    return [
        'inserted' => $inserted,
        'updated' => $updated,
        'skipped' => $skipped
    ];
}

// Helper functions (ported from import_sftools.php)

function detectCsvDelimiter($line) {
    $commas = substr_count($line, ',');
    $semis = substr_count($line, ';');
    return $semis > $commas ? ';' : ',';
}

function toUtf8($s) {
    $s = str_replace("\xEF\xBB\xBF", '', $s);
    if ($s === '') return $s;
    
    if (function_exists('mb_check_encoding') && mb_check_encoding($s, 'UTF-8')) {
        return $s;
    }
    
    $enc = function_exists('mb_detect_encoding') 
        ? mb_detect_encoding($s, ['UTF-8', 'Windows-1252', 'ISO-8859-1', 'ISO-8859-2'], true)
        : null;
    
    if (!$enc) $enc = 'Windows-1252';
    
    $out = @iconv($enc, 'UTF-8//IGNORE', $s);
    return $out === false ? $s : $out;
}

function cleanName($s) {
    $s = toUtf8($s);
    $s = preg_replace('/\p{C}+/u', '', $s) ?? $s;
    $s = preg_replace('/^\p{Z}+|\p{Z}+$/u', '', $s) ?? trim($s);
    $s = preg_replace('/\s+/u', ' ', $s) ?? $s;
    return $s;
}

function normalizeHeader($s) {
    $s = str_replace("\xEF\xBB\xBF", '', $s);
    $s = trim(mb_strtolower($s, 'UTF-8'));
    $s = str_replace([' ', "\t", "\r", "\n"], '', $s);
    $s = str_replace(['.', ':', '(', ')'], '', $s);
    $s = str_replace(['ä', 'ö', 'ü', 'ß'], ['ae', 'oe', 'ue', 'ss'], $s);
    return $s;
}

function csvGet($row, $map, $key, $default = '') {
    if (!isset($map[$key])) return $default;
    $idx = $map[$key];
    return $row[$idx] ?? $default;
}

function normalizeDateDE($dateStr) {
    // Convert DD.MM.YYYY to YYYY-MM-DD
    if (preg_match('/^(\d{2})\.(\d{2})\.(\d{4})$/', $dateStr, $m)) {
        return $m[3] . '-' . $m[2] . '-' . $m[1];
    }
    return $dateStr;
}
