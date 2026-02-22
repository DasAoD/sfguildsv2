/**
 * Guild page JavaScript
 * Member management and inline editing
 */

const rankIcons = {
    'Anführer': '<img src="/assets/images/crown.png" alt="Anführer" class="rank-icon">',
    'Offizier': '<img src="/assets/images/medal.png" alt="Offizier" class="rank-icon">',
    'Mitglied': '<img src="/assets/images/helmet.png" alt="Mitglied" class="rank-icon">',
    '': '<img src="/assets/images/helmet.png" alt="Mitglied" class="rank-icon">',
    null: '<img src="/assets/images/helmet.png" alt="Mitglied" class="rank-icon">'
};

let allGuilds = [];

// Load all guilds for tabs
async function loadGuilds() {
    try {
        const r = await fetch('/api/guilds.php');
        const d = await r.json();
        if (d.success) {
            allGuilds = d.guilds;
            renderGuildTabs();
        }
    } catch (e) {
        console.error(e);
    }
}

function renderGuildTabs() {
    if (!allGuilds.length) return;
    const tabs = document.getElementById('guildTabs');
    tabs.innerHTML = allGuilds.map(g => `
        <a href="/guild.php?id=${g.id}" class="guild-tab ${g.id == guildId ? 'active' : ''}">${escapeHtml(g.name)}</a>
    `).join('');
}

// Load guild data
async function loadGuild() {
    try {
        const r = await fetch(`/api/members.php?guild_id=${guildId}`);
        const d = await r.json();
        if (d.success) {
            const crestHtml = d.guild.crest_file 
                ? `<img src="/assets/images/${d.guild.crest_file}" alt="Wappen" style="width:100%;height:100%;object-fit:contain">` 
                : '<img src="/assets/images/helmet.png" alt="Wappen" style="width:100%;height:100%;object-fit:contain">';
            document.getElementById('guildCrest').innerHTML = crestHtml;
            document.getElementById('guildName').textContent = d.guild.name;
            document.getElementById('guildServer').textContent = `Server: ${d.guild.server}`;
            renderStats(d.stats);
            currentMembers = d.members;
            renderTable(d.members, d.is_logged_in);
        } else {
            document.getElementById('tableBody').innerHTML = '<tr><td colspan="13" class="loading">Fehler beim Laden</td></tr>';
        }
    } catch (e) {
        console.error(e);
        document.getElementById('tableBody').innerHTML = '<tr><td colspan="13" class="loading">Fehler beim Laden</td></tr>';
    }
}

function renderStats(s) {
    const grid = document.getElementById('statsGrid');
    grid.innerHTML = `
        <div class="stat-card"><div class="stat-label">Aktive Mitglieder</div><div class="stat-value">${s.active_members}</div></div>
        <div class="stat-card"><div class="stat-label">Level Ø</div><div class="stat-value">${s.avg_level}</div></div>
        <div class="stat-card"><div class="stat-label">Ritterhallenpunkte</div><div class="stat-value">${s.knight_hall_total.toLocaleString('de-DE')}</div></div>
    `;
}

