<?php
/**
 * Update Member API Endpoint
 * Updates member information (notes, fired_at, left_at)
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/logger.php';

// Must be logged in
requireModeratorAPI();

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Nur POST erlaubt'], 405);
}

// Get input
$input = json_decode(file_get_contents('php://input'), true);
$memberId = $input['member_id'] ?? null;
$field = $input['field'] ?? null;
$value = $input['value'] ?? null;

if (!$memberId || !$field) {
    jsonResponse(['success' => false, 'message' => 'Member ID und Feld erforderlich'], 400);
}

// Allowed fields
$allowedFields = ['notes', 'fired_at', 'left_at'];
if (!in_array($field, $allowedFields)) {
    jsonResponse(['success' => false, 'message' => 'Ungültiges Feld'], 400);
}

try {
    // Convert empty strings to NULL for date fields and notes
    if (in_array($field, ['fired_at', 'left_at', 'notes']) && ($value === '' || $value === null)) {
        $value = null;
    }
    
    // Update member
    $updated = execute(
        "UPDATE members SET $field = ?, updated_at = datetime('now') WHERE id = ?",
        [$value, $memberId]
    );
    
    if ($updated) {
        // Get member name for log
        $member = queryOne('SELECT name FROM members WHERE id = ?', [$memberId]);
        
        // German field labels
        $fieldLabels = ['notes' => 'Notizen', 'fired_at' => 'Entlassen', 'left_at' => 'Verlassen'];
        
        // Format date values to DD.MM.YYYY
        $logValue = $value;
        if (in_array($field, ['fired_at', 'left_at'])) {
            if ($value === null) {
                $logValue = '(Datum entfernt)';
            } elseif (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m)) {
                $logValue = "{$m[3]}.{$m[2]}.{$m[1]}";
            }
        } elseif ($field === 'notes') {
            $logValue = $value === null ? '(Notiz entfernt)' : '(Notiz geändert)';
        }
        
        logActivity('Mitglied aktualisiert', [
            'Name'  => $member['name'] ?? '?',
            'ID'    => $memberId,
            'Feld'  => $fieldLabels[$field] ?? $field,
            'Wert'  => $logValue
        ]);
        jsonResponse([
            'success' => true,
            'message' => 'Erfolgreich gespeichert'
        ]);
    } else {
        jsonResponse([
            'success' => false,
            'message' => 'Keine Änderung vorgenommen'
        ], 400);
    }
    
} catch (Exception $e) {
    logError('Update member failed', ['id' => $memberId, 'field' => $field, 'error' => $e->getMessage()]);
    jsonResponse([
        'success' => false,
        'message' => 'Fehler beim Speichern'
    ], 500);
}
