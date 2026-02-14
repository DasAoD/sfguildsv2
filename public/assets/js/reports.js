/**
 * Reports page JavaScript
 * Battle participation statistics
 */

let allGuilds = [];
let currentStats = null;

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
        // Silent fail
    }
}

function renderGuildTabs() {
    if (!allGuilds.length) return;
    const tabs = document.getElementById('guildTabs');
    tabs.innerHTML = allGuilds.map(g => `
        <a href="/reports.php?id=${g.id}" class="guild-tab ${g.id == guildId ? 'active' : ''}">${escapeHtml(g.name)}</a>
    `).join('');
}

// Load battle statistics
async function loadStats() {
    try {
        const r = await fetch(`/api/battle_stats.php?guild_id=${guildId}`);
        const d = await r.json();
        if (d.success) {
            currentStats = d;
            
            // Render guild info
            const crestHtml = d.guild.crest_file 
                ? `<img src="/assets/images/${d.guild.crest_file}" alt="Wappen" style="width:100%;height:100%;object-fit:contain">` 
                : '⚔️';
            document.getElementById('guildCrest').innerHTML = crestHtml;
            document.getElementById('guildName').textContent = `${d.guild.name} (Report)`;
            document.getElementById('guildServer').textContent = `Server: ${d.guild.server} | Letzte Aktualisierung: ${new Date().toLocaleDateString('de-DE')}`;
            
            renderStatsGrid(d.stats);
            renderTable(d.players);
            renderSidebar(d.insights);
        } else {
            document.getElementById('tableBody').innerHTML = '<tr><td colspan="6" class="loading">Fehler beim Laden</td></tr>';
        }
    } catch (e) {
        document.getElementById('tableBody').innerHTML = '<tr><td colspan="6" class="loading">Fehler beim Laden</td></tr>';
    }
}

function renderStatsGrid(stats) {
    const grid = document.getElementById('statsGrid');
    const missing = stats.player_count > 0 ? Math.round(stats.player_count * (100 - stats.avg_participation) / 100) : 0;
    
    let html = `
        <div class="stat-card">
            <div class="stat-label">Angriffe</div>
            <div class="stat-value">${stats.attacks}</div>
            <div class="stat-sublabel">Gesamt</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Verteidigungen</div>
            <div class="stat-value">${stats.defenses}</div>
            <div class="stat-sublabel">Gesamt</div>
        </div>
    `;
    
    if (stats.raids > 0) {
        html += `
        <div class="stat-card">
            <div class="stat-label">Raids</div>
            <div class="stat-value">${stats.raids}</div>
            <div class="stat-sublabel">Gesamt</div>
        </div>
        `;
    }
    
    html += `
        <div class="stat-card">
            <div class="stat-label">Quote</div>
            <div class="stat-value">${stats.avg_participation}%</div>
            <div class="stat-sublabel">⌀ Teilnahme gesamt</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Fehlende</div>
            <div class="stat-value">${missing}</div>
            <div class="stat-sublabel">Nicht teilgenommen</div>
        </div>
    `;
    
    grid.innerHTML = html;
}

function renderTable(players) {
    const tbody = document.getElementById('tableBody');
    
    if (!players.length) {
        tbody.innerHTML = '<tr><td colspan="6" class="loading">Keine Daten vorhanden</td></tr>';
        return;
    }
    
    const stats = currentStats.stats;
    
    tbody.innerHTML = players.map(p => {
        const pct = parseFloat(p.participation_pct);
        const progressClass = pct >= 90 ? 'progress-excellent' : (pct >= 60 ? 'progress-good' : 'progress-poor');
        
        // Rank icon (same as guild.php)
        let rankIcon = '';
        if (p.current_rank === 'Anführer') rankIcon = '<img src="/assets/images/crown.png" alt="Anführer" class="rank-icon">';
        else if (p.current_rank === 'Offizier') rankIcon = '<img src="/assets/images/medal.png" alt="Offizier" class="rank-icon">';
        else if (p.current_rank === 'Mitglied') rankIcon = '<img src="/assets/images/helmet.png" alt="Mitglied" class="rank-icon">';
        
        // Build player display name - make it clickable!
        let playerDisplay = rankIcon ? `${rankIcon} ` : '';
        playerDisplay += `<a href="#" class="player-link" data-player="${p.player_name}">${escapeHtml(p.player_name)}</a>`;
        if (p.current_level) playerDisplay += ` (${p.current_level})`;
        
        // Participated / Total for each type
        const attacksDisplay = `${p.attacks}/${p.total_attacks || stats.attacks}`;
        const defensesDisplay = `${p.defenses}/${p.total_defenses || stats.defenses}`;
        const raidsDisplay = `${p.raids_participated || 0}/${p.total_raids || stats.raids || 0}`;
        
        return `
            <tr>
                <td>${playerDisplay}</td>
                <td class="center">${attacksDisplay}</td>
                <td class="center">${defensesDisplay}</td>
                <td class="center">${raidsDisplay}</td>
                <td class="center">${p.participated}/${p.total_fights}</td>
                <td>
                    <div class="progress-container">
                        <div class="progress-bar ${progressClass}">
                            <div class="progress-fill" style="width: ${pct}%"></div>
                        </div>
                        <span class="progress-text">${pct}%</span>
                    </div>
                </td>
            </tr>
        `;
    }).join('');
    
    // Add click handlers for player links
    document.querySelectorAll('.player-link').forEach(link => {
        link.addEventListener('click', (e) => {
            e.preventDefault();
            const playerName = e.target.dataset.player;
            showPlayerDetailsModal(playerName);
        });
    });
}

