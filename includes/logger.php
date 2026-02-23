<?php
/**
 * Application Logger
 * Centralized logging with separate activity and error logs
 * 
 * Usage:
 *   logActivity('Mitglied gelöscht', ['Name' => 'Zogger900', 'Guild-ID' => 3]);
 *   logError('Database query failed', ['sql' => '...', 'error' => $e->getMessage()]);
 */

// Log directory
define('LOG_PATH', dirname(__DIR__) . '/data/logs');

// Max log file size before rotation (5 MB)
define('LOG_MAX_SIZE', 5 * 1024 * 1024);

// How many rotated files to keep
define('LOG_MAX_FILES', 5);

/**
 * Log an activity (user actions, audit trail)
 * 
 * @param string $action  Short description (e.g. "member_deleted", "import_csv")
 * @param array  $context Additional data (will be JSON-encoded)
 */
function logActivity(string $action, array $context = []): void {
    // Auto-add user info if available
    if (!isset($context['User']) && function_exists('getCurrentUsername')) {
        $username = getCurrentUsername();
        if ($username) {
            $context['User'] = $username;
        }
    }
    
    writeLog('activity', $action, $context);
}

/**
 * Log an error (exceptions, failures)
 * 
 * @param string $message Error description
 * @param array  $context Additional data (exception info, stack trace, etc.)
 */
function logError(string $message, array $context = []): void {
    writeLog('error', $message, $context);
}

/**
 * Read log entries (for admin panel display)
 * 
 * @param string $type    'activity' or 'error'
 * @param int    $lines   Number of lines to return (newest first)
 * @param string $filter  Optional text filter
 * @return array Array of parsed log entries
 */
function readLog(string $type, int $lines = 100, string $filter = ''): array {
    $file = LOG_PATH . "/{$type}.log";
    
    if (!file_exists($file)) {
        return [];
    }
    
    // Read file and get last N lines
    $allLines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!$allLines) {
        return [];
    }
    
    // Reverse for newest first
    $allLines = array_reverse($allLines);
    
    $entries = [];
    $count = 0;
    
    foreach ($allLines as $line) {
        if ($count >= $lines) break;
        
        // Apply filter
        if ($filter && stripos($line, $filter) === false) {
            continue;
        }
        
        // Parse log line: [2026-02-07 00:08:21] ACTION | {"context":"data"}
        if (preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] (.+?)(?:\s*\|\s*(.+))?$/', $line, $matches)) {
            $entry = [
                'timestamp' => $matches[1],
                'message'   => $matches[2],
                'context'   => isset($matches[3]) ? json_decode($matches[3], true) : null
            ];
            $entries[] = $entry;
            $count++;
        }
    }
    
    return $entries;
}

/**
 * Get log file info (for admin panel)
 * 
 * @param string $type 'activity' or 'error'
 * @return array File stats
 */
function getLogInfo(string $type): array {
    $file = LOG_PATH . "/{$type}.log";
    
    if (!file_exists($file)) {
        return ['exists' => false, 'size' => 0, 'lines' => 0, 'modified' => null];
    }
    
    $size = filesize($file);
    $lines = count(file($file, FILE_SKIP_EMPTY_LINES));
    $modified = date('Y-m-d H:i:s', filemtime($file));
    
    return [
        'exists'   => true,
        'size'     => $size,
        'size_human' => formatBytes($size),
        'lines'    => $lines,
        'modified' => $modified
    ];
}

/**
 * Clear a log file
 * 
 * @param string $type 'activity' or 'error'
 */
function clearLog(string $type): bool {
    $file = LOG_PATH . "/{$type}.log";
    
    if (!file_exists($file)) {
        return true;
    }
    
    return file_put_contents($file, '') !== false;
}

// ─── Internal Functions ────────────────────────────────────────

/**
 * Strip control characters to prevent log-forging via newlines/special chars
 */
function stripLogControlChars(string $s): string {
    return preg_replace('/[\x00-\x1F\x7F]/u', '', $s);
}

/**
 * Write a log entry
 */
function writeLog(string $type, string $message, array $context): void {
    ensureLogDir();
    
    $file = LOG_PATH . "/{$type}.log";
    
    // Rotate if too large
    rotateLog($file);
    
    // Steuerzeichen aus Message und Context entfernen (verhindert Log-Forging)
    $message = stripLogControlChars($message);
    array_walk_recursive($context, function (&$val) {
        if (is_string($val)) {
            $val = stripLogControlChars($val);
        }
    });

    // Build log line (CET/CEST)
    $timestamp = (new DateTime('now', new DateTimeZone('Europe/Berlin')))->format('Y-m-d H:i:s');
    $line = "[{$timestamp}] {$message}";
    
    if (!empty($context)) {
        $line .= ' | ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    
    $line .= "\n";
    
    // Append to file (with lock to prevent race conditions)
    file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
}

/**
 * Ensure log directory exists
 */
function ensureLogDir(): void {
    if (!is_dir(LOG_PATH)) {
        mkdir(LOG_PATH, 0750, true);
    }
}

/**
 * Rotate log file if it exceeds max size
 */
function rotateLog(string $file): void {
    if (!file_exists($file) || filesize($file) < LOG_MAX_SIZE) {
        return;
    }
    
    // Shift existing rotated files: .4 → .5, .3 → .4, etc.
    for ($i = LOG_MAX_FILES; $i >= 1; $i--) {
        $old = "{$file}.{$i}";
        $new = "{$file}." . ($i + 1);
        
        if ($i === LOG_MAX_FILES && file_exists($old)) {
            unlink($old); // Delete oldest
        } elseif (file_exists($old)) {
            rename($old, $new);
        }
    }
    
    // Current → .1
    rename($file, "{$file}.1");
}

/**
 * Format bytes to human-readable
 */
function formatBytes(int $bytes): string {
    if ($bytes === 0) return '0 B';
    
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = floor(log($bytes, 1024));
    
    return round($bytes / pow(1024, $i), 1) . ' ' . $units[$i];
}