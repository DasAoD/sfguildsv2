<?php
/**
 * Hellevator - Höllen-Angriffe / Hell Attacks
 * Info page for guild members about Hellevator hell attacks
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/template.php';

$embed = !empty($_GET['embed']);

?>
<!DOCTYPE html>
<html lang="de">
<head>
    <?php renderHead('Hellevator - Höllen-Angriffe', ['/assets/css/hellevator.css']); ?>
</head>
<body>
    <?php renderNavbar('hellevator', $embed ? ['hide_dashboard' => true] : []); ?>

    <!-- Background Music -->
    <audio id="bgMusic" loop preload="auto">
        <source src="/assets/hellevator/hellevator_music.mp3" type="audio/mpeg">
    </audio>

    <!-- Header Banner -->
    <div class="hellevator-banner">
        <img src="/assets/hellevator/helle_header.jpg" alt="Hellevator">
    </div>

    <div class="container hellevator-container">

        <!-- Content Layout: 3 columns -->
        <div class="hellevator-layout">

            <!-- Left: Info Boxes -->
            <div class="hellevator-left">
                <div class="warning-box info" id="warningInfo">
                    <span>📋 </span><span class="warning-label info-text" id="infoLabel">Hinweis:</span>
                    <span id="tableNote"> Level beziehen sich auf nur einen Spieler.</span>
                </div>

                <div class="warning-box caution" id="warningCaution">
                    <span>⚠️ </span><span class="warning-label caution-text" id="cautionLabel">Wichtig:</span>
                    <span id="cautionText"> Melden sich 2 oder mehr Spieler in einer Etage an, bitte aufs Level achten – sonst werden unnötig Schlüsselkarten verplempert.</span>
                </div>

                <div class="warning-box danger" id="warningDanger">
                    <span>🚫 </span><span class="warning-label danger-text" id="dangerLabel">Ganz Wichtig:</span>
                    <span id="dangerText"> Nur wer im Kampf tatsächlich zum Einsatz kommt, erhält eine Belohnung – überzählige Anmeldungen gehen leer aus!</span>
                </div>

                <div class="warning-box info" id="warningMarket">
                    <span>🛒 </span><span class="warning-label info-text" id="marketLabel">Schwarzmarkt:</span>
                    <span id="marketText"> Während des Events könnt ihr im Schwarzmarkt je 3× Schlüsselkarten für Lucky Coins und 3× für Pilze kaufen.</span>
                </div>
            </div>

            <!-- Center: Table -->
            <div class="hellevator-center">
                <div class="hellevator-section-header">
                    <span class="icon">🔥</span>
                    <span id="sectionTitle">Höllen-Angriffe – Anmeldungen</span>
                </div>

                <table class="hellevator-table">
                    <thead>
                        <tr>
                            <th id="thFloor">Stockwerk</th>
                            <th id="thStage">Etage</th>
                            <th id="thPlayers">Spieler / Level</th>
                        </tr>
                    </thead>
                    <tbody id="tableBody">
                    </tbody>
                </table>
            </div>

            <!-- Right: Lang Toggle + Image + Help Link -->
            <div class="hellevator-right">
                <div class="lang-toggle">
                    <button class="lang-btn active" onclick="setLang('de')" id="btn-de">🇩🇪 Deutsch</button>
                    <button class="lang-btn" onclick="setLang('en')" id="btn-en">🇬🇧 English</button>
                </div>
                <div class="hellevator-side-image">
                    <img src="/assets/hellevator/helle_side.webp" alt="Hellevator Szene">
                </div>
                <a href="https://playa-games.helpshift.com/hc/de/4-shakes-fidget-1653988985/faq/135-hellevator/" target="_blank" class="hellevator-help-link">
                    <span class="help-link-icon">📘</span>
                    <span class="help-link-text">
                        <span class="help-link-title" id="helpTitle">Offizielle Hellevator-Hilfe</span>
                        <span class="help-link-desc" id="helpDesc">Playa Games Helpshift – Alles über den Hellevator</span>
                    </span>
                </a>
            </div>

        </div>
    </div>

    <!-- Music Toggle Button -->
    <button class="music-toggle" id="musicToggle" onclick="toggleMusic()" title="Musik an/aus">
        🔊
    </button>

    <script>
        // === DATA ===
        const floors = {
            de: [
                { floor: 'ab 50',  name: 'Highway to Hell', info: '1 Spieler (mind. Lvl 150)' },
                { floor: 'ab 100', name: 'Limbo',           info: '1 Spieler (min. Lvl 300)' },
                { floor: 'ab 150', name: 'Lust',            info: '1 Spieler (min. Lvl 500)' },
                { floor: 'ab 200', name: 'Völlerei',        info: '1 Spieler (min. Lvl 600)' },
                { floor: 'ab 250', name: 'Gier',            info: '3 Spieler (min. Lvl 600+)' },
                { floor: 'ab 300', name: 'Zorn',            info: 'mind. 5 Spieler (Lvl 600+)' },
                { floor: 'ab 350', name: 'Häresie',         info: 'keine Daten' },
                { floor: 'ab 400', name: 'Gewalt',          info: 'keine Daten' },
                { floor: 'ab 450', name: 'Betrug',          info: 'keine Daten' },
                { floor: 'ab 500', name: 'Verrat',          info: 'keine Daten' },
                { floor: 'ab 550', name: 'Limbo',           info: 'keine Daten' },
                { floor: 'ab 600', name: 'Highway to Hell', info: 'keine Daten' },
            ],
            en: [
                { floor: 'from 50',  name: 'Highway to Hell', info: '1 player (min. Lvl 150)' },
                { floor: 'from 100', name: 'Limbo',           info: '1 player (min. Lvl 300)' },
                { floor: 'from 150', name: 'Lust',            info: '1 player (min. Lvl 500)' },
                { floor: 'from 200', name: 'Gluttony',        info: '1 player (min. Lvl 600)' },
                { floor: 'from 250', name: 'Greed',           info: '3 players (min. Lvl 600+)' },
                { floor: 'from 300', name: 'Wrath',           info: 'at least 5 players (Lvl 600+)' },
                { floor: 'from 350', name: 'Heresy',          info: 'no data' },
                { floor: 'from 400', name: 'Violence',        info: 'no data' },
                { floor: 'from 450', name: 'Fraud',           info: 'no data' },
                { floor: 'from 500', name: 'Treachery',       info: 'no data' },
                { floor: 'from 550', name: 'Limbo',           info: 'no data' },
                { floor: 'from 600', name: 'Highway to Hell', info: 'no data' },
            ]
        };

        const translations = {
            de: {
                sectionTitle: 'Höllen-Angriffe – Anmeldungen',
                thFloor: 'Stockwerk',
                thStage: 'Etage',
                thPlayers: 'Spieler / Level',
                infoLabel: 'Hinweis:',
                tableNote: ' Level beziehen sich auf nur einen Spieler.',
                cautionLabel: 'Wichtig:',
                cautionText: ' Melden sich 2 oder mehr Spieler in einer Etage an, bitte aufs Level achten – sonst werden unnötig Schlüsselkarten verplempert.',
                dangerLabel: 'Ganz Wichtig:',
                dangerText: ' Nur wer im Kampf tatsächlich zum Einsatz kommt, erhält eine Belohnung – überzählige Anmeldungen gehen leer aus!',
                marketLabel: 'Schwarzmarkt:',
                marketText: ' Während des Events könnt ihr im Schwarzmarkt je 3× Schlüsselkarten für Lucky Coins und 3× für Pilze kaufen.',
                helpTitle: 'Offizielle Hellevator-Hilfe',
                helpDesc: 'Playa Games Helpshift – Alles über den Hellevator',
                noData: 'keine Daten'
            },
            en: {
                sectionTitle: 'Hell Attacks – Registrations',
                thFloor: 'Floor',
                thStage: 'Stage',
                thPlayers: 'Players / Level',
                infoLabel: 'Note:',
                tableNote: ' Levels refer to one player only.',
                cautionLabel: 'Important:',
                cautionText: ' If 2 or more players register on one floor, please pay attention to the level, otherwise key cards will be wasted unnecessarily.',
                dangerLabel: 'Very Important:',
                dangerText: ' Only players who are actually deployed in battle receive a reward – surplus registrations go empty-handed!',
                marketLabel: 'Black Market:',
                marketText: ' During the event you can buy key cards on the Black Market: 3× for Lucky Coins and 3× for Mushrooms.',
                helpTitle: 'Official Hellevator Help',
                helpDesc: 'Playa Games Helpshift – Everything about the Hellevator',
                noData: 'no data'
            }
        };

        let currentLang = 'de';

        function setLang(lang) {
            currentLang = lang;
            document.getElementById('btn-de').classList.toggle('active', lang === 'de');
            document.getElementById('btn-en').classList.toggle('active', lang === 'en');

            const t = translations[lang];
            document.getElementById('sectionTitle').textContent = t.sectionTitle;
            document.getElementById('thFloor').textContent = t.thFloor;
            document.getElementById('thStage').textContent = t.thStage;
            document.getElementById('thPlayers').textContent = t.thPlayers;
            document.getElementById('infoLabel').textContent = t.infoLabel;
            document.getElementById('tableNote').textContent = t.tableNote;
            document.getElementById('cautionLabel').textContent = t.cautionLabel;
            document.getElementById('cautionText').textContent = t.cautionText;
            document.getElementById('dangerLabel').textContent = t.dangerLabel;
            document.getElementById('dangerText').textContent = t.dangerText;
            document.getElementById('marketLabel').textContent = t.marketLabel;
            document.getElementById('marketText').textContent = t.marketText;
            document.getElementById('helpTitle').textContent = t.helpTitle;
            document.getElementById('helpDesc').textContent = t.helpDesc;

            renderTable(lang);
        }

        function renderTable(lang) {
            const noData = translations[lang].noData;
            const tbody = document.getElementById('tableBody');
            tbody.innerHTML = floors[lang].map(row => `
                <tr>
                    <td><span class="floor-badge">${row.floor}</span></td>
                    <td class="floor-name">${row.name}</td>
                    <td class="player-info${row.info === noData ? ' no-data' : ''}">${row.info}</td>
                </tr>
            `).join('');
        }

        // === MUSIC ===
        const audio = document.getElementById('bgMusic');
        const toggleBtn = document.getElementById('musicToggle');
        let isMuted = false;
        let musicStarted = false;

        audio.volume = 0.6;

        function toggleMusic() {
            if (!musicStarted) {
                audio.play().then(() => {
                    musicStarted = true;
                    isMuted = false;
                    toggleBtn.textContent = '🔊';
                }).catch(() => {});
                return;
            }
            isMuted = !isMuted;
            audio.muted = isMuted;
            toggleBtn.textContent = isMuted ? '🔇' : '🔊';
            toggleBtn.classList.toggle('muted', isMuted);
        }

        // Auto-start music on first user interaction
        function startMusicOnInteraction() {
            if (!musicStarted) {
                audio.play().then(() => {
                    musicStarted = true;
                    toggleBtn.textContent = '🔊';
                }).catch(() => {});
            }
            document.removeEventListener('click', startMusicOnInteraction);
            document.removeEventListener('scroll', startMusicOnInteraction);
        }
        document.addEventListener('click', startMusicOnInteraction);
        document.addEventListener('scroll', startMusicOnInteraction);

        // Init
        renderTable('de');
    </script>

<?php
renderFooter();
?>
</body>
</html>