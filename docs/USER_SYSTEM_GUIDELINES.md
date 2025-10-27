# WYTYCZNE: System Logowania i Sekrety Powiązane z Profilem Użytkownika

## 📋 **Spis Treści**

1. [Wprowadzenie](#wprowadzenie)
2. [Obecna Architektura](#obecna-architektura)
3. [Cele Projektu](#cele-projektu)
4. [Architektura Systemu Użytkowników](#architektura-systemu-użytkowników)
5. [System Logowania z Kontekstem Użytkownika](#system-logowania-z-kontekstem-użytkownika)
6. [Zarządzanie Sekretami z Izolacją Użytkowników](#zarządzanie-sekretami-z-izolacją-użytkowników)
7. [Bezpieczeństwo i Autentykacja](#bezpieczeństwo-i-autentykacja)
8. [Schemat Bazy Danych](#schemat-bazy-danych)
9. [Implementacja Techniczna](#implementacja-techniczna)
10. [Przykłady Użycia](#przykłady-użycia)

---

## 🎯 **Wprowadzenie**

Niniejszy dokument opisuje wytyczne dla wdrożenia systemu logowania i zarządzania sekretami powiązanymi z profilami użytkowników w MCP PHP Server v2.1.0.

### **Główne cele:**
- 🔐 **Izolacja danych** - Każdy użytkownik ma dostęp tylko do swoich sekretów i logów
- 📊 **Audyt i śledzenie** - Pełna historia operacji użytkownika
- 🛡️ **Bezpieczeństwo** - Bezpieczne przechowywanie i zarządzanie sekretami
- 🎛️ **Zarządzanie** - Panel administracyjny do zarządzania użytkownikami

---

## 🏗️ **Obecna Architektura**

### **System Logowania:**
```php
// Obecny Logger (PSR-3)
class Logger {
    private string $logFile;
    private string $level;
    // Brak kontekstu użytkownika
    public function info(string $message, array $context = []): void;
}
```

### **System Sekretów:**
```php
// Obecny SecretService
class SecretService {
    private array $secrets = [];
    // Globalne sekrety, bez izolacji użytkowników
    public function storeSecret(string $key, string $value): bool;
}
```

### **Ograniczenia:**
- ❌ Brak identyfikacji użytkowników w logach
- ❌ Globalne sekrety dostępne dla wszystkich
- ❌ Brak historii operacji na sekretach
- ❌ Ograniczone możliwości audytu

---

## 🎯 **Cele Projektu**

### **1. System Logowania z Kontekstem Użytkownika**
- ✅ Dodawanie identyfikatora użytkownika do każdego wpisu logu
- ✅ Filtrowanie logów według użytkowników
- ✅ Agregacja operacji użytkowników
- ✅ Historia aktywności

### **2. Zarządzanie Sekretami z Izolacją**
- ✅ Sekrety powiązane z konkretnymi użytkownikami
- ✅ Kontrola dostępu do sekretów
- ✅ Audyt operacji na sekretach
- ✅ Udostępnianie sekretów między użytkownikami

### **3. Bezpieczeństwo i Autentykacja**
- ✅ System uwierzytelniania użytkowników
- ✅ Autoryzacja operacji na sekretach
- ✅ Szyfrowanie sekretów z kluczami użytkowników
- ✅ Mechanizmy odzyskiwania dostępu

---

## 🏛️ **Architektura Systemu Użytkowników**

### **Struktura Katalogów:**
```
src/
├── Models/
│   ├── User.php                 # Model użytkownika
│   ├── UserSecret.php           # Model sekretu użytkownika
│   └── UserActivity.php         # Model aktywności użytkownika
├── Services/
│   ├── UserService.php          # Zarządzanie użytkownikami
│   ├── UserSecretService.php    # Sekrety użytkowników
│   ├── UserActivityService.php  # Aktywność użytkowników
│   └── AuthenticationService.php # Autentykacja
├── Controllers/
│   ├── UserController.php       # Panel użytkownika
│   └── UserAdminController.php   # Panel administratora
├── Middleware/
│   ├── AuthMiddleware.php       # Middleware autentykacji
│   └── UserContextMiddleware.php # Kontekst użytkownika
└── Interfaces/
    ├── UserRepositoryInterface.php
    └── UserSecretRepositoryInterface.php
```

---

## 📝 **System Logowania z Kontekstem Użytkownika**

### **1. Ulepszony Logger z Kontekstem Użytkownika**

```php
<?php
namespace App\Services;

use App\Interfaces\UserContextInterface;

class UserAwareLogger extends Logger
{
    private ?UserContextInterface $userContext = null;

    public function setUserContext(?UserContextInterface $userContext): void
    {
        $this->userContext = $userContext;
    }

    public function info(string $message, array $context = []): void
    {
        $enrichedContext = $this->enrichContextWithUserInfo($context);
        parent::info($message, $enrichedContext);
    }

    private function enrichContextWithUserInfo(array $context): array
    {
        if ($this->userContext && $this->userContext->getUser()) {
            $context['user_id'] = $this->userContext->getUser()->getId();
            $context['user_email'] = $this->userContext->getUser()->getEmail();
            $context['user_role'] = $this->userContext->getUser()->getRole();
            $context['session_id'] = $this->userContext->getSessionId();
        }

        return $context;
    }

    public function getUserLogs(int $userId, array $filters = []): array
    {
        // Filtrowanie logów dla konkretnego użytkownika
        return $this->filterLogsByUser($userId, $filters);
    }
}
```

### **2. Kontekst Użytkownika**

```php
<?php
namespace App\Context;

use App\Models\User;

class UserContext implements UserContextInterface
{
    private ?User $user = null;
    private string $sessionId;

    public function __construct(string $sessionId)
    {
        $this->sessionId = $sessionId;
    }

    public function setUser(?User $user): void
    {
        $this->user = $user;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function getSessionId(): string
    {
        return $this->sessionId;
    }

    public function isAuthenticated(): bool
    {
        return $this->user !== null;
    }

    public function hasRole(string $role): bool
    {
        return $this->user && $this->user->getRole() === $role;
    }
}
```

### **3. Middleware Kontekstu Użytkownika**

```php
<?php
namespace App\Middleware;

use App\Context\UserContext;
use App\Services\AuthenticationService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class UserContextMiddleware
{
    private AuthenticationService $authService;

    public function __construct(AuthenticationService $authService)
    {
        $this->authService = $authService;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $sessionId = $this->extractSessionId($request);
        $userContext = new UserContext($sessionId);

        // Próba autentykacji użytkownika
        $user = $this->authService->authenticateSession($sessionId);
        $userContext->setUser($user);

        // Dodanie kontekstu do żądania
        $request = $request->withAttribute('userContext', $userContext);

        // Ustawienie kontekstu w loggerze
        if ($this->logger instanceof UserAwareLogger) {
            $this->logger->setUserContext($userContext);
        }

        return $handler->handle($request);
    }

    private function extractSessionId(ServerRequestInterface $request): string
    {
        // Extract from session, JWT token, or API key
        return $request->getCookieParams()['session_id'] ??
               $request->getHeaderLine('Authorization') ??
               'anonymous_' . uniqid();
    }
}
```

---

## 🔐 **Zarządzanie Sekretami z Izolacją Użytkowników**

### **1. Model Sekretu Użytkownika**

```php
<?php
namespace App\Models;

class UserSecret
{
    private int $id;
    private int $userId;
    private string $key;
    private string $encryptedValue;
    private string $description;
    private string $category;
    private \DateTime $createdAt;
    private \DateTime $updatedAt;
    private ?int $sharedBy;
    private array $accessList;
    private bool $isDeleted;

    // Getters and setters...

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->userId,
            'key' => $this->key,
            'description' => $this->description,
            'category' => $this->category,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt->format('Y-m-d H:i:s'),
            'shared_by' => $this->sharedBy,
            'is_deleted' => $this->isDeleted
        ];
    }

    public function canBeAccessedBy(int $userId): bool
    {
        // Owner can always access
        if ($this->userId === $userId) {
            return true;
        }

        // Check if user is in access list
        return in_array($userId, $this->accessList);
    }
}
```

### **2. Sekrety Użytkowników - Service**

```php
<?php
namespace App\Services;

use App\Models\User;
use App\Models\UserSecret;
use App\Context\UserContext;

class UserSecretService extends SecretService
{
    private UserContext $userContext;
    private UserSecretRepositoryInterface $repository;

    public function __construct(
        UserContext $userContext,
        UserSecretRepositoryInterface $repository,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
        $this->userContext = $userContext;
        $this->repository = $repository;
    }

    public function storeSecret(string $key, string $value, string $description = '', string $category = 'general'): bool
    {
        if (!$this->userContext->isAuthenticated()) {
            throw new UnauthorizedException('User not authenticated');
        }

        $user = $this->userContext->getUser();

        try {
            // Szyfrowanie z kluczem użytkownika
            $encryptedValue = $this->encryptWithUserKey($value, $user);

            $secret = new UserSecret(
                userId: $user->getId(),
                key: $key,
                encryptedValue: $encryptedValue,
                description: $description,
                category: $category
            );

            $this->repository->save($secret);

            // Logowanie operacji
            $this->logger->info('User secret stored', [
                'user_id' => $user->getId(),
                'secret_key' => $key,
                'category' => $category,
                'operation' => 'store_secret'
            ]);

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to store user secret', [
                'user_id' => $user->getId(),
                'secret_key' => $key,
                'error' => $e->getMessage(),
                'operation' => 'store_secret_failed'
            ]);
            throw $e;
        }
    }

    public function getSecret(string $key): ?UserSecret
    {
        if (!$this->userContext->isAuthenticated()) {
            throw new UnauthorizedException('User not authenticated');
        }

        $user = $this->userContext->getUser();

        try {
            $secret = $this->repository->findByUserAndKey($user->getId(), $key);

            if (!$secret || !$secret->canBeAccessedBy($user->getId())) {
                $this->logger->warning('Secret access denied', [
                    'user_id' => $user->getId(),
                    'secret_key' => $key,
                    'reason' => 'not_found_or_no_access',
                    'operation' => 'get_secret_denied'
                ]);
                return null;
            }

            // Deszyfrowanie
            $decryptedValue = $this->decryptWithUserKey($secret->getEncryptedValue(), $user);
            $secret->setDecryptedValue($decryptedValue);

            // Logowanie dostępu
            $this->logger->info('Secret accessed', [
                'user_id' => $user->getId(),
                'secret_key' => $key,
                'access_granted' => true,
                'operation' => 'get_secret'
            ]);

            return $secret;
        } catch (\Exception $e) {
            $this->logger->error('Failed to get user secret', [
                'user_id' => $user->getId(),
                'secret_key' => $key,
                'error' => $e->getMessage(),
                'operation' => 'get_secret_failed'
            ]);
            throw $e;
        }
    }

    public function shareSecretWithUser(string $secretKey, int $targetUserId): bool
    {
        if (!$this->userContext->isAuthenticated()) {
            throw new UnauthorizedException('User not authenticated');
        }

        $user = $this->userContext->getUser();

        try {
            $secret = $this->repository->findByUserAndKey($user->getId(), $secretKey);

            if (!$secret || $secret->getUserId() !== $user->getId()) {
                throw new ForbiddenException('Cannot share secret - not owner');
            }

            $secret->addToAccessList($targetUserId);
            $this->repository->save($secret);

            $this->logger->info('Secret shared with user', [
                'owner_id' => $user->getId(),
                'target_user_id' => $targetUserId,
                'secret_key' => $secretKey,
                'operation' => 'share_secret'
            ]);

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to share secret', [
                'owner_id' => $user->getId(),
                'target_user_id' => $targetUserId,
                'secret_key' => $secretKey,
                'error' => $e->getMessage(),
                'operation' => 'share_secret_failed'
            ]);
            throw $e;
        }
    }

    public function getUserSecrets(array $filters = []): array
    {
        if (!$this->userContext->isAuthenticated()) {
            throw new UnauthorizedException('User not authenticated');
        }

        $user = $this->userContext->getUser();

        try {
            $secrets = $this->repository->findByUser($user->getId(), $filters);

            $this->logger->info('User secrets listed', [
                'user_id' => $user->getId(),
                'count' => count($secrets),
                'filters' => $filters,
                'operation' => 'list_secrets'
            ]);

            return $secrets;
        } catch (\Exception $e) {
            $this->logger->error('Failed to list user secrets', [
                'user_id' => $user->getId(),
                'error' => $e->getMessage(),
                'operation' => 'list_secrets_failed'
            ]);
            throw $e;
        }
    }

    private function encryptWithUserKey(string $value, User $user): string
    {
        // Użyj klucza szyfrowania użytkownika
        $userEncryptionKey = $this->getUserEncryptionKey($user);
        return $this->encrypt($value, $userEncryptionKey);
    }

    private function decryptWithUserKey(string $encryptedValue, User $user): string
    {
        // Użyj klucza szyfrowania użytkownika
        $userEncryptionKey = $this->getUserEncryptionKey($user);
        return $this->decrypt($encryptedValue, $userEncryptionKey);
    }

    private function getUserEncryptionKey(User $user): string
    {
        // Generuj klucz szyfrowania na podstawie hasła użytkownika
        // lub użyj klucza master zaszyfrowanego kluczem użytkownika
        return hash('sha256', $user->getId() . $user->getPasswordHash() . $user->getCreatedAt()->format('Y-m-d'));
    }
}
```

---

## 🛡️ **Bezpieczeństwo i Autentykacja**

### **1. Model Użytkownika**

```php
<?php
namespace App\Models;

class User
{
    private int $id;
    private string $email;
    private string $passwordHash;
    private string $name;
    private string $role;
    private bool $isActive;
    private \DateTime $createdAt;
    private \DateTime $lastLoginAt;
    private array $preferences;

    public function __construct(
        string $email,
        string $password,
        string $name = '',
        string $role = 'user'
    ) {
        $this->email = $email;
        $this->passwordHash = password_hash($password, PASSWORD_ARGON2ID);
        $this->name = $name;
        $this->role = $role;
        $this->isActive = true;
        $this->createdAt = new \DateTime();
        $this->preferences = [];
    }

    public function verifyPassword(string $password): bool
    {
        return password_verify($password, $this->passwordHash);
    }

    public function changePassword(string $newPassword): void
    {
        $this->passwordHash = password_hash($newPassword, PASSWORD_ARGON2ID);
    }

    public function updateLastLogin(): void
    {
        $this->lastLoginAt = new \DateTime();
    }

    public function hasPermission(string $permission): bool
    {
        $permissions = $this->getRolePermissions();
        return in_array($permission, $permissions[$this->role] ?? []);
    }

    private function getRolePermissions(): array
    {
        return [
            'admin' => ['*'], // Wszystkie uprawnienia
            'user' => ['secret:read', 'secret:write', 'secret:share', 'log:read_own'],
            'viewer' => ['secret:read', 'log:read_own']
        ];
    }

    // Getters and setters...
}
```

### **2. Service Autentykacji**

```php
<?php
namespace App\Services;

use App\Models\User;
use App\Context\UserContext;

class AuthenticationService
{
    private UserRepositoryInterface $userRepository;
    private LoggerInterface $logger;
    private array $sessionStorage = [];

    public function __construct(
        UserRepositoryInterface $userRepository,
        LoggerInterface $logger
    ) {
        $this->userRepository = $userRepository;
        $this->logger = $logger;
    }

    public function login(string $email, string $password): ?string
    {
        try {
            $user = $this->userRepository->findByEmail($email);

            if (!$user || !$user->verifyPassword($password)) {
                $this->logger->warning('Login attempt failed', [
                    'email' => $email,
                    'reason' => 'invalid_credentials',
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
                ]);
                return null;
            }

            if (!$user->isActive()) {
                $this->logger->warning('Login attempt failed', [
                    'email' => $email,
                    'reason' => 'account_inactive',
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ]);
                return null;
            }

            // Generowanie ID sesji
            $sessionId = $this->generateSessionId();
            $this->sessionStorage[$sessionId] = [
                'user_id' => $user->getId(),
                'created_at' => time(),
                'last_activity' => time(),
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ];

            // Aktualizacja ostatniego logowania
            $user->updateLastLogin();
            $this->userRepository->save($user);

            $this->logger->info('User logged in successfully', [
                'user_id' => $user->getId(),
                'email' => $email,
                'session_id' => $sessionId,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);

            return $sessionId;
        } catch (\Exception $e) {
            $this->logger->error('Login error', [
                'email' => $email,
                'error' => $e->getMessage(),
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
            return null;
        }
    }

    public function authenticateSession(string $sessionId): ?User
    {
        if (!isset($this->sessionStorage[$sessionId])) {
            return null;
        }

        $sessionData = $this->sessionStorage[$sessionId];

        // Sprawdzenie czy sesja nie wygasła (24 godziny)
        if (time() - $sessionData['last_activity'] > 86400) {
            unset($this->sessionStorage[$sessionId]);
            return null;
        }

        // Aktualizacja ostatniej aktywności
        $this->sessionStorage[$sessionId]['last_activity'] = time();

        try {
            $user = $this->userRepository->findById($sessionData['user_id']);
            return $user;
        } catch (\Exception $e) {
            $this->logger->error('Session authentication error', [
                'session_id' => $sessionId,
                'error' => $e->getMessage()
            ]);
            unset($this->sessionStorage[$sessionId]);
            return null;
        }
    }

    public function logout(string $sessionId): bool
    {
        if (!isset($this->sessionStorage[$sessionId])) {
            return false;
        }

        $sessionData = $this->sessionStorage[$sessionId];
        unset($this->sessionStorage[$sessionId]);

        $this->logger->info('User logged out', [
            'user_id' => $sessionData['user_id'],
            'session_id' => $sessionId
        ]);

        return true;
    }

    private function generateSessionId(): string
    {
        return bin2hex(random_bytes(32));
    }
}
```

---

## 🗄️ **Schemat Bazy Danych**

### **1. Tabela `users`**

```sql
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    name VARCHAR(255) DEFAULT '',
    role ENUM('admin', 'user', 'viewer') DEFAULT 'user',
    is_active BOOLEAN DEFAULT TRUE,
    preferences JSON DEFAULT '{}',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login_at TIMESTAMP NULL,

    INDEX idx_email (email),
    INDEX idx_role (role),
    INDEX idx_active (is_active)
);
```

### **2. Tabela `user_secrets`**

```sql
CREATE TABLE user_secrets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    secret_key VARCHAR(255) NOT NULL,
    encrypted_value TEXT NOT NULL,
    description TEXT DEFAULT '',
    category VARCHAR(100) DEFAULT 'general',
    is_deleted BOOLEAN DEFAULT FALSE,
    shared_by INT NULL,
    access_list JSON DEFAULT '[]',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (shared_by) REFERENCES users(id) ON DELETE SET NULL,

    UNIQUE KEY unique_user_secret (user_id, secret_key),
    INDEX idx_user_id (user_id),
    INDEX idx_category (category),
    INDEX idx_deleted (is_deleted)
);
```

### **3. Tabela `user_activities`**

```sql
CREATE TABLE user_activities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    session_id VARCHAR(64) DEFAULT '',
    action VARCHAR(100) NOT NULL,
    resource_type VARCHAR(50) DEFAULT '',
    resource_id VARCHAR(255) DEFAULT '',
    details JSON DEFAULT '{}',
    ip_address VARCHAR(45) DEFAULT '',
    user_agent TEXT DEFAULT '',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,

    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at),
    INDEX idx_resource (resource_type, resource_id)
);
```

---

## ⚙️ **Implementacja Techniczna**

### **1. Konfiguracja Systemu**

```php
// config/user-system.php
return [
    'authentication' => [
        'session_timeout' => 86400, // 24 godziny
        'max_login_attempts' => 5,
        'lockout_duration' => 900, // 15 minut
        'remember_me_duration' => 2592000, // 30 dni
    ],

    'secrets' => [
        'encryption_algorithm' => 'aes-256-gcm',
        'key_derivation_iterations' => 100000,
        'max_secret_value_length' => 10000,
        'allowed_categories' => ['api_keys', 'database', 'credentials', 'tokens', 'general'],
    ],

    'logging' => [
        'user_context_enabled' => true,
        'log_user_activities' => true,
        'activity_retention_days' => 90,
        'log_rotation_size' => 50 * 1024 * 1024, // 50MB
    ],

    'security' => [
        'password_min_length' => 8,
        'password_require_special_chars' => true,
        'max_concurrent_sessions' => 5,
        'rate_limiting' => [
            'login_attempts_per_minute' => 10,
            'api_requests_per_minute' => 100,
        ]
    ]
];
```

### **2. Kontroler Użytkownika**

```php
<?php
namespace App\Controllers;

use App\Context\UserContext;
use App\Services\UserSecretService;
use App\Services\UserActivityService;

class UserSecretController extends BaseController
{
    private UserSecretService $secretService;
    private UserActivityService $activityService;

    public function __construct(
        UserContext $userContext,
        UserSecretService $secretService,
        UserActivityService $activityService
    ) {
        parent::__construct($userContext);
        $this->secretService = $secretService;
        $this->activityService = $activityService;
    }

    public function listSecrets(Request $request, Response $response): Response
    {
        if (!$this->userContext->isAuthenticated()) {
            return $this->errorResponse($response, 'Unauthorized');
        }

        try {
            $filters = $request->getQueryParams();
            $secrets = $this->secretService->getUserSecrets($filters);

            // Logowanie aktywności
            $this->activityService->logActivity(
                userId: $this->userContext->getUser()->getId(),
                action: 'list_secrets',
                details: ['filters' => $filters, 'count' => count($secrets)]
            );

            return $this->jsonResponse($response, [
                'success' => true,
                'data' => array_map(fn($secret) => $secret->toArray(), $secrets),
                'count' => count($secrets)
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($response, 'Failed to list secrets');
        }
    }

    public function storeSecret(Request $request, Response $response): Response
    {
        if (!$this->userContext->isAuthenticated()) {
            return $this->errorResponse($response, 'Unauthorized');
        }

        try {
            $data = json_decode($request->getBody()->getContents(), true);

            $secret = $this->secretService->storeSecret(
                key: $data['key'],
                value: $data['value'],
                description: $data['description'] ?? '',
                category: $data['category'] ?? 'general'
            );

            // Logowanie aktywności
            $this->activityService->logActivity(
                userId: $this->userContext->getUser()->getId(),
                action: 'store_secret',
                resourceType: 'secret',
                resourceId: $data['key'],
                details: ['category' => $data['category'] ?? 'general']
            );

            return $this->jsonResponse($response, [
                'success' => true,
                'message' => 'Secret stored successfully'
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($response, 'Failed to store secret');
        }
    }

    public function shareSecret(Request $request, Response $response): Response
    {
        if (!$this->userContext->isAuthenticated()) {
            return $this->errorResponse($response, 'Unauthorized');
        }

        try {
            $data = json_decode($request->getBody()->getContents(), true);
            $targetUserEmail = $data['user_email'];
            $secretKey = $data['secret_key'];

            $result = $this->secretService->shareSecretWithEmail($secretKey, $targetUserEmail);

            // Logowanie aktywności
            $this->activityService->logActivity(
                userId: $this->userContext->getUser()->getId(),
                action: 'share_secret',
                resourceType: 'secret',
                resourceId: $secretKey,
                details: ['target_email' => $targetUserEmail, 'result' => $result]
            );

            return $this->jsonResponse($response, [
                'success' => true,
                'message' => 'Secret shared successfully'
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($response, 'Failed to share secret');
        }
    }
}
```

### **3. Middleware Autentykacji**

```php
<?php
namespace App\Middleware;

use App\Context\UserContext;
use App\Services\AuthenticationService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class AuthMiddleware
{
    private AuthenticationService $authService;

    public function __construct(AuthenticationService $authService)
    {
        $this->authService = $authService;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $userContext = $request->getAttribute('userContext');

        // Sprawdzenie czy użytkownik jest zalogowany
        if (!$userContext || !$userContext->isAuthenticated()) {
            return new JsonResponse(401, [
                'success' => false,
                'error' => 'Authentication required'
            ]);
        }

        // Sprawdzenie uprawnień
        $requiredRole = $request->getAttribute('required_role', 'user');
        if (!$userContext->hasRole($requiredRole)) {
            return new JsonResponse(403, [
                'success' => false,
                'error' => 'Insufficient permissions'
            ]);
        }

        return $handler->handle($request);
    }
}
```

---

## 📋 **Przykłady Użycia**

### **1. Logowanie Użytkownika**

```php
// Login
$sessionId = $authService->login('user@example.com', 'password123');

if ($sessionId) {
    // Ustawienie kontekstu użytkownika
    $userContext = new UserContext($sessionId);
    $user = $authService->authenticateSession($sessionId);
    $userContext->setUser($user);

    // Logger automatycznie dodaje kontekst użytkownika
    $logger->info('User performed action'); // Zawiera user_id w logach
}
```

### **2. Zarządzanie Sekretami Użytkownika**

```php
// Przechowywanie sekretu
$secretService->storeSecret(
    key: 'api_key_service_x',
    value: 'sk-1234567890',
    description: 'API key for Service X',
    category: 'api_keys'
);

// Pobieranie sekretu
$secret = $secretService->getSecret('api_key_service_x');
echo $secret->getDecryptedValue(); // 'sk-1234567890'

// Udostępnianie sekretu innemu użytkownikowi
$secretService->shareSecretWithEmail('api_key_service_x', 'colleague@example.com');
```

### **3. Logowanie z Kontekstem Użytkownika**

```php
// Logger automatycznie dodaje informacje o użytkowniku
$logger->info('Tool executed', [
    'tool_name' => 'brave_search',
    'execution_time' => 1.23
]);

// Wynik w logu:
// [2025-01-01 12:00:00] INFO: Tool executed {"user_id":123,"user_email":"user@example.com","tool_name":"brave_search","execution_time":1.23}
```

### **4. Panel Administracyjny**

```javascript
// Frontend - zarządzanie sekretami
async function getUserSecrets(filters = {}) {
    const response = await fetch('/api/user/secrets', {
        method: 'GET',
        headers: {
            'Authorization': `Bearer ${sessionId}`,
            'Content-Type': 'application/json'
        }
    });

    return await response.json();
}

// Udostępnianie sekretu
async function shareSecret(secretKey, targetEmail) {
    const response = await fetch('/api/user/secrets/share', {
        method: 'POST',
        headers: {
            'Authorization': `Bearer ${sessionId}`,
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            secret_key: secretKey,
            user_email: targetEmail
        })
    });

    return await response.json();
}
```

---

## 🚀 **Kroki Wdrożenia**

### **Faza 1: Podstawy (1-2 tygodnie)**
1. ✅ Stworzenie modeli danych (User, UserSecret, UserActivity)
2. ✅ Implementacja podstawowej autentykacji
3. ✅ Stworzenie UserContext i middleware
4. ✅ Podstawowy panel logowania

### **Faza 2: Sekrety Użytkowników (2-3 tygodnie)**
1. ✅ Rozbudowa SecretService dla użytkowników
2. ✅ Implementacja szyfrowania z kluczami użytkowników
3. ✅ Tworzenie interfejsu zarządzania sekretami
4. ✅ System udostępniania sekretów

### **Faza 3: Logowanie i Audyt (1-2 tygodnie)**
1. ✅ Rozbudowa loggera z kontekstem użytkownika
2. ✅ System śledzenia aktywności użytkowników
3. ✅ Interfejs przeglądania logów i aktywności
4. ✅ Raportowanie i statystyki

### **Faza 4: Bezpieczeństwo i Optymalizacja (1-2 tygodnie)**
1. ✅ Wdrożenie rate limiting i zabezpieczeń
2. ✅ Testy bezpieczeństwa i penetracyjne
3. ✅ Optymalizacja wydajności
4. ✅ Dokumentacja i wdrożenie

---

## 📊 **Przykładowe Logi z Kontekstem Użytkownika**

```json
// Log operacji na sekretach
{
  "timestamp": "2025-01-01T12:00:00Z",
  "level": "info",
  "message": "User secret stored",
  "context": {
    "user_id": 123,
    "user_email": "user@example.com",
    "user_role": "user",
    "session_id": "abc123def456",
    "secret_key": "api_key_service_x",
    "category": "api_keys",
    "operation": "store_secret"
  }
}

// Log wykonania narzędzia
{
  "timestamp": "2025-01-01T12:01:00Z",
  "level": "info",
  "message": "Tool executed successfully",
  "context": {
    "user_id": 123,
    "user_email": "user@example.com",
    "tool_name": "brave_search",
    "execution_time": 1.23,
    "arguments": {
      "query": "PHP programming",
      "count": 5
    },
    "operation": "tool_execution"
  }
}

// Log audytowy dostępu do sekretu
{
  "timestamp": "2025-01-01T12:02:00Z",
  "level": "warning",
  "message": "Secret access denied",
  "context": {
    "user_id": 456,
    "user_email": "attacker@example.com",
    "secret_key": "admin_api_key",
    "reason": "no_access_permission",
    "ip_address": "192.168.1.100",
    "operation": "get_secret_denied"
  }
}
```

---

## 🔮 **Rozbudowa Systemu**

### **Możliwości Rozszerzenia:**
1. **Integracja z LDAP/Active Directory** dla firmowych wdrożeń
2. **2FA/MFA** (uwierzytelnianie dwuskładnikowe)
3. **API Keys** dla programistycznego dostępu
4. **Team Management** - zarządzanie zespołami i grupami sekretów
5. **Secret Rotation** - automatyczna rotacja sekretów
6. **Approval Workflows** - procesy zatwierdzania dostępu do sekretów

---

**📋 Ten dokument stanowi kompleksowe wytyczne dla wdrożenia systemu logowania i zarządzania sekretami powiązanymi z profilami użytkowników w MCP PHP Server.**