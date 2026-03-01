<?php
/**
 * API: Get S&F Characters
 * GET: Returns characters for a specific account (or all accounts)
 * POST: Fetches all characters from S&F (test connection with new credentials)
 *
 * GET Parameters:
 *   account_id - (optional) Specific account to load characters for
 *
 * POST Body:
 *   username, password - Fresh credentials to test
 *   account_id - (optional) Existing account to refresh characters for
 */
require_once __DIR__ . '/../includes/bootstrap_api.php';
require_once __DIR__ . '/../includes/encryption.php';

header('Content-Type: application/json');

checkAuth();

$db = getDB();
$userId = $_SESSION['user_id'];

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        handlePost($db, $userId);
    } else {
        handleGet($db, $userId);
    }
} catch (Exception $e) {
    logError('sf_get_characters failed', ['error' => $e->getMessage()]);
    jsonError('Interner Fehler', 500);
}

/**
 * list_chars via SSH auf Heimserver ausführen (Residential-IP).
 * Credentials werden via stdin übergeben – nie in argv/Prozessliste sichtbar.
 * Hard-Timeout: 30 Sekunden, danach SIGKILL.
 */
function runListChars(string $username, string $password): array {
    $sshCmd = ['sudo', '-u', 'sfetch', '/opt/sfetch/run_fetch.sh', '/root/sf-api/run_list_chars_wrapper.sh'];

    $descriptorspec = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $process = proc_open($sshCmd, $descriptorspec, $pipes, null, $_ENV);

    if (!is_resource($process)) {
        throw new Exception('Prozess konnte nicht gestartet werden');
    }

    fwrite($pipes[0], $username . "\n");
    fwrite($pipes[0], $password . "\n");
    fclose($pipes[0]);

    // Hard-Timeout: 30s per SIGKILL
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);

    $stdout   = '';
    $stderr   = '';
    $deadline = microtime(true) + 30;

    while (true) {
        $remaining = $deadline - microtime(true);
        if ($remaining <= 0) {
            proc_terminate($process, 9); // SIGKILL
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);
            throw new Exception('Timeout: Zeichenabruf hat 30 Sekunden überschritten');
        }

        $read   = [$pipes[1], $pipes[2]];
        $write  = null;
        $except = null;
        $sec    = (int) $remaining;
        $usec   = (int)(($remaining - $sec) * 1_000_000);

        $ready = stream_select($read, $write, $except, $sec, $usec);
        if ($ready === false) {
            break;
        }

        foreach ($read as $stream) {
            $chunk = fread($stream, 8192);
            if ($chunk !== false) {
                if ($stream === $pipes[1]) {
                    $stdout .= $chunk;
                } else {
                    $stderr .= $chunk;
                }
            }
        }

        if (feof($pipes[1]) && feof($pipes[2])) {
            break;
        }
    }

    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($process);

    if (empty($stdout) || str_contains($stdout, 'Login failed')) {
        logError('list_chars failed', ['stderr' => trim($stderr)]);
        throw new Exception('Keine Charaktere gefunden oder Login fehlgeschlagen');
    }

    return explode("\n", $stdout);
}

/**
 * POST: Verbindung testen und alle Charaktere laden
 */
function handlePost($db, $userId) {
    $input     = json_decode(file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
    $username  = $input['username'] ?? '';
    $password  = $input['password'] ?? '';
    $accountId = $input['account_id'] ?? null;

    if ($accountId && (empty($username) || empty($password))) {
        $stmt = $db->prepare("SELECT sf_username, sf_password_encrypted, sf_iv, sf_hmac FROM sf_accounts WHERE id = ? AND user_id = ?");
        $stmt->execute([$accountId, $userId]);
        $account = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$account) {
            jsonError('Account nicht gefunden', 404);
        }

        $username = $account['sf_username'];
        $password = decryptData($account['sf_password_encrypted'], $account['sf_iv'], $account['sf_hmac'] ?? null);
    }

    if (empty($username) || empty($password)) {
        jsonError('Benutzername und Passwort erforderlich', 400);
    }

    $output     = runListChars($username, $password);
    $characters = parseCharacterList($output);
    enrichWithGuildNames($characters, $db, $userId);

    jsonResponse(['success' => true, 'characters' => $characters]);
}

/**
 * GET: Gespeicherte Charaktere für einen Account zurückgeben
 */
function handleGet($db, $userId) {
    $accountId = $_GET['account_id'] ?? null;

    if ($accountId) {
        $stmt = $db->prepare("SELECT sf_username, sf_password_encrypted, sf_iv, sf_hmac, selected_characters FROM sf_accounts WHERE id = ? AND user_id = ?");
        $stmt->execute([$accountId, $userId]);
        $account = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$account) {
            jsonError('Account nicht gefunden', 404);
        }
    } else {
        $stmt = $db->prepare("SELECT sf_username, sf_password_encrypted, sf_iv, sf_hmac, selected_characters FROM sf_accounts WHERE user_id = ? AND is_default = 1");
        $stmt->execute([$userId]);
        $account = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$account || !$account['sf_password_encrypted']) {
            jsonError('Kein S&F Account verknüpft', 400);
        }
    }

    $sfPassword = decryptData($account['sf_password_encrypted'], $account['sf_iv'], $account['sf_hmac'] ?? null);
    $output     = runListChars($account['sf_username'], $sfPassword);
    $characters = parseCharacterList($output);
    enrichWithGuildNames($characters, $db, $userId);

    jsonResponse(['success' => true, 'characters' => $characters, 'from_selection' => false]);
}

/**
 * Charakternamen mit Gildennamen aus der Datenbank anreichern
 */
function enrichWithGuildNames(array &$characters, $db, int $userId): void {
    foreach ($characters as &$char) {
        if (!empty($char['guild'])) {
            continue;
        }
        $stmt = $db->prepare("
            SELECT DISTINCT g.name
            FROM battle_inbox bi
            JOIN guilds g ON bi.guild_id = g.id
            WHERE bi.character_name = ? AND bi.user_id = ?
            LIMIT 1
        ");
        $stmt->execute([$char['name'], $userId]);
        $guild = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($guild) {
            $char['guild'] = $guild['name'];
        }
    }
}

/**
 * Rust-Ausgabe in Charakter-Array umwandeln
 */
function parseCharacterList(array $output): array {
    $characters  = [];
    $currentChar = null;

    foreach ($output as $line) {
        $line = trim($line);

        if (preg_match('/^=== Mail/', $line)) {
            break;
        }

        if (preg_match('/^Character #\d+$/i', $line)) {
            if ($currentChar && isset($currentChar['name'])) {
                $characters[] = $currentChar;
            }
            $currentChar = [];
            continue;
        }

        if ($currentChar === null) {
            continue;
        }

        if (preg_match('/^Server:\s*https?:\/\/([^\/]+)/i', $line, $m)) {
            $currentChar['server'] = $m[1];
        } elseif (preg_match('/^Name:\s*(.+)$/i', $line, $m)) {
            $currentChar['name'] = trim($m[1]);
        } elseif (preg_match('/^Level:\s*(\d+)$/i', $line, $m)) {
            $currentChar['level'] = (int) $m[1];
        } elseif (preg_match('/^\s*Guild:\s*(.+)$/i', $line, $m)) {
            $currentChar['guild'] = trim($m[1]);
        }
    }

    if ($currentChar && isset($currentChar['name'])) {
        $characters[] = $currentChar;
    }

    return array_values(array_filter($characters, fn($c) => isset($c['name'], $c['server'])));
}