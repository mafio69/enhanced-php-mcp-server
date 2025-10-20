# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is **MCP PHP Server v2.1.0** - an advanced PHP implementation of a Model Context Protocol (MCP) server that operates in both CLI and HTTP modes. The server uses modern PHP architecture with Slim Framework, PHP-DI container, and comprehensive tooling for development tasks.

## Common Development Commands

### Server Management
```bash
# Interactive menu (recommended for development)
./start.sh

# Direct mode startup
./start.sh 1      # CLI mode (MCP protocol via stdin/stdout)
./start.sh 2      # HTTP mode (REST API on port 8888)
./start.sh 3      # Both modes simultaneously
./start.sh 4      # Check server status
./start.sh 5      # View recent logs
./start.sh 6      # Install dependencies

# Manual startup
php index.php                    # Auto-detects CLI vs HTTP
composer start                  # Same as php index.php
```

### Code Quality
```bash
composer cs-check    # Check code style with PHP CS Fixer
composer cs-fix      # Auto-fix code style issues
composer test        # Run PHPUnit tests
```

### Dependencies
```bash
composer install     # Install all dependencies
composer update      # Update dependencies
```

### Testing
```bash
./test.sh           # Basic smoke test and validation
composer test       # Full PHPUnit test suite
```

## Architecture Overview

### Dual-Mode Operation
The server automatically detects execution context:
- **CLI Mode** (`php_sapi_name() === 'cli'`): MCP JSON-RPC protocol over stdin/stdout
- **HTTP Mode**: REST API with Slim Framework on port 8888

### Core Components

#### 1. **Dependency Injection Container** (`src/AppContainer.php`)
- PHP-DI based container with autowiring
- Singleton pattern for performance
- Factory methods for complex services (Logger, HTTP Client, Slim App)
- Environment-aware configuration loading

#### 2. **Server Implementations**
- `MCPServer.php`: CLI mode with ReactPHP event loop for non-blocking I/O
- `MCPServerHTTP.php`: HTTP mode with Slim Framework REST endpoints
- Both share the same tool registry but with different transport layers

#### 3. **Tool System**
The server provides 10 built-in tools accessible in both modes:
- `hello` - Greeting utility
- `get_time` - Current date/time information
- `calculate` - Mathematical operations (add, subtract, multiply, divide)
- `read_file` / `write_file` - File operations within project directory
- `list_files` - Directory listing
- `system_info` - System and PHP diagnostics
- `http_request` - HTTP client for external API calls
- `json_parse` - JSON parsing and formatting
- `get_weather` - Weather information (simulated)

#### 4. **Configuration** (`config/server.php`)
Centralized configuration with sections for:
- Server metadata (name, version, description)
- Logging configuration (file, level, rotation)
- Security settings (allowed paths, file size limits)
- HTTP client settings (timeout, user agent)
- Tool registry (enabled/restricted tools)

#### 5. **Monitoring** (`src/MonitoringService.php`)
Comprehensive metrics collection:
- Tool execution metrics (success/failure rates, timing)
- System metrics (memory usage, uptime)
- HTTP request metrics (response times, status codes)
- Performance aggregation with counters and gauges

### Key Architecture Patterns

#### **PSR Standards Compliance**
- PSR-3 (Logger) - Monolog with custom wrapper
- PSR-11 (Container) - PHP-DI implementation
- PSR-7 (HTTP Messages) - Slim PSR-7 implementation

#### **Security Model**
- Path restrictions: File operations limited to `__DIR__ . '/..'` and `__DIR__ . '/../storage'`
- File size limits: 10MB maximum for file operations
- Input validation: All inputs sanitized and validated
- Error handling: No sensitive information exposure

#### **Logging System**
- Monolog with rotating file handler
- Dual output: File + console (CLI mode only)
- Configurable log levels and file rotation
- Structured logging with context information

## HTTP API Endpoints

When running in HTTP mode (`./start.sh 2`), the server provides these endpoints:

- `GET /` - Server information and endpoint listing
- `GET /api/tools` - List all available tools with schemas
- `GET /api/status` - Server status and performance metrics
- `GET /api/logs` - Recent log entries (last 50 lines)
- `GET /api/metrics` - Detailed system metrics
- `POST /api/tools/call` - Execute a tool with JSON payload

### Example Tool Execution via HTTP
```bash
curl -X POST http://localhost:8888/api/tools/call \
  -H "Content-Type: application/json" \
  -d '{"tool": "calculate", "arguments": {"operation": "add", "a": 10, "b": 5}}'
```

## Development Workflow

### **File Structure**
```
src/
├── AppContainer.php      # DI container builder
├── MCPServer.php         # CLI mode implementation
├── MCPServerHTTP.php     # HTTP mode implementation
├── MonitoringService.php # Performance metrics
└── Logger.php            # PSR-3 logger wrapper

config/
└── server.php            # Central configuration

logs/                     # Application logs (auto-created)
storage/                  # Temporary storage (auto-created)
vendor/                   # Composer dependencies
```

### **Adding New Tools**
1. Implement tool logic in both `MCPServer.php` (CLI) and `MCPServerHTTP.php` (HTTP)
2. Add tool to `config/server.php` in the `tools.enabled` array
3. Update tool schemas in both server classes
4. Add security restrictions if needed in `config/server.php`

### **Configuration Management**
- All configuration centralized in `config/server.php`
- Environment-specific values can be overridden using `.env` files
- Debug mode available via `debug` flag in configuration
- Logging levels: `debug`, `info`, `warning`, `error`

### **Code Style Requirements**
- PSR-12 coding standards
- PHP 8.1+ features used throughout
- PHP CS Fixer configuration in `composer.json`
- English comments and documentation (mixed Polish/English in legacy code)

## Performance and Monitoring

### **Built-in Metrics**
The server automatically tracks:
- Tool execution count and success rates
- Average execution time per tool
- Memory usage and peak consumption
- HTTP request response times
- System uptime and performance counters

### **Log Analysis**
Logs are structured with timestamps and severity levels:
```
[2024-01-01 12:00:00] mcp-server.INFO: Server started
[2024-01-01 12:00:01] mcp-server.INFO: Tool 'hello' executed
```

### **Development Debugging**
- Set `'debug' => true` in `config/server.php` for detailed logging
- Use `./start.sh 5` to view recent logs
- Monitor metrics via `GET /api/metrics` endpoint in HTTP mode

## Important Notes

### **Security Considerations**
- Never expose this server directly to the internet without authentication
- File operations are restricted to project directories for security
- Monitor logs for unauthorized access attempts
- Regular dependency updates recommended (`composer update`)

### **Performance Optimization**
- Use CLI mode for direct MCP client connections (lower overhead)
- HTTP mode provides better debugging and monitoring capabilities
- Consider running both modes simultaneously for development (`./start.sh 3`)
- Monitor memory usage in long-running CLI processes

### **Error Handling**
- Comprehensive error logging throughout the application
- Graceful degradation when optional dependencies are missing
- Clear error messages returned via API responses
- Critical errors logged to system error log as fallback

### **Port Management**
- Default HTTP port: 8888
- Automatic port detection and fallback to 8889 if port is occupied
- Port configuration can be modified in `start.sh` script
- Multiple processes can run simultaneously on different ports