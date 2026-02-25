<?php
/**
 * Authentication Functions
 * Session management and user authentication
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    // Session strict mode: ungültige Session-IDs werden abgelehnt statt akzeptiert
    ini_set('session.use_strict_mode', '1');
    // Nur Cookie-basierte Sessions erlauben (keine Session-ID in der URL)
    ini_set('session.use_only_cookies', '1');
    // Sichere Cookie-Parameter setzen bevor session_start() aufgerufen wird
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => true,   // Nur über HTTPS
        'httponly' => true,   // Kein JS-Zugriff auf Session-Cookie
        'samesite' => 'Strict',
    ]);
    session_start();
}

/**
 * Check if user is authenticated
 * Redirects to login if not authenticated
 */


function checkAuth() {
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        header('Location: /errors/401.html');
        exit;
    }
    
}

/**
 * Check authentication for API endpoints
 * Returns JSON error instead of redirect
 */
function checkAuthAPI() {
    if (!isLoggedIn()) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Nicht angemeldet', 'error' => 'unauthorized']);
        exit;
    }
}

/**
 * Check if user is logged in
 * @return bool
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['username']);
}

/**
 * Get current user ID
 * @return int|null
 */
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get current username
 * @return string|null
 */
function getCurrentUsername() {
    return $_SESSION['username'] ?? null;
}

/**
 * Get current user role from session (UI/display use only)
 * @return string  'admin' | 'moderator' | 'user'
 */
function getCurrentUserRole() {
    return $_SESSION['user_role'] ?? 'user';
}

/**
 * Get current user role fresh from DB and sync session.
 * Used for privilege checks so role changes take effect immediately
 * without requiring a re-login.
 * @return string  'admin' | 'moderator' | 'user'
 */
function getCurrentUserRoleFresh() {
    if (!isset($_SESSION['user_id'])) {
        return 'user';
    }
    // Pro Request einmal aus der DB holen und danach cachen
    static $cachedRole = null;
    if ($cachedRole !== null) {
        return $cachedRole;
    }
    require_once __DIR__ . '/../config/database.php';
    $user = queryOne('SELECT role FROM users WHERE id = ?', [$_SESSION['user_id']]);
    $role = $user['role'] ?? 'user';
    // Session aktualisieren damit UI konsistent bleibt
    $_SESSION['user_role'] = $role;
    $cachedRole = $role;
    return $role;
}

/**
 * Check if current user is admin (DB-fresh check)
 */
function isAdmin() {
    return getCurrentUserRoleFresh() === 'admin';
}

/**
 * Check if current user is moderator or higher (DB-fresh check)
 */
function isModerator() {
    return in_array(getCurrentUserRoleFresh(), ['admin', 'moderator']);
}

/**
 * Require admin role for page access – redirects on failure
 */
function requireAdmin() {
    checkAuth();
    if (!isAdmin()) {
        http_response_code(403);
        exit;
    }
}

/**
 * Require admin role for API endpoints – returns JSON on failure
 */
function requireAdminAPI() {
    checkAuthAPI();
    if (!isAdmin()) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Keine Berechtigung', 'error' => 'forbidden']);
        exit;
    }
}

/**
 * Require moderator role or higher for API endpoints
 */
function requireModeratorAPI() {
    checkAuthAPI();
    if (!isModerator()) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Keine Berechtigung', 'error' => 'forbidden']);
        exit;
    }
}

/**
 * Login user
 * @param int $userId
 * @param string $username
 */
function login($userId, $username) {
    $_SESSION['user_id'] = $userId;
    $_SESSION['username'] = $username;
    $_SESSION['login_time'] = time();

    // Rolle aus DB laden und in Session speichern
    require_once __DIR__ . '/../config/database.php';
    $user = queryOne('SELECT role FROM users WHERE id = ?', [$userId]);
    $_SESSION['user_role'] = $user['role'] ?? 'user';

    // Regenerate session ID for security
    session_regenerate_id(true);
}

/**
 * Logout user
 */
function logout() {
    // Unset all session variables
    $_SESSION = array();

    // Destroy the session cookie with the same parameters it was created with
    if (isset($_COOKIE[session_name()])) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(), '',
            time() - 3600,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    // Destroy the session
    session_destroy();
}

/**
 * Verify password against database
 * @param string $username
 * @param string $password
 * @return array|false User data or false
 */
function verifyUser($username, $password) {
    require_once __DIR__ . '/../config/database.php';
    
    $user = queryOne(
        'SELECT id, username, password_hash FROM users WHERE username = ?',
        [$username]
    );
    
    if ($user && password_verify($password, $user['password_hash'])) {
        // Hash automatisch aktualisieren wenn PHP einen neueren Algorithmus empfiehlt
        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            execute('UPDATE users SET password_hash = ? WHERE id = ?', [$newHash, $user['id']]);
        }
        return $user;
    }
    
    return false;
}

/**
 * Hash password
 * @param string $password
 * @return string
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}