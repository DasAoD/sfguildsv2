<?php
declare(strict_types=1);

/**
 * SFTools Callback Handler
 * 
 * Empfängt POST-Daten von SFTools request.html nach Endpoint-Import
 * und konvertiert sie in CSV für den automatischen Import via systemd
 */

// Logging für Debug
$logFile = '/var/www/sfguildsv2/storage/import/sftools_callback.log';
$incomingDir = '/var/www/sfguildsv2/storage/import/incoming';

function logDebug(string $msg): void {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[{$timestamp}] {$msg}\n", FILE_APPEND);
}

// CORS Headers für SFTools iframe
header('Access-Control-Allow-Origin: https://sftools.mar21.eu');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Nur POST erlauben
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    logDebug('ERROR: Nur POST erlaubt, erhalten: ' . $_SERVER['REQUEST_METHOD']);
    echo json_encode(['error' => 'Only POST allowed']);
    exit;
}

try {
    // POST-Daten lesen
    $rawInput = file_get_contents('php://input');
    logDebug('Received POST data length: ' . strlen($rawInput));
    
    // Content-Type prüfen
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    logDebug('Content-Type: ' . $contentType);
    
    $data = null;
    
    // JSON im Body?
    if (strpos($contentType, 'application/json') !== false) {
        $data = json_decode($rawInput, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('JSON decode error: ' . json_last_error_msg());
        }
        logDebug('Parsed as JSON, keys: ' . implode(', ', array_keys($data)));
    }
    // Form-encoded?
    elseif (strpos($contentType, 'application/x-www-form-urlencoded') !== false) {
        parse_str($rawInput, $data);
        logDebug('Parsed as form data, keys: ' . implode(', ', array_keys($data)));
    }
    // Fallback: versuche beides
    else {
        // Erst JSON versuchen
        $jsonData = json_decode($rawInput, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $data = $jsonData;
            logDebug('Fallback: Parsed as JSON');
        } else {
            // Dann form data
            parse_str($rawInput, $data);
            logDebug('Fallback: Parsed as form data');
        }
    }
    
    if (!$data) {
        logDebug('ERROR: Keine Daten empfangen oder parsing fehlgeschlagen');
        logDebug('Raw input (first 500 chars): ' . substr($rawInput, 0, 500));
        http_response_code(400);
        echo json_encode(['error' => 'No data received']);
        exit;
    }
    
    // Debug: Alle empfangenen Felder loggen
    logDebug('Received data structure: ' . json_encode(array_keys($data)));
    
    // Guild Info extrahieren
    $guildName = $data['guild']['name'] ?? $data['guildName'] ?? 'Unknown';
    $guildId = $data['guild']['id'] ?? $data['guildId'] ?? '';
    
    // Members extrahieren
    $members = $data['members'] ?? $data['roster'] ?? $data['guild']['members'] ?? [];
    
    if (empty($members)) {
        logDebug('ERROR: Keine Mitglieder in Daten gefunden');
        logDebug('Data keys: ' . implode(', ', array_keys($data)));
        http_response_code(400);
        echo json_encode(['error' => 'No members found in data']);
        exit;
    }
    
    logDebug('Found ' . count($members) . ' members for guild: ' . $guildName);
    
    // CSV erstellen
    $timestamp = date('Ymd_His');
    $guildSlug = strtolower(preg_replace('/[^a-z0-9]+/i', '_', $guildName));
    $guildSlug = trim($guildSlug, '_');
    
    $filename = "{$guildSlug}__{$timestamp}.csv";
    $tempFile = sys_get_temp_dir() . '/' . $filename;
    
    $fp = fopen($tempFile, 'w');
    if (!$fp) {
        throw new RuntimeException('Could not create temp file: ' . $tempFile);
    }
    
    // CSV Header - entsprechend deinem import_sftools.php Script
    $headers = [
        'Name',
        'Rang',
        'Level',
        'zul. Online',
        'Gildenbeitritt',
        'Goldschatz',
        'Lehrmeister',
        'Ritterhalle',
        'Gildenpet',
        'Tage offline',
        'Entlassen',
        'Verlassen',
        'Sonstige Notizen'
    ];
    
    fputcsv($fp, $headers, ';');
    
    // Members schreiben
    foreach ($members as $member) {
        // Felder extrahieren (verschiedene mögliche Key-Namen)
        $row = [
            $member['name'] ?? $member['Name'] ?? '',
            $member['rank'] ?? $member['role'] ?? $member['Rang'] ?? '',
            $member['level'] ?? $member['Level'] ?? '',
            $member['lastActive'] ?? $member['last_active'] ?? $member['zul. Online'] ?? '',
            $member['joinedAt'] ?? $member['joined_at'] ?? $member['Gildenbeitritt'] ?? '',
            $member['treasure'] ?? $member['gold'] ?? $member['Goldschatz'] ?? '',
            $member['instructor'] ?? $member['mentor'] ?? $member['Lehrmeister'] ?? '',
            $member['knights'] ?? $member['knight_hall'] ?? $member['Ritterhalle'] ?? '',
            $member['pet'] ?? $member['guild_pet'] ?? $member['Gildenpet'] ?? '',
            '', // Tage offline (wird berechnet)
            '', // Entlassen (manuell)
            '', // Verlassen (manuell)
            ''  // Notizen (manuell)
        ];
        
        fputcsv($fp, $row, ';');
    }
    
    fclose($fp);
    
    // CSV in incoming-Ordner verschieben
    $targetFile = $incomingDir . '/' . $filename;
    
    if (!is_dir($incomingDir)) {
        mkdir($incomingDir, 0775, true);
    }
    
    if (!rename($tempFile, $targetFile)) {
        throw new RuntimeException('Could not move file to incoming: ' . $targetFile);
    }
    
    // Berechtigungen setzen (für systemd service)
    chmod($targetFile, 0664);
    
    logDebug('SUCCESS: CSV erstellt: ' . $filename . ' (' . count($members) . ' members)');
    
    // Erfolgreiche Antwort
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Import erfolgreich',
        'file' => $filename,
        'members_count' => count($members),
        'guild' => $guildName
    ]);
    
} catch (Throwable $e) {
    logDebug('EXCEPTION: ' . $e->getMessage());
    logDebug('Stack: ' . $e->getTraceAsString());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
