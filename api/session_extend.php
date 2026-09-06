<?php
/**
 * Session Extend API
 * Verlängert die Session bei "echter Aktivität" auf einer JS-lastigen Seite
 * (Klick / Tastendruck), die sonst keinen Server-Request auslösen würde.
 *
 * Das eigentliche Verlängern erledigt bereits auth.php beim Include
 * (last_activity = now, da hier KEIN SESSION_SKIP_ACTIVITY_TOUCH gesetzt ist).
 * Der Endpoint bestätigt nur und gibt die neue Restzeit zurück.
 */
require_once __DIR__ . '/../includes/bootstrap_api.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

if (!isLoggedIn()) {
    echo json_encode(['authenticated' => false], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode([
    'authenticated' => true,
    'remaining'     => SESSION_IDLE_TIMEOUT,
    'timeout'       => SESSION_IDLE_TIMEOUT,
    'warning'       => SESSION_IDLE_WARNING,
], JSON_UNESCAPED_UNICODE);
