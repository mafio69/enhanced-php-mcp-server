<?php

namespace App\Database;

use App\Services\UserAwareLogger;
use Exception;
use PDO;
use PDOException;

class MigrationManager
{
    private PDO $pdo;
    private UserAwareLogger $logger;
    private string $migrationsPath;

    public function __construct(PDO $pdo, UserAwareLogger $logger, string $migrationsPath = null)
    {
        $this->pdo = $pdo;
        $this->logger = $logger;
        $this->migrationsPath = $migrationsPath ?: __DIR__ . '/../../migrations';
    }

    public function migrate(): array
    {
        $results = [
            'success' => true,
            'executed' => [],
            'failed' => [],
            'already_run' => [],
            'total' => 0
        ];

        try {
            // Create migrations table if it doesn't exist
            $this->createMigrationsTable();

            // Get all migration files
            $migrationFiles = $this->getMigrationFiles();
            $results['total'] = count($migrationFiles);

            // Get already executed migrations
            $executedMigrations = $this->getExecutedMigrations();

            foreach ($migrationFiles as $file) {
                $migrationName = pathinfo($file, PATHINFO_FILENAME);

                if (in_array($migrationName, $executedMigrations)) {
                    $results['already_run'][] = $migrationName;
                    continue;
                }

                try {
                    $this->executeMigration($file);
                    $this->markMigrationAsExecuted($migrationName);
                    $results['executed'][] = $migrationName;

                    $this->logger->info("Migration executed successfully", [
                        'migration' => $migrationName,
                        'file' => $file
                    ]);

                } catch (Exception $e) {
                    $results['failed'][] = [
                        'migration' => $migrationName,
                        'error' => $e->getMessage()
                    ];
                    $results['success'] = false;

                    $this->logger->error("Migration failed", [
                        'migration' => $migrationName,
                        'error' => $e->getMessage()
                    ]);
                }
            }

        } catch (Exception $e) {
            $results['success'] = false;
            $results['error'] = $e->getMessage();

            $this->logger->error("Migration process failed", [
                'error' => $e->getMessage()
            ]);
        }

        return $results;
    }

    public function rollback(int $steps = 1): array
    {
        $results = [
            'success' => true,
            'rolled_back' => [],
            'failed' => [],
            'total_requested' => $steps
        ];

        try {
            $executedMigrations = $this->getExecutedMigrations(true);
            $toRollback = array_slice($executedMigrations, 0, $steps);

            foreach ($toRollback as $migration) {
                try {
                    $this->rollbackMigration($migration);
                    $this->markMigrationAsRolledBack($migration['name']);
                    $results['rolled_back'][] = $migration['name'];

                    $this->logger->info("Migration rolled back successfully", [
                        'migration' => $migration['name']
                    ]);

                } catch (Exception $e) {
                    $results['failed'][] = [
                        'migration' => $migration['name'],
                        'error' => $e->getMessage()
                    ];
                    $results['success'] = false;

                    $this->logger->error("Migration rollback failed", [
                        'migration' => $migration['name'],
                        'error' => $e->getMessage()
                    ]);
                }
            }

        } catch (Exception $e) {
            $results['success'] = false;
            $results['error'] = $e->getMessage();

            $this->logger->error("Rollback process failed", [
                'error' => $e->getMessage()
            ]);
        }

        return $results;
    }

