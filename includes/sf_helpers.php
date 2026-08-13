<?php
/**
 * S&F API Helper Functions
 * Parsing utilities for battle reports
 */

require_once __DIR__ . '/encryption.php';
require_once __DIR__ . '/raid_names.php';

/**
 * Parse Rust-format battle report header
 */
function parseRustBattleReport($content) {
    // Check if it's Rust format
    if (!preg_match('/^=== S&F KAMPFBERICHT ===/', $content)) {
        return null; // Not Rust format
    }

    $result = [];

    // Parse header fields
    preg_match('/Gildenname:\s*(.+)/m', $content, $m);
    $result['guild_name'] = trim($m[1] ?? '');

    preg_match('/Server:\s*(.+)/m', $content, $m);
    $result['server'] = trim($m[1] ?? '');

    preg_match('/Charakter:\s*(.+)/m', $content, $m);
    $result['character'] = trim($m[1] ?? '');

    preg_match('/Gegner:\s*(.+)/m', $content, $m);
    $result['opponent'] = trim($m[1] ?? '');

    preg_match('/Typ:\s*(Angriff|Verteidigung|Gildenraid)/m', $content, $m);
    $typMap = [
        'Angriff' => 'attack',
        'Verteidigung' => 'defense',
        'Gildenraid' => 'raid',
    ];
    $result['type'] = $typMap[$m[1] ?? ''] ?? 'attack';

    // Raid-Name aus numerischer ID auflösen
    if ($result['type'] === 'raid' && is_numeric($result['opponent'])) {
        $result['raid_id'] = (int)$result['opponent'];
        $result['opponent'] = resolveRaidName($result['raid_id']);
    }

    preg_match('/Datum:\s*(\d{2}\.\d{2}\.\d{4})/m', $content, $m);
    $result['date'] = convertDateToSQL($m[1] ?? '');

    preg_match('/Uhrzeit:\s*(\d{2}:\d{2})/m', $content, $m);
    $result['time'] = $m[1] ?? '';

    preg_match('/Message-ID:\s*msg(\d+)/m', $content, $m);
    $result['message_id'] = $m[1] ?? null;

    // Ergebnis: "Ergebnis: Gewonnen" / "Ergebnis: Verloren" / fehlt → null
    if (preg_match('/Ergebnis:\s*(Gewonnen|Verloren)/m', $content, $m)) {
        $result['won'] = ($m[1] === 'Gewonnen') ? 1 : 0;
    } else {
        $result['won'] = null;
    }

    // Extract content after header
    $parts = preg_split('/=== ENDE HEADER ===\s*/m', $content, 2);
    $result['content'] = trim($parts[1] ?? '');

    // Parse participants from content
    $result['participants'] = parseRustParticipants($result['content']);

    return $result;
}

/**
 * Parse participants from Rust format content
 */
function parseRustParticipants($content) {
    $participants = [];
    $lines = explode("\n", $content);
    $currentSection = null;

    foreach ($lines as $line) {
        $line = trim($line);

        if (strpos($line, 'nicht teilgenommen haben') !== false) {
            $currentSection = 'not_participated';
            continue;
        }
        if (strpos($line, 'teilgenommen haben') !== false) {
            $currentSection = 'participated';
            continue;
        }

        // Parse player line: Name (Stufe Level)
        if (!preg_match('/^(.+?)\s*\(Stufe\s*(\d+)\)\s*$/ui', $line, $m)) {
            continue;
        }

        $namePart = trim($m[1]);
        $level = (int)$m[2];

        // Extract server tag if present (but keep it in the name!)
        $serverTag = null;
        if (preg_match('/\(([a-z][0-9]+[a-z0-9]+)\)/ui', $namePart, $mm)) {
            $serverTag = strtolower($mm[1]);
        }

        $participants[] = [
            'name' => $namePart,
            'level' => $level,
            'server_tag' => $serverTag,
            'participated' => ($currentSection === 'participated') ? 1 : 0
        ];
    }

    return $participants;
}

/**
 * Convert German date format to SQL format
 */
function convertDateToSQL($dateStr) {
    // DD.MM.YYYY → YYYY-MM-DD
    if (preg_match('/^(\d{2})\.(\d{2})\.(\d{4})$/', $dateStr, $m)) {
        return "{$m[3]}-{$m[2]}-{$m[1]}";
    }
    return $dateStr;
}

/**
 * Sanitize filename for guild name
 */
function sanitizeGuildName($name) {
    $name = mb_strtolower($name, 'UTF-8');
    $name = preg_replace('/[^a-z0-9\s-]/u', '', $name);
    $name = preg_replace('/\s+/', '_', $name);
    return trim($name);
}

/**
 * Find guild ID by name
 */
