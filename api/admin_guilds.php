<?php
/**
 * Guild Management API
 * Admin-only endpoint for guild CRUD operations
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/logger.php';

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
            jsonResponse([
                'success' => true,
                'guilds' => $guilds
            ]);
            break;
            
        case 'POST':
            // Create new guild
            $name = trim($_POST['name'] ?? '');
            $server = trim($_POST['server'] ?? '');
            $tag = trim($_POST['tag'] ?? '');
            $notes = trim($_POST['notes'] ?? '');
            $crestFile = null;
            $crestUploaded = false;
            $crestError = null;
            
            if (empty($name) || empty($server)) {
                jsonResponse(['success' => false, 'message' => 'Name und Server erforderlich'], 400);
            }
            
            // Insert guild first to get ID
            $guildId = insert(
                'INSERT INTO guilds (name, server, tag, notes, created_at) VALUES (?, ?, ?, ?, datetime("now"))',
                [$name, $server, $tag, $notes]
            );
            
            // Handle file upload with guild ID
            if (isset($_FILES['crest']) && $_FILES['crest']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = __DIR__ . '/../public/assets/images/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                // Dateigröße-Limit: 2MB (non-fatal: Gilde wird trotzdem angelegt)
                if ($_FILES['crest']['size'] > 2.5 * 1024 * 1024) {
                    $crestError = 'Wappen-Datei zu groß (max. 2,5MB)';
                } else {
                    $fileExt = strtolower(pathinfo($_FILES['crest']['name'], PATHINFO_EXTENSION));
                    $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];

                    if (!in_array($fileExt, $allowedExts)) {
                        $crestError = 'Ungültiges Dateiformat (erlaubt: jpg, png, gif, bmp, webp)';
                    } else {
                        $tmpFile  = $_FILES['crest']['tmp_name'];
                        $crestFile = 'guild_' . $guildId . '.webp';
                        $outputPath = $uploadDir . $crestFile;

                        // Dimensionen prüfen bevor GD lädt
                        $imgInfo = @getimagesize($tmpFile);
                        if ($imgInfo === false) {
                            $crestError = 'Bilddatei konnte nicht gelesen werden';
                        } elseif ($imgInfo[0] > 4096 || $imgInfo[1] > 4096) {
                            $crestError = 'Bild zu groß (max. 4096×4096 Pixel)';
                        } else {
                            try {
                                // finfo_file() prüft echten MIME-Typ der Datei, nicht den Client-Input
                                $finfo    = new finfo(FILEINFO_MIME_TYPE);
                                $mimeType = $finfo->file($tmpFile);
                                $mimeToLoader = [
                                    'image/jpeg' => 'imagecreatefromjpeg',
                                    'image/png'  => 'imagecreatefrompng',
                                    'image/gif'  => 'imagecreatefromgif',
                                    'image/bmp'  => 'imagecreatefrombmp',
                                    'image/x-bmp'=> 'imagecreatefrombmp',
                                    'image/webp' => 'imagecreatefromwebp',
                                ];
                                $loader = $mimeToLoader[$mimeType] ?? null;
                                $image  = $loader ? @$loader($tmpFile) : null;
                                if ($image) {
                                    if (imagewebp($image, $outputPath, 90)) {
                                        imagedestroy($image);
                                        execute('UPDATE guilds SET crest_file = ? WHERE id = ?', [$crestFile, $guildId]);
                                        $crestUploaded = true;
                                    } else {
                                        $crestError = 'Konvertierung zu WEBP fehlgeschlagen';
                                        logError("WEBP conversion failed (create guild)", ["guild_id" => $guildId]);
                                    }
                                } else {
                                    $crestError = 'Bildformat nicht unterstützt oder Datei beschädigt';
                                    logError("Image load failed (create guild)", ["mime" => $mimeType, "guild_id" => $guildId]);
                                }
                            } catch (Exception $e) {
                                logError('Bildverarbeitung fehlgeschlagen (create)', ['error' => $e->getMessage()]);
                                $crestError = 'Fehler bei der Bildverarbeitung';
                            }
                        }
                    }
                }
            }
            
            logActivity('Gilde erstellt', ['Name' => $name, 'Server' => $server]);
            
            $message = 'Gilde erfolgreich angelegt';
            if ($crestUploaded) {
                $message .= ' (mit Wappen)';
            } elseif ($crestError) {
                $message .= ' (Wappen konnte nicht hochgeladen werden: ' . $crestError . ')';
            }
            
            jsonResponse([
                'success' => true,
                'message' => $message,
                'crest_uploaded' => $crestUploaded
            ]);
            break;
            
        case 'PUT':
            // Update guild (via POST with _method=PUT)
            $guildId = isset($_POST['guild_id']) ? (int)$_POST['guild_id'] : 0;
            $name = trim($_POST['name'] ?? '');
            $server = trim($_POST['server'] ?? '');
            $tag = trim($_POST['tag'] ?? '');
            $notes = trim($_POST['notes'] ?? '');

            if (!$guildId || empty($name) || empty($server)) {
                jsonResponse(['success' => false, 'message' => 'Guild ID, Name und Server erforderlich'], 400);
            }
            
            // Handle file upload
            $crestFile = null;
            if (isset($_FILES['crest']) && $_FILES['crest']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = __DIR__ . '/../public/assets/images/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                // Dateigröße-Limit: 2MB (non-fatal: Update wird trotzdem durchgeführt)
                if ($_FILES['crest']['size'] > 2 * 1024 * 1024) {
                    $crestError = 'Wappen-Datei zu groß (max. 2MB)';
                } else {
                    $fileExt = strtolower(pathinfo($_FILES['crest']['name'], PATHINFO_EXTENSION));
                    $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];

                    if (!in_array($fileExt, $allowedExts)) {
                        $crestError = 'Ungültiges Dateiformat (erlaubt: jpg, png, gif, bmp, webp)';
                    } else {
                        // Altes Wappen löschen
                        $oldGuild = queryOne('SELECT crest_file FROM guilds WHERE id = ?', [$guildId]);
                        if ($oldGuild && $oldGuild['crest_file']) {
                            $oldFile = $uploadDir . $oldGuild['crest_file'];
                            if (file_exists($oldFile)) { unlink($oldFile); }
                        }
                        foreach (glob($uploadDir . 'guild_' . $guildId . '.*') as $file) {
                            if (file_exists($file)) { unlink($file); }
                        }

                        $tmpFile   = $_FILES['crest']['tmp_name'];
                        $crestFile = 'guild_' . $guildId . '.webp';
                        $outputPath = $uploadDir . $crestFile;

                        // Dimensionen prüfen bevor GD lädt
                        $imgInfo = @getimagesize($tmpFile);
                        if ($imgInfo === false) {
                            $crestError = 'Bilddatei konnte nicht gelesen werden';
                            $crestFile = null;
                        } elseif ($imgInfo[0] > 4096 || $imgInfo[1] > 4096) {
                            $crestError = 'Bild zu groß (max. 4096×4096 Pixel)';
                            $crestFile = null;
                        } else {
                            try {
                                // finfo_file() prüft echten MIME-Typ der Datei, nicht den Client-Input
                                $finfo    = new finfo(FILEINFO_MIME_TYPE);
                                $mimeType = $finfo->file($tmpFile);
                                $mimeToLoader = [
                                    'image/jpeg' => 'imagecreatefromjpeg',
                                    'image/png'  => 'imagecreatefrompng',
                                    'image/gif'  => 'imagecreatefromgif',
                                    'image/bmp'  => 'imagecreatefrombmp',
                                    'image/x-bmp'=> 'imagecreatefrombmp',
                                    'image/webp' => 'imagecreatefromwebp',
                                ];
                                $loader = $mimeToLoader[$mimeType] ?? null;
                                $image  = $loader ? @$loader($tmpFile) : null;
                                if ($image) {
                                    if (imagewebp($image, $outputPath, 90)) {
                                        imagedestroy($image);
                                    } else {
                                        imagedestroy($image);
                                        $crestFile = null;
                                        logError("WEBP conversion failed (update guild)", ["guild_id" => $guildId]);
                                    }
                                } else {
                                    $crestFile = null;
                                    logError("Image load failed (update guild)", ["mime" => $mimeType, "guild_id" => $guildId]);
                                }
                            } catch (Exception $e) {
                                logError("Image conversion failed (update guild)", ["error" => $e->getMessage()]);
                                $crestFile = null;
                            }
                        }
                    }
                }
            }
            
            // Update guild
            if ($crestFile) {
                execute(
                    'UPDATE guilds SET name = ?, server = ?, tag = ?, notes = ?, crest_file = ?, updated_at = datetime("now") WHERE id = ?',
                    [$name, $server, $tag, $notes, $crestFile, $guildId]
                );
            } else {
                execute(
                    'UPDATE guilds SET name = ?, server = ?, tag = ?, notes = ?, updated_at = datetime("now") WHERE id = ?',
                    [$name, $server, $tag, $notes, $guildId]
                );
            }
            
            logActivity('Gilde aktualisiert', ['ID' => $guildId, 'Name' => $name]);
            
            jsonResponse([
                'success' => true,
                'message' => 'Gilde erfolgreich aktualisiert'
            ]);
            break;
            
        case 'DELETE':
            // Delete guild
            $input = json_decode(file_get_contents('php://input'), true);
            $guildId = isset($input['guild_id']) ? (int)$input['guild_id'] : 0;
            $force = $input['force'] ?? false; // Optional: Force delete with members

            if (!$guildId) {
                jsonResponse(['success' => false, 'message' => 'Guild ID erforderlich'], 400);
            }
            
            // Get guild info before deletion
            $guild = queryOne('SELECT name, crest_file FROM guilds WHERE id = ?', [$guildId]);
            if (!$guild) {
                jsonResponse(['success' => false, 'message' => 'Gilde nicht gefunden'], 404);
            }
            
            // Check member count for logging and deletion
            $memberCount = queryOne('SELECT COUNT(*) as count FROM members WHERE guild_id = ?', [$guildId]);
            
            // Delete crest file if exists
            if ($guild['crest_file']) {
                $uploadDir = __DIR__ . '/../public/assets/images/';
                $crestPath = $uploadDir . $guild['crest_file'];
                
                if (file_exists($crestPath)) {
                    @unlink($crestPath);
                }
                
                // Also clean up any other guild crest files with this ID
                foreach (glob($uploadDir . 'guild_' . $guildId . '.*') as $file) {
                    if (file_exists($file)) {
                        @unlink($file);
                    }
                }
            }
            
            // Delete associated battle data
            $battleCount = queryOne('SELECT COUNT(*) as count FROM sf_eval_battles WHERE guild_id = ?', [$guildId]);
            if ($battleCount && $battleCount['count'] > 0) {
                execute('DELETE FROM sf_eval_battles WHERE guild_id = ?', [$guildId]);
            }
            
            // Block delete if members exist and force is not set
            if (!$force && $memberCount['count'] > 0) {
                jsonResponse([
                    'success' => false,
                    'message' => 'Gilde hat noch ' . $memberCount['count'] . ' Mitglieder. Bitte "force" setzen um die Gilde samt Mitgliedern zu löschen.',
                    'member_count' => $memberCount['count'],
                ], 409);
            }

            // Delete members if force is enabled
            if ($force && $memberCount['count'] > 0) {
                execute('DELETE FROM members WHERE guild_id = ?', [$guildId]);
            }

            // Delete guild
            execute('DELETE FROM guilds WHERE id = ?', [$guildId]);
            
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
            if ($guild['crest_file']) {
                $details[] = 'Wappen';
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
    
} catch (Exception $e) {
    logError("Guild management failed", ["error" => $e->getMessage()]);
    jsonResponse([
        'success' => false,
        'message' => 'Fehler bei der Verarbeitung'
    ], 500);
}
