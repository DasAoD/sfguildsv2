<?php
/**
 * Change Password Page
 * Shown after login when must_change_password = 1
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/template.php';
require_once __DIR__ . '/../config/database.php';

checkAuth();

$userId = $_SESSION['user_id'];
$user = queryOne('SELECT username, must_change_password FROM users WHERE id = ?', [$userId]);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <?php renderHead('Passwort ändern'); ?>
    <style>
    .change-pw-container {
        max-width: 480px;
        margin: 4rem auto;
        padding: 0 1rem;
    }
    .change-pw-box {
        background: var(--color-surface);
        border: 1px solid var(--color-border);
        border-radius: 8px;
        padding: 2rem;
    }
    .change-pw-box h1 {
        margin-bottom: 0.5rem;
    }
    .change-pw-box .subtitle {
        color: var(--color-text-muted);
        margin-bottom: 1.5rem;
        font-size: 0.95rem;
    }
    .form-group {
        margin-bottom: 1.25rem;
    }
    .form-group label {
        display: block;
        margin-bottom: 0.4rem;
        font-weight: 500;
    }
    .form-group input {
        width: 100%;
        box-sizing: border-box;
    }
    .alert {
        padding: 0.75rem 1rem;
        border-radius: 6px;
        margin-bottom: 1rem;
    }
    .alert-error { background: var(--color-danger-bg, #3a1a1a); color: var(--color-danger, #f87171); }
    .alert-success { background: var(--color-success-bg, #1a3a1a); color: var(--color-success, #4ade80); }
    </style>
</head>
<body>
    <?php if (empty($user['must_change_password'])): ?>
        <?php renderHeader($user['username'] ?? '', $_SESSION['role'] ?? ''); ?>
    <?php endif; ?>

    <div class="change-pw-container">
        <div class="change-pw-box">
            <h1>Passwort ändern</h1>
            <?php if (!empty($user['must_change_password'])): ?>
                <p class="subtitle">⚠️ Du musst dein temporäres Passwort ändern bevor du fortfahren kannst.</p>
            <?php else: ?>
                <p class="subtitle">Hier kannst du dein Passwort ändern.</p>
            <?php endif; ?>

            <div id="message"></div>

            <form id="changePasswordForm">
                <div class="form-group">
                    <label for="current_password">Aktuelles Passwort</label>
                    <input type="password" id="current_password" name="current_password" required autocomplete="current-password">
                </div>
                <div class="form-group">
                    <label for="new_password">Neues Passwort</label>
                    <input type="password" id="new_password" name="new_password" required autocomplete="new-password" minlength="8">
                </div>
                <div class="form-group">
                    <label for="new_password_confirm">Neues Passwort bestätigen</label>
                    <input type="password" id="new_password_confirm" name="new_password_confirm" required autocomplete="new-password" minlength="8">
                </div>
                <button type="submit" class="btn btn-primary" style="width:100%">Passwort ändern</button>
            </form>
        </div>
    </div>

    <script>
    document.getElementById('changePasswordForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        const msgDiv = document.getElementById('message');
        msgDiv.innerHTML = '';

        const currentPw = document.getElementById('current_password').value;
        const newPw = document.getElementById('new_password').value;
        const confirmPw = document.getElementById('new_password_confirm').value;

        if (newPw !== confirmPw) {
            msgDiv.innerHTML = '<div class="alert alert-error">Die neuen Passwörter stimmen nicht überein.</div>';
            return;
        }

        if (newPw.length < 8) {
            msgDiv.innerHTML = '<div class="alert alert-error">Das neue Passwort muss mindestens 8 Zeichen lang sein.</div>';
            return;
        }

        try {
            const response = await fetch('/api/change_password.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ current_password: currentPw, new_password: newPw })
            });
            const result = await response.json();

            if (result.success) {
                msgDiv.innerHTML = '<div class="alert alert-success">Passwort erfolgreich geändert. Du wirst weitergeleitet…</div>';
                setTimeout(() => { window.location.href = '/'; }, 1500);
            } else {
                msgDiv.innerHTML = '<div class="alert alert-error">' + escapeHtml(result.message || result.error || 'Fehler') + '</div>';
            }
        } catch (err) {
            msgDiv.innerHTML = '<div class="alert alert-error">Verbindungsfehler. Bitte versuche es erneut.</div>';
        }
    });

    function escapeHtml(str) {
        const d = document.createElement('div');
        d.appendChild(document.createTextNode(str));
        return d.innerHTML;
    }
    </script>
</body>
</html>