<?php

namespace App\Tools;

use App\Interfaces\ToolInterface;

class GitHubTool implements ToolInterface
{
    public function execute(array $arguments = []): string
    {
        $action = $arguments['action'] ?? 'info';
        $owner = $arguments['owner'] ?? '';
        $repo = $arguments['repo'] ?? '';
        $username = $arguments['username'] ?? '';
        $token = $arguments['token'] ?? '';
        $searchQuery = $arguments['searchQuery'] ?? '';
        $per_page = $arguments['per_page'] ?? 10;
        $page = $arguments['page'] ?? 1;

        switch ($action) {
            case 'info':
                return $this->getInfo();

            case 'get_user_info':
                if (empty($username)) {
                    throw new \Exception("Username jest wymagany dla akcji 'get_user_info'");
                }
                return $this->getUserInfo($username, $token);

            case 'get_repo_info':
                if (empty($owner) || empty($repo)) {
                    throw new \Exception("Owner i repo są wymagane dla akcji 'get_repo_info'");
                }
                return $this->getRepoInfo($owner, $repo, $token);

            case 'list_user_repos':
                if (empty($username)) {
                    throw new \Exception("Username jest wymagany dla akcji 'list_user_repos'");
                }
                return $this->listUserRepos($username, $token, $per_page, $page);

            case 'list_repo_commits':
                if (empty($owner) || empty($repo)) {
                    throw new \Exception("Owner i repo są wymagane dla akcji 'list_repo_commits'");
                }
                return $this->listRepoCommits($owner, $repo, $token, $per_page, $page);

            case 'search_repositories':
                if (empty($searchQuery)) {
                    throw new \Exception("Search query jest wymagany dla akcji 'search_repositories'");
                }
                return $this->searchRepositories($searchQuery, $token, $per_page, $page);

            case 'get_repo_contents':
                if (empty($owner) || empty($repo)) {
                    throw new \Exception("Owner i repo są wymagane dla akcji 'get_repo_contents'");
                }
                $path = $arguments['path'] ?? '';
                return $this->getRepoContents($owner, $repo, $path, $token);

            default:
                throw new \Exception("Nieznana akcja: {$action}");
        }
    }

    private function getInfo(): string
    {
        return "=== GITHUB TOOL ===\n\n" .
               "🐙 Narzędzie do interakcji z GitHub API\n" .
               "Umożliwia: pobieranie informacji o użytkownikach, repozytoriach, commitach, wyszukiwanie\n\n" .
               "Dostępne akcje:\n" .
               "• get_user_info - Pobierz informacje o użytkowniku\n" .
               "• get_repo_info - Pobierz informacje o repozytorium\n" .
               "• list_user_repos - Lista repozytoriów użytkownika\n" .
               "• list_repo_commits - Lista commitów repozytorium\n" .
               "• search_repositories - Wyszukaj repozytoria\n" .
               "• get_repo_contents - Pobierz zawartość pliku/katalogu\n\n" .
               "Uwaga: Token GitHub API jest opcjonalny, ale zwiększa limit zapytań\n" .
               "Utwórz token: https://github.com/settings/tokens";
    }

    private function makeApiCall(string $url, ?string $token = null): array
    {
        $ch = curl_init();

        $headers = [
            'Accept: application/vnd.github.v3+json',
            'User-Agent: MCP-PHP-Server'
        ];

        // Use provided token or get from environment
        $apiToken = $token ?: $this->getGitHubToken();

        if ($apiToken) {
            $headers[] = "Authorization: token {$apiToken}";
        }

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \Exception("Błąd API GitHub: {$error}");
        }

        $data = json_decode($response, true);

        if ($httpCode >= 400) {
            $message = $data['message'] ?? 'Unknown error';
            throw new \Exception("GitHub API Error ({$httpCode}): {$message}");
        }

