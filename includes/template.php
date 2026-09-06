<?php
/**
 * Template Helper Functions
 * Common HTML components for all pages
 */

/**
 * Render HTML head with CSS links
 */
function renderHead($title, $additionalCSS = []) {
    $version = '20260906a'; // Update this when CSS changes
    $cssFiles = array_merge(['/assets/css/main.css', '/assets/css/overlay.css'], $additionalCSS);
    ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($title); ?> - S&F Guilds</title>
    <?php foreach ($cssFiles as $css): ?>
    <link rel="stylesheet" href="<?php echo $css . '?v=' . $version; ?>">
    <?php endforeach; ?>
    <?php
}

/**
 * Render navigation bar
 */
function renderNavbar($activePage = '', $options = []) {
    // Auto-detect active page if not provided
    if (empty($activePage)) {
        $currentPage = basename($_SERVER['PHP_SELF'], '.php');
        if ($currentPage === 'index') $activePage = 'dashboard';
        elseif ($currentPage === 'hellevator') $activePage = 'hellevator';
        elseif ($currentPage === 'fights') $activePage = 'battles';
        elseif ($currentPage === 'reports') $activePage = 'reports';
        elseif ($currentPage === 'guild_attack_check') $activePage = 'attack-check';
        elseif ($currentPage === 'admin') $activePage = 'admin';
    }
    
    $isLoggedIn = isLoggedIn();
    $currentUser = $isLoggedIn ? getCurrentUsername() : null;
    
// Get inbox count for logged in users
    $inboxCount = 0;
    if ($isLoggedIn) {
        try {
            require_once __DIR__ . '/../config/database.php';
            $db = getDB();
            $stmt = $db->prepare("
    SELECT COUNT(*) 
    FROM battle_inbox i
    LEFT JOIN sf_eval_battles b ON (
        b.message_id = i.message_id 
        OR (
            b.guild_id = i.guild_id 
            AND b.battle_date = i.battle_date 
            AND b.battle_time = i.battle_time 
            AND b.opponent_guild = i.opponent_guild
        )
    )
    WHERE i.user_id = ? 
    AND i.status = 'pending'
    AND b.id IS NULL
");
            $stmt->execute([$_SESSION['user_id']]);
            $inboxCount = $stmt->fetchColumn();
        } catch (Throwable $e) {
            // Ignore errors (table might not exist yet)
        }
    }
    ?>
    <nav class="navbar">
        <div class="container">
            <a href="/" class="navbar-brand">
                <div class="logo">
                    <img src="/assets/images/sf-logo.png" alt="Shakes & Fidget Logo" />
                </div>
                <span class="brand-text">S&F Guilds</span>
            </a>
            <div class="navbar-menu">
                <?php if (empty($options['hide_dashboard'])): ?>
                <a href="/" class="nav-item <?php echo $activePage === 'dashboard' ? 'active' : ''; ?>">Dashboard</a>
                <?php endif; ?>
                <a href="/hellevator.php" class="nav-item <?php echo $activePage === 'hellevator' ? 'active' : ''; ?>">Hellevator</a>
                <?php if ($isLoggedIn): ?>
                <a href="/fights.php" class="nav-item <?php echo $activePage === 'battles' ? 'active' : ''; ?>">Kämpfe</a>
                <a href="/inbox.php" class="nav-item <?php echo $activePage === 'inbox' ? 'active' : ''; ?>">
                    Posteingang
                    <?php if ($inboxCount > 0): ?>
                        <span style="background: var(--color-danger); color: white; padding: 0.125rem 0.5rem; border-radius: 1rem; font-size: 0.75rem; margin-left: 0.25rem;"><?php echo $inboxCount; ?></span>
                    <?php endif; ?>
                </a>
                <a href="/reports.php" class="nav-item <?php echo $activePage === 'reports' ? 'active' : ''; ?>">Reports</a>
                <a href="/guild_attack_check.php" class="nav-item <?php echo $activePage === 'attack-check' ? 'active' : ''; ?>">Angriffszeit</a>
                <?php if (isAdmin()): ?>
                <a href="/admin.php" class="nav-item <?php echo $activePage === 'admin' ? 'active' : ''; ?>">Admin</a>
                <?php endif; ?>
                <?php endif; ?>
            </div>
            <div class="user-menu">
                <?php if ($isLoggedIn): ?>
                <span class="user-name">Angemeldet als: <strong><?php echo e($currentUser); ?></strong></span>
                <span class="session-timer" id="sessionTimer" title="Verbleibende Sitzungszeit – wird bei jeder Aktion zurückgesetzt" hidden></span>
                <a href="/settings.php" class="btn-settings" title="Einstellungen">⚙️</a>
                <a href="/logout.php" class="btn-logout">Abmelden</a>
                <?php else: ?>
                <a href="/login.php?return=<?php echo e($_SERVER['REQUEST_URI']); ?>" class="btn-login">Anmelden</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>
    <?php
    // Session-Idle-Timer: Config + Loader auf JEDER Seite mit Navbar –
    // auch fights/hellevator/inbox/settings, die renderScripts() nicht aufrufen.
    if ($isLoggedIn && defined('SESSION_IDLE_TIMEOUT')) {
        $remaining = max(0, SESSION_IDLE_TIMEOUT - (time() - ($_SESSION['last_activity'] ?? time())));
        ?>
    <script>
    window.SF_SESSION = { timeout: <?php echo SESSION_IDLE_TIMEOUT; ?>, warning: <?php echo SESSION_IDLE_WARNING; ?>, remaining: <?php echo $remaining; ?> };
    </script>
    <script src="/assets/js/session-timeout.js?v=20260906b"></script>
    <?php
    }
}

/**
 * Render footer
 */
function renderFooter() {
    ?>
    <footer class="footer">
        <div class="container">
            &copy; <?php echo date('Y'); ?> S&F Guilds - Shakes & Fidget Gildenverwaltung
        </div>
    </footer>
    <?php
}

/**
 * Render script tags
 */
function renderScripts($additionalJS = []) {
    $version = '20260820a'; // Update this when JS changes
    // Hinweis: der Session-Idle-Timer wird in renderNavbar() geladen, damit er
    // auch auf Seiten ohne renderScripts() (fights/hellevator/inbox) greift.
    $jsFiles = array_merge(['/assets/js/overlay.js', '/assets/js/main.js'], $additionalJS);
    foreach ($jsFiles as $js): ?>
    <script src="<?php echo $js . '?v=' . $version; ?>"></script>
    <?php endforeach;
}
