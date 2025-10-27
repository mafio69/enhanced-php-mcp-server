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

                    // Generate default arguments for common tools
                    let defaultArgs = {};
                    switch (tool.name) {
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

                    toolElement.innerHTML = `
                        <div class="tool-name">${tool.name}</div>
                        <div class="tool-desc">${tool.description || 'No description available'}</div>
                        <div style="margin-top: 15px;">
                            <button data-tool="${tool.name}"
                                    class="tool-run-btn">
                                ⚡ Uruchom
                            </button>
                        </div>
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

// Execute a tool
window.runTool = async function(toolName, argsJson) {
    const resultDiv = document.getElementById(`result-${toolName}`);
    const button = event.target;

    // Parse arguments
    let args = {};
    try {
        if (argsJson) {
            args = JSON.parse(argsJson);
        }
    } catch (e) {
        showNotification(`❌ Invalid arguments for ${toolName}`, 'error');
        return;
    }

    // Update button state
    const originalText = button.textContent;
    button.textContent = '⏳ Uruchamianie...';
    button.disabled = true;

    // Show result area
    resultDiv.style.display = 'block';
    resultDiv.innerHTML = '<div class="loading-result">⏳ Wykonywanie narzędzia...</div>';

    try {
        const response = await fetch('/api/tools/call', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                tool: toolName,
                arguments: args
            })
        });

        const data = await response.json();

        if (response.ok && data.success) {
            resultDiv.innerHTML = `
                <div class="result-success">
                    <strong>✅ Wynik:</strong><br>
                    <pre>${escapeHtml(data.data)}</pre>
                </div>
            `;
            showNotification(`✅ ${toolName} executed successfully`, 'success');
        } else {
            const errorMsg = data.error?.message || data.details?.details || data.message || 'Unknown error';
            resultDiv.innerHTML = `
                <div class="result-error">
                    <strong>❌ Błąd:</strong><br>
                    <span>${escapeHtml(errorMsg)}</span>
                </div>
            `;
            showNotification(`❌ ${toolName} failed`, 'error');
        }
    } catch (error) {
        resultDiv.innerHTML = `
            <div class="result-error">
                <strong>❌ Błąd sieci:</strong><br>
                <span>${escapeHtml(error.message)}</span>
            </div>
        `;
        showNotification(`❌ Network error executing ${toolName}`, 'error');
    } finally {
        button.textContent = originalText;
        button.disabled = false;
    }
}

// Escape HTML to prevent XSS
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
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

    // Add event delegation for tool run buttons
    document.addEventListener('click', function(event) {
        if (event.target.classList.contains('tool-run-btn')) {
            const toolName = event.target.dataset.tool;
            openToolModal(toolName);
        }
    });
});

// Modal system
function openToolModal(toolName) {
    const modal = createToolModal(toolName);
    document.body.appendChild(modal);

    // Focus first input
    const firstInput = modal.querySelector('input, textarea, select');
    if (firstInput) {
        setTimeout(() => firstInput.focus(), 100);
    }
}

function createToolModal(toolName) {
    const overlay = document.createElement('div');
    overlay.className = 'modal-overlay';
    overlay.onclick = function(e) {
        if (e.target === overlay) {
            closeModal(overlay);
        }
    };

    const modal = document.createElement('div');
    modal.className = 'modal';

    modal.innerHTML = `
        <div class="modal-header">
            <h2 class="modal-title">🔧 ${toolName}</h2>
            <button class="modal-close" onclick="closeModal(this.closest('.modal-overlay'))">×</button>
        </div>
        <div class="modal-body">
            ${generateToolForm(toolName)}
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal(this.closest('.modal-overlay'))">
                Anuluj
            </button>
            <button type="button" class="btn btn-primary" onclick="executeToolFromModal('${toolName}', this)">
                ▶️ Uruchom narzędzie
            </button>
        </div>
    `;

    overlay.appendChild(modal);
    return overlay;
}

function generateToolForm(toolName) {
    switch (toolName) {
        case 'hello':
            return `
                <div class="form-group">
                    <label class="form-label" for="hello-name">Imię do powitania:</label>
                    <input type="text" id="hello-name" class="form-input" placeholder="Świat" value="Świat">
                    <div class="form-help">Wpisz imię lub nazwę, którą chcesz przywitać</div>
                </div>
            `;

        case 'get_time':
            return `
                <div class="form-group">
                    <label class="form-label" for="time-format">Format daty PHP (opcjonalne):</label>
                    <input type="text" id="time-format" class="form-input" placeholder="Y-m-d H:i:s" value="Y-m-d H:i:s">
                    <div class="form-help">Format PHP daty i czasu, np. Y-m-d H:i:s</div>
                </div>
                <div class="form-group">
                    <label class="form-label" for="time-timezone">Strefa czasowa (opcjonalne):</label>
                    <select id="time-timezone" class="form-select">
                        <option value="">Automatyczna</option>
                        <option value="Europe/Warsaw">Europe/Warsaw</option>
                        <option value="UTC">UTC</option>
                        <option value="America/New_York">America/New_York</option>
                        <option value="Asia/Tokyo">Asia/Tokyo</option>
                    </select>
                </div>
            `;

        case 'calculate':
            return `
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="calc-operation">Operacja:</label>
                        <select id="calc-operation" class="form-select" required>
                            <option value="add">Dodawanie (+)</option>
                            <option value="subtract">Odejmowanie (-)</option>
                            <option value="multiply">Mnożenie (×)</option>
                            <option value="divide">Dzielenie (÷)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="calc-a">Liczba A:</label>
                        <input type="number" id="calc-a" class="form-input" value="5" required>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label" for="calc-b">Liczba B:</label>
                    <input type="number" id="calc-b" class="form-input" value="3" required>
                </div>
            `;

        case 'list_files':
            return `
                <div class="form-group">
                    <label class="form-label" for="files-path">Ścieżka do katalogu:</label>
                    <input type="text" id="files-path" class="form-input" placeholder="." value=".">
                    <div class="form-help">Ścieżka do katalogu, np. . (bieżący), ./src, /var/www</div>
                </div>
            `;

        case 'read_file':
            return `
                <div class="form-group">
                    <label class="form-label" for="read-path">Ścieżka do pliku:</label>
                    <input type="text" id="read-path" class="form-input" placeholder="./config/server.php" required>
                    <div class="form-help">Wprowadź pełną ścieżkę do pliku</div>
                </div>
            `;

        case 'write_file':
            return `
                <div class="form-group">
                    <label class="form-label" for="write-path">Ścieżka do pliku:</label>
                    <input type="text" id="write-path" class="form-input" placeholder="./test.txt" required>
                    <div class="form-help">Ścieżka, gdzie plik zostanie zapisany</div>
                </div>
                <div class="form-group">
                    <label class="form-label" for="write-content">Zawartość pliku:</label>
                    <textarea id="write-content" class="form-textarea" rows="6" placeholder="Wpisz zawartość pliku..." required>Hello World!</textarea>
                    <div class="form-help">Wpisz treść, która zostanie zapisana w pliku</div>
                </div>
            `;

        case 'json_parse':
            return `
                <div class="form-group">
                    <label class="form-label" for="json-input">JSON do sparsowania:</label>
                    <textarea id="json-input" class="form-textarea" rows="4" placeholder='{"name": "value"}' required>{"test": "value", "number": 123}</textarea>
                    <div class="form-help">Wklej lub wpisz tekst JSON do przetworzenia</div>
                </div>
            `;

        case 'get_weather':
            return `
                <div class="form-group">
                    <label class="form-label" for="weather-city">Miasto:</label>
                    <input type="text" id="weather-city" class="form-input" placeholder="Warszawa" value="Warszawa" required>
                    <div class="form-help">Wpisz nazwę miasta dla prognozy pogody</div>
                </div>
            `;

        case 'brave_search':
            return `
                <div class="form-group">
                    <label class="form-label" for="brave-query">Fraza wyszukiwania:</label>
                    <input type="text" id="brave-query" class="form-input" placeholder="Wpisz szukaną frazę..." value="MCP PHP Server" required>
                    <div class="form-help">Co chcesz znaleźć w internecie?</div>
                </div>
                <div class="form-group">
                    <label class="form-label" for="brave-count">Liczba wyników (1-20):</label>
                    <input type="number" id="brave-count" class="form-input" min="1" max="20" value="5" required>
                    <div class="form-help">Ile wyników wyszukiwania wyświetlić?</div>
                </div>
                <div class="form-group">
                    <label class="form-label" for="brave-api-key">Klucz API Brave (opcjonalne):</label>
                    <input type="password" id="brave-api-key" class="form-input" placeholder="Wklej klucz API Brave...">
                    <div class="form-help">Jeśli nie podasz klucza API, użyta zostanie symulacja</div>
                </div>
            `;

        case 'system_info':
            return `
                <div style="text-align: center; padding: 20px; color: #666;">
                    <p style="font-size: 1.1rem; margin-bottom: 15px;">
                        🔍 To narzędzie nie wymaga żadnych parametrów
                    </p>
                    <p>Kliknij "Uruchom narzędzie" aby wyświetlić szczegółowe informacje o systemie.</p>
                </div>
            `;

        case 'playwright':
            return `
                <div class="form-group">
                    <label class="form-label" for="playwright-action">Akcja:</label>
                    <select id="playwright-action" class="form-select" required>
                        <option value="info">ℹ️ Informacje o Playwright</option>
                        <option value="check_installation">🔧 Sprawdź instalację</option>
                        <option value="start_browser">🚀 Uruchom przeglądarkę</option>
                        <option value="navigate">🌐 Nawiguj do strony</option>
                        <option value="get_content">📥 Pobierz treść strony</option>
                        <option value="find_element">🔍 Znajdź element</option>
                        <option value="click_element">🖱️ Kliknij element</option>
                        <option value="type_text">⌨️ Wpisz tekst</option>
                        <option value="take_screenshot">📸 Zrób zrzut ekranu</option>
                    </select>
                </div>

                <!-- Fields for URL-based actions -->
                <div id="playwright-url-fields" style="display: none;">
                    <div class="form-group">
                        <label class="form-label" for="playwright-url">URL strony:</label>
                        <input type="url" id="playwright-url" class="form-input" placeholder="https://example.com">
                        <div class="form-help">Adres URL strony do przetworzenia</div>
                    </div>
                </div>

                <!-- Fields for element-based actions -->
                <div id="playwright-element-fields" style="display: none;">
                    <div class="form-group">
                        <label class="form-label" for="playwright-selector">CSS Selector:</label>
                        <input type="text" id="playwright-selector" class="form-input" placeholder="#my-id, .my-class, button">
                        <div class="form-help">CSS selector elementu (np. #id, .class, button[type='submit'])</div>
                    </div>
                </div>

                <!-- Fields for text input -->
                <div id="playwright-text-fields" style="display: none;">
                    <div class="form-group">
                        <label class="form-label" for="playwright-text">Tekst do wpisania:</label>
                        <input type="text" id="playwright-text" class="form-input" placeholder="Wpisz tekst...">
                        <div class="form-help">Tekst, który zostanie wpisany w pole formularza</div>
                    </div>
                </div>

                <!-- Additional options -->
                <div id="playwright-options-fields" style="display: none;">
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="playwright-wait">Czas oczekiwania (ms):</label>
                            <input type="number" id="playwright-wait" class="form-input" min="1000" max="30000" value="5000">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="playwright-screenshot">Zrób zrzut ekranu:</label>
                            <select id="playwright-screenshot" class="form-select">
                                <option value="false">Nie</option>
                                <option value="true">Tak</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="playwright-storage">Stan sesji (opcjonalne):</label>
                        <input type="text" id="playwright-storage" class="form-input" placeholder="/path/to/storage.json">
                        <div class="form-help">Ścieżka do pliku stanu sesji Playwright</div>
                    </div>
                </div>

                <script>
                    // Show/hide fields based on selected action
                    document.getElementById('playwright-action').addEventListener('change', function() {
                        const action = this.value;
                        const urlFields = document.getElementById('playwright-url-fields');
                        const elementFields = document.getElementById('playwright-element-fields');
                        const textFields = document.getElementById('playwright-text-fields');
                        const optionsFields = document.getElementById('playwright-options-fields');

                        // Hide all fields first
                        urlFields.style.display = 'none';
                        elementFields.style.display = 'none';
                        textFields.style.display = 'none';
                        optionsFields.style.display = 'none';

                        // Show relevant fields based on action
                        if (['navigate', 'get_content', 'find_element', 'click_element', 'type_text', 'take_screenshot'].includes(action)) {
                            urlFields.style.display = 'block';
                            optionsFields.style.display = 'block';
                        }

                        if (['find_element', 'click_element', 'type_text'].includes(action)) {
                            elementFields.style.display = 'block';
                        }

                        if (['type_text'].includes(action)) {
                            textFields.style.display = 'block';
                        }

                        if (['start_browser'].includes(action)) {
                            optionsFields.style.display = 'block';
                        }
                    });
                </script>
            `;

        case 'http_request':
            return `
                <div class="form-group">
                    <label class="form-label" for="http-url">URL:</label>
                    <input type="url" id="http-url" class="form-input" placeholder="https://api.example.com/data" required>
                    <div class="form-help">Adres URL do wywołania</div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="http-method">Metoda HTTP:</label>
                        <select id="http-method" class="form-select">
                            <option value="GET">GET</option>
                            <option value="POST">POST</option>
                            <option value="PUT">PUT</option>
                            <option value="DELETE">DELETE</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="http-headers">Nagłówki (JSON):</label>
                        <textarea id="http-headers" class="form-textarea" rows="3" placeholder='{"Authorization": "Bearer token"}'></textarea>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label" for="http-body">Treść zapytania (dla POST/PUT):</label>
                    <textarea id="http-body" class="form-textarea" rows="4" placeholder='{"key": "value"}'></textarea>
                    <div class="form-help">Treść zapytania w formacie JSON</div>
                </div>
            `;

        default:
            return `
                <div style="text-align: center; padding: 20px; color: #666;">
                    <p>⚠️ To narzędzie nie ma jeszcze zdefiniowanego formularza</p>
                    <p>Kliknij "Uruchom narzędzie" aby użyć domyślnych parametrów.</p>
                </div>
            `;
    }
}

function closeModal(overlay) {
    if (overlay && overlay.parentNode) {
        overlay.remove();
    }
}

// Execute tool from modal
async function executeToolFromModal(toolName, button) {
    const modal = button.closest('.modal');
    const modalBody = modal.querySelector('.modal-body');

    // Collect form data
    const args = collectFormData(toolName, modal);

    // Validate required fields
    if (!validateFormData(toolName, args, modalBody)) {
        return;
    }

    // Update button state
    const originalText = button.innerHTML;
    button.innerHTML = '⏳ Uruchamianie...';
    button.disabled = true;

    // Remove previous results
    const prevResult = modal.querySelector('.modal-result');
    if (prevResult) prevResult.remove();

    // Add loading result
    const resultDiv = document.createElement('div');
    resultDiv.className = 'modal-result loading';
    resultDiv.innerHTML = '⏳ Wykonywanie narzędzia...';
    modalBody.appendChild(resultDiv);

    try {
        const response = await fetch('/api/tools/call', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                tool: toolName,
                arguments: args
            })
        });

        const data = await response.json();

        if (response.ok && data.success) {
            resultDiv.className = 'modal-result success';
            resultDiv.innerHTML = `
                <strong>✅ Wynik:</strong>
                <pre>${escapeHtml(data.data)}</pre>
            `;
            showNotification(`✅ ${toolName} executed successfully`, 'success');
        } else {
            const errorMsg = data.error?.message || data.details?.details || data.message || 'Unknown error';
            resultDiv.className = 'modal-result error';
            resultDiv.innerHTML = `
                <strong>❌ Błąd:</strong>
                <span>${escapeHtml(errorMsg)}</span>
            `;
            showNotification(`❌ ${toolName} failed`, 'error');
        }
    } catch (error) {
        resultDiv.className = 'modal-result error';
        resultDiv.innerHTML = `
            <strong>❌ Błąd sieci:</strong>
            <span>${escapeHtml(error.message)}</span>
        `;
        showNotification(`❌ Network error executing ${toolName}`, 'error');
    } finally {
        button.innerHTML = originalText;
        button.disabled = false;
    }
}

// Collect form data based on tool type
function collectFormData(toolName, modal) {
    const args = {};

    switch (toolName) {
        case 'hello':
            const name = modal.querySelector('#hello-name').value.trim();
            if (name) args.name = name;
            break;

        case 'get_time':
            const format = modal.querySelector('#time-format').value.trim();
            const timezone = modal.querySelector('#time-timezone').value.trim();
            if (format) args.format = format;
            if (timezone) args.timezone = timezone;
            break;

        case 'calculate':
            args.operation = modal.querySelector('#calc-operation').value;
            args.a = parseFloat(modal.querySelector('#calc-a').value);
            args.b = parseFloat(modal.querySelector('#calc-b').value);
            break;

        case 'list_files':
            const path = modal.querySelector('#files-path').value.trim();
            if (path) args.path = path;
            break;

        case 'read_file':
            args.path = modal.querySelector('#read-path').value.trim();
            break;

        case 'write_file':
            args.path = modal.querySelector('#write-path').value.trim();
            args.content = modal.querySelector('#write-content').value;
            break;

        case 'json_parse':
            args.json = modal.querySelector('#json-input').value.trim();
            break;

        case 'get_weather':
            args.city = modal.querySelector('#weather-city').value.trim();
            break;

        case 'brave_search':
            args.query = modal.querySelector('#brave-query').value.trim();
            args.count = parseInt(modal.querySelector('#brave-count').value);
            const apiKey = modal.querySelector('#brave-api-key').value.trim();
            if (apiKey) {
                // Temporarily set the API key in environment
                window.tempBraveApiKey = apiKey;
            }
            break;

        case 'system_info':
            // No parameters needed
            break;

        case 'playwright':
            args.action = modal.querySelector('#playwright-action').value;

            const url = modal.querySelector('#playwright-url').value.trim();
            if (url) args.url = url;

            const selector = modal.querySelector('#playwright-selector').value.trim();
            if (selector) args.selector = selector;

            const text = modal.querySelector('#playwright-text').value.trim();
            if (text) args.text = text;

            const waitFor = parseInt(modal.querySelector('#playwright-wait').value);
            if (!isNaN(waitFor)) args.waitFor = waitFor;

            const screenshot = modal.querySelector('#playwright-screenshot').value === 'true';
            args.screenshot = screenshot;

            const storageState = modal.querySelector('#playwright-storage').value.trim();
            if (storageState) args.storageState = storageState;
            break;

        case 'http_request':
            args.url = modal.querySelector('#http-url').value.trim();
            args.method = modal.querySelector('#http-method').value;

            const headersText = modal.querySelector('#http-headers').value.trim();
            if (headersText) {
                try {
                    args.headers = JSON.parse(headersText);
                } catch (e) {
                    args.headers = {};
                }
            }

            const bodyText = modal.querySelector('#http-body').value.trim();
            if (bodyText) {
                args.body = bodyText;
            }
            break;
    }

    return args;
}

// Validate form data
function validateFormData(toolName, args, modalBody) {
    switch (toolName) {
        case 'read_file':
        case 'write_file':
            if (!args.path) {
                showNotification('❌ Ścieżka do pliku jest wymagana', 'error');
                return false;
            }
            break;

        case 'get_weather':
            if (!args.city) {
                showNotification('❌ Nazwa miasta jest wymagana', 'error');
                return false;
            }
            break;

        case 'brave_search':
            if (!args.query) {
                showNotification('❌ Fraza wyszukiwania jest wymagana', 'error');
                return false;
            }
            if (args.count < 1 || args.count > 20) {
                showNotification('❌ Liczba wyników musi być między 1 a 20', 'error');
                return false;
            }
            break;

        case 'http_request':
            if (!args.url) {
                showNotification('❌ URL jest wymagany', 'error');
                return false;
            }
            break;

        case 'calculate':
            if (isNaN(args.a) || isNaN(args.b)) {
                showNotification('❌ Wprowadź poprawne liczby', 'error');
                return false;
            }
            if (args.operation === 'divide' && args.b === 0) {
                showNotification('❌ Dzielenie przez zero jest niedozwolone', 'error');
                return false;
            }
            break;

        case 'playwright':
            if (!args.action) {
                showNotification('❌ Akcja jest wymagana', 'error');
                return false;
            }

            // URL validation for actions that need it
            const urlRequiredActions = ['navigate', 'get_content', 'find_element', 'click_element', 'type_text', 'take_screenshot'];
            if (urlRequiredActions.includes(args.action) && !args.url) {
                showNotification('❌ URL jest wymagany dla tej akcji', 'error');
                return false;
            }

            // Selector validation for actions that need it
            const selectorRequiredActions = ['find_element', 'click_element', 'type_text'];
            if (selectorRequiredActions.includes(args.action) && !args.selector) {
                showNotification('❌ Selector jest wymagany dla tej akcji', 'error');
                return false;
            }

            // Text validation for actions that need it
            if (args.action === 'type_text' && !args.text) {
                showNotification('❌ Tekst jest wymagany dla akcji type_text', 'error');
                return false;
            }

            // Wait time validation
            if (args.waitFor && (args.waitFor < 1000 || args.waitFor > 30000)) {
                showNotification('❌ Czas oczekiwania musi być między 1000 a 30000 ms', 'error');
                return false;
            }
            break;
    }

    return true;
}

// Separate function for tool execution
async function executeTool(toolName, argsJson, button) {
    const resultDiv = document.getElementById(`result-${toolName}`);

    // Parse arguments
    let args = {};
    try {
        if (argsJson) {
            args = JSON.parse(argsJson);
        }
    } catch (e) {
        showNotification(`❌ Invalid arguments for ${toolName}`, 'error');
        return;
    }

    // Update button state
    const originalText = button.textContent;
    button.textContent = '⏳ Uruchamianie...';
    button.disabled = true;

    // Show result area
    resultDiv.style.display = 'block';
    resultDiv.innerHTML = '<div class="loading-result">⏳ Wykonywanie narzędzia...</div>';

    try {
        const response = await fetch('/api/tools/call', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                tool: toolName,
                arguments: args
            })
        });

        const data = await response.json();

        if (response.ok && data.success) {
            resultDiv.innerHTML = `
                <div class="result-success">
                    <strong>✅ Wynik:</strong><br>
                    <pre>${escapeHtml(data.data)}</pre>
                </div>
            `;
            showNotification(`✅ ${toolName} executed successfully`, 'success');
        } else {
            const errorMsg = data.error?.message || data.details?.details || data.message || 'Unknown error';
            resultDiv.innerHTML = `
                <div class="result-error">
                    <strong>❌ Błąd:</strong><br>
                    <span>${escapeHtml(errorMsg)}</span>
                </div>
            `;
            showNotification(`❌ ${toolName} failed`, 'error');
        }
    } catch (error) {
        resultDiv.innerHTML = `
            <div class="result-error">
                <strong>❌ Błąd sieci:</strong><br>
                <span>${escapeHtml(error.message)}</span>
            </div>
        `;
        showNotification(`❌ Network error executing ${toolName}`, 'error');
    } finally {
        button.textContent = originalText;
        button.disabled = false;
    }
}