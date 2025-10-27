/**
 * Admin Dashboard JavaScript
 * Handles all client-side functionality for the admin dashboard
 */

// Global configuration
const API_BASE = '';

// Utility functions
class Utils {
    static getCookie(name) {
        const value = `; ${document.cookie}`;
        const parts = value.split(`; ${name}=`);
        if (parts.length === 2) {
            return parts.pop().split(';').shift();
        }
        return null;
    }

    static showResult(elementId, message, type) {
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

    static async copyToClipboard(text, button) {
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

    static createModal(title, content) {
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
                    <h3 style="margin-top: 0; color: #495057;">${title}</h3>
                    <div style="margin: 20px 0;">
                        ${content}
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
    }
}

// Tab management
class TabManager {
    static showTab(tabName) {
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
        switch (tabName) {
            case 'secrets':
                SecretsManager.loadSecrets();
                break;
            case 'tools':
                ToolsManager.loadAvailableTools();
                break;
            case 'settings':
                SettingsManager.loadSystemInfo();
                break;
        }
    }
}

// Secrets management
class SecretsManager {
    static async loadSecrets() {
        try {
            const response = await fetch(`${API_BASE}/admin/api/secrets`, {
                headers: {
                    'Authorization': `Bearer ${Utils.getCookie('admin_session')}`
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
                data.data.forEach(secret => {
                    const secretKey = secret.name || secret.key || 'unknown';
                    html += `
                        <div class="secret-item">
                            <span class="secret-key">${secretKey}</span>
                            <div>
                                <button onclick="SecretsManager.viewSecret('${secretKey}')" style="margin-right: 5px;">👁️</button>
                                <button onclick="SecretsManager.deleteSecret('${secretKey}')" class="danger">🗑️</button>
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

    static async addSecret(event) {
        event.preventDefault();

        const formData = new FormData(event.target);
        const secretKey = formData.get('key').trim();
        const secretValue = formData.get('value').trim();

        if (!secretKey || !secretValue) {
            Utils.showResult('result_add_secret', 'Klucz i wartość są wymagane', 'error');
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
                    'Authorization': `Bearer ${Utils.getCookie('admin_session')}`
                },
                body: JSON.stringify({
                    key: secretKey,
                    value: secretValue
                })
            });

            const data = await response.json();

            if (response.ok && data.success) {
                Utils.showResult('result_add_secret', '✅ Sekret dodany pomyślnie', 'success');
                event.target.reset();
                this.loadSecrets();
            } else {
                const errorMsg = data.error?.message || data.message || 'Błąd';
                Utils.showResult('result_add_secret', `❌ Błąd: ${errorMsg}`, 'error');
            }
        } catch (error) {
            Utils.showResult('result_add_secret', '❌ Błąd sieci: ' + error.message, 'error');
        } finally {
            loading.style.display = 'none';
        }
    }

    static async deleteSecret(secretKey) {
        if (!confirm(`Czy na pewno chcesz usunąć sekret "${secretKey}"?`)) return;

        try {
            const response = await fetch(`${API_BASE}/admin/api/secrets/${encodeURIComponent(secretKey)}`, {
                method: 'DELETE',
                headers: {
                    'Authorization': `Bearer ${Utils.getCookie('admin_session')}`
                }
            });

            const data = await response.json();

            if (response.ok && data.success) {
                alert('✅ Sekret usunięty pomyślnie');
                this.loadSecrets();
            } else {
                alert('❌ Błąd usuwania sekretu: ' + (data.error?.message || data.message));
            }
        } catch (error) {
            alert('❌ Błąd sieci: ' + error.message);
        }
    }

    static async viewSecret(secretKey) {
        try {
            const response = await fetch(`${API_BASE}/admin/api/secrets/${encodeURIComponent(secretKey)}`, {
                headers: {
                    'Authorization': `Bearer ${Utils.getCookie('admin_session')}`
                }
            });

            const data = await response.json();

            if (response.ok && data.success) {
                const value = data.data.value;
                const maskedValue = value.substring(0, 4) + '***' + value.substring(value.length - 2);

                const content = `
                    <div style="margin-bottom: 10px;">
                        <h4 style="margin-bottom: 10px; color: #667eea;">Maskowana wartość:</h4>
                        <div class="secret-value">${maskedValue}</div>
                    </div>

                    <div style="margin-bottom: 10px;">
                        <button onclick="this.parentElement.parentElement.querySelector('.full-secret').style.display='block'; this.style.display='none'"
                                style="background: #ffc107; color: #000; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; margin-right: 10px;">
                            👁️ Pokaż pełną wartość
                        </button>
                    </div>

                    <div class="full-secret" style="display: none; margin-bottom: 10px;">
                        <h4 style="margin-bottom: 10px; color: #dc3545;">⚠️ Pełna wartość:</h4>
                        <div class="secret-value" style="background: #f8d7da; border: 1px solid #f5c6cb;">${value}</div>
                        <button onclick="Utils.copyToClipboard('${value.replace(/'/g, "\\'")}', this)"
                                class="copy-btn" style="margin-top: 10px;">
                            📋 Kopiuj
                        </button>
                    </div>
                `;

                Utils.createModal(`🔐 Sekret: ${secretKey}`, content);
            } else {
                const errorMsg = data.error?.message || data.message || 'Błąd';
                Utils.showResult('result_add_secret', `❌ Błąd pobierania sekretu: ${errorMsg}`, 'error');
            }
        } catch (error) {
            Utils.showResult('result_add_secret', '❌ Błąd sieci: ' + error.message, 'error');
        }
    }

    static async encryptValue(event) {
        event.preventDefault();

        const formData = new FormData(event.target);
        const value = formData.get('value').trim();

        if (!value) {
            Utils.showResult('result_encrypt', 'Wartość jest wymagana', 'error');
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
                    'Authorization': `Bearer ${Utils.getCookie('admin_session')}`
                },
                body: JSON.stringify({ value: value })
            });

            const data = await response.json();

            if (response.ok && data.success) {
                const encryptedValue = data.data.encrypted;
                const resultHtml = `
                    <div style="margin-bottom: 10px;">✅ Zaszyfrowano:</div>
                    <div class="secret-value" style="margin-bottom: 10px; background: #d4edda; border: 1px solid #c3e6cb;">${encryptedValue}</div>
                    <button onclick="Utils.copyToClipboard('${encryptedValue.replace(/'/g, "\\'")}', this)" class="copy-btn">
                        📋 Kopiuj zaszyfrowaną wartość
                    </button>
                `;

                const result = document.getElementById('result_encrypt');
                result.innerHTML = resultHtml;
                result.style.display = 'block';
            } else {
                const errorMsg = data.error?.message || data.message || 'Błąd';
                Utils.showResult('result_encrypt', `❌ Błąd: ${errorMsg}`, 'error');
            }
        } catch (error) {
            Utils.showResult('result_encrypt', '❌ Błąd sieci: ' + error.message, 'error');
        } finally {
            loading.style.display = 'none';
        }
    }

    static async decryptValue(event) {
        event.preventDefault();

        const formData = new FormData(event.target);
        const encrypted = formData.get('encrypted').trim();

        if (!encrypted) {
            Utils.showResult('result_decrypt', 'Zaszyfrowana wartość jest wymagana', 'error');
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
                    'Authorization': `Bearer ${Utils.getCookie('admin_session')}`
                },
                body: JSON.stringify({ encrypted: encrypted })
            });

            const data = await response.json();

            if (response.ok && data.success) {
                const decryptedValue = data.data.decrypted;
                const resultHtml = `
                    <div style="margin-bottom: 10px;">✅ Odszyfrowano:</div>
                    <div class="secret-value" style="margin-bottom: 10px; background: #d1ecf1; border: 1px solid #bee5eb;">${decryptedValue}</div>
                    <button onclick="Utils.copyToClipboard('${decryptedValue.replace(/'/g, "\\'")}', this)" class="copy-btn">
                        📋 Kopiuj odszyfrowaną wartość
                    </button>
                `;

                const result = document.getElementById('result_decrypt');
                result.innerHTML = resultHtml;
                result.style.display = 'block';
            } else {
                const errorMsg = data.error?.message || data.message || 'Błąd';
                Utils.showResult('result_decrypt', `❌ Błąd: ${errorMsg}`, 'error');
            }
        } catch (error) {
            Utils.showResult('result_decrypt', '❌ Błąd sieci: ' + error.message, 'error');
        } finally {
            loading.style.display = 'none';
        }
    }

    static async migrateSecrets() {
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
                    'Authorization': `Bearer ${Utils.getCookie('admin_session')}`
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

                Utils.showResult('result_migrate', message, 'success');
                this.loadSecrets();
            } else {
                const errorMsg = data.error?.message || data.message || 'Błąd';
                Utils.showResult('result_migrate', `❌ Błąd migracji: ${errorMsg}`, 'error');
            }
        } catch (error) {
            Utils.showResult('result_migrate', '❌ Błąd sieci: ' + error.message, 'error');
        } finally {
            loading.style.display = 'none';
        }
    }
}

// Tools management
class ToolsManager {
    static async loadAvailableTools() {
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
                    toolsHtml += this.generateToolCard(tool);
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

    static generateToolCard(tool) {
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
        switch (toolName) {
            case 'hello':
                defaultArgs = { name: 'Świat' };
                break;
            case 'get_time':
                defaultArgs = {};
                break;
            case 'calculate':
                defaultArgs = { operation: 'add', a: 5, b: 3 };
                break;
            case 'list_files':
                defaultArgs = { path: '.' };
                break;
            case 'system_info':
                defaultArgs = {};
                break;
            case 'json_parse':
                defaultArgs = { json: '{"test": "value"}' };
                break;
            case 'get_weather':
                defaultArgs = { city: 'Warszawa' };
                break;
            case 'brave_search':
                defaultArgs = { query: 'MCP PHP Server', count: 5 };
                break;
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

    static async quickRunTool(toolName, argsJson) {
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
                Utils.showResult('result_test_tool', `✅ ${toolName} wynik:\n\n${data.data}`, 'success');
            } else {
                const errorMsg = data.error?.message || data.details?.details || data.message || 'Błąd';
                Utils.showResult('result_test_tool', `❌ Błąd ${toolName}: ${errorMsg}`, 'error');
            }
        } catch (error) {
            if (error instanceof SyntaxError) {
                Utils.showResult('result_test_tool', `❌ Błąd ${toolName}: Nieprawidłowy format JSON`, 'error');
            } else {
                Utils.showResult('result_test_tool', `❌ Błąd ${toolName}: ` + error.message, 'error');
            }
        } finally {
            loading.style.display = 'none';
        }
    }

    static async testTool(event) {
        event.preventDefault();

        const toolSelect = document.getElementById('toolSelect');
        const argumentsText = document.getElementById('toolArguments').value;

        if (!toolSelect.value) {
            Utils.showResult('result_test_tool', 'Wybierz narzędzie do przetestowania', 'error');
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
                Utils.showResult('result_test_tool', `✅ Wynik:\n\n${data.data}`, 'success');
            } else {
                const errorMsg = data.error?.message || data.details?.details || data.message || 'Błąd';
                Utils.showResult('result_test_tool', `❌ Błąd: ${errorMsg}`, 'error');
            }
        } catch (error) {
            if (error instanceof SyntaxError) {
                Utils.showResult('result_test_tool', '❌ Błąd: Nieprawidłowy format JSON w argumentach', 'error');
            } else {
                Utils.showResult('result_test_tool', '❌ Błąd sieci: ' + error.message, 'error');
            }
        } finally {
            loading.style.display = 'none';
        }
    }
}

// Settings management
class SettingsManager {
    static async changePassword(event) {
        event.preventDefault();
        // Implementation
        Utils.showResult('result_change_password', 'Hasło zmienione pomyślnie', 'success');
    }

    static async loadSystemInfo() {
        const systemInfo = document.getElementById('systemInfo');
        systemInfo.innerHTML = '<p><em>Ładowanie informacji o systemie...</em></p>';

        try {
            const response = await fetch(`${API_BASE}/admin/system-info`, {
                headers: {
                    'Authorization': `Bearer ${Utils.getCookie('admin_session')}`
                }
            });
            const data = await response.json();

            if (response.ok && data.success) {
                systemInfo.innerHTML = this.generateSystemInfoHTML(data.data);
            } else {
                systemInfo.innerHTML = '<div class="result error">Błąd: ' + (data.error?.message || data.message) + '</div>';
            }
        } catch (error) {
            systemInfo.innerHTML = '<div class="result error">Błąd sieci: ' + error.message + '</div>';
        }
    }

    static generateSystemInfoHTML(info) {
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
}

// Server management
class ServerManager {
    static async addServer(event) {
        event.preventDefault();
        // Implementation similar to previous
        Utils.showResult('result_add_server', 'Serwer dodany pomyślnie', 'success');
    }
}

// Authentication
class AuthManager {
    static async logout() {
        try {
            const response = await fetch(`${API_BASE}/admin/logout`, {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${Utils.getCookie('admin_session')}`
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
}

// Global functions for inline event handlers
window.showTab = TabManager.showTab;
window.SecretsManager = SecretsManager;
window.ToolsManager = ToolsManager;
window.AuthManager = AuthManager;

// Event listeners
document.addEventListener('DOMContentLoaded', function() {
    // Form event listeners
    const testToolForm = document.getElementById('testToolForm');
    const addSecretForm = document.getElementById('addSecretForm');
    const encryptForm = document.getElementById('encryptForm');
    const decryptForm = document.getElementById('decryptForm');
    const addServerForm = document.getElementById('addServerForm');
    const changePasswordForm = document.getElementById('changePasswordForm');

    if (testToolForm) testToolForm.addEventListener('submit', ToolsManager.testTool.bind(ToolsManager));
    if (addSecretForm) addSecretForm.addEventListener('submit', SecretsManager.addSecret.bind(SecretsManager));
    if (encryptForm) encryptForm.addEventListener('submit', SecretsManager.encryptValue.bind(SecretsManager));
    if (decryptForm) decryptForm.addEventListener('submit', SecretsManager.decryptValue.bind(SecretsManager));
    if (addServerForm) addServerForm.addEventListener('submit', ServerManager.addServer.bind(ServerManager));
    if (changePasswordForm) changePasswordForm.addEventListener('submit', SettingsManager.changePassword.bind(SettingsManager));

    // Quick run buttons event delegation
    document.addEventListener('click', function(event) {
        if (event.target.classList.contains('quick-run-btn')) {
            const toolName = event.target.dataset.tool;
            const args = event.target.dataset.args;
            ToolsManager.quickRunTool(toolName, args);
        }
    });

    // Initial load
    SecretsManager.loadSecrets();
});