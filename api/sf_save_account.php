<?php
/**
 * API: Save S&F Account Credentials
 * Encrypts and stores SF username/password for user
 */

session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/sf_helpers.php';

header('Content-Type: application/json');

// Check authentication
checkAuth();

$db = getDB();
$userId = $_SESSION['user_id'];

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);
$sfUsername = trim($input['sf_username'] ?? '');
$sfPassword = $input['sf_password'] ?? '';

// Validate
if (empty($sfUsername)) {
    http_response_code(400);
    echo json_encode(['error' => 'S&F Username ist erforderlich']);
    exit;
}

try {
    // If password is provided, encrypt it
    if (!empty($sfPassword)) {
        $encrypted = encryptData($sfPassword);
        
        $stmt = $db->prepare("
            UPDATE users 
            SET sf_username = ?,
                sf_password_encrypted = ?,
                sf_iv = ?,
                sf_hmac = ?,
                sf_updated_at = datetime('now')
            WHERE id = ?
        ");
        
        $stmt->execute([
            $sfUsername,
            $encrypted['encrypted'],
            $encrypted['iv'],
            $encrypted['hmac'],
            $userId
        ]);
    } else {
        // Only update username (password remains unchanged)
        $stmt = $db->prepare("
            UPDATE users 
            SET sf_username = ?,
                sf_updated_at = datetime('now')
            WHERE id = ?
        ");
        
        $stmt->execute([$sfUsername, $userId]);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'S&F Account erfolgreich verknüpft'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    logError('sf_save_account failed', ['error' => $e->getMessage()]);
    echo json_encode(['error' => 'Interner Fehler']);
}
