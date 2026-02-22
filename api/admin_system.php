<?php
/**
 * System Info & Backup API
 * Admin-only endpoint for system information and database backup
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/logger.php';

// Must be logged in
requireAdminAPI();
// Admin-only endpoint
if (false) {
    jsonResponse(['success' => false, 'message' => 'Nicht authentifiziert'], 401);
}

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'info':
            // Get system information
            $dbPath = __DIR__ . '/../data/sfguilds.sqlite';
            $dbSize = file_exists($dbPath) ? filesize($dbPath) : 0;
            
            // Count records
            $userCount = queryOne('SELECT COUNT(*) as count FROM users')['count'];
            $guildCount = queryOne('SELECT COUNT(*) as count FROM guilds')['count'];
            $memberCount = queryOne('SELECT COUNT(*) as count FROM members')['count'];
            $battleCount = queryOne('SELECT COUNT(*) as count FROM sf_eval_battles')['count'];
            
            $diskFree = @disk_free_space(__DIR__ . '/..');
            $diskFreeFormatted = $diskFree !== false 
                ? round($diskFree / 1024 / 1024 / 1024, 2) . ' GB' 
                : 'N/A';
            
            jsonResponse([
                'success' => true,
                'info' => [
                    'php_version' => phpversion(),
                    'sqlite_version' => SQLite3::version()['versionString'],
                    'db_size' => round($dbSize / 1024 / 1024, 2) . ' MB',
                    'disk_free' => $diskFreeFormatted,
                    'users' => $userCount,
                    'guilds' => $guildCount,
                    'members' => $memberCount,
                    'battles' => $battleCount
                ]
            ]);
            break;
            
        case 'backup':
            // Create database backup
            $dbPath = __DIR__ . '/../data/sfguilds.sqlite';

            if (!file_exists($dbPath)) {
                jsonResponse(['success' => false, 'message' => 'Datenbank nicht gefunden'], 404);
            }

            // Generate filename with timestamp
            $timestamp = date('Y-m-d_H-i-s');
            $filename  = "sfguilds_backup_{$timestamp}.sqlite";
            $tmpPath   = sys_get_temp_dir() . '/' . $filename;

            try {
                // VACUUM INTO erzeugt ein konsistentes Backup das alle WAL-Änderungen einschließt.
                // readfile() auf die Live-DB würde in WAL-Mode ggf. einen inkonsistenten Zustand liefern,
                // da aktuelle Änderungen noch in der -wal Datei stecken können.
                $db = getDB();
                $db->exec("VACUUM INTO " . $db->quote($tmpPath));
            } catch (Exception $e) {
                logError('Backup VACUUM INTO failed', ['error' => $e->getMessage()]);
                jsonResponse(['success' => false, 'message' => 'Backup fehlgeschlagen'], 500);
            }

            logActivity('Backup heruntergeladen', ['Größe' => filesize($tmpPath)]);
            header('Content-Type: application/x-sqlite3');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . filesize($tmpPath));

            readfile($tmpPath);
            @unlink($tmpPath);
            exit;
            
        default:
            jsonResponse(['success' => false, 'message' => 'Ungültige Aktion'], 400);
    }
    
} catch (Exception $e) {
    logError('System API failed', ['error' => $e->getMessage()]);
    jsonResponse([
        'success' => false,
        'message' => 'Fehler bei der Verarbeitung'
    ], 500);
}
