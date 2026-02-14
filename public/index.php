<?php
/**
 * Dashboard Page
 * Guild overview with statistics
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/template.php';

$isLoggedIn = isLoggedIn();
$pageTitle = 'Dashboard';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <?php renderHead($pageTitle, ['/assets/css/dashboard.css']); ?>
</head>
<body>
    <?php renderNavbar('dashboard'); ?>

    <main class="main-content">
        <div class="container">
            <div class="page-header">
                <h1 class="page-title">Gilden-Übersicht</h1>
                <p class="page-subtitle">Verwalte deine Shakes & Fidget Gilden</p>
            </div>

            <div class="guilds-grid" id="guildsGrid">
                <div class="loading">Lade Gilden...</div>
            </div>
        </div>
    </main>

    <?php renderFooter(); ?>

    <?php renderScripts(); ?>
    <script>
        // Load guilds from API
        async function loadGuilds() {
            try {
                const response = await fetch('/api/guilds.php');
                const data = await response.json();

                if (data.success) {
                    renderGuilds(data.guilds);
                } else {
                    document.getElementById('guildsGrid').innerHTML = '<div class="loading">Fehler beim Laden der Gilden</div>';
                }
            } catch (error) {
                console.error('Error loading guilds:', error);
                document.getElementById('guildsGrid').innerHTML = '<div class="loading">Fehler beim Laden der Gilden</div>';
            }
        }

        function renderGuilds(guilds) {
            const grid = document.getElementById('guildsGrid');
            
            if (guilds.length === 0) {
                grid.innerHTML = '<div class="loading">Keine Gilden gefunden</div>';
                return;
            }

            grid.innerHTML = guilds.map(guild => {
                const crestImg = guild.crest_file 
                    ? `<img src="/assets/images/${guild.crest_file}" alt="${escapeHtml(guild.name)} Wappen" style="width:100%;height:100%;object-fit:contain">`
                    : '<img src="/assets/images/helmet.png" alt="Wappen" style="width:3rem;height:3rem;object-fit:contain">';
                return `
                <div class="guild-card" onclick="location.href='/guild.php?id=${guild.id}'">
                    <div class="guild-header">
                        <div class="guild-crest">${crestImg}</div>
                        <div class="guild-info">
                            <div class="guild-name">${escapeHtml(guild.name)}</div>
                            <div class="guild-server">${escapeHtml(guild.server)}</div>
                        </div>
                    </div>

                    <div class="guild-stats">
                        <div class="stat">
                            <div class="stat-label">Mitglieder</div>
                            <div class="stat-value">${guild.active_members}</div>
                        </div>
                        <div class="stat">
                            <div class="stat-label">Level Ø</div>
                            <div class="stat-value">${guild.avg_level}</div>
                        </div>
                        <div class="stat">
                            <div class="stat-label">Ritterhalle</div>
                            <div class="stat-value">${guild.knight_hall_total || 0}</div>
                        </div>
                        <?php if ($isLoggedIn): ?>
                        <div class="stat">
                            <div class="stat-label">Kämpfe</div>
                            <div class="stat-value">${guild.total_battles}</div>
                        </div>
                        <div class="stat">
                            <div class="stat-label">Teilnahme (30 T)</div>
                            <div class="stat-value">${guild.participation_quote}%</div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="guild-footer">
                        <div class="last-update">
                            ${guild.last_update ? 'Aktualisiert: ' + formatDateTime(guild.last_update) : 'Noch nie aktualisiert'}
                        </div>
                        <a href="/guild.php?id=${guild.id}" class="btn-details" onclick="event.stopPropagation()">Details →</a>
                    </div>
                </div>
                `;
            }).join('');
        }

        // Load guilds on page load
        loadGuilds();
    </script>
</body>
</html>
