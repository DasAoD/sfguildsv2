<?php
/**
 * Guild Detail Page
 * Member list and guild statistics
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/template.php';

$isLoggedIn = isLoggedIn();
$guildId = isset($_GET['id']) ? (int)$_GET['id'] : 1;
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <?php renderHead('Gilde', ['/assets/css/guild.css']); ?>
</head>
<body>
    <?php renderNavbar(); ?>

    <main class="main-content">
        <div class="container">
            <div class="guild-tabs" id="guildTabs">
                <!-- Tabs loaded via JS -->
            </div>

            <div class="page-header">
                <div style="display:flex;align-items:center;justify-content:space-between;width:100%">
                    <div style="display:flex;align-items:center;gap:1.5rem">
                        <div id="guildCrest" style="width:100px;height:100px;display:flex;align-items:center;justify-content:center;font-size:5rem"></div>
                        <div>
                            <h1 class="guild-title" id="guildName">Lädt...</h1>
                            <div class="guild-server" id="guildServer"></div>
                        </div>
                    </div>
                    <button onclick="openImportModal()" class="btn btn-primary" id="importBtn" style="display:none">+ Mitglieder importieren</button>
                </div>
            </div>

            <div class="stats-grid" id="statsGrid"></div>

            <div class="table-container">
                <table id="membersTable">
                    <thead id="tableHead"></thead>
                    <tbody id="tableBody">
                        <tr><td colspan="13" class="loading">Lade Mitglieder...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- Import Modal -->
    <div id="importModal" class="modal">
        <div class="modal-content" style="max-width: 600px;">
            <div class="modal-header">
                <h3>Mitglieder importieren (CSV)</h3>
                <button class="modal-close" onclick="closeImportModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="importForm">
                    <div class="form-group">
                        <label for="csvFile">CSV-Datei auswählen</label>
                        <input type="file" id="csvFile" name="file" accept=".csv" required class="form-input">
                        <small style="color: var(--color-text-secondary); display: block; margin-top: var(--spacing-xs);">
                            Exportiere die Gildenliste von <a href="https://sftools.mar21.eu" target="_blank" style="color: var(--color-primary)">sftools.mar21.eu</a> als CSV
                        </small>
                    </div>
                    <div style="display: flex; gap: var(--spacing-md); justify-content: flex-end; margin-top: var(--spacing-lg);">
                        <button type="button" class="btn-small" onclick="closeImportModal()">Abbrechen</button>
                        <button type="submit" class="btn-small btn-primary">Importieren</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php renderFooter(); ?>

    <script>
        // Define variables BEFORE guild.js loads
        const isLoggedIn = <?php echo $isLoggedIn ? 'true' : 'false'; ?>;
        const guildId = <?php echo $guildId; ?>;
        let currentMembers = [];
    </script>
    
    <?php renderScripts(['/assets/js/guild.js']); ?>
</body>
</html>
