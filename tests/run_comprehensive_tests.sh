#!/bin/bash

# Kompleksowy skrypt testowy dla MCP PHP Server
# Autor: Claude Code Assistant
# Wersja: 1.0

# Kolory do wyświetlania
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

# Zmienne globalne
SERVER_PORT=8888
BASE_URL="http://localhost:$SERVER_PORT"
TOTAL_TESTS=0
PASSED_TESTS=0
FAILED_TESTS=0
TEST_RESULTS=()

# Funkcje pomocnicze
show_header() {
    echo -e "${CYAN}╔══════════════════════════════════════════════════════════════╗${NC}"
    echo -e "${CYAN}║           KOMPLEKSOWE TESTY MCP PHP SERVERA                  ║${NC}"
    echo -e "${CYAN}║              Wersja: 1.0 | Auto-detekcja portu                ║${NC}"
    echo -e "${CYAN}╚══════════════════════════════════════════════════════════════╝${NC}"
    echo ""
}

log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[PASS]${NC} $1"
}

log_error() {
    echo -e "${RED}[FAIL]${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

# Funkcja do wykrywania portu serwera
detect_server_port() {
    local ports=(8888 8889 8890)

    log_info "Wykrywanie działającego serwera MCP..."

    for port in "${ports[@]}"; do
        if curl -s --max-time 2 "http://localhost:$port/api/status" > /dev/null 2>&1; then
            SERVER_PORT=$port
            BASE_URL="http://localhost:$SERVER_PORT"
            log_success "Znaleziono serwer na porcie $SERVER_PORT"
            return 0
        fi
    done

    log_error "Nie znaleziono działającego serwera MCP na portach 8888, 8889, 8890"
    log_info "Uruchom serwer za pomocą: ./start.sh 2"
    return 1
}

# Funkcja do wykonania zapytania API
make_api_request() {
    local method=$1
    local endpoint=$2
    local data=$3

    local url="${BASE_URL}${endpoint}"
    local curl_cmd="curl -s -w '%{http_code}' -X $method"

    if [[ -n "$data" ]]; then
        curl_cmd="$curl_cmd -H 'Content-Type: application/json' -d '$data'"
    fi

    curl_cmd="$curl_cmd '$url'"

    local response=$(eval $curl_cmd)
    local http_code="${response: -3}"
    local body="${response%???}"

    echo "$body"
    return $http_code
}

# Funkcja do uruchamiania testu
run_test() {
    local test_name=$1
    local test_function=$2

    TOTAL_TESTS=$((TOTAL_TESTS + 1))

    echo -e "\n${PURPLE}🧪 Test: $test_name${NC}"
    echo -e "${PURPLE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"

    if $test_function; then
        PASSED_TESTS=$((PASSED_TESTS + 1))
        TEST_RESULTS+=("✅ $test_name")
        log_success "$test_name - ZALICZONY"
    else
        FAILED_TESTS=$((FAILED_TESTS + 1))
        TEST_RESULTS+=("❌ $test_name")
        log_error "$test_name - NIEZALICZONY"
    fi
}

# Funkcje testowe

test_server_connection() {
    local response=$(make_api_request "GET" "/api/status")
    local status=$?

    if [[ $status -eq 200 ]]; then
        local server_status=$(echo "$response" | jq -r '.status // "unknown"' 2>/dev/null || echo "unknown")
        if [[ "$server_status" == "running" ]]; then
            echo "  ✅ Serwer działa poprawnie"
            echo "  📍 Status: $server_status"
            return 0
        fi
    fi

    echo "  ❌ Błąd połączenia z serwerem (HTTP $status)"
    return 1
}

test_tools_endpoint() {
    local response=$(make_api_request "GET" "/api/tools")
    local status=$?

    if [[ $status -eq 200 ]]; then
        local tool_count=$(echo "$response" | jq '.tools | length' 2>/dev/null || echo "0")
        if [[ "$tool_count" == "10" ]]; then
            echo "  ✅ Pobrano listę narzędzi ($tool_count narzędzi)"
            local tools=$(echo "$response" | jq -r '.tools[].name' 2>/dev/null | tr '\n' ', ' | sed 's/,$//')
            echo "  🛠️  Dostępne narzędzia: $tools"
            return 0
        else
            echo "  ❌ Nieprawidłowa liczba narzędzi: $tool_count (oczekiwano 10)"
        fi
    fi

    echo "  ❌ Błąd pobierania narzędzi (HTTP $status)"
    return 1
}

test_hello_tool() {
    local data='{"tool": "hello", "arguments": {"name": "Bash Test"}}'
    local response=$(make_api_request "POST" "/api/tools/call" "$data")
    local status=$?

    if [[ $status -eq 200 ]]; then
        local success=$(echo "$response" | jq -r '.success // false' 2>/dev/null)
        local result=$(echo "$response" | jq -r '.result // ""' 2>/dev/null)

        if [[ "$success" == "true" && "$result" == *"Bash Test"* ]]; then
            echo "  ✅ Narzędzie hello działa poprawnie"
            echo "  📝 Odpowiedź: $result"
            return 0
        fi
    fi

    echo "  ❌ Błąd narzędzia hello"
    echo "  📄 Odpowiedź: $response"
    return 1
}

test_calculate_tool() {
    local operations=(
        '{"tool": "calculate", "arguments": {"operation": "add", "a": 10, "b": 5}}'
        '{"tool": "calculate", "arguments": {"operation": "multiply", "a": 6, "b": 7}}'
        '{"tool": "calculate", "arguments": {"operation": "divide", "a": 20, "b": 4}}'
    )

    local expected_results=("Wynik: 15" "Wynik: 42" "Wynik: 5")
    local all_passed=true

    for i in "${!operations[@]}"; do
        local response=$(make_api_request "POST" "/api/tools/call" "${operations[$i]}")
        local status=$?

        if [[ $status -eq 200 ]]; then
            local success=$(echo "$response" | jq -r '.success // false' 2>/dev/null)
            local result=$(echo "$response" | jq -r '.result // ""' 2>/dev/null)

            if [[ "$success" == "true" && "$result" == *"${expected_results[$i]}"* ]]; then
                echo "  ✅ Operacja $(echo "${operations[$i]}" | jq -r '.arguments.operation'): $result"
            else
                echo "  ❌ Błąd operacji $(echo "${operations[$i]}" | jq -r '.arguments.operation')"
                all_passed=false
            fi
        else
            echo "  ❌ Błąd API (HTTP $status)"
            all_passed=false
        fi
    done

    if [[ "$all_passed" == "true" ]]; then
        echo "  ✅ Wszystkie operacje matematyczne zadziałały poprawnie"
        return 0
    else
        echo "  ❌ Niektóre operacje matematyczne nie zadziałały"
        return 1
    fi
}

test_file_operations() {
    local test_content="Test content from bash script - $(date)"
    local test_file="bash_test_file.txt"

    # Test zapisu pliku
    local write_data="{\"tool\": \"write_file\", \"arguments\": {\"path\": \"$test_file\", \"content\": \"$test_content\"}}"
    local write_response=$(make_api_request "POST" "/api/tools/call" "$write_data")
    local write_status=$?

    if [[ $write_status -ne 200 ]]; then
        echo "  ❌ Błąd zapisu pliku (HTTP $write_status)"
        return 1
    fi

    local write_success=$(echo "$write_response" | jq -r '.success // false' 2>/dev/null)
    if [[ "$write_success" != "true" ]]; then
        echo "  ❌ Zapis pliku nie powiódł się: $(echo "$write_response" | jq -r '.error // "")"
        return 1
    fi

    echo "  ✅ Plik zapisany poprawnie"

    # Test odczytu pliku
    local read_data="{\"tool\": \"read_file\", \"arguments\": {\"path\": \"$test_file\"}}"
    local read_response=$(make_api_request "POST" "/api/tools/call" "$read_data")
    local read_status=$?

    if [[ $read_status -ne 200 ]]; then
        echo "  ❌ Błąd odczytu pliku (HTTP $read_status)"
        return 1
    fi

    local read_success=$(echo "$read_response" | jq -r '.success // false' 2>/dev/null)
    local read_result=$(echo "$read_response" | jq -r '.result // ""' 2>/dev/null)

    if [[ "$read_success" != "true" || "$read_result" != *"$test_content"* ]]; then
        echo "  ❌ Odczyt pliku nie powiódł się lub zawartość nie zgadza się"
        return 1
    fi

    echo "  ✅ Plik odczytany poprawnie"

    # Sprzątanie
    rm -f "$test_file" 2>/dev/null

    return 0
}

test_list_files() {
    local data='{"tool": "list_files", "arguments": {"path": "."}}'
    local response=$(make_api_request "POST" "/api/tools/call" "$data")
    local status=$?

    if [[ $status -eq 200 ]]; then
        local success=$(echo "$response" | jq -r '.success // false' 2>/dev/null)
        local result=$(echo "$response" | jq -r '.result // ""' 2>/dev/null)

        if [[ "$success" == "true" && "$result" == *"Pliki w katalogu:"* && "$result" == *"composer.json"* ]]; then
            echo "  ✅ Listowanie plików działa poprawnie"
            local file_count=$(echo "$result" | grep -c '\[FILE\]' || echo "0")
            echo "  📁 Znaleziono plików: $file_count"
            return 0
        fi
    fi

    echo "  ❌ Błąd listowania plików"
    return 1
}

test_security_features() {
    echo "  🔒 Testowanie zabezpieczeń..."

    # Test path traversal
    local malicious_paths=(
        '../../../etc/passwd'
        '/etc/passwd'
        '~/.ssh/id_rsa'
    )

    local all_blocked=true

    for path in "${malicious_paths[@]}"; do
        local data="{\"tool\": \"read_file\", \"arguments\": {\"path\": \"$path\"}}"
        local response=$(make_api_request "POST" "/api/tools/call" "$data")
        local status=$?

        if [[ $status -eq 200 ]]; then
            local success=$(echo "$response" | jq -r '.success // false' 2>/dev/null)
            if [[ "$success" == "true" ]]; then
                echo "  ❌ Ominięto zabezpieczenie dla ścieżki: $path"
                all_blocked=false
            else
                echo "  ✅ Zablokowano próbę dostępu do: $path"
            fi
        else
            echo "  ✅ Zablokowano próbę dostępu do: $path"
        fi
    done

    if [[ "$all_blocked" == "true" ]]; then
        echo "  ✅ Wszystkie testy bezpieczeństwa zaliczone"
        return 0
    else
        echo "  ❌ Niektóre testy bezpieczeństwa nie zostały zaliczone"
        return 1
    fi
}

test_unknown_tool() {
    local data='{"tool": "nonexistent_tool_xyz", "arguments": {}}'
    local response=$(make_api_request "POST" "/api/tools/call" "$data")
    local status=$?

    if [[ $status -eq 200 ]]; then
        local success=$(echo "$response" | jq -r '.success // false' 2>/dev/null)
        if [[ "$success" == "false" ]]; then
            echo "  ✅ Nieznane narzędzie poprawnie zwróciło błąd"
            return 0
        fi
    fi

    echo "  ❌ Nieznane narzędzie powinno zwrócić błąd"
    return 1
}

test_json_operations() {
    local test_json='{"test": "value", "number": 42, "boolean": true}'
    local data="{\"tool\": \"json_parse\", \"arguments\": {\"json\": \"$test_json\"}}"
    local response=$(make_api_request "POST" "/api/tools/call" "$data")
    local status=$?

    if [[ $status -eq 200 ]]; then
        local success=$(echo "$response" | jq -r '.success // false' 2>/dev/null)
        local result=$(echo "$response" | jq -r '.result // ""' 2>/dev/null)

        if [[ "$success" == "true" && "$result" == *"SPARSOWANY JSON"* && "$result" == *"test"* ]]; then
            echo "  ✅ Parsowanie JSON działa poprawnie"
            return 0
        fi
    fi

    echo "  ❌ Błąd parsowania JSON"
    return 1
}

test_weather_tool() {
    local data='{"tool": "get_weather", "arguments": {"city": "Test City"}}'
    local response=$(make_api_request "POST" "/api/tools/call" "$data")
    local status=$?

    if [[ $status -eq 200 ]]; then
        local success=$(echo "$response" | jq -r '.success // false' 2>/dev/null)
        local result=$(echo "$response" | jq -r '.result // ""' 2>/dev/null)

        if [[ "$success" == "true" && "$result" == *"POGODA DLA MIASTA: TEST CITY"* && "$result" == *"°C"* ]]; then
            echo "  ✅ Narzędzie pogodowe działa poprawnie"
            return 0
        fi
    fi

    echo "  ❌ Błąd narzędzia pogodowego"
    return 1
}

# Funkcja do wyświetlania podsumowania
show_summary() {
    echo ""
    echo -e "${CYAN}╔══════════════════════════════════════════════════════════════╗${NC}"
    echo -e "${CYAN}║                         PODSUMOWANIE                           ║${NC}"
    echo -e "${CYAN}╚══════════════════════════════════════════════════════════════╝${NC}"
    echo ""
    echo -e "${BLUE}📊 Statystyki testów:${NC}"
    echo -e "   Łącznie testów: ${YELLOW}$TOTAL_TESTS${NC}"
    echo -e "   Zaliczone: ${GREEN}$PASSED_TESTS${NC}"
    echo -e "   Nie zaliczone: ${RED}$FAILED_TESTS${NC}"

    local success_rate=0
    if [[ $TOTAL_TESTS -gt 0 ]]; then
        success_rate=$((PASSED_TESTS * 100 / TOTAL_TESTS))
    fi

    echo -e "   Skuteczność: ${YELLOW}$success_rate%${NC}"
    echo ""

    if [[ $FAILED_TESTS -eq 0 ]]; then
        echo -e "${GREEN}🎉 Wszystkie testy zaliczone! Serwer działa poprawnie.${NC}"
    else
        echo -e "${RED}❌ Niektóre testy nie zostały zaliczone. Sprawdź logi powyżej.${NC}"
        echo ""
        echo -e "${YELLOW}📋 Lista testów:${NC}"
        for result in "${TEST_RESULTS[@]}"; do
            echo "   $result"
        done
    fi

    echo ""
    echo -e "${BLUE}🌐 Serwer testowany na: $BASE_URL${NC}"
    echo -e "${BLUE}🕐 Czas testowania: $(date)${NC}"
}

# Funkcja do uruchamiania PHPUnit testów
run_phpunit_tests() {
    echo ""
    echo -e "${PURPLE}🧪 Uruchamianie testów PHPUnit...${NC}"
    echo -e "${PURPLE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"

    if [[ ! -f "vendor/bin/phpunit" ]]; then
        log_warning "PHPUnit nie jest zainstalowany. Uruchom 'composer install'"
        return 1
    fi

    if ./vendor/bin/phpunit --configuration=tests/phpunit.xml tests/Unit; then
        log_success "Testy jednostkowe PHP zaliczone"
        return 0
    else
        log_error "Testy jednostkowe PHP nie zaliczone"
        return 1
    fi
}

# Funkcja do otwierania testów JavaScript w przeglądarce
open_frontend_tests() {
    echo ""
    echo -e "${PURPLE}🌐 Otwieranie testów frontendowych...${NC}"

    if command -v xdg-open > /dev/null; then
        xdg-open "tests/frontend/mcp-api-tests.html"
    elif command -v open > /dev/null; then
        open "tests/frontend/mcp-api-tests.html"
    else
        log_info "Otwórz ręcznie: tests/frontend/mcp-api-tests.html"
        return 1
    fi

    log_success "Testy frontendowe otwarte w przeglądarce"
    return 0
}

# Główna funkcja
main() {
    show_header

    # Sprawdzenie zależności
    if ! command -v curl > /dev/null; then
        log_error "curl jest wymagany do uruchomienia testów"
        exit 1
    fi

    if ! command -v jq > /dev/null; then
        log_warning "jq nie jest zainstalowany. Testy mogą nie działać poprawnie."
    fi

    # Wykrycie serwera
    if ! detect_server_port; then
        exit 1
    fi

    echo ""
    echo -e "${BLUE}🚀 Uruchamianie testów integracyjnych HTTP...${NC}"
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"

    # Uruchomienie testów
    run_test "Połączenie z serwerem" "test_server_connection"
    run_test "Pobranie listy narzędzi" "test_tools_endpoint"
    run_test "Narzędzie hello" "test_hello_tool"
    run_test "Narzędzie calculate" "test_calculate_tool"
    run_test "Operacje na plikach" "test_file_operations"
    run_test "Listowanie plików" "test_list_files"
    run_test "Testy bezpieczeństwa" "test_security_features"
    run_test "Nieznane narzędzie" "test_unknown_tool"
    run_test "Operacje JSON" "test_json_operations"
    run_test "Narzędzie pogodowe" "test_weather_tool"

    # Podsumowanie
    show_summary

    # Opcjonalne testy
    echo ""
    echo -e "${YELLOW}📋 Dodatkowe opcje testowania:${NC}"
    echo "  1. Uruchom testy jednostkowe PHP (PHPUnit)"
    echo "  2. Otwórz testy frontendowe (JavaScript)"
    echo "  3. Uruchom wszystkie rodzaje testów"
    echo "  0. Zakończ"
    echo ""
    echo -ne "${CYAN}Wybierz opcję [0-3]: ${NC}"

    read -r choice

    case $choice in
        1)
            run_phpunit_tests
            ;;
        2)
            open_frontend_tests
            ;;
        3)
            echo ""
            log_info "Uruchamianie wszystkich typów testów..."
            run_phpunit_tests
            open_frontend_tests
            ;;
        0)
            log_info "Zakończono testowanie"
            ;;
        *)
            log_warning "Nieprawidłowy wybór"
            ;;
    esac

    echo ""
    log_info "Testowanie zakończone!"
}

# Sprawdzenie czy skrypt jest uruchamiany bezpośrednio
if [[ "${BASH_SOURCE[0]}" == "${0}" ]]; then
    main "$@"
fi