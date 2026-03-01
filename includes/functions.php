<?php
/**
 * Helper Functions
 * Utility functions used across the application
 */

/**
 * Sanitize output for HTML
 * @param string $string
 * @return string
 */
function e($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Redirect to a URL
 * @param string $url
 */
function redirect($url) {
    header('Location: ' . $url);
    exit;
}

/**
 * Send JSON response
 * @param mixed $data
 * @param int $statusCode
 */
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Send a JSON error response
 */
function jsonError(string $message, int $statusCode = 400): never {
    jsonResponse(['success' => false, 'error' => $message], $statusCode);
}

/**
 * Get POST data
 * @param string $key
 * @param mixed $default
 * @return mixed
 */
function post($key, $default = null) {
    return $_POST[$key] ?? $default;
}

/**
 * Get GET data
 * @param string $key
 * @param mixed $default
 * @return mixed
 */
function get($key, $default = null) {
    return $_GET[$key] ?? $default;
}

/**
 * Format date in German format
 * @param string $date
 * @return string
 */
function formatDate($date) {
    if (!$date) return '—';
    $timestamp = strtotime($date);
    return date('d.m.Y', $timestamp);
}

/**
 * Format datetime in German format
 * @param string $datetime
 * @return string
 */
function formatDateTime($datetime) {
    if (!$datetime) return '—';
    $timestamp = strtotime($datetime);
    return date('d.m.Y H:i:s', $timestamp);
}

/**
 * Calculate days offline
 * @param string $lastOnline
 * @return int
 */
function calculateDaysOffline($lastOnline) {
    if (!$lastOnline) return 0;
    $lastTime = strtotime($lastOnline);
    $now = time();
    $diff = $now - $lastTime;
    return floor($diff / (60 * 60 * 24));
}