<?php
/**
 * API: Fetch Battle Reports (Parallel)
 * Fetches reports from all selected characters in parallel
 */

// Increase timeout for multiple character fetches
set_time_limit(300); // 5 Minuten
ini_set('max_execution_time', '300');

session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/sf_helpers.php';

header('Content-Type: application/json');

checkAuth();

$db = getDB();
$userId = $_SESSION['user_id'];

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);
$server = $input['server'] ?? null;
$character = $input['character'] ?? null;

try {
    // Get user's SF credentials
    $stmt = $db->prepare("SELECT sf_username, sf_password_encrypted, sf_iv, selected_characters FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user || !$user['sf_password_encrypted']) {
        throw new Exception('Keine S&F Credentials gefunden');
    }
    
    // Decrypt password
    $sfPassword = decryptData($user['sf_password_encrypted'], $user['sf_iv']);
    
    // Determine which characters to fetch
    $charactersToFetch = [];
    
    if ($server && $character) {
        // Single character mode (old behavior - for backwards compatibility)
        $charactersToFetch[] = [
            'name' => $character,
            'server' => $server,
            'guild' => 'Unbekannt'
        ];
    } else {
        // All selected characters mode (new behavior)
        if (empty($user['selected_characters'])) {
            throw new Exception('Keine Charaktere ausgewählt');
        }
        
        $charactersToFetch = json_decode($user['selected_characters'], true);
    }
    
    // Start all fetch processes in parallel
    $processes = [];
    $pipes = [];
    
    foreach ($charactersToFetch as $char) {
        $charJson = json_encode($char);
        
        $cmd = sprintf(
            'php %s/sf_fetch_single.php %s %s %s %s 2>&1',
            escapeshellarg(__DIR__),
            escapeshellarg($charJson),
            escapeshellarg($userId),
            escapeshellarg($user['sf_username']),
            escapeshellarg($sfPassword)
        );
        
        $descriptorspec = [
            0 => ['pipe', 'r'],  // stdin
            1 => ['pipe', 'w'],  // stdout
            2 => ['pipe', 'w']   // stderr
        ];
        
        $process = proc_open($cmd, $descriptorspec, $procPipes);
        
        if (is_resource($process)) {
            $processes[] = $process;
            $pipes[] = $procPipes;
            
            // Close stdin immediately
            fclose($procPipes[0]);
        }
    }
    
    // Wait for all processes and collect results
    $results = [];
    $totalCount = 0;
    
    foreach ($processes as $i => $process) {
        // Read stdout
        $output = stream_get_contents($pipes[$i][1]);
        fclose($pipes[$i][1]);
        
        // Read stderr (for errors)
        $errors = stream_get_contents($pipes[$i][2]);
        fclose($pipes[$i][2]);
        
        // Wait for process to finish
        $returnCode = proc_close($process);
        
        // Parse JSON output
        $result = json_decode($output, true);
        
        if ($result) {
            $results[] = $result;
            if ($result['success']) {
                $totalCount += $result['count'];
            }
        } else {
            // Fallback if JSON parsing failed
            $results[] = [
                'success' => false,
                'character' => $charactersToFetch[$i]['name'] ?? 'Unbekannt',
                'server' => $charactersToFetch[$i]['server'] ?? '',
                'guild' => $charactersToFetch[$i]['guild'] ?? 'Unbekannt',
                'count' => 0,
                'error' => $errors ?: 'Unbekannter Fehler'
            ];
        }
    }
    
    echo json_encode([
        'success' => true,
        'total' => $totalCount,
        'results' => $results
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
