/**
 * SFTools Import Integration
 * 
 * Öffnet ein Modal mit SFTools request.html für direkten Daten-Import
 * ohne CSV-Download/Upload
 */

class SFToolsImport {
    constructor(guildId, guildName) {
        this.guildId = guildId;
        this.guildName = guildName;
        this.modal = null;
        this.iframe = null;
    }
    
    /**
     * Öffnet das SFTools Import Modal
     */
    open() {
        // Modal erstellen
        this.createModal();
        
        // SFTools request.html laden
        const callbackUrl = `${window.location.protocol}//${window.location.host}/sftools_callback.php`;
        const redirectUrl = encodeURIComponent(callbackUrl);
        const origin = window.location.hostname;
        
        // SFTools URL mit Parametern
        const sftoolsUrl = `https://sftools.mar21.eu/request.html?redirect=${redirectUrl}&origin=${origin}&scope=default`;
        
        console.log('Opening SFTools:', sftoolsUrl);
        
        this.iframe.src = sftoolsUrl;
        this.modal.style.display = 'flex';
        
        // Message Listener für postMessage von SFTools
        window.addEventListener('message', this.handleMessage.bind(this));
    }
    
    /**
     * Erstellt das Modal HTML
     */
    createModal() {
        // Modal Container
        this.modal = document.createElement('div');
        this.modal.id = 'sftools-import-modal';
        this.modal.style.cssText = `
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            z-index: 10000;
            align-items: center;
            justify-content: center;
        `;
        
        // Modal Content
        const content = document.createElement('div');
        content.style.cssText = `
            background: #1a1a1a;
            border: 2px solid #d4af37;
            border-radius: 8px;
            width: 90%;
            max-width: 900px;
            height: 80%;
            max-height: 700px;
            display: flex;
            flex-direction: column;
            box-shadow: 0 4px 20px rgba(212, 175, 55, 0.3);
        `;
        
        // Header
        const header = document.createElement('div');
        header.style.cssText = `
            padding: 15px 20px;
            border-bottom: 1px solid #d4af37;
            display: flex;
            justify-content: space-between;
            align-items: center;
        `;
        
        const title = document.createElement('h3');
        title.textContent = `Gilde aktualisieren: ${this.guildName}`;
        title.style.cssText = `
            margin: 0;
            color: #d4af37;
            font-size: 18px;
        `;
        
        const closeBtn = document.createElement('button');
        closeBtn.textContent = '✕';
        closeBtn.style.cssText = `
            background: none;
            border: none;
            color: #d4af37;
            font-size: 24px;
            cursor: pointer;
            padding: 0;
            width: 30px;
            height: 30px;
            line-height: 1;
        `;
        closeBtn.onclick = () => this.close();
        
        header.appendChild(title);
        header.appendChild(closeBtn);
        
        // iframe Container
        const iframeContainer = document.createElement('div');
        iframeContainer.style.cssText = `
            flex: 1;
            padding: 20px;
            overflow: hidden;
        `;
        
        // iframe
        this.iframe = document.createElement('iframe');
        this.iframe.style.cssText = `
            width: 100%;
            height: 100%;
            border: none;
            border-radius: 4px;
        `;
        
        // Loading Indicator
        const loading = document.createElement('div');
        loading.id = 'sftools-loading';
        loading.style.cssText = `
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: #d4af37;
            font-size: 16px;
        `;
        loading.textContent = 'SFTools wird geladen...';
        
        iframeContainer.appendChild(this.iframe);
        iframeContainer.appendChild(loading);
        
        // iframe onload - Loading ausblenden
        this.iframe.onload = () => {
            loading.style.display = 'none';
        };
        
        content.appendChild(header);
        content.appendChild(iframeContainer);
        this.modal.appendChild(content);
        document.body.appendChild(this.modal);
    }
    
    /**
     * Schließt das Modal
     */
    close() {
        if (this.modal) {
            this.modal.style.display = 'none';
            window.removeEventListener('message', this.handleMessage.bind(this));
        }
    }
    
    /**
     * Handler für postMessage von SFTools (falls genutzt)
     */
    handleMessage(event) {
        // Nur Messages von SFTools akzeptieren
        if (event.origin !== 'https://sftools.mar21.eu') {
            return;
        }
        
        console.log('Message from SFTools:', event.data);
        
        // Wenn Daten empfangen, Modal schließen und Seite neu laden
        if (event.data && event.data.success) {
            this.showSuccess('Import erfolgreich! Seite wird aktualisiert...');
            setTimeout(() => {
                window.location.reload();
            }, 2000);
        }
    }
    
    /**
     * Zeigt Erfolgs-Nachricht
     */
    showSuccess(message) {
        const alert = document.createElement('div');
        alert.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: #2ecc71;
            color: white;
            padding: 15px 20px;
            border-radius: 4px;
            z-index: 10001;
            box-shadow: 0 2px 10px rgba(0,0,0,0.3);
        `;
        alert.textContent = message;
        document.body.appendChild(alert);
        
        setTimeout(() => {
            alert.remove();
        }, 3000);
    }
    
    /**
     * Zeigt Fehler-Nachricht
     */
    showError(message) {
        const alert = document.createElement('div');
        alert.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: #e74c3c;
            color: white;
            padding: 15px 20px;
            border-radius: 4px;
            z-index: 10001;
            box-shadow: 0 2px 10px rgba(0,0,0,0.3);
        `;
        alert.textContent = message;
        document.body.appendChild(alert);
        
        setTimeout(() => {
            alert.remove();
        }, 5000);
    }
}

/**
 * Initialisiert den SFTools Import Button
 * 
 * @param {string} buttonId - ID des Button-Elements
 * @param {number} guildId - Guild ID
 * @param {string} guildName - Guild Name
 */
function initSFToolsImport(buttonId, guildId, guildName) {
    const button = document.getElementById(buttonId);
    if (!button) {
        console.error('SFTools Import Button not found:', buttonId);
        return;
    }
    
    button.addEventListener('click', () => {
        const importer = new SFToolsImport(guildId, guildName);
        importer.open();
    });
}

// Global verfügbar machen
window.SFToolsImport = SFToolsImport;
window.initSFToolsImport = initSFToolsImport;
