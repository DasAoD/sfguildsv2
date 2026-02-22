<?php
/**
 * Change Password API
 * Allows any logged-in user to change their own password
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/logger.php';

checkAuthAPI();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Methode nicht erlaubt'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$currentPassword = $input['current_password'] ?? '';
$newPassword     = $input['new_password'] ?? '';

if (empty($currentPassword) || empty($newPassword)) {
    jsonResponse(['success' => false, 'message' => 'Aktuelles und neues Passwort erforderlich'], 400);
}

if (strlen($newPassword) < 8) {
    jsonResponse(['success' => false, 'message' => 'Neues Passwort muss mindestens 8 Zeichen lang sein'], 400);
}

$userId = getCurrentUserId();
$user = queryOne('SELECT password_hash FROM users WHERE id = ?', [$userId]);

if (!$user || !password_verify($currentPassword, $user['password_hash'])) {
    jsonResponse(['success' => false, 'message' => 'Aktuelles Passwort ist falsch'], 401);
}

execute(
    'UPDATE users SET password_hash = ? WHERE id = ?',
    [password_hash($newPassword, PASSWORD_DEFAULT), $userId]
);

logActivity('Passwort geändert (selbst)', ['User-ID' => $userId]);

jsonResponse(['success' => true, 'message' => 'Passwort erfolgreich geändert']);
