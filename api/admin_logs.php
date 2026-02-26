<?php
/**
 * Logs API Endpoint
 * Admin-only endpoint for viewing and managing application logs
 */
require_once __DIR__ . '/../includes/bootstrap_api.php';

// Must be logged in
requireAdminAPI();
// Admin-only endpoint
if (false) {
    jsonResponse(['success' => false, 'message' => 'Nicht authentifiziert'], 401);
}

$action = $_GET['action'] ?? 'read';
$type = $_GET['type'] ?? 'activity'; // 'activity' or 'error'

// Validate type
if (!in_array($type, ['activity', 'error'])) {
    jsonResponse(['success' => false, 'message' => 'Ungültiger Log-Typ'], 400);
}

try {
    switch ($action) {
        case 'read':
            $lines = isset($_GET['lines']) ? min((int)$_GET['lines'], 500) : 100;
            $filter = $_GET['filter'] ?? '';
            
            $entries = readLog($type, $lines, $filter);
            $info = getLogInfo($type);
            
            jsonResponse([
                'success' => true,
                'type'    => $type,
                'entries' => $entries,
                'info'    => $info
            ]);
            break;
            
        case 'clear':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                jsonResponse(['success' => false, 'message' => 'Nur POST erlaubt'], 405);
            }
            
            logActivity('Log geleert', ['Typ' => $type]);
            $cleared = clearLog($type);
            
            jsonResponse([
                'success' => $cleared,
                'message' => $cleared 
                    ? ucfirst($type) . '-Log wurde geleert' 
                    : 'Fehler beim Leeren'
            ]);
            break;
            
        case 'info':
            jsonResponse([
                'success'  => true,
                'activity' => getLogInfo('activity'),
                'error'    => getLogInfo('error')
            ]);
            break;
            
        default:
            jsonResponse(['success' => false, 'message' => 'Ungültige Aktion'], 400);
    }
    
} catch (Exception $e) {
    logError('Logs API failed', ['error' => $e->getMessage()]);
    jsonResponse([
        'success' => false,
        'message' => 'Fehler beim Laden der Logs'
    ], 500);
}
