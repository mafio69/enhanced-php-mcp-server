#!/bin/bash

# Colors for display
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

# Function to display header
show_header() {
    echo -e "${CYAN}╔══════════════════════════════════════════════════════════════╗${NC}"
    echo -e "${CYAN}║                    MCP PHP SERVER v2.1.0                     ║${NC}"
    echo -e "${CYAN}║              Advanced PHP Server with Slim Framework           ║${NC}"
    echo -e "${CYAN}╚══════════════════════════════════════════════════════════════╝${NC}"
    echo ""
}

# Function to display menu
show_menu() {
    echo -e "${YELLOW}Choose startup mode:${NC}"
    echo ""
    echo -e "${GREEN}  1)${NC} CLI Mode     - MCP Server via command line"
    echo -e "${GREEN}  2)${NC} Web Mode     - HTTP API with Slim Framework"
    echo -e "${GREEN}  3)${NC} All Modes     - CLI + Web simultaneously"
    echo ""
    echo -e "${GREEN}  4)${NC} Status       - Check server status"
    echo -e "${GREEN}  5)${NC} Logs         - Show recent logs"
    echo -e "${GREEN}  6)${NC} Install      - Install dependencies"
    echo ""
    echo -e "${RED}  0)${NC} Exit         - Exit"
    echo ""
    echo -ne "${CYAN}Your choice [1-6,0]: ${NC}"
}

# Function to check dependencies
check_dependencies() {
    echo -e "${BLUE}🔍 Checking dependencies...${NC}"

    # Check PHP
    if ! command -v php &> /dev/null; then
        echo -e "${RED}❌ PHP is not installed!${NC}"
        return 1
    fi

    PHP_VERSION=$(php -r "echo PHP_VERSION;")
    echo -e "${GREEN}✅ PHP $PHP_VERSION${NC}"

    # Check Composer
    if ! command -v composer &> /dev/null; then
        echo -e "${YELLOW}⚠️  Composer is not installed${NC}"
        echo -e "${YELLOW}   You can download it from: https://getcomposer.org/${NC}"
    else
        COMPOSER_VERSION=$(composer --version | cut -d' ' -f3)
        echo -e "${GREEN}✅ Composer $COMPOSER_VERSION${NC}"
    fi

    # Check vendor directory
    if [ ! -d "vendor" ]; then
        echo -e "${YELLOW}⚠️  Missing vendor directory - run 'Install'${NC}"
        return 1
    fi

    # Check logs directory
    if [ ! -d "logs" ]; then
        echo -e "${YELLOW}📁 Creating logs directory...${NC}"
        mkdir -p logs
    fi

    echo -e "${GREEN}✅ Dependencies are OK${NC}"
    return 0
}

# Function to install dependencies
install_dependencies() {
    echo -e "${BLUE}📦 Installing dependencies...${NC}"

    if ! command -v composer &> /dev/null; then
        echo -e "${RED}❌ Composer is not available!${NC}"
        echo -e "${YELLOW}Install Composer: https://getcomposer.org/download/${NC}"
        return 1
    fi

    echo -e "${YELLOW}Running 'composer install'...${NC}"
    if composer install; then
        echo -e "${GREEN}✅ Dependencies installed successfully!${NC}"

        # Create necessary directories
        mkdir -p logs storage

        echo -e "${GREEN}✅ Directory structure created${NC}"
    else
        echo -e "${RED}❌ Error during dependency installation!${NC}"
        return 1
    fi
}

# Function to start CLI mode
start_cli() {
    echo -e "${BLUE}🚀 Starting MCP Server in CLI mode...${NC}"
    echo -e "${YELLOW}Server listening on STDIN/STDOUT${NC}"
    echo -e "${YELLOW}Ctrl+C to stop${NC}"
    echo ""

    if check_dependencies; then
        php public/index.php
    else
        echo -e "${RED}❌ Cannot start server - check dependencies${NC}"
    fi
}

