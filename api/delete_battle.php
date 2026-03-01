<?php
require_once __DIR__ . '/../includes/bootstrap_api.php';

// Check authentication
requireAdminAPI();

// Get database connection
$db = getDB();

// Get POST data
try {
    $input = json_decode(file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
} catch (JsonException $e) {
    jsonResponse(['success' => false, 'message' => 'Ungültige JSON-Daten'], 400);
}
$battleId = $input['battle_id'] ?? null;

if (!$battleId) {
    jsonError('Battle ID required', 400);
}

try {
    $db->beginTransaction();

    // Find related inbox entry (to get file_path before deleting)
    $stmt = $db->prepare("SELECT id, file_path FROM battle_inbox WHERE imported_to_battle_id = ?");
    $stmt->execute([$battleId]);
    $inboxEntry = $stmt->fetch(PDO::FETCH_ASSOC);

    // Delete battle (participants deleted automatically via CASCADE)
    $stmt = $db->prepare("DELETE FROM sf_eval_battles WHERE id = ?");
    $stmt->execute([$battleId]);

    // Delete inbox entry
    if ($inboxEntry) {
        $stmt = $db->prepare("DELETE FROM battle_inbox WHERE id = ?");
        $stmt->execute([$inboxEntry['id']]);

        // Delete .txt file from disk
        if (!empty($inboxEntry['file_path']) && file_exists($inboxEntry['file_path'])) {
            unlink($inboxEntry['file_path']);
        }
    }

    $db->commit();

    jsonResponse(['success' => true]);

} catch (PDOException $e) {
    $db->rollBack();
    logError('delete_battle failed', ['error' => $e->getMessage()]);
    jsonError('Database error', 500);
}