<?php
/**
 * Fights Calendar Page
 * Monthly battle overview with guild tabs
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/template.php';
require_once __DIR__ . '/../config/database.php';

checkAuth();

// Get database connection
$db = getDB();

// Get all guilds (sorted alphabetically)
$stmt = $db->query("SELECT id, name, server, tag, crest_file FROM guilds ORDER BY name ASC");
$guilds = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get selected guild (default to first guild)
$selectedGuildId = get('guild_id', $guilds[0]['id'] ?? null);
$selectedGuild = null;

foreach ($guilds as $guild) {
    if ($guild['id'] == $selectedGuildId) {
        $selectedGuild = $guild;
        break;
    }
}

// Get current month/year
$year = (int)get('year', (int)date('Y'));
$month = (int)get('month', (int)date('m'));
$month = max(1, min(12, $month));
$monthPadded = str_pad($month, 2, '0', STR_PAD_LEFT);
$monthStart = "$year-$monthPadded-01";
$monthEnd = date('Y-m-t', strtotime($monthStart));

// Get battles for selected guild and month
$stmt = $db->prepare("
    SELECT 
        id,
        battle_type,
        opponent_guild,
        battle_date,
        battle_time
    FROM sf_eval_battles
    WHERE guild_id = ? 
    AND battle_date BETWEEN ? AND ?
    ORDER BY battle_date, battle_time
");

$stmt->execute([$selectedGuildId, $monthStart, $monthEnd]);
$battles = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group battles by date
$battlesByDate = [];
$totalAttacks = 0;
$totalDefenses = 0;
$totalRaids = 0;

foreach ($battles as $battle) {
    $date = $battle['battle_date'];
    if (!isset($battlesByDate[$date])) {
        $battlesByDate[$date] = ['attacks' => 0, 'defenses' => 0, 'raids' => 0, 'battles' => []];
    }
    
    if ($battle['battle_type'] === 'attack') {
        $battlesByDate[$date]['attacks']++;
        $totalAttacks++;
    } elseif ($battle['battle_type'] === 'raid') {
        $battlesByDate[$date]['raids']++;
        $totalRaids++;
    } else {
        $battlesByDate[$date]['defenses']++;
        $totalDefenses++;
    }
    
    $battlesByDate[$date]['battles'][] = $battle;
}

// Calendar generation
$firstDay = date('N', strtotime($monthStart)); // 1 (Monday) to 7 (Sunday)
$daysInMonth = date('t', strtotime($monthStart));

// German month names
$germanMonths = [
    1 => 'Januar', 2 => 'Februar', 3 => 'März', 4 => 'April',
    5 => 'Mai', 6 => 'Juni', 7 => 'Juli', 8 => 'August',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Dezember'
];

$isLoggedIn = isLoggedIn();
$currentUser = getCurrentUsername();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <?php renderHead('Kämpfe', ['/assets/css/calendar.css']); ?>
</head>
<body>
    <?php renderNavbar('battles'); ?>

    <main class="main-content">
        <div class="container">
            <!-- Guild Tabs -->
            <div class="guild-tabs">
                <?php foreach ($guilds as $guild): ?>
                    <a href="?guild_id=<?= $guild['id'] ?>&year=<?= $year ?>&month=<?= $month ?>" 
                       class="guild-tab <?= $guild['id'] == $selectedGuildId ? 'active' : '' ?>">
                        <?= e($guild['name']) ?>
                    </a>
                <?php endforeach; ?>
            </div>
            
            <?php if ($selectedGuild): ?>
                <!-- Guild Header -->
                <div class="page-header">
                    <div style="display:flex;align-items:center;justify-content:space-between;width:100%">
                        <div style="display:flex;align-items:center;gap:1rem">
                            <div class="guild-crest">
                                <?php if (!empty($selectedGuild['crest_file'])): ?>
                                    <img src="/assets/images/<?= e($selectedGuild['crest_file']) ?>" 
                                         alt="<?= e($selectedGuild['name']) ?> Wappen"
                                         style="width:100px;height:100px;object-fit:contain">
                                <?php else: ?>
                                    <div style="width:100px;height:100px;display:flex;align-items:center;justify-content:center;font-size:5rem">
                                        🛡️
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div>
                                <h1 class="guild-title">
                                    <?= e($selectedGuild['name']) ?>
                                </h1>
                                <div class="guild-server">
                                    Server: <?= e($selectedGuild['server']) ?> | A: <?= $totalAttacks ?> / V: <?= $totalDefenses ?><?php if ($totalRaids > 0): ?> / R: <?= $totalRaids ?><?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div style="display: flex; gap: 0.5rem;">
                            <button onclick="openImportModal()" class="btn btn-primary">+ Kämpfe importieren</button>
                        </div>
                    </div>
                </div>

                <!-- Month Navigation -->
                <div class="calendar-header">
                    <div class="month-nav">
                        <?php
                        $prevMonth = date('Y-m', strtotime("$year-$month-01 -1 month"));
                        $nextMonth = date('Y-m', strtotime("$year-$month-01 +1 month"));
                        list($prevYear, $prevMonthNum) = explode('-', $prevMonth);
                        list($nextYear, $nextMonthNum) = explode('-', $nextMonth);
                        ?>
                        <a href="?guild_id=<?= $selectedGuildId ?>&year=<?= $prevYear ?>&month=<?= $prevMonthNum ?>" class="nav-btn">‹</a>
                        <h2>Kämpfe <?= $germanMonths[(int)$month] ?> <?= $year ?></h2>
                        <a href="?guild_id=<?= $selectedGuildId ?>&year=<?= $nextYear ?>&month=<?= $nextMonthNum ?>" class="nav-btn">›</a>
                    </div>
                </div>
        
        <!-- Calendar -->
        <div class="calendar">
            <div class="calendar-weekdays">
                <div>Mo</div>
                <div>Di</div>
                <div>Mi</div>
                <div>Do</div>
                <div>Fr</div>
                <div>Sa</div>
                <div>So</div>
            </div>
            
            <div class="calendar-days">
                <?php
                // Empty cells before first day
                for ($i = 1; $i < $firstDay; $i++) {
                    echo '<div class="calendar-day empty"></div>';
                }
                
                // Days of month
                for ($day = 1; $day <= $daysInMonth; $day++) {
                    $date = sprintf('%s-%s-%02d', $year, $monthPadded, $day);
                    $dayData = $battlesByDate[$date] ?? null;
                    $hasData = $dayData !== null;
                    ?>
                    <div class="calendar-day <?= $hasData ? 'has-data' : '' ?>" 
                         <?= $hasData ? 'data-date="' . $date . '"' : '' ?>>
                        <div class="day-number"><?= $day ?></div>
                        <?php if ($hasData): ?>
                            <div class="day-stats">
                                A: <?= $dayData['attacks'] ?> / V: <?= $dayData['defenses'] ?><?php if ($dayData['raids'] > 0): ?> / R: <?= $dayData['raids'] ?><?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php
                }
                ?>
            </div>
            </div>
        <?php else: ?>
            <p>Keine Gilden vorhanden.</p>
        <?php endif; ?>
        </div>
    </main>

<!-- Battle Details Modal -->
<div id="battleModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Details für <span id="modalDate"></span></h3>
            <button class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Uhrzeit</th>
                        <th>Typ</th>
                        <th>Gegner</th>
                        <th>Aktionen</th>
                    </tr>
                </thead>
                <tbody id="battleList">
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Participants Modal -->
<div id="participantsModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Teilnehmer</h3>
            <button class="modal-close">&times;</button>
        </div>
        <div class="modal-body" id="participantsContent">
        </div>
    </div>
</div>

<!-- Confirm Modal -->
<div id="confirmModal" class="modal">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h3 id="confirmTitle">Bestätigung</h3>
            <button class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <p id="confirmMessage"></p>
            <div style="display: flex; gap: var(--spacing-md); justify-content: flex-end; margin-top: var(--spacing-lg);">
                <button id="confirmCancel" class="btn-small">Abbrechen</button>
                <button id="confirmOk" class="btn-small btn-danger">OK</button>
            </div>
        </div>
    </div>
</div>

<!-- Import Modal -->
<div id="importModal" class="modal">
    <div class="modal-content" style="max-width: 700px;">
        <div class="modal-header">
            <h3>Kämpfe importieren</h3>
            <button class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <form id="importForm">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--spacing-md);">
                    <div class="form-group">
                        <label for="importDate">Datum</label>
                        <input type="date" id="importDate" name="date" required class="form-input">
                    </div>
                    <div class="form-group">
                        <label for="importTime">Uhrzeit</label>
                        <input type="time" id="importTime" name="time" required class="form-input">
                    </div>
                </div>
                <div class="form-group">
                    <label for="importText">Kampfbericht (Copy & Paste)</label>
                    <textarea id="importText" name="text" rows="10" required class="form-input" placeholder="Füge hier den Kampfbericht ein..."></textarea>
                </div>
                <div style="display: flex; gap: var(--spacing-md); justify-content: flex-end; margin-top: var(--spacing-lg);">
                    <button type="button" class="btn-small" onclick="closeImportModal()">Abbrechen</button>
                    <button type="submit" class="btn-small btn-primary">Importieren</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Battle data from PHP
const battlesByDate = <?= json_encode($battlesByDate) ?>;
const selectedGuildId = <?= $selectedGuildId ?>;

// Modal handling
const battleModal = document.getElementById('battleModal');
const participantsModal = document.getElementById('participantsModal');
const modalCloses = document.querySelectorAll('.modal-close');

// Open battle details modal
document.querySelectorAll('.calendar-day.has-data').forEach(day => {
    day.addEventListener('click', function() {
        const date = this.dataset.date;
        const data = battlesByDate[date];
        
        // Format date to DD.MM.YYYY
        const [year, month, day] = date.split('-');
        const formattedDate = `${day}.${month}.${year}`;
        document.getElementById('modalDate').textContent = formattedDate;
        
        const battleList = document.getElementById('battleList');
        battleList.innerHTML = '';
        
        data.battles.forEach(battle => {
            const row = document.createElement('tr');
            const escapedOpponent = escapeHtml(battle.opponent_guild);
            row.innerHTML = `
                <td>${battle.battle_time}</td>
                <td>${battle.battle_type === 'attack' ? 'Angriff' : battle.battle_type === 'defense' ? 'Verteidigung' : 'Gildenraid'}</td>
                <td>${escapedOpponent}</td>
                <td>
                    <button onclick="showParticipants(${battle.id}, '${battle.battle_date}', '${battle.battle_time}', '${escapedOpponent}', '${battle.battle_type}')" class="btn-small">Teilnehmer</button>
                    <button onclick="deleteBattle(${battle.id})" class="btn-small btn-danger">Löschen</button>
                    <select onchange="moveBattle(${battle.id}, this.value)" class="guild-select">
                        <option value="">Verschieben...</option>
                        <?php foreach ($guilds as $g): ?>
                            <option value="<?= $g['id'] ?>"><?= e($g['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            `;
            battleList.appendChild(row);
        });
        
        battleModal.style.display = 'flex';
    });
});

// Close modals
modalCloses.forEach(btn => {
    btn.addEventListener('click', function() {
        this.closest('.modal').style.display = 'none';
    });
});

// Helper function to escape HTML in JS
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Custom alert modal
function showAlert(message, title = 'Hinweis') {
    return new Promise((resolve) => {
        const modal = document.getElementById('confirmModal');
        const titleEl = document.getElementById('confirmTitle');
        const messageEl = document.getElementById('confirmMessage');
        const okBtn = document.getElementById('confirmOk');
        const cancelBtn = document.getElementById('confirmCancel');
        const closeBtn = modal.querySelector('.modal-close');
        
        titleEl.textContent = title;
        messageEl.textContent = message;
        
        // Hide cancel button for alerts
        cancelBtn.style.display = 'none';
        okBtn.textContent = 'OK';
        okBtn.className = 'btn-small'; // Remove btn-danger class
        
        modal.style.display = 'flex';
        
        const cleanup = () => {
            modal.style.display = 'none';
            cancelBtn.style.display = ''; // Show again for next use
            okBtn.textContent = 'OK';
            okBtn.className = 'btn-small btn-danger';
            okBtn.replaceWith(okBtn.cloneNode(true));
            closeBtn.replaceWith(closeBtn.cloneNode(true));
        };
        
        document.getElementById('confirmOk').onclick = () => {
            cleanup();
            resolve();
        };
        
        modal.querySelector('.modal-close').onclick = () => {
            cleanup();
            resolve();
        };
    });
}

// Custom Confirm Modal (Ja/Nein)
function showConfirm(message, title = 'Bestätigung') {
    return new Promise((resolve) => {
        const modal = document.createElement('div');
        modal.className = 'modal-backdrop';
        modal.innerHTML = `
            <div class="modal-content">
                <div class="modal-header">
                    <h3>${title}</h3>
                    <button class="modal-close" onclick="this.closest('.modal-backdrop').remove(); event.stopPropagation();">×</button>
                </div>
                <div class="modal-body">
                    <p style="white-space: pre-line;">${message}</p>
                </div>
                <div class="modal-footer" style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button class="btn btn-secondary" onclick="this.closest('.modal-backdrop').remove();">Nein</button>
                    <button class="btn btn-primary confirm-yes">Ja</button>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        
        const yesBtn = modal.querySelector('.confirm-yes');
        yesBtn.onclick = () => {
            modal.remove();
            resolve(true);
        };
        
        const noBtn = modal.querySelector('.btn-secondary');
        noBtn.onclick = () => {
            modal.remove();
            resolve(false);
        };
        
        yesBtn.focus();
    });
}

// Show participants
async function showParticipants(battleId, date, time, opponent, type) {
    try {
        const response = await fetch(`../api/get_participants.php?battle_id=${battleId}`);
        const data = await response.json();
        
        // Format date to DD.MM.YYYY
        const [year, month, day] = date.split('-');
        const formattedDate = `${day}.${month}.${year}`;
        
        const content = document.getElementById('participantsContent');
        content.innerHTML = `
            <div class="battle-info">
                <p><strong>Datum:</strong> ${formattedDate} ${time}</p>
                <p><strong>Gegner:</strong> ${opponent}</p>
                <p><strong>Typ:</strong> ${type === 'attack' ? 'Angriff' : type === 'defense' ? 'Verteidigung' : 'Gildenraid'}</p>
            </div>
            <div class="participants-grid">
                <div class="participants-column">
                    <h4>Teilgenommen (${data.participated.length})</h4>
                    <ul>
                        ${data.participated.map(p => {
                            let display = escapeHtml(p.player_name);
                            if (p.player_server_tag) display += ` (${escapeHtml(p.player_server_tag)})`;
                            if (p.player_level) display += ` (${p.player_level})`;
                            return `<li>${display}</li>`;
                        }).join('')}
                    </ul>
                </div>
                <div class="participants-column">
                    <h4>Nicht teilgenommen (${data.not_participated.length})</h4>
                    <ul>
                        ${data.not_participated.map(p => {
                            let display = escapeHtml(p.player_name);
                            if (p.player_server_tag) display += ` (${escapeHtml(p.player_server_tag)})`;
                            if (p.player_level) display += ` (${p.player_level})`;
                            return `<li>${display}</li>`;
                        }).join('')}
                    </ul>
                </div>
            </div>
        `;
        
        participantsModal.style.display = 'flex';
    } catch (error) {
        await showAlert('Fehler beim Laden der Teilnehmer', 'Fehler');
        console.error(error);
    }
}

// Custom confirm modal
function showConfirm(message, title = 'Bestätigung') {
    return new Promise((resolve) => {
        const modal = document.getElementById('confirmModal');
        const titleEl = document.getElementById('confirmTitle');
        const messageEl = document.getElementById('confirmMessage');
        const okBtn = document.getElementById('confirmOk');
        const cancelBtn = document.getElementById('confirmCancel');
        const closeBtn = modal.querySelector('.modal-close');
        
        titleEl.textContent = title;
        messageEl.textContent = message;
        modal.style.display = 'flex';
        
        const cleanup = () => {
            modal.style.display = 'none';
            okBtn.replaceWith(okBtn.cloneNode(true));
            cancelBtn.replaceWith(cancelBtn.cloneNode(true));
            closeBtn.replaceWith(closeBtn.cloneNode(true));
        };
        
        document.getElementById('confirmOk').onclick = () => {
            cleanup();
            resolve(true);
        };
        
        document.getElementById('confirmCancel').onclick = () => {
            cleanup();
            resolve(false);
        };
        
        modal.querySelector('.modal-close').onclick = () => {
            cleanup();
            resolve(false);
        };
    });
}

// Delete battle
async function deleteBattle(battleId) {
    const confirmed = await showConfirm('Möchten Sie diesen Kampf wirklich löschen?', 'Kampf löschen');
    if (!confirmed) return;
    
    try {
        const response = await fetch('../api/delete_battle.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({battle_id: battleId})
        });
        
        const result = await response.json();
        
        if (result.success) {
            location.reload();
        } else {
            await showAlert(result.error || 'Fehler beim Löschen', 'Fehler');
        }
    } catch (error) {
        await showAlert('Fehler beim Löschen', 'Fehler');
        console.error(error);
    }
}

// Move battle
async function moveBattle(battleId, targetGuildId) {
    if (!targetGuildId) return;
    
    const confirmed = await showConfirm('Möchten Sie diesen Kampf wirklich verschieben?', 'Kampf verschieben');
    if (!confirmed) {
        event.target.value = '';
        return;
    }
    
    try {
        const response = await fetch('../api/move_battle.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                battle_id: battleId,
                target_guild_id: targetGuildId
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            location.reload();
        } else {
            await showAlert(result.error || 'Fehler beim Verschieben', 'Fehler');
        }
    } catch (error) {
        await showAlert('Fehler beim Verschieben', 'Fehler');
        console.error(error);
    }
}
</script>

<script>
// Import Modal Functions
function openImportModal(date = null) {
    const modal = document.getElementById('importModal');
    const dateInput = document.getElementById('importDate');
    
    if (date) {
        dateInput.value = date; // Format: YYYY-MM-DD
    } else {
        dateInput.value = ''; // Empty for manual entry
    }
    
    document.getElementById('importTime').value = '';
    document.getElementById('importText').value = '';
    
    modal.style.display = 'flex';
}

function closeImportModal() {
    document.getElementById('importModal').style.display = 'none';
}

// Handle import form submission
document.getElementById('importForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const formData = {
        guild_id: <?= $selectedGuildId ?>,
        date: document.getElementById('importDate').value,
        time: document.getElementById('importTime').value,
        text: document.getElementById('importText').value
    };
    
try {
        const response = await fetch('/api/import_battle.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(formData)
        });
        
        // Get response text and parse
        const responseText = await response.text();
        const result = JSON.parse(responseText);
        
        // Close import modal first
        closeImportModal();

        // Check result
        if (result.success) {
            await showAlert('Kampfbericht erfolgreich importiert!', 'Erfolg');
            location.reload();
        } else {
            const errorMsg = result.error || 'Fehler beim Importieren';
            await showAlert(errorMsg, errorMsg.includes('bereits') ? 'Duplikat' : 'Fehler');
        }
    } catch (error) {
        closeImportModal();
        await showAlert('Fehler beim Importieren', 'Fehler');
        console.error(error);
    }
});

// Add click handler to empty calendar days for quick import
document.querySelectorAll('.calendar-day:not(.has-data):not(.empty)').forEach(day => {
    day.style.cursor = 'pointer';
    day.addEventListener('click', function() {
        const dayNumber = this.querySelector('.day-number').textContent;
        const date = `<?= $year ?>-<?= str_pad($month, 2, '0', STR_PAD_LEFT) ?>-${dayNumber.padStart(2, '0')}`;
        openImportModal(date);
    });
});

// Add right-click handler to days with data for quick import
document.querySelectorAll('.calendar-day.has-data').forEach(day => {
    day.addEventListener('contextmenu', function(e) {
        e.preventDefault(); // Prevent context menu
        const date = this.dataset.date;
        openImportModal(date);
    });
    
    // Also add hover hint
    day.title = 'Linksklick: Details anzeigen | Rechtsklick: Kampf importieren';
});
</script>

<!-- Fetch Reports Modal -->
<div id="fetchModal" class="modal">
    <div class="modal-content" style="max-width: 700px;">
        <div class="modal-header">
            <h3>📥 Kampfberichte von S&F abholen</h3>
            <button class="modal-close" onclick="closeFetchModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div id="fetchStatus">
                <p style="text-align: center; padding: 2rem;">
                    ⏳ Lade Charaktere...
                </p>
            </div>
            <div id="characterSelection" style="display: none;">
                <p style="margin-bottom: 1rem; color: var(--color-text-secondary);">
                    Wähle einen Charakter, um dessen Gildenkampfberichte abzuholen:
                </p>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Charakter</th>
                            <th>Level</th>
                            <th>Server</th>
                            <th>Aktion</th>
                        </tr>
                    </thead>
                    <tbody id="characterList"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
// Fetch Reports Modal Functions
async function openFetchModal() {
    const modal = document.getElementById('fetchModal');
    modal.style.display = 'flex';
    
    try {
        const response = await fetch('/api/sf_get_characters.php');
        const result = await response.json();
        
        if (result.success && result.characters.length > 0) {
            showCharacterSelection(result.characters);
        } else if (result.success && result.characters.length === 0) {
            document.getElementById('fetchStatus').innerHTML = `
                <div style="text-align: center; padding: 2rem;">
                    <div style="font-size: 3rem; margin-bottom: 1rem;">📋</div>
                    <h3 style="margin-bottom: 0.5rem;">Keine Charaktere ausgewählt</h3>
                    <p style="color: var(--color-text-secondary); margin-bottom: 1.5rem;">
                        Du hast noch keine Charaktere für das automatische Abholen<br>
                        von Kampfberichten ausgewählt.
                    </p>
                    <a href="/settings.php" class="btn btn-primary">
                        Charaktere auswählen
                    </a>
                </div>
            `;
        } else if (result.error && result.error.includes('Kein S&F Account')) {
            document.getElementById('fetchStatus').innerHTML = `
                <div style="text-align: center; padding: 2rem;">
                    <div style="font-size: 3rem; margin-bottom: 1rem;">🔐</div>
                    <h3 style="margin-bottom: 0.5rem;">Kein S&F Account verknüpft</h3>
                    <p style="color: var(--color-text-secondary); margin-bottom: 1.5rem;">
                        Um Kampfberichte automatisch abzuholen, musst du zuerst deinen<br>
                        Shakes & Fidget Account in den Einstellungen verknüpfen.
                    </p>
                    <a href="/settings.php" class="btn btn-primary">
                        Zu den Einstellungen
                    </a>
                </div>
            `;
        } else {
            throw new Error(result.error || 'Keine Charaktere gefunden');
        }
    } catch (error) {
        document.getElementById('fetchStatus').innerHTML = `
            <div style="text-align: center; padding: 2rem; color: var(--color-danger);">
                <div style="font-size: 3rem; margin-bottom: 1rem;">❌</div>
                <p>Fehler beim Laden der Charaktere</p>
                <p style="font-size: 0.875rem; margin-top: 0.5rem;">${error.message}</p>
            </div>
        `;
    }
}

function closeFetchModal() {
    document.getElementById('fetchModal').style.display = 'none';
}

function showCharacterSelection(characters) {
    document.getElementById('fetchStatus').style.display = 'none';
    document.getElementById('characterSelection').style.display = 'block';
    
    const tbody = document.getElementById('characterList');
    tbody.innerHTML = characters.map(c => `
        <tr>
            <td><strong>${escapeHtml(c.name)}</strong></td>
            <td>${c.level || '-'}</td>
            <td><small>${escapeHtml(c.server)}</small></td>
            <td>
                <button 
                    onclick="fetchReports('${escapeHtml(c.server)}', '${escapeHtml(c.name)}')"
                    class="btn btn-primary btn-small"
                >
                    Abholen
                </button>
            </td>
        </tr>
    `).join('');
}

async function fetchReports(server, character) {
    const btn = event?.target || document.querySelector(`button[onclick*="${character}"]`);
    if (!btn) return;
    btn.disabled = true;
    btn.textContent = '⏳ Lädt...';
    
    try {
        const response = await fetch('/api/sf_fetch_reports.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({server, character})
        });
        
        const result = await response.json();
        
        closeFetchModal();
        
        if (result.success) {
            if (result.count > 0) {
                const goToInbox = await showConfirm(
                    `✅ ${result.count} neue Bericht(e) abgeholt!\n\nMöchtest du zum Posteingang gehen um sie zu überprüfen?`,
                    'Erfolg'
                );
                if (goToInbox) {
                    window.location.href = '/inbox.php';
                }
            } else {
                await showAlert('Keine neuen Berichte gefunden', 'Hinweis');
            }
        } else {
            await showAlert(result.error || 'Fehler beim Abholen', 'Fehler');
        }
    } catch (error) {
        closeFetchModal();
        await showAlert('Fehler beim Abholen der Berichte', 'Fehler');
        console.error(error);
    } finally {
        btn.disabled = false;
        btn.textContent = 'Abholen';
    }
}
</script>

    <?php renderFooter(); ?>
</body>
</html>