    public function getStatus(): array
    {
        try {
            $this->createMigrationsTable();

            $migrationFiles = $this->getMigrationFiles();
            $executedMigrations = $this->getExecutedMigrations();

            $status = [
                'total_migrations' => count($migrationFiles),
                'executed_migrations' => count($executedMigrations),
                'pending_migrations' => count($migrationFiles) - count($executedMigrations),
                'migrations' => []
            ];

            foreach ($migrationFiles as $file) {
                $migrationName = pathinfo($file, PATHINFO_FILENAME);
                $isExecuted = in_array($migrationName, $executedMigrations);

                $status['migrations'][] = [
                    'name' => $migrationName,
                    'file' => $file,
                    'executed' => $isExecuted,
                    'executed_at' => $isExecuted ? $this->getMigrationExecutionDate($migrationName) : null
                ];
            }

            return $status;

        } catch (Exception $e) {
            $this->logger->error("Failed to get migration status", [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function createAdminUser(string $email, string $password, string $name = ''): array
    {
        try {
            // Check if user already exists
            $stmt = $this->pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);

            if ($stmt->fetch()) {
                return [
                    'success' => false,
                    'error' => 'User with this email already exists'
                ];
            }

            // Hash password
            $passwordHash = password_hash($password, PASSWORD_ARGON2ID);

            // Insert admin user
            $stmt = $this->pdo->prepare("
                INSERT INTO users (email, password_hash, name, role, is_active, preferences)
                VALUES (?, ?, ?, 'admin', TRUE, '{}')
            ");

            $result = $stmt->execute([$email, $passwordHash, $name]);

            if ($result) {
                $userId = $this->pdo->lastInsertId();

                $this->logger->info("Admin user created", [
                    'user_id' => $userId,
                    'email' => $email,
                    'name' => $name
                ]);

                return [
                    'success' => true,
                    'user_id' => $userId,
                    'message' => 'Admin user created successfully'
                ];
            } else {
                throw new Exception("Failed to insert admin user");
            }

        } catch (Exception $e) {
            $this->logger->error("Failed to create admin user", [
                'email' => $email,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function createRegularUser(string $email, string $password, string $name = ''): array
    {
        try {
            // Check if user already exists
            $stmt = $this->pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);

            if ($stmt->fetch()) {
                return [
                    'success' => false,
                    'error' => 'User with this email already exists'
                ];
            }

            // Hash password
            $passwordHash = password_hash($password, PASSWORD_ARGON2ID);

            // Insert regular user
            $stmt = $this->pdo->prepare("
                INSERT INTO users (email, password_hash, name, role, is_active, preferences)
                VALUES (?, ?, ?, 'user', TRUE, '{}')
            ");

            $result = $stmt->execute([$email, $passwordHash, $name]);

            if ($result) {
                $userId = $this->pdo->lastInsertId();

                $this->logger->info("Regular user created", [
                    'user_id' => $userId,
                    'email' => $email,
                    'name' => $name
                ]);

                return [
                    'success' => true,
                    'user_id' => $userId,
                    'message' => 'Regular user created successfully'
                ];
            } else {
                throw new Exception("Failed to insert regular user");
            }

        } catch (Exception $e) {
            $this->logger->error("Failed to create regular user", [
                'email' => $email,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    private function createMigrationsTable(): void
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS `migrations` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `migration` VARCHAR(255) NOT NULL UNIQUE,
                `executed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_migration` (`migration`),
                INDEX `idx_executed_at` (`executed_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";

        $this->pdo->exec($sql);
    }

    private function getMigrationFiles(): array
    {
        $files = glob($this->migrationsPath . '/*.sql');
        sort($files);
        return $files;
    }

    private function getExecutedMigrations(bool $withDetails = false): array
    {
        $stmt = $this->pdo->prepare("
            SELECT migration, executed_at
            FROM migrations
            ORDER BY executed_at DESC
        ");
        $stmt->execute();

        if ($withDetails) {
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        }
    }

    private function executeMigration(string $file): void
    {
        $sql = file_get_contents($file);

        if ($sql === false) {
            throw new Exception("Cannot read migration file: {$file}");
        }

        // Split SQL file into individual statements
        $statements = $this->splitSqlStatements($sql);

        $this->pdo->beginTransaction();

        try {
            foreach ($statements as $statement) {
                if (!empty(trim($statement))) {
                    $this->pdo->exec($statement);
                }
            }

            $this->pdo->commit();

        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    private function rollbackMigration(array $migration): void
    {
        // For now, we don't have automatic rollback functionality
        // In a real implementation, you would have down() methods or rollback SQL files
        throw new Exception("Automatic rollback not implemented for migration: {$migration['name']}");
    }

    private function markMigrationAsExecuted(string $migrationName): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO migrations (migration) VALUES (:migration)
        ");
        $stmt->execute(['migration' => $migrationName]);
    }

    private function markMigrationAsRolledBack(string $migrationName): void
    {
        $stmt = $this->pdo->prepare("
            DELETE FROM migrations WHERE migration = :migration
        ");
        $stmt->execute(['migration' => $migrationName]);
    }

    private function getMigrationExecutionDate(string $migrationName): ?string
    {
        $stmt = $this->pdo->prepare("
            SELECT executed_at FROM migrations WHERE migration = :migration
        ");
        $stmt->execute(['migration' => $migrationName]);

        $result = $stmt->fetch(PDO::FETCH_COLUMN);
        return $result ?: null;
    }

    private function splitSqlStatements(string $sql): array
    {
        $statements = [];
        $currentStatement = '';
        $delimiter = ';';
        $inDelimiter = false;

        $lines = explode("\n", $sql);

        foreach ($lines as $line) {
            $line = trim($line);

            // Skip empty lines and comments
            if (empty($line) || strpos($line, '--') === 0) {
                continue;
            }

            // Check for custom delimiter
            if (strpos($line, 'DELIMITER') === 0) {
                $parts = explode(' ', $line);
                $delimiter = end($parts);
                $inDelimiter = true;
                continue;
            }

            $currentStatement .= $line . "\n";

            // Check for end of statement
            if (substr($line, -strlen($delimiter)) === $delimiter) {
                if ($inDelimiter) {
                    $statements[] = substr($currentStatement, 0, -strlen($delimiter));
                    $inDelimiter = false;
                    $delimiter = ';';
                } else {
                    $statements[] = substr($currentStatement, 0, -1);
                }
                $currentStatement = '';
            }
        }

        return $statements;
    }

    public function testConnection(): bool
    {
        try {
            $this->pdo->query("SELECT 1");
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }

    public function getDatabaseInfo(): array
    {
        try {
            $version = $this->pdo->query("SELECT VERSION() as version")->fetch(PDO::FETCH_ASSOC);
            $databases = $this->pdo->query("SHOW DATABASES")->fetchAll(PDO::FETCH_COLUMN);

            return [
                'connected' => true,
                'version' => $version['version'],
                'available_databases' => $databases,
                'current_database' => $this->pdo->query("SELECT DATABASE()")->fetch(PDO::FETCH_COLUMN)
            ];
        } catch (Exception $e) {
            return [
                'connected' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}