# Function to start Web mode
start_web() {
    echo -e "${BLUE}🌐 Starting MCP Server in Web mode...${NC}"

    if check_dependencies; then
        # Check if port 8888 is free
        if lsof -i :8888 &>/dev/null; then
            echo -e "${YELLOW}⚠️  Port 8888 is already occupied${NC}"
            echo -ne "${CYAN}Enter different port [e.g. 8889]: ${NC}"
            read -r CUSTOM_PORT
            PORT=${CUSTOM_PORT:-8889}
        else
            PORT=8888
        fi

        echo -e "${GREEN}🌍 Server available at: http://localhost:$PORT${NC}"
        echo -e "${GREEN}📊 API Dashboard: http://localhost:$PORT/api/status${NC}"
        echo -e "${YELLOW}Ctrl+C to stop${NC}"
        echo ""

        # Start PHP server in background and show logs
        php -S localhost:"$PORT" public/index.php
    else
        echo -e "${RED}❌ Cannot start server - check dependencies${NC}"
    fi
}

# Function to start both modes
start_all() {
    echo -e "${PURPLE}🔄 Starting MCP Server in both modes...${NC}"
    echo ""

    if ! check_dependencies; then
        echo -e "${RED}❌ Cannot start server - check dependencies${NC}"
        return 1
    fi

    # Start Web server in background
    echo -e "${BLUE}🌐 Starting Web server...${NC}"
    PORT=8888
    if lsof -i :$PORT &>/dev/null; then
        echo -e "${YELLOW}⚠️  Port $PORT occupied, using 8889${NC}"
        PORT=8889
    fi

    # Start web server in background
    php -S localhost:"$PORT" public/index.php > logs/web_server.log 2>&1 &
    WEB_PID=$!

    echo -e "${GREEN}✅ Web server started (PID: $WEB_PID) at http://localhost:$PORT${NC}"
    echo ""

    # Wait a moment for web server to start
    sleep 2

    # Check if web server is running
    if kill -0 $WEB_PID 2>/dev/null; then
        echo -e "${GREEN}✅ Web server is running correctly${NC}"
    else
        echo -e "${RED}❌ Web server did not start correctly${NC}"
        kill $WEB_PID 2>/dev/null
        return 1
    fi

    echo ""
    echo -e "${PURPLE}📋 Running services:${NC}"
    echo -e "${GREEN}  🌐 Web Server: http://localhost:$PORT (PID: $WEB_PID)${NC}"
    echo -e "${GREEN}  💻 CLI Server: will be started below${NC}"
    echo ""
    echo -e "${YELLOW}To stop both servers, use Ctrl+C${NC}"
    echo ""

    # Function for cleanup on exit
    cleanup() {
        echo ""
        echo -e "${YELLOW}🛑 Stopping servers...${NC}"
        if kill -0 $WEB_PID 2>/dev/null; then
            kill $WEB_PID
            echo -e "${GREEN}✅ Web server stopped${NC}"
        fi
        exit 0
    }

    # Catch Ctrl+C
    trap cleanup SIGINT SIGTERM

    # Start CLI server in foreground
    start_cli
}

# Function to check status
check_status() {
    echo -e "${BLUE}📊 Checking MCP Server status...${NC}"
    echo ""

    # Check processes
    if pgrep -f "php public/index.php" > /dev/null; then
        echo -e "${GREEN}✅ CLI Server is running${NC}"
        CLI_PIDS=$(pgrep -f "php public/index.php")
        echo -e "${CYAN}   PIDs: $CLI_PIDS${NC}"
    else
        echo -e "${RED}❌ CLI Server is not running${NC}"
    fi

    if pgrep -f "php -S" > /dev/null; then
        echo -e "${GREEN}✅ Web Server is running${NC}"
        WEB_PIDS=$(pgrep -f "php -S")
        echo -e "${CYAN}   PIDs: $WEB_PIDS${NC}"

        # Check ports
        echo -e "${BLUE}🔍 Active ports:${NC}"
        netstat -tlnp 2>/dev/null | grep ":888[0-9]" | while read line; do
            echo -e "${CYAN}   $line${NC}"
        done
    else
        echo -e "${RED}❌ Web Server is not running${NC}"
    fi

    echo ""
    echo -e "${BLUE}📁 File status:${NC}"

    # Check logs
    if [ -f "logs/server.log" ]; then
        LOG_SIZE=$(du -h logs/server.log | cut -f1)
        echo -e "${GREEN}✅ Logs available ($LOG_SIZE)${NC}"
    else
        echo -e "${YELLOW}⚠️  No log file${NC}"
    fi

    # Check vendor
    if [ -d "vendor" ]; then
        VENDOR_SIZE=$(du -sh vendor | cut -f1)
        echo -e "${GREEN}✅ Vendor directory ($VENDOR_SIZE)${NC}"
    else
        echo -e "${RED}❌ Missing vendor directory${NC}"
    fi

    echo ""
}

