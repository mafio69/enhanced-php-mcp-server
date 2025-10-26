/**
 * Landing Page JavaScript
 * Handles API testing, tools display, and server status monitoring
 */

// Test API functionality
window.testAPI = async function() {
    const btn = event.target;
    const originalText = btn.textContent;
    btn.textContent = '⏳ Testing...';
    btn.disabled = true;

    try {
        const response = await fetch('/api/status', {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
            }
        });

        const data = await response.json();

        if (response.ok) {
            showNotification('✅ API is working! Server status: ' + (data.status || 'OK'), 'success');
        } else {
            showNotification('❌ API Error: ' + (data.error || 'Unknown error'), 'error');
        }
    } catch (error) {
        showNotification('❌ Network Error: ' + error.message, 'error');
    } finally {
        btn.textContent = originalText;
        btn.disabled = false;
    }
}

// Load and display tools
window.showTools = async function() {
    const section = document.getElementById('toolsSection');
    const grid = document.getElementById('toolsGrid');

    if (section.style.display === 'none') {
        grid.innerHTML = '<div style="grid-column: 1/-1; text-align: center;">⏳ Loading tools...</div>';
        section.style.display = 'block';

        try {
            const response = await fetch('/api/tools');
            const data = await response.json();

            if (response.ok && data) {
                grid.innerHTML = '';
                data.forEach(tool => {
                    const toolElement = document.createElement('div');
                    toolElement.className = 'tool-item';
                    toolElement.innerHTML = `
                        <div class="tool-name">${tool.name}</div>
                        <div class="tool-desc">${tool.description || 'No description available'}</div>
                    `;
                    grid.appendChild(toolElement);
                });
            } else {
                grid.innerHTML = '<div style="grid-column: 1/-1; text-align: center; color: red;">❌ Failed to load tools</div>';
                console.log('API Response:', data);
            }
        } catch (error) {
            grid.innerHTML = '<div style="grid-column: 1/-1; text-align: center; color: red;">❌ Network error loading tools</div>';
            console.error('Error:', error);
        }
    } else {
        section.style.display = 'none';
    }
}

// Notification system
function showNotification(message, type = 'info') {
    // Remove existing notifications
    const existing = document.querySelector('.notification');
    if (existing) existing.remove();

    const notification = document.createElement('div');
    notification.className = 'notification';
    notification.textContent = message;
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: ${type === 'success' ? '#28a745' : type === 'error' ? '#dc3545' : '#17a2b8'};
        color: white;
        padding: 15px 20px;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        z-index: 1000;
        animation: slideIn 0.3s ease;
        max-width: 300px;
    `;

    const style = document.createElement('style');
    style.textContent = `
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
    `;
    document.head.appendChild(style);

    document.body.appendChild(notification);

    // Auto-remove after 5 seconds
    setTimeout(() => {
        notification.style.animation = 'slideIn 0.3s ease reverse';
        setTimeout(() => notification.remove(), 300);
    }, 5000);
}

// Check server status on load
document.addEventListener('DOMContentLoaded', function() {
    fetch('/api/health')
        .then(response => response.json())
        .then(data => {
            const statusIndicator = document.querySelector('.status-indicator span');
            const statusDot = document.querySelector('.status-dot');

            if (data.status === 'healthy' || data.status === 'ok') {
                statusIndicator.textContent = 'Server Online';
                statusDot.style.background = '#28a745';
            } else {
                statusIndicator.textContent = 'Server Issues';
                statusDot.style.background = '#ffc107';
            }
        })
        .catch(() => {
            const statusIndicator = document.querySelector('.status-indicator span');
            const statusDot = document.querySelector('.status-dot');
            statusIndicator.textContent = 'Server Offline';
            statusDot.style.background = '#dc3545';
        });
});