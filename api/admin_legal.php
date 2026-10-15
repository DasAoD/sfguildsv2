<?php
/**
 * Legal Settings API
 * Admin-only endpoint for Impressum/Datenschutz configuration
 */
require_once __DIR__ . '/../includes/bootstrap_api.php';
require_once __DIR__ . '/../includes/site_settings.php';

requireAdminAPI();

try {
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            jsonResponse(['success' => true, 'settings' => getLegalSettings()]);
            break;

        case 'PUT':
            try {
                $input = json_decode(file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $e) {
                jsonError('Ungültige JSON-Daten', 400);
            }
            if (!is_array($input)) {
                jsonError('Ungültige JSON-Daten', 400);
            }
            $email = trim((string)($input['imprint_email'] ?? ''));
            if ($email !== '' && (mb_strlen($email) > 120 || !filter_var($email, FILTER_VALIDATE_EMAIL))) {
                jsonError('Ungültige E-Mail-Adresse', 400);
            }
            if (!empty($input['imprint_enabled'])
                && (trim((string)($input['imprint_name'] ?? '')) === '' || trim((string)($input['imprint_street'] ?? '')) === ''
                    || trim((string)($input['imprint_city'] ?? '')) === '' || $email === '')) {
                jsonError('Für das Impressum sind Name, Straße, PLZ/Ort und E-Mail erforderlich', 400);
            }
            if (!empty($input['privacy_enabled']) && (trim((string)($input['imprint_name'] ?? '')) === '' || $email === '')) {
                jsonError('Für die Datenschutzerklärung sind Name und E-Mail erforderlich', 400);
            }
            saveLegalSettings($input);
            logActivity('Rechtliche Seiten aktualisiert');
            jsonResponse(['success' => true]);
            break;

        default:
            jsonError('Methode nicht erlaubt', 405);
    }
} catch (Throwable $e) {
    logError('admin_legal failed', ['error' => $e->getMessage()]);
    jsonError('Serverfehler', 500);
}
