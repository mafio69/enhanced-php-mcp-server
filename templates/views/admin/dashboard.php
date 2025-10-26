<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - MCP Server</title>
    <link rel="stylesheet" href="/assets/css/dashboard.css">
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
            <button class="logout-btn" onclick="AuthManager.logout()">Wyloguj</button>
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
                <button onclick="SecretsManager.loadSecrets()">🔄 Odśwież listę</button>
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
                <button onclick="SecretsManager.migrateSecrets()" style="background-color: #28a745;">📦 Migruj sekrety</button>
                <div class="loading" id="loading_migrate">⏳ Migrowanie...</div>
                <div class="result" id="result_migrate"></div>
            </div>
        </div>

        <!-- Tools Tab -->
        <div id="tools" class="tab-content">
            <h2>🔧 Dostępne narzędzia MCP</h2>

            <div style="margin-bottom: 20px;">
                <button onclick="ToolsManager.loadAvailableTools()" style="margin-right: 10px;">🔄 Odśwież listę</button>
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

    <script src="/assets/js/dashboard.js"></script>
</body>
</html>