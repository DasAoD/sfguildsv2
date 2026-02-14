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
     * Erstellt das Modal mit iframe
     */
    createModal() {
        if (this.modal) {
            return; // Modal bereits erstellt
        }

        // Modal Overlay
        this.modal = document.createElement('div');
        this.modal.className = 'modal';
        this.modal.style.cssText = `
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            z-index: 9999;
            align-items: center;
            justify-content: center;
        `;

        // Modal Content
        const content = document.createElement('div');
        content.className = 'modal-content';
        content.style.cssText = `
            background: var(--color-bg-secondary, #1a1a1a);
            border-radius: 8px;
            width: 95%;
            max-width: 1400px;
            height: 95%;
            position: relative;
            display: flex;
            flex-direction: column;
        `;

        // Header
        const header = document.createElement('div');
        header.className = 'modal-header';
        header.style.cssText = `
            padding: 0.75rem 1rem;
            border-bottom: 1px solid var(--color-border, #333);
            display: flex;
            justify-content: space-between;
            align-items: center;
        `;

        const title = document.createElement('h3');
        title.textContent = `SFTools Import: ${this.guildName}`;
        title.style.margin = '0';

        const closeBtn = document.createElement('button');
        closeBtn.textContent = '×';
        closeBtn.className = 'modal-close';
        closeBtn.style.cssText = `
            background: none;
            border: none;
            color: inherit;
            font-size: 2rem;
            cursor: pointer;
            line-height: 1;
        `;
        closeBtn.onclick = () => this.close();

        header.appendChild(title);
        header.appendChild(closeBtn);

        // iFrame Container
        const iframeContainer = document.createElement('div');
        iframeContainer.style.cssText = `
            flex: 1;
            padding: 0.5rem;
            overflow: hidden;
        `;

        this.iframe = document.createElement('iframe');
        this.iframe.style.cssText = `
            width: 100%;
            height: 100%;
            border: none;
            border-radius: 4px;
            background: white;
        `;

        iframeContainer.appendChild(this.iframe);
        content.appendChild(header);
        content.appendChild(iframeContainer);
        this.modal.appendChild(content);
        document.body.appendChild(this.modal);

        // Click outside to close
        this.modal.addEventListener('click', (e) => {
            if (e.target === this.modal) {
                this.close();
            }
        });
    }

    /**
     * Schließt das Modal
     */
    close() {
        if (this.modal) {
            this.modal.style.display = 'none';
            if (this.iframe) {
                this.iframe.src = 'about:blank';
            }
        }
    }

    /**
     * Handler für postMessage von SFTools (falls verwendet)
     */
    handleMessage(event) {
        if (event.origin !== 'https://sftools.mar21.eu') {
            return;
        }

        console.log('Message from SFTools:', event.data);

        if (event.data.type === 'import_complete') {
            this.showNotification('Import erfolgreich!', 'success');
            this.close();
            
            // Seite neu laden um aktualisierte Daten zu zeigen
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        }
    }

    /**
     * Zeigt eine Benachrichtigung
     */
    showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.style.cssText = `
            position: fixed;
            top: 2rem;
            right: 2rem;
            padding: 1rem 1.5rem;
            border-radius: 4px;
            background: ${type === 'success' ? 'var(--color-success, #22c55e)' : 'var(--color-error, #ef4444)'};
            color: white;
            font-weight: 500;
            z-index: 10000;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        `;
        notification.textContent = message;

        document.body.appendChild(notification);

        setTimeout(() => {
            notification.remove();
        }, 5000);
    }
}

/**
 * Initialisiert den SFTools Import Button
 * 
 * @param {string} buttonId - ID des Buttons
 * @param {number} guildId - ID der Gilde
 * @param {string} guildName - Name der Gilde
 */
function initSFToolsImport(buttonId, guildId, guildName) {
    const button = document.getElementById(buttonId);
    if (!button) {
        console.error('SFTools Import Button not found:', buttonId);
        return;
    }

    // Button einblenden
    button.style.display = 'inline-block';

    button.addEventListener('click', () => {
        const importer = new SFToolsImport(guildId, guildName);
        importer.open();
    });
}

// Global verfügbar machen
window.SFToolsImport = SFToolsImport;
window.initSFToolsImport = initSFToolsImport;
