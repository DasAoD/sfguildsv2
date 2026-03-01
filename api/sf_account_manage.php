<?php
/**
 * API: SF Account Management
 * CRUD operations for multiple SF accounts per user
 * 
 * GET    - List all accounts for current user
 * POST   - Create or update an account
 * DELETE - Delete an account
 */
require_once __DIR__ . '/../includes/bootstrap_api.php';

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/encryption.php';

checkAuth();

$db = getDB();
$userId = $_SESSION['user_id'];

try {
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            handleGet($db, $userId);
            break;
        case 'POST':
            handlePost($db, $userId);
            break;
        case 'DELETE':
            handleDelete($db, $userId);
            break;
        default:
            jsonError('Method not allowed', 405);
    }
} catch (Exception $e) {
    logError('sf_account_manage failed', ['error' => $e->getMessage()]);
    jsonError('Interner Fehler', 500);
}

/**
 * GET: List all SF accounts for user
 */
function handleGet($db, $userId) {
    $stmt = $db->prepare("
        SELECT id, account_name, sf_username, selected_characters, is_default, created_at, updated_at
        FROM sf_accounts 
        WHERE user_id = ? 
        ORDER BY is_default DESC, created_at ASC
    ");
    $stmt->execute([$userId]);
    $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Decode selected_characters JSON and add stats
    foreach ($accounts as &$account) {
        $chars = $account['selected_characters'] ? json_decode($account['selected_characters'], true) : [];
        $account['selected_characters'] = $chars;
        $account['character_count'] = count($chars);
        
        // Count unique guilds from selected characters
        $guilds = array_unique(array_filter(array_column($chars, 'guild')));
        $account['guild_count'] = count($guilds);
    }
    
    jsonResponse(['success' => true, 'accounts' => $accounts]);
}

/**
 * POST: Create or update an SF account
 */
function handlePost($db, $userId) {
    try {
        $input = json_decode(file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        jsonError('Ungültige JSON-Daten', 400);
    }
    
    $accountId = $input['id'] ?? null;
    $accountName = trim($input['account_name'] ?? '');
    $sfUsername = trim($input['sf_username'] ?? '');
    $sfPassword = $input['sf_password'] ?? '';
    $isDefault = !empty($input['is_default']);
    
    // Validation
    if (empty($accountName)) {
        $accountName = $sfUsername ?: 'Neuer Account';
    }
    
    if (empty($sfUsername)) {
        jsonError('S&F Benutzername ist erforderlich', 400);
    }
    
    $db->beginTransaction();
    
    try {
        if ($accountId) {
            // UPDATE existing account
            $stmt = $db->prepare("SELECT id FROM sf_accounts WHERE id = ? AND user_id = ?");
            $stmt->execute([$accountId, $userId]);
            if (!$stmt->fetch()) {
                $db->rollBack();
                jsonError('Account nicht gefunden', 404);
            }
            
            if (!empty($sfPassword)) {
                // Update with new password
                $encrypted = encryptData($sfPassword);
                $stmt = $db->prepare("
                    UPDATE sf_accounts 
                    SET account_name = ?, sf_username = ?, sf_password_encrypted = ?, sf_iv = ?, sf_hmac = ?, updated_at = datetime('now')
                    WHERE id = ? AND user_id = ?
                ");
                $stmt->execute([$accountName, $sfUsername, $encrypted['encrypted'], $encrypted['iv'], $encrypted['hmac'], $accountId, $userId]);
            } else {
                // Update without changing password
                $stmt = $db->prepare("
                    UPDATE sf_accounts 
                    SET account_name = ?, sf_username = ?, updated_at = datetime('now')
                    WHERE id = ? AND user_id = ?
                ");
                $stmt->execute([$accountName, $sfUsername, $accountId, $userId]);
            }
        } else {
            // CREATE new account
            if (empty($sfPassword)) {
                $db->rollBack();
                jsonError('Passwort ist beim Erstellen erforderlich', 400);
            }
            
            // Check for duplicate
            $stmt = $db->prepare("SELECT id FROM sf_accounts WHERE user_id = ? AND sf_username = ?");
            $stmt->execute([$userId, $sfUsername]);
            if ($stmt->fetch()) {
                $db->rollBack();
                jsonError('Ein Account mit diesem Benutzernamen existiert bereits', 409);
            }
            
            $encrypted = encryptData($sfPassword);
            
            // Check if this is the first account (make it default)
            $stmt = $db->prepare("SELECT COUNT(*) FROM sf_accounts WHERE user_id = ?");
            $stmt->execute([$userId]);
            $existingCount = $stmt->fetchColumn();
            
            if ($existingCount === 0) {
                $isDefault = true;
            }
            
            $stmt = $db->prepare("
                INSERT INTO sf_accounts (user_id, account_name, sf_username, sf_password_encrypted, sf_iv, sf_hmac, is_default)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$userId, $accountName, $sfUsername, $encrypted['encrypted'], $encrypted['iv'], $encrypted['hmac'], $isDefault ? 1 : 0]);
            $accountId = $db->lastInsertId();
        }
        
        // Handle default flag
        if ($isDefault) {
            // Remove default from all others
            $stmt = $db->prepare("UPDATE sf_accounts SET is_default = 0 WHERE user_id = ? AND id != ?");
            $stmt->execute([$userId, $accountId]);
            
            // Set this one as default
            $stmt = $db->prepare("UPDATE sf_accounts SET is_default = 1 WHERE id = ? AND user_id = ?");
            $stmt->execute([$accountId, $userId]);
        }
        
        $db->commit();
        
        jsonResponse(['success' => true, 'account_id' => (int)$accountId, 'message' => $accountId && isset($input['id']) ? 'Account aktualisiert' : 'Account erstellt']);
        
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}

/**
 * DELETE: Remove an SF account
 */
function handleDelete($db, $userId) {
    try {
        $input = json_decode(file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        jsonError('Ungültige JSON-Daten', 400);
    }
    $accountId = $input['id'] ?? null;
    
    if (!$accountId) {
        jsonError('Account-ID erforderlich', 400);
    }
    
    $db->beginTransaction();
    
    try {
        // Check if account exists and belongs to user
        $stmt = $db->prepare("SELECT is_default FROM sf_accounts WHERE id = ? AND user_id = ?");
        $stmt->execute([$accountId, $userId]);
        $account = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$account) {
            $db->rollBack();
            jsonError('Account nicht gefunden', 404);
        }
        
        // Delete the account
        $stmt = $db->prepare("DELETE FROM sf_accounts WHERE id = ? AND user_id = ?");
        $stmt->execute([$accountId, $userId]);
        
        // If deleted account was default, make another one default
        if ($account['is_default']) {
            $stmt = $db->prepare("
                UPDATE sf_accounts SET is_default = 1 
                WHERE user_id = ? 
                ORDER BY created_at ASC 
                LIMIT 1
            ");
            $stmt->execute([$userId]);
        }
        
        $db->commit();
        
        jsonResponse(['success' => true, 'message' => 'Account gelöscht']);
        
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}