function findGuildIdByName($db, $guildName) {
    $stmt = $db->prepare("SELECT id FROM guilds WHERE LOWER(name) = LOWER(?)");
    $stmt->execute([$guildName]);
    $guild = $stmt->fetch(PDO::FETCH_ASSOC);
    return $guild ? $guild['id'] : null;
}

/**
 * Findet den passenden sf_account + Charakter für eine Gilde anhand von
 * sf_accounts.selected_characters (JSON-Array mit 'guild'-Feld pro Charakter).
 *
 * $userId = null durchsucht die Accounts ALLER Admin-User (Cron-Kontext,
 * kein eingeloggter Benutzer vorhanden). Ein konkreter $userId beschränkt
 * die Suche auf dessen eigene Accounts (manueller Trigger im eingeloggten
 * Kontext, z.B. "Mitglieder synchronisieren"-Button).
 *
 * @return array{account: array, character: array}|null
 */
function findSfAccountForGuild($db, ?int $userId, string $guildName) {
    if ($userId !== null) {
        $stmt = $db->prepare("
            SELECT id, user_id, sf_username, sf_password_encrypted, sf_iv, sf_hmac, selected_characters
            FROM sf_accounts
            WHERE user_id = ? AND selected_characters IS NOT NULL AND selected_characters != '[]'
        ");
        $stmt->execute([$userId]);
    } else {
        $stmt = $db->query("
            SELECT a.id, a.user_id, a.sf_username, a.sf_password_encrypted, a.sf_iv, a.sf_hmac, a.selected_characters
            FROM sf_accounts a
            JOIN users u ON u.id = a.user_id
            WHERE u.role = 'admin'
              AND a.selected_characters IS NOT NULL AND a.selected_characters != '[]'
        ");
    }
    $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($accounts as $account) {
        $chars = json_decode($account['selected_characters'], true) ?? [];
        foreach ($chars as $char) {
            if (($char['guild'] ?? '') === $guildName) {
                return ['account' => $account, 'character' => $char];
            }
        }
    }

    return null;
}

/**
 * Findet einen sf_account + Charakter des übergebenen Users für einen
 * Server-Hostnamen (z.B. "f25.sfgame.net") — unabhängig von der Gilde. Für
 * Abfragen, die nur irgendeinen eingeloggten Charakter auf dem richtigen
 * Server brauchen (z.B. guild_attack_time gegen eine fremde Zielgilde),
 * nicht zwingend einen aus der Zielgilde selbst wie findSfAccountForGuild().
 *
 * Bewusst KEIN Fallback auf Accounts anderer Nutzer: laut CLAUDE.md dürfen
 * nur Cron/automatisierte Jobs auf fremde (Admin-)Accounts zurückgreifen,
 * interaktive Nutzer-Aktionen sollen ausschließlich mit dem eigenen Account
 * des klickenden Nutzers laufen — sonst würde ein fremder SF-Account ohne
 * Wissen/Zutun seines Besitzers eingeloggt, nur weil irgendwer sonst auf
 * einen Button geklickt hat.
 *
 * @return array{account: array, character: array}|null
 */
function findAnySfAccountForServer($db, int $userId, string $serverHost) {
    $stmt = $db->prepare("
        SELECT id, user_id, sf_username, sf_password_encrypted, sf_iv, sf_hmac, selected_characters
        FROM sf_accounts
        WHERE user_id = ? AND selected_characters IS NOT NULL AND selected_characters != '[]'
    ");
    $stmt->execute([$userId]);

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $account) {
        $chars = json_decode($account['selected_characters'], true) ?? [];
        foreach ($chars as $char) {
            if (($char['server'] ?? '') === $serverHost) {
                return ['account' => $account, 'character' => $char];
            }
        }
    }

    return null;
}

/**
 * Server-Hostnamen, für die der übergebene User selbst einen nutzbaren
 * Charakter hat — für das Server-Dropdown der Angriffszeit-Abfrage.
 * Bewusst nutzerspezifisch (nicht global über alle sf_accounts), passend
 * zu findAnySfAccountForServer()'s Beschränkung auf den eigenen Account.
 * @return string[] sortierte, eindeutige Hostnamen, z.B. ["f25.sfgame.net", "f9.sfgame.net"]
 */
function listKnownServersForUser($db, int $userId) {
    $stmt = $db->prepare("
        SELECT selected_characters FROM sf_accounts
        WHERE user_id = ? AND selected_characters IS NOT NULL AND selected_characters != '[]'
    ");
    $stmt->execute([$userId]);
    $servers = [];
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $json) {
        $chars = json_decode($json, true) ?? [];
        foreach ($chars as $char) {
            if (!empty($char['server'])) {
                $servers[$char['server']] = true;
            }
        }
    }
    $list = array_keys($servers);
    sort($list);
    return $list;
}
