// Real-time Notifications using WebSocket
class RealTimeNotifications {
    constructor() {
        this.ws = null;
        this.userId = null;
        this.isConnected = false;
        this.reconnectAttempts = 0;
        this.maxReconnectAttempts = 5;
        this.reconnectDelay = 3000;
    }

    connect(userId) {
        this.userId = userId;
        
        try {
            // Connect to WebSocket server
            this.ws = new WebSocket('ws://localhost:8080');
            
            this.ws.onopen = () => {
                console.log('WebSocket connected');
                this.isConnected = true;
                this.reconnectAttempts = 0;
                
                // Authenticate user
                this.ws.send(JSON.stringify({
                    type: 'auth',
                    user_id: this.userId
                }));
            };
            
            this.ws.onmessage = (event) => {
                const data = JSON.parse(event.data);
                this.handleNotification(data);
            };
            
            this.ws.onclose = () => {
                console.log('WebSocket disconnected');
                this.isConnected = false;
                this.handleReconnect();
            };
            
            this.ws.onerror = (error) => {
                console.error('WebSocket error:', error);
                this.isConnected = false;
            };
            
        } catch (error) {
            console.error('Failed to connect to WebSocket:', error);
            this.fallbackToPolling();
        }
    }
    
    handleReconnect() {
        if (this.reconnectAttempts < this.maxReconnectAttempts) {
            this.reconnectAttempts++;
            console.log(`Attempting to reconnect (${this.reconnectAttempts}/${this.maxReconnectAttempts})...`);
            
            setTimeout(() => {
                this.connect(this.userId);
            }, this.reconnectDelay);
        } else {
            console.log('Max reconnection attempts reached, falling back to polling');
            this.fallbackToPolling();
        }
    }
    
    fallbackToPolling() {
        console.log('Using polling fallback for notifications');
        // Fallback to existing polling mechanism
        if (typeof pollNotifications === 'function') {
            setInterval(pollNotifications, 30000);
        }
    }
    
    handleNotification(data) {
        if (data.type === 'notification') {
            // Show toast notification
            this.showToast(data.message, 'info');
            
            // Update notification badge
            this.updateNotificationBadge();
            
            // Play notification sound
            this.playNotificationSound();
            
            // Show browser notification if permitted
            this.showBrowserNotification(data.message);
        }
    }
    
    showToast(message, type = 'info') {
        if (typeof showToast === 'function') {
            showToast(message, type);
        } else {
            // Fallback toast implementation
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            toast.textContent = message;
            document.body.appendChild(toast);
            
            setTimeout(() => {
                toast.remove();
            }, 5000);
        }
    }
    
    updateNotificationBadge() {
        const badge = document.getElementById('notification-badge');
        if (badge) {
            const currentCount = parseInt(badge.textContent) || 0;
            badge.textContent = currentCount + 1;
            badge.style.display = 'block';
        }
    }
    
    playNotificationSound() {
        // Create audio element for notification sound
        const audio = new Audio('data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp66hVFApGn+DyvmwhBSuBzvLZiTYIG2m98OScTgwOUarm7blmGgU7k9n1unEiBC13yO/eizEIHWq+8+OWT');
        audio.play().catch(e => console.log('Audio play failed:', e));
    }
    
    showBrowserNotification(message) {
        if ('Notification' in window && Notification.permission === 'granted') {
            new Notification('Pet Care Notification', {
                body: message,
                icon: '/assets/images/favicon.ico'
            });
        }
    }
    
    requestNotificationPermission() {
        if ('Notification' in window && Notification.permission === 'default') {
            Notification.requestPermission().then(permission => {
                if (permission === 'granted') {
                    console.log('Browser notifications enabled');
                }
            });
        }
    }
    
    disconnect() {
        if (this.ws) {
            this.ws.close();
            this.ws = null;
        }
        this.isConnected = false;
    }
    
    sendNotification(userId, message) {
        if (this.isConnected && this.ws) {
            this.ws.send(JSON.stringify({
                type: 'notification',
                user_id: userId,
                message: message
            }));
        }
    }
}

// Initialize real-time notifications
const realTimeNotifications = new RealTimeNotifications();

// Request notification permission on page load
document.addEventListener('DOMContentLoaded', () => {
    realTimeNotifications.requestNotificationPermission();
});

// Connect when user logs in
window.connectRealTimeNotifications = function(userId) {
    realTimeNotifications.connect(userId);
};

// Disconnect when user logs out
window.disconnectRealTimeNotifications = function() {
    realTimeNotifications.disconnect();
}; 