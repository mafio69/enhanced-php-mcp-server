#!/bin/bash

# Kolory do wyświetlania
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

# Funkcja do wyświetlania nagłówka
show_header() {
    echo -e "${CYAN}╔══════════════════════════════════════════════════════════════╗${NC}"
    echo -e "${CYAN}║                    MCP PHP SERVER v2.1.0                     ║${NC}"
    echo -e "${CYAN}║              Advanced PHP Server with Slim Framework           ║${NC}"
    echo -e "${CYAN}╚══════════════════════════════════════════════════════════════╝${NC}"
    echo ""
}

# Funkcja do wyświetlania menu
show_menu() {
    echo -e "${YELLOW}Wybierz tryb uruchomienia:${NC}"
    echo ""
    echo -e "${GREEN}  1)${NC} CLI Mode     - MCP Server przez wiersz poleceń"
    echo -e "${GREEN}  2)${NC} Web Mode     - HTTP API z Slim Framework"
    echo -e "${GREEN}  3)${NC} All Modes     - CLI + Web jednocześnie"
    echo ""
    echo -e "${GREEN}  4)${NC} Status       - Sprawdź status serwera"
    echo -e "${GREEN}  5)${NC} Logs         - Pokaż ostatnie logi"
    echo -e "${GREEN}  6)${NC} Install      - Zainstaluj zależności"
    echo ""
    echo -e "${RED}  0)${NC} Exit         - Wyjście"
    echo ""
    echo -ne "${CYAN}Twój wybór [1-6,0]: ${NC}"
}

# Funkcja do sprawdzania zależności
check_dependencies() {
    echo -e "${BLUE}🔍 Sprawdzanie zależności...${NC}"

    # Sprawdź PHP
    if ! command -v php &> /dev/null; then
        echo -e "${RED}❌ PHP nie jest zainstalowany!${NC}"
        return 1
    fi

    PHP_VERSION=$(php -r "echo PHP_VERSION;")
    echo -e "${GREEN}✅ PHP $PHP_VERSION${NC}"

    # Sprawdź Composer
    if ! command -v composer &> /dev/null; then
        echo -e "${YELLOW}⚠️  Composer nie jest zainstalowany${NC}"
        echo -e "${YELLOW}   Możesz go pobrać z: https://getcomposer.org/${NC}"
    else
        COMPOSER_VERSION=$(composer --version | cut -d' ' -f3)
        echo -e "${GREEN}✅ Composer $COMPOSER_VERSION${NC}"
    fi

    # Sprawdź vendor directory
    if [ ! -d "vendor" ]; then
        echo -e "${YELLOW}⚠️  Brak vendor directory - uruchom 'Install'${NC}"
        return 1
    fi

    # Sprawdź logi directory
    if [ ! -d "logs" ]; then
        echo -e "${YELLOW}📁 Tworzenie logs directory...${NC}"
        mkdir -p logs
    fi

    echo -e "${GREEN}✅ Zależności są OK${NC}"
    return 0
}

# Funkcja do instalacji zależności
install_dependencies() {
    echo -e "${BLUE}📦 Instalacja zależności...${NC}"

    if ! command -v composer &> /dev/null; then
        echo -e "${RED}❌ Composer nie jest dostępny!${NC}"
        echo -e "${YELLOW}Zainstaluj Composer: https://getcomposer.org/download/${NC}"
        return 1
    fi

    echo -e "${YELLOW}Uruchamiam 'composer install'...${NC}"
    if composer install; then
        echo -e "${GREEN}✅ Zależności zainstalowane pomyślnie!${NC}"

        # Utwórz niezbędne katalogi
        mkdir -p logs storage

        echo -e "${GREEN}✅ Struktura katalogów utworzona${NC}"
    else
        echo -e "${RED}❌ Błąd podczas instalacji zależności!${NC}"
        return 1
    fi
}

