<?php
/**
 * Analytics Report API
 * Admin-only endpoint that serves the static GoAccess HTML report
 * (generated periodically via cron on the server from the nginx access
 * log, see install/ANALYTICS_INSTALL.md). The report file lives outside
 * public/, so this endpoint is the only way to reach it - same
 * admin-gated pattern as the database backup in admin_system.php.
 */
require_once __DIR__ . '/../includes/bootstrap_api.php';

requireAdminAPI();

$reportPath = DATA_PATH . '/analytics/report.html';

if (!file_exists($reportPath)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Noch kein Report vorhanden - der Cronjob erzeugt ihn alle 30 Minuten.';
    exit;
}

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
readfile($reportPath);
