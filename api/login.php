<?php
/**
 * Login API Endpoint
 * Handles user authentication
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/logger.php';

header('Content-Type: application/json');

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

// Get input data
$input = json_decode(file_get_contents('php://input'), true);
$username = $input['username'] ?? '';
$password = $input['password'] ?? '';
$returnUrl = $input['return'] ?? '/';

// Validate input
if (empty($username) || empty($password)) {
    jsonResponse(['success' => false, 'message' => 'Benutzername und Passwort sind erforderlich'], 400);
}

// Verify user credentials
$user = verifyUser($username, $password);

if ($user) {
    // Login successful
    login($user['id'], $user['username']);
    logActivity('Login erfolgreich', ['User' => $user['username']]);
    jsonResponse([
        'success' => true,
        'message' => 'Login erfolgreich',
        'redirect' => $returnUrl
    ]);
} else {
    // Login failed
    logActivity('Login fehlgeschlagen', ['Benutzername' => $username]);
    jsonResponse([
        'success' => false,
        'message' => 'Ungültige Anmeldedaten'
    ], 401);
}
