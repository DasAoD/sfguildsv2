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
if (!isLoggedIn()) {
    jsonResponse(['success' => false, 'message' => 'Nicht authentifiziert'], 401);
}

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
            execute(
                'INSERT INTO guilds (name, server, tag, notes, created_at) VALUES (?, ?, ?, ?, datetime("now"))',
                [$name, $server, $tag, $notes]
            );
            
            // Get the new guild ID
            $db = getConnection();
            $guildId = $db->lastInsertRowID();
            
            // Handle file upload with guild ID
            if (isset($_FILES['crest']) && $_FILES['crest']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = __DIR__ . '/../public/assets/images/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                $fileExt = strtolower(pathinfo($_FILES['crest']['name'], PATHINFO_EXTENSION));
                $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];
                
                if (in_array($fileExt, $allowedExts)) {
                    $tmpFile = $_FILES['crest']['tmp_name'];
                    $crestFile = 'guild_' . $guildId . '.webp';
                    $outputPath = $uploadDir . $crestFile;
                    
                    // Convert to WEBP
                    try {
                        $image = null;
                        
                        // Load image based on type
                        switch ($fileExt) {
                            case 'jpg':
                            case 'jpeg':
                                $image = @imagecreatefromjpeg($tmpFile);
                                break;
                            case 'png':
                                $image = @imagecreatefrompng($tmpFile);
                                break;
                            case 'gif':
                                $image = @imagecreatefromgif($tmpFile);
                                break;
                            case 'bmp':
                                $image = @imagecreatefrombmp($tmpFile);
                                break;
                            case 'webp':
                                $image = @imagecreatefromwebp($tmpFile);
                                break;
                        }
                        
                        if ($image) {
                            // Convert to WEBP with 90% quality
                            if (imagewebp($image, $outputPath, 90)) {
                                imagedestroy($image);
                                
                                // Update guild with crest filename
                                execute(
                                    'UPDATE guilds SET crest_file = ? WHERE id = ?',
                                    [$crestFile, $guildId]
                                );
                                $crestUploaded = true;
                            } else {
                                $crestError = 'Konvertierung zu WEBP fehlgeschlagen';
                                logError("WEBP conversion failed (create guild)", ["guild_id" => $guildId]);
                            }
                        } else {
                            $crestError = 'Bildformat nicht unterstützt oder Datei beschädigt';
                            logError("Image load failed (create guild)", ["extension" => $fileExt, "guild_id" => $guildId]);
                        }
                    } catch (Exception $e) {
                        $crestError = 'Fehler bei der Bildverarbeitung: ' . $e->getMessage();
                        logError("Image conversion failed (create guild)", ["error" => $e->getMessage()]);
                    }
                } else {
                    $crestError = 'Ungültiges Dateiformat (erlaubt: jpg, png, gif, bmp, webp)';
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
            $guildId = $_POST['guild_id'] ?? null;
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
                $fileExt = strtolower(pathinfo($_FILES['crest']['name'], PATHINFO_EXTENSION));
                $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];
                
                if (in_array($fileExt, $allowedExts)) {
                    // Delete old crest files for this guild (all extensions)
                    $oldGuild = queryOne('SELECT crest_file FROM guilds WHERE id = ?', [$guildId]);
                    if ($oldGuild && $oldGuild['crest_file']) {
                        $oldFile = $uploadDir . $oldGuild['crest_file'];
                        if (file_exists($oldFile)) {
                            unlink($oldFile);
                        }
                    }
                    
                    // Also clean up any timestamp-based files
                    foreach (glob($uploadDir . 'guild_' . $guildId . '.*') as $file) {
                        if (file_exists($file)) {
                            unlink($file);
                        }
                    }
                    
                    $tmpFile = $_FILES['crest']['tmp_name'];
                    $crestFile = 'guild_' . $guildId . '.webp';
                    $outputPath = $uploadDir . $crestFile;
                    
                    // Convert to WEBP
                    try {
                        $image = null;
                        
                        // Load image based on type
                        switch ($fileExt) {
                            case 'jpg':
                            case 'jpeg':
                                $image = @imagecreatefromjpeg($tmpFile);
                                break;
                            case 'png':
                                $image = @imagecreatefrompng($tmpFile);
                                break;
                            case 'gif':
                                $image = @imagecreatefromgif($tmpFile);
                                break;
                            case 'bmp':
                                $image = @imagecreatefrombmp($tmpFile);
                                break;
                            case 'webp':
                                $image = @imagecreatefromwebp($tmpFile);
                                break;
                        }
                        
                        if ($image) {
                            // Convert to WEBP with 90% quality
                            imagewebp($image, $outputPath, 90);
                            imagedestroy($image);
                        } else {
                            // If conversion failed, don't update crest_file
                            $crestFile = null;
                        }
                    } catch (Exception $e) {
                        logError("Image conversion failed (update guild)", ["error" => $e->getMessage()]);
                        $crestFile = null;
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
            $guildId = $input['guild_id'] ?? null;
            
            if (!$guildId) {
                jsonResponse(['success' => false, 'message' => 'Guild ID erforderlich'], 400);
            }
            
            // Check if guild has members
            $memberCount = queryOne('SELECT COUNT(*) as count FROM members WHERE guild_id = ?', [$guildId]);
            if ($memberCount['count'] > 0) {
                jsonResponse(['success' => false, 'message' => 'Gilde hat noch Mitglieder. Bitte zuerst alle Mitglieder entfernen.'], 400);
            }
            
            // Delete guild
            $guild = queryOne('SELECT name FROM guilds WHERE id = ?', [$guildId]);
            execute('DELETE FROM guilds WHERE id = ?', [$guildId]);
            
            logActivity('Gilde gelöscht', ['ID' => $guildId, 'Name' => $guild['name'] ?? '?']);
            
            jsonResponse([
                'success' => true,
                'message' => 'Gilde erfolgreich gelöscht'
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
