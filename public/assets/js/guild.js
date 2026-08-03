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
            const notesEl = document.getElementById('guildNotes');
            if (notesEl) {
                if (d.guild.notes && d.guild.notes.trim()) {
                    notesEl.textContent = d.guild.notes;
                    notesEl.style.display = '';
                } else {
                    notesEl.style.display = 'none';
                }
            }
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
    const gsTotal  = (s.goldschatz_total  || 0).toLocaleString('de-DE');
    const lmTotal  = (s.lehrmeister_total || 0).toLocaleString('de-DE');
    const gsPct    = s.goldschatz_pct  ?? null;
    const lmPct    = s.lehrmeister_pct ?? null;
    grid.innerHTML = `
        <div class="stat-card"><div class="stat-label">Aktive Mitglieder</div><div class="stat-value">${s.active_members}</div></div>
        <div class="stat-card"><div class="stat-label">Level Ø</div><div class="stat-value">${s.avg_level}</div></div>
        <div class="stat-card"><div class="stat-label">Ritterhallenpunkte</div><div class="stat-value">${s.knight_hall_total.toLocaleString('de-DE')}</div></div>
        <div class="stat-card">
            <div class="stat-label">Goldschatz</div>
            <div class="stat-value">${gsTotal} <span class="stat-max">/ 1000</span></div>
            ${gsPct !== null ? `<div class="stat-bonus">${gsPct}% Bonus</div>` : ''}
        </div>
        <div class="stat-card">
            <div class="stat-label">Lehrmeister</div>
            <div class="stat-value">${lmTotal} <span class="stat-max">/ 1000</span></div>
            ${lmPct !== null ? `<div class="stat-bonus">${lmPct}% Bonus</div>` : ''}
        </div>
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
                <td><span class="player-name" data-player="${escapeHtml(m.name)}">${rank}<span class="player-name-text">${escapeHtml(m.name)}</span></span></td>
                <td class="center">${m.level}</td>
                <td>${formatDate(m.last_online)}</td>
                <td class="editable" data-idx="${idx}" data-field="joined_at">${formatDate(m.joined_at)}</td>
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

// Inline editing + Charakter-Modal
document.addEventListener('click', function(e) {
    const nameEl = e.target.closest('.player-name');
    if (nameEl && isLoggedIn) {
        openCharacterModal(nameEl.dataset.player);
        return;
    }

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
    const fieldLabels = { notes: 'Notizen', fired_at: 'Entlassen-Datum', left_at: 'Verlassen-Datum', joined_at: 'Gildenbeitritt' };
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

// ─── Character Modal ──────────────────────────────────────────────

const slotLabels = {
    Hat: 'Kopf', BreastPlate: 'Brust', Gloves: 'Hände', FootWear: 'Füße',
    Amulet: 'Hals', Belt: 'Taille', Ring: 'Finger', Talisman: 'Schmuck',
    Weapon: 'Waffe', Shield: 'Waffe 2'
};
const slotOrder = ['Hat', 'BreastPlate', 'Gloves', 'FootWear', 'Amulet', 'Belt', 'Ring', 'Talisman', 'Weapon', 'Shield'];
const attrLabels = { Strength: 'Stärke', Dexterity: 'Geschick', Intelligence: 'Intelligenz', Constitution: 'Ausdauer', Luck: 'Glück' };
const classLabels = {
    Warrior: 'Krieger', Mage: 'Magier', Scout: 'Kundschafter', Assassin: 'Assassine',
    BattleMage: 'Kampfmagier', Berserker: 'Berserker', DemonHunter: 'Dämonenjäger',
    Druid: 'Druide', Bard: 'Barde', Necromancer: 'Nekromant', Paladin: 'Paladin', PlagueDoctor: 'Pestdoktor'
};
const raceLabels = {
    Human: 'Mensch', Elf: 'Elf', Dwarf: 'Zwerg', Gnome: 'Gnom', Orc: 'Ork',
    DarkElf: 'Dunkelelf', Goblin: 'Goblin', Demon: 'Dämon'
};
const gemTypeLabels = { ...attrLabels, All: 'Alle', Legendary: 'Legendär' };
const runeTypeLabels = {
    QuestGold: 'Questgold', EpicChance: 'Epenchance', ItemQuality: 'Itemqualität', QuestXP: 'Quest-EP',
    ExtraHitPoints: 'Extra-Lebenspunkte', FireResistance: 'Feuerresistenz', ColdResistence: 'Kälteresistenz',
    LightningResistance: 'Blitzresistenz', TotalResistence: 'Gesamtresistenz', FireDamage: 'Feuerschaden',
    ColdDamage: 'Kälteschaden', LightningDamage: 'Blitzschaden'
};
// Feste Effektwerte pro Verzauberung (spielweite Konstanten, nicht Item-abhängig)
const enchantmentLabels = {
    SwordOfVengeance: { label: 'Fuchtel des Rächers', effect: '+5% Schaden bei kritischen Treffern' },
    MariosBeard: { label: 'Marios Bart', effect: '+50% Pilzfundchance' },
    ManyFeetBoots: { label: '36960-Fuß-Stiefel', effect: '+10% Metallbonus auf Expeditionen' },
    ShadowOfTheCowboy: { label: 'Schatten des Cowboys', effect: '+1 Reaktionswert' },
    AdventurersArchaeologicalAura: { label: 'Abenteuerarchäologenaura', effect: '+10% Erfahrung auf Expeditionen' },
    ThirstyWanderer: { label: 'Durstiger Wanderer', effect: '+1 Bier täglich' },
    UnholyAcquisitiveness: { label: 'Unheilige Sammelwut', effect: '+10% Itemfundchance' },
    TheGraveRobbersPrayer: { label: 'Gebet des Grabräubers', effect: '+10% Gold auf Expeditionen' },
    RobberBaronRitual: { label: 'Raubritter-Ritual', effect: '+10% arkaner Splitterbonus auf Expeditionen und beim Würfelspiel' }
};

// Platzhalter-Icons pro Slot, bis echte Item-Grafiken verfügbar sind
const slotIcons = {
    Hat: '🎩', BreastPlate: '👕', Gloves: '🧤', FootWear: '👢', Belt: '🎗️',
    Amulet: '📿', Ring: '💍', Talisman: '🔮', Weapon: '⚔️', Shield: '🛡️'
};
const slotColumns = {
    armor: ['Hat', 'BreastPlate', 'Gloves', 'FootWear'],
    weapons: ['Weapon', 'Shield'],
    jewelry: ['Amulet', 'Belt', 'Ring', 'Talisman']
};
const gemColors = {
    Strength: '#e74c3c', Dexterity: '#2ecc71', Intelligence: '#3498db',
    Constitution: '#e84393', Luck: '#f1c40f', All: '#ecf0f1', Legendary: '#9b59b6'
};
const potionTypeLabels = {
    Strength: 'Stärketrank', Dexterity: 'Geschicklichkeitstrank', Intelligence: 'Intelligenztrank',
    Constitution: 'Ausdauertrank', Luck: 'Glückstrank', EternalLife: 'Trank des ewigen Lebens'
};
const potionSizeLabels = { Small: 'Klein', Medium: 'Mittel', Large: 'Groß' };

function formatCountdown(iso) {
    if (!iso) return null;
    const target = new Date(iso).getTime();
    if (isNaN(target)) return null;
    const diffMs = target - Date.now();
    if (diffMs <= 0) return 'abgelaufen';
    const totalHours = Math.floor(diffMs / 3600000);
    const days = Math.floor(totalHours / 24);
    const hours = totalHours % 24;
    return days > 0 ? `${days}T ${hours}Std` : `${hours}Std`;
}

function buildEquipTile(slot, item) {
    const icon = slotIcons[slot] || '❔';
    const label = slotLabels[slot];
    if (!item) {
        return `<div class="char-slot char-slot-empty" title="${escapeHtml(label)}: leer">
            <div class="char-slot-icon">${icon}</div>
            <div class="char-slot-label">${escapeHtml(label)}</div>
        </div>`;
    }
    const iconHtml = item.icon
        ? `<img class="char-slot-icon-img" src="${escapeHtml(item.icon)}" alt="" onerror="this.style.display='none'; this.nextElementSibling.style.display='';">
           <div class="char-slot-icon" style="display:none">${icon}</div>`
        : `<div class="char-slot-icon">${icon}</div>`;
    const lines = [`${label}:`];
    Object.entries(item.attributes || {})
        .filter(([, v]) => v > 0)
        .forEach(([k, v]) => lines.push(`${attrLabels[k] || k}: +${v.toLocaleString('de-DE')}`));
    if (item.gem) lines.push(`Edelstein: ${gemTypeLabels[item.gem.typ] || item.gem.typ} +${item.gem.value.toLocaleString('de-DE')}`);
    if (item.rune) lines.push(`Rune: ${runeTypeLabels[item.rune.typ] || item.rune.typ} ${item.rune.value}%`);
    if (item.enchantment) {
        const ench = enchantmentLabels[item.enchantment];
        lines.push(`VZ: ${ench ? ench.effect : item.enchantment}`);
    }
    if (item.upgrade_count) lines.push(`Verbesserung: ${item.upgrade_count}x`);
    const tooltip = lines.join('\n');
    const gemColor = item.gem ? (gemColors[item.gem.typ] || gemColors.All) : null;
    const badges = [];
    if (gemColor) badges.push(`<span class="char-slot-badge-gem" style="background:${gemColor}"></span>`);
    if (item.enchantment) badges.push(`<span class="char-slot-badge-ench">✨</span>`);
    if (item.upgrade_count) badges.push(`<span class="char-slot-badge-upgrade">+${item.upgrade_count}</span>`);
    return `<div class="char-slot" title="${escapeHtml(tooltip)}">
        ${iconHtml}
        ${badges.join('')}
        <div class="char-slot-label">${escapeHtml(label)}</div>
    </div>`;
}

function formatCharDate(iso) {
    if (!iso) return '—';
    const d = new Date(iso);
    if (isNaN(d)) return '—';
    return d.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' }) + ' ' +
           d.toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' });
}

async function openCharacterModal(playerName) {
    document.getElementById('characterModalTitle').textContent = playerName;
    document.getElementById('characterModalBody').innerHTML = '<div class="loading">Lade Charakterdaten…</div>';
    document.getElementById('characterModal').style.display = 'flex';

    try {
        const r = await fetch(`/api/player_character.php?guild_id=${guildId}&player_name=${encodeURIComponent(playerName)}`);
        const d = await r.json();

        if (!d.success) {
            document.getElementById('characterModalBody').innerHTML = `<div class="loading">Fehler beim Laden</div>`;
            return;
        }
        if (!d.available) {
            document.getElementById('characterModalBody').innerHTML = '<div class="loading">Noch keine Charakterdaten vorhanden. Werden beim nächsten automatischen Sync abgerufen.</div>';
            return;
        }

        renderCharacterModal(d.data, d.fetched_at, playerName);
    } catch (e) {
        console.error(e);
        document.getElementById('characterModalBody').innerHTML = '<div class="loading">Fehler beim Laden</div>';
    }
}

function closeCharacterModal() {
    document.getElementById('characterModal').style.display = 'none';
}

function renderCharacterModal(data, fetchedAt, playerName) {
    const attrRow1Keys = ['Strength', 'Dexterity', 'Intelligence', 'Constitution'];
    const attrsRow1 = attrRow1Keys.map(key => `
        <div class="char-attr">
            <div class="char-attr-label">${attrLabels[key]}</div>
            <div class="char-attr-value">${(data.attributes?.[key] ?? 0).toLocaleString('de-DE')}</div>
        </div>
    `).join('');
    const attrsRow2 = `
        <div class="char-attr">
            <div class="char-attr-label">${attrLabels.Luck}</div>
            <div class="char-attr-value">${(data.attributes?.Luck ?? 0).toLocaleString('de-DE')}</div>
        </div>
        <div class="char-attr">
            <div class="char-attr-label">Rüstung</div>
            <div class="char-attr-value">${(data.armor || 0).toLocaleString('de-DE')}</div>
        </div>
        <div class="char-attr">
            <div class="char-attr-label">Schaden</div>
            <div class="char-attr-value">${(data.min_damage || 0).toLocaleString('de-DE')}–${(data.max_damage || 0).toLocaleString('de-DE')}</div>
        </div>
    `;

    const armorHtml = slotColumns.armor.map(slot => buildEquipTile(slot, data.equipment?.[slot])).join('');
    const weaponHtml = slotColumns.weapons.map(slot => buildEquipTile(slot, data.equipment?.[slot])).join('');
    const jewelryHtml = slotColumns.jewelry.map(slot => buildEquipTile(slot, data.equipment?.[slot])).join('');

    const activePotions = (data.potions || []).filter(p => p);
    const potionsHtml = activePotions.length
        ? activePotions.map(p => {
            const potionIconHtml = p.icon
                ? `<img class="char-potion-icon-img" src="${escapeHtml(p.icon)}" alt="" onerror="this.style.display='none'; this.nextElementSibling.style.display='';">
                   <div class="char-potion-icon" style="display:none">🧪</div>`
                : `<div class="char-potion-icon">🧪</div>`;
            return `
            <div class="char-potion" title="${escapeHtml(potionTypeLabels[p.typ] || p.typ)} (${escapeHtml(potionSizeLabels[p.size] || p.size)})">
                ${potionIconHtml}
                <div class="char-potion-countdown">${escapeHtml(formatCountdown(p.expires) || '—')}</div>
            </div>
        `;
        }).join('')
        : '<div class="char-potion-empty">Keine aktiven Träke</div>';

    const guildNameText = document.getElementById('guildName')?.textContent?.trim() || '';
    const heroHtml = `
        <div class="char-hero">
            <div class="char-equip-col char-equip-col-armor">${armorHtml}</div>
            <div class="char-portrait-col">
                <div class="char-portrait-frame">
                    <div class="char-portrait-placeholder">${escapeHtml((playerName || '?').charAt(0).toUpperCase())}</div>
                </div>
                <div class="char-name">${escapeHtml(playerName || '')}</div>
                ${guildNameText ? `<div class="char-guild-tag">[${escapeHtml(guildNameText)}]</div>` : ''}
                <div class="char-level-badge">Stufe ${data.level ?? '?'}</div>
                <div class="char-class-race">${escapeHtml(classLabels[data.class] || data.class || '')} · ${escapeHtml(raceLabels[data.race] || data.race || '')}</div>
                <div class="char-honor-rank">Ehre ${(data.honor || 0).toLocaleString('de-DE')} · Rang ${(data.rank || 0).toLocaleString('de-DE')}</div>
                <div class="char-section-title">Tränke</div>
                <div class="char-potions-row">${potionsHtml}</div>
                <div class="char-weapon-row">${weaponHtml}</div>
            </div>
            <div class="char-equip-col char-equip-col-jewelry">${jewelryHtml}</div>
        </div>
    `;

    document.getElementById('characterModalBody').innerHTML = `
        ${heroHtml}
        <div class="char-attrs-row">${attrsRow1}</div>
        <div class="char-attrs-row char-attrs-row-last">${attrsRow2}</div>
        <div class="char-fetched-at">Stand: ${formatCharDate(fetchedAt)}</div>
    `;
}

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
    document.getElementById('syncBtn').style.display = 'block';
}

// Initialize
loadGuilds();
loadGuild();
// Member Sync via sf-api
async function syncMembers() {
    const guildId = new URLSearchParams(window.location.search).get('id');
    if (!guildId) { showAlert('Keine Gilde ausgewählt.'); return; }

    const btn = document.getElementById('syncBtn');
    btn.disabled = true;
    showOverlay('Mitglieder werden synchronisiert…');

    try {
        const r = await fetch('/api/sf_member_sync.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ guild_id: parseInt(guildId) })
        });
        const d = await r.json();

        if (d.success) {
            const parts = [];
            if (d.inserted  > 0) parts.push(`${d.inserted} neu`);
            if (d.updated   > 0) parts.push(`${d.updated} aktualisiert`);
            if (d.rejoined  > 0) parts.push(`${d.rejoined} wiedereingetreten`);
            const summary = parts.length > 0 ? parts.join(', ') : 'Keine Änderungen';
            showAlert(`Sync abgeschlossen: ${summary} (${d.total} Mitglieder gesamt).`);
            loadGuild();
        } else {
            showAlert('Fehler beim Sync: ' + (d.message || 'Unbekannt'));
        }
    } catch (e) {
        console.error(e);
        showAlert('Fehler beim Sync: Verbindungsfehler');
    } finally {
        btn.disabled = false;
        hideOverlay();
    }
}
