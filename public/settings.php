<?php
/**
 * Settings Page (Multi-Account Version)
 * User settings with multiple SF account support
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/template.php';
require_once __DIR__ . '/../config/database.php';

checkAuth();

$db = getDB();
$userId = $_SESSION['user_id'];

// Get user data
$stmt = $db->prepare("SELECT username FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Get SF accounts
$stmt = $db->prepare("SELECT id, account_name, sf_username, selected_characters, is_default, updated_at FROM sf_accounts WHERE user_id = ? ORDER BY is_default DESC, created_at ASC");
$stmt->execute([$userId]);
$sfAccounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Decode selected_characters for each account
foreach ($sfAccounts as &$acc) {
    $acc['selected_characters'] = $acc['selected_characters'] ? json_decode($acc['selected_characters'], true) : [];
}
unset($acc);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <?php renderHead('Einstellungen'); ?>
    <style>
    .settings-section {
        margin-bottom: 2rem;
    }
    
    .settings-section h2 {
        margin-bottom: 1rem;
        padding-bottom: 0.5rem;
        border-bottom: 2px solid var(--color-border);
    }
    
    .form-group {
        margin-bottom: 1.5rem;
    }
    
    .form-group label {
        display: block;
        margin-bottom: 0.5rem;
        font-weight: 600;
        color: var(--color-text-primary);
    }
    
    .form-group input[type="text"],
    .form-group input[type="password"] {
        width: 100%;
        max-width: 400px;
        padding: 0.75rem;
        border: 1px solid var(--color-border);
        border-radius: var(--radius-md);
        background: var(--color-bg-primary);
        color: var(--color-text-primary);
        font-size: 1rem;
    }
    
    .form-group input[type="text"]:focus,
    .form-group input[type="password"]:focus {
        outline: none;
        border-color: var(--color-primary);
    }
    
    .form-help {
        margin-top: 0.5rem;
        font-size: 0.875rem;
        color: var(--color-text-secondary);
    }
    
    /* Account Cards */
    .account-list {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }
    
    .account-card {
        border: 1px solid var(--color-border);
        border-radius: var(--radius-md);
        background: var(--color-bg-primary);
        overflow: hidden;
        transition: border-color 0.2s;
    }
    
    .account-card.is-default {
        border-color: var(--color-primary);
    }
    
    .account-card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1rem 1.25rem;
        background: var(--color-bg-secondary);
        border-bottom: 1px solid var(--color-border);
        cursor: pointer;
        user-select: none;
    }
    
    .account-card-header:hover {
        background: var(--color-bg-hover);
    }
    
    .account-card-title {
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    
    .account-card-title h3 {
        margin: 0;
        font-size: 1rem;
    }
    
    .account-card-meta {
        font-size: 0.8rem;
        color: var(--color-text-secondary);
    }
    
    .account-card-badges {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .badge {
        display: inline-block;
        padding: 0.2rem 0.6rem;
        border-radius: var(--radius-sm);
        font-size: 0.75rem;
        font-weight: 600;
    }
    
    .badge-default {
        background: rgba(99, 102, 241, 0.2);
        color: var(--color-primary);
    }
    
    .badge-chars {
        background: rgba(34, 197, 94, 0.15);
        color: #22c55e;
    }
    
    .badge-no-chars {
        background: rgba(239, 68, 68, 0.15);
        color: #ef4444;
    }
    
    .account-card-body {
        padding: 1.25rem;
        display: none;
    }
    
    .account-card.expanded .account-card-body {
        display: block;
    }
    
    .account-card-header .expand-icon {
        transition: transform 0.2s;
        color: var(--color-text-secondary);
    }
    
    .account-card.expanded .expand-icon {
        transform: rotate(180deg);
    }
    
    /* Character List */
    .character-list {
        margin-top: 1rem;
        max-height: 400px;
        overflow-y: auto;
        border: 1px solid var(--color-border);
        border-radius: var(--radius-md);
        background: var(--color-bg-primary);
    }
    
    .character-item {
        display: flex;
        align-items: center;
        padding: 0.75rem 1rem;
        border-bottom: 1px solid var(--color-border);
        transition: background 0.2s;
    }
    
    .character-item:last-child {
        border-bottom: none;
    }
    
    .character-item:hover {
        background: var(--color-bg-hover);
    }
    
    .character-checkbox {
        width: 20px;
        height: 20px;
        margin-right: 1rem;
        cursor: pointer;
    }
    
    .character-info {
        flex: 1;
    }
    
    .character-name {
        font-weight: 600;
        color: var(--color-text-primary);
    }
    
    .character-meta {
        font-size: 0.875rem;
        color: var(--color-text-secondary);
        margin-top: 0.25rem;
    }
    
    .character-guild {
        color: var(--color-primary);
    }
    
    .btn-group {
        display: flex;
        gap: 0.75rem;
        margin-top: 1rem;
        flex-wrap: wrap;
    }
    
    .btn-group-right {
        margin-left: auto;
    }
    
    /* Add Account Button */
    .btn-add-account {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        padding: 1rem;
        border: 2px dashed var(--color-border);
        border-radius: var(--radius-md);
        background: transparent;
        color: var(--color-text-secondary);
        font-size: 1rem;
        cursor: pointer;
        transition: all 0.2s;
        width: 100%;
    }
    
    .btn-add-account:hover {
        border-color: var(--color-primary);
        color: var(--color-primary);
        background: rgba(99, 102, 241, 0.05);
    }
    
    /* Loading Spinner */
    .loading-spinner {
        display: inline-block;
        width: 16px;
        height: 16px;
        border: 2px solid var(--color-border);
        border-top-color: var(--color-primary);
        border-radius: 50%;
        animation: spin 0.6s linear infinite;
    }
    
    @keyframes spin {
        to { transform: rotate(360deg); }
    }
    
    /* Modal Overlay */
    .modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.5);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 9999;
    }
    
    .modal-content {
        background: var(--color-bg-secondary);
        border-radius: var(--radius-lg);
        max-width: 500px;
        width: 90%;
        box-shadow: 0 10px 40px rgba(0,0,0,0.3);
    }
    
    .modal-header {
        padding: 1.25rem 1.5rem;
        border-bottom: 1px solid var(--color-border);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .modal-header h3 {
        margin: 0;
    }
    
    .modal-body {
        padding: 1.5rem;
    }
    
    .modal-footer {
        padding: 1rem 1.5rem;
        border-top: 1px solid var(--color-border);
        display: flex;
        justify-content: flex-end;
        gap: 0.75rem;
    }
    
    .btn-close-modal {
        background: none;
        border: none;
        font-size: 1.5rem;
        cursor: pointer;
        color: var(--color-text-secondary);
        padding: 0;
        line-height: 1;
    }
    
    /* Saved Characters Preview */
    .saved-chars-preview {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        margin-top: 0.75rem;
    }
    
    .saved-char-tag {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        padding: 0.3rem 0.7rem;
        background: rgba(99, 102, 241, 0.1);
        border: 1px solid rgba(99, 102, 241, 0.3);
        border-radius: var(--radius-sm);
        font-size: 0.8rem;
        color: var(--color-text-primary);
    }
    
    .saved-char-guild {
        color: var(--color-primary);
        font-size: 0.75rem;
    }
    </style>
</head>
<body>
    <?php renderNavbar(); ?>

    <main class="main-content">
        <div class="container">
            <div class="page-header">
                <h1>⚙️ Einstellungen</h1>
                <p class="page-description">
                    Verwalte deine Account-Einstellungen und S&F Verbindungen
                </p>
            </div>

            <!-- Account Info -->
            <div class="card settings-section">
                <div class="card-header">
                    <h2>Account</h2>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label>Benutzername</label>
                        <input type="text" value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
                    </div>
                </div>
            </div>

            <!-- Passwort ändern -->
            <div class="card settings-section">
                <div class="card-header">
                    <h2>Passwort ändern</h2>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label for="currentPassword">Aktuelles Passwort</label>
                        <input type="password" id="currentPassword" placeholder="••••••••">
                    </div>
                    <div class="form-group">
                        <label for="newPassword">Neues Passwort</label>
                        <input type="password" id="newPassword" placeholder="Mindestens 8 Zeichen">
                    </div>
                    <div class="form-group">
                        <label for="newPasswordConfirm">Neues Passwort bestätigen</label>
                        <input type="password" id="newPasswordConfirm" placeholder="••••••••">
                    </div>
                    <div id="passwordChangeMsg" style="display:none;margin-bottom:0.75rem;font-size:0.875rem"></div>
                    <button class="btn btn-primary" onclick="changePassword()">Passwort ändern</button>
                </div>
            </div>

            <!-- SF Accounts -->
            <div class="card settings-section">
                <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                    <h2>Shakes & Fidget Accounts</h2>
                    <span style="font-size: 0.875rem; color: var(--color-text-secondary);">
                        <?php echo count($sfAccounts); ?> Account(s) verknüpft
                    </span>
                </div>
                <div class="card-body">
                    <p class="form-help" style="margin-bottom: 1rem;">
                        Verknüpfe deine S&F Accounts, um Kampfberichte automatisch abzurufen.
                        Pro Account kannst du auswählen, von welchen Charakteren Berichte geholt werden sollen.
                    </p>
                    
                    <div class="account-list" id="accountList">
                        <!-- Accounts werden per JS gerendert -->
                    </div>
                    
                    <button class="btn-add-account" onclick="showAccountModal()" style="margin-top: 1rem;">
                        + Account hinzufügen
                    </button>
                </div>
            </div>

        </div>
    </main>

    <script>
    // State
    let accounts = <?php echo json_encode($sfAccounts); ?>;
    let characterCache = {}; // account_id -> characters array

    // ===== PASSWORT ÄNDERN =====

    async function changePassword() {
        const current = document.getElementById('currentPassword').value;
        const newPw   = document.getElementById('newPassword').value;
        const confirm = document.getElementById('newPasswordConfirm').value;
        const msg     = document.getElementById('passwordChangeMsg');

        const show = (text, ok) => {
            msg.textContent = text;
            msg.style.color = ok ? 'var(--color-success)' : 'var(--color-error)';
            msg.style.display = 'block';
        };

        if (!current || !newPw || !confirm) { show('Bitte alle Felder ausfüllen.', false); return; }
        if (newPw !== confirm)              { show('Neue Passwörter stimmen nicht überein.', false); return; }
        if (newPw.length < 8)              { show('Neues Passwort muss mindestens 8 Zeichen lang sein.', false); return; }

        try {
            const r = await fetch('/api/change_password.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ current_password: current, new_password: newPw })
            });
            const d = await r.json();
            show(d.message, d.success);
            if (d.success) {
                document.getElementById('currentPassword').value = '';
                document.getElementById('newPassword').value = '';
                document.getElementById('newPasswordConfirm').value = '';
            }
        } catch (e) {
            show('Fehler: ' + e.message, false);
        }
    }

    
    
    function renderAccounts() {
        const container = document.getElementById('accountList');
        
        if (accounts.length === 0) {
            container.innerHTML = `
                <div style="text-align: center; padding: 2rem; color: var(--color-text-secondary);">
                    Noch keine S&F Accounts verknüpft. Füge deinen ersten Account hinzu!
                </div>
            `;
            return;
        }
        
        container.innerHTML = accounts.map(acc => {
            const charCount = (acc.selected_characters || []).length;
            const guilds = [...new Set((acc.selected_characters || []).map(c => c.guild).filter(Boolean))];
            const isExpanded = acc._expanded ? 'expanded' : '';
            const isDefault = acc.is_default == 1;
            
            return `
                <div class="account-card ${isDefault ? 'is-default' : ''} ${isExpanded}" data-id="${acc.id}">
                    <div class="account-card-header" onclick="toggleAccount(${acc.id})">
                        <div class="account-card-title">
                            <div>
                                <h3>${escapeHtml(acc.account_name)}</h3>
                                <div class="account-card-meta">${escapeHtml(acc.sf_username)}</div>
                            </div>
                        </div>
                        <div class="account-card-badges">
                            ${isDefault ? '<span class="badge badge-default">Standard</span>' : ''}
                            ${charCount > 0 
                                ? `<span class="badge badge-chars">${charCount} Charakter(e)</span>`
                                : '<span class="badge badge-no-chars">Keine Auswahl</span>'
                            }
                            <span class="expand-icon">▼</span>
                        </div>
                    </div>
                    <div class="account-card-body">
                        ${renderAccountBody(acc)}
                    </div>
                </div>
            `;
        }).join('');
    }
    
    function renderAccountBody(acc) {
        const chars = acc.selected_characters || [];
        
        let savedCharsHtml = '';
        if (chars.length > 0) {
            savedCharsHtml = `
                <div style="margin-bottom: 1.5rem;">
                    <label style="font-weight: 600; display: block; margin-bottom: 0.5rem;">Gespeicherte Auswahl:</label>
                    <div class="saved-chars-preview">
                        ${chars.map(c => `
                            <span class="saved-char-tag">
                                ${escapeHtml(c.name)}
                                ${c.guild ? `<span class="saved-char-guild">(${escapeHtml(c.guild)})</span>` : ''}
                            </span>
                        `).join('')}
                    </div>
                </div>
            `;
        }
        
        return `
            ${savedCharsHtml}
            
            <div id="charSection-${acc.id}" style="display: none;">
                <label style="font-weight: 600; display: block; margin-bottom: 0.5rem;">Charakter-Auswahl:</label>
                <div id="charList-${acc.id}" class="character-list">
                    <div style="text-align: center; padding: 2rem; color: var(--color-text-secondary);">
                        Klicke "Charaktere laden" um die Charaktere dieses Accounts abzurufen.
                    </div>
                </div>
                <div class="btn-group" style="margin-top: 0.75rem;">
                    <button class="btn btn-primary" onclick="saveCharacters(${acc.id})" id="btnSaveChars-${acc.id}" disabled>
                        Auswahl speichern
                    </button>
                    <span id="charCount-${acc.id}" style="color: var(--color-text-secondary); align-self: center; font-size: 0.875rem;"></span>
                </div>
            </div>
            
            <div class="btn-group">
                <button class="btn btn-primary" onclick="loadCharacters(${acc.id})" id="btnLoadChars-${acc.id}">
                    Charaktere laden
                </button>
                <button class="btn btn-secondary" onclick="showAccountModal(${acc.id})">
                    Bearbeiten
                </button>
                ${acc.is_default != 1 ? `
                    <button class="btn btn-secondary" onclick="setDefault(${acc.id})">
                        Als Standard setzen
                    </button>
                ` : ''}
                <div class="btn-group-right">
                    <button class="btn btn-danger" onclick="deleteAccount(${acc.id})">
                        Löschen
                    </button>
                </div>
            </div>
        `;
    }
    
    // ===== ACCOUNT ACTIONS =====
    
    function toggleAccount(id) {
        const acc = accounts.find(a => a.id === id);
        if (acc) {
            acc._expanded = !acc._expanded;
            renderAccounts();
        }
    }
    
    function showAccountModal(editId = null) {
        const acc = editId ? accounts.find(a => a.id === editId) : null;
        const isEdit = !!acc;
        
        const modal = document.createElement('div');
        modal.className = 'modal-overlay';
        modal.innerHTML = `
            <div class="modal-content">
                <div class="modal-header">
                    <h3>${isEdit ? 'Account bearbeiten' : 'Neuen Account hinzufügen'}</h3>
                    <button class="btn-close-modal" onclick="this.closest('.modal-overlay').remove()">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="modal_account_name">Account-Name</label>
                        <input type="text" id="modal_account_name" placeholder="z.B. Haupt-Account, Zweitaccount..."
                               value="${isEdit ? escapeHtml(acc.account_name) : ''}">
                        <p class="form-help">Ein Name zur Unterscheidung deiner Accounts</p>
                    </div>
                    <div class="form-group">
                        <label for="modal_sf_username">S&F Benutzername</label>
                        <input type="text" id="modal_sf_username" placeholder="DeinBenutzername"
                               value="${isEdit ? escapeHtml(acc.sf_username) : ''}">
                        <p class="form-help">Dein Shakes & Fidget Login-Name (nicht Charaktername)</p>
                    </div>
                    <div class="form-group">
                        <label for="modal_sf_password">S&F Passwort</label>
                        <input type="password" id="modal_sf_password" placeholder="${isEdit ? '(unverändert lassen)' : '••••••••'}">
                        <p class="form-help">Wird verschlüsselt gespeichert${isEdit ? '. Leer lassen = Passwort bleibt unverändert' : ''}</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" onclick="this.closest('.modal-overlay').remove()">Abbrechen</button>
                    <button class="btn btn-primary" id="btnSaveAccount" onclick="saveAccount(${editId || 'null'})">
                        ${isEdit ? 'Speichern' : 'Hinzufügen'}
                    </button>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        
        // Close on backdrop click
        modal.addEventListener('click', (e) => {
            if (e.target === modal) modal.remove();
        });
        
        // Focus first field
        setTimeout(() => document.getElementById('modal_account_name').focus(), 100);
    }
    
    async function saveAccount(editId) {
        const accountName = document.getElementById('modal_account_name').value.trim();
        const sfUsername = document.getElementById('modal_sf_username').value.trim();
        const sfPassword = document.getElementById('modal_sf_password').value;
        
        if (!sfUsername) {
            await showAlert('Bitte S&F Benutzername eingeben', 'Fehler');
            return;
        }
        
        if (!editId && !sfPassword) {
            await showAlert('Bitte Passwort eingeben', 'Fehler');
            return;
        }
        
        const btn = document.getElementById('btnSaveAccount');
        btn.disabled = true;
        btn.innerHTML = '<span class="loading-spinner"></span> Speichere...';
        
        try {
            const body = {
                account_name: accountName || sfUsername,
                sf_username: sfUsername
            };
            
            if (editId) body.id = editId;
            if (sfPassword) body.sf_password = sfPassword;
            
            const response = await fetch('/api/sf_account_manage.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(body)
            });
            
            const result = await response.json();
            
            if (result.success) {
                document.querySelector('.modal-overlay')?.remove();
                await showAlert(editId ? 'Account aktualisiert!' : 'Account hinzugefügt!', 'Erfolg');
                location.reload();
            } else {
                await showAlert(result.error || 'Fehler beim Speichern', 'Fehler');
            }
        } catch (error) {
            await showAlert('Fehler: ' + error.message, 'Fehler');
        } finally {
            btn.disabled = false;
            btn.textContent = editId ? 'Speichern' : 'Hinzufügen';
        }
    }
    
    async function deleteAccount(id) {
        const acc = accounts.find(a => a.id === id);
        const confirmed = await showConfirm(
            `Account "${acc.account_name}" wirklich löschen?\n\nGespeicherte Zugangsdaten und Charakter-Auswahl werden gelöscht.`,
            'Account löschen'
        );
        
        if (!confirmed) return;
        
        try {
            const response = await fetch('/api/sf_account_manage.php', {
                method: 'DELETE',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ id })
            });
            
            const result = await response.json();
            
            if (result.success) {
                await showAlert('Account gelöscht', 'Erfolg');
                location.reload();
            } else {
                await showAlert(result.error || 'Fehler beim Löschen', 'Fehler');
            }
        } catch (error) {
            await showAlert('Fehler: ' + error.message, 'Fehler');
        }
    }
    
    async function setDefault(id) {
        try {
            const acc = accounts.find(a => a.id === id);
            const response = await fetch('/api/sf_account_manage.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    id: id,
                    account_name: acc.account_name,
                    sf_username: acc.sf_username,
                    is_default: true
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                location.reload();
            } else {
                await showAlert(result.error || 'Fehler', 'Fehler');
            }
        } catch (error) {
            await showAlert('Fehler: ' + error.message, 'Fehler');
        }
    }
    
    // ===== CHARACTER LOADING =====
    
    async function loadCharacters(accountId) {
        const btn = document.getElementById(`btnLoadChars-${accountId}`);
        btn.disabled = true;
        btn.innerHTML = '<span class="loading-spinner"></span> Lade Charaktere...';
        
        try {
            const response = await fetch('/api/sf_get_characters.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ account_id: accountId })
            });
            
            const result = await response.json();
            
            if (result.success && result.characters.length > 0) {
                characterCache[accountId] = result.characters;
                renderCharacterList(accountId);
                document.getElementById(`charSection-${accountId}`).style.display = 'block';
                await showAlert(`${result.characters.length} Charakter(e) geladen!`, 'Erfolg');
            } else {
                await showAlert(result.error || 'Keine Charaktere gefunden oder Login fehlgeschlagen', 'Fehler');
            }
        } catch (error) {
            await showAlert('Fehler: ' + error.message, 'Fehler');
        } finally {
            btn.disabled = false;
            btn.textContent = 'Charaktere laden';
        }
    }
    
    function renderCharacterList(accountId) {
        const container = document.getElementById(`charList-${accountId}`);
        const characters = characterCache[accountId] || [];
        const acc = accounts.find(a => a.id === accountId);
        const savedChars = acc?.selected_characters || [];
        
        container.innerHTML = characters.map(char => {
            const isSelected = savedChars.some(
                s => s.name === char.name && s.server === char.server
            );
            
            return `
                <div class="character-item">
                    <input 
                        type="checkbox" 
                        class="character-checkbox" 
                        data-account="${accountId}"
                        data-name="${escapeHtml(char.name)}"
                        data-server="${escapeHtml(char.server)}"
                        data-guild="${escapeHtml(char.guild || '')}"
                        data-level="${char.level || 0}"
                        ${isSelected ? 'checked' : ''}
                        onchange="updateCharCount(${accountId})"
                    >
                    <div class="character-info">
                        <div class="character-name">${escapeHtml(char.name)}</div>
                        <div class="character-meta">
                            Level ${char.level || '?'} · 
                            <span class="character-guild">${escapeHtml(char.guild || 'Keine Gilde')}</span> · 
                            ${escapeHtml(char.server)}
                        </div>
                    </div>
                </div>
            `;
        }).join('');
        
        updateCharCount(accountId);
    }
    
    function updateCharCount(accountId) {
        const checked = document.querySelectorAll(`.character-checkbox[data-account="${accountId}"]:checked`);
        const countEl = document.getElementById(`charCount-${accountId}`);
        const saveBtn = document.getElementById(`btnSaveChars-${accountId}`);
        
        if (countEl) countEl.textContent = `${checked.length} ausgewählt`;
        if (saveBtn) saveBtn.disabled = checked.length === 0;
    }
    
    async function saveCharacters(accountId) {
        const checkboxes = document.querySelectorAll(`.character-checkbox[data-account="${accountId}"]:checked`);
        const selected = [];
        
        checkboxes.forEach(cb => {
            selected.push({
                name: cb.dataset.name,
                server: cb.dataset.server,
                guild: cb.dataset.guild,
                level: parseInt(cb.dataset.level) || 0
            });
        });
        
        if (selected.length === 0) return;
        
        const btn = document.getElementById(`btnSaveChars-${accountId}`);
        btn.disabled = true;
        btn.textContent = 'Speichere...';
        
        try {
            const response = await fetch('/api/sf_save_characters.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ account_id: accountId, characters: selected })
            });
            
            const result = await response.json();
            
            if (result.success) {
                // Update local state
                const acc = accounts.find(a => a.id === accountId);
                if (acc) acc.selected_characters = selected;
                
                await showAlert(`${result.count} Charakter(e) gespeichert!`, 'Erfolg');
                renderAccounts();
                
                // Re-expand and show char section
                const accObj = accounts.find(a => a.id === accountId);
                if (accObj) accObj._expanded = true;
                renderAccounts();
                
                // Re-render character list if cache exists
                if (characterCache[accountId]) {
                    document.getElementById(`charSection-${accountId}`).style.display = 'block';
                    renderCharacterList(accountId);
                }
            } else {
                await showAlert(result.error || 'Fehler beim Speichern', 'Fehler');
            }
        } catch (error) {
            await showAlert('Fehler: ' + error.message, 'Fehler');
        } finally {
            btn.disabled = false;
            btn.textContent = 'Auswahl speichern';
        }
    }
    
    // ===== HELPERS =====
    
    function showAlert(message, title = 'Hinweis') {
        return new Promise((resolve) => {
            const modal = document.createElement('div');
            modal.className = 'modal-overlay';
            modal.innerHTML = `
                <div class="modal-content">
                    <div class="modal-header">
                        <h3>${title}</h3>
                        <button class="btn-close-modal" onclick="this.closest('.modal-overlay').remove()">&times;</button>
                    </div>
                    <div class="modal-body" style="white-space: pre-line;">${message}</div>
                    <div class="modal-footer">
                        <button class="btn btn-primary" id="alertOkBtn">OK</button>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
            
            const closeModal = () => {
                modal.remove();
                resolve();
            };
            
            modal.querySelector('#alertOkBtn').onclick = closeModal;
            modal.querySelector('.btn-close-modal').onclick = closeModal;
            modal.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });
        });
    }
    
    function showConfirm(message, title = 'Bestätigung') {
        return new Promise((resolve) => {
            const modal = document.createElement('div');
            modal.className = 'modal-overlay';
            modal.innerHTML = `
                <div class="modal-content">
                    <div class="modal-header">
                        <h3>${title}</h3>
                        <button class="btn-close-modal">&times;</button>
                    </div>
                    <div class="modal-body" style="white-space: pre-line;">${message}</div>
                    <div class="modal-footer">
                        <button class="btn btn-secondary" id="confirmCancelBtn">Abbrechen</button>
                        <button class="btn btn-danger" id="confirmOkBtn">Löschen</button>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
            
            const close = (result) => { modal.remove(); resolve(result); };
            
            modal.querySelector('#confirmOkBtn').onclick = () => close(true);
            modal.querySelector('#confirmCancelBtn').onclick = () => close(false);
            modal.querySelector('.btn-close-modal').onclick = () => close(false);
            modal.addEventListener('click', (e) => { if (e.target === modal) close(false); });
        });
    }

    function escapeHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }
    
    // Initial render
    renderAccounts();
    </script>

    <?php renderFooter(); ?>
</body>
</html>
