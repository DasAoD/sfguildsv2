<?php
/**
 * Guild Detail Page
 * Member list and guild statistics
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/template.php';

$isLoggedIn = isLoggedIn();
require_once __DIR__ . '/../config/database.php';

// Same order as the guild tabs (api/guilds.php), so the first tab is preselected
$firstGuild = queryOne('SELECT id FROM guilds ORDER BY name LIMIT 1');
$guildId = isset($_GET['id']) ? (int)$_GET['id'] : (int)($firstGuild['id'] ?? 1);
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
                <div class="guild-head">
                    <div class="guild-head-main">
                        <div id="guildCrest"></div>
                        <div>
                            <h1 class="guild-title" id="guildName">Lädt...</h1>
                            <div class="guild-server" id="guildServer"></div>
                            <div class="guild-notes" id="guildNotes" style="display:none;color:var(--color-text-secondary);font-size:0.875rem;margin-top:0.25rem"></div>
                        </div>
                    </div>
                    <div class="guild-head-actions">
                        <button onclick="syncMembers()" class="btn btn-secondary" id="syncBtn" style="display:none">⟳ Mitglieder synchronisieren</button>
                    </div>
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

    <!-- Character Modal -->
    <div id="characterModal" class="modal">
        <div class="modal-content" style="max-width: 560px;">
            <div class="modal-header">
                <h3 id="characterModalTitle">Charakter</h3>
                <button class="modal-close" onclick="closeCharacterModal()">&times;</button>
            </div>
            <div class="modal-body" id="characterModalBody">
                <div class="loading">Lade…</div>
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

    <?php renderScripts(['/assets/js/portrait.js', '/assets/js/guild.js']); ?>
</body>
</html>
