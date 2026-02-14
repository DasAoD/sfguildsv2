<?php
/**
 * Login Page
 * User authentication interface
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/template.php';

// Get return URL (validate: nur interne Pfade)
$returnUrl = get('return', '/');
if (!str_starts_with($returnUrl, '/') || str_contains($returnUrl, '//')) {
    $returnUrl = '/';
}

// If already logged in, redirect
if (isLoggedIn()) {
    redirect($returnUrl);
}

?>
<!DOCTYPE html>
<html lang="de">
<head>
    <?php renderHead('Login', ['/assets/css/login.css']); ?>
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <div class="login-header">
                <img src="/assets/images/sf-logo.png" alt="S&F Guilds Logo" class="login-logo">
                <h1 class="login-title">S&F Guilds</h1>
                <p class="login-subtitle">Gildenverwaltung für Shakes & Fidget</p>
            </div>

            <div id="errorMessage"></div>

            <form id="loginForm" class="login-form">
                <input type="hidden" name="return" value="<?php echo e($returnUrl); ?>">
                
                <div class="form-group">
                    <label for="username">Benutzername</label>
                    <input type="text" id="username" name="username" required autofocus>
                </div>

                <div class="form-group">
                    <label for="password">Passwort</label>
                    <input type="password" id="password" name="password" required>
                </div>

                <button type="submit" class="btn-login-submit">Anmelden</button>
            </form>

            <div class="login-footer">
                <a href="/" class="btn-dashboard">← Zurück zum Dashboard</a>
            </div>
        </div>
    </div>

    <?php renderScripts(); ?>
    <script>
        document.getElementById('loginForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const formData = new FormData(e.target);
            const data = {
                username: formData.get('username'),
                password: formData.get('password'),
                return: formData.get('return')
            };

            try {
                const response = await fetch('/api/login.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });

                const result = await response.json();

                if (result.success) {
                    window.location.href = result.redirect || '/';
                } else {
                    const errorDiv = document.getElementById('errorMessage');
                    errorDiv.innerHTML = '<div class="alert alert-error">' + escapeHtml(result.message) + '</div>';
                }
            } catch (error) {
                const errorDiv = document.getElementById('errorMessage');
                errorDiv.innerHTML = '<div class="alert alert-error">Verbindungsfehler. Bitte versuche es erneut.</div>';
            }
        });
    </script>
</body>
</html>
