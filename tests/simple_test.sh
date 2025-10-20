#!/bin/bash

# Simple test script for MCP PHP Server
# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

echo -e "${BLUE}🧪 Simple MCP PHP Server Tests${NC}"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"

# Function to test API
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
        echo -e "${GREEN}✅ Success${NC}"
        echo "Response: $response" | head -c 200
        echo ""
        return 0
    else
        echo -e "${RED}❌ Error${NC}"
        return 1
    fi
}

# Detect server port
echo "Detecting server..."
for port in 8888 8889 8890; do
    if curl -s "http://localhost:$port/api/status" > /dev/null 2>&1; then
        echo -e "${GREEN}✅ Found server on port $port${NC}"
        SERVER_PORT=$port
        break
    fi
done

if [ -z "$SERVER_PORT" ]; then
    echo -e "${RED}❌ No running server found${NC}"
    echo "Start server: ./start.sh 2"
    exit 1
fi

# Tests
passed=0
total=0

total=$((total + 1))
if test_api $SERVER_PORT "Server status" "/api/status"; then
    passed=$((passed + 1))
fi

total=$((total + 1))
if test_api $SERVER_PORT "Tools list" "/api/tools"; then
    passed=$((passed + 1))
fi

total=$((total + 1))
if test_api $SERVER_PORT "Hello tool" "/api/tools/call" '{"tool": "hello", "arguments": {"name": "Test"}}'; then
    passed=$((passed + 1))
fi

total=$((total + 1))
if test_api $SERVER_PORT "Calculator" "/api/tools/call" '{"tool": "calculate", "arguments": {"operation": "add", "a": 5, "b": 3}}'; then
    passed=$((passed + 1))
fi

total=$((total + 1))
if test_api $SERVER_PORT "File listing" "/api/tools/call" '{"tool": "list_files", "arguments": {"path": "."}}'; then
    passed=$((passed + 1))
fi

# Summary
echo ""
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${BLUE}📊 Summary:${NC}"
echo "Passed: $passed/$total tests"

if [ $passed -eq $total ]; then
    echo -e "${GREEN}🎉 All tests passed!${NC}"
else
    echo -e "${RED}❌ Some tests failed${NC}"
fi

echo -e "${BLUE}🌐 Server: http://localhost:$SERVER_PORT${NC}"