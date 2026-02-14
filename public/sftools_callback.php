<?php
/**
 * SFTools Callback Handler
 * 
 * Receives POST data from SFTools, converts JSON to CSV,
 * and saves to import/incoming directory for processing by systemd service
 */

// CORS Headers - only allow SFTools
header('Access-Control-Allow-Origin: https://sftools.mar21.eu');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Base directory
$baseDir = dirname(__DIR__);

// Logging
$logFile = $baseDir . '/storage/import/sftools_callback.log';
$incomingDir = $baseDir . '/storage/import/incoming';

function logMessage($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

logMessage("=== SFTools Callback Triggered ===");
logMessage("Method: " . $_SERVER['REQUEST_METHOD']);
logMessage("Content-Type: " . ($_SERVER['CONTENT_TYPE'] ?? 'not set'));

// For multipart/form-data, use $_POST instead of php://input
$data = null;

// Check if we have POST data
if (!empty($_POST)) {
    logMessage("Using \$_POST data");
    $data = $_POST;
    logMessage("POST keys: " . implode(', ', array_keys($_POST)));
} else {
    // Fallback to raw input (for JSON)
    $rawInput = file_get_contents('php://input');
    logMessage("Raw input length: " . strlen($rawInput));
    
    if (strlen($rawInput) > 0) {
        $data = json_decode($rawInput, true);
        if ($data === null) {
            parse_str($rawInput, $data);
            logMessage("Parsed as form-encoded data");
        } else {
            logMessage("Parsed as JSON");
        }
    }
}

// Check if we have data
if (empty($data)) {
    logMessage("ERROR: No data received");
    http_response_code(400);
    echo json_encode(['error' => 'No data received']);
    exit;
}

logMessage("Data keys: " . implode(', ', array_keys($data)));

// SFTools might send data in different formats:
// Option 1: Direct members array in POST
// Option 2: JSON string in a POST field
// Option 3: Nested structure

$members = null;

// Try to find members array
if (isset($data['members'])) {
    $members = $data['members'];
    logMessage("Found members in \$data['members']");
} elseif (isset($data['data'])) {
    $members = $data['data'];
    logMessage("Found members in \$data['data']");
} elseif (isset($data['guild']['members'])) {
    $members = $data['guild']['members'];
    logMessage("Found members in \$data['guild']['members']");
} else {
    // Maybe it's a JSON string in one of the POST fields?
    foreach ($data as $key => $value) {
        if (is_string($value) && (substr($value, 0, 1) === '{' || substr($value, 0, 1) === '[')) {
            $decoded = json_decode($value, true);
            if ($decoded !== null && isset($decoded['members'])) {
                $members = $decoded['members'];
                logMessage("Found JSON-encoded members in POST field: $key");
                break;
            } elseif ($decoded !== null && is_array($decoded) && !empty($decoded)) {
                // Maybe the whole thing IS the members array?
                $firstKey = array_key_first($decoded);
                if (is_array($decoded[$firstKey]) && isset($decoded[$firstKey]['name'])) {
                    $members = $decoded;
                    logMessage("Found members array in JSON-encoded POST field: $key");
                    break;
                }
            }
        }
    }
    
    // Still nothing? Log the full data structure for debugging
    if (!$members) {
        logMessage("Data structure: " . json_encode($data, JSON_PRETTY_PRINT));
        http_response_code(400);
        echo json_encode(['error' => 'No members data found', 'received_keys' => array_keys($data)]);
        exit;
    }
}

if (!is_array($members)) {
    logMessage("ERROR: Members is not an array");
    http_response_code(400);
    echo json_encode(['error' => 'Members data is not an array']);
    exit;
}

logMessage("Found " . count($members) . " members");

// Convert to CSV
$csvData = [];

// CSV Header (must match cli/import_sftools.php expectations)
$header = [
    'Name',
    'Rang',
    'Level',
    'zul. Online',
    'Gildenbeitritt',
    'Goldschatz',
    'Lehrmeister',
    'Ritterhalle',
    'Gildenpet',
    'Tage offline',  // Will be empty - calculated by import script
    'Entlassen',     // Will be empty - manual field
    'Verlassen',     // Will be empty - manual field
    'Sonstige Notizen' // Will be empty - manual field
];
$csvData[] = $header;

// Field mapping - SFTools can use different key names
$fieldMap = [
    'name' => ['name', 'Name', 'player', 'Player'],
    'rank' => ['rank', 'Rang', 'role', 'Role'],
    'level' => ['level', 'Level', 'lvl'],
    'last_online' => ['last_online', 'lastOnline', 'zul. Online', 'last_active', 'lastActive'],
    'joined_at' => ['joined_at', 'joinedAt', 'Gildenbeitritt', 'joined', 'guild_joined'],
    'gold' => ['gold', 'Gold', 'Goldschatz', 'treasury'],
    'mentor' => ['mentor', 'Mentor', 'Lehrmeister'],
    'knight_hall' => ['knight_hall', 'knightHall', 'Ritterhalle', 'hall'],
    'guild_pet' => ['guild_pet', 'guildPet', 'Gildenpet', 'pet']
];

function findField($member, $possibleKeys) {
    foreach ($possibleKeys as $key) {
        if (isset($member[$key])) {
            return $member[$key];
        }
    }
    return '';
}

// Process each member
foreach ($members as $member) {
    $row = [
        findField($member, $fieldMap['name']),
        findField($member, $fieldMap['rank']),
        findField($member, $fieldMap['level']),
        findField($member, $fieldMap['last_online']),
        findField($member, $fieldMap['joined_at']),
        findField($member, $fieldMap['gold']),
        findField($member, $fieldMap['mentor']),
        findField($member, $fieldMap['knight_hall']),
        findField($member, $fieldMap['guild_pet']),
        '', // Tage offline - empty
        '', // Entlassen - empty (manual field)
        '', // Verlassen - empty (manual field)
        ''  // Sonstige Notizen - empty (manual field)
    ];
    
    $csvData[] = $row;
}

// Create CSV file
$filename = 'sftools_import_' . date('Y-m-d_His') . '.csv';
$filepath = $incomingDir . '/' . $filename;

// Ensure incoming directory exists
if (!is_dir($incomingDir)) {
    mkdir($incomingDir, 0775, true);
    logMessage("Created incoming directory");
}

// Write CSV
$fp = fopen($filepath, 'w');
if (!$fp) {
    logMessage("ERROR: Could not create file: $filepath");
    http_response_code(500);
    echo json_encode(['error' => 'Could not create CSV file']);
    exit;
}

// Use semicolon as delimiter to match existing import format
foreach ($csvData as $row) {
    fputcsv($fp, $row, ';');
}
fclose($fp);

// Set proper permissions
chmod($filepath, 0664);

logMessage("SUCCESS: Created CSV file: $filename with " . (count($csvData) - 1) . " members");

// Return success response
http_response_code(200);
echo json_encode([
    'success' => true,
    'message' => 'Import successful',
    'filename' => $filename,
    'members_count' => count($csvData) - 1
]);