function renderSidebar(insights) {
    // Top Missing - show top 5, only players with missed > 0
    const topMissingEl = document.getElementById('topMissing');
    if (insights.top_missing.length > 0) {
        topMissingEl.innerHTML = insights.top_missing.map(p => {
            let playerDisplay = escapeHtml(p.player_name);
            if (p.current_level) playerDisplay += ` (${p.current_level})`;
            
            return `<div class="sidebar-row">
                <span class="sidebar-name">${playerDisplay}</span>
                <span class="sidebar-value highlight">${p.missed}/${p.total_fights}</span>
            </div>`;
        }).join('');
    } else {
        topMissingEl.innerHTML = '<span>Keine Daten</span>';
    }
    
    // Distribution - Option 4: 95-100%, 85-94%, 75-84%, <75%
    const distEl = document.getElementById('distribution');
    const dist = insights.distribution;
    distEl.innerHTML = `
        <div class="sidebar-row">
            <span class="sidebar-name">95-100%</span>
            <span class="sidebar-value">${dist.excellent} Spieler</span>
        </div>
        <div class="sidebar-row">
            <span class="sidebar-name">85-94%</span>
            <span class="sidebar-value">${dist.good} Spieler</span>
        </div>
        <div class="sidebar-row">
            <span class="sidebar-name">75-84%</span>
            <span class="sidebar-value">${dist.acceptable} Spieler</span>
        </div>
        <div class="sidebar-row">
            <span class="sidebar-name"><75%</span>
            <span class="sidebar-value highlight">${dist.poor} Spieler</span>
        </div>
    `;
    
    // Inactive (ab 7 Tagen) - days_offline calculated LIVE
    const inactiveEl = document.getElementById('inactive');
    if (insights.inactive.length > 0) {
        inactiveEl.innerHTML = insights.inactive.map(p => {
            let playerDisplay = escapeHtml(p.name);
            if (p.level) playerDisplay += ` (${p.level})`;
            
            // Round days_offline to integer and format as "X Tage offline"
            const daysOffline = Math.floor(p.days_offline);
            const daysText = daysOffline === 1 ? 'Tag' : 'Tage';
            
            return `<div class="sidebar-row">
                <span class="sidebar-name">${playerDisplay}</span>
                <span class="sidebar-value highlight">${daysOffline} ${daysText} offline</span>
            </div>`;
        }).join('');
    } else {
        inactiveEl.innerHTML = '<span>Keine inaktiven Spieler</span>';
    }
}

