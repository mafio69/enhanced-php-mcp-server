# 🚀 Plan Testów Wydajnościowych MCP PHP Server v2.1.0

## 📋 Cel Testów

Zidentyfikowanie "cienkich gardeł" w wydajności MCP Servera poprzez kompleksowe testy obciążeniowe i analizę wydajności.

## 🎯 Scenariusze Testowe

### 1. Testy Współbieżności (Concurrency Tests)

#### 1.1 Równoległe zapytania API
```bash
# Test 100 równoległych zapytań do różnych narzędzi
ab -n 100 -c 10 -p http://localhost:8888/api/tools/call \
  -T 'application/json' \
  -d '{"tool":"hello","arguments":{"name":"User_$(uuidgen)"}}'

# Test mieszanych operacji w czasie rzeczywistym
for i in {1..50}; do
  curl -s -X POST http://localhost:8888/api/tools/call \
    -H "Content-Type: application/json" \
    -d '{"tool":"'$(shuf -e hello calculate list_files read_file -n 1)'","arguments":{}}' &
done
```

#### 1.2 Streszcenie połączeń
```bash
# Test 1000 równoległych połączeń
hey -z 30s -c 100 -m POST http://localhost:8888/api/tools/call \
  -H "Content-Type: application/json" \
  -d '{"tool":"calculate","arguments":{"operation":"multiply","a":100,"b":100}}'

# Narastające obciążenie: 10, 50, 100, 500 połączeń
for concurrency in 10 50 100 500; do
  echo "Testing with $concurrency concurrent requests"
  ab -n $((concurrency * 10)) -c $concurrency http://localhost:8888/api/status
done
```

### 2. Testy Obciążenia Ciężkiego (Heavy Load Tests)

#### 2.1 Operacje plikowe pod obciążeniem
```bash
# 1000 równoległych operacji zapisu/odczytu plików
for i in {1..1000}; do
  {
    echo '{"tool":"write_file","arguments":{"path":"perf_test_'$i'.txt","content":"Performance test content '$i'"}}' | \
      curl -s -X POST http://localhost:8888/api/tools/call \
        -H "Content-Type: application/json" -d @- &
    echo '{"tool":"read_file","arguments":{"path":"composer.json"}}' | \
      curl -s -X POST http://localhost:8888/api/tools/call \
        -H "Content-Type: application/json" -d @- &
  } &
done
```

#### 2.2 Duże obiekty JSON
```bash
# Testy z dużymi payloadami JSON
large_json=$(cat <<'EOF'
{
  "data": [$(for i in {1..1000}; do echo '{"id":'$i',"content":"'$(base64 /dev/urandom | head -c 1000)'"}'; done | paste -sd,)]
}
EOF
)

curl -X POST http://localhost:8888/api/tools/call \
  -H "Content-Type: application/json" \
  -d '{"tool":"json_parse","arguments":{"json":"'"$large_json"'"}}'
```

#### 2.3 HTTP Request zewnętrzne API
```bash
# Testy z zewnętrznymi API (wolne odpowiedzi)
external_apis=(
  "https://httpbin.org/delay/5"
  "https://jsonplaceholder.typicode.com/posts/1"
  "https://api.github.com/repos/php/php-src"
)

for api in "${external_apis[@]}"; do
  curl -X POST http://localhost:8888/api/tools/call \
    -H "Content-Type: application/json" \
    -d '{"tool":"http_request","arguments":{"url":"'$api'","method":"GET"}}' &
done
```

### 3. Testy Pamięci (Memory Tests)

#### 3.1 Wyciek pamięci - duże pliki
```bash
# Zapis dużej liczby dużych plików
for i in {1..100}; do
  large_content=$(dd if=/dev/zero bs=1024 count=100 2>/dev/null | base64 | head -c 50000)
  curl -X POST http://localhost:8888/api/tools/call \
    -H "Content-Type: application/json" \
    -d '{"tool":"write_file","arguments":{"path":"memory_test_'$i'.txt","content":"'$large_content'"}}' &
done
```

