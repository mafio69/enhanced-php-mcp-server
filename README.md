# Enhanced PHP MCP Server with Web Dashboard

Advanced PHP MCP Server with comprehensive toolset and web interface. This server communicates via standard input/output using JSON-based protocol and provides a rich set of remotely callable tools with a modern web dashboard for management and testing.

## 🚀 Features

- **9 Built-in Tools**: Hello, Time, Calculator, File Operations, System Info, JSON Parsing, Weather
- **Web Dashboard**: Modern web interface for tool management, testing, and monitoring
- **Security**: Path restrictions, file size limits, input validation
- **Configuration**: Centralized config system with environment support
- **Logging**: Comprehensive logging with configurable levels
- **CLI & HTTP**: Dual mode operation support
- **Modern PHP**: Requires PHP 8.1+ with proper autoloading

## 📋 Requirements

- PHP >= 8.1
- Composer
- Required extensions: `json`, `curl`

## 🛠️ Installation

1. Clone or download the repository
2. Install dependencies:
   ```bash
   composer install
   ```
3. Make the start script executable:
   ```bash
   chmod +x start
   chmod +x web_dashboard
   ```

## 🎯 Quick Start

### 🚀 Szybki start (zalecane)
```bash
# 1. Instalacja zależności
./start.sh 6

# 2. Uruchom serwer web (interfejs przeglądarkowy)
./start.sh 2
```
Otwórz http://localhost:8888 w przeglądarce aby uzyskać dostęp do API.

### 🎮 Interaktywne menu
```bash
./start.sh
```
Pokaże kolorowe menu z opcjami do wyboru.

### 🌐 Web API (dla przeglądarki)
```bash
./start.sh 2      # Uruchom serwer web na http://localhost:8888
```

### 📡 CLI Mode (dla MCP klientów)
```bash
./start.sh 1      # Uruchom serwer MCP przez stdin/stdout
```

### 🔍 Zarządzanie serwerem
```bash
./start.sh 4      # Sprawdź status
./start.sh 5      # Zobacz logi
```

### 🖥️ CLI Mode (Interactive Menu)
```bash
./start.sh
```

Jeśli terminal nie jest interaktywny, zostanie wyświetlone menu z opcjami:
```bash
./start.sh        # Pokaż menu z opcjami
./start.sh 1      # CLI Mode - serwer MCP przez stdin/stdout
./start.sh 2      # Web Mode - serwer HTTP z Slim Framework
./start.sh 3      # All Modes - CLI + Web jednocześnie
./start.sh 4      # Status - sprawdź status serwera
./start.sh 5      # Logs - pokaż ostatnie logi
./start.sh 6      # Install - zainstaluj zależności
```

### Przykłady użycia CLI
```bash
# Uruchom serwer w trybie interaktywnym (pokaże menu)
./start.sh

# Uruchom bezpośrednio w trybie CLI (dla MCP klientów)
./start.sh 1

# Uruchom serwer web (dla przeglądarki)
./start.sh 2

# Sprawdź status działających serwerów
./start.sh 4

# Zobacz ostatnie logi
./start.sh 5
```

### Manual Start
```bash
php index.php
```

### Composer Script
```bash
composer start
```

## 📚 Available Tools

The server provides 10 powerful tools:

### 1. **hello**
Greets a person by name.
```bash
echo '{"jsonrpc":"2.0","id":1,"method":"tools/call","params":{"name":"hello","arguments":{"name":"John"}}}' | php index.php
```

### 2. **get_time**
Returns current date and time information.
```bash
echo '{"jsonrpc":"2.0","id":1,"method":"tools/call","params":{"name":"get_time"}}' | php index.php
```

### 3. **calculate**
Performs mathematical operations (add, subtract, multiply, divide).
```bash
echo '{"jsonrpc":"2.0","id":1,"method":"tools/call","params":{"name":"calculate","arguments":{"operation":"add","a":10,"b":5}}}' | php index.php
```

### 4. **read_file**
Safely reads file contents within project directory.
```bash
echo '{"jsonrpc":"2.0","id":1,"method":"tools/call","params":{"name":"read_file","arguments":{"path":"README.md"}}}' | php index.php
```

### 5. **write_file**
Safely writes content to files within project directory.
```bash
echo '{"jsonrpc":"2.0","id":1,"method":"tools/call","params":{"name":"write_file","arguments":{"path":"test.txt","content":"Hello World"}}}' | php index.php
```

### 6. **list_files**
Lists files and directories in a specified path.
```bash
echo '{"jsonrpc":"2.0","id":1,"method":"tools/call","params":{"name":"list_files","arguments":{"path":"."}}}' | php index.php
```

### 7. **system_info**
Returns comprehensive system and PHP information.
```bash
echo '{"jsonrpc":"2.0","id":1,"method":"tools/call","params":{"name":"system_info"}}' | php index.php
```

