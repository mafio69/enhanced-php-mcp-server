#!/bin/bash

# Prosty skrypt testowy MCP PHP Server
# Kolory
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

echo -e "${BLUE}🧪 Proste testy MCP PHP Servera${NC}"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"

# Funkcja do testowania API
test_api() {
    local port=$1
    local test_name=$2
    local endpoint=$3
    local data=$4

    echo -e "\n${YELLOW}Test: $test_name${NC}"

    if [ -n "$data" ]; then
        response=$(curl -s -X POST "http://localhost:$port$endpoint" \
            -H "Content-Type: application/json" \
            -d "$data" 2>/dev/null)
    else
        response=$(curl -s "http://localhost:$port$endpoint" 2>/dev/null)
    fi

    if [ $? -eq 0 ] && [ -n "$response" ]; then
        echo -e "${GREEN}✅ Sukces${NC}"
        echo "Odpowiedź: $response" | head -c 200
        echo ""
        return 0
    else
        echo -e "${RED}❌ Błąd${NC}"
        return 1
    fi
}

# Wykryj port serwera
echo "Wykrywanie serwera..."
for port in 8888 8889 8890; do
    if curl -s "http://localhost:$port/api/status" > /dev/null 2>&1; then
        echo -e "${GREEN}✅ Znaleziono serwer na porcie $port${NC}"
        SERVER_PORT=$port
        break
    fi
done

if [ -z "$SERVER_PORT" ]; then
    echo -e "${RED}❌ Nie znaleziono działającego serwera${NC}"
    echo "Uruchom serwer: ./start.sh 2"
    exit 1
fi

# Testy
passed=0
total=0

total=$((total + 1))
if test_api $SERVER_PORT "Status serwera" "/api/status"; then
    passed=$((passed + 1))
fi

total=$((total + 1))
if test_api $SERVER_PORT "Lista narzędzi" "/api/tools"; then
    passed=$((passed + 1))
fi

total=$((total + 1))
if test_api $SERVER_PORT "Narzędzie hello" "/api/tools/call" '{"tool": "hello", "arguments": {"name": "Test"}}'; then
    passed=$((passed + 1))
fi

total=$((total + 1))
if test_api $SERVER_PORT "Kalkulator" "/api/tools/call" '{"tool": "calculate", "arguments": {"operation": "add", "a": 5, "b": 3}}'; then
    passed=$((passed + 1))
fi

total=$((total + 1))
if test_api $SERVER_PORT "Listowanie plików" "/api/tools/call" '{"tool": "list_files", "arguments": {"path": "."}}'; then
    passed=$((passed + 1))
fi

# Podsumowanie
echo ""
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${BLUE}📊 Podsumowanie:${NC}"
echo "Zaliczone: $passed/$total testów"

if [ $passed -eq $total ]; then
    echo -e "${GREEN}🎉 Wszystkie testy zaliczone!${NC}"
else
    echo -e "${RED}❌ Niektóre testy nie zaliczone${NC}"
fi

echo -e "${BLUE}🌐 Serwer: http://localhost:$SERVER_PORT${NC}"