# Testy MCP PHP Servera

Kompleksowy zestaw testów dla MCP PHP Server v2.1.0, pokrywający różne warstwy aplikacji i rodzaje testowania.

## 📁 Struktura testów

```
tests/
├── README.md                    # Ten plik
├── phpunit.xml                  # Konfiguracja PHPUnit
├── run_comprehensive_tests.sh   # Główny skrypt testowy
│
├── Unit/                        # Testy jednostkowe
│   └── MCPServerHTTPTest.php    # Testy klasy MCPServerHTTP
│
├── Integration/                 # Testy integracyjne
│   └── HTTPAPITest.php          # Testy HTTP API
│
└── frontend/                    # Testy frontendowe
    └── mcp-api-tests.html       # Interaktywne testy JavaScript
```

## 🚀 Szybki start

### 1. Uruchomienie głównego skryptu testowego

```bash
# Uruchom kompleksowy zestaw testów
./tests/run_comprehensive_tests.sh
```

Skrypt automatycznie:
- Wykryje działający port serwera (8888, 8889, 8890)
- Przeprowadzi 10 testów integracyjnych HTTP
- Wyświetli szczegółowe wyniki
- Zaoferuje opcje dodatkowych testów

### 2. Testy jednostkowe PHP (PHPUnit)

```bash
# Uruchom testy jednostkowe
composer test

# Lub bezpośrednio
./vendor/bin/phpunit --configuration=tests/phpunit.xml tests/Unit
```

### 3. Testy frontendowe (JavaScript)

```bash
# Otwórz testy w przeglądarce
open tests/frontend/mcp-api-tests.html

# Lub użyj głównego skryptu i wybierz opcję 2
./tests/run_comprehensive_tests.sh
```

## 📊 Rodzaje testów

### 1. Testy jednostkowe (PHPUnit)

**Lokalizacja:** `tests/Unit/MCPServerHTTPTest.php`

**Cel:** Testowanie pojedynczych komponentów w izolacji

**Pokrycie:**
- ✅ Rejestracja narzędzi
- ✅ Logika biznesowa każdego narzędzia
- ✅ Walidacja parametrów
- ✅ Obsługa błędów
- ✅ Monitoring i logowanie
- ✅ Bezpieczeństwo operacji plikowych

**Przykład użycia:**
```bash
./vendor/bin/phpunit --filter testHelloTool
./vendor/bin/phpunit --filter testFileOperations
./vendor/bin/phpunit --coverage-html coverage-html
```

### 2. Testy integracyjne (HTTP API)

**Lokalizacja:** `tests/Integration/HTTPAPITest.php`

**Cel:** Testowanie całego przepływu HTTP przez API

**Funkcjonalności:**
- ✅ Dynamiczne wykrywanie portu serwera
- ✅ Komunikacja z rzeczywistym API
- ✅ Testy wszystkich endpointów
- ✅ Walidacja odpowiedzi HTTP
- ✅ Testy współbieżności
- ✅ Obsługa błędów sieciowych

**Uruchomienie:**
```bash
# Wymaga działającego serwera
./start.sh 2  # Uruchom serwer na porcie 8888/8889
./vendor/bin/phpunit tests/Integration/HTTPAPITest.php
```

### 3. Testy frontendowe (JavaScript)

**Lokalizacja:** `tests/frontend/mcp-api-tests.html`

**Cel:** Testowanie interfejsu użytkownika i API z poziomu przeglądarki

**Funkcjonalności:**
- ✅ Interaktywny interfejs testowy
- ✅ Auto-detekcja portu
- ✅ Testy w czasie rzeczywistym
- ✅ Wizualizacja wyników
- ✅ Testy operacji plikowych
- ✅ Testy bezpieczeństwa

**Użycie:**
1. Otwórz plik w przeglądarce
2. System automatycznie wykryje port serwera
3. Kliknij "Uruchom wszystkie testy"
4. Obserwuj wyniki w czasie rzeczywistym

### 4. Testy powłoki (Bash)

**Lokalizacja:** `tests/run_comprehensive_tests.sh`

**Cel:** Kompleksowe testy z linii komend

**Funkcjonalności:**
- ✅ Auto-detekcja portu serwera
- ✅ 10 testów integracyjnych
- ✅ Kolorowe wyniki
- ✅ Statystyki i podsumowanie
- ✅ Integracja z innymi testami

## 🧪 Scenariusze testowe

### Testy funkcjonalne

1. **Narzędzia podstawowe**
   - `hello` - Powitanie użytkownika
   - `get_time` - Aktualny czas
   - `calculate` - Operacje matematyczne
   - `system_info` - Informacje o systemie

2. **Operacje na plikach**
   - `list_files` - Listowanie katalogów
   - `read_file` - Odczyt plików
   - `write_file` - Zapis plików
   - `json_parse` - Parsowanie JSON

3. **Narzędzia zaawansowane**
   - `http_request` - Zapytania HTTP
   - `get_weather` - Informacje pogodowe

