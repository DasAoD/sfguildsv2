<?php
/**
 * Battle Inbox Page
 * Review and import fetched battle reports
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/template.php';
require_once __DIR__ . '/../config/database.php';

checkAuth();

$db = getDB();
$userId = $_SESSION['user_id'];

// Get user's guilds with pending reports count
$stmt = $db->prepare("
    SELECT 
        g.id,
        g.name,
        g.server,
        COUNT(i.id) as pending_count
    FROM guilds g
    LEFT JOIN battle_inbox i ON g.id = i.guild_id AND i.user_id = ? AND i.status = 'pending'
    WHERE NOT EXISTS (
        SELECT 1 FROM sf_eval_battles b
        WHERE b.message_id = i.message_id 
        OR (
            b.guild_id = i.guild_id 
            AND b.battle_date = i.battle_date 
            AND b.battle_time = i.battle_time 
            AND b.opponent_guild = i.opponent_guild
        )
    )
    GROUP BY g.id, g.name, g.server
    HAVING pending_count > 0
    ORDER BY g.name
");
$stmt->execute([$userId]);
$guilds = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get total pending count
$stmt = $db->prepare("SELECT COUNT(*) FROM battle_inbox WHERE user_id = ? AND status = 'pending'");
$stmt->execute([$userId]);
$totalPending = $stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <?php renderHead('Kampfbericht-Posteingang', ['/assets/css/calendar.css']); ?>
    <style>
    .guild-tabs {
        display: flex;
        gap: 0.5rem;
        margin-bottom: 1.5rem;
        border-bottom: 2px solid var(--color-border);
        overflow-x: auto;
        padding-bottom: 0;
    }
    
    .guild-tab {
        padding: 0.75rem 1.5rem;
        background: transparent;
        border: none;
        border-bottom: 3px solid transparent;
        color: var(--color-text-secondary);
        cursor: pointer;
        font-size: 1rem;
        font-weight: 500;
        white-space: nowrap;
        transition: all 0.2s;
        position: relative;
        margin-bottom: -2px;
    }
    
    .guild-tab:hover {
        color: var(--color-text-primary);
        background: var(--color-bg-hover);
    }
    
    .guild-tab.active {
        color: var(--color-primary);
        border-bottom-color: var(--color-primary);
    }
    
    .guild-tab .count {
        background: var(--color-danger);
        color: white;
        padding: 0.125rem 0.5rem;
        border-radius: 1rem;
        font-size: 0.75rem;
        margin-left: 0.5rem;
    }
    
    .inbox-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 1rem;
    }
    
    .inbox-table th,
    .inbox-table td {
        padding: 0.75rem;
        text-align: left;
        border-bottom: 1px solid var(--color-border);
    }
    
    .inbox-table th {
        background: var(--color-bg-tertiary);
        font-weight: 600;
        position: sticky;
        top: 0;
    }
    
    .inbox-table tr:hover {
        background: var(--color-bg-hover);
    }
    
    .inbox-table input[type="checkbox"] {
        width: 18px;
        height: 18px;
        cursor: pointer;
    }
    
    .battle-type-badge {
        display: inline-block;
        padding: 0.25rem 0.5rem;
        border-radius: var(--radius-sm);
        font-size: 0.875rem;
        font-weight: 600;
    }
    
    .battle-type-attack {
        background: rgba(239, 68, 68, 0.2);
        color: #ef4444;
    }
    
    .battle-type-defense {
        background: rgba(59, 130, 246, 0.2);
        color: #3b82f6;
    }
    
    .battle-type-raid {
        background: rgba(234, 179, 8, 0.2);
        color: #eab308;
    }
    
    .inbox-actions {
        position: sticky;
        bottom: 0;
        background: var(--color-bg-secondary);
        padding: 1rem;
        border-top: 2px solid var(--color-border);
        display: flex;
        gap: 1rem;
        align-items: center;
    }
    
    .selected-count {
        color: var(--color-text-secondary);
        font-size: 0.875rem;
    }
    
    .preview-modal .modal-content {
        max-width: 900px;
        max-height: 90vh;
        overflow-y: auto;
    }
    
    .preview-content {
        background: var(--color-bg-primary);
        padding: 1rem;
        border-radius: var(--radius-md);
        font-family: monospace;
        white-space: pre-wrap;
        font-size: 0.875rem;
        line-height: 1.6;
    }
    
    .guild-content {
        display: none;
    }
    
    .guild-content.active {
        display: block;
    }
    </style>
</head>
<body>
    <?php renderNavbar('inbox'); ?>

    <main class="main-content">
        <div class="container">
            <div class="page-header" style="display: flex; justify-content: space-between; align-items: flex-start;">
                <div>
                    <h1>📥 Kampfbericht-Posteingang</h1>
                    <p class="page-description">
                        Überprüfe automatisch abgeholte Kampfberichte vor dem Import
                    </p>
                </div>
                <button onclick="fetchAllReports()" class="btn btn-primary" style="margin-top: 0.5rem;">
                    📥 Berichte abholen
                </button>
            </div>

            <?php if ($totalPending === 0): ?>
                <div class="card">
                    <div class="card-body" style="text-align: center; padding: 3rem;">
                        <div style="font-size: 4rem; margin-bottom: 1rem;">🔭</div>
                        <h2 style="margin-bottom: 0.5rem;">Posteingang ist leer</h2>
                        <p style="color: var(--color-text-secondary); margin-bottom: 2rem;">
                            Keine neuen Kampfberichte zum Importieren
                        </p>
                        <a href="/fights.php" class="btn btn-primary">
                            Zu den Kämpfen
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <!-- Guild Tabs -->
                <div class="guild-tabs">
                    <?php foreach ($guilds as $index => $guild): ?>
                        <button 
                            class="guild-tab <?php echo $index === 0 ? 'active' : ''; ?>" 
                            data-guild-id="<?php echo $guild['id']; ?>"
                            onclick="switchGuild(<?php echo $guild['id']; ?>)"
                        >
                            <?php echo htmlspecialchars($guild['name']); ?>
                            <span class="count"><?php echo $guild['pending_count']; ?></span>
                        </button>
                    <?php endforeach; ?>
                </div>

                <!-- Guild Content Areas -->
                <?php foreach ($guilds as $index => $guild): ?>
                    <div class="guild-content <?php echo $index === 0 ? 'active' : ''; ?>" id="guild-<?php echo $guild['id']; ?>">
                        <div class="card">
                            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                                <h2><?php echo htmlspecialchars($guild['name']); ?> (<?php echo $guild['pending_count']; ?>)</h2>
                                <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                                    <input type="checkbox" class="select-all" data-guild-id="<?php echo $guild['id']; ?>" style="width: 18px; height: 18px;">
                                    <span>Alle auswählen</span>
                                </label>
                            </div>
                            <div class="card-body" style="padding: 0;">
                                <table class="inbox-table">
                                    <thead>
                                        <tr>
                                            <th style="width: 50px;"></th>
                                            <th>Datum</th>
                                            <th>Zeit</th>
                                            <th>Typ</th>
                                            <th>Gegner</th>
                                            <th>Server</th>
                                            <th>Aktionen</th>
                                        </tr>
                                    </thead>
                                    <tbody class="inbox-table-body" data-guild-id="<?php echo $guild['id']; ?>">
                                        <tr>
                                            <td colspan="7" style="text-align: center; padding: 2rem;">
                                                ⏳ Lade Berichte...
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Actions Bar for this guild -->
                        <div class="inbox-actions">
                            <span class="selected-count">
                                <span class="selected-count-num" data-guild-id="<?php echo $guild['id']; ?>">0</span> ausgewählt
                            </span>
                            <button class="btn btn-primary btn-import" data-guild-id="<?php echo $guild['id']; ?>" disabled>
                                ✅ Ausgewählte importieren
                            </button>
                            <button class="btn btn-secondary btn-reject" data-guild-id="<?php echo $guild['id']; ?>" disabled>
                                ❌ Ausgewählte ablehnen
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>

    <!-- Preview Modal -->
    <div id="previewModal" class="modal preview-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); align-items: center; justify-content: center; z-index: 9999;">
        <div style="background: var(--color-bg-secondary); border-radius: var(--radius-lg); padding: 0; max-width: 900px; width: 90%; max-height: 90vh; overflow-y: auto; box-shadow: 0 10px 40px rgba(0,0,0,0.3);">
            <div style="padding: 1.5rem; border-bottom: 1px solid var(--color-border); display: flex; justify-content: space-between; align-items: center;">
                <h3 style="margin: 0;">Kampfbericht-Vorschau</h3>
                <button class="modal-close" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--color-text-secondary);">&times;</button>
            </div>
            <div style="padding: 1.5rem;">
                <div id="previewContent" class="preview-content"></div>
            </div>
        </div>
    </div>

    <script>
    // Modal Helper Functions
    function showAlert(message, title = 'Hinweis') {
        return new Promise((resolve) => {
            const modal = document.createElement('div');
            modal.className = 'modal-backdrop';
            modal.style.cssText = 'position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 9999;';
            modal.innerHTML = `
                <div style="background: var(--color-bg-secondary); border-radius: var(--radius-lg); padding: 0; max-width: 500px; width: 90%; box-shadow: 0 10px 40px rgba(0,0,0,0.3);">
                    <div style="padding: 1.5rem; border-bottom: 1px solid var(--color-border); display: flex; justify-content: space-between; align-items: center;">
                        <h3 style="margin: 0;">${title}</h3>
                        <button style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--color-text-secondary);">&times;</button>
                    </div>
                    <div style="padding: 1.5rem; white-space: pre-line;">${message}</div>
                    <div style="padding: 1rem 1.5rem; border-top: 1px solid var(--color-border); display: flex; justify-content: flex-end;">
                        <button class="btn btn-primary">OK</button>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
            
            const closeModal = () => {
                modal.remove();
                resolve();
            };
            
            modal.querySelector('button.btn-primary').onclick = closeModal;
            modal.querySelector('button[style*="font-size: 1.5rem"]').onclick = closeModal;
            modal.onclick = (e) => { if (e.target === modal) closeModal(); };
        });
    }

    function showConfirm(message, title = 'Bestätigung') {
        return new Promise((resolve) => {
            const modal = document.createElement('div');
            modal.className = 'modal-backdrop';
            modal.style.cssText = 'position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 9999;';
            modal.innerHTML = `
                <div style="background: var(--color-bg-secondary); border-radius: var(--radius-lg); padding: 0; max-width: 500px; width: 90%; box-shadow: 0 10px 40px rgba(0,0,0,0.3);">
                    <div style="padding: 1.5rem; border-bottom: 1px solid var(--color-border); display: flex; justify-content: space-between; align-items: center;">
                        <h3 style="margin: 0;">${title}</h3>
                        <button style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--color-text-secondary);">&times;</button>
                    </div>
                    <div style="padding: 1.5rem; white-space: pre-line;">${message}</div>
                    <div style="padding: 1rem 1.5rem; border-top: 1px solid var(--color-border); display: flex; justify-content: flex-end; gap: 0.5rem;">
                        <button class="btn btn-secondary btn-no">Nein</button>
                        <button class="btn btn-primary btn-yes">Ja</button>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
            
            const closeModal = (result) => {
                modal.remove();
                resolve(result);
            };
            
            modal.querySelector('.btn-yes').onclick = () => closeModal(true);
            modal.querySelector('.btn-no').onclick = () => closeModal(false);
            modal.querySelector('button[style*="font-size: 1.5rem"]').onclick = () => closeModal(false);
            modal.onclick = (e) => { if (e.target === modal) closeModal(false); };
        });
    }

    let allReports = [];
    let selectedIds = new Map(); // Map<guildId, Set<reportId>>

    // Load reports on page load
    document.addEventListener('DOMContentLoaded', loadReports);

    async function loadReports() {
        try {
            const response = await fetch('/api/inbox_list.php');
            const result = await response.json();
            
            if (result.success) {
                allReports = result.reports;
                renderAllTables();
            }
        } catch (error) {
            console.error('Error loading reports:', error);
        }
    }

    function renderAllTables() {
        // Group reports by guild
        const reportsByGuild = {};
        allReports.forEach(r => {
            if (!reportsByGuild[r.guild_id]) {
                reportsByGuild[r.guild_id] = [];
            }
            reportsByGuild[r.guild_id].push(r);
        });
        
        // Render each guild's table
        document.querySelectorAll('.inbox-table-body').forEach(tbody => {
            const guildId = parseInt(tbody.dataset.guildId);
            const reports = reportsByGuild[guildId] || [];
            
            if (reports.length === 0) {
                tbody.innerHTML = `
                    <tr><td colspan="7" style="text-align: center; padding: 2rem;">
                        Keine Berichte gefunden
                    </td></tr>
                `;
                return;
            }
            
            tbody.innerHTML = reports.map(r => `
                <tr>
                    <td>
                        <input type="checkbox" class="report-checkbox" data-id="${r.id}" data-guild-id="${r.guild_id}">
                    </td>
                    <td>${formatDate(r.battle_date)}</td>
                    <td>${r.battle_time}</td>
                    <td>
                        <span class="battle-type-badge battle-type-${r.battle_type}">
                            ${r.battle_type === 'attack' ? '⚔️ Angriff' : r.battle_type === 'defense' ? '🛡️ Verteidigung' : '🏰 Gildenraid'}
                        </span>
                    </td>
                    <td>${escapeHtml(r.opponent_guild)}</td>
                    <td><small>${escapeHtml(r.server)}</small></td>
                    <td>
                        <button onclick="previewReport(${r.id})" class="btn btn-small">
                            👁️ Ansehen
                        </button>
                    </td>
                </tr>
            `).join('');
            
            // Initialize selectedIds for this guild
            if (!selectedIds.has(guildId)) {
                selectedIds.set(guildId, new Set());
            }
        });
        
        // Add event listeners
        addEventListeners();
    }

    function addEventListeners() {
        // Checkbox listeners
        document.querySelectorAll('.report-checkbox').forEach(cb => {
            cb.addEventListener('change', updateSelection);
        });
        
        // Select all listeners
        document.querySelectorAll('.select-all').forEach(cb => {
            cb.addEventListener('change', function() {
                const guildId = parseInt(this.dataset.guildId);
                document.querySelectorAll(`.report-checkbox[data-guild-id="${guildId}"]`).forEach(checkbox => {
                    checkbox.checked = this.checked;
                });
                updateSelection();
            });
        });
        
        // Import button listeners
        document.querySelectorAll('.btn-import').forEach(btn => {
            btn.addEventListener('click', async function() {
                const guildId = parseInt(this.dataset.guildId);
                await handleImport(guildId);
            });
        });
        
        // Reject button listeners
        document.querySelectorAll('.btn-reject').forEach(btn => {
            btn.addEventListener('click', async function() {
                const guildId = parseInt(this.dataset.guildId);
                await handleReject(guildId);
            });
        });
    }

    function updateSelection() {
        // Update selectedIds for each guild
        selectedIds.forEach((set, guildId) => set.clear());
        
        document.querySelectorAll('.report-checkbox:checked').forEach(cb => {
            const reportId = parseInt(cb.dataset.id);
            const guildId = parseInt(cb.dataset.guildId);
            
            if (!selectedIds.has(guildId)) {
                selectedIds.set(guildId, new Set());
            }
            selectedIds.get(guildId).add(reportId);
        });
        
        // Update UI for each guild
        selectedIds.forEach((set, guildId) => {
            const count = set.size;
            const countElem = document.querySelector(`.selected-count-num[data-guild-id="${guildId}"]`);
            const importBtn = document.querySelector(`.btn-import[data-guild-id="${guildId}"]`);
            const rejectBtn = document.querySelector(`.btn-reject[data-guild-id="${guildId}"]`);
            
            if (countElem) countElem.textContent = count;
            if (importBtn) importBtn.disabled = count === 0;
            if (rejectBtn) rejectBtn.disabled = count === 0;
        });
    }

    async function handleImport(guildId) {
        const ids = Array.from(selectedIds.get(guildId) || []);
        if (ids.length === 0) return;
        
        const confirmed = await showConfirm(`${ids.length} Bericht(e) importieren?`, 'Bestätigung');
        if (!confirmed) return;
        
        const btn = document.querySelector(`.btn-import[data-guild-id="${guildId}"]`);
        btn.disabled = true;
        btn.textContent = '⏳ Importiere...';
        
        try {
            const response = await fetch('/api/inbox_import.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({report_ids: ids})
            });
            
            const result = await response.json();
            
            if (result.success) {
                const title = result.skipped > 0 ? 'Hinweis' : 'Erfolg';
                await showAlert(result.message || `${result.imported} importiert`, title);
                location.reload();
            } else {
                await showAlert('❌ ' + (result.error || 'Fehler beim Importieren'), 'Fehler');
            }
        } catch (error) {
            await showAlert('❌ Fehler beim Importieren', 'Fehler');
            console.error(error);
        } finally {
            btn.disabled = false;
            btn.textContent = '✅ Ausgewählte importieren';
        }
    }

    async function handleReject(guildId) {
        const ids = Array.from(selectedIds.get(guildId) || []);
        if (ids.length === 0) return;
        
        const confirmed = await showConfirm(`${ids.length} Bericht(e) ablehnen?`, 'Bestätigung');
        if (!confirmed) return;
        
        const btn = document.querySelector(`.btn-reject[data-guild-id="${guildId}"]`);
        btn.disabled = true;
        btn.textContent = '⏳ Lehne ab...';
        
        try {
            const response = await fetch('/api/inbox_reject.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({report_ids: ids})
            });
            
            const result = await response.json();
            
            if (result.success) {
                await showAlert(`✅ ${result.rejected} Bericht(e) abgelehnt`, 'Erfolg');
                location.reload();
            } else {
                await showAlert('❌ ' + (result.error || 'Fehler beim Ablehnen'), 'Fehler');
            }
        } catch (error) {
            await showAlert('❌ Fehler beim Ablehnen', 'Fehler');
            console.error(error);
        } finally {
            btn.disabled = false;
            btn.textContent = '❌ Ausgewählte ablehnen';
        }
    }

    function switchGuild(guildId) {
        // Update tabs
        document.querySelectorAll('.guild-tab').forEach(tab => {
            tab.classList.toggle('active', parseInt(tab.dataset.guildId) === guildId);
        });
        
        // Update content
        document.querySelectorAll('.guild-content').forEach(content => {
            content.classList.toggle('active', content.id === `guild-${guildId}`);
        });
    }

    // Preview report
    async function previewReport(id) {
        try {
            const response = await fetch(`/api/inbox_preview.php?id=${id}`);
            const result = await response.json();
            
            if (result.success) {
                document.getElementById('previewContent').textContent = result.content;
                document.getElementById('previewModal').style.display = 'flex';
            } else {
                await showAlert('❌ Fehler beim Laden der Vorschau', 'Fehler');
            }
        } catch (error) {
            await showAlert('❌ Fehler beim Laden der Vorschau', 'Fehler');
            console.error(error);
        }
    }

    // Close preview modal
    document.querySelector('#previewModal .modal-close').addEventListener('click', function() {
        document.getElementById('previewModal').style.display = 'none';
    });

    document.getElementById('previewModal').addEventListener('click', function(e) {
        if (e.target === this) {
            this.style.display = 'none';
        }
    });

    // Fetch all reports
    async function fetchAllReports() {
        const btn = event.target;
        btn.disabled = true;
        btn.textContent = '⏳ Hole Berichte ab...';
        
        try {
            const response = await fetch('/api/sf_fetch_reports.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({}) // Empty = fetch all selected characters
            });
            
            const result = await response.json();
            
            if (result.success) {
                // Build summary message
                let message = `✅ ${result.total} neue Bericht(e) abgeholt!\n\n`;
                
                result.results.forEach(r => {
                    const icon = r.success ? '✓' : '✗';
                    const status = r.success ? `${r.count} Berichte` : r.error;
                    const guildText = r.guild ? ` (${r.guild})` : '';
					message += `${icon} ${r.character}${guildText}: ${status}\n`;
                });
                
                await showAlert(message, 'Berichte abgeholt');
                location.reload();
            } else {
                await showAlert('❌ ' + (result.error || 'Fehler beim Abholen'), 'Fehler');
            }
        } catch (error) {
            await showAlert('❌ Fehler beim Abholen der Berichte', 'Fehler');
            console.error(error);
        } finally {
            btn.disabled = false;
            btn.textContent = '📥 Berichte abholen';
        }
    }

    // Helper functions
    function formatDate(dateStr) {
        const [y, m, d] = dateStr.split('-');
        return `${d}.${m}.${y}`;
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }
    </script>

    <?php renderFooter(); ?>
</body>
</html>