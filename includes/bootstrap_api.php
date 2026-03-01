<?php
/**
 * API Bootstrap
 * Lädt alle gemeinsamen Dependencies für API-Endpunkte.
 * Jeder Endpoint in api/ sollte dies als erstes require_once einbinden.
 *
 * Ausnahmen (kein Bootstrap):
 *   - api/login.php         (kein checkAuth() nötig)
 *   - api/sf_fetch_single.php (Subprocess, kein HTTP-Kontext)
 *
 * Spezielle Includes bleiben im jeweiligen Endpoint:
 *   - includes/encryption.php  (sf_get_characters, sf_account_manage)
 *   - includes/sf_helpers.php  (sf_fetch_*, inbox_*, sf_save_account)
 *   - includes/raid_names.php  (guilds, members)
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/logger.php';
require_once __DIR__ . '/../includes/auth.php';
