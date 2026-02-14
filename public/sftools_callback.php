<?php
/**
 * DEBUG VERSION - Logs everything SFTools sends
 * Temporarily replace sftools_callback.php with this to see what's being sent
 */

header('Access-Control-Allow-Origin: https://sftools.mar21.eu');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$baseDir = dirname(__DIR__);
$logFile = $baseDir . '/storage/import/sftools_callback_DEBUG.log';

function logDebug($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

logDebug("========================================");
logDebug("FULL DEBUG DUMP");
logDebug("========================================");
logDebug("Method: " . $_SERVER['REQUEST_METHOD']);
logDebug("Content-Type: " . ($_SERVER['CONTENT_TYPE'] ?? 'not set'));
logDebug("");
logDebug("--- \$_POST ---");
logDebug(print_r($_POST, true));
logDebug("");
logDebug("--- \$_GET ---");
logDebug(print_r($_GET, true));
logDebug("");
logDebug("--- \$_FILES ---");
logDebug(print_r($_FILES, true));
logDebug("");
logDebug("--- php://input ---");
$rawInput = file_get_contents('php://input');
logDebug("Length: " . strlen($rawInput));
logDebug("Content: " . $rawInput);
logDebug("");
logDebug("--- \$_SERVER ---");
logDebug(print_r($_SERVER, true));
logDebug("========================================");

http_response_code(200);
echo json_encode(['debug' => 'Data logged to sftools_callback_DEBUG.log']);
