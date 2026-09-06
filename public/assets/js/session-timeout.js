/**
 * S&F Guilds - Inaktivitäts-Timeout
 *
 * Zeigt neben dem Benutzernamen die verbleibende Sitzungszeit an, verlängert
 * die Session bei "echten Aktionen" (Klick / Tastendruck / Tap) und blendet in
 * der letzten Minute eine Warnung ein. Läuft der Timer ab, wird zur Login-Seite
 * umgeleitet.
 *
 * Aktiv nur, wenn das Template window.SF_SESSION gesetzt hat (= eingeloggt).
 * Server ist die Wahrheit: Antworten von session_status.php / session_extend.php
 * überschreiben die lokale Restzeit.
 */
(function () {
    'use strict';

    var cfg = window.SF_SESSION;
    if (!cfg || typeof cfg.timeout !== 'number') {
        return; // nicht eingeloggt
    }

    var TIMEOUT_MS = cfg.timeout * 1000;
    var WARNING_MS = cfg.warning * 1000;
    var EXTEND_THROTTLE_MS = 60 * 1000;  // Server frühestens 1x pro Minute verlängern
    var SYNC_INTERVAL_MS = 45 * 1000;    // Abgleich mit Server (andere Tabs etc.)
    var TICK_MS = 1000;

    var expiresAt = Date.now() + (cfg.remaining * 1000);
    var lastServerExtend = Date.now();
    var warningOpen = false;
    var redirecting = false;
    var overlayEl = null;
    var countEl = null;

    var timerEl = document.getElementById('sessionTimer');

    function pad(n) {
        return n < 10 ? '0' + n : String(n);
    }

    function formatRemaining(ms) {
        var total = Math.max(0, Math.round(ms / 1000));
        return Math.floor(total / 60) + ':' + pad(total % 60);
    }

    function adoptServerRemaining(seconds) {
        // Server-Restzeit übernehmen (kann kürzer ODER länger als lokal sein)
        expiresAt = Date.now() + (seconds * 1000);
    }

    function redirectToLogin() {
        if (redirecting) return;
        redirecting = true;
        var ret = encodeURIComponent(location.pathname + location.search);
        location.href = '/login.php?expired=1&return=' + ret;
    }

    function extend(explicit) {
        // explicit = true: Klick auf "Angemeldet bleiben" im Warndialog
        var lowRemaining = (expiresAt - Date.now()) <= (WARNING_MS + 30000);
        if (!explicit && !lowRemaining && Date.now() - lastServerExtend < EXTEND_THROTTLE_MS) {
            // Komfortzone + innerhalb des Drossel-Fensters: nur lokal weiterzählen
            expiresAt = Date.now() + TIMEOUT_MS;
            return;
        }
        lastServerExtend = Date.now();
        expiresAt = Date.now() + TIMEOUT_MS; // optimistisch, Antwort korrigiert
        fetch('/api/session_extend.php', { method: 'POST', headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d || !d.authenticated) { redirectToLogin(); return; }
                adoptServerRemaining(d.remaining);
                closeWarning();
            })
            .catch(function () { /* Netzwerkfehler: nächster Tick/Sync versucht es erneut */ });
    }

    function syncStatus() {
        fetch('/api/session_status.php', { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d || !d.authenticated) { redirectToLogin(); return; }
                adoptServerRemaining(d.remaining);
            })
            .catch(function () { /* ignorieren */ });
    }

    /* ---------- Warndialog ---------- */

    function buildWarning() {
        overlayEl = document.createElement('div');
        overlayEl.className = 'confirm-overlay';
        overlayEl.setAttribute('role', 'alertdialog');
        overlayEl.innerHTML =
            '<div class="confirm-modal">' +
                '<div class="confirm-header">Bald abgemeldet</div>' +
                '<div class="confirm-message">' +
                    'Du wirst wegen Inaktivität in <span class="session-warning-count">--</span> abgemeldet.' +
                '</div>' +
                '<div class="confirm-actions">' +
                    '<button type="button" class="btn btn-danger" data-session-stay>Angemeldet bleiben</button>' +
                '</div>' +
            '</div>';
        document.body.appendChild(overlayEl);
        countEl = overlayEl.querySelector('.session-warning-count');
        overlayEl.querySelector('[data-session-stay]').addEventListener('click', function () {
            extend(true);
        });
    }

    function openWarning() {
        if (warningOpen) return;
        warningOpen = true;
        if (!overlayEl) buildWarning();
        overlayEl.hidden = false;
        overlayEl.style.display = '';
        var btn = overlayEl.querySelector('[data-session-stay]');
        if (btn) setTimeout(function () { btn.focus(); }, 50);
    }

    function closeWarning() {
        warningOpen = false;
        if (overlayEl) overlayEl.style.display = 'none';
    }

    /* ---------- Haupt-Tick ---------- */

    function tick() {
        var remainingMs = expiresAt - Date.now();

        if (timerEl) {
            timerEl.hidden = false;
            timerEl.textContent = formatRemaining(remainingMs);
            timerEl.classList.toggle('session-timer--warning', remainingMs <= WARNING_MS);
        }

        if (remainingMs <= 0) {
            // Im Hintergrund-Tab nicht sofort wegspringen – erst bei Sichtbarkeit
            if (document.hidden) { closeWarning(); return; }
            redirectToLogin();
            return;
        }

        if (remainingMs <= WARNING_MS) {
            if (!document.hidden) {
                openWarning();
                if (countEl) countEl.textContent = formatRemaining(remainingMs);
            }
        } else if (warningOpen) {
            closeWarning();
        }
    }

    /* ---------- Aktivitäts-Erkennung: nur echte Aktionen ---------- */

    function onRealAction() {
        if (redirecting) return;
        if (warningOpen) return; // im Warnfenster zählt nur der Button
        extend(false);
    }

    ['click', 'keydown', 'touchstart'].forEach(function (evt) {
        document.addEventListener(evt, onRealAction, { passive: true, capture: true });
    });

    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) {
            syncStatus();
            setTimeout(tick, 200);
        }
    });

    tick();
    setInterval(tick, TICK_MS);
    setInterval(syncStatus, SYNC_INTERVAL_MS);
})();
