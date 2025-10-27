#!/usr/bin/env php
<?php

/**
 * MCP PHP Server Console
 *
 * This script provides command-line interface for managing the application,
 * including database migrations, user management, and system maintenance.
 */

require_once __DIR__ . '/vendor/autoload.php';

use App\Database\MigrationManager;
use App\Services\UserAwareLogger;
use PDO;
use PDOException;

class Console
{
    private PDO $pdo;
    private MigrationManager $migrationManager;
    private UserAwareLogger $logger;

    public function __construct()
    {
        $this->initializeDatabase();
        $this->initializeServices();
    }

    public function run(array $argv): void
    {
        $command = $argv[1] ?? 'help';

        switch ($command) {
            case 'migrate':
                $this->handleMigrate();
                break;

            case 'migrate:rollback':
                $steps = intval($argv[2] ?? 1);
                $this->handleRollback($steps);
                break;

            case 'migrate:status':
                $this->handleStatus();
                break;

            case 'db:info':
                $this->handleDatabaseInfo();
                break;

            case 'user:create-admin':
                $email = $argv[2] ?? null;
                $password = $argv[3] ?? null;
                $name = $argv[4] ?? '';
                $this->handleCreateAdmin($email, $password, $name);
                break;

            case 'user:create-user':
                $email = $argv[2] ?? null;
                $password = $argv[3] ?? null;
                $name = $argv[4] ?? '';
                $this->handleCreateUser($email, $password, $name);
                break;

            case 'user:list':
                $this->handleListUsers();
                break;

            case 'server:start':
                $mode = $argv[2] ?? '2';
                $this->handleStartServer($mode);
                break;

            case 'logs:tail':
                $lines = intval($argv[2] ?? 50);
                $this->handleTailLogs($lines);
                break;

            case 'install':
                $this->handleInstall();
                break;

            case 'help':
            default:
                $this->showHelp();
                break;
        }
    }

