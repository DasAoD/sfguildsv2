<?php
/**
 * Settings Page
 * User settings including S&F account connection and character selection
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/template.php';
require_once __DIR__ . '/../config/database.php';

checkAuth();

$db = getDB();
$userId = $_SESSION['user_id'];

// Get user data
$stmt = $db->prepare("SELECT username, sf_username, selected_characters FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$hasCredentials = !empty($user['sf_username']);
$selectedCharacters = $user['selected_characters'] ? json_decode($user['selected_characters'], true) : [];
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
    
    .status-badge {
        display: inline-block;
        padding: 0.25rem 0.75rem;
        border-radius: var(--radius-sm);
        font-size: 0.875rem;
        font-weight: 600;
    }
    
    .status-connected {
        background: rgba(34, 197, 94, 0.2);
        color: #22c55e;
    }
    
    .status-disconnected {
        background: rgba(239, 68, 68, 0.2);
        color: #ef4444;
    }
    
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
        gap: 1rem;
        margin-top: 1rem;
    }
    
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
    </style>
</head>
<body>
    <?php renderNavbar(); ?>

    <main class="main-content">
        <div class="container">
            <div class="page-header">
                <h1>⚙️ Einstellungen</h1>
                <p class="page-description">
                    Verwalte deine Account-Einstellungen und S&F Verbindung
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

            <!-- S&F Connection -->
            <div class="card settings-section">
                <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                    <h2>Shakes & Fidget Verbindung</h2>
                    <?php if ($hasCredentials): ?>
                        <span class="status-badge status-connected">✓ Verbunden</span>
                    <?php else: ?>
                        <span class="status-badge status-disconnected">✗ Nicht verbunden</span>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label for="sf_username">S&F Benutzername</label>
                        <input 
                            type="text" 
                            id="sf_username" 
                            placeholder="DeinBenutzername"
                            value="<?php echo htmlspecialchars($user['sf_username'] ?? ''); ?>"
                        >
                        <p class="form-help">Dein Shakes & Fidget Login-Name (nicht Charaktername)</p>
                    </div>

                    <div class="form-group">
                        <label for="sf_password">S&F Passwort</label>
                        <input 
                            type="password" 
                            id="sf_password" 
                            placeholder="••••••••"
                        >
                        <p class="form-help">Wird verschlüsselt gespeichert</p>
                    </div>

                    <div class="btn-group">
                        <button id="btnTestConnection" class="btn btn-primary">
                            Verbindung testen & Charaktere laden
                        </button>
                        <button id="btnSaveCredentials" class="btn btn-secondary">
                            Zugangsdaten speichern
                        </button>
                        <?php if ($hasCredentials): ?>
                            <button id="btnDisconnect" class="btn btn-danger">
                                Verbindung trennen
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Character Selection -->
            <div class="card settings-section" id="characterSection" style="display: <?php echo $hasCredentials ? 'block' : 'none'; ?>;">
                <div class="card-header">
                    <h2>Charakter-Auswahl für Berichte</h2>
                    <p class="form-help" style="margin-top: 0.5rem;">
                        Wähle die Charaktere aus, von denen automatisch Kampfberichte abgeholt werden sollen.
                        <span id="selectedCount" style="font-weight: 600; color: var(--color-primary);">
                            <?php echo count($selectedCharacters); ?> ausgewählt
                        </span>
                    </p>
                </div>
                <div class="card-body">
                    <div id="characterList" class="character-list">
                        <div style="text-align: center; padding: 3rem; color: var(--color-text-secondary);">
                            Klicke auf "Verbindung testen" um deine Charaktere zu laden
                        </div>
                    </div>

                    <div class="btn-group">
                        <button id="btnSaveSelection" class="btn btn-primary" disabled>
                            Auswahl speichern
                        </button>
                    </div>
                </div>
            </div>

        </div>
    </main>

    <script>
    let allCharacters = [];
    let selectedCharacters = <?php echo json_encode($selectedCharacters); ?>;

    // Modal Helper
    function showAlert(message, title = 'Hinweis') {
        return new Promise((resolve) => {
            const modal = document.createElement('div');
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

    // Test Connection
    document.getElementById('btnTestConnection').addEventListener('click', async function() {
        const username = document.getElementById('sf_username').value.trim();
        const password = document.getElementById('sf_password').value;

        // Wenn Username gefüllt, aber Passwort leer → Aus DB laden
        if (username && !password) {
            this.disabled = true;
            this.innerHTML = '<span class="loading-spinner"></span> Lade Charaktere...';

            try {
                const response = await fetch('/api/sf_get_characters.php', {
                    method: 'GET'
                });

                const result = await response.json();

                if (result.success) {
                    allCharacters = result.characters;
                    renderCharacterList();
                    document.getElementById('characterSection').style.display = 'block';
                    await showAlert(`✅ ${result.characters.length} Charakter(e) geladen!`, 'Erfolg');
                } else {
                    await showAlert(result.error || 'Fehler beim Laden', 'Fehler');
                }
            } catch (error) {
                await showAlert('Fehler: ' + error.message, 'Fehler');
            } finally {
                this.disabled = false;
                this.textContent = '🔍 Verbindung testen & Charaktere laden';
            }
            return;
        }
        
        // Wenn beides leer oder nur eins gefüllt: Fehler
        if (!username || !password) {
            await showAlert('Bitte Benutzername und Passwort eingeben', 'Fehler');
            return;
        }

        this.disabled = true;
        this.innerHTML = '<span class="loading-spinner"></span> Teste Verbindung...';

        try {
            const response = await fetch('/api/sf_get_characters.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({username, password})
            });

            const result = await response.json();

            if (result.success && result.characters.length > 0) {
                allCharacters = result.characters;
                renderCharacterList();
                document.getElementById('characterSection').style.display = 'block';
                await showAlert(`✅ ${result.characters.length} Charaktere gefunden!`, 'Erfolg');
            } else {
                await showAlert('Keine Charaktere gefunden oder Login fehlgeschlagen', 'Fehler');
            }
        } catch (error) {
            await showAlert('Verbindungstest fehlgeschlagen', 'Fehler');
            console.error(error);
        } finally {
            this.disabled = false;
            this.textContent = '🔍 Verbindung testen & Charaktere laden';
        }
    });
    // Save Credentials
    document.getElementById('btnSaveCredentials').addEventListener('click', async function() {
        const username = document.getElementById('sf_username').value.trim();
        const password = document.getElementById('sf_password').value;

        if (!username || !password) {
            await showAlert('Bitte Benutzername und Passwort eingeben', 'Fehler');
            return;
        }

        this.disabled = true;
        this.textContent = 'Speichere...';

        try {
            const response = await fetch('/api/sf_save_credentials.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({username, password})
            });

            const result = await response.json();

            if (result.success) {
                await showAlert('Zugangsdaten erfolgreich gespeichert!', 'Erfolg');
                location.reload();
            } else {
                await showAlert(result.error || 'Fehler beim Speichern', 'Fehler');
            }
        } catch (error) {
            await showAlert('Fehler beim Speichern', 'Fehler');
            console.error(error);
        } finally {
            this.disabled = false;
            this.textContent = 'Zugangsdaten speichern';
        }
    });

    // Disconnect
    <?php if ($hasCredentials): ?>
    document.getElementById('btnDisconnect').addEventListener('click', async function() {
        if (!confirm('Verbindung wirklich trennen? Gespeicherte Zugangsdaten werden gelöscht.')) return;

        this.disabled = true;
        this.textContent = 'Trenne...';

        try {
            const response = await fetch('/api/sf_disconnect.php', {method: 'POST'});
            const result = await response.json();

            if (result.success) {
                await showAlert('Verbindung getrennt', 'Erfolg');
                location.reload();
            }
        } catch (error) {
            await showAlert('Fehler beim Trennen', 'Fehler');
        }
    });
    <?php endif; ?>

    // Render Character List
    function renderCharacterList() {
        const container = document.getElementById('characterList');
        
        container.innerHTML = allCharacters.map(char => {
            const isSelected = selectedCharacters.some(
                s => s.name === char.name && s.server === char.server
            );
            
            return `
                <div class="character-item">
                    <input 
                        type="checkbox" 
                        class="character-checkbox" 
                        data-name="${escapeHtml(char.name)}"
                        data-server="${escapeHtml(char.server)}"
                        data-guild="${escapeHtml(char.guild || '')}"
                        ${isSelected ? 'checked' : ''}
                    >
                    <div class="character-info">
                        <div class="character-name">${escapeHtml(char.name)}</div>
                        <div class="character-meta">
                            Level ${char.level} • 
                            <span class="character-guild">${escapeHtml(char.guild || 'Keine Gilde')}</span> • 
                            ${escapeHtml(char.server)}
                        </div>
                    </div>
                </div>
            `;
        }).join('');

        // Add event listeners
        document.querySelectorAll('.character-checkbox').forEach(cb => {
            cb.addEventListener('change', updateSelection);
        });

        updateSelection();
    }

    // Update Selection
    function updateSelection() {
        selectedCharacters = [];
        
        document.querySelectorAll('.character-checkbox:checked').forEach(cb => {
            selectedCharacters.push({
                name: cb.dataset.name,
                server: cb.dataset.server,
                guild: cb.dataset.guild
            });
        });

        document.getElementById('selectedCount').textContent = `${selectedCharacters.length} ausgewählt`;
        document.getElementById('btnSaveSelection').disabled = selectedCharacters.length === 0;
    }

    // Save Selection
    document.getElementById('btnSaveSelection').addEventListener('click', async function() {
        if (selectedCharacters.length === 0) return;

        this.disabled = true;
        this.textContent = 'Speichere...';

        try {
            const response = await fetch('/api/sf_save_characters.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({characters: selectedCharacters})
            });

            const result = await response.json();

            if (result.success) {
                await showAlert(`✅ ${result.count} Charakter(e) gespeichert!`, 'Erfolg');
            } else {
                await showAlert(result.error || 'Fehler beim Speichern', 'Fehler');
            }
        } catch (error) {
            await showAlert('Fehler beim Speichern', 'Fehler');
            console.error(error);
        } finally {
            this.disabled = false;
            this.textContent = 'Auswahl speichern';
        }
    });

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }
    </script>

    <?php renderFooter(); ?>
</body>
</html>