/**
 * S&F Guilds - Main JavaScript
 * Common utilities and functions
 */

/**
 * Escape HTML to prevent XSS
 */
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/**
 * Format datetime string to German locale
 */
function formatDateTime(dateString) {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleDateString('de-DE', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

/**
 * Format date string to German locale (date only)
 */
function formatDate(dateString) {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleDateString('de-DE', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric'
    });
}

/**
 * Show alert message
 */
function showAlert(message, type = 'success') {
    const container = document.getElementById('alertContainer');
    if (!container) return;
    
    const alert = document.createElement('div');
    alert.className = `alert alert-${type}`;
    alert.textContent = message;
    container.appendChild(alert);
    
    setTimeout(() => alert.remove(), 5000);
}

/**
 * Fetch wrapper with error handling
 */
async function fetchJSON(url, options = {}) {
    try {
        const response = await fetch(url, options);
        const data = await response.json();
        return data;
    } catch (error) {
        console.error('Fetch error:', error);
        return { success: false, message: 'Netzwerkfehler' };
    }
}

/**
 * Custom Confirm Dialog
 * Replaces native browser confirm() with styled modal
 */
function confirmDialog(message, onConfirm, onCancel = null) {
    // Create modal overlay
    const overlay = document.createElement('div');
    overlay.className = 'confirm-overlay';
    
    // Create modal
    const modal = document.createElement('div');
    modal.className = 'confirm-modal';
    
    // Create content
    modal.innerHTML = `
        <div class="confirm-header">Bestätigung erforderlich</div>
        <div class="confirm-message">${escapeHtml(message)}</div>
        <div class="confirm-actions">
            <button class="btn btn-secondary confirm-cancel">Abbrechen</button>
            <button class="btn btn-danger confirm-ok">OK</button>
        </div>
    `;
    
    overlay.appendChild(modal);
    document.body.appendChild(overlay);
    
    // Focus OK button
    setTimeout(() => modal.querySelector('.confirm-ok').focus(), 100);
    
    // Handle clicks
    const cleanup = () => {
        overlay.remove();
    };
    
    modal.querySelector('.confirm-ok').addEventListener('click', () => {
        cleanup();
        if (onConfirm) onConfirm();
    });
    
    modal.querySelector('.confirm-cancel').addEventListener('click', () => {
        cleanup();
        if (onCancel) onCancel();
    });
    
    // Close on overlay click
    overlay.addEventListener('click', (e) => {
        if (e.target === overlay) {
            cleanup();
            if (onCancel) onCancel();
        }
    });
    
    // ESC key to close
    const escHandler = (e) => {
        if (e.key === 'Escape') {
            cleanup();
            document.removeEventListener('keydown', escHandler);
            if (onCancel) onCancel();
        }
    };
    document.addEventListener('keydown', escHandler);
}
