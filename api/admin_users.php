<?php
/**
 * User Management API
 * Admin-only endpoint for user CRUD operations
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/logger.php';

// Must be logged in
if (!isLoggedIn()) {
    jsonResponse(['success' => false, 'message' => 'Nicht authentifiziert'], 401);
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            // List all users
            $users = query('SELECT id, username, created_at FROM users ORDER BY username');
            jsonResponse([
                'success' => true,
                'users' => $users
            ]);
            break;
            
        case 'POST':
            // Create new user
            $input = json_decode(file_get_contents('php://input'), true);
            $username = trim($input['username'] ?? '');
            $password = trim($input['password'] ?? '');
            
            if (empty($username) || empty($password)) {
                jsonResponse(['success' => false, 'message' => 'Benutzername und Passwort erforderlich'], 400);
            }
            
            // Check if username exists
            $existing = queryOne('SELECT id FROM users WHERE username = ?', [$username]);
            if ($existing) {
                jsonResponse(['success' => false, 'message' => 'Benutzername bereits vergeben'], 400);
            }
            
            // Hash password
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert user
            execute(
                'INSERT INTO users (username, password_hash, created_at) VALUES (?, ?, datetime("now"))',
                [$username, $passwordHash]
            );
            
            logActivity('Benutzer erstellt', ['Benutzername' => $username]);
            
            jsonResponse([
                'success' => true,
                'message' => 'Benutzer erfolgreich angelegt'
            ]);
            break;
            
        case 'PUT':
            // Update user (password change)
            $input = json_decode(file_get_contents('php://input'), true);
            $userId = $input['user_id'] ?? null;
            $newPassword = trim($input['new_password'] ?? '');
            
            if (!$userId || empty($newPassword)) {
                jsonResponse(['success' => false, 'message' => 'User ID und neues Passwort erforderlich'], 400);
            }
            
            // Hash new password
            $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
            
            // Update user
            execute(
                'UPDATE users SET password_hash = ? WHERE id = ?',
                [$passwordHash, $userId]
            );
            
            logActivity('Passwort geändert', ['Ziel-User-ID' => $userId]);
            
            jsonResponse([
                'success' => true,
                'message' => 'Passwort erfolgreich geändert'
            ]);
            break;
            
        case 'DELETE':
            // Delete user
            $input = json_decode(file_get_contents('php://input'), true);
            $userId = $input['user_id'] ?? null;
            
            if (!$userId) {
                jsonResponse(['success' => false, 'message' => 'User ID erforderlich'], 400);
            }
            
            // Don't allow deleting yourself
            if ($userId == $_SESSION['user_id']) {
                jsonResponse(['success' => false, 'message' => 'Du kannst dich nicht selbst löschen'], 400);
            }
            
            // Delete user
            $targetUser = queryOne('SELECT username FROM users WHERE id = ?', [$userId]);
            execute('DELETE FROM users WHERE id = ?', [$userId]);
            
            logActivity('Benutzer gelöscht', ['Ziel-User' => $targetUser['username'] ?? '?', 'Ziel-ID' => $userId]);
            
            jsonResponse([
                'success' => true,
                'message' => 'Benutzer erfolgreich gelöscht'
            ]);
            break;
            
        default:
            jsonResponse(['success' => false, 'message' => 'Methode nicht erlaubt'], 405);
    }
    
} catch (Exception $e) {
    logError('User management failed', ['error' => $e->getMessage()]);
    jsonResponse([
        'success' => false,
        'message' => 'Fehler bei der Verarbeitung'
    ], 500);
}
