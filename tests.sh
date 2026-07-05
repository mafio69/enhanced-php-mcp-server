#!/bin/sh

# Upewnijmy się, że uruchamiamy skrypt z katalogu głównego
cd "$(dirname "$0")" || exit 1

echo "=================================================="
echo "      URUCHAMIANIE TESTÓW JEDNOSTKOWYCH (PHP)     "
echo "=================================================="

if [ -f "./vendor/bin/phpunit" ]; then
    ./vendor/bin/phpunit -c tests/phpunit.xml --no-coverage
    if [ $? -ne 0 ]; then
        echo "❌ Testy jednostkowe (PHPUnit) zakończyły się błędem!"
        exit 1
    fi
    echo "✅ Testy jednostkowe zaliczone."
else
    echo "⚠️ Nie znaleziono PHPUnit. Pomijam testy jednostkowe."
fi

echo ""
echo "=================================================="
echo "      URUCHAMIANIE TESTÓW E2E (Playwright)        "
echo "=================================================="

# Uruchomienie testów Playwright
if ! command -v npx >/dev/null 2>&1; then
    echo "⚠️ Nie znaleziono narzędzia 'npx'. Pomijam testy E2E (Playwright)."
    echo "✅ Zakończono sprawdzanie."
    exit 0
fi

rm -f storage/.test_admin_password
CI=true npx playwright test

if [ $? -ne 0 ]; then
    echo "❌ Testy E2E (Playwright) zakończyły się błędem!"
    echo "📄 Szczegółowy raport HTML znajdziesz w folderze .local (index.html)"
    exit 1
fi

echo "✅ Testy E2E zaliczone."
echo "📄 Szczegółowy raport HTML z testów znajduje się w folderze .local (index.html)"
echo "🎉 Wszystkie testy przebiegły pomyślnie!"
exit 0
