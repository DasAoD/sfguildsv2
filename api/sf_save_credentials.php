<?php
/**
 * API: Save S&F Credentials
 * Saves encrypted S&F username and password
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/encryption.php';

header('Content-Type: application/json');

checkAuth();

$db = getDB();
$userId = $_SESSION['user_id'];

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);
$username = $input['username'] ?? '';
$password = $input['password'] ?? '';

if (empty($username) || empty($password)) {
    http_response_code(400);
    echo json_encode(['error' => 'Benutzername und Passwort erforderlich']);
    exit;
}

try {
    // Encrypt password
    $encryptedPassword = encryptData($password);
    
    // Save credentials
    $stmt = $db->prepare("UPDATE users SET sf_username = ?, sf_password_encrypted = ?, sf_iv = ?, sf_hmac = ? WHERE id = ?");
    $stmt->execute([$username, $encryptedPassword['encrypted'], $encryptedPassword['iv'], $encryptedPassword['hmac'], $userId]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Zugangsdaten gespeichert'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