    private function initializeDatabase(): void
    {
        try {
            $config = $this->getDatabaseConfig();

            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=utf8mb4',
                $config['host'],
                $config['database']
            );

            $this->pdo = new PDO($dsn, $config['username'], $config['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);

        } catch (PDOException $e) {
            $this->error("Database connection failed: " . $e->getMessage());
            exit(1);
        }
    }

    private function initializeServices(): void
    {
        $this->logger = new UserAwareLogger([
            'file' => __DIR__ . '/logs/console.log',
            'level' => 'info'
        ]);

        $this->migrationManager = new MigrationManager(
            $this->pdo,
            $this->logger
        );
    }

    private function handleMigrate(): void
    {
        $this->info("Running database migrations...");

        $result = $this->migrationManager->migrate();

        if ($result['success']) {
            $this->success("Migrations completed successfully!");

            if (!empty($result['executed'])) {
                $this->info("Executed migrations: " . implode(', ', $result['executed']));
            }

            if (!empty($result['already_run'])) {
                $this->info("Already executed: " . implode(', ', $result['already_run']));
            }

            $this->info("Total migrations: {$result['total']}");
        } else {
            $this->error("Migration failed!");

            if (!empty($result['failed'])) {
                foreach ($result['failed'] as $failure) {
                    $this->error("- {$failure['migration']}: {$failure['error']}");
                }
            }

            if (isset($result['error'])) {
                $this->error("System error: {$result['error']}");
            }

            exit(1);
        }
    }

    private function handleRollback(int $steps): void
    {
        $this->info("Rolling back {$steps} migration(s)...");

        $result = $this->migrationManager->rollback($steps);

        if ($result['success']) {
            $this->success("Rollback completed!");

            if (!empty($result['rolled_back'])) {
                $this->info("Rolled back: " . implode(', ', $result['rolled_back']));
            } else {
                $this->info("No migrations to rollback");
            }
        } else {
            $this->error("Rollback failed!");

            if (!empty($result['failed'])) {
                foreach ($result['failed'] as $failure) {
                    $this->error("- {$failure['migration']}: {$failure['error']}");
                }
            }

            exit(1);
        }
    }

    private function handleStatus(): void
    {
        $this->info("Migration status:");

        try {
            $status = $this->migrationManager->getStatus();

            echo "\n";
            $this->info("Total migrations: {$status['total_migrations']}");
            $this->info("Executed migrations: {$status['executed_migrations']}");
            $this->info("Pending migrations: {$status['pending_migrations']}");

            echo "\nMigration details:\n";
            foreach ($status['migrations'] as $migration) {
                $statusIcon = $migration['executed'] ? '✅' : '⏳';
                $executedAt = $migration['executed_at'] ? " ({$migration['executed_at']})" : '';
                echo "{$statusIcon} {$migration['name']}{$executedAt}\n";
            }

        } catch (Exception $e) {
            $this->error("Failed to get migration status: " . $e->getMessage());
            exit(1);
        }
    }

    private function handleDatabaseInfo(): void
    {
        $this->info("Database information:");

        $info = $this->migrationManager->getDatabaseInfo();

        if ($info['connected']) {
            $this->success("✅ Connected to database");
            $this->info("MySQL version: {$info['version']}");
            $this->info("Current database: {$info['current_database']}");
            $this->info("Available databases: " . implode(', ', array_slice($info['available_databases'], 0, 5)));

            if (count($info['available_databases']) > 5) {
                $this->info("... and " . (count($info['available_databases']) - 5) . " more");
            }
        } else {
            $this->error("❌ Database connection failed");
            $this->error("Error: {$info['error']}");
            exit(1);
        }
    }

    private function handleCreateAdmin(?string $email, ?string $password, string $name): void
    {
        if (!$email || !$password) {
            $this->error("Usage: php console.php user:create-admin <email> <password> [name]");
            exit(1);
        }

        $this->info("Creating admin user: {$email}");

        $result = $this->migrationManager->createAdminUser($email, $password, $name);

        if ($result['success']) {
            $this->success("Admin user created successfully!");
            $this->info("User ID: {$result['user_id']}");
            $this->info("Email: {$email}");
            $this->info("Role: admin");
        } else {
            $this->error("Failed to create admin user: {$result['error']}");
            exit(1);
        }
    }

    private function handleCreateUser(?string $email, ?string $password, string $name): void
    {
        if (!$email || !$password) {
            $this->error("Usage: php console.php user:create-user <email> <password> [name]");
            exit(1);
        }

        $this->info("Creating regular user: {$email}");

        $result = $this->migrationManager->createRegularUser($email, $password, $name);

        if ($result['success']) {
            $this->success("Regular user created successfully!");
            $this->info("User ID: {$result['user_id']}");
            $this->info("Email: {$email}");
            $this->info("Role: user");
        } else {
            $this->error("Failed to create regular user: {$result['error']}");
            exit(1);
        }
    }

    private function handleListUsers(): void
    {
        $this->info("Listing users:");

        try {
            $stmt = $this->pdo->query("
                SELECT id, email, name, role, is_active, created_at, last_login_at
                FROM users
                ORDER BY created_at DESC
            ");

            $users = $stmt->fetchAll();

            if (empty($users)) {
                $this->warning("No users found. Run migrations first: php console.php migrate");
                return;
            }

            echo "\n";
            printf("%-5s %-30s %-20s %-10s %-10s %-20s %-20s\n",
                "ID", "Email", "Name", "Role", "Active", "Created", "Last Login");
            echo str_repeat("-", 125) . "\n";

            foreach ($users as $user) {
                $active = $user['is_active'] ? '✅' : '❌';
                printf("%-5s %-30s %-20s %-10s %-10s %-20s %-20s\n",
                    $user['id'],
                    $user['email'],
                    $user['name'] ?: '-',
                    $user['role'],
                    $active,
                    $user['created_at'],
                    $user['last_login_at'] ?: '-'
                );
            }

        } catch (Exception $e) {
            $this->error("Failed to list users: " . $e->getMessage());
            exit(1);
        }
    }

    private function handleStartServer(string $mode): void
    {
        $this->info("Starting MCP PHP Server in mode {$mode}...");

        $script = __DIR__ . '/start.sh';

        if (!file_exists($script)) {
            $this->error("Start script not found: {$script}");
            exit(1);
        }

        passthru("php {$script} {$mode}", $exitCode);

        if ($exitCode !== 0) {
            $this->error("Server failed to start with exit code: {$exitCode}");
            exit($exitCode);
        }
    }

    private function handleTailLogs(int $lines): void
    {
        $logFile = __DIR__ . '/logs/server-' . date('Y-m-d') . '.log';

        if (!file_exists($logFile)) {
            $this->warning("Log file not found: {$logFile}");
            return;
        }

        $this->info("Showing last {$lines} lines from {$logFile}:");
        echo "\n";

        passthru("tail -n {$lines} {$logFile}");
    }

    private function handleInstall(): void
    {
        $this->info("Starting MCP PHP Server installation...");

        echo "\n";
        $this->info("Step 1: Running database migrations...");
        $this->handleMigrate();

        echo "\n";
        $this->info("Step 2: Creating default admin user...");
        $this->handleCreateAdmin('admin@mcp-server.local', 'admin123', 'System Administrator');

        echo "\n";
        $this->info("Step 3: Creating default regular user...");
        $this->handleCreateUser('user@mcp-server.local', 'user123', 'Regular User');

        echo "\n";
        $this->success("🎉 Installation completed successfully!");
        echo "\n";
        $this->info("Default users created:");
        $this->info("Admin: admin@mcp-server.local / admin123");
        $this->info("User:  user@mcp-server.local / user123");
        echo "\n";
        $this->info("Start the server with: php console.php server:start");
        $this->info("Or use: ./start.sh 2");
    }

    private function showHelp(): void
    {
        echo "
MCP PHP Server Console
======================

Available commands:

Database:
  migrate                 Run all pending migrations
  migrate:rollback [n]    Rollback last n migrations (default: 1)
  migrate:status          Show migration status
  db:info                 Show database connection info

User Management:
  user:create-admin <email> <password> [name]  Create admin user
  user:create-user <email> <password> [name]   Create regular user
  user:list                                       List all users

Server:
  server:start [mode]    Start server (1=CLI, 2=HTTP, 3=Both, 4=Status, 5=Logs)
  logs:tail [n]          Show last n log lines (default: 50)

Utilities:
  install                Run complete installation (migrate + create users)
  help                   Show this help message

Examples:
  php console.php install                          # Full installation
  php console.php migrate                         # Run migrations
  php console.php user:create-admin admin@site.com secret123
  php console.php server:start 2                  # Start HTTP server
  php console.php logs:tail 100                   # Show last 100 log lines

";
    }

    private function getDatabaseConfig(): array
    {
        // Try to get config from environment variables first
        $config = [
            'host' => $_ENV['DB_HOST'] ?? 'localhost',
            'database' => $_ENV['DB_DATABASE'] ?? 'mcp_php_server',
            'username' => $_ENV['DB_USERNAME'] ?? 'root',
            'password' => $_ENV['DB_PASSWORD'] ?? ''
        ];

        // Try to get from config file
        $configFile = __DIR__ . '/config/database.php';
        if (file_exists($configFile)) {
            $fileConfig = require $configFile;
            $config = array_merge($config, $fileConfig);
        }

        return $config;
    }

    private function info(string $message): void
    {
        echo "\033[36m[INFO]\033[0m {$message}\n";
    }

    private function success(string $message): void
    {
        echo "\033[32m[SUCCESS]\033[0m {$message}\n";
    }

    private function warning(string $message): void
    {
        echo "\033[33m[WARNING]\033[0m {$message}\n";
    }

    private function error(string $message): void
    {
        echo "\033[31m[ERROR]\033[0m {$message}\n";
    }
}

// Run the console
$console = new Console();
$console->run($argv);