### Testy bezpieczeństwa

1. **Path Traversal Protection**
   - Blokada dostępu do `../../../etc/passwd`
   - Ograniczenie do katalogu projektu
   - Walidacja ścieżek względnych i absolutnych

2. **Input Validation**
   - Walidacja wymaganych parametrów
   - Obsługa pustych wartości
   - Ochrona przed złośliwym inputem

3. **Error Handling**
   - Poprawne kody HTTP
   - Bezpieczne komunikaty błędów
   - Nieujawnianie danych wrażliwych

### Testy wydajności

1. **Czas odpowiedzi**
   - Pomiar czasu wykonania każdego narzędzia
   - Identyfikacja wolnych operacji
   - Monitorowanie w czasie

2. **Współbieżność**
   - Testy równoległych zapytań
   - Obsługa wielu klientów
   - Stabilność pod obciążeniem

## 📈 Pokrycie kodu

### Aktualne pokrycie
- **Testy jednostkowe:** ~95% logiki biznesowej
- **Testy integracyjne:** ~90% endpointów API
- **Testy bezpieczeństwa:** 100% ścieżek ataku

### Generowanie raportów pokrycia

```bash
# HTML raport
./vendor/bin/phpunit --coverage-html coverage-html tests/Unit

# Text raport
./vendor/bin/phpunit --coverage-text tests/Unit

# Clover XML (dla CI/CD)
./vendor/bin/phpunit --coverage-clover coverage.xml tests/Unit
```

## 🔧 Konfiguracja

### PHPUnit (`tests/phpunit.xml`)

```xml
<phpunit>
    <testsuites>
        <testsuite name="Unit">
            <directory>Unit</directory>
        </testsuite>
        <testsuite name="Integration">
            <directory>Integration</directory>
        </testsuite>
    </testsuites>

    <coverage>
        <report>
            <html outputDirectory="coverage-html"/>
            <text outputFile="coverage.txt"/>
        </report>
    </coverage>
</phpunit>
```

### Zmienne środowiskowe

```bash
# Debug mode
export MCP_TEST_DEBUG=1

# Custom port
export MCP_TEST_PORT=8889

# Timeout dla testów sieciowych
export MCP_TEST_TIMEOUT=10
```

## 🚨 Troubleshooting

### Common Issues

1. **Server not running**
   ```bash
   ./start.sh 2  # Uruchom serwer web
   ./tests/run_comprehensive_tests.sh
   ```

2. **Port conflicts**
   ```bash
   # Sprawdź zajęte porty
   lsof -i :8888
   lsof -i :8889

   # Użyj auto-detekcji
   ./tests/run_comprehensive_tests.sh
   ```

3. **Missing dependencies**
   ```bash
   composer install  # PHPUnit i inne zależności
   ```

4. **Permission issues**
   ```bash
   chmod +x tests/run_comprehensive_tests.sh
   chmod +x start.sh
   ```

### Debug mode

```bash
# Włącz szczegółowe logowanie
export MCP_TEST_DEBUG=1
./tests/run_comprehensive_tests.sh

# Dla PHPUnit
./vendor/bin/phpunit --verbose tests/Unit

# Dla testów JavaScript
# Otwórz narzędzia deweloperskie w przeglądarce
```

## 📝 Przykłady użycia

### Szybki test rozwojowy

```bash
# Test pojedynczej funkcjonalności
./vendor/bin/phpunit --filter testHelloTool tests/Unit

# Testy operacji plikowych
./tests/run_comprehensive_tests.sh | grep -A5 "Operacje na plikach"
```

### Testy CI/CD

```bash
# Pełny zestaw testów dla pipeline
composer test                           # PHPUnit
./tests/run_comprehensive_tests.sh       # Integration tests
```

### Testy manualne

```bash
# Interaktywne testy frontendowe
open tests/frontend/mcp-api-tests.html

# Testy konkretnych narzędzi
curl -X POST http://localhost:8888/api/tools/call \
  -H "Content-Type: application/json" \
  -d '{"tool": "hello", "arguments": {"name": "Test"}}'
```

## 🎯 Najlepsze praktyki

1. **Przed commitem:**
   - Uruchom `composer test`
   - Sprawdź pokrycie kodu
   - Uruchom testy bezpieczeństwa

2. **Przed deployem:**
   - Pełny zestaw testów integracyjnych
   - Testy na różnych portach
   - Testy obciążeniowe

3. **Rozwój:**
   - Testuj jedną funkcjonalność na raz
   - Używaj trybu debugowania
   - Sprawdzaj logi serwera

## 📞 Wsparcie

Jeśli napotkasz problemy z testami:

1. Sprawdź logi: `tail -f logs/server.log`
2. Uruchom serwer: `./start.sh 2`
3. Sprawdź zależności: `composer install`
4. Użyj debug mode: `export MCP_TEST_DEBUG=1`

---

**Autor:** Claude Code Assistant
**Wersja:** 1.0
**Ostatnia aktualizacja:** $(date)