function renderTable(members, logged) {
    const head = document.getElementById('tableHead');
    const body = document.getElementById('tableBody');
    
    if (logged) {
        head.innerHTML = `<tr>
            <th>Name</th><th class="center">Level</th><th>Zul. Online</th><th>Beitritt</th>
            <th class="center">Goldschatz</th><th class="center">Lehrmeister</th><th class="center">Ritterhalle</th><th class="center">Gildenpet</th>
            <th class="center">Tage offline</th><th>Entlassen</th><th>Verlassen</th><th>Notizen</th><th class="center">Aktion</th>
        </tr>`;
    } else {
        head.innerHTML = `<tr>
            <th>Name</th><th class="center">Level</th>
            <th class="center">Goldschatz</th><th class="center">Lehrmeister</th><th class="center">Ritterhalle</th><th class="center">Gildenpet</th>
        </tr>`;
    }
    
    if (!members.length) {
        body.innerHTML = '<tr><td colspan="13" class="loading">Keine Mitglieder</td></tr>';
        return;
    }
    
    body.innerHTML = members.map((m, idx) => {
        const rank = rankIcons[m.rank] || rankIcons['member'];
        let rowClass = '';
        
        if (m.fired_at) {
            rowClass = 'fired';
        } else if (m.left_at) {
            rowClass = 'left';
        } else if (m.notes && m.notes.trim()) {
            rowClass = 'has-notes';
        }
        
        if (m.days_offline >= 14) {
            rowClass += ' long-offline';
        }
        
        if (logged) {
            return `<tr class="${rowClass.trim()}">
                <td>${rank} ${escapeHtml(m.name)}</td>
                <td class="center">${m.level}</td>
                <td>${formatDate(m.last_online)}</td>
                <td>${formatDate(m.joined_at)}</td>
                <td class="center">${m.gold?.toLocaleString('de-DE') || 0}</td>
                <td class="center">${m.mentor || 0}</td>
                <td class="center">${m.knight_hall?.toLocaleString('de-DE') || 0}</td>
                <td class="center">${m.guild_pet || 0}</td>
                <td class="center">${m.days_offline > 0 ? '-' + m.days_offline : ''}</td>
                <td class="editable" data-idx="${idx}" data-field="fired_at">${formatDate(m.fired_at)}</td>
                <td class="editable" data-idx="${idx}" data-field="left_at">${formatDate(m.left_at)}</td>
                <td class="editable" data-idx="${idx}" data-field="notes">${escapeHtml(m.notes) || ''}</td>
                <td class="center"><button class="btn-delete" onclick="deleteMember(${idx})" title="Mitglied löschen">🗑️</button></td>
            </tr>`;
        } else {
            return `<tr>
                <td>${rank} ${escapeHtml(m.name)}</td>
                <td class="center">${m.level}</td>
                <td class="center">${m.gold?.toLocaleString('de-DE') || 0}</td>
                <td class="center">${m.mentor || 0}</td>
                <td class="center">${m.knight_hall?.toLocaleString('de-DE') || 0}</td>
                <td class="center">${m.guild_pet || 0}</td>
            </tr>`;
        }
    }).join('');
}

// Inline editing
document.addEventListener('click', function(e) {
    if (!isLoggedIn) return;
    
    const cell = e.target.closest('td.editable');
    if (!cell) return;
    if (cell.querySelector('.edit-input')) return;
    
    const idx = parseInt(cell.dataset.idx);
    const field = cell.dataset.field;
    
    if (field === 'notes') {
        editNotes(idx, cell);
    } else {
        editDate(idx, field, cell);
    }
});

function editNotes(idx, cell) {
    const member = currentMembers[idx];
    const oldValue = member.notes || '';
    const clearBtn = oldValue ? `<button class="btn btn-danger btn-sm" onclick="clearField(${idx}, 'notes')">Löschen</button>` : '';
    
    cell.innerHTML = `
        <div class="edit-form">
            <textarea class="edit-input" rows="2">${escapeHtml(oldValue)}</textarea>
            <div class="edit-actions">
                <button class="btn btn-primary btn-sm" onclick="saveEdit(${idx}, 'notes', this.closest('.edit-form'))">Speichern</button>
                ${clearBtn}
                <button class="btn btn-secondary btn-sm" onclick="cancelEdit(${idx}, 'notes')">Abbrechen</button>
            </div>
        </div>
    `;
    
    cell.querySelector('textarea').focus();
}

function editDate(idx, field, cell) {
    const member = currentMembers[idx];
    const oldValue = member[field] || '';
    const clearBtn = oldValue ? `<button class="btn btn-danger btn-sm" onclick="clearField(${idx}, '${field}')">Löschen</button>` : '';
    
    cell.innerHTML = `
        <div class="edit-form">
            <input type="date" class="edit-input" value="${oldValue}">
            <div class="edit-actions">
                <button class="btn btn-primary btn-sm" onclick="saveEdit(${idx}, '${field}', this.closest('.edit-form'))">Speichern</button>
                ${clearBtn}
                <button class="btn btn-secondary btn-sm" onclick="cancelEdit(${idx}, '${field}')">Abbrechen</button>
            </div>
        </div>
    `;
    
    cell.querySelector('input').focus();
}

async function saveEdit(idx, field, form) {
    const input = form.querySelector('.edit-input');
    const newValue = input.value.trim();
    const member = currentMembers[idx];
    
    try {
        const r = await fetch('/api/update_member.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                member_id: member.id,
                field: field,
                value: newValue
            })
        });
        
        const d = await r.json();
        
        if (d.success) {
            member[field] = newValue;
            loadGuild();
        } else {
            showAlert('Fehler beim Speichern: ' + d.message);
        }
    } catch (e) {
        console.error(e);
        showAlert('Fehler beim Speichern');
    }
}