# Function to show logs
show_logs() {
    echo -e "${BLUE}📄 Recent MCP Server logs...${NC}"
    echo ""

    if [ -f "logs/server.log" ]; then
        echo -e "${YELLOW}Last 20 lines from logs/server.log:${NC}"
        echo -e "${CYAN}$(tail -20 logs/server.log)${NC}"
        echo ""

        echo -ne "${CYAN}Show more lines? [y/N]: ${NC}"
        read -r MORE
        if [[ $MORE =~ ^[Yy]$ ]]; then
            echo -e "${YELLOW}Last 100 lines:${NC}"
            tail -100 logs/server.log
        fi
    else
        echo -e "${RED}❌ Log file does not exist: logs/server.log${NC}"

        # Check other log files
        if ls logs/*.log 1> /dev/null 2>&1; then
            echo -e "${YELLOW}Available log files:${NC}"
            ls -la logs/
        fi
    fi
}

# Function to start with argument
start_with_mode() {
    local mode="$1"
    # Remove dashes from beginning of argument
    mode=$(echo "$mode" | sed 's/^-*//')

    case $mode in
        1|cli)
            clear
            show_header
            start_cli
            ;;
        2|web)
            clear
            show_header
            start_web
            ;;
        3|all)
            clear
            show_header
            start_all
            ;;
        4|status)
            clear
            show_header
            check_status
            ;;
        5|logs)
            clear
            show_header
            show_logs
            ;;
        6|install)
            clear
            show_header
            install_dependencies
            ;;
        *)
            echo -e "${RED}❌ Invalid choice: '$1'${NC}"
            echo -e "${YELLOW}Usage: $0 [1|2|3|4|5|6|cli|web|all|status|logs|install]${NC}"
            echo ""
            show_menu
            return 1
            ;;
    esac
}

# Main menu function (only if run without arguments)
main_menu() {
    # Check if terminal is interactive (better detection for WSL)
    if ! [[ -t 0 && -t 1 ]]; then
        show_header
        show_menu
        echo ""
        echo -e "${YELLOW}Choose option via argument:${NC}"
        echo "  $0 1     - CLI Mode"
        echo "  $0 2     - Web Mode"
        echo "  $0 3     - All Modes"
        echo "  $0 4     - Status"
        echo "  $0 5     - Logs"
        echo "  $0 6     - Install"
        echo ""
        echo -e "${GREEN}Example: ${CYAN}./start.sh 2${NC} (will start Web Mode)"
        echo ""
        return 0
    fi

    # Show interactive menu
    show_header
    show_menu

    while true; do
        read -r choice
        case $choice in
            1|cli)
                start_cli
                break
                ;;
            2|web)
                start_web
                break
                ;;
            3|all)
                start_all
                break
                ;;
            4|status)
                check_status
                break
                ;;
            5|logs)
                show_logs
                break
                ;;
            6|install)
                install_dependencies
                break
                ;;
            0|exit|quit)
                echo -e "${GREEN}👋 Goodbye!${NC}"
                exit 0
                ;;
            *)
                echo -e "${RED}❌ Invalid choice. Try again [1-6,0]:${NC}"
                ;;
        esac
    done
}

# Check if script is run directly or via source
if [[ "${BASH_SOURCE[0]}" == "${0}" ]]; then
    # Direct execution
    if [[ $# -gt 0 ]]; then
        start_with_mode "$1"
    else
        main_menu
    fi
elif [[ "${BASH_SOURCE[0]}" == "start.sh" && -n "$PS1" ]]; then
    # Execution via source (.) in interactive terminal
    if [[ $# -gt 0 ]]; then
        start_with_mode "$1"
    else
        main_menu
    fi
fi