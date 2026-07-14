<?php

// Standalone install: pakiet ma własny vendor/. Zainstalowany jako zależność
// Composera: autoload żyje trzy poziomy wyżej, w vendor/autoload.php hosta.
$standaloneAutoload = dirname(__DIR__) . '/vendor/autoload.php';
$hostAutoload = dirname(__DIR__, 3) . '/autoload.php';
require_once file_exists($standaloneAutoload) ? $standaloneAutoload : $hostAutoload;

use App\AppContainer;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

// Mini-parser .env z automatycznym generowaniem klucza
$envPath = dirname(__DIR__) . '/.env';

// Jeśli MCP_SECRET_KEY już jest w środowisku (np. wstrzyknięty przez hosta jako
// zależność), nie próbuj dopisywać go do lokalnego .env — ten katalog bywa
// tylko do odczytu, gdy pakiet leży w cudzym vendor/.
if (getenv('MCP_SECRET_KEY') === false
    && (!file_exists($envPath) || !str_contains(file_get_contents($envPath), 'MCP_SECRET_KEY='))
) {
    $newKey = base64_encode(random_bytes(32));
    file_put_contents($envPath, "MCP_SECRET_KEY={$newKey}\n", FILE_APPEND);
}

if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#')) continue;
        if (str_contains($line, '=')) {
            [$name, $value] = explode('=', $line, 2);
            $envName = trim($name);
            $envValue = trim($value);
            if (getenv($envName) === false) {
                putenv($envName . '=' . $envValue);
            }
        }
    }
}

try {
    // Zbudowanie i zwrócenie kontenera DI
    return AppContainer::build();
} catch (Exception $e) {
    // Logujemy błąd krytyczny
    $logMessage = "Critical error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine();
    error_log($logMessage);

    if (php_sapi_name() === 'cli') {
        fwrite(STDERR, "Błąd krytyczny serwera: " . $e->getMessage() . "\n");
        exit(1);
    } else {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Internal Server Error', 'message' => $e->getMessage()]);
        exit(1);
    }
} catch (NotFoundExceptionInterface|ContainerExceptionInterface $e) {
    $logMessage = "Critical error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine();
    error_log($logMessage);
    exit(1);
}
