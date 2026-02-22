<?php
/**
 * API: Fetch Reports for Single Character
 * Called by sf_fetch_reports.php in parallel
 * 
 * Usage: SF_PASSWORD=secret php sf_fetch_single.php '{"name":"Beedle","server":"f25.sfgame.net","guild":"Blutzirkel"}' USER_ID USERNAME
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/sf_helpers.php';

// Get command line arguments
if ($argc < 4) {
    echo json_encode(['success' => false, 'error' => 'Missing arguments']);
    exit(1);
}

$charData = json_decode($argv[1], true);
$userId = $argv[2];
$username = $argv[3];
// Passwort über Umgebungsvariable – nicht in argv (sonst in ps aux sichtbar)
$password = getenv('SF_PASSWORD');
if (empty($password)) {
    echo json_encode(['success' => false, 'error' => 'SF_PASSWORD not set']);
    exit(1);
}

$character = $charData['name'];
$server = $charData['server'];
$guild = '';

try {
    $db = getDB();
    
    // Create temp directory for this fetch
    $tempDir = __DIR__ . '/../storage/sf_reports/temp/' . $userId . '_' . time() . '_' . sanitizeGuildName($character);
    if (!mkdir($tempDir, 0775, true)) {
        throw new Exception('Konnte temp-Verzeichnis nicht erstellen');
    }
    
    // proc_open mit Array statt String: keine Shell involviert, sicherer und robuster
    // Credentials über env-Array, nicht in argv (nicht in ps aux sichtbar)
    $procCmd = [
        '/opt/sf-api/target/release/examples/fetch_guild_reports',
    ];
    $procEnv = array_merge($_ENV, [
        'SSO_USERNAME' => $username,
        'PASSWORD'     => $password,
        'SERVER_HOST'  => $server,
        'CHARACTER'    => $character,
        'OUT_DIR'      => $tempDir,
    ]);

    $descriptorspec = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $process = proc_open($procCmd, $descriptorspec, $procPipes, null, $procEnv);

    if (!is_resource($process)) {
        throw new Exception('Prozess konnte nicht gestartet werden');
    }

    fclose($procPipes[0]);
    $stdout = stream_get_contents($procPipes[1]);
    $stderr = stream_get_contents($procPipes[2]);
    fclose($procPipes[1]);
    fclose($procPipes[2]);
    $returnCode = proc_close($process);

    $output = $stdout ? explode("\n", trim($stdout)) : [];

    // Log output for debugging (ohne Passwort)
    $logFile = __DIR__ . '/../storage/sf_reports/fetch_' . sanitizeGuildName($character) . '_' . date('Y-m-d_H-i-s') . '.log';
    $cmdSafe = 'fetch_guild_reports SSO_USERNAME=*** PASSWORD=*** SERVER_HOST=' . $server . ' CHARACTER=' . $character;
    file_put_contents($logFile, "Command: $cmdSafe\n\n" . implode("\n", $output));

    if ($returnCode !== 0) {
        logError('Rust-Script fehlgeschlagen', ['log' => $logFile, 'char' => $character]);
        throw new Exception('Fetch fehlgeschlagen');
    }
    
    // Import files to inbox
    $count = importToInbox($tempDir, $userId, $db);
    
    // Hole Guild-Namen aus der DB (nach dem Import!)
    $stmt = $db->prepare("
        SELECT DISTINCT g.name 
        FROM battle_inbox bi
        JOIN guilds g ON bi.guild_id = g.id
        WHERE bi.character_name = ? AND bi.user_id = ?
        ORDER BY bi.created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$character, $userId]);
    $guildRow = $stmt->fetch(PDO::FETCH_ASSOC);
    $guild = $guildRow ? $guildRow['name'] : '';

    // Cleanup temp directory
    // PHP-eigene Löschfunktion statt exec('rm -rf') - kein Shell-Aufruf nötig
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($tempDir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($files as $file) {
        $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    }
    rmdir($tempDir);
    
    echo json_encode([
        'success' => true,
        'character' => $character,
        'server' => $server,
        'guild' => $guild,
        'count' => $count
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'character' => $character,
        'server' => $server,
        'guild' => $guild,
        'count' => 0,
        'error' => $e->getMessage()
    ]);
    exit(1);
}

/**
 * Import battle report files to inbox
 */
function importToInbox($dir, $userId, $db) {
    $count = 0;
    $storagePath = __DIR__ . '/../storage/sf_reports';
    
    // Scan all guild subdirectories
    foreach (glob("$dir/*/*.txt") as $file) {
        $content = file_get_contents($file);
        
        // Parse Rust format
        $parsed = parseRustBattleReport($content);
        if (!$parsed) {
            continue; // Skip invalid files
        }
        
        // SERVER-OFFSET FIX: F9 ist 24h voraus
        if (stripos($parsed['server'], 'f9.sfgame.net') !== false) {
            // Datum um 1 Tag zurück
            $date = new DateTime($parsed['date']);
            $date->modify('-1 day');
            $parsed['date'] = $date->format('Y-m-d');
        }
        
        $guildName = $parsed['guild_name'];
        $messageId = $parsed['message_id'];
        
        if (!$messageId) {
            continue; // Skip if no message ID
        }
        
        // Find guild ID
        $guildId = findGuildIdByName($db, $guildName);
        if (!$guildId) {
            continue; // Guild not found in system
        }
        
        // Move file to permanent storage
        $guildDir = $storagePath . '/' . sanitizeGuildName($guildName);
        if (!is_dir($guildDir)) {
            mkdir($guildDir, 0775, true);
        }
        
        $newPath = $guildDir . '/' . basename($file);
        rename($file, $newPath);
        
        // Insert into inbox (ignore if duplicate)
        try {
            $stmt = $db->prepare("
                INSERT INTO battle_inbox 
                (user_id, guild_id, message_id, opponent_guild, battle_type,
                 battle_date, battle_time, server, character_name, file_path)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $userId,
                $guildId,
                $messageId,
                $parsed['opponent'],
                $parsed['type'],
                $parsed['date'],
                $parsed['time'],
                $parsed['server'],
                $parsed['character'],
                $newPath
            ]);
            
            $count++;
        } catch (PDOException $e) {
            // Duplicate message_id - skip
            if ($e->getCode() == 23000) {
                continue;
            }
            throw $e;
        }
    }
    
    return $count;
}