// Export to CSV
function exportCSV() {
    if (!currentStats || !currentStats.players.length) {
        alert('Keine Daten zum Exportieren');
        return;
    }
    
    const headers = ['Spieler', 'Level', 'Angriffe', 'Verteidigungen', 'Raids', 'Teilgenommen', 'Gesamt', 'Quote'];
    const rows = currentStats.players.map(p => [
        p.player_name,
        p.player_level || '',
        p.attacks,
        p.defenses,
        p.raids_participated || 0,
        p.participated,
        p.total_fights,
        p.participation_pct + '%'
    ]);
    
    const csv = [headers, ...rows].map(row => 
        row.map(cell => `"${cell}"`).join(',')
    ).join('\n');
    
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    const timestamp = new Date().toISOString().slice(0, 10);
    
    link.setAttribute('href', url);
    link.setAttribute('download', `${currentStats.guild.name}_report_${timestamp}.csv`);
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// Show player details modal
async function showPlayerDetailsModal(playerName) {
    try {
        // Get guild ID from current stats (not from URL - reports page uses tabs!)
        const guildId = currentStats?.guild?.id || new URLSearchParams(window.location.search).get('id') || 1;
        const url = `/api/player_details.php?guild_id=${guildId}&player_name=${encodeURIComponent(playerName)}`;
        
        const response = await fetch(url);
        const data = await response.json();
        
        if (!data.success) {
            alert(data.message || 'Fehler beim Laden der Spieler-Details');
            return;
        }
        
        // Build modal content
        const player = data.player;
        const stats = data.stats;
        const battles = data.battles;
        
        // Player info header
        let rankIcon = '';
        if (player.rank === 'Anführer') rankIcon = '<img src="/assets/images/crown.png" alt="Anführer" class="rank-icon">';
        else if (player.rank === 'Offizier') rankIcon = '<img src="/assets/images/medal.png" alt="Offizier" class="rank-icon">';
        else if (player.rank === 'Mitglied') rankIcon = '<img src="/assets/images/helmet.png" alt="Mitglied" class="rank-icon">';
        
        const pct = stats.participation_pct;
        
        // Build type-specific stats (only show raids if > 0)
        let typeStatsHtml = `
            <div class="stat-card">
                <div class="stat-label">Angriffe</div>
                <div class="stat-value">${stats.attacks_participated}/${stats.attacks}</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Verteidigungen</div>
                <div class="stat-value">${stats.defenses_participated}/${stats.defenses}</div>
            </div>
        `;
        let typeCols = 2;
        if (stats.raids > 0) {
            typeStatsHtml += `
            <div class="stat-card">
                <div class="stat-label">Raids</div>
                <div class="stat-value">${stats.raids_participated}/${stats.raids}</div>
            </div>
            `;
            typeCols = 3;
        }
        
        // Battle type icon helper
        function bTypeIcon(type) {
            if (type === 'attack') return '<img src="/assets/images/swords.png" alt="Angriff" class="battle-icon"> Angriff';
            if (type === 'defense') return '<img src="/assets/images/shield.png" alt="Verteidigung" class="battle-icon"> Verteidigung';
            if (type === 'raid') return '🏰 Gildenraid';
            return type;
        }
        
        // Create modal
        const modal = document.createElement('div');
        modal.className = 'modal-overlay';
        modal.innerHTML = `
            <div class="modal-content" style="max-width: 900px;">
                <button class="modal-close" onclick="this.closest('.modal-overlay').remove()">×</button>
                <h2 style="margin: 0 0 1.5rem 0; display: flex; align-items: center; gap: 0.5rem;">
                    ${rankIcon} ${escapeHtml(player.name)} ${player.level ? `(${player.level})` : ''}
                </h2>
                
                <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: 1.5rem;">
                    <div class="stat-card">
                        <div class="stat-label">Gesamt</div>
                        <div class="stat-value">${stats.total_fights}</div>
                        <div class="stat-sublabel">Kämpfe</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Teilgenommen</div>
                        <div class="stat-value">${stats.participated}</div>
                        <div class="stat-sublabel">Kämpfe</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Gefehlt</div>
                        <div class="stat-value">${stats.missed}</div>
                        <div class="stat-sublabel">Kämpfe</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Quote</div>
                        <div class="stat-value">${pct}%</div>
                        <div class="stat-sublabel">Teilnahme</div>
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: repeat(${typeCols}, 1fr); gap: 1rem; margin-bottom: 2rem;">
                    ${typeStatsHtml}
                </div>
                
                <h3 style="margin: 0 0 1rem 0;">Kampfhistorie (${battles.length} Kämpfe)</h3>
                <div style="max-height: 400px; overflow-y: auto; border: 1px solid var(--color-border); border-radius: var(--radius-md);">
                    <table style="width: 100%;">
                        <thead style="position: sticky; top: 0; background: var(--color-bg-secondary); z-index: 1;">
                            <tr>
                                <th style="padding: 0.75rem;">Datum</th>
                                <th style="padding: 0.75rem;">Uhrzeit</th>
                                <th style="padding: 0.75rem;">Typ</th>
                                <th style="padding: 0.75rem;">Gegner</th>
                                <th style="padding: 0.75rem; text-align: center;">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${battles.map(b => {
                                const dateParts = b.battle_date.split('-');
                                const germanDate = `${dateParts[2]}.${dateParts[1]}.${dateParts[0]}`;
                                
                                return `
                                <tr style="background: ${b.participated == 1 ? 'rgba(34, 197, 94, 0.1)' : 'rgba(239, 68, 68, 0.1)'};">
                                    <td style="padding: 0.75rem;">${germanDate}</td>
                                    <td style="padding: 0.75rem;">${b.battle_time}</td>
                                    <td style="padding: 0.75rem;">${bTypeIcon(b.battle_type)}</td>
                                    <td style="padding: 0.75rem;">${escapeHtml(b.opponent_guild)}</td>
                                    <td style="padding: 0.75rem; text-align: center; font-size: 1.2rem;">${b.participated == 1 ? '✅' : '❌'}</td>
                                </tr>
                            `}).join('')}
                        </tbody>
                    </table>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        
        // Close on overlay click
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.remove();
            }
        });
        
    } catch (error) {
        alert('Fehler beim Laden der Spieler-Details');
    }
}

// Initialize
loadGuilds();
loadStats();