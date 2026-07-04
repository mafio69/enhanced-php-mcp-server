#!/bin/bash

# Pre-deployment verification script for MCP PHP Server
# Run this script before deploying to production

set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

ERRORS=0
WARNINGS=0

log_pass() {
    echo -e "${GREEN}[PASS]${NC} $1"
}

log_fail() {
    echo -e "${RED}[FAIL]${NC} $1"
    ERRORS=$((ERRORS + 1))
}

log_warn() {
    echo -e "${YELLOW}[WARN]${NC} $1"
    WARNINGS=$((WARNINGS + 1))
}

log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

echo -e "${BLUE}╔══════════════════════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║           MCP PHP Server - Pre-deployment Check             ║${NC}"
echo -e "${BLUE}╚══════════════════════════════════════════════════════════════╝${NC}"
echo ""

# 1. Check PHP version
log_info "Checking PHP version..."
PHP_VERSION=$(php -r 'echo PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION;')
PHP_REQUIRED="8.1"

if [ "$(printf '%s\n' "$PHP_REQUIRED" "$PHP_VERSION" | sort -V | head -n1)" = "$PHP_REQUIRED" ]; then
    log_pass "PHP version: $PHP_VERSION (required: >= $PHP_REQUIRED)"
else
    log_fail "PHP version: $PHP_VERSION (required: >= $PHP_REQUIRED)"
fi

# 2. Check required PHP extensions
log_info "Checking PHP extensions..."

if php -m | grep -q "curl"; then
    log_pass "cURL extension is loaded"
else
    log_fail "cURL extension is NOT loaded (required for http_request tool)"
fi

if php -m | grep -q "json"; then
    log_pass "JSON extension is loaded"
else
    log_fail "JSON extension is NOT loaded"
fi

if php -m | grep -q "mbstring"; then
    log_pass "mbstring extension is loaded"
else
    log_warn "mbstring extension is NOT loaded (recommended)"
fi

# 3. Check environment variables
log_info "Checking environment variables..."

check_env_var() {
    local var_name=$1
    local required=$2
    
    if [ -f ".env" ]; then
        local value=$(grep "^${var_name}=" .env 2>/dev/null | cut -d'=' -f2-)
        if [ -n "$value" ]; then
            log_pass "Environment variable $var_name is set"
            return 0
        fi
    fi
    
    local env_value=$(printenv "$var_name" 2>/dev/null)
    if [ -n "$env_value" ]; then
        log_pass "Environment variable $var_name is set (from system env)"
        return 0
    fi
    
    if [ "$required" = "required" ]; then
        log_fail "Environment variable $var_name is NOT set (REQUIRED)"
    else
        log_warn "Environment variable $var_name is NOT set (optional)"
    fi
    return 1
}

check_env_var "MCP_SECRET_KEY" "required"
check_env_var "ADMIN_PASSWORD" "required"
check_env_var "ADMIN_USERNAME" "optional"
check_env_var "BRAVE_API_KEY" "optional"
check_env_var "GIT_ACCES_TOKEN" "optional"

# 4. Check directory permissions
log_info "Checking directory permissions..."

check_writable_dir() {
    local dir=$1
    if [ ! -d "$dir" ]; then
        log_warn "Directory $dir does not exist (will be created on first run)"
        return
    fi
    
    if [ -w "$dir" ]; then
        log_pass "Directory $dir is writable"
    else
        log_fail "Directory $dir is NOT writable"
    fi
}

check_writable_dir "logs"
check_writable_dir "storage"

# 5. Check debug mode
log_info "Checking debug mode..."

if grep -q "'debug' => true" config/server.php 2>/dev/null; then
    log_fail "Debug mode is ENABLED in config/server.php (should be false for production)"
else
    log_pass "Debug mode is disabled"
fi

# 6. Check if composer dependencies are installed
log_info "Checking composer dependencies..."

if [ -d "vendor" ]; then
    log_pass "Vendor directory exists"
else
    log_fail "Vendor directory does NOT exist. Run: composer install"
fi

# 7. Run tests
log_info "Running PHPUnit tests..."

if composer test --quiet 2>/dev/null; then
    log_pass "All tests passed"
else
    log_fail "Some tests failed"
fi

# Summary
echo ""
echo -e "${BLUE}══════════════════════════════════════════════════════════════${NC}"
echo -e "${BLUE}                    SUMMARY                                  ${NC}"
echo -e "${BLUE}══════════════════════════════════════════════════════════════${NC}"
echo -e "Errors:   ${RED}$ERRORS${NC}"
echo -e "Warnings: ${YELLOW}$WARNINGS${NC}"
echo ""

if [ $ERRORS -gt 0 ]; then
    echo -e "${RED}Pre-deployment check FAILED. Fix errors before deploying.${NC}"
    exit 1
elif [ $WARNINGS -gt 0 ]; then
    echo -e "${YELLOW}Pre-deployment check PASSED with warnings.${NC}"
    exit 0
else
    echo -e "${GREEN}Pre-deployment check PASSED. Ready to deploy!${NC}"
    exit 0
fi
