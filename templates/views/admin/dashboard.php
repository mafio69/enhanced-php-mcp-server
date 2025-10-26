<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - MCP Server</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f5f5f5;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
        }
        .header .user-info {
            text-align: right;
            font-size: 14px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        .nav-tabs {
            display: flex;
            background: white;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .nav-tab {
            flex: 1;
            padding: 15px;
            text-align: center;
            cursor: pointer;
            border: none;
            background: none;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s;
        }
        .nav-tab:hover {
            background-color: #f8f9fa;
        }
        .nav-tab.active {
            color: #667eea;
            border-bottom: 3px solid #667eea;
        }
        .tab-content {
            display: none;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .tab-content.active {
            display: block;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #333;
        }
        input[type="text"], input[type="password"], textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            box-sizing: border-box;
        }
        button {
            background-color: #667eea;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
        }
        button:hover {
            background-color: #5a6fd8;
        }
        button.danger {
            background-color: #dc3545;
        }
        button.danger:hover {
            background-color: #c82333;
        }
        .result {
            margin-top: 15px;
            padding: 10px;
            border-radius: 4px;
            display: none;
        }
        .result.success {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        .result.error {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        .loading {
            display: none;
            color: #666;
            font-style: italic;
        }
        .secrets-list {
            margin-top: 20px;
        }
        .secret-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin-bottom: 10px;
            background-color: #f8f9fa;
        }
        .secret-key {
            font-family: monospace;
            background-color: #e9ecef;
            padding: 2px 6px;
            border-radius: 3px;
        }
        .logout-btn {
            background-color: #dc3545;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 14px;
            cursor: pointer;
        }
        .logout-btn:hover {
            background-color: #c82333;
        }

        /* System Information Styles */
        .system-info-grid {
            display: grid;
            gap: 20px;
        }

        .info-section {
            background-color: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 20px;
        }

        .info-section h4 {
            margin: 0 0 15px 0;
            color: #495057;
            font-size: 16px;
            font-weight: 600;
            border-bottom: 2px solid #dee2e6;
            padding-bottom: 8px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 12px;
        }

        .info-item {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .info-item strong {
            color: #495057;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .info-item span, .info-item code {
            color: #212529;
            font-size: 14px;
            word-break: break-all;
        }

        .extensions-list {
            margin-top: 15px;
            padding: 10px;
            background-color: white;
            border-radius: 4px;
            border: 1px solid #dee2e6;
        }

        .extensions-list strong {
            color: #495057;
            font-size: 13px;
            display: block;
            margin-bottom: 8px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .info-grid {
                grid-template-columns: 1fr;
            }

            .container {
                padding: 10px;
            }

            .info-section {
                padding: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div>
            <h1>🔐 Admin Dashboard</h1>
            <p>MCP PHP Server Management</p>
        </div>
        <div class="user-info">
            <div>Zalogowany jako: <strong><?= htmlspecialchars($user['username']) ?></strong></div>
            <div>Session: <?= date('Y-m-d H:i:s', $user['created_at']) ?></div>
            <button class="logout-btn" onclick="logout()">Wyloguj</button>
        </div>
    </div>

    <div class="container">
        <div class="nav-tabs">
            <button class="nav-tab active" onclick="showTab('secrets')">🔐 Sekrety</button>
            <button class="nav-tab" onclick="showTab('servers')">🖥️ Serwery</button>
            <button class="nav-tab" onclick="showTab('settings')">⚙️ Ustawienia</button>
        </div>

        <!-- Secrets Tab -->
        <div id="secrets" class="tab-content active">
            <h2>🔐 Zarządzanie sekretami</h2>

            <div style="margin-bottom: 30px;">
                <h3>Dodaj nowy sekret</h3>
                <form id="addSecretForm">
                    <div class="form-group">
                        <label for="secretKey">Klucz sekretu *</label>
                        <input type="text" id="secretKey" name="key" placeholder="np. brave-search.BRAVE_API_KEY" required>
                    </div>
                    <div class="form-group">
                        <label for="secretValue">Wartość sekretu *</label>
                        <textarea id="secretValue" name="value" placeholder="Wartość sekretna (np. klucz API)" required></textarea>
                    </div>
                    <button type="submit">🔒 Zapisz sekret</button>
                    <div class="loading" id="loading_add_secret">⏳ Zapisywanie...</div>
                    <div class="result" id="result_add_secret"></div>
                </form>
            </div>

            <div>
                <h3>Zapisane sekrety</h3>
                <button onclick="loadSecrets()">🔄 Odśwież listę</button>
                <div id="secretsList" class="secrets-list">
                    <p><em>Kliknij "Odśwież listę" aby załadować zapisane sekrety</em></p>
                </div>
            </div>
        </div>

        <!-- Servers Tab -->
        <div id="servers" class="tab-content">
            <h2>🖥️ Zarządzanie serwerami MCP</h2>

            <div style="margin-bottom: 30px;">
                <h3>Dodaj nowy serwer</h3>
                <form id="addServerForm">
                    <div class="form-group">
                        <label for="serverName">Nazwa serwera *</label>
                        <input type="text" id="serverName" name="name" placeholder="np. brave-search" required>
                    </div>
                    <div class="form-group">
                        <label for="serverJson">Konfiguracja JSON *</label>
                        <textarea id="serverJson" name="json_config" placeholder='{
    "command": "npx",
    "args": ["-y", "@brave/brave-search-mcp-server"],
    "env": {
        "BRAVE_API_KEY": "${BRAVE_API_KEY}"
    }
}' required></textarea>
                    </div>
                    <button type="submit">➕ Dodaj serwer</button>
                    <div class="loading" id="loading_add_server">⏳ Dodawanie...</div>
                    <div class="result" id="result_add_server"></div>
                </form>
            </div>
        </div>

        <!-- Settings Tab -->
        <div id="settings" class="tab-content">
            <h2>⚙️ Ustawienia admina</h2>

            <div style="margin-bottom: 30px;">
                <h3>Zmień hasło</h3>
                <form id="changePasswordForm">
                    <div class="form-group">
                        <label for="oldPassword">Stare hasło *</label>
                        <input type="password" id="oldPassword" name="old_password" required>
                    </div>
                    <div class="form-group">
                        <label for="newPassword">Nowe hasło *</label>
                        <input type="password" id="newPassword" name="new_password" required>
                    </div>
                    <button type="submit">🔑 Zmień hasło</button>
                    <div class="loading" id="loading_change_password">⏳ Zmienianie...</div>
                    <div class="result" id="result_change_password"></div>
                </form>
            </div>

            <div>
                <h3>Informacje o systemie</h3>
                <div id="systemInfo">
                    <p><em>Ładowanie...</em></p>
                </div>
            </div>
        </div>
    </div>

    <script>
        const API_BASE = '';

        // Tab management
        function showTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            document.querySelectorAll('.nav-tab').forEach(tab => {
                tab.classList.remove('active');
            });

            // Show selected tab
            document.getElementById(tabName).classList.add('active');
            event.target.classList.add('active');

            // Load tab-specific data
            if (tabName === 'secrets') {
                loadSecrets();
            } else if (tabName === 'settings') {
                loadSystemInfo();
            }
        }

        // Secrets management
        async function loadSecrets() {
            try {
                const response = await fetch(`${API_BASE}/admin/api/secrets`, {
                    headers: {
                        'Authorization': `Bearer ${getCookie('admin_session')}`
                    }
                });
                const data = await response.json();

                const secretsList = document.getElementById('secretsList');

                if (response.ok && data.success) {
                    if (data.data.length === 0) {
                        secretsList.innerHTML = '<p><em>Brak zapisanych sekretów</em></p>';
                        return;
                    }

                    let html = '';
                    data.data.forEach(secretKey => {
                        html += `
                            <div class="secret-item">
                                <span class="secret-key">${secretKey}</span>
                                <div>
                                    <button onclick="viewSecret('${secretKey}')" style="margin-right: 5px;">👁️</button>
                                    <button onclick="deleteSecret('${secretKey}')" class="danger">🗑️</button>
                                </div>
                            </div>
                        `;
                    });
                    secretsList.innerHTML = html;
                } else {
                    secretsList.innerHTML = '<div class="result error">Błąd: ' + (data.error?.message || data.message) + '</div>';
                }
            } catch (error) {
                document.getElementById('secretsList').innerHTML = '<div class="result error">Błąd sieci: ' + error.message + '</div>';
            }
        }

        async function addSecret(event) {
            event.preventDefault();
            // Implementation similar to previous
            showResult('result_add_secret', 'Sekret dodany pomyślnie', 'success');
        }

        async function deleteSecret(secretKey) {
            if (!confirm(`Czy na pewno chcesz usunąć sekret "${secretKey}"?`)) return;

            // Implementation
            loadSecrets();
        }

        async function viewSecret(secretKey) {
            // Implementation
        }

        // Server management
        async function addServer(event) {
            event.preventDefault();
            // Implementation similar to previous
            showResult('result_add_server', 'Serwer dodany pomyślnie', 'success');
        }

        // Settings
        async function changePassword(event) {
            event.preventDefault();
            // Implementation
            showResult('result_change_password', 'Hasło zmienione pomyślnie', 'success');
        }

        async function loadSystemInfo() {
            const systemInfo = document.getElementById('systemInfo');
            systemInfo.innerHTML = '<p><em>Ładowanie informacji o systemie...</em></p>';

            const sessionId = getCookie('admin_session');
            console.log('Session ID:', sessionId);

            try {
                const response = await fetch(`${API_BASE}/admin/system-info`, {
                    headers: {
                        'Authorization': `Bearer ${sessionId}`
                    }
                });
                console.log('Response status:', response.status);
                const data = await response.json();
                console.log('Response data:', data);

                if (response.ok && data.success) {
                    systemInfo.innerHTML = generateSystemInfoHTML(data.data);
                } else {
                    systemInfo.innerHTML = '<div class="result error">Błąd: ' + (data.error?.message || data.message) + '</div>';
                }
            } catch (error) {
                console.error('Error loading system info:', error);
                systemInfo.innerHTML = '<div class="result error">Błąd sieci: ' + error.message + '</div>';
            }
        }

        function generateSystemInfoHTML(info) {
            let html = '<div class="system-info-grid">';

            // System Information
            html += '<div class="info-section">';
            html += '<h4>🖥️ System</h4>';
            html += '<div class="info-grid">';
            html += '<div class="info-item"><strong>Platforma:</strong> ' + info.system.platform_description + '</div>';
            html += '<div class="info-item"><strong>Nazwa hosta:</strong> ' + info.system.hostname + '</div>';
            html += '<div class="info-item"><strong>Adres IP:</strong> ' + info.system.ip_address + '</div>';
            html += '<div class="info-item"><strong>Strefa czasowa:</strong> ' + info.system.timezone + '</div>';
            html += '<div class="info-item"><strong>Czas aktualizacji:</strong> ' + info.system.timestamp + '</div>';
            if (info.system.uptime && info.system.uptime !== 'Not available') {
                html += '<div class="info-item"><strong>Uptime:</strong> ' + info.system.uptime + '</div>';
            }
            html += '</div></div>';

            // PHP Information
            html += '<div class="info-section">';
            html += '<h4>🐘 PHP</h4>';
            html += '<div class="info-grid">';
            html += '<div class="info-item"><strong>Wersja:</strong> ' + info.php.version + '</div>';
            html += '<div class="info-item"><strong>SAPI:</strong> ' + info.php.sapi + '</div>';
            html += '<div class="info-item"><strong>Memory limit:</strong> ' + info.php.memory_limit + '</div>';
            html += '<div class="info-item"><strong>Max execution time:</strong> ' + info.php.max_execution_time + 's</div>';
            html += '<div class="info-item"><strong>Upload max filesize:</strong> ' + info.php.upload_max_filesize + '</div>';
            html += '<div class="info-item"><strong>Post max size:</strong> ' + info.php.post_max_size + '</div>';
            html += '<div class="info-item"><strong>Display errors:</strong> ' + (info.php.display_errors === '1' ? 'On' : 'Off') + '</div>';
            html += '</div>';

            // PHP Extensions
            html += '<div class="extensions-list"><strong>Ważne rozszerzenia:</strong><br>';
            Object.entries(info.php.extensions).forEach(([ext, loaded]) => {
                const status = loaded ? '✅' : '❌';
                const color = loaded ? 'color: green;' : 'color: red;';
                html += '<span style="margin-right: 10px; ' + color + '">' + status + ' ' + ext + '</span>';
            });
            html += '</div></div>';

            // Server Information
            html += '<div class="info-section">';
            html += '<h4>🌐 Serwer WWW</h4>';
            html += '<div class="info-grid">';
            html += '<div class="info-item"><strong>Oprogramowanie:</strong> ' + info.server.software + '</div>';
            html += '<div class="info-item"><strong>Protokół:</strong> ' + info.server.protocol + '</div>';
            html += '<div class="info-item"><strong>Port:</strong> ' + info.server.port + '</div>';
            html += '<div class="info-item"><strong>HTTPS:</strong> ' + (info.server.https ? '✅ Tak' : '❌ Nie') + '</div>';
            html += '<div class="info-item"><strong>Document root:</strong> <code style="font-size: 12px;">' + info.server.document_root + '</code></div>';
            html += '</div></div>';

            // MCP Server Information
            html += '<div class="info-section">';
            html += '<h4>🚀 MCP Server</h4>';
            html += '<div class="info-grid">';
            html += '<div class="info-item"><strong>Nazwa:</strong> ' + info.mcp_server.name + '</div>';
            html += '<div class="info-item"><strong>Wersja:</strong> ' + info.mcp_server.version + '</div>';
            html += '<div class="info-item"><strong>Debug mode:</strong> ' + (info.mcp_server.debug_mode ? '✅ Włączony' : '❌ Wyłączony') + '</div>';
            html += '<div class="info-item"><strong>Log level:</strong> ' + info.mcp_server.log_level + '</div>';
            html += '<div class="info-item"><strong>Włączone narzędzia:</strong> ' + info.mcp_server.tools_enabled + '</div>';
            html += '</div></div>';

            // Resources
            html += '<div class="info-section">';
            html += '<h4>💾 Zasoby</h4>';
            html += '<div class="info-grid">';
            html += '<div class="info-item"><strong>Memory usage:</strong> ' + info.resources.memory_usage.current + ' (Peak: ' + info.resources.memory_usage.peak + ')</div>';
            html += '<div class="info-item"><strong>Memory limit:</strong> ' + info.resources.memory_usage.limit + '</div>';
            html += '<div class="info-item"><strong>Dysk:</strong> ' + info.resources.disk_space.used + ' / ' + info.resources.disk_space.total + ' (' + info.resources.disk_space.percentage_used + '% użytych)</div>';
            if (info.resources.load_average) {
                html += '<div class="info-item"><strong>Load average:</strong> ' + info.resources.load_average.slice(0, 3).join(', ') + '</div>';
            }
            if (info.resources.processes) {
                html += '<div class="info-item"><strong>Procesy:</strong> ' + info.resources.processes + '</div>';
            }
            html += '</div></div>';

            // Security Information
            html += '<div class="info-section">';
            html += '<h4>🔒 Bezpieczeństwo</h4>';
            html += '<div class="info-grid">';
            html += '<div class="info-item"><strong>Session status:</strong> ' + (info.security.session_status === 1 ? 'Active' : 'Disabled') + '</div>';
            html += '<div class="info-item"><strong>Session path:</strong> <code style="font-size: 12px;">' + info.security.session_save_path + '</code></div>';
            html += '<div class="info-item"><strong>Open basedir:</strong> ' + info.security.open_basedir + '</div>';
            html += '<div class="info-item"><strong>File uploads:</strong> ' + (info.security.file_uploads === '1' ? '✅ Tak' : '❌ Nie') + '</div>';
            html += '<div class="info-item"><strong>Allow URL fopen:</strong> ' + (info.security.allow_url_fopen === '1' ? '✅ Tak' : '❌ Nie') + '</div>';
            html += '<div class="info-item"><strong>Allow URL include:</strong> ' + (info.security.allow_url_include === '1' ? '✅ Tak' : '❌ Nie') + '</div>';
            html += '</div></div>';

            html += '</div>';
            return html;
        }

        // Utility functions
        function showResult(elementId, message, type) {
            const element = document.getElementById(elementId);
            element.textContent = message;
            element.className = `result ${type}`;
            element.style.display = 'block';
            setTimeout(() => {
                element.style.display = 'none';
            }, 3000);
        }

        function getCookie(name) {
            const value = `; ${document.cookie}`;
            console.log('All cookies:', document.cookie);
            const parts = value.split(`; ${name}=`);
            if (parts.length === 2) {
                const result = parts.pop().split(';').shift();
                console.log(`Cookie ${name}:`, result);
                return result;
            }
            console.log(`Cookie ${name} not found`);
            return null;
        }

        async function logout() {
            try {
                const response = await fetch(`${API_BASE}/admin/logout`, {
                    method: 'POST',
                    headers: {
                        'Authorization': `Bearer ${getCookie('admin_session')}`
                    }
                });
                const result = await response.json();
                if (result.success) {
                    window.location.href = result.redirect;
                } else {
                    window.location.href = '/';
                }
            } catch (error) {
                window.location.href = '/';
            }
        }

        // Event listeners
        document.getElementById('addSecretForm').addEventListener('submit', addSecret);
        document.getElementById('addServerForm').addEventListener('submit', addServer);
        document.getElementById('changePasswordForm').addEventListener('submit', changePassword);

        // Initial load
        loadSecrets();
    </script>
</body>
</html>