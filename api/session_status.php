<?php
/**
 * Session Status API
 * Liefert die verbleibende Zeit bis zum Inaktivitäts-Logout.
 *
 * WICHTIG: Dieser Endpoint verlängert die Session NICHT – er ist der reine
 * Poll für die Restzeit-Anzeige im Frontend. Deshalb wird vor dem Bootstrap
 * SESSION_SKIP_ACTIVITY_TOUCH gesetzt, damit auth.php last_activity in Ruhe lässt.
 */
define('SESSION_SKIP_ACTIVITY_TOUCH', true);

require_once __DIR__ . '/../includes/bootstrap_api.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

if (!isLoggedIn()) {
    echo json_encode(['authenticated' => false], JSON_UNESCAPED_UNICODE);
    exit;
}

$now       = time();
$last      = $_SESSION['last_activity'] ?? $now;
$remaining = max(0, SESSION_IDLE_TIMEOUT - ($now - $last));

echo json_encode([
    'authenticated' => true,
    'remaining'     => $remaining,
    'timeout'       => SESSION_IDLE_TIMEOUT,
    'warning'       => SESSION_IDLE_WARNING,
], JSON_UNESCAPED_UNICODE);
