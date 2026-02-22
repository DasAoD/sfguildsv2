// Tab switching
function switchTab(tab){
document.querySelectorAll('.tab').forEach(t=>t.classList.remove('active'));
document.querySelectorAll('.tab-content').forEach(c=>c.classList.remove('active'));
event.target.classList.add('active');
document.getElementById('tab-'+tab).classList.add('active');
if(tab==='system')loadSystemInfo();
if(tab==='logs')loadLogs();
}


// Users
// Rollen-Badge
function roleBadge(role) {
    const map = {
        admin:     'background:var(--color-error);color:#fff',
        moderator: 'background:var(--color-warning,#f59e0b);color:#000',
        user:      'background:var(--color-border);color:var(--color-text-primary)'
    };
    const style = map[role] || map.user;
    return `<span style="padding:0.15rem 0.5rem;border-radius:999px;font-size:0.75rem;font-weight:600;${style}">${escapeHtml(role)}</span>`;
}

async function loadUsers(){
const r=await fetch('/api/admin_users.php');
const d=await r.json();
if(d.success){
const currentUser = CURRENT_USER;
const tbody=document.getElementById('usersTable');
tbody.innerHTML=d.users.map(u=>{
const isCurrentUser=u.username===currentUser;
return`
<tr>
<td>${escapeHtml(u.username)}</td>
<td>${roleBadge(u.role)}</td>
<td>${formatDate(u.created_at)}</td>
<td class="actions">
<button class="btn btn-secondary" onclick="openEditUser(${u.id},'${escapeHtml(u.username)}','${escapeHtml(u.role)}')">Bearbeiten</button>
<button class="btn btn-danger" onclick="deleteUser(${u.id},'${escapeHtml(u.username)}')" ${isCurrentUser?'disabled title="Du kannst dich nicht selbst löschen"':''}>Löschen</button>
</td>
</tr>
`;}).join('');
}
}

function openUserModal(){
document.getElementById('userModalTitle').textContent='Neuer Benutzer';
document.getElementById('userId').value='';
document.getElementById('username').value='';
document.getElementById('username').disabled=false;
document.getElementById('userRole').value='user';
document.getElementById('password').value='';
document.getElementById('password').required=true;
document.getElementById('passwordHint').style.display='none';
document.getElementById('userModal').classList.add('active');
}

function openEditUser(userId, username, role){
document.getElementById('userModalTitle').textContent='Benutzer bearbeiten';
document.getElementById('userId').value=userId;
document.getElementById('username').value=username;
document.getElementById('username').disabled=true;
document.getElementById('userRole').value=role;
document.getElementById('password').value='';
document.getElementById('password').required=false;
document.getElementById('passwordHint').style.display='block';
document.getElementById('userModal').classList.add('active');
}

function closeUserModal(){
document.getElementById('userModal').classList.remove('active');
}

