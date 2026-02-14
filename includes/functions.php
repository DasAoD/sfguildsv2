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
    echo json_encode($data);
    exit;
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

/**
 * Upload file
 * @param array $file $_FILES array element
 * @param string $destination Directory path
 * @param array $allowedTypes Allowed MIME types
 * @param int $maxSize Maximum file size in bytes
 * @return string|false Filename on success, false on failure
 */
function uploadFile($file, $destination, $allowedTypes = [], $maxSize = 2097152) {
    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }
    
    // Check file size
    if ($file['size'] > $maxSize) {
        return false;
    }
    
    // Check MIME type
    if (!empty($allowedTypes) && !in_array($file['type'], $allowedTypes)) {
        return false;
    }
    
    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '.' . $extension;
    $filepath = $destination . '/' . $filename;
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return $filename;
    }
    
    return false;
}

/**
 * Delete file
 * @param string $filepath
 * @return bool
 */
function deleteFile($filepath) {
    if (file_exists($filepath)) {
        return unlink($filepath);
    }
    return false;
}