function cancelEdit(idx, field) {
    loadGuild();
}

async function clearField(idx, field) {
    const member = currentMembers[idx];
    const fieldLabels = { notes: 'Notizen', fired_at: 'Entlassen-Datum', left_at: 'Verlassen-Datum' };
    const label = fieldLabels[field] || field;
    
    confirmDialog(`${label} von "${member.name}" wirklich löschen?`, async () => {
        try {
            const r = await fetch('/api/update_member.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    member_id: member.id,
                    field: field,
                    value: ''
                })
            });
            const d = await r.json();
            if (d.success) {
                member[field] = null;
                loadGuild();
            } else {
                showAlert('Fehler: ' + d.message);
            }
        } catch (e) {
            console.error(e);
            showAlert('Fehler beim Löschen');
        }
    });
}

// Delete member
async function deleteMember(idx) {
    const member = currentMembers[idx];
    if (!member) return;
    
    confirmDialog(
        `"${member.name}" (Level ${member.level}) wirklich löschen?\n\nDiese Aktion kann nicht rückgängig gemacht werden!`,
        async () => {
            try {
                const r = await fetch('/api/delete_member.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ member_id: member.id })
                });
                const d = await r.json();
                if (d.success) {
                    loadGuild();
                } else {
                    await showAlert('Fehler: ' + d.message, 'Fehler');
                }
            } catch (e) {
                await showAlert('Fehler beim Löschen', 'Fehler');
            }
        }
    );
}

// Import Modal Functions
function openImportModal() {
    document.getElementById('importModal').style.display = 'flex';
    document.getElementById('csvFile').value = '';
}

function closeImportModal() {
    document.getElementById('importModal').style.display = 'none';
}

// Handle import form submission
document.getElementById('importForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const fileInput = document.getElementById('csvFile');
    const file = fileInput.files[0];
    
    if (!file) {
        await showAlert('Bitte wähle eine CSV-Datei aus', 'Fehler');
        return;
    }
    
    const formData = new FormData();
    formData.append('file', file);
    formData.append('guild_id', guildId);
    
    try {
        const response = await fetch('/api/import_guild_members.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        closeImportModal();
        
        if (result.success) {
            await showAlert(`Import erfolgreich!\n\nEingefügt: ${result.inserted}\nAktualisiert: ${result.updated}\nÜbersprungen: ${result.skipped}`, 'Erfolg');
            loadGuild(); // Reload data
        } else {
            await showAlert('Fehler beim Import: ' + (result.error || 'Unbekannter Fehler'), 'Fehler');
        }
    } catch (error) {
        closeImportModal();
        await showAlert('Fehler beim Import: ' + error.message, 'Fehler');
        console.error(error);
    }
});

// Custom alert modal function (from fights.php)
function showAlert(message, title = 'Hinweis') {
    return new Promise((resolve) => {
        // Check if we have a confirm modal to reuse
        let modal = document.getElementById('confirmModal');
        if (!modal) {
            // Create modal if it doesn't exist
            modal = document.createElement('div');
            modal.id = 'confirmModal';
            modal.className = 'modal';
            modal.innerHTML = `
                <div class="modal-content" style="max-width: 500px;">
                    <div class="modal-header">
                        <h3 id="confirmTitle">Hinweis</h3>
                        <button class="modal-close">&times;</button>
                    </div>
                    <div class="modal-body">
                        <p id="confirmMessage" style="white-space: pre-line;"></p>
                        <div style="display: flex; gap: var(--spacing-md); justify-content: flex-end; margin-top: var(--spacing-lg);">
                            <button id="confirmCancel" class="btn-small">Abbrechen</button>
                            <button id="confirmOk" class="btn-small btn-primary">OK</button>
                        </div>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
        }
        
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
        okBtn.className = 'btn-small btn-primary';
        
        modal.style.display = 'flex';
        
        const cleanup = () => {
            modal.style.display = 'none';
            cancelBtn.style.display = '';
            okBtn.className = 'btn-small btn-primary';
        };
        
        const handleOk = () => {
            cleanup();
            okBtn.removeEventListener('click', handleOk);
            closeBtn.removeEventListener('click', handleOk);
            resolve();
        };
        
        okBtn.addEventListener('click', handleOk);
        closeBtn.addEventListener('click', handleOk);
    });
}

// Show import button only if logged in
if (isLoggedIn) {
    document.getElementById('importBtn').style.display = 'block';
}

// Initialize
loadGuilds();
loadGuild();
