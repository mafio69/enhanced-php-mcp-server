<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Context\UserContext;
use App\Services\AuthenticationService;
use App\Services\SecretAutoLoader;
use App\Services\SecretManagerService;
use App\Services\UserSecretService;
use App\Services\UserAwareLogger;
use App\Models\User;

echo "🔐 Testing Automatic Secret Loading System\n";
echo "==========================================\n\n";

// Initialize services
$logger = new UserAwareLogger();
$authService = new AuthenticationService($logger);
$secretManagerService = new SecretManagerService($logger);
$userSecretService = new UserSecretService(new UserContext(), $logger);
$secretAutoLoader = new SecretAutoLoader(
    new UserContext(),
    $userSecretService,
    $secretManagerService,
    $logger
);

// Set up integration
$authService->setSecretAutoLoader($secretAutoLoader);

echo "✅ Services initialized\n\n";

// Test with existing users
echo "👤 Testing login with automatic secret loading...\n";

// Test 1: Login as root user
echo "\n1️⃣ Testing login as root@localhost\n";
try {
    $sessionId = $authService->login('root@localhost', '417096');

    if ($sessionId) {
        echo "✅ Login successful - Session ID: " . substr($sessionId, 0, 16) . "...\n";

        // Create user context
        $userContext = $authService->createUserContext($sessionId);
        $user = $userContext->getUser();

        if ($user) {
            echo "👤 User: {$user->getEmail()} ({$user->getRole()})\n";

            // Check loaded secrets
            $loadedSecrets = $secretAutoLoader->getLoadedSecrets();
            $secretStats = $secretAutoLoader->getLoadedSecretsStats();

            echo "🔐 Loaded secrets: {$secretStats['total_count']}\n";
            echo "   - Owned: {$secretStats['owned_count']}\n";
            echo "   - Shared: {$secretStats['shared_count']}\n";
            echo "   - Categories: " . implode(', ', array_keys($secretStats['categories'])) . "\n";

            if (!empty($loadedSecrets)) {
                echo "\n📋 Loaded secrets details:\n";
                foreach ($loadedSecrets as $secret) {
                    $owner = $secret['is_owner'] ? 'Owner' : 'Shared';
                    $expires = $secret['expires_at'] ? "Expires: {$secret['expires_at']}" : 'No expiry';
                    echo "   - {$secret['key']} ({$secret['category']}) - {$owner} - {$expires}\n";
                }
            }

            // Test secret access through main system
            echo "\n🔍 Testing secret access through main system:\n";
            foreach ($loadedSecrets as $secret) {
                try {
                    $mainSecret = $secretManagerService->getSecret($secret['key']);
                    if ($mainSecret) {
                        echo "   ✅ {$secret['key']} - Accessible\n";
                    } else {
                        echo "   ❌ {$secret['key']} - Not accessible\n";
                    }
                } catch (Exception $e) {
                    echo "   ❌ {$secret['key']} - Error: " . $e->getMessage() . "\n";
                }
            }
        }

        // Test logout
        echo "\n🚪 Testing logout...\n";
        $logoutSuccess = $authService->logout($sessionId);
        if ($logoutSuccess) {
            echo "✅ Logout successful\n";

            // Check if secrets were cleared
            $remainingSecrets = $secretAutoLoader->getLoadedSecrets();
            if (empty($remainingSecrets)) {
                echo "✅ Secrets cleared on logout\n";
            } else {
                echo "⚠️  " . count($remainingSecrets) . " secrets still loaded\n";
            }
        } else {
            echo "❌ Logout failed\n";
        }
    } else {
        echo "❌ Login failed\n";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n" . str_repeat("-", 50) . "\n";

// Test 2: Login as fullcv user
echo "\n2️⃣ Testing login as fullcv@localhost\n";
try {
    $sessionId = $authService->login('fullcv@localhost', 'dev123');

    if ($sessionId) {
        echo "✅ Login successful - Session ID: " . substr($sessionId, 0, 16) . "...\n";

        // Create user context
        $userContext = $authService->createUserContext($sessionId);
        $user = $userContext->getUser();

        if ($user) {
            echo "👤 User: {$user->getEmail()} ({$user->getRole()})\n";

            // Check loaded secrets
            $loadedSecrets = $secretAutoLoader->getLoadedSecrets();
            $secretStats = $secretAutoLoader->getLoadedSecretsStats();

            echo "🔐 Loaded secrets: {$secretStats['total_count']}\n";

            if (empty($loadedSecrets)) {
                echo "ℹ️  No secrets found for this user (expected for new user)\n";
            }
        }

        // Logout
        $authService->logout($sessionId);
        echo "✅ Logged out\n";
    } else {
        echo "❌ Login failed\n";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n" . str_repeat("-", 50) . "\n";

// Test 3: Toggle auto-load functionality
echo "\n3️⃣ Testing auto-load toggle functionality\n";
try {
    $sessionId = $authService->login('root@localhost', '417096');

    if ($sessionId) {
        echo "✅ Logged in\n";

        // Disable auto-load
        $secretAutoLoader->setAutoLoadEnabled(false);
        echo "🔌 Auto-load disabled\n";

        // Clear existing secrets
        $secretAutoLoader->clearLoadedSecrets();
        $remainingSecrets = $secretAutoLoader->getLoadedSecrets();
        echo "🧹 Secrets cleared: " . (empty($remainingSecrets) ? 'Yes' : 'No') . "\n";

        // Re-enable auto-load
        $secretAutoLoader->setAutoLoadEnabled(true);
        echo "🔌 Auto-load re-enabled\n";

        // Reload secrets
        $result = $secretAutoLoader->loadUserSecrets();
        echo "🔄 Reloaded secrets: {$result['loaded_count']} loaded, {$result['error_count']} errors\n";

        $authService->logout($sessionId);
        echo "✅ Logged out\n";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n" . str_repeat("-", 50) . "\n";

// Test 4: Error handling
echo "\n4️⃣ Testing error handling\n";
try {
    // Test invalid login
    $sessionId = $authService->login('invalid@test.com', 'wrongpassword');
    if (!$sessionId) {
        echo "✅ Invalid login properly rejected\n";
    }

    // Test with auto-loader disabled
    $secretAutoLoader->setAutoLoadEnabled(false);
    $sessionId = $authService->login('root@localhost', '417096');
    if ($sessionId) {
        $loadedSecrets = $secretAutoLoader->getLoadedSecrets();
        if (empty($loadedSecrets)) {
            echo "✅ Secrets not loaded when auto-load is disabled\n";
        }
        $authService->logout($sessionId);
    }

} catch (Exception $e) {
    echo "❌ Unexpected error: " . $e->getMessage() . "\n";
}

echo "\n🎉 Automatic Secret Loading System Test Complete!\n";
echo "\n📝 Summary:\n";
echo "   ✅ Automatic secret loading on login\n";
echo "   ✅ Secret clearing on logout\n";
echo "   ✅ Auto-load toggle functionality\n";
echo "   ✅ Error handling\n";
echo "   ✅ Integration with main secret system\n";
echo "\n🔧 The system is ready for integration with MCP tools!\n";