#### 3.2 Monitorowanie użycia pamięci
```bash
# Monitorowanie pamięci podczas testów
while true; do
  ps aux | grep "[p]hp.*index.php" | awk '{print $6}' | paste -sd+ | bc
  curl -s http://localhost:8888/api/status | jq '.metrics'
  sleep 2
done &
```

### 4. Testy Czasu Odpowiedzi (Response Time Tests)

#### 4.1 Pomiar latencji
```bash
# Testy z precyzyjnym pomiarem czasu
start_time=$(date +%s%N)

curl -w "%{time_total}\n" -s -o /dev/null -X POST \
  http://localhost:8888/api/tools/call \
  -H "Content-Type: application/json" \
  -d '{"tool":"hello","arguments":{"name":"Latency Test"}}'

end_time=$(date +%s%N)
response_time=$((end_time - start_time))
echo "Response time: ${response_time}ns"
```

#### 4.2 Czas odpowiedzi dla różnych narzędzi
```bash
# Porównanie czasów odpowiedzi dla wszystkich narzędzi
tools=("hello" "get_time" "calculate" "system_info" "json_parse")

for tool in "${tools[@]}"; do
  echo "Testing $tool..."
  curl -w "%{time_total}\n" -s -o /dev/null -X POST \
    http://localhost:8888/api/tools/call \
    -H "Content-Type: application/json" \
    -d "{\"tool\":\"$tool\",\"arguments\":{}}" | \
    grep -v "0.000"
done
```

### 5. Testy Długotrwałego Działania (Long-running Tests)

#### 5.1 Stabilność pod ciągłym obciążeniem
```bash
# 24-godzinny test stabilności
echo "Starting 24-hour stability test..."
for hour in {1..24}; do
  echo "Hour $hour: Running performance tests..."

  # Test przez minutę
  timeout 60s bash -c '
    for i in {1..100}; do
      curl -s http://localhost:8888/api/status > /dev/null &
      sleep 0.5
    done
  '

  echo "Hour $hour completed. Memory usage:"
  ps aux | grep "[p]hp.*index.php" | awk '{sum+=$6} END {print sum/1024"MiB"}'

  sleep 3600  # Czekaj 1 godzinę
done
```

#### 5.2 Wycieki zasobów (Resource Leaks)
```bash
# Testowanie wycieków pamięci przez długi czas
echo "Testing for memory leaks..."

baseline_memory=$(ps aux | grep "[p]hp.*index.php" | awk '{sum+=$6} END {print sum}')

for i in {1..1000}; do
  curl -s -X POST http://localhost:8888/api/tools/call \
    -H "Content-Type: application/json" \
    -d '{"tool":"calculate","arguments":{"operation":"add","a":1,"b":1}}' > /dev/null

  if [ $((i % 100)) -eq 0 ]; then
    current_memory=$(ps aux | grep "[p]hp.*index.php" | awk '{sum+=$6} END {print sum}')
    memory_increase=$((current_memory - baseline_memory))
    echo "Iteration $i: Memory increase: ${memory_increase}KB"
  fi
done
```

## 🛠️ Narzędzia do Testowania Wydajności

### Instalacja narzędzi
```bash
# Apache Benchmark
sudo apt-get install apache2-utils

# Hey (Go-based load tester)
go install github.com/rakyll/hey@latest

# Wrk (modern HTTP benchmarking tool)
sudo apt-get install wrk

# Siege
sudo apt-get install siege

# Monitorowanie systemowe
sudo apt-get install htop iotop
```

### Skrypty automatyzujące
```bash
#!/bin/bash
# performance_test_suite.sh

echo "🚀 MCP Server Performance Test Suite"
echo "================================="

# 1. Baseline measurements
echo "📊 Baseline measurements..."
./tests/simple_test.sh

# 2. Concurrency tests
echo "🔄 Concurrency tests..."
./tests/performance/concurrency_tests.sh

# 3. Memory tests
echo "💾 Memory tests..."
./tests/performance/memory_tests.sh

# 4. Response time tests
echo "⏱️ Response time tests..."
./tests/performance/latency_tests.sh

# 5. Load tests
echo "📈 Load tests..."
./tests/performance/load_tests.sh

# 6. Generate report
echo "📋 Generating report..."
./tests/performance/generate_report.sh
```

