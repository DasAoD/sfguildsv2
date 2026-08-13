<?php
/**
 * Gilden-Angriffszeit-Check
 * Fragt live beim Spiel ab, wann eine beliebige Gilde angegriffen wird
 * (server-/account-/gildenübergreifend) — siehe api/guild_attack_time.php.
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/template.php';
require_once __DIR__ . '/../includes/sf_helpers.php';
require_once __DIR__ . '/../config/database.php';

checkAuth();

$db = getDB();
$servers = listKnownServersForUser($db, (int)$_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <?php renderHead('Angriffszeit-Check'); ?>
</head>
<body>
    <?php renderNavbar('attack-check'); ?>

    <main class="main-content">
        <div class="container">
            <div class="page-header">
                <h1>🎯 Angriffszeit-Check</h1>
                <p class="page-description">
                    Fragt live beim Spiel ab, wann eine Gilde angegriffen wird — für beliebige Gilden,
                    Server und deine hinterlegten Accounts.
                </p>
            </div>

            <div id="alertContainer"></div>

            <div class="card" style="max-width: 560px;">
                <div class="card-header">
                    <h2>Gilde abfragen</h2>
                </div>
                <div class="card-body">
                    <?php if (empty($servers)): ?>
                        <p>Du hast noch keine eigenen Charaktere hinterlegt. Bitte zuerst in den
                           <a href="/settings.php">Einstellungen</a> einen S&F-Account mit Charakteren verknüpfen.</p>
                    <?php else: ?>
                        <div class="form-group">
                            <label for="serverSelect">Server</label>
                            <select id="serverSelect">
                                <?php foreach ($servers as $s): ?>
                                    <option value="<?php echo e($s); ?>"><?php echo e(strtoupper(strtok($s, '.'))); ?> (<?php echo e($s); ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="targetGuildInput">Gildenname</label>
                            <input type="text" id="targetGuildInput" placeholder="z.B. Blutzirkel" maxlength="64"
                                   onkeydown="if (event.key === 'Enter') checkAttackTime();">
                        </div>
                        <button class="btn btn-primary" id="checkBtn" onclick="checkAttackTime()">Abfragen</button>

                        <div id="attackResult" style="margin-top: 1.5rem;"></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <?php renderFooter(); ?>

    <script>
    async function checkAttackTime() {
        const server = document.getElementById('serverSelect').value;
        const targetGuild = document.getElementById('targetGuildInput').value.trim();
        const resultBox = document.getElementById('attackResult');
        const btn = document.getElementById('checkBtn');

        if (!targetGuild) {
            showAlert('Bitte einen Gildennamen eingeben.', 'error');
            return;
        }

        btn.disabled = true;
        resultBox.innerHTML = '';
        showOverlay('Frage live beim Spiel ab…');

        try {
            const d = await fetchJSON('/api/guild_attack_time.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ server, target_guild: targetGuild }),
            });

            if (!d.success) {
                resultBox.innerHTML = `<div class="alert alert-error">${escapeHtml(d.error || d.message || 'Abfrage fehlgeschlagen')}</div>`;
                return;
            }

            if (d.attacked_at) {
                // Das Spiel zeigt Kampfzeiten selbst in UTC an (siehe Kommentar in
                // guild_battle_time.rs) — bewusst NICHT toLocaleString() ohne
                // timeZone nutzen, das würde in die Browser-Zeitzone umrechnen
                // (z.B. CEST = UTC+2 im Sommer) und die Zeit 2h "falsch" wirken
                // lassen im Vergleich zur Zeit, die das Spiel selbst anzeigt.
                const dt = new Date(d.attacked_at * 1000);
                const formatted = dt.toLocaleString('de-DE', {
                    timeZone: 'UTC',
                    day: '2-digit', month: '2-digit', year: 'numeric',
                    hour: '2-digit', minute: '2-digit', second: '2-digit',
                });
                resultBox.innerHTML = `
                    <div class="alert alert-success">
                        <strong>${escapeHtml(d.target_guild)}</strong> (${escapeHtml(d.server)}) wird angegriffen:<br>
                        📅 ${formatted} UTC${d.attacked_by ? ` — von <strong>${escapeHtml(d.attacked_by)}</strong>` : ''}
                    </div>`;
            } else {
                resultBox.innerHTML = `
                    <div class="alert alert-success">
                        <strong>${escapeHtml(d.target_guild)}</strong> (${escapeHtml(d.server)}): aktuell kein Angriff geplant.
                    </div>`;
            }
        } finally {
            hideOverlay();
            // Client-seitiges Cooldown passend zum Server-Rate-Limit (10s in api/guild_attack_time.php)
            let remaining = 10;
            btn.textContent = `Abfragen (${remaining}s)`;
            const tick = setInterval(() => {
                remaining--;
                if (remaining <= 0) {
                    clearInterval(tick);
                    btn.textContent = 'Abfragen';
                    btn.disabled = false;
                } else {
                    btn.textContent = `Abfragen (${remaining}s)`;
                }
            }, 1000);
        }
    }
    </script>

    <?php renderScripts(); ?>
</body>
</html>
