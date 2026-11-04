<?php
/**
 * Guild Management API
 * Admin-only endpoint for guild CRUD operations
 */
require_once __DIR__ . '/../includes/bootstrap_api.php';
require_once __DIR__ . '/../includes/guild_crest.php';
require_once __DIR__ . '/../includes/sf_helpers.php';

// Must be logged in
requireAdminAPI();

$method = $_SERVER['REQUEST_METHOD'];

// Handle _method override for file uploads
if ($method === 'POST' && isset($_POST['_method'])) {
    $method = $_POST['_method'];
}

try {
    switch ($method) {
        case 'GET':
            // List all guilds
            $guilds = query('SELECT * FROM guilds ORDER BY name');
            foreach ($guilds as &$guild) {
                $guild['coa_image'] = resolveGuildCrestImage((int) $guild['id'], $guild['coa_code'], $guild['name']);
                unset($guild['coa_code']);
            }
            unset($guild);
            jsonResponse([
                'success' => true,
                'guilds' => $guilds
            ]);
            break;
            
        case 'POST':
            // Create new guild
            $name   = mb_substr(trim($_POST['name']   ?? ''), 0, 30);
            $server = mb_substr(trim($_POST['server'] ?? ''), 0, 20);
            $tag    = mb_substr(trim($_POST['tag']    ?? ''), 0, 10);
            $notes  = mb_substr(trim($_POST['notes']  ?? ''), 0, 1000);

            if (empty($name) || empty($server)) {
                jsonResponse(['success' => false, 'message' => 'Name und Server erforderlich'], 400);
            }
            
            // Insert guild first to get ID
            $guildId = insert(
                'INSERT INTO guilds (name, server, tag, notes, created_at) VALUES (?, ?, ?, ?, datetime("now"))',
                [$name, $server, $tag, $notes]
            );
            
            logActivity('Gilde erstellt', ['Name' => $name, 'Server' => $server]);
            
            jsonResponse([
                'success' => true,
                'message' => 'Gilde erfolgreich angelegt'
            ]);
            break;
            
        case 'PUT':
            // Update guild (via POST with _method=PUT)
            $guildId = isset($_POST['guild_id']) ? (int)$_POST['guild_id'] : 0;
            $name   = mb_substr(trim($_POST['name']   ?? ''), 0, 30);
            $server = mb_substr(trim($_POST['server'] ?? ''), 0, 20);
            $tag    = mb_substr(trim($_POST['tag']    ?? ''), 0, 10);
            $notes  = mb_substr(trim($_POST['notes']  ?? ''), 0, 1000);

            if (!$guildId || empty($name) || empty($server)) {
                jsonResponse(['success' => false, 'message' => 'Guild ID, Name und Server erforderlich'], 400);
            }
            
            execute(
                'UPDATE guilds SET name = ?, server = ?, tag = ?, notes = ?, updated_at = datetime("now") WHERE id = ?',
                [$name, $server, $tag, $notes, $guildId]
            );
            
            logActivity('Gilde aktualisiert', ['ID' => $guildId, 'Name' => $name]);
            
            jsonResponse([
                'success' => true,
                'message' => 'Gilde erfolgreich aktualisiert'
            ]);
            break;
            
        case 'DELETE':
            // Delete guild
            try {
                $input = json_decode(file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $e) {
                jsonError('Ungültige JSON-Daten', 400);
            }
            $guildId = isset($input['guild_id']) ? (int)$input['guild_id'] : 0;
            $force = $input['force'] ?? false; // Optional: Force delete with members

            if (!$guildId) {
                jsonResponse(['success' => false, 'message' => 'Guild ID erforderlich'], 400);
            }
            
            // Get guild info before deletion
            $guild = queryOne('SELECT name FROM guilds WHERE id = ?', [$guildId]);
            if (!$guild) {
                jsonResponse(['success' => false, 'message' => 'Gilde nicht gefunden'], 404);
            }
            
            $memberCount = queryOne('SELECT COUNT(*) as count FROM members WHERE guild_id = ?', [$guildId]);

            if (!$force && $memberCount['count'] > 0) {
                jsonResponse([
                    'success' => false,
                    'message' => 'Gilde hat noch ' . $memberCount['count'] . ' Mitglieder. Bitte "force" setzen um die Gilde samt Mitgliedern zu löschen.',
                    'member_count' => $memberCount['count'],
                ], 409);
            }

            $battleCount = queryOne('SELECT COUNT(*) as count FROM sf_eval_battles WHERE guild_id = ?', [$guildId]);

            // Battles take their participants along, the guild its inbox entries (FK cascade)
            $db = getDB();
            $db->beginTransaction();
            execute('DELETE FROM sf_eval_battles WHERE guild_id = ?', [$guildId]);
            execute('DELETE FROM members WHERE guild_id = ?', [$guildId]);
            execute('DELETE FROM guilds WHERE id = ?', [$guildId]);
            $db->commit();

            // Files only after the commit, so a failed delete leaves them intact
            $imageDir = __DIR__ . '/../public/assets/images/';
            $crestFiles = array_merge(
                glob($imageDir . 'guild_' . $guildId . '.*') ?: [],
                glob($imageDir . 'guild_coa_' . $guildId . '_*.webp') ?: []
            );
            foreach ($crestFiles as $file) {
                @unlink($file);
            }

            // Report folder is named after the guild; skip if another guild maps to the same slug
            $reportsBase = realpath(__DIR__ . '/../storage/sf_reports');
            $slug = sanitizeGuildName($guild['name']);
            $slugTaken = false;
            foreach (query('SELECT name FROM guilds') as $other) {
                if (sanitizeGuildName($other['name']) === $slug) {
                    $slugTaken = true;
                }
            }
            $reportsDir = $reportsBase !== false && $slug !== '' && $slug !== 'temp' && !$slugTaken
                ? realpath($reportsBase . '/' . $slug)
                : false;
            if ($reportsDir !== false && dirname($reportsDir) === $reportsBase) {
                foreach (glob($reportsDir . '/*') ?: [] as $file) {
                    @unlink($file);
                }
                @rmdir($reportsDir);
            }

            logActivity('Gilde gelöscht', [
                'ID' => $guildId, 
                'Name' => $guild['name'],
                'Mitglieder gelöscht' => $memberCount['count'],
                'Battles gelöscht' => $battleCount['count'] ?? 0
            ]);
            
            $message = 'Gilde erfolgreich gelöscht';
            $details = [];
            
            if ($memberCount['count'] > 0) {
                $details[] = $memberCount['count'] . ' Mitglieder';
            }
            if ($battleCount && $battleCount['count'] > 0) {
                $details[] = $battleCount['count'] . ' Kampfdaten';
            }
            
            if (!empty($details)) {
                $message .= ' (inkl. ' . implode(', ', $details) . ')';
            }
            
            jsonResponse([
                'success' => true,
                'message' => $message
            ]);
            break;
            
        default:
            jsonResponse(['success' => false, 'message' => 'Methode nicht erlaubt'], 405);
    }
    
} catch (Throwable $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    logError("Guild management failed", ["error" => $e->getMessage()]);
    jsonResponse([
        'success' => false,
        'message' => 'Fehler bei der Verarbeitung'
    ], 500);
}