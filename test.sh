#!/bin/bash

# Prosty test bez interaktywnej pętli
echo "🚀 Test MCP PHP Server"
echo ""

echo "1. Test CLI mode:"
php -r "echo 'PHP version: ' . PHP_VERSION . PHP_EOL;"
echo ""

echo "2. Test zależności:"
if [ -d "vendor" ]; then
    echo "✅ Vendor directory exists"
else
    echo "❌ Vendor directory missing - run 'composer install'"
fi

echo ""
echo "3. Test log directory:"
if [ -d "logs" ]; then
    echo "✅ Logs directory exists"
else
    echo "❌ Logs directory missing"
fi

echo ""
echo "🎯 Uruchom serwer web mode na porcie 8888..."
php -S localhost:8888 index.php