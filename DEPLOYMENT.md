# Deployment Guide - MCP PHP Server

This guide covers the deployment process for the MCP PHP Server in production environments.

## Requirements

### System Requirements
- PHP >= 8.1
- Web server (Apache/Nginx) or Docker
- Composer

### Required PHP Extensions
- `curl` - for HTTP requests
- `json` - for JSON processing
- `mbstring` - recommended for string handling

## Configuration

### Environment Variables

Copy `.env.example` to `.env` and configure:

```bash
cp .env.example .env
```

| Variable | Required | Description |
|----------|----------|-------------|
| `MCP_SECRET_KEY` | **Yes** | Encryption key for SecretManagerService. Generate with: `openssl rand -base64 32` |
| `ADMIN_PASSWORD` | **Yes** | Password for admin panel access |
| `ADMIN_USERNAME` | No | Admin username (default: `admin`) |
| `BRAVE_API_KEY` | No | API key for Brave Search integration |
| `GIT_ACCES_TOKEN` | No | Git access token for private repositories |

### Application Configuration

Edit `config/server.php` for application settings:

- `debug` - Set to `false` for production
- `logging.level` - Recommended: `info` or `warning` for production
- `security.max_file_size` - Maximum file size for file operations (default: 10MB)

## Deployment Steps

### 1. Install Dependencies

```bash
composer install --no-dev --optimize-autoloader
```

### 2. Configure Environment

```bash
cp .env.example .env
# Edit .env with your production values
nano .env
```

### 3. Set Directory Permissions

```bash
# Create required directories
mkdir -p logs storage

# Set permissions (adjust user/group for your web server)
chown -R www-data:www-data logs storage
chmod -R 755 logs storage
```

### 4. Disable Debug Mode

Ensure `debug => false` in `config/server.php`:

```php
'debug' => false,
```

### 5. Run Pre-deployment Check

```bash
./scripts/pre-deploy-check.sh
```

This script verifies:
- PHP version and extensions
- Environment variables
- Directory permissions
- Debug mode status
- Test suite

### 6. Start the Server

#### Option A: CLI Mode (MCP Protocol)
```bash
./start.sh 1
```

#### Option B: HTTP Mode (REST API)
```bash
./start.sh 2
```

#### Option C: Both Modes
```bash
./start.sh 3
```

#### Option D: Docker
```bash
docker-compose up -d --build
```

## Post-deployment Verification

### 1. Check Server Status

```bash
curl http://localhost:8794/api/status
```

### 2. Run Integration Tests

```bash
./tests/run_comprehensive_tests.sh
```

### 3. Verify Admin Panel

Navigate to the admin panel URL and verify:
- Login works with configured credentials
- Dashboard renders correctly
- All tools are accessible

## Security Checklist

- [ ] `MCP_SECRET_KEY` is set with a strong random value
- [ ] `ADMIN_PASSWORD` is changed from default
- [ ] Debug mode is disabled (`debug => false`)
- [ ] Log files are not accessible from web root
- [ ] `storage/` directory is not accessible from web root
- [ ] HTTPS is enabled for HTTP mode
- [ ] Firewall rules restrict access to admin panel

## Troubleshooting

### Issue: "cURL extension is NOT loaded"
**Solution:** Install/enable cURL extension:
```bash
# Ubuntu/Debian
sudo apt-get install php-curl
sudo systemctl restart php-fpm

# CentOS/RHEL
sudo yum install php-curl
sudo systemctl restart php-fpm
```

### Issue: "Directory is NOT writable"
**Solution:** Fix permissions:
```bash
chown -R www-data:www-data logs storage
chmod -R 755 logs storage
```

### Issue: "Environment variable is NOT set"
**Solution:** Ensure `.env` file exists and contains the required variable, or set it in your web server configuration:

**Apache (.htaccess):**
```apache
SetEnv MCP_SECRET_KEY your-secret-key
```

**Nginx (fastcgi_params):**
```nginx
fastcgi_param MCP_SECRET_KEY "your-secret-key";
```

**PHP-FPM (pool config):**
```ini
env[MCP_SECRET_KEY] = your-secret-key
```

## Monitoring

### Log Files
- Application logs: `logs/server.log`
- Access logs: Web server access logs

### Health Check Endpoint
```bash
curl http://localhost:8794/api/status
```

### Metrics Endpoint
```bash
curl http://localhost:8794/api/metrics
```

## Rollback Procedure

If issues occur after deployment:

1. Stop the server: `./start.sh 0` or `docker-compose down`
2. Restore previous code version
3. Restore previous configuration files
4. Restart the server
5. Verify functionality

## Support

For issues and questions:
- Check logs: `./start.sh 5`
- Run diagnostics: `./scripts/pre-deploy-check.sh`
- Review documentation: `README.md`