# Funkcja do uruchamiania CLI mode
start_cli() {
    echo -e "${BLUE}🚀 Uruchamianie MCP Server w CLI mode...${NC}"
    echo -e "${YELLOW}Serwer nasłuchuje na STDIN/STDOUT${NC}"
    echo -e "${YELLOW}Ctrl+C aby zatrzymać${NC}"
    echo ""

    if check_dependencies; then
        php index.php
    else
        echo -e "${RED}❌ Nie można uruchomić serwera - sprawdź zależności${NC}"
    fi
}

# Funkcja do uruchamiania Web mode
start_web() {
    echo -e "${BLUE}🌐 Uruchamianie MCP Server w Web mode...${NC}"

    if check_dependencies; then
        # Sprawdź czy port 8888 jest wolny
        if lsof -i :8888 &>/dev/null; then
            echo -e "${YELLOW}⚠️  Port 8888 jest już zajęty${NC}"
            echo -ne "${CYAN}Podaj inny port [np. 8889]: ${NC}"
            read -r CUSTOM_PORT
            PORT=${CUSTOM_PORT:-8889}
        else
            PORT=8888
        fi

        echo -e "${GREEN}🌍 Serwer dostępny na: http://localhost:$PORT${NC}"
        echo -e "${GREEN}📊 API Dashboard: http://localhost:$PORT/api/status${NC}"
        echo -e "${YELLOW}Ctrl+C aby zatrzymać${NC}"
        echo ""

        # Uruchom serwer PHP w tle i pokaż logi
        php -S localhost:"$PORT" index.php
    else
        echo -e "${RED}❌ Nie można uruchomić serwera - sprawdź zależności${NC}"
    fi
}

# Funkcja do uruchamiania obu trybów
start_all() {
    echo -e "${PURPLE}🔄 Uruchamianie MCP Server w obu trybach...${NC}"
    echo ""

    if ! check_dependencies; then
        echo -e "${RED}❌ Nie można uruchomić serwera - sprawdź zależności${NC}"
        return 1
    fi

    # Uruchom Web server w tle
    echo -e "${BLUE}🌐 Uruchamianie Web server...${NC}"
    PORT=8888
    if lsof -i :$PORT &>/dev/null; then
        echo -e "${YELLOW}⚠️  Port $PORT zajęty, używam 8889${NC}"
        PORT=8889
    fi

    # Uruchom web server w tle
    php -S localhost:"$PORT" index.php > logs/web_server.log 2>&1 &
    WEB_PID=$!

    echo -e "${GREEN}✅ Web server uruchomiony (PID: $WEB_PID) na http://localhost:$PORT${NC}"
    echo ""

    # Poczekaj chwilę na start web servera
    sleep 2

    # Sprawdź czy web server działa
    if kill -0 $WEB_PID 2>/dev/null; then
        echo -e "${GREEN}✅ Web server działa poprawnie${NC}"
    else
        echo -e "${RED}❌ Web server nie wystartował poprawnie${NC}"
        kill $WEB_PID 2>/dev/null
        return 1
    fi

    echo ""
    echo -e "${PURPLE}📋 Uruchamione serwisy:${NC}"
    echo -e "${GREEN}  🌐 Web Server: http://localhost:$PORT (PID: $WEB_PID)${NC}"
    echo -e "${GREEN}  💻 CLI Server: zostanie uruchomiony poniżej${NC}"
    echo ""
    echo -e "${YELLOW}Aby zatrzymać oba serwery, użyj Ctrl+C${NC}"
    echo ""

    # Funkcja do cleanup przy exit
    cleanup() {
        echo ""
        echo -e "${YELLOW}🛑 Zatrzymywanie serwerów...${NC}"
        if kill -0 $WEB_PID 2>/dev/null; then
            kill $WEB_PID
            echo -e "${GREEN}✅ Web server zatrzymany${NC}"
        fi
        exit 0
    }

    # Przechwyć Ctrl+C
    trap cleanup SIGINT SIGTERM

    # Uruchom CLI server na pierwszym planie
    start_cli
}

