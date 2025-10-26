<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MCP PHP Server - Model Context Protocol Implementation</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        header {
            text-align: center;
            padding: 60px 20px;
            color: white;
        }

        .logo {
            font-size: 3rem;
            margin-bottom: 20px;
            animation: float 3s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }

        h1 {
            font-size: 3rem;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }

        .subtitle {
            font-size: 1.5rem;
            margin-bottom: 30px;
            opacity: 0.9;
        }

        .version {
            display: inline-block;
            background: rgba(255,255,255,0.2);
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
            backdrop-filter: blur(10px);
        }

        .features {
            background: white;
            border-radius: 15px;
            padding: 40px;
            margin: 40px 0;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }

        .features h2 {
            text-align: center;
            margin-bottom: 40px;
            color: #667eea;
            font-size: 2rem;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            margin-bottom: 40px;
        }

        .feature-card {
            text-align: center;
            padding: 30px 20px;
            border-radius: 10px;
            background: #f8f9fa;
            border: 2px solid transparent;
            transition: all 0.3s ease;
        }

        .feature-card:hover {
            transform: translateY(-5px);
            border-color: #667eea;
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.15);
        }

        .feature-icon {
            font-size: 3rem;
            margin-bottom: 20px;
        }

        .feature-card h3 {
            color: #333;
            margin-bottom: 15px;
            font-size: 1.3rem;
        }

        .feature-card p {
            color: #666;
            line-height: 1.6;
        }

        .cta-section {
            text-align: center;
            margin: 60px 0;
        }

        .cta-buttons {
            display: flex;
            gap: 20px;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 30px;
        }

        .btn {
            display: inline-block;
            padding: 15px 30px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            cursor: pointer;
            border: none;
            font-size: 1.1rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }

        .btn-secondary {
            background: white;
            color: #667eea;
            border: 2px solid #667eea;
        }

        .btn-secondary:hover {
            background: #667eea;
            color: white;
        }

        .tools-section {
            background: rgba(255,255,255,0.95);
            border-radius: 15px;
            padding: 40px;
            margin: 40px 0;
            backdrop-filter: blur(10px);
        }

        .tools-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }

        .tool-item {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 20px 15px;
            border-radius: 8px;
            text-align: center;
            border: 1px solid #dee2e6;
            transition: all 0.3s ease;
        }

        .tool-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .tool-name {
            font-weight: 600;
            color: #495057;
            margin-bottom: 5px;
        }

        .tool-desc {
            font-size: 0.9rem;
            color: #6c757d;
        }

        footer {
            text-align: center;
            padding: 40px 20px;
            color: white;
            opacity: 0.9;
        }

        .footer-links {
            margin-top: 20px;
        }

        .footer-links a {
            color: white;
            text-decoration: none;
            margin: 0 10px;
            opacity: 0.8;
            transition: opacity 0.3s ease;
        }

        .footer-links a:hover {
            opacity: 1;
        }

        .status-indicator {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255,255,255,0.2);
            padding: 8px 16px;
            border-radius: 20px;
            margin: 20px 0;
            backdrop-filter: blur(10px);
        }

        .status-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #28a745;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }

        /* Responsive */
        @media (max-width: 768px) {
            h1 { font-size: 2rem; }
            .subtitle { font-size: 1.2rem; }
            .features { padding: 20px; }
            .features-grid { grid-template-columns: 1fr; gap: 20px; }
            .cta-buttons { flex-direction: column; align-items: center; }
            .btn { width: 100%; max-width: 300px; }
        }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <div class="logo">🚀</div>
            <h1>MCP PHP Server</h1>
            <p class="subtitle">Advanced Model Context Protocol Implementation in PHP</p>
            <div class="status-indicator">
                <div class="status-dot"></div>
                <span>Server Online</span>
            </div>
            <div class="version">Version 2.1.0</div>
        </div>
    </header>

    <main class="container">
        <section class="features">
            <h2>Powerful Features</h2>
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">🔧</div>
                    <h3>10 Built-in Tools</h3>
                    <p>Complete toolkit including file operations, HTTP requests, system monitoring, and JSON parsing capabilities.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">🔄</div>
                    <h3>Dual Mode Operation</h3>
                    <p>Seamlessly switch between CLI mode for direct MCP connections and HTTP mode for REST API integration.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">📊</div>
                    <h3>Real-time Monitoring</h3>
                    <p>Comprehensive metrics collection, performance tracking, and detailed logging for production environments.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">🔒</div>
                    <h3>Enterprise Security</h3>
                    <p>Path restrictions, input validation, file size limits, and secure session management for production deployment.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">🏗️</div>
                    <h3>Modern Architecture</h3>
                    <p>Built with PSR standards, dependency injection, and clean code principles for maintainable projects.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">⚡</div>
                    <h3>High Performance</h3>
                    <p>Optimized for speed with async processing, minimal dependencies, and efficient memory management.</p>
                </div>
            </div>

            <div class="cta-section">
                <h2>Get Started Now</h2>
                <p style="font-size: 1.2rem; color: #666; margin-bottom: 20px;">
                    Choose how you want to interact with the MCP server
                </p>
                <div class="cta-buttons">
                    <a href="/admin/dashboard" class="btn btn-primary">🔐 Admin Panel</a>
                    <button onclick="testAPI()" class="btn btn-secondary">🧪 Test API</button>
                    <button onclick="showTools()" class="btn btn-secondary">🔧 View Tools</button>
                </div>
            </div>
        </section>

        <section class="tools-section" id="toolsSection" style="display: none;">
            <h2 style="text-align: center; color: #667eea; margin-bottom: 20px;">Available Tools</h2>
            <div class="tools-grid" id="toolsGrid">
                <!-- Tools will be loaded here -->
            </div>
        </section>
    </main>

    <footer>
        <div class="container">
            <p>&copy; 2024 MCP PHP Server. Built with ❤️ using modern PHP practices.</p>
            <div class="footer-links">
                <a href="/api/status">API Status</a>
                <a href="/api/health">Health Check</a>
                <a href="https://github.com/model-context-protocol" target="_blank">MCP Specification</a>
            </div>
        </div>
    </footer>

    <script>
        // Test API functionality
        async function testAPI() {
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
        async function showTools() {
            const section = document.getElementById('toolsSection');
            const grid = document.getElementById('toolsGrid');

            if (section.style.display === 'none') {
                grid.innerHTML = '<div style="grid-column: 1/-1; text-align: center;">⏳ Loading tools...</div>';
                section.style.display = 'block';

                try {
                    const response = await fetch('/api/tools');
                    const data = await response.json();

                    if (response.ok && data.tools && data.tools.tools) {
                        grid.innerHTML = '';
                        data.tools.tools.forEach(tool => {
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
    </script>
</body>
</html>