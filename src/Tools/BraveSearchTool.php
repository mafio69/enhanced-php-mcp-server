<?php

namespace App\Tools;

use App\Interfaces\ToolInterface;

class BraveSearchTool implements ToolInterface
{
    private $secretManager;

    public function __construct()
    {
        // Defer secret manager initialization until execution
        $this->secretManager = null;
    }

    public function execute(array $arguments = []): string
    {
        $query = $arguments['query'] ?? '';
        $count = intval($arguments['count'] ?? 10);

        if (empty($query)) {
            throw new \Exception("Parametr 'query' jest wymagany");
        }

        // Try to get API key from multiple sources
        $apiKey = $this->getBraveApiKey();

        if (!$apiKey) {
            return "=== BRAVE SEARCH ===\n\n❌ BRAVE_API_KEY nie jest ustawiony.\n\nMożliwości:\n1. Ustaw zmienną środowiskową: export BRAVE_API_KEY='twój_klucz_api'\n2. Dodaj klucz przez panel admina: http://localhost:8889/admin/\n3. Zapisz sekret jako 'brave-search.BRAVE_API_KEY'";
        }

        // Przygotowanie zapytania do Brave Search API
        $url = 'https://api.search.brave.com/res/v1/web/search';
        $headers = [
            'Accept: application/json',
            'Accept-Encoding: gzip',
            'X-Subscription-Token: ' . $apiKey
        ];

        $params = [
            'q' => $query,
            'count' => min($count, 20), // Max 20 wyników
            'text_decorations' => 'false',
            'search_lang' => 'pl',
            'ui_lang' => 'pl',
            'result_filter' => 'news,web,discussions',
            'safesearch' => 'moderate'
        ];

        $fullUrl = $url . '?' . http_build_query($params);

        // Wykonanie zapytania
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $fullUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT => 'PHP-MCP-Server/2.1.0'
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return "=== BRAVE SEARCH ===\n\n❌ Błąd połączenia: " . $error;
        }

        if ($httpCode !== 200) {
            return "=== BRAVE SEARCH ===\n\n❌ Błąd API (HTTP {$httpCode})\n\nOdpowiedź: " . substr($response, 0, 200) . "...";
        }

        // Przetworzenie wyników
        $data = json_decode($response, true);
        if (!$data) {
            return "=== BRAVE SEARCH ===\n\n❌ Nie można przetworzyć odpowiedzi JSON";
        }

        return $this->formatSearchResults($query, $data, $count);
    }

    private function formatSearchResults(string $query, array $data, int $requestedCount): string
    {
        $result = "=== BRAVE SEARCH ===\n";
        $result .= "Zapytanie: \"{$query}\"\n";
        $result .= "Czas wyszukiwania: " . date('Y-m-d H:i:s') . "\n";
        $result .= str_repeat("=", 60) . "\n\n";

        $totalResults = $data['web']['results'] ?? [];
        $actualCount = min(count($totalResults), $requestedCount);

        if ($actualCount === 0) {
            $result .= "❌ Brak wyników dla podanego zapytania.\n";
            return $result;
        }

        $result .= "📊 Znaleziono wyników: {$actualCount}\n\n";

        for ($i = 0; $i < $actualCount; $i++) {
            $item = $totalResults[$i];

            $result .= ($i + 1) . ". " . $this->cleanText($item['title'] ?? 'Brak tytułu') . "\n";
            $result .= "   🔗 " . ($item['url'] ?? 'Brak URL') . "\n";

            if (!empty($item['description'])) {
                $result .= "   📝 " . $this->cleanText($item['description']) . "\n";
            }

            // Dodatkowe informacje
            if (!empty($item['age'])) {
                $result .= "   📅 " . $item['age'] . "\n";
            }

            if (!empty($item['language'])) {
                $result .= "   🌐 " . strtoupper($item['language']) . "\n";
            }

            $result .= "\n";
        }

        // Dodatkowe informacje
        if (isset($data['web']['search_time'])) {
            $result .= "⚡ Czas odpowiedzi: " . number_format($data['web']['search_time'], 3) . "s\n";
        }

        $result .= "\n---\n";
        $result .= "Wyniki z Brave Search API • Limit: {$actualCount}/{$requestedCount}\n";

        return $result;
    }

    private function cleanText(string $text): string
    {
        // Usuń HTML entities i nadmiarowe spacje
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/', ' ', $text);
        return trim($text);
    }

    public function getName(): string
    {
        return 'brave_search';
    }

    public function getDescription(): string
    {
        return 'Przeszukuje internet za pomocą Brave Search API';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => [
                    'type' => 'string',
                    'description' => 'Fraza wyszukiwania (wymagana)'
                ],
                'count' => [
                    'type' => 'integer',
                    'description' => 'Liczba wyników (1-20, domyślnie 10)',
                    'minimum' => 1,
                    'maximum' => 20,
                    'default' => 10
                ]
            ],
            'required' => ['query']
        ];
    }

    public function isEnabled(): bool
    {
        return true; // Włączone, ale sprawdzi klucz API przy wykonaniu
    }

    /**
     * Get Brave API key from multiple sources
     */
    private function getBraveApiKey(): ?string
    {
        // 1. Try environment variable first
        $apiKey = getenv('BRAVE_API_KEY') ?: $_ENV['BRAVE_API_KEY'] ?? null;
        if ($apiKey) {
            return $apiKey;
        }

        // 2. Try reading secret directly from file storage
        $secretsPath = __DIR__ . '/../../storage/secrets/brave-search_BRAVE_API_KEY.sec';
        if (file_exists($secretsPath)) {
            try {
                // Try to read the encrypted file
                $encryptedContent = file_get_contents($secretsPath);
                if ($encryptedContent !== false) {
                    // Since we can't decrypt without the full SecretManagerService,
                    // let's try to check if there's a simpler way
                    return $this->tryDecryptWithSimpleMethod($encryptedContent);
                }
            } catch (\Exception $e) {
                // File reading failed, continue
            }
        }

        return null;
    }

    /**
     * Simple decryption attempt - just try to detect if it's already a plain text key
     */
    private function tryDecryptWithSimpleMethod($content): ?string
    {
        // If it looks like an API key already (plain text)
        if (is_string($content) && strlen($content) > 20 && preg_match('/^[A-Za-z0-9_\-]+$/', $content)) {
            return $content;
        }

        // Try base64 decode in case it's simple encoding
        $decoded = base64_decode($content);
        if ($decoded && strlen($decoded) > 20 && preg_match('/^[A-Za-z0-9_\-]+$/', $decoded)) {
            return $decoded;
        }

        return null;
    }
}