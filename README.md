# Enhanced PHP MCP Server with Web Dashboard

Advanced PHP MCP Server with comprehensive toolset and web interface. This server communicates via standard input/output using JSON-based protocol and provides a rich set of remotely callable tools with a modern web dashboard for management and testing.

## 🚀 Features

- **10 Built-in Tools**: Hello, Time, Calculator, File Operations, System Info, JSON Parsing, Weather, HTTP Request
- **Admin Panel**: Password-protected management dashboard with system info and secret management
- **Secret Manager**: Encrypted storage (AES-256-CBC) for API keys and credentials
- **MCP Server Management**: Dynamic addition of external MCP servers via API or CLI
- **Security**: Path restrictions, file size limits, input validation
- **Configuration**: Centralized config system with environment support
- **Logging**: Comprehensive logging with configurable levels
- **CLI & HTTP**: Dual mode operation support
- **Modern PHP**: Requires PHP 8.1+ with proper autoloading
- **Comprehensive Testing**: Unit tests, integration tests, performance tests

## 🐳 Suite Integration (Devbox)

This MCP Server is officially integrated as the 4th core component of the [docker-fast-php-logger](https://github.com/mafio69/docker-fast-php-logger) developer suite ecosystem.

When running inside the integrated Docker environment:
- **Web Dashboard:** Automatically accessible at `http://localhost:8000`
- **Container:** Runs automatically as the `mcp-server` Docker service
- **Installation:** Fully managed via Composer globally within the suite (`mafio69/enhanced-php-mcp-server`)

If you are using the Suite, you do **not** need to use `./start.sh`. Simply manage it via the suite script:
```bash
./suite start
# or individually:
./suite mcp:start
```

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
   chmod +x start.sh
   ```

## 🎯 Quick Start

### 🚀 Quick Start (Recommended)
```bash
# 1. Install dependencies
./start.sh 6

# 2. Run web server (browser interface)
./start.sh 2
```
Open http://localhost:8794 in your browser to access the API.

### 🎮 Interactive Menu
```bash
./start.sh
```
This will show a colorful menu with options to choose from.

### 🌐 Web API (for Browser)
```bash
./start.sh 2      # Run web server on http://localhost:8794
```

### 📡 CLI Mode (for MCP Clients)
```bash
./start.sh 1      # Run MCP server via stdin/stdout
```

### 🔍 Server Management
```bash
./start.sh 4      # Check status
./start.sh 5      # View logs
```

### 🖥️ CLI Mode (Interactive Menu)
```bash
./start.sh
```

If terminal is not interactive, it will show menu with options:
```bash
./start.sh        # Show menu with options
./start.sh 1      # CLI Mode - MCP server via stdin/stdout
./start.sh 2      # Web Mode - HTTP server with Slim Framework
./start.sh 3      # All Modes - CLI + Web simultaneously
./start.sh 4      # Status - check server status
./start.sh 5      # Logs - show recent logs
./start.sh 6      # Install - install dependencies
```

### CLI Usage Examples
```bash
# Run server in interactive mode (shows menu)
./start.sh

# Run directly in CLI mode (for MCP clients)
./start.sh 1

# Run web server (for browser)
./start.sh 2

# Check status of running servers
./start.sh 4

# View recent logs
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

When server is running in Web mode (`./start.sh 2`), the following endpoints are available:

### Basic Endpoints
- `GET /` - Server information/landing page
- `GET /api/tools` - List of available tools
- `GET /api/status` - Server status and metrics
- `GET /api/metrics` - System metrics
- `GET /api/health` - Health check endpoint

### Tool Execution
- `POST /api/tools/call` - Execute a tool

### Log Management
- `GET /api/logs` - Recent log entries
- `GET /api/logs/download` - Download logs as file
- `GET /api/logs/stats` - Log statistics
- `DELETE /api/logs` - Clear all logs

**Tool Call Example:**
```bash
# Greeting
curl -X POST http://localhost:8794/api/tools/call \
  -H "Content-Type: application/json" \
  -d '{"tool": "hello", "arguments": {"name": "John"}}'

# Calculation
curl -X POST http://localhost:8794/api/tools/call \
  -H "Content-Type: application/json" \
  -d '{"tool": "calculate", "arguments": {"operation": "add", "a": 10, "b": 5}}'

# File listing
curl -X POST http://localhost:8794/api/tools/call \
  -H "Content-Type: application/json" \
  -d '{"tool": "list_files", "arguments": {"path": "src"}}'
```

## 🔐 Admin Panel

The server includes a password-protected admin panel for management and monitoring.

### Accessing the Panel

```bash
./start.sh 2                     # Start web server
# Open http://localhost:8794/admin/login
```

**Default credentials:** `admin` / `admin123`

> ⚠️ Change the default password immediately after first login. Set custom credentials via `ADMIN_USERNAME` and `ADMIN_PASSWORD` environment variables.

### Auth Methods

The admin panel supports two authentication methods:
- **Cookie-based**: Login form sets an HttpOnly, SameSite=Strict cookie (8h session)
- **Bearer token**: API requests can use `Authorization: Bearer <session_id>` header

### Admin Routes

| Route | Method | Description |
|-------|--------|-------------|
| `/admin/login` | GET | Login page |
| `/admin/login` | POST | Authenticate user |
| `/admin/dashboard` | GET | Admin dashboard (requires auth) |
| `/admin/logout` | POST | End session |
| `/admin/user` | GET | Current user info |
| `/admin/change-password` | POST | Change admin password |
| `/admin/config` | GET | Auth configuration status |
| `/admin/system-info` | GET | Detailed system information |

### System Info

The authenticated `/admin/system-info` endpoint returns comprehensive data:
- **System**: OS, hostname, uptime, platform details
- **PHP**: Version, memory limit, extensions, error reporting
- **Server**: Software, protocol, port, SSL status
- **MCP Server**: Name, version, debug mode, enabled tools
- **Resources**: Memory usage (current/peak), disk space, load average
- **Security**: Session status, open_basedir, disable_functions

## 📋 Environment Variables

| Variable | Default | Description |
|----------|---------|-------------|
| `ADMIN_USERNAME` | `admin` | Admin panel username |
| `ADMIN_PASSWORD` | `admin123` | Admin panel password |
| `MCP_SECRET_KEY` | Auto-generated | AES-256-CBC encryption key for secrets |

## 🔧 Configuration

Server configuration is managed through `config/server.php`:

```php
return [
    'server' => [
        'name' => 'enhanced-php-mcp-server',
        'version' => '2.1.0',
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

## 🔐 Secret Manager

The server includes an encrypted secret storage system for sensitive data like API keys and credentials.

### Overview

- **Encryption**: AES-256-CBC with unique IV per value
- **Storage**: Encrypted files in `storage/secrets/` with restricted permissions (0600)
- **Key Management**: Encryption key from `MCP_SECRET_KEY` env var, auto-generated `storage/.secret_key` file, or newly generated on first use

### Admin API Endpoints

All endpoints require admin authentication (Bearer token or session cookie).

| Method | Route | Description |
|--------|-------|-------------|
| `GET` | `/admin/api/secrets` | List all stored secrets (keys only) |
| `POST` | `/admin/api/secrets` | Store a new secret |
| `GET` | `/admin/api/secrets/{key}` | Retrieve a secret value |
| `DELETE` | `/admin/api/secrets/{key}` | Delete a secret |
| `GET` | `/admin/api/secrets/{key}/check` | Check if a secret exists |
| `POST` | `/admin/api/secrets/encrypt` | Encrypt a value without storing |
| `POST` | `/admin/api/secrets/decrypt` | Decrypt a value |
| `POST` | `/admin/api/secrets/migrate` | Migrate secrets from config |

### Examples

```bash
# Store a secret
curl -X POST http://localhost:8794/admin/api/secrets \
  -H "Content-Type: application/json" \
  -d '{"key": "api.openai.key", "value": "sk-..."}'

# Retrieve a secret
curl http://localhost:8794/admin/api/secrets/api.openai.key

# List all secrets
curl http://localhost:8794/admin/api/secrets

# Encrypt a value
curl -X POST http://localhost:8794/admin/api/secrets/encrypt \
  -H "Content-Type: application/json" \
  -d '{"value": "my-sensitive-data"}'

# Decrypt a value
curl -X POST http://localhost:8794/admin/api/secrets/decrypt \
  -H "Content-Type: application/json" \
  -d '{"encrypted": "base64encrypteddata..."}'
```

### Secret Migration

Run the migration endpoint to automatically detect and migrate secrets from `config/server.php`:

```bash
curl -X POST http://localhost:8794/admin/api/secrets/migrate
```

The migrator detects values matching common patterns (OpenAI `sk-*`, Google `AIza*`, long alphanumeric keys, etc.).

## 🔗 MCP Server Management

The server supports dynamic management of external MCP servers through the admin API and CLI.

### Configuration

External MCP servers can be defined in `config/server.php` under the `mcpServers` key:

```php
'mcpServers' => [
    'Brave-search' => [
        'mcpServers' => [
            'brave-search' => [
                'command' => 'npx',
                'args' => ['-y', '@brave/brave-search-mcp-server', '--transport', 'http'],
                'env' => [
                    'BRAVE_API_KEY' => '${BRAVE_API_KEY}',
                ],
            ],
        ],
    ],
],
```

### Admin API

| Method | Route | Description |
|--------|-------|-------------|
| `POST` | `/admin/api/servers` | Add a new MCP server |

### CLI Command

```bash
php index.php app:add-server <name> <ipAddress> <port>
```

## 📁 Project Structure

```
enhanced-php-mcp-server/
├── config/              # Configuration files
│   └── server.php       # Server configuration
├── src/                 # Source code
│   ├── Commands/        # CLI commands (AddServerCommand)
│   ├── Config/          # ServerConfig class
│   ├── Controllers/     # HTTP controllers (Admin, Logs, Secret, Server, Status, Tools)
│   ├── DTO/             # Data Transfer Objects (AddServer, Error, ServerInfo, Status, Tool)
│   ├── Middleware/      # PSR-15 middleware (AdminAuth)
│   ├── Routing/         # Route definitions (ApiRoutes)
│   ├── Services/        # Business logic (AdminAuth, Monitoring, SecretManager, Server, Tool)
│   ├── Utils/           # Helpers (ApiResponse)
│   ├── AppContainer.php # PHP-DI container builder
│   ├── Logger.php       # PSR-3 logger wrapper
│   ├── MCPServer.php    # CLI mode implementation
│   └── MCPServerHTTP.php # HTTP mode implementation
├── templates/           # HTML templates
│   └── views/
│       ├── admin/       # Admin panel views (dashboard, login)
│       └── loading.php  # Landing page
├── tests/               # Test files (Unit, Integration, Performance)
├── logs/                # Log files
├── storage/             # Session & secrets storage
├── vendor/              # Composer dependencies
├── index.php            # Main entry point
├── start.sh             # Start script
├── composer.json        # Dependencies and autoloading
└── README.md            # This file
```

## 🔒 Security Features

- **Path Restrictions**: File operations limited to allowed directories
- **File Size Limits**: Configurable maximum file size for operations
- **Input Validation**: All inputs are validated and sanitized
- **Error Handling**: Comprehensive error handling without exposing sensitive information
- **Path Traversal Protection**: Prevents access to files outside project directory

## 🧪 Testing

The project includes comprehensive testing suite:

### Quick Test
```bash
# Simple bash tests
./tests/simple_test.sh
```

### PHPUnit Tests
```bash
# Run all unit tests
composer test

# Run specific test
./vendor/bin/phpunit --filter testHelloTool

# Generate coverage report
./vendor/bin/phpunit --coverage-html coverage-html
```

### Integration Tests
```bash
# Full HTTP API tests
./tests/run_comprehensive_tests.sh
```

### Performance Tests
```bash
# Performance testing plan
./tests/PERFORMANCE_TESTS_PLAN.md
```

## 📝 Development

### Code Style
```bash
composer cs-check    # Check code style
composer cs-fix      # Fix code style issues
```

### Testing
```bash
composer test        # Run all tests
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

1. **Permission Denied**: Ensure the `start.sh` script is executable (`chmod +x start.sh`)
2. **Dependencies Missing**: Run `composer install`
3. **Extensions Missing**: Install required PHP extensions (`php-json`, `php-curl`)
4. **File Access**: Check file permissions in project directory
5. **Port Conflicts**: Server auto-detects ports 8794, 8795, 8890

### Debug Mode

Enable detailed logging by setting log level to `debug` in `config/server.php`.

## 📊 Performance Metrics

The server includes built-in monitoring:
- Response time tracking
- Memory usage monitoring
- Tool execution statistics
- System metrics collection

Access via `/api/status` or `/api/metrics` endpoints.

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
- Run the test suite to diagnose issues