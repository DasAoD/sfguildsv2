<?php
/**
 * User Management API
 * Admin-only endpoint for user CRUD operations
 */
require_once __DIR__ . '/../includes/bootstrap_api.php';

// Must be logged in
requireAdminAPI();
// Admin-only endpoint
if (false) {
    jsonResponse(['success' => false, 'message' => 'Nicht authentifiziert'], 401);
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            // List all users
            $users = query('SELECT id, username, role, created_at FROM users ORDER BY username');
            jsonResponse([
                'success' => true,
                'users' => $users
            ]);
            break;
            
        case 'POST':
            // Create new user
            try {
                $input = json_decode(file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $e) {
                jsonError('Ungültige JSON-Daten', 400);
            }
            $username = trim($input['username'] ?? '');
            $password = trim($input['password'] ?? '');
            $role = $input['role'] ?? 'user';
            
            if (!in_array($role, ['admin', 'moderator', 'user'])) {
                $role = 'user';
            }
            
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
            insert(
                'INSERT INTO users (username, password_hash, role, created_at) VALUES (?, ?, ?, datetime("now"))',
                [$username, $passwordHash, $role]
            );
            
            logActivity('Benutzer erstellt', ['Benutzername' => $username, 'Rolle' => $role]);
            
            jsonResponse([
                'success' => true,
                'message' => 'Benutzer erfolgreich angelegt'
            ]);
            break;
            
        case 'PUT':
            try {
                $input = json_decode(file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $e) {
                jsonError('Ungültige JSON-Daten', 400);
            }
            $userId = $input['user_id'] ?? null;

            if (!$userId) {
                jsonResponse(['success' => false, 'message' => 'User ID erforderlich'], 400);
            }

            // Passwort-Reset
            if (!empty($input['new_password']) || !empty($input['reset_password'])) {
                if (!empty($input['reset_password'])) {
                    // Admin-initiierter Reset: zufälliges Einmal-Passwort
                    $newPassword = bin2hex(random_bytes(8));
                    $mustChange = 1;
                } else {
                    // Manuelles neues Passwort
                    $newPassword = trim($input['new_password']);
                    $mustChange = 0;
                }
                if (strlen($newPassword) < 8) {
                    jsonError('Passwort muss mindestens 8 Zeichen lang sein', 400);
                }
                $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
                execute(
                    'UPDATE users SET password_hash = ?, must_change_password = ?, updated_at = datetime(\'now\') WHERE id = ?',
                    [$passwordHash, $mustChange, $userId]
                );
                $targetUser = queryOne('SELECT username FROM users WHERE id = ?', [$userId]);
                logActivity('Passwort zurückgesetzt', ['Ziel-User' => $targetUser['username'] ?? '?', 'Einmal-Reset' => $mustChange]);
                $response = ['success' => true, 'message' => 'Passwort erfolgreich zurückgesetzt'];
                if ($mustChange) {
                    $response['temp_password'] = $newPassword;
                }
                jsonResponse($response);
            }

            // Rollen-Änderung
            if (isset($input['role'])) {
                $newRole = $input['role'];
                if (!in_array($newRole, ['admin', 'moderator', 'user'])) {
                    jsonResponse(['success' => false, 'message' => 'Ungültige Rolle'], 400);
                }
                // Letzten Admin schützen
                if ($newRole !== 'admin') {
                    $adminCount = queryOne("SELECT COUNT(*) as cnt FROM users WHERE role = 'admin'");
                    $targetUser = queryOne("SELECT role FROM users WHERE id = ?", [$userId]);
                    if ($targetUser['role'] === 'admin' && $adminCount['cnt'] <= 1) {
                        jsonResponse(['success' => false, 'message' => 'Letzten Admin kann nicht degradiert werden'], 400);
                    }
                }
                execute('UPDATE users SET role = ? WHERE id = ?', [$newRole, $userId]);
                $targetUser = queryOne('SELECT username FROM users WHERE id = ?', [$userId]);
                logActivity('Rolle geändert', ['Ziel-User' => $targetUser['username'] ?? '?', 'Neue Rolle' => $newRole]);
                jsonResponse(['success' => true, 'message' => 'Rolle erfolgreich geändert']);
            }

            jsonResponse(['success' => false, 'message' => 'Keine Änderung angegeben'], 400);
            break;
            
        case 'DELETE':
            // Delete user
            try {
                $input = json_decode(file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $e) {
                jsonError('Ungültige JSON-Daten', 400);
            }
            $userId = $input['user_id'] ?? null;
            
            if (!$userId) {
                jsonResponse(['success' => false, 'message' => 'User ID erforderlich'], 400);
            }
            
            // Don't allow deleting yourself
            if ($userId == $_SESSION['user_id']) {
                jsonResponse(['success' => false, 'message' => 'Du kannst dich nicht selbst löschen'], 400);
            }

            // Don't allow deleting the last admin
            $targetUser = queryOne('SELECT username, role FROM users WHERE id = ?', [$userId]);
            if ($targetUser && $targetUser['role'] === 'admin') {
                $adminCount = queryOne("SELECT COUNT(*) as cnt FROM users WHERE role = 'admin'");
                if ($adminCount['cnt'] <= 1) {
                    jsonResponse(['success' => false, 'message' => 'Der letzte Admin kann nicht gelöscht werden'], 400);
                }
            }

            // Delete user
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
    
} catch (Throwable $e) {
    logError('User management failed', ['error' => $e->getMessage()]);
    jsonResponse([
        'success' => false,
        'message' => 'Fehler bei der Verarbeitung'
    ], 500);
}
