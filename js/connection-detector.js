/**
 * Connection Detector
 * Detects online/offline status and notifies the application
 */

const ConnectionDetector = {
    isOnline: true,
    listeners: [],
    
    /**
     * Initialize the connection detector
     */
    init() {
        // Check initial status
        this.updateStatus();
        
        // Listen for online/offline events
        window.addEventListener('online', () => this.handleOnline());
        window.addEventListener('offline', () => this.handleOffline());
        
        // Also check server availability periodically
        this.checkServerConnection();
        setInterval(() => this.checkServerConnection(), 30000); // Every 30 seconds
        
        console.log('[ConnectionDetector] Initialized. Status:', this.isOnline ? 'Online' : 'Offline');
    },
    
    /**
     * Update connection status based on navigator.onLine
     */
    updateStatus() {
        this.isOnline = navigator.onLine;
    },
    
    /**
     * Handle when connection is restored
     */
    handleOnline() {
        this.isOnline = true;
        console.log('[ConnectionDetector] Connection restored!');
        this.notifyListeners('online');
        this.showNotification('Connection restored! Switching to online mode.', 'success');
    },
    
    /**
     * Handle when connection is lost
     */
    handleOffline() {
        this.isOnline = false;
        console.log('[ConnectionDetector] Connection lost!');
        this.notifyListeners('offline');
        this.showNotification('No internet connection. Switching to offline mode.', 'warning');
    },
    
    /**
     * Check if server is reachable (for hybrid mode)
     */
    async checkServerConnection() {
        try {
            const response = await fetch('config/db.php', { 
                method: 'HEAD',
                mode: 'no-cors'
            });
            this.isOnline = true;
        } catch (error) {
            // Server not reachable
            this.isOnline = false;
        }
        this.notifyListeners('statuschange');
        return this.isOnline;
    },
    
    /**
     * Add listener for connection changes
     */
    addListener(callback) {
        if (typeof callback === 'function') {
            this.listeners.push(callback);
        }
    },
    
    /**
     * Remove listener
     */
    removeListener(callback) {
        const index = this.listeners.indexOf(callback);
        if (index > -1) {
            this.listeners.splice(index, 1);
        }
    },
    
    /**
     * Notify all listeners of status change
     */
    notifyListeners(event) {
        this.listeners.forEach(callback => {
            try {
                callback(event, this.isOnline);
            } catch (e) {
                console.error('[ConnectionDetector] Listener error:', e);
            }
        });
    },
    
    /**
     * Show notification to user
     */
    showNotification(message, type = 'info') {
        // Remove existing notification
        const existing = document.getElementById('connection-notification');
        if (existing) existing.remove();
        
        const notification = document.createElement('div');
        notification.id = 'connection-notification';
        notification.className = `connection-notification notification-${type}`;
        notification.innerHTML = `
            <span>${message}</span>
            <button onclick="this.parentElement.remove()">&times;</button>
        `;
        
        // Add styles
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 20px;
            border-radius: 5px;
            z-index: 10000;
            font-family: Arial, sans-serif;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
            animation: slideIn 0.3s ease;
            ${type === 'success' ? 'background: #28a745; color: white;' : ''}
            ${type === 'warning' ? 'background: #ffc107; color: #333;' : ''}
            ${type === 'info' ? 'background: #17a2b8; color: white;' : ''}
        `;
        
        document.body.appendChild(notification);
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (notification.parentElement) {
                notification.remove();
            }
        }, 5000);
    },
    
    /**
     * Get current status
     */
    getStatus() {
        return this.isOnline;
    }
};

// Add CSS for notification animation
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    .connection-notification button {
        background: none;
        border: none;
        color: inherit;
        font-size: 20px;
        cursor: pointer;
        margin-left: 10px;
    }
`;
document.head.appendChild(style);

// Auto-initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => ConnectionDetector.init());
} else {
    ConnectionDetector.init();
}