# Funkcja do sprawdzania statusu
check_status() {
    echo -e "${BLUE}📊 Sprawdzanie statusu MCP Server...${NC}"
    echo ""

    # Sprawdź procesy
    if pgrep -f "php index.php" > /dev/null; then
        echo -e "${GREEN}✅ CLI Server działa${NC}"
        CLI_PIDS=$(pgrep -f "php index.php")
        echo -e "${CYAN}   PIDs: $CLI_PIDS${NC}"
    else
        echo -e "${RED}❌ CLI Server nie działa${NC}"
    fi

    if pgrep -f "php -S" > /dev/null; then
        echo -e "${GREEN}✅ Web Server działa${NC}"
        WEB_PIDS=$(pgrep -f "php -S")
        echo -e "${CYAN}   PIDs: $WEB_PIDS${NC}"

        # Sprawdź porty
        echo -e "${BLUE}🔍 Aktywne porty:${NC}"
        netstat -tlnp 2>/dev/null | grep ":888[0-9]" | while read line; do
            echo -e "${CYAN}   $line${NC}"
        done
    else
        echo -e "${RED}❌ Web Server nie działa${NC}"
    fi

    echo ""
    echo -e "${BLUE}📁 Status plików:${NC}"

    # Sprawdź logi
    if [ -f "logs/server.log" ]; then
        LOG_SIZE=$(du -h logs/server.log | cut -f1)
        echo -e "${GREEN}✅ Logi dostępne ($LOG_SIZE)${NC}"
    else
        echo -e "${YELLOW}⚠️  Brak pliku logów${NC}"
    fi

    # Sprawdź vendor
    if [ -d "vendor" ]; then
        VENDOR_SIZE=$(du -sh vendor | cut -f1)
        echo -e "${GREEN}✅ Vendor directory ($VENDOR_SIZE)${NC}"
    else
        echo -e "${RED}❌ Brak vendor directory${NC}"
    fi

    echo ""
}

# Funkcja do pokazywania logów
show_logs() {
    echo -e "${BLUE}📄 Ostatnie logi MCP Server...${NC}"
    echo ""

    if [ -f "logs/server.log" ]; then
        echo -e "${YELLOW}Ostatnie 20 linii z logs/server.log:${NC}"
        echo -e "${CYAN}$(tail -20 logs/server.log)${NC}"
        echo ""

        echo -ne "${CYAN}Pokaż więcej linii? [t/N]: ${NC}"
        read -r MORE
        if [[ $MORE =~ ^[Tt]$ ]]; then
            echo -e "${YELLOW}Ostatnie 100 linii:${NC}"
            tail -100 logs/server.log
        fi
    else
        echo -e "${RED}❌ Plik logów nie istnieje: logs/server.log${NC}"

        # Sprawdź inne pliki logów
        if ls logs/*.log 1> /dev/null 2>&1; then
            echo -e "${YELLOW}Dostępne pliki logów:${NC}"
            ls -la logs/
        fi
    fi
}

# Główna funkcja menu
main_menu() {
    while true; do
        clear
        show_header
        show_menu
        read -r choice

        case $choice in
            1)
                clear
                show_header
                start_cli
                ;;
            2)
                clear
                show_header
                start_web
                ;;
            3)
                clear
                show_header
                start_all
                ;;
            4)
                clear
                show_header
                check_status
                echo -ne "${CYAN}Naciśnij Enter aby wrócić do menu...${NC}"
                read -r
                ;;
            5)
                clear
                show_header
                show_logs
                echo -ne "${CYAN}Naciśnij Enter aby wrócić do menu...${NC}"
                read -r
                ;;
            6)
                clear
                show_header
                install_dependencies
                echo -ne "${CYAN}Naciśnij Enter aby wrócić do menu...${NC}"
                read -r
                ;;
            0|q|Q)
                echo -e "${GREEN}👋 Do widzenia!${NC}"
                exit 0
                ;;
            *)
                echo -e "${RED}❌ Nieprawidłowy wybór! Wybierz 1-6 lub 0${NC}"
                sleep 2
                ;;
        esac
    done
}

# Sprawdź czy skrypt jest uruchamiany bezpośrednio
if [[ "${BASH_SOURCE[0]}" == "${0}" ]]; then
    main_menu
fi