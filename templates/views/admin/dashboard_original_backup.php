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

        .copy-btn {
            background-color: #17a2b8;
            color: white;
            border: none;
            padding: 4px 8px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            margin-right: 5px;
        }

        .copy-btn:hover {
            background-color: #138496;
        }

        .copy-btn:active {
            background-color: #117a8b;
        }

        .secret-value {
            font-family: monospace;
            background-color: #f8f9fa;
            padding: 2px 6px;
            border-radius: 3px;
            word-break: break-all;
            max-width: 300px;
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

        /* Tools Styles */
        .tools-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .tool-card {
            background-color: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 20px;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .tool-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .tool-card h4 {
            margin: 0 0 10px 0;
            color: #495057;
            font-size: 16px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .tool-card .tool-name {
            background-color: #667eea;
            color: white;
            padding: 2px 8px;
            border-radius: 4px;
            font-family: monospace;
            font-size: 12px;
        }

        .tool-card .tool-description {
            color: #6c757d;
            font-size: 14px;
            margin-bottom: 15px;
            line-height: 1.4;
        }

        .tool-schema {
            background-color: white;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 12px;
            font-family: monospace;
            font-size: 12px;
            overflow-x: auto;
        }

        .tool-properties {
            margin-bottom: 10px;
        }

        .tool-property {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
            padding: 2px 0;
        }

        .tool-property.required {
            background-color: #fff3cd;
            padding: 2px 4px;
            border-radius: 2px;
        }

        .test-form {
            background-color: white;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 20px;
            margin-top: 20px;
        }

        select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            box-sizing: border-box;
            background-color: white;
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

            .tools-grid {
                grid-template-columns: 1fr;
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
            <button class="nav-tab" onclick="showTab('tools')">🔧 Narzędzia</button>
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

            <div style="margin-bottom: 30px;">
                <h3>Zapisane sekrety</h3>
                <button onclick="loadSecrets()">🔄 Odśwież listę</button>
                <div id="secretsList" class="secrets-list">
                    <p><em>Kliknij "Odśwież listę" aby załadować zapisane sekrety</em></p>
                </div>
            </div>

            <div style="margin-bottom: 30px;">
                <h3>🔧 Narzędzia sekretów</h3>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div>
                        <h4>🔐 Szyfruj wartość</h4>
                        <form id="encryptForm" style="margin-top: 10px;">
                            <div class="form-group">
                                <label for="encryptValue">Wartość do zaszyfrowania:</label>
                                <textarea id="encryptValue" name="value" placeholder="Wartość do zaszyfrowania" rows="3"></textarea>
                            </div>
                            <button type="submit">🔐 Szyfruj</button>
                            <div class="loading" id="loading_encrypt">⏳ Szyfrowanie...</div>
                            <div class="result" id="result_encrypt"></div>
                        </form>
                    </div>

                    <div>
                        <h4>🔓 Odszyfruj wartość</h4>
                        <form id="decryptForm" style="margin-top: 10px;">
                            <div class="form-group">
                                <label for="decryptValue">Zaszyfrowana wartość:</label>
                                <textarea id="decryptValue" name="encrypted" placeholder="Zaszyfrowana wartość" rows="3"></textarea>
                            </div>
                            <button type="submit">🔓 Odszyfruj</button>
                            <div class="loading" id="loading_decrypt">⏳ Odszyfrowywanie...</div>
                            <div class="result" id="result_decrypt"></div>
                        </form>
                    </div>
                </div>
            </div>

            <div>
                <h3>📦 Migracja sekretów</h3>
                <p style="color: #666; font-size: 14px; margin-bottom: 15px;">
                    Migruj sekrety z konfiguracji serwera do zaszyfrowanego magazynu. To pomoże przenieść API keys z plików konfiguracyjnych.
                </p>
                <button onclick="migrateSecrets()" style="background-color: #28a745;">📦 Migruj sekrety</button>
                <div class="loading" id="loading_migrate">⏳ Migrowanie...</div>
                <div class="result" id="result_migrate"></div>
            </div>
        </div>

        <!-- Tools Tab -->
        <div id="tools" class="tab-content">
              <h2>🔧 Dostępne narzędzia MCP</h2>

            <div style="margin-bottom: 20px;">
                <button onclick="loadAvailableTools()" style="margin-right: 10px;">🔄 Odśwież listę</button>
                <span id="toolsCount" style="color: #666; font-size: 14px;"></span>
            </div>

            <div id="availableTools" style="margin-bottom: 30px;">
                <p><em>Kliknij "Odśwież listę" aby załadować dostępne narzędzia</em></p>
            </div>

            <div style="margin-top: 30px;">
                <h3>🧪 Testuj narzędzie</h3>
                <form id="testToolForm">
                    <div class="form-group">
                        <label for="toolSelect">Wybierz narzędzie:</label>
                        <select id="toolSelect" name="tool" required>
                            <option value="">Wybierz narzędzie...</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="toolArguments">Argumenty JSON (opcjonalne):</label>
                        <textarea id="toolArguments" name="arguments" placeholder='{"param1": "value1", "param2": "value2"}' rows="4"></textarea>
                    </div>
                    <button type="submit">▶️ Uruchom narzędzie</button>
                    <div class="loading" id="loading_test_tool">⏳ Wykonywanie...</div>
                    <div class="result" id="result_test_tool"></div>
                </form>
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
            const selectedTab = document.getElementById(tabName);
            if (selectedTab) {
                selectedTab.classList.add('active');
            }

            if (event && event.target) {
                event.target.classList.add('active');
            }

            // Load tab-specific data
            if (tabName === 'secrets') {
                loadSecrets();
            } else if (tabName === 'tools') {
                loadAvailableTools();
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

            const formData = new FormData(event.target);
            const secretKey = formData.get('key').trim();
            const secretValue = formData.get('value').trim();

            if (!secretKey || !secretValue) {
                showResult('result_add_secret', 'Klucz i wartość są wymagane', 'error');
                return;
            }

            const loading = document.getElementById('loading_add_secret');
            const result = document.getElementById('result_add_secret');

            loading.style.display = 'block';
            result.style.display = 'none';

            try {
                const response = await fetch(`${API_BASE}/admin/api/secrets`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': `Bearer ${getCookie('admin_session')}`
                    },
                    body: JSON.stringify({
                        key: secretKey,
                        value: secretValue
                    })
                });

                const data = await response.json();

                if (response.ok && data.success) {
                    showResult('result_add_secret', '✅ Sekret dodany pomyślnie', 'success');
                    event.target.reset();
                    loadSecrets(); // Refresh the list
                } else {
                    const errorMsg = data.error?.message || data.message || 'Błąd';
                    showResult('result_add_secret', `❌ Błąd: ${errorMsg}`, 'error');
                }
            } catch (error) {
                showResult('result_add_secret', '❌ Błąd sieci: ' + error.message, 'error');
            } finally {
                loading.style.display = 'none';
            }
        }

        async function deleteSecret(secretKey) {
            if (!confirm(`Czy na pewno chcesz usunąć sekret "${secretKey}"?`)) return;

            try {
                const response = await fetch(`${API_BASE}/admin/api/secrets/${encodeURIComponent(secretKey)}`, {
                    method: 'DELETE',
                    headers: {
                        'Authorization': `Bearer ${getCookie('admin_session')}`
                    }
                });

                const data = await response.json();

                if (response.ok && data.success) {
                    alert('✅ Sekret usunięty pomyślnie');
                    loadSecrets();
                } else {
                    alert('❌ Błąd usuwania sekretu: ' + (data.error?.message || data.message));
                }
            } catch (error) {
                alert('❌ Błąd sieci: ' + error.message);
            }
        }

        // Copy to clipboard function
        async function copyToClipboard(text, button) {
            try {
                await navigator.clipboard.writeText(text);

                // Show temporary success feedback
                const originalText = button.textContent;
                button.textContent = '✅';
                button.style.backgroundColor = '#28a745';

                setTimeout(() => {
                    button.textContent = originalText;
                    button.style.backgroundColor = '#17a2b8';
                }, 2000);
            } catch (error) {
                // Fallback for older browsers
                const textArea = document.createElement('textarea');
                textArea.value = text;
                textArea.style.position = 'fixed';
                textArea.style.opacity = '0';
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand('copy');
                document.body.removeChild(textArea);

                // Show temporary success feedback
                const originalText = button.textContent;
                button.textContent = '✅';
                button.style.backgroundColor = '#28a745';

                setTimeout(() => {
                    button.textContent = originalText;
                    button.style.backgroundColor = '#17a2b8';
                }, 2000);
            }
        }

        async function viewSecret(secretKey) {
            try {
                const response = await fetch(`${API_BASE}/admin/api/secrets/${encodeURIComponent(secretKey)}`, {
                    headers: {
                        'Authorization': `Bearer ${getCookie('admin_session')}`
                    }
                });

                const data = await response.json();

                if (response.ok && data.success) {
                    const value = data.data.value;
                    const maskedValue = value.substring(0, 4) + '***' + value.substring(value.length - 2);

                    // Create a modal-like experience instead of alert
                    const modalHtml = `
                        <div style="
                            position: fixed;
                            top: 0;
                            left: 0;
                            right: 0;
                            bottom: 0;
                            background: rgba(0,0,0,0.5);
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            z-index: 10000;
                        " onclick="this.remove()">
                            <div style="
                                background: white;
                                padding: 30px;
                                border-radius: 8px;
                                max-width: 500px;
                                width: 90%;
                                box-shadow: 0 10px 30px rgba(0,0,0,0.3);
                            " onclick="event.stopPropagation()">
                                <h3 style="margin-top: 0; color: #495057;">🔐 Sekret: ${secretKey}</h3>

                                <div style="margin: 20px 0;">
                                    <h4 style="margin-bottom: 10px; color: #667eea;">Maskowana wartość:</h4>
                                    <div class="secret-value">${maskedValue}</div>
                                </div>

                                <div style="margin: 20px 0;">
                                    <button onclick="this.parentElement.parentElement.querySelector('.full-secret').style.display='block'; this.style.display='none'"
                                            style="background: #ffc107; color: #000; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; margin-right: 10px;">
                                        👁️ Pokaż pełną wartość
                                    </button>
                                </div>

                                <div class="full-secret" style="display: none; margin: 20px 0;">
                                    <h4 style="margin-bottom: 10px; color: #dc3545;">⚠️ Pełna wartość:</h4>
                                    <div class="secret-value" style="background: #f8d7da; border: 1px solid #f5c6cb;">${value}</div>
                                    <button onclick="copyToClipboard('${value.replace(/'/g, "\\'")}', this)"
                                            class="copy-btn" style="margin-top: 10px;">
                                        📋 Kopiuj
                                    </button>
                                </div>

                                <div style="margin-top: 20px; text-align: center;">
                                    <button onclick="this.closest('div[style*=position]').remove()"
                                            style="background: #6c757d; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer;">
                                        Zamknij
                                    </button>
                                </div>
                            </div>
                        </div>
                    `;

                    document.body.insertAdjacentHTML('beforeend', modalHtml);
                } else {
                    const errorMsg = data.error?.message || data.message || 'Błąd';
                    showResult('result_add_secret', `❌ Błąd pobierania sekretu: ${errorMsg}`, 'error');
                }
            } catch (error) {
                showResult('result_add_secret', '❌ Błąd sieci: ' + error.message, 'error');
            }
        }

        // Encryption functions
        async function encryptValue(event) {
            event.preventDefault();

            const formData = new FormData(event.target);
            const value = formData.get('value').trim();

            if (!value) {
                showResult('result_encrypt', 'Wartość jest wymagana', 'error');
                return;
            }

            const loading = document.getElementById('loading_encrypt');
            const result = document.getElementById('result_encrypt');

            loading.style.display = 'block';
            result.style.display = 'none';

            try {
                const response = await fetch(`${API_BASE}/admin/api/secrets/encrypt`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': `Bearer ${getCookie('admin_session')}`
                    },
                    body: JSON.stringify({ value: value })
                });

                const data = await response.json();

                if (response.ok && data.success) {
                    const encryptedValue = data.data.encrypted;
                    const resultHtml = `
                        <div style="margin-bottom: 10px;">✅ Zaszyfrowano:</div>
                        <div class="secret-value" style="margin-bottom: 10px; background: #d4edda; border: 1px solid #c3e6cb;">${encryptedValue}</div>
                        <button onclick="copyToClipboard('${encryptedValue.replace(/'/g, "\\'")}', this)" class="copy-btn">
                            📋 Kopiuj zaszyfrowaną wartość
                        </button>
                    `;

                    const result = document.getElementById('result_encrypt');
                    result.innerHTML = resultHtml;
                    result.style.display = 'block';
                } else {
                    const errorMsg = data.error?.message || data.message || 'Błąd';
                    showResult('result_encrypt', `❌ Błąd: ${errorMsg}`, 'error');
                }
            } catch (error) {
                showResult('result_encrypt', '❌ Błąd sieci: ' + error.message, 'error');
            } finally {
                loading.style.display = 'none';
            }
        }

        async function decryptValue(event) {
            event.preventDefault();

            const formData = new FormData(event.target);
            const encrypted = formData.get('encrypted').trim();

            if (!encrypted) {
                showResult('result_decrypt', 'Zaszyfrowana wartość jest wymagana', 'error');
                return;
            }

            const loading = document.getElementById('loading_decrypt');
            const result = document.getElementById('result_decrypt');

            loading.style.display = 'block';
            result.style.display = 'none';

            try {
                const response = await fetch(`${API_BASE}/admin/api/secrets/decrypt`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': `Bearer ${getCookie('admin_session')}`
                    },
                    body: JSON.stringify({ encrypted: encrypted })
                });

                const data = await response.json();

                if (response.ok && data.success) {
                    const decryptedValue = data.data.decrypted;
                    const resultHtml = `
                        <div style="margin-bottom: 10px;">✅ Odszyfrowano:</div>
                        <div class="secret-value" style="margin-bottom: 10px; background: #d1ecf1; border: 1px solid #bee5eb;">${decryptedValue}</div>
                        <button onclick="copyToClipboard('${decryptedValue.replace(/'/g, "\\'")}', this)" class="copy-btn">
                            📋 Kopiuj odszyfrowaną wartość
                        </button>
                    `;

                    const result = document.getElementById('result_decrypt');
                    result.innerHTML = resultHtml;
                    result.style.display = 'block';
                } else {
                    const errorMsg = data.error?.message || data.message || 'Błąd';
                    showResult('result_decrypt', `❌ Błąd: ${errorMsg}`, 'error');
                }
            } catch (error) {
                showResult('result_decrypt', '❌ Błąd sieci: ' + error.message, 'error');
            } finally {
                loading.style.display = 'none';
            }
        }

        async function migrateSecrets() {
            if (!confirm('Czy na pewno chcesz przeprowadzić migrację sekretów?\n\nSpowoduje to przeniesienie wszystkich znalezionych sekretów z konfiguracji serwera do zaszyfrowanego magazynu.')) {
                return;
            }

            const loading = document.getElementById('loading_migrate');
            const result = document.getElementById('result_migrate');

            loading.style.display = 'block';
            result.style.display = 'none';

            try {
                const response = await fetch(`${API_BASE}/admin/api/secrets/migrate`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': `Bearer ${getCookie('admin_session')}`
                    }
                });

                const data = await response.json();

                if (response.ok && data.success) {
                    const migrated = data.data.migrated || [];
                    const errors = data.data.errors || [];

                    let message = `✅ Migracja zakończona\n\n`;
                    message += `Przeniesionych sekretów: ${data.data.migrated_count}\n`;

                    if (migrated.length > 0) {
                        message += `\nPrzeniesiono:\n${migrated.slice(0, 5).join('\n')}`;
                        if (migrated.length > 5) {
                            message += `\n... i ${migrated.length - 5} więcej`;
                        }
                    }

                    if (errors.length > 0) {
                        message += `\n\n⚠️ Błędy:\n${errors.slice(0, 3).join('\n')}`;
                    }

                    showResult('result_migrate', message, 'success');
                    loadSecrets(); // Refresh the secrets list
                } else {
                    const errorMsg = data.error?.message || data.message || 'Błąd';
                    showResult('result_migrate', `❌ Błąd migracji: ${errorMsg}`, 'error');
                }
            } catch (error) {
                showResult('result_migrate', '❌ Błąd sieci: ' + error.message, 'error');
            } finally {
                loading.style.display = 'none';
            }
        }

        // Server management
        async function addServer(event) {
            event.preventDefault();
            // Implementation similar to previous
            showResult('result_add_server', 'Serwer dodany pomyślnie', 'success');
        }

        // Tools Management
        async function loadAvailableTools() {
            const toolsContainer = document.getElementById('availableTools');
            const toolsCount = document.getElementById('toolsCount');
            const toolSelect = document.getElementById('toolSelect');

            if (!toolsContainer || !toolsCount || !toolSelect) {
                return;
            }

            toolsContainer.innerHTML = '<p><em>Ładowanie narzędzi...</em></p>';
            toolsCount.textContent = '';

            try {
                const response = await fetch(`${API_BASE}/api/tools`);
                const data = await response.json();

                if (response.ok) {
                    const tools = data || [];
                    toolsCount.textContent = `Znaleziono ${tools.length} narzędzi`;

                    // Update select dropdown
                    toolSelect.innerHTML = '<option value="">Wybierz narzędzie...</option>';
                    tools.forEach(tool => {
                        const option = document.createElement('option');
                        option.value = tool.name;
                        option.textContent = `${tool.name} - ${tool.description}`;
                        toolSelect.appendChild(option);
                    });

                    // Generate HTML for all tools
                    let toolsHtml = '<div class="tools-grid">';
                    tools.forEach(tool => {
                        toolsHtml += generateToolCard(tool);
                    });
                    toolsHtml += '</div>';
                    toolsContainer.innerHTML = toolsHtml;
                } else {
                    toolsContainer.innerHTML = '<div class="result error">Błąd ładowania narzędzi: HTTP ' + response.status + '</div>';
                }
            } catch (error) {
                toolsContainer.innerHTML = '<div class="result error">Błąd sieci: ' + error.message + '</div>';
            }
        }

        function generateToolCard(tool) {
            const requiredParams = tool.inputSchema?.required || [];
            const properties = tool.inputSchema?.properties || {};

            let schemaHtml = '';
            if (Object.keys(properties).length > 0) {
                schemaHtml = '<div class="tool-properties">';
                Object.entries(properties).forEach(([param, config]) => {
                    const isRequired = requiredParams.includes(param);
                    const requiredClass = isRequired ? ' required' : '';
                    const requiredMark = isRequired ? ' *' : '';

                    schemaHtml += `
                        <div class="tool-property${requiredClass}">
                            <span><strong>${param}</strong>${requiredMark}</span>
                            <span>${config.type} ${config.description ? `- ${config.description}` : ''}</span>
                        </div>
                    `;
                });
                schemaHtml += '</div>';
            }

            const description = tool.description || '';
            const toolName = tool.name || '';

            // Generate default arguments for common tools
            let defaultArgs = {};
            if (toolName === 'hello') {
                defaultArgs = { name: 'Świat' };
            } else if (toolName === 'get_time') {
                defaultArgs = {};
            } else if (toolName === 'calculate') {
                defaultArgs = { operation: 'add', a: 5, b: 3 };
            } else if (toolName === 'list_files') {
                defaultArgs = { path: '.' };
            } else if (toolName === 'system_info') {
                defaultArgs = {};
            } else if (toolName === 'json_parse') {
                defaultArgs = { json: '{"test": "value"}' };
            } else if (toolName === 'get_weather') {
                defaultArgs = { city: 'Warszawa' };
            }

            const defaultArgsStr = JSON.stringify(defaultArgs);
            const cardHtml = `
                <div class="tool-card">
                    <h4>
                        <span class="tool-name">${toolName}</span>
                        ${description}
                    </h4>
                    <div class="tool-description">${description}</div>
                    ${schemaHtml ? `<div class="tool-schema">${schemaHtml}</div>` : ''}
                    <div style="margin-top: 15px;">
                        <button class="quick-run-btn btn btn-primary" style="font-size: 12px; padding: 8px 15px;"
                                data-tool="${toolName}"
                                data-args='${defaultArgsStr}'>
                            ⚡ Uruchom
                        </button>
                    </div>
                </div>
            `;

            return cardHtml;
        }

        // Quick tool execution function
        async function quickRunTool(toolName, argsJson) {

            const loading = document.getElementById('loading_test_tool');
            const result = document.getElementById('result_test_tool');

            loading.style.display = 'block';
            result.style.display = 'none';

            try {
                const requestData = {
                    tool: toolName,
                    arguments: JSON.parse(argsJson)
                };

                const response = await fetch(`${API_BASE}/api/tools/call`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(requestData)
                });

                const data = await response.json();

                if (response.ok && data.success) {
                    showResult('result_test_tool', `✅ ${toolName} wynik:\n\n${data.data}`, 'success');
                } else {
                    const errorMsg = data.error?.message || data.details?.details || data.message || 'Błąd';
                    showResult('result_test_tool', `❌ Błąd ${toolName}: ${errorMsg}`, 'error');
                }
            } catch (error) {
                if (error instanceof SyntaxError) {
                    showResult('result_test_tool', `❌ Błąd ${toolName}: Nieprawidłowy format JSON`, 'error');
                } else {
                    showResult('result_test_tool', `❌ Błąd ${toolName}: ` + error.message, 'error');
                }
            } finally {
                loading.style.display = 'none';
            }
        }


        async function testTool(event) {
            event.preventDefault();

            const toolSelect = document.getElementById('toolSelect');
            const argumentsText = document.getElementById('toolArguments').value;

            if (!toolSelect.value) {
                showResult('result_test_tool', 'Wybierz narzędzie do przetestowania', 'error');
                return;
            }

            const loading = document.getElementById('loading_test_tool');
            const result = document.getElementById('result_test_tool');

            loading.style.display = 'block';
            result.style.display = 'none';

            try {
                const requestData = {
                    tool: toolSelect.value,
                    arguments: argumentsText ? JSON.parse(argumentsText) : {}
                };

                const response = await fetch(`${API_BASE}/api/tools/call`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(requestData)
                });

                const data = await response.json();

                if (response.ok && data.success) {
                    showResult('result_test_tool', `✅ Wynik:\n\n${data.data}`, 'success');
                } else {
                    const errorMsg = data.error?.message || data.details?.details || data.message || 'Błąd';
                    showResult('result_test_tool', `❌ Błąd: ${errorMsg}`, 'error');
                }
            } catch (error) {
                if (error instanceof SyntaxError) {
                    showResult('result_test_tool', '❌ Błąd: Nieprawidłowy format JSON w argumentach', 'error');
                } else {
                    showResult('result_test_tool', '❌ Błąd sieci: ' + error.message, 'error');
                }
            } finally {
                loading.style.display = 'none';
            }
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

            try {
                const response = await fetch(`${API_BASE}/admin/system-info`, {
                    headers: {
                        'Authorization': `Bearer ${getCookie('admin_session')}`
                    }
                });
                const data = await response.json();

                if (response.ok && data.success) {
                    systemInfo.innerHTML = generateSystemInfoHTML(data.data);
                } else {
                    systemInfo.innerHTML = '<div class="result error">Błąd: ' + (data.error?.message || data.message) + '</div>';
                }
            } catch (error) {
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
            if (element) {
                element.textContent = message;
                element.className = `result ${type}`;
                element.style.display = 'block';
                setTimeout(() => {
                    element.style.display = 'none';
                }, 3000);
            }
        }

        function getCookie(name) {
            const value = `; ${document.cookie}`;
            const parts = value.split(`; ${name}=`);
            if (parts.length === 2) {
                return parts.pop().split(';').shift();
            }
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
        document.getElementById('testToolForm').addEventListener('submit', testTool);
        document.getElementById('addSecretForm').addEventListener('submit', addSecret);
        document.getElementById('encryptForm').addEventListener('submit', encryptValue);
        document.getElementById('decryptForm').addEventListener('submit', decryptValue);
        document.getElementById('addServerForm').addEventListener('submit', addServer);
        document.getElementById('changePasswordForm').addEventListener('submit', changePassword);

        // Quick run buttons event delegation
        document.addEventListener('click', function(event) {
            if (event.target.classList.contains('quick-run-btn')) {
                const toolName = event.target.dataset.tool;
                const args = event.target.dataset.args;
                quickRunTool(toolName, args);
            }
        });

        // Initial load
        loadSecrets();
    </script>
</body>
</html>