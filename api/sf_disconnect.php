<?php
/**
 * API: Disconnect S&F Account
 * Removes S&F credentials and selected characters
 */
require_once __DIR__ . '/../includes/bootstrap_api.php';
ini_set('log_errors', 1);

header('Content-Type: application/json');

checkAuth();

$db = getDB();
$userId = $_SESSION['user_id'];

try {
    // Clear S&F credentials and selected characters
    $stmt = $db->prepare("
        UPDATE users 
        SET sf_username = NULL, 
            sf_password_encrypted = NULL,
            selected_characters = NULL
        WHERE id = ?
    ");
    $stmt->execute([$userId]);
    
    jsonResponse(['success' => true, 'message' => 'Verbindung getrennt']);




    
} catch (Exception $e) {
    logError('sf_disconnect failed', ['error' => $e->getMessage()]);
    jsonError('Interner Fehler', 500);
}