        return $data;
    }

    /**
     * Get GitHub token from environment variables
     */
    private function getGitHubToken(): ?string
    {
        // Try $_ENV first (from .env), then getenv()
        $token = $_ENV['GITHUB_PERSONAL_ACCESS_TOKEN'] ??
                 $_ENV['GITHUB_TOKEN'] ?:
                 getenv('GITHUB_PERSONAL_ACCESS_TOKEN') ?:
                 getenv('GITHUB_TOKEN') ?? null;

        return $token;
    }

    private function getUserInfo(string $username, ?string $token = null): string
    {
        $result = "=== GITHUB USER INFO: {$username} ===\n\n";

        try {
            $url = "https://api.github.com/users/" . urlencode($username);
            $data = $this->makeApiCall($url, $token);

            $result .= "👤 Login: {$data['login']}\n";
            $result .= "📝 Name: " . ($data['name'] ?? 'N/A') . "\n";
            $result .= "📧 Email: " . ($data['email'] ?? 'N/A') . "\n";
            $result .= "🏢 Company: " . ($data['company'] ?? 'N/A') . "\n";
            $result .= "📍 Location: " . ($data['location'] ?? 'N/A') . "\n";
            $result .= "🔗 Profile: " . ($data['html_url'] ?? 'N/A') . "\n";
            $result .= "📊 Public repos: " . ($data['public_repos'] ?? 0) . "\n";
            $result .= "👥 Followers: " . ($data['followers'] ?? 0) . "\n";
            $result .= "➕ Following: " . ($data['following'] ?? 0) . "\n";
            $result .= "📅 Created: " . ($data['created_at'] ?? 'N/A') . "\n";
            $result .= "🔄 Updated: " . ($data['updated_at'] ?? 'N/A') . "\n";

        } catch (\Exception $e) {
            $result .= "❌ Błąd: " . $e->getMessage() . "\n";
        }

        return $result;
    }

    private function getRepoInfo(string $owner, string $repo, ?string $token = null): string
    {
        $result = "=== GITHUB REPO INFO: {$owner}/{$repo} ===\n\n";

        try {
            $url = "https://api.github.com/repos/" . urlencode($owner) . "/" . urlencode($repo);
            $data = $this->makeApiCall($url, $token);

            $result .= "📦 Name: {$data['name']}\n";
            $result .= "📝 Description: " . ($data['description'] ?? 'No description') . "\n";
            $result .= "👤 Owner: {$data['owner']['login']}\n";
            $result .= "🌐 URL: " . ($data['html_url'] ?? 'N/A') . "\n";
            $result .= "🔗 Clone URL: " . ($data['clone_url'] ?? 'N/A') . "\n";
            $result .= "⭐ Stars: " . ($data['stargazers_count'] ?? 0) . "\n";
            $result .= "🍴 Forks: " . ($data['forks_count'] ?? 0) . "\n";
            $result .= "👀 Watchers: " . ($data['watchers_count'] ?? 0) . "\n";
            $result .= "🔀 Default branch: " . ($data['default_branch'] ?? 'main') . "\n";
            $result .= "📄 Language: " . ($data['language'] ?? 'N/A') . "\n";
            $result .= "📅 Created: " . ($data['created_at'] ?? 'N/A') . "\n";
            $result .= "🔄 Updated: " . ($data['updated_at'] ?? 'N/A') . "\n";
            $result .= "📊 Size: " . ($data['size'] ?? 0) . " KB\n";
            $result .= "🔓 Is private: " . (($data['private'] ?? false) ? 'Yes' : 'No') . "\n";

        } catch (\Exception $e) {
            $result .= "❌ Błąd: " . $e->getMessage() . "\n";
        }

        return $result;
    }

    private function listUserRepos(string $username, ?string $token = null, int $per_page = 10, int $page = 1): string
    {
        $result = "=== GITHUB USER REPOS: {$username} (Page {$page}) ===\n\n";

        try {
            $per_page = min($per_page, 100); // GitHub limit
            $url = "https://api.github.com/users/" . urlencode($username) . "/repos?per_page={$per_page}&page={$page}";
            $data = $this->makeApiCall($url, $token);

            if (empty($data)) {
                $result .= "ℹ️  Brak repozytoriów\n";
                return $result;
            }

            $result .= "📁 Znaleziono " . count($data) . " repozytoriów:\n\n";

            foreach ($data as $repo) {
                $result .= "📦 {$repo['name']}\n";
                $result .= "   📝 " . ($repo['description'] ?? 'No description') . "\n";
                $result .= "   ⭐ {$repo['stargazers_count']} ⭐ | 🍴 {$repo['forks_count']} 🍴 | 🔀 {$repo['default_branch']}\n";
                $result .= "   🔗 {$repo['html_url']}\n\n";
            }

        } catch (\Exception $e) {
            $result .= "❌ Błąd: " . $e->getMessage() . "\n";
        }

        return $result;
    }

    private function listRepoCommits(string $owner, string $repo, ?string $token = null, int $per_page = 10, int $page = 1): string
    {
        $result = "=== GITHUB COMMITS: {$owner}/{$repo} (Page {$page}) ===\n\n";

        try {
            $per_page = min($per_page, 100); // GitHub limit
            $url = "https://api.github.com/repos/" . urlencode($owner) . "/" . urlencode($repo) . "/commits?per_page={$per_page}&page={$page}";
            $data = $this->makeApiCall($url, $token);

            if (empty($data)) {
                $result .= "ℹ️  Brak commitów\n";
                return $result;
            }

            $result .= "📝 Znaleziono " . count($data) . " commitów:\n\n";

            foreach ($data as $commit) {
                $result .= "🔹 {$commit['sha']}\n";
                $result .= "   👤 {$commit['commit']['author']['name']} ({$commit['commit']['author']['email']})\n";
                $result .= "   📅 " . $commit['commit']['author']['date'] . "\n";
                $result .= "   📝 " . substr($commit['commit']['message'], 0, 100) . "...\n\n";
            }

        } catch (\Exception $e) {
            $result .= "❌ Błąd: " . $e->getMessage() . "\n";
        }

        return $result;
    }

    private function searchRepositories(string $query, ?string $token = null, int $per_page = 10, int $page = 1): string
    {
        $result = "=== GITHUB REPO SEARCH: \"{$query}\" (Page {$page}) ===\n\n";

        try {
            $per_page = min($per_page, 100); // GitHub limit
            $url = "https://api.github.com/search/repositories?q=" . urlencode($query) . "&per_page={$per_page}&page={$page}&sort=stars&order=desc";
            $data = $this->makeApiCall($url, $token);

            if (empty($data['items'])) {
                $result .= "ℹ️  Brak wyników wyszukiwania\n";
                return $result;
            }

            $result .= "🔍 Znaleziono {$data['total_count']} repozytoriów (pokazuję " . count($data['items']) . "):\n\n";

            foreach ($data['items'] as $repo) {
                $result .= "📦 {$repo['full_name']}\n";
                $result .= "   📝 " . ($repo['description'] ?? 'No description') . "\n";
                $result .= "   ⭐ {$repo['stargazers_count']} ⭐ | 🍴 {$repo['forks_count']} 🍴 | 🔀 {$repo['default_branch']}\n";
                $result .= "   🔗 {$repo['html_url']}\n\n";
            }

        } catch (\Exception $e) {
            $result .= "❌ Błąd: " . $e->getMessage() . "\n";
        }

        return $result;
    }

    private function getRepoContents(string $owner, string $repo, string $path = '', ?string $token = null): string
    {
        $result = "=== GITHUB REPO CONTENTS: {$owner}/{$repo}" . ($path ? "/{$path}" : '') . " ===\n\n";

        try {
            $url = "https://api.github.com/repos/" . urlencode($owner) . "/" . urlencode($repo) . "/contents";
            if ($path) {
                $url .= "/" . urlencode($path);
            }

            $data = $this->makeApiCall($url, $token);

            if (isset($data['type']) && !isset($data[0])) {
                // Single file response (GitHub API returns object with 'type' field)
                $item = $data;
                $type = $item['type'] ?? 'file';
                $icon = $type === 'dir' ? '📁' : '📄';

                $result .= "{$icon} {$item['name']}\n";

                if ($type === 'file') {
                    $result .= "   📏 " . ($item['size'] ?? 0) . " bytes\n";
                    if ($item['download_url'] ?? null) {
                        $result .= "   🔗 " . $item['download_url'] . "\n";
                    }
                } elseif ($type === 'dir') {
                    $result .= "   📂 Directory\n";
                }
            } else {
                // Directory listing (array of items)
                $items = is_array($data) && isset($data[0]) ? $data : [];
                foreach ($items as $item) {
                    $type = $item['type'] ?? 'unknown';
                    $icon = $type === 'dir' ? '📁' : '📄';

                    $result .= "{$icon} {$item['name']}\n";

                    if ($type === 'file') {
                        $result .= "   📏 " . ($item['size'] ?? 0) . " bytes\n";
                        if ($item['download_url'] ?? null) {
                            $result .= "   🔗 " . $item['download_url'] . "\n";
                        }
                    } elseif ($type === 'dir') {
                        $result .= "   📂 Directory\n";
                    }

                    $result .= "\n";
                }
            }

        } catch (\Exception $e) {
            $result .= "❌ Błąd: " . $e->getMessage() . "\n";
        }

        return $result;
    }

    public function getName(): string
    {
        return 'github';
    }

    public function getDescription(): string
    {
        return 'Narzędzie do interakcji z GitHub API - pobieranie informacji o użytkownikach, repozytoriach, commitach i wyszukiwanie';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'action' => [
                    'type' => 'string',
                    'description' => 'Akcja do wykonania (info, get_user_info, get_repo_info, list_user_repos, list_repo_commits, search_repositories, get_repo_contents)',
                    'enum' => ['info', 'get_user_info', 'get_repo_info', 'list_user_repos', 'list_repo_commits', 'search_repositories', 'get_repo_contents'],
                    'default' => 'info'
                ],
                'username' => [
                    'type' => 'string',
                    'description' => 'Nazwa użytkownika GitHub (wymagana dla akcji związanych z użytkownikiem)'
                ],
                'owner' => [
                    'type' => 'string',
                    'description' => 'Właściciel repozytorium (wymagany dla akcji związanych z repozytorium)'
                ],
                'repo' => [
                    'type' => 'string',
                    'description' => 'Nazwa repozytorium (wymagana dla akcji związanych z repozytorium)'
                ],
                'token' => [
                    'type' => 'string',
                    'description' => 'Token GitHub API (opcjonalny, zwiększa limit zapytań)'
                ],
                'searchQuery' => [
                    'type' => 'string',
                    'description' => 'Fraza wyszukiwania (wymagana dla akcji search_repositories)'
                ],
                'path' => [
                    'type' => 'string',
                    'description' => 'Ścieżka w repozytorium (opcjonalna dla get_repo_contents)'
                ],
                'per_page' => [
                    'type' => 'integer',
                    'description' => 'Liczba wyników na stronę',
                    'minimum' => 1,
                    'maximum' => 100,
                    'default' => 10
                ],
                'page' => [
                    'type' => 'integer',
                    'description' => 'Numer strony',
                    'minimum' => 1,
                    'default' => 1
                ]
            ],
            'required' => ['action']
        ];
    }

    public function isEnabled(): bool
    {
        return true;
    }
}