document.getElementById('userForm').addEventListener('submit',async(e)=>{
e.preventDefault();
const userId=document.getElementById('userId').value;
const username=document.getElementById('username').value;
const role=document.getElementById('userRole').value;
const password=document.getElementById('password').value;

if(userId){
    // Edit existing user
    const updates = [];

    // Rolle ändern
    const rRole=await fetch('/api/admin_users.php',{method:'PUT',headers:{'Content-Type':'application/json'},body:JSON.stringify({user_id:parseInt(userId),role})});
    const dRole=await rRole.json();
    if(!dRole.success){ showAlert(dRole.message,'error'); return; }
    updates.push('Rolle');

    // Passwort reset (nur wenn ausgefüllt)
    if(password){
        const rPw=await fetch('/api/admin_users.php',{method:'PUT',headers:{'Content-Type':'application/json'},body:JSON.stringify({user_id:parseInt(userId),new_password:password})});
        const dPw=await rPw.json();
        if(!dPw.success){ showAlert(dPw.message,'error'); return; }
        updates.push('Passwort');
    }
    showAlert(updates.join(' & ')+' erfolgreich geändert','success');
}else{
    // Neuer User
    if(!password){ showAlert('Passwort erforderlich','error'); return; }
    const r=await fetch('/api/admin_users.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({username,password,role})});
    const d=await r.json();
    showAlert(d.message,d.success?'success':'error');
    if(!d.success) return;
}
closeUserModal();
loadUsers();
});

async function deleteUser(userId,username){
confirmDialog(`Benutzer "${username}" wirklich löschen?`, async () => {
const r=await fetch('/api/admin_users.php',{method:'DELETE',headers:{'Content-Type':'application/json'},body:JSON.stringify({user_id:userId})});
const d=await r.json();
showAlert(d.message,d.success?'success':'error');
loadUsers();
});
}

// Guilds
async function loadGuilds(){
const r=await fetch('/api/admin_guilds.php');
const d=await r.json();
if(d.success){
const tbody=document.getElementById('guildsTable');
tbody.innerHTML=d.guilds.map(g=>{
const crestImg=g.crest_file?`<img src="/assets/images/${g.crest_file}" alt="Wappen" style="width:32px;height:32px;object-fit:contain">`:'—';
return`
<tr>
<td>${crestImg}</td>
<td>${escapeHtml(g.name)}</td>
<td>${escapeHtml(g.server)}</td>
<td>${escapeHtml(g.tag)||'-'}</td>
<td>${escapeHtml(g.notes)||'-'}</td>
<td class="actions">
<button class="btn btn-secondary" onclick="editGuild(${g.id},'${escapeHtml(g.name)}','${escapeHtml(g.server)}','${escapeHtml(g.tag)||''}','${escapeHtml(g.notes)||''}','${escapeHtml(g.crest_file)||''}')">Bearbeiten</button>
<button class="btn btn-danger" onclick="deleteGuild(${g.id},'${escapeHtml(g.name)}')">Löschen</button>
</td>
</tr>
`;}).join('');
}
}

function openGuildModal(){
document.getElementById('guildModalTitle').textContent='Neue Gilde';
document.getElementById('guildId').value='';
document.getElementById('guildName').value='';
document.getElementById('guildServer').value='';
document.getElementById('guildTag').value='';
document.getElementById('guildNotes').value='';
document.getElementById('currentCrest').value='';
document.getElementById('guildCrest').value='';
document.getElementById('currentCrestPreview').innerHTML='';
document.getElementById('guildModal').classList.add('active');
}

function closeGuildModal(){
document.getElementById('guildModal').classList.remove('active');
}

function editGuild(id,name,server,tag,notes,crest){
document.getElementById('guildModalTitle').textContent='Gilde bearbeiten';
document.getElementById('guildId').value=id;
document.getElementById('guildName').value=name;
document.getElementById('guildServer').value=server;
document.getElementById('guildTag').value=tag;
document.getElementById('guildNotes').value=notes;
document.getElementById('currentCrest').value=crest;
document.getElementById('guildCrest').value='';
if(crest){
document.getElementById('currentCrestPreview').innerHTML=`<div style="display:flex;align-items:center;gap:0.5rem"><img src="/assets/images/${crest}" style="width:32px;height:32px;object-fit:contain"><span style="color:var(--color-text-secondary);font-size:0.875rem">${crest}</span></div>`;
}else{
document.getElementById('currentCrestPreview').innerHTML='';
}
document.getElementById('guildModal').classList.add('active');
}

document.getElementById('guildForm').addEventListener('submit',async(e)=>{
e.preventDefault();
const guildId=document.getElementById('guildId').value;
const name=document.getElementById('guildName').value;
const server=document.getElementById('guildServer').value;
const tag=document.getElementById('guildTag').value;
const notes=document.getElementById('guildNotes').value;
const crestFile=document.getElementById('guildCrest').files[0];
const crestError=document.getElementById('guildCrestError');

// Client-side Größencheck
crestError.style.display='none';
if(crestFile && crestFile.size > 2*1024*1024){
    crestError.textContent='Wappen-Datei zu groß (max. 2MB)';
    crestError.style.display='block';
    return;
}

const formData=new FormData();
formData.append('name',name);
formData.append('server',server);
formData.append('tag',tag);
formData.append('notes',notes);
if(guildId){
formData.append('_method','PUT');
formData.append('guild_id',guildId);
}
if(crestFile){
formData.append('crest',crestFile);
}
const r=await fetch('/api/admin_guilds.php',{method:'POST',body:formData});
const d=await r.json();
if(d.success){
showAlert(d.message,'success');
closeGuildModal();
loadGuilds();
}else{
// Wappen-Fehler direkt im Modal anzeigen, andere als Toast
if(d.message && d.message.toLowerCase().includes('wappen')){
    crestError.textContent=d.message;
    crestError.style.display='block';
}else{
    showAlert(d.message,'error');
}
}
});

async function deleteGuild(guildId,name){
confirmDialog(`Gilde "${name}" wirklich löschen?\n\nAlle Mitglieder, Kampfdaten und das Wappen werden ebenfalls gelöscht!\n\nDiese Aktion kann nicht rückgängig gemacht werden.`, async () => {
const r=await fetch('/api/admin_guilds.php',{method:'DELETE',headers:{'Content-Type':'application/json'},body:JSON.stringify({guild_id:guildId,force:true})});
const d=await r.json();
showAlert(d.message,d.success?'success':'error');
loadGuilds();
});
}

// System
async function loadSystemInfo(){
const r=await fetch('/api/admin_system.php?action=info',{credentials:'same-origin'});
const d=await r.json();
if(d.success){
const cards=document.querySelectorAll('#systemInfo .info-card');
const values=['php_version','sqlite_version','db_size','disk_free','users','guilds','members','battles'];
values.forEach((key,i)=>{
cards[i].querySelector('.info-value').textContent=d.info[key];
});
}else{
console.error('System info error:',d);
}
}

function downloadBackup(){
window.location.href='/api/admin_system.php?action=backup';
showAlert('Backup wird heruntergeladen...','success');
}

// ─── Logs ───────────────────────────────────────────────────────
let currentLogType = 'activity';
let filterTimeout = null;

function switchLogType(type) {
    currentLogType = type;
    document.getElementById('btnLogActivity').classList.toggle('active', type === 'activity');
    document.getElementById('btnLogError').classList.toggle('active', type === 'error');
    document.getElementById('logFilter').value = '';
    loadLogs();
}

function filterLogsDebounced() {
    clearTimeout(filterTimeout);
    filterTimeout = setTimeout(loadLogs, 300);
}

async function loadLogs() {
    const lines = document.getElementById('logLines').value;
    const filter = document.getElementById('logFilter').value;
    const container = document.getElementById('logContainer');
    
    container.innerHTML = '<div class="log-empty">Lade...</div>';
    
    const params = new URLSearchParams({
        type: currentLogType,
        lines: lines,
        filter: filter
    });
    
    try {
        const r = await fetch(`/api/admin_logs.php?action=read&${params}`);
        const d = await r.json();
        
        if (!d.success) {
            container.innerHTML = '<div class="log-empty">Fehler beim Laden</div>';
            return;
        }
        
        // Update info text
        const info = d.info;
        const infoEl = document.getElementById('logInfo');
        if (info.exists) {
            infoEl.textContent = `${info.lines} Einträge · ${info.size_human}`;
        } else {
            infoEl.textContent = 'Keine Log-Datei vorhanden';
        }
        
        // Render entries
        if (!d.entries.length) {
            container.innerHTML = '<div class="log-empty">Keine Einträge' + (filter ? ` für "${escapeHtml(filter)}"` : '') + '</div>';
            return;
        }
        
        container.innerHTML = d.entries.map(entry => {
            const ctx = entry.context;
            let contextHtml = '';
            
            if (ctx) {
                contextHtml = '<div class="log-context">' + formatContext(ctx) + '</div>';
            }
            
            // Determine icon based on log type and content
            let icon = currentLogType === 'error' ? '⚠️' : '📋';
            const msg = entry.message.toLowerCase();
            if (msg.includes('gelöscht') || msg.includes('geleert')) icon = '🗑️';
            else if (msg.includes('aktualisiert') || msg.includes('geändert')) icon = '✏️';
            else if (msg.includes('importiert')) icon = '📥';
            else if (msg.includes('login')) icon = '🔑';
            else if (msg.includes('erstellt')) icon = '➕';
            else if (msg.includes('backup')) icon = '💾';
            
            return `<div class="log-entry log-${currentLogType}">
                <div class="log-header">
                    <span class="log-icon">${icon}</span>
                    <span class="log-message">${escapeHtml(entry.message)}</span>
                    <span class="log-time">${formatLogTimestamp(entry.timestamp)}</span>
                </div>
                ${contextHtml}
            </div>`;
        }).join('');
        
    } catch (e) {
        container.innerHTML = '<div class="log-empty">Netzwerkfehler</div>';
    }
}

function formatContext(ctx) {
    if (!ctx) return '';
    
    return Object.entries(ctx).map(([key, val]) => {
        let displayVal = val;
        
        // Truncate long values (like stack traces)
        if (typeof val === 'string' && val.length > 200) {
            displayVal = val.substring(0, 200) + '…';
        }
        
        return `<span class="log-ctx-item"><span class="log-ctx-key">${escapeHtml(key)}:</span> ${escapeHtml(String(displayVal))}</span>`;
    }).join('');
}

async function clearCurrentLog() {
    const label = currentLogType === 'activity' ? 'Aktivitäten' : 'Fehler';
    confirmDialog(`${label}-Log wirklich leeren?\n\nDiese Aktion kann nicht rückgängig gemacht werden!`, async () => {
        try {
            const r = await fetch(`/api/admin_logs.php?action=clear&type=${currentLogType}`, {
                method: 'POST'
            });
            const d = await r.json();
            showAlert(d.message, d.success ? 'success' : 'error');
            loadLogs();
        } catch (e) {
            showAlert('Fehler beim Leeren', 'error');
        }
    });
}

// Utils
function escapeHtml(t){if(!t)return'';const d=document.createElement('div');d.textContent=t;return d.innerHTML;}
function formatDate(d){if(!d)return'-';const date=new Date(d);return date.toLocaleDateString('de-DE',{day:'2-digit',month:'2-digit',year:'numeric',hour:'2-digit',minute:'2-digit'});}

// Format log timestamp from "2026-02-07 00:43:00" to "07.02.2026 00:43:00"
function formatLogTimestamp(ts) {
    if (!ts) return '-';
    const m = ts.match(/^(\d{4})-(\d{2})-(\d{2}) (\d{2}:\d{2}:\d{2})$/);
    if (m) return `${m[3]}.${m[2]}.${m[1]} ${m[4]}`;
    return ts;
}

// Load initial data
loadUsers();
loadGuilds();
loadSystemInfo();

// === Maintenance: Player Rename ===

async function loadRenameGuilds() {
    // Reuse guilds data from admin_guilds API
    try {
        const r = await fetch('/api/admin_guilds.php');
        const d = await r.json();
        if (d.success) {
            const select = document.getElementById('renameGuild');
            select.innerHTML = '<option value="">-- Gilde wählen --</option>' +
                d.guilds.map(g => `<option value="${g.id}">${escapeHtml(g.name)}</option>`).join('');
        }
    } catch (e) {}
}

async function loadGuildMembers() {
    const guildId = document.getElementById('renameGuild').value;
    const oldSelect = document.getElementById('renameOldName');
    const newSelect = document.getElementById('renameNewName');
    
    if (!guildId) {
        oldSelect.innerHTML = '<option value="">-- Erst Gilde wählen --</option>';
        newSelect.innerHTML = '<option value="">-- Erst Gilde wählen --</option>';
        return;
    }
    
    oldSelect.innerHTML = '<option value="">Lade...</option>';
    newSelect.innerHTML = '<option value="">Lade...</option>';
    
    try {
        const r = await fetch(`/api/admin_player_merge.php?action=members&guild_id=${guildId}`);
        const d = await r.json();
        
        if (d.success) {
            const options = '<option value="">-- Spieler wählen --</option>' +
                d.members.map(m => `<option value="${escapeAttr(m.name)}">${escapeHtml(m.name)} (${m.level || '?'})</option>`).join('');
            oldSelect.innerHTML = options;
            newSelect.innerHTML = options;
        }
    } catch (e) {
        oldSelect.innerHTML = '<option value="">Fehler beim Laden</option>';
        newSelect.innerHTML = '<option value="">Fehler beim Laden</option>';
    }
}

async function executeRename() {
    const guildId = document.getElementById('renameGuild').value;
    const oldName = document.getElementById('renameOldName').value;
    const newName = document.getElementById('renameNewName').value;
    const resultDiv = document.getElementById('renameResult');
    
    if (!guildId || !oldName || !newName) {
        showAlert('Bitte Gilde, alten Namen und neuen Namen auswählen.', 'error');
        return;
    }
    
    if (oldName === newName) {
        showAlert('Alter und neuer Name sind identisch.', 'error');
        return;
    }
    
    confirmDialog(
        `"${oldName}" wird zu "${newName}" umbenannt.\n\nAlle Kampfeinträge werden auf den neuen Namen übertragen und der alte Mitgliedseintrag wird entfernt.\n\nFortfahren?`,
        async () => {
            try {
                const r = await fetch('/api/admin_player_merge.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ guild_id: parseInt(guildId), old_name: oldName, new_name: newName })
                });
                const d = await r.json();

                if (d.success) {
                    resultDiv.innerHTML = `<p style="color:var(--color-success)">✅ ${escapeHtml(d.message)}</p>`;
                    loadGuildMembers(); // Refresh lists
                } else {
                    resultDiv.innerHTML = `<p style="color:var(--color-error)">❌ ${escapeHtml(d.message)}</p>`;
                }
            } catch (e) {
                resultDiv.innerHTML = `<p style="color:var(--color-error)">❌ Fehler: ${escapeHtml(e.message)}</p>`;
            }
        }
    );
}

function escapeAttr(str) {
    return str.replace(/\\/g, '\\\\').replace(/'/g, "\\'").replace(/"/g, '&quot;');
}

function showAlert(message, type = 'success') {
    const container = document.getElementById('alertContainer');
    const el = document.createElement('div');
    el.className = `alert alert-${type}`;
    el.textContent = message;
    container.appendChild(el);
    setTimeout(() => el.remove(), 5000);
}

// Init maintenance guilds on load
loadRenameGuilds();
