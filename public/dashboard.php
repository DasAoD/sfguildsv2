<?php
/**
 * Dashboard Page
 * Guild overview with statistics
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Dashboard is publicly accessible
$isLoggedIn = isLoggedIn();
$pageTitle = 'Dashboard';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - S&F Guilds</title>
    <style>
        :root {
            --color-primary: #ff6b35;
            --color-secondary: #4ecdc4;
            --color-success: #4ade80;
            --color-warning: #fbbf24;
            --color-danger: #ef4444;
            --color-bg-primary: #0f0f0f;
            --color-bg-secondary: #1a1a1a;
            --color-bg-tertiary: #252525;
            --color-bg-hover: #2a2a2a;
            --color-text-primary: #ffffff;
            --color-text-secondary: #a0a0a0;
            --color-text-muted: #666666;
            --color-border: #333333;
            --spacing-xs: 0.5rem;
            --spacing-sm: 0.75rem;
            --spacing-md: 1rem;
            --spacing-lg: 1.5rem;
            --spacing-xl: 2rem;
            --spacing-2xl: 3rem;
            --radius-md: 0.5rem;
            --radius-lg: 0.75rem;
            --radius-xl: 1rem;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: var(--color-bg-primary);
            color: var(--color-text-primary);
            line-height: 1.6;
            min-height: 100vh;
        }

        .container {
            width: 100%;
            max-width: 100%;
            margin: 0 auto;
            padding: 0 var(--spacing-lg);
        }

        /* Navigation */
        .navbar {
            background-color: rgba(26, 26, 26, 0.95);
            border-bottom: 1px solid var(--color-border);
            padding: var(--spacing-md) 0;
            position: sticky;
            top: 0;
            z-index: 100;
            backdrop-filter: blur(10px);
        }

        .navbar .container {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: var(--spacing-xl);
            flex-wrap: wrap;
        }

        .navbar-brand {
            display: flex;
            align-items: center;
            gap: var(--spacing-md);
        }

        .logo {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--color-primary), var(--color-secondary));
            border-radius: var(--radius-md);
        }

        .brand-text {
            font-size: 1.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--color-primary), var(--color-secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .navbar-menu {
            display: flex;
            gap: var(--spacing-sm);
            flex: 1;
        }

        .nav-item {
            padding: var(--spacing-sm) var(--spacing-md);
            border-radius: var(--radius-md);
            color: var(--color-text-secondary);
            transition: all 0.25s;
            font-weight: 500;
            text-decoration: none;
        }

        .nav-item:hover {
            background-color: var(--color-bg-hover);
            color: var(--color-text-primary);
        }

        .nav-item.active {
            background-color: var(--color-primary);
            color: white;
        }

        .user-menu {
            display: flex;
            align-items: center;
            gap: var(--spacing-md);
        }

        .user-name {
            color: var(--color-text-secondary);
            font-size: 0.875rem;
        }

        .btn-logout {
            padding: var(--spacing-sm) var(--spacing-md);
            background-color: rgba(239, 68, 68, 0.1);
            color: var(--color-danger);
            border: none;
            border-radius: var(--radius-md);
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.25s;
        }

        .btn-logout:hover {
            background-color: var(--color-danger);
            color: white;
        }

        .btn-login {
            padding: var(--spacing-sm) var(--spacing-md);
            background: linear-gradient(135deg, var(--color-primary), #ff8c5a);
            border: none;
            border-radius: var(--radius-md);
            color: white;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.25s;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(255, 107, 53, 0.4);
        }

        /* Main Content */
        .main-content {
            padding: var(--spacing-2xl) 0;
        }

        .page-header {
            margin-bottom: var(--spacing-2xl);
        }

        .page-title {
            font-size: 2rem;
            font-weight: 800;
            margin-bottom: var(--spacing-xs);
        }

        .page-subtitle {
            color: var(--color-text-secondary);
        }

        /* Guild Cards */
        .guilds-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: var(--spacing-xl);
            margin-bottom: var(--spacing-2xl);
        }

        .guild-card {
            background-color: var(--color-bg-secondary);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-xl);
            padding: var(--spacing-xl);
            transition: all 0.25s;
            cursor: pointer;
        }

        .guild-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.5);
            border-color: var(--color-primary);
        }

        .guild-header {
            display: flex;
            align-items: center;
            gap: var(--spacing-md);
            margin-bottom: var(--spacing-lg);
        }

        .guild-crest {
            width: 60px;
            height: 60px;
            border-radius: var(--radius-lg);
            background: linear-gradient(135deg, var(--color-bg-tertiary), var(--color-bg-hover));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            flex-shrink: 0;
        }

        .guild-info {
            flex: 1;
        }

        .guild-name {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }

        .guild-server {
            color: var(--color-text-secondary);
            font-size: 0.875rem;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .status-badge.active {
            background-color: rgba(74, 222, 128, 0.1);
            color: var(--color-success);
        }

        .status-badge.warning {
            background-color: rgba(251, 191, 36, 0.1);
            color: var(--color-warning);
        }

        .status-badge.inactive {
            background-color: rgba(160, 160, 160, 0.1);
            color: var(--color-text-secondary);
        }

        .guild-stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: var(--spacing-md);
            margin-bottom: var(--spacing-lg);
        }

        .stat {
            display: flex;
            flex-direction: column;
        }

        .stat-label {
            color: var(--color-text-secondary);
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.25rem;
        }

        .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
        }

        .guild-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: var(--spacing-md);
            border-top: 1px solid var(--color-border);
        }

        .last-update {
            color: var(--color-text-muted);
            font-size: 0.75rem;
        }

        .btn-details {
            padding: 0.5rem var(--spacing-md);
            background-color: var(--color-primary);
            border: none;
            border-radius: var(--radius-md);
            color: white;
            font-weight: 600;
            font-size: 0.875rem;
            text-decoration: none;
            transition: all 0.25s;
        }

        .btn-details:hover {
            background-color: #ff8c5a;
        }

        .loading {
            text-align: center;
            padding: var(--spacing-2xl);
            color: var(--color-text-secondary);
        }

        /* Footer */
        .footer {
            background-color: var(--color-bg-secondary);
            border-top: 1px solid var(--color-border);
            padding: var(--spacing-xl) 0;
            margin-top: var(--spacing-2xl);
            text-align: center;
            color: var(--color-text-secondary);
            font-size: 0.875rem;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="container">
            <div class="navbar-brand">
                <div class="logo"></div>
                <span class="brand-text">S&F Guilds</span>
            </div>
            <div class="navbar-menu">
                <a href="/dashboard.php" class="nav-item active">Dashboard</a>
                <?php if ($isLoggedIn): ?>
                <a href="/calendar.php" class="nav-item">Kämpfe</a>
                <a href="/reports.php" class="nav-item">Reports</a>
                <a href="/admin.php" class="nav-item">Admin</a>
                <?php endif; ?>
            </div>
            <div class="user-menu">
                <?php if ($isLoggedIn): ?>
                <span class="user-name">Angemeldet als: <strong><?php echo e(getCurrentUsername()); ?></strong></span>
                <a href="/logout.php" class="btn-logout">Abmelden</a>
                <?php else: ?>
                <a href="/index.php" class="btn-login">Anmelden</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="main-content">
        <div class="container">
            <div class="page-header">
                <h1 class="page-title">Gilden-Übersicht</h1>
                <p class="page-subtitle">Verwalte deine Shakes & Fidget Gilden</p>
            </div>

            <div id="guildsGrid" class="guilds-grid">
                <div class="loading">Lade Gilden...</div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            &copy; <?php echo date('Y'); ?> S&F Guilds - Shakes & Fidget Gildenverwaltung
        </div>
    </footer>

    <script>
        // Guild crest emojis
        const guildCrests = {
            'Blutzirkel': '🔴',
            'Equilibrium': '🟣',
            'Glücksbärchis': '🐻',
            'Gurkistan': '🥒'
        };

        // Load guilds from API
        async function loadGuilds() {
            try {
                const response = await fetch('/api/guilds.php');
                const data = await response.json();

                if (data.success) {
                    renderGuilds(data.guilds);
                } else {
                    document.getElementById('guildsGrid').innerHTML = 
                        '<div class="loading">Fehler beim Laden der Gilden</div>';
                }
            } catch (error) {
                console.error('Error loading guilds:', error);
                document.getElementById('guildsGrid').innerHTML = 
                    '<div class="loading">Fehler beim Laden der Gilden</div>';
            }
        }

        function renderGuilds(guilds) {
            const grid = document.getElementById('guildsGrid');
            
            if (guilds.length === 0) {
                grid.innerHTML = '<div class="loading">Keine Gilden gefunden</div>';
                return;
            }

            grid.innerHTML = guilds.map(guild => `
                <div class="guild-card" onclick="location.href='/guild.php?id=${guild.id}'">
                    <div class="guild-header">
                        <div class="guild-crest">${guildCrests[guild.name] || '⚔️'}</div>
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
                            <div class="stat-label">Teilnahme (30 T)</div>
                            <div class="stat-value">${guild.participation_quote}%</div>
                        </div>
                        <div class="stat">
                            <div class="stat-label">Kämpfe</div>
                            <div class="stat-value">${guild.total_battles}</div>
                        </div>
                    </div>

                    <div class="guild-footer">
                        <div class="last-update">
                            ${guild.last_update ? 'Aktualisiert: ' + formatDateTime(guild.last_update) : 'Noch nie aktualisiert'}
                        </div>
                        <a href="/guild.php?id=${guild.id}" class="btn-details" onclick="event.stopPropagation()">Details →</a>
                    </div>
                </div>
            `).join('');
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function formatDateTime(datetime) {
            if (!datetime) return '—';
            const date = new Date(datetime);
            return date.toLocaleDateString('de-DE', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric'
            }) + ' ' + date.toLocaleTimeString('de-DE', {
                hour: '2-digit',
                minute: '2-digit'
            });
        }

        // Load guilds on page load
        loadGuilds();
    </script>
</body>
</html>
