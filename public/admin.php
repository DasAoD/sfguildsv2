<?php
/**
 * Admin Page
 * Management interface for users, guilds, and system
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/template.php';

checkAuth();
requireAdmin();

$isLoggedIn = isLoggedIn();
$currentUser = getCurrentUsername();
$currentUserRole = getCurrentUserRole();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <?php renderHead('Admin', ['/assets/css/admin.css']); ?>
</head>
<body>
    <?php renderNavbar('admin'); ?>

    <div class="container">
        <div class="page-header">
            <h1>Admin-Bereich</h1>
            <p class="subtitle">Verwaltung von Benutzern, Gilden und System</p>
        </div>

        <div id="alertContainer"></div>

        <div class="tabs">
            <button class="tab active" onclick="switchTab('users')">Benutzer</button>
            <button class="tab" onclick="switchTab('guilds')">Gilden</button>
            <button class="tab" onclick="switchTab('logs')">Logs</button>
            <button class="tab" onclick="switchTab('maintenance')">Wartung</button>
            <button class="tab" onclick="switchTab('system')">System</button>
        </div>

        <!-- Users Tab -->
        <div id="tab-users" class="tab-content active">
            <div class="section">
                <div class="section-header">
                    <h2 class="section-title">Benutzerverwaltung</h2>
                    <button class="btn btn-primary" onclick="openUserModal()">+ Neuer Benutzer</button>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Benutzername</th>
                            <th>Rolle</th>
                            <th>Erstellt am</th>
                            <th>Aktionen</th>
                        </tr>
                    </thead>
                    <tbody id="usersTable">
                        <tr><td colspan="4" class="loading">Lädt...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Guilds Tab -->
        <div id="tab-guilds" class="tab-content">
            <div class="section">
                <div class="section-header">
                    <h2 class="section-title">Gildenverwaltung</h2>
                    <button class="btn btn-primary" onclick="openGuildModal()">+ Neue Gilde</button>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Wappen</th>
                            <th>Name</th>
                            <th>Server</th>
                            <th>Tag</th>
                            <th>Notizen</th>
                            <th>Aktionen</th>
                        </tr>
                    </thead>
                    <tbody id="guildsTable">
                        <tr><td colspan="6" class="loading">Lädt...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Logs Tab -->
        <div id="tab-logs" class="tab-content">
            <div class="section">
                <div class="section-header">
                    <h2 class="section-title">Anwendungs-Logs</h2>
                    <div class="log-controls">
                        <div class="log-toggle">
                            <button class="btn btn-toggle active" id="btnLogActivity" onclick="switchLogType('activity')">📋 Aktivitäten</button>
                            <button class="btn btn-toggle" id="btnLogError" onclick="switchLogType('error')">⚠️ Fehler</button>
                        </div>
                    </div>
                </div>

                <div class="log-toolbar">
                    <div class="log-toolbar-left">
                        <input type="text" id="logFilter" placeholder="Filter..." class="log-filter-input" oninput="filterLogsDebounced()">
                        <select id="logLines" class="log-lines-select" onchange="loadLogs()">
                            <option value="50">50 Einträge</option>
                            <option value="100" selected>100 Einträge</option>
                            <option value="200">200 Einträge</option>
                            <option value="500">500 Einträge</option>
                        </select>
                    </div>
                    <div class="log-toolbar-right">
                        <span id="logInfo" class="log-info-text"></span>
                        <button class="btn btn-secondary btn-sm" onclick="loadLogs()">🔄 Aktualisieren</button>
                        <button class="btn btn-danger btn-sm" onclick="clearCurrentLog()">🗑️ Leeren</button>
                    </div>
                </div>

                <div class="log-container" id="logContainer">
                    <div class="log-empty">Log wird geladen...</div>
                </div>
            </div>
        </div>

        <!-- Maintenance Tab -->
        <div id="tab-maintenance" class="tab-content">
            <div class="section">
                <h2 class="section-title">Spieler umbenennen</h2>
                <p style="color:var(--color-text-secondary);margin-bottom:var(--spacing-lg)">
                    Führt zwei Spielereinträge zusammen, z.B. nach einer Umbenennung im Spiel. Alle Kampfeinträge des alten Namens werden auf den neuen Namen übertragen und der alte Mitgliedseintrag wird entfernt.
                </p>
                <div style="display:flex;flex-direction:column;gap:var(--spacing-md);max-width:500px;">
                    <div class="form-group">
                        <label>Gilde</label>
                        <select id="renameGuild" onchange="loadGuildMembers()" style="width:100%;padding:0.5rem;background:var(--color-bg-primary);color:var(--color-text-primary);border:1px solid var(--color-border);border-radius:var(--radius-sm);">
                            <option value="">-- Gilde wählen --</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Alter Name (wird entfernt)</label>
                        <select id="renameOldName" style="width:100%;padding:0.5rem;background:var(--color-bg-primary);color:var(--color-text-primary);border:1px solid var(--color-border);border-radius:var(--radius-sm);">
                            <option value="">-- Erst Gilde wählen --</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Neuer Name (bleibt bestehen)</label>
                        <select id="renameNewName" style="width:100%;padding:0.5rem;background:var(--color-bg-primary);color:var(--color-text-primary);border:1px solid var(--color-border);border-radius:var(--radius-sm);">
                            <option value="">-- Erst Gilde wählen --</option>
                        </select>
                    </div>
                    <div>
                        <button class="btn btn-primary" onclick="executeRename()">Umbenennen</button>
                    </div>
                </div>
                <div id="renameResult" style="margin-top:var(--spacing-lg);"></div>
            </div>
        </div>

        <!-- System Tab -->
        <div id="tab-system" class="tab-content">
            <div class="section">
                <h2 class="section-title">System-Informationen</h2>
                <div class="info-grid" id="systemInfo">
                    <div class="info-card"><div class="info-label">PHP Version</div><div class="info-value">-</div></div>
                    <div class="info-card"><div class="info-label">SQLite Version</div><div class="info-value">-</div></div>
                    <div class="info-card"><div class="info-label">DB Größe</div><div class="info-value">-</div></div>
                    <div class="info-card"><div class="info-label">Freier Speicher</div><div class="info-value">-</div></div>
                    <div class="info-card"><div class="info-label">Benutzer</div><div class="info-value">-</div></div>
                    <div class="info-card"><div class="info-label">Gilden</div><div class="info-value">-</div></div>
                    <div class="info-card"><div class="info-label">Mitglieder</div><div class="info-value">-</div></div>
                    <div class="info-card"><div class="info-label">Kämpfe</div><div class="info-value">-</div></div>
                </div>
            </div>
            <div class="section">
                <h2 class="section-title">Datenbank-Backup</h2>
                <p style="color:var(--color-text-secondary);margin-bottom:var(--spacing-lg)">Erstelle ein Backup der gesamten Datenbank. Die Datei wird mit Zeitstempel heruntergeladen.</p>
                <button class="btn btn-success" onclick="downloadBackup()">📥 Backup herunterladen</button>
            </div>
        </div>
    </div>

    <!-- User Modal -->
    <div id="userModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="userModalTitle">Neuer Benutzer</h3>
                <button class="btn-close" onclick="closeUserModal()">×</button>
            </div>
            <form id="userForm">
                <input type="hidden" id="userId">
                <div class="form-group">
                    <label>Benutzername</label>
                    <input type="text" id="username" required>
                </div>
                <div class="form-group">
                    <label>Rolle</label>
                    <select id="userRole" style="width:100%;padding:0.5rem;background:var(--color-bg-primary);color:var(--color-text-primary);border:1px solid var(--color-border);border-radius:var(--radius-sm);">
                        <option value="user">User</option>
                        <option value="moderator">Moderator</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <div class="form-group" id="passwordGroup">
                    <label id="passwordLabel">Passwort</label>
                    <input type="password" id="password">
                    <small id="passwordHint" style="color:var(--color-text-secondary);display:none;margin-top:0.25rem">Leer lassen um Passwort nicht zu ändern</small>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeUserModal()">Abbrechen</button>
                    <button type="submit" class="btn btn-primary">Speichern</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Guild Modal -->
    <div id="guildModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="guildModalTitle">Neue Gilde</h3>
                <button class="btn-close" onclick="closeGuildModal()">×</button>
            </div>
            <form id="guildForm" enctype="multipart/form-data">
                <input type="hidden" id="guildId">
                <input type="hidden" id="currentCrest">
                <div class="form-group">
                    <label>Gildenname</label>
                    <input type="text" id="guildName" required>
                </div>
                <div class="form-group">
                    <label>Server</label>
                    <input type="text" id="guildServer" required>
                </div>
                <div class="form-group">
                    <label>Tag (optional)</label>
                    <input type="text" id="guildTag">
                </div>
                <div class="form-group">
                    <label>Notizen (optional)</label>
                    <input type="text" id="guildNotes">
                </div>
                <div class="form-group">
                    <label>Wappen (optional)</label>
                    <div id="currentCrestPreview" style="margin-bottom:0.5rem"></div>
                    <input type="file" id="guildCrest" accept="image/*">
                    <small style="color:var(--color-text-secondary);display:block;margin-top:0.5rem">Unterstützte Formate: PNG, JPG, GIF, BMP, WEBP · max. 2.5MB (wird zu WEBP konvertiert)</small>
                    <div id="guildCrestError" style="display:none;color:var(--color-error);font-size:0.875rem;margin-top:0.4rem"></div>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeGuildModal()">Abbrechen</button>
                    <button type="submit" class="btn btn-primary">Speichern</button>
                </div>
            </form>
        </div>
    </div>

    <?php renderScripts(['/assets/js/admin.js']); ?>
    <script>
        // Set current user for client-side logic
        const CURRENT_USER = '<?php echo e($currentUser); ?>';
        const CURRENT_USER_ROLE = '<?php echo e($currentUserRole); ?>';
    </script>
</body>
</html>