### 8. **http_request**
Makes HTTP requests to external APIs.
```bash
echo '{"jsonrpc":"2.0","id":1,"method":"tools/call","params":{"name":"http_request","arguments":{"url":"https://api.github.com","method":"GET"}}}' | php index.php
```

### 9. **json_parse**
Parses and formats JSON strings.
```bash
echo '{"jsonrpc":"2.0","id":1,"method":"tools/call","params":{"name":"json_parse","arguments":{"json":"{\"test\": true}"}}}' | php index.php
```

### 10. **get_weather**
Fetches weather information for a specified city.
```bash
echo '{"jsonrpc":"2.0","id":1,"method":"tools/call","params":{"name":"get_weather","arguments":{"city":"London"}}}' | php index.php
```

## 🌐 Web API (HTTP Mode)

Gdy serwer jest uruchomiony w trybie Web (`./start.sh 2`), dostępne są następujące endpointy:

### Podstawowe endpointy
- `GET /` - Informacje o serwerze i dostępne endpointy
- `GET /api/tools` - Lista dostępnych narzędzi
- `GET /api/status` - Status serwera i metryki
- `GET /api/logs` - Ostatnie logi
- `GET /api/metrics` - System metrics

### Wykonywanie narzędzi
- `POST /api/tools/call` - Wykonaj narzędzie

**Przykład wywołania narzędzia:**
```bash
# Powitanie
curl -X POST http://localhost:8888/api/tools/call \
  -H "Content-Type: application/json" \
  -d '{"tool": "hello", "arguments": {"name": "Jan"}}'

# Obliczenia
curl -X POST http://localhost:8888/api/tools/call \
  -H "Content-Type: application/json" \
  -d '{"tool": "calculate", "arguments": {"operation": "add", "a": 10, "b": 5}}'

# Lista plików
curl -X POST http://localhost:8888/api/tools/call \
  -H "Content-Type: application/json" \
  -d '{"tool": "list_files", "arguments": {"path": "src"}}'
```

## 🔧 Configuration

Server configuration is managed through `config/server.php`:

```php
return [
    'server' => [
        'name' => 'enhanced-php-mcp-server',
        'version' => '2.0.0',
    ],
    'logging' => [
        'enabled' => true,
        'file' => __DIR__ . '/../logs/server.log',
        'level' => 'info',
    ],
    'security' => [
        'allowed_paths' => [__DIR__ . '/..'],
        'max_file_size' => 10 * 1024 * 1024, // 10MB
    ],
    // ... more configuration
];
```

## 📁 Project Structure

```
mcp-php-server/
├── config/           # Configuration files
├── src/             # Source code
├── tools/           # Tool implementations (future)
├── logs/            # Log files
├── storage/         # Temporary storage
├── vendor/          # Composer dependencies
├── index.php        # Main entry point
├── server.php       # Complete server implementation
├── start            # Start script
├── composer.json    # Dependencies and autoloading
└── README.md        # This file
```

## 🔒 Security Features

- **Path Restrictions**: File operations limited to allowed directories
- **File Size Limits**: Configurable maximum file size for operations
- **Input Validation**: All inputs are validated and sanitized
- **Error Handling**: Comprehensive error handling without exposing sensitive information

## 📝 Development

### Code Style
```bash
composer cs-check    # Check code style
composer cs-fix      # Fix code style issues
```

### Testing
```bash
composer test        # Run tests
```

### Dependencies
```bash
composer install     # Install dependencies
composer update      # Update dependencies
```

## 📝 Logging

Logs are automatically created in `logs/server.log` with timestamps and severity levels:

```
[2024-01-01 12:00:00] INFO Server started
[2024-01-01 12:00:01] INFO Tool 'hello' executed
```

## 🌟 Examples

### Initialize Server
```bash
echo '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{}}' | php index.php
```

### List All Tools
```bash
echo '{"jsonrpc":"2.0","id":1,"method":"tools/list","params":{}}' | php index.php
```

### Chain Operations
```bash
# Write a file, then read it back
echo '{"jsonrpc":"2.0","id":1,"method":"tools/call","params":{"name":"write_file","arguments":{"path":"demo.txt","content":"Hello from MCP!"}}}' | php index.php

echo '{"jsonrpc":"2.0","id":2,"method":"tools/call","params":{"name":"read_file","arguments":{"path":"demo.txt"}}}' | php index.php
```

## 🚨 Troubleshooting

### Common Issues

1. **Permission Denied**: Ensure the `start` script is executable (`chmod +x start`)
2. **Dependencies Missing**: Run `composer install`
3. **Extensions Missing**: Install required PHP extensions (`php-json`, `php-curl`)
4. **File Access**: Check file permissions in project directory

### Debug Mode

Enable detailed logging by setting log level to `debug` in `config/server.php`.

## 📄 License

MIT License - see LICENSE file for details.

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests if applicable
5. Submit a pull request

## 📞 Support

For issues and questions:
- Check the logs in `logs/server.log`
- Review the configuration in `config/server.php`
- Ensure all requirements are met