## 📊 Metryki do Monitorowania

### Poziom Aplikacji (Application Level)
- Czas odpowiedzi per narzędzie
- Liczba zapytań na sekundę (RPS)
- Czas wykonania każdej operacji
- Błędy i wyjątki

### Poziom Systemu (System Level)
- Użycie CPU i pamięci
- Liczba aktywnych procesów PHP
- Użycie dysku I/O
- Szybkość sieci

### Poziom Infrastruktury (Infrastructure Level)
- Czas odpowiedzi sieci
- Liczba aktywnych połączeń
- Przepustowość łącza

## 🎯 Kryteria Sukcesu

### Wydajność Docelowa
- **RPS (Requests Per Second):** > 100 dla prostych operacji
- **Czas odpowiedzi:** < 100ms dla narzędzi prostych, < 500ms dla operacji plikowych
- **Pamięć:** < 50MB stałego zużycia + 10MB na 100 równoległych połączeń

### Stabilność
- **Uptime:** > 99.9% podczas testów obciążeniowych
- **Memory leaks:** < 1MB wzrostu pamięci na 1000 operacji
- **Błędy:** < 0.1% rate błędów pod obciążeniem

### Skalowalność
- **Konkurencja:** Bez problemów z 100+ równoległymi zapytaniami
- **Obciążenie:** Utrzymanie działania przy 5x normalnym obciążeniu
- **Odzyski:** Szybkie odzyski po zakończeniu obciążenia

## 📈 Raportowanie Wyników

### Format Raportu
```markdown
# MCP Performance Test Report - $(date)

## Test Environment
- Server: MCP PHP Server v2.1.0
- PHP Version: $(php -v)
- System: $(uname -a)
- Test Date: $(date)

## Results Summary
- Total Tests: [liczba]
- Passed: [liczba]
- Failed: [liczba]
- Success Rate: [procent]%

## Performance Metrics
### Concurrency Tests
- Max Concurrent Requests: [wartość]
- Average Response Time: [czas]ms
- Requests Per Second: [wartość]

### Memory Usage
- Baseline Memory: [wartość]MB
- Peak Memory: [wartość]MB
- Memory Leak Rate: [wartość]KB/1000ops

### Response Times by Tool
- hello: [czas]ms
- get_time: [czas]ms
- calculate: [czas]ms
- list_files: [czas]ms
- read_file: [czas]ms
- write_file: [czas]ms
```

## 🕐️ Harmonogram na Jutro

### 9:00 - 9:30: Przygotowanie środowiska
- Instalacja narzędzi
- Sprawdzenie konfiguracji serwera
- Uruchomienie monitoringu

### 9:30 - 10:30: Testy bazowe i concurrency
- Testy wydajności pojedynczych narzędzi
- Testy współbieżności (10, 50, 100 połączeń)
- Pomiar RPS i czasu odpowiedzi

### 10:30 - 11:30: Testy obciążenia ciężkiego
- Operacje plikowe pod obciążeniem
- Testy z dużymi payloadami JSON
- Testy zewnętrznych API

### 11:30 - 12:00: Testy pamięci i stabilności
- Wycieki pamięci
- Testy długotrwałego działania
- Analiza wyników

### 12:00 - 12:30: Analiza i raportowanie
- Generowanie raportów
- Identyfikacja "cienkich gardeł"
- Rekomendacje optymalizacji

## 🔧 Potencjalne Obszary Optymalizacji

### Kod
- Opcjonalne cache'owanie wyników
- Lazy loading dla ciężkich operacji
- Buforowanie odpowiedzi

### Konfiguracja
- Tune PHP-FPM settings
- Optymalizacja ustawień serwera
- Konfiguracja połączeń bazodanowych

### Infrastruktura
- Load balancer
- Redis cache
- CDN dla statycznych zasobów

---

**Planowany:** $(date)
**Autor:** Claude Code Assistant
**Wersja:** 1.0