<?php
/**
 * Betreiber-Einstellungen (Impressum/Datenschutz), im Admin-Bereich pflegbar.
 */
require_once __DIR__ . '/../config/database.php';

const LEGAL_FIELDS = [
    'imprint_enabled' => 1,
    'privacy_enabled' => 1,
    'imprint_name'    => 120,
    'imprint_street'  => 120,
    'imprint_city'    => 120,
    'imprint_phone'   => 60,
    'imprint_email'   => 120,
];

function getLegalSettings(): array {
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $cache = array_fill_keys(array_keys(LEGAL_FIELDS), '');
    if (!is_file(DB_PATH)) {
        return $cache;
    }
    try {
        getDB()->exec('CREATE TABLE IF NOT EXISTS site_settings (key TEXT PRIMARY KEY, value TEXT NOT NULL)');
        foreach (query('SELECT key, value FROM site_settings') as $row) {
            if (array_key_exists($row['key'], $cache)) {
                $cache[$row['key']] = $row['value'];
            }
        }
    } catch (PDOException $e) {
        error_log('site_settings: ' . $e->getMessage());
    }
    return $cache;
}

function saveLegalSettings(array $input): void {
    getLegalSettings();
    $db = getDB();
    $db->beginTransaction();
    try {
        foreach (LEGAL_FIELDS as $key => $max) {
            $value = $max === 1
                ? (empty($input[$key]) ? '0' : '1')
                : mb_substr(trim((string)($input[$key] ?? '')), 0, $max);
            execute('INSERT INTO site_settings (key, value) VALUES (?, ?)
                     ON CONFLICT(key) DO UPDATE SET value = excluded.value', [$key, $value]);
        }
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
}

function legalPageActive(string $page): bool {
    return (getLegalSettings()[$page . '_enabled'] ?? '0') === '1';
}
