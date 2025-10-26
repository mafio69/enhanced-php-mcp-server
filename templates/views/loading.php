<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MCP PHP Server - Model Context Protocol Implementation</title>
    <link rel="stylesheet" href="/assets/css/landing.css">
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
                    <h3>11 Built-in Tools</h3>
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

    <script src="/assets/js/landing.js"></script>
</body>
</html>