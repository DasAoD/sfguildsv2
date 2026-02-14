<?php
/**
 * Reports Page
 * Battle participation statistics per player
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/template.php';

// Require login for this page
checkAuth();

$isLoggedIn = isLoggedIn();
$guildId = isset($_GET['id']) ? (int)$_GET['id'] : 1;
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <?php renderHead('Reports', ['/assets/css/reports.css']); ?>
</head>
<body>
    <?php renderNavbar(); ?>

    <main class="main-content">
        <div class="container">
            <div class="guild-tabs" id="guildTabs">
                <!-- Tabs loaded via JS -->
            </div>

            <div class="page-header">
                <div style="display:flex;align-items:center;gap:1.5rem">
                    <div id="guildCrest" style="width:100px;height:100px;display:flex;align-items:center;justify-content:center;font-size:5rem"></div>
                    <div>
                        <h1 class="guild-title" id="guildName">Lädt...</h1>
                        <div class="guild-server" id="guildServer"></div>
                    </div>
                </div>
            </div>

            <div class="report-layout">
                <div class="report-main">
                    <div class="stats-grid" id="statsGrid"></div>

                    <div class="table-container">
                        <h3>Spieler (kompakt)</h3>
                        <table id="statsTable">
                            <thead>
                                <tr>
                                    <th>Spieler</th>
                                    <th class="center">Angriffe</th>
                                    <th class="center">Verteidigungen</th>
                                    <th class="center">Raids</th>
                                    <th class="center">Gesamt</th>
                                    <th>Quote</th>
                                </tr>
                            </thead>
                            <tbody id="tableBody">
                                <tr><td colspan="6" class="loading">Lade Statistiken...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="report-sidebar">
                    <div class="sidebar-section">
                        <h3>Auffälligkeiten</h3>
                        
                        <div class="sidebar-card">
                            <h4>Top Nicht teilgenommen</h4>
                            <div id="topMissing"></div>
                        </div>

                        <div class="sidebar-card">
                            <h4>Verteilung</h4>
                            <div id="distribution"></div>
                        </div>

                        <div class="sidebar-card">
                            <h4>Inaktiv (ab 7 Tagen)</h4>
                            <div id="inactive"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <?php renderFooter(); ?>

    <script>
        const isLoggedIn = <?php echo $isLoggedIn ? 'true' : 'false'; ?>;
        const guildId = <?php echo $guildId; ?>;
    </script>
    
    <?php renderScripts(['/assets/js/reports.js']); ?>
</body>
</html>
