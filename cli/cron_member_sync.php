<?php
/**
 * Cron: Mitglieder synchronisieren (alle Gilden)
 * Wird von cron_runner.php aufgerufen.
 */
if (PHP_SAPI !== 'cli') { exit(1); }

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/sf_helpers.php';
require_once __DIR__ . '/../includes/logger.php';

$db = getDB();
$binary = '/root/sf-api/target/release/examples/member_sync';

if (!file_exists($binary) || !is_executable($binary)) {
    echo "member_sync Binary nicht gefunden!\n";
    return ['total' => 0, 'success' => 0, 'errors' => 1];
}

// Alle Gilden holen
$guilds = $db->query("SELECT id, name, server FROM guilds")->fetchAll(PDO::FETCH_ASSOC);

// Alle Accounts holen
$stmt = $db->query("
    SELECT id, user_id, sf_username, sf_password_encrypted, sf_iv, sf_hmac, selected_characters
    FROM sf_accounts
    WHERE selected_characters IS NOT NULL AND selected_characters != '[]'
");
$accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total = 0; $success = 0; $errors = 0;

foreach ($guilds as $guild) {
    // Passenden Charakter finden
    $foundChar = null; $foundAccount = null;
    foreach ($accounts as $account) {
        $chars = json_decode($account['selected_characters'], true) ?? [];
        foreach ($chars as $char) {
            if (($char['guild'] ?? '') === $guild['name']) {
                $foundChar = $char;
                $foundAccount = $account;
                break 2;
            }
        }
    }

    if (!$foundChar) {
        echo "⚠ Kein Charakter für Gilde '{$guild['name']}' gefunden, übersprungen.\n";
        continue;
    }

    $sfPassword = decryptData(
        $foundAccount['sf_password_encrypted'],
        $foundAccount['sf_iv'],
        $foundAccount['sf_hmac'] ?? null
    );

    $cmd = [$binary];
    $env = array_merge($_ENV, [
        'SSO_USERNAME' => $foundAccount['sf_username'],
        'SSO_PASSWORD' => $sfPassword,
        'SERVER_HOST'  => $foundChar['server'],
        'CHARACTER'    => $foundChar['name'],
    ]);
    $spec = [0 => ['pipe','r'], 1 => ['pipe','w'], 2 => ['pipe','w']];
    $proc = proc_open($cmd, $spec, $pipes, null, $env);
    if (!is_resource($proc)) { $errors++; continue; }
    fclose($pipes[0]);

    $out = ''; $start = time(); $stream = $pipes[1];
    stream_set_blocking($stream, false);
    while (!feof($stream)) {
        if ((time() - $start) > 90) { proc_terminate($proc, 9); break; }
        $r = [$stream]; $w = []; $e = [];
        if (stream_select($r, $w, $e, 1)) {
            $chunk = fread($stream, 8192);
            if ($chunk !== false) $out .= $chunk;
        }
    }
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($proc);

    $data = json_decode($out, true);
    $total++;

    if ($data && $data['success']) {
        // DB-Update (selbe Logik wie sf_member_sync.php)
        $members  = $data['members'] ?? [];
        $inserted = 0; $updated = 0; $rejoined = 0;
        $today    = gmdate('Y-m-d');

        $selStmt = $db->prepare("SELECT id, fired_at, left_at FROM members WHERE guild_id = ? AND name = ? LIMIT 1");
        $updStmt = $db->prepare("UPDATE members SET level=:level,rank=:rank,last_online=:last_online,gold=:gold,mentor=:mentor,knight_hall=:knight_hall,guild_pet=:guild_pet,updated_at=:updated_at WHERE id=:id");
        $rejStmt = $db->prepare("UPDATE members SET level=:level,rank=:rank,last_online=:last_online,gold=:gold,mentor=:mentor,knight_hall=:knight_hall,guild_pet=:guild_pet,fired_at=NULL,left_at=NULL,joined_at=:joined_at,updated_at=:updated_at WHERE id=:id");
        $insStmt = $db->prepare("INSERT INTO members (guild_id,name,level,rank,last_online,gold,mentor,knight_hall,guild_pet,joined_at,updated_at) VALUES (:guild_id,:name,:level,:rank,:last_online,:gold,:mentor,:knight_hall,:guild_pet,:joined_at,:updated_at)");

        $db->beginTransaction();
        try {
            foreach ($members as $m) {
                $selStmt->execute([$guild['id'], $m['name']]);
                $existing = $selStmt->fetch(PDO::FETCH_ASSOC);
                $params = [':level'=>$m['level'],':rank'=>$m['rank'],':last_online'=>$m['last_online']??null,':gold'=>$m['gold'],':mentor'=>$m['mentor'],':knight_hall'=>$m['knight_hall'],':guild_pet'=>$m['guild_pet'],':updated_at'=>gmdate('c')];
                if ($existing) {
                    if (!empty($existing['fired_at']) || !empty($existing['left_at'])) {
                        $rejStmt->execute(array_merge($params, [':joined_at'=>$today,':id'=>$existing['id']]));
                        $rejoined++;
                    } else {
                        $updStmt->execute(array_merge($params, [':id'=>$existing['id']]));
                        $updated++;
                    }
                } else {
                    $insStmt->execute(array_merge($params, [':guild_id'=>$guild['id'],':name'=>$m['name'],':joined_at'=>$today]));
                    $inserted++;
                }
            }
            execute("UPDATE guilds SET last_import_at=:ts,updated_at=datetime('now') WHERE id=:id", [':ts'=>gmdate('c'),':id'=>$guild['id']]);
            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            echo "✗ DB-Fehler {$guild['name']}: {$e->getMessage()}\n";
            $errors++; continue;
        }

        $success++;
        echo "✓ {$guild['name']}: +{$inserted} neu, {$updated} aktualisiert, {$rejoined} wiederbeigetreten\n";
    } else {
        $errors++;
        echo "✗ {$guild['name']}: " . ($data['error'] ?? 'Unbekannt') . "\n";
    }
}

return ['total' => $total, 'success' => $success, 'errors' => $errors];
