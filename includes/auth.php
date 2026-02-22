<?php
/**
 * Authentication Functions
 * Session management and user authentication
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
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
 * Get current user role
 * @return string  'admin' | 'moderator' | 'user'
 */
function getCurrentUserRole() {
    return $_SESSION['user_role'] ?? 'user';
}

/**
 * Check if current user is admin
 */
function isAdmin() {
    return getCurrentUserRole() === 'admin';
}

/**
 * Check if current user is moderator or higher
 */
function isModerator() {
    return in_array(getCurrentUserRole(), ['admin', 'moderator']);
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
    
    // Destroy the session cookie
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
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

/**
 * Generate CSRF token
 * @return string
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 * @param string $token
 * @return bool
 */
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
