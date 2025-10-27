#!/usr/bin/env php
<?php

/**
 * Quick Setup Script for User System
 *
 * This script adds user management tables to existing database
 */

require_once __DIR__ . '/vendor/autoload.php';

use App\Services\UserAwareLogger;

echo "🚀 MCP PHP Server - User System Setup\n";
echo "========================================\n\n";

try {
    // Database configuration
    $config = [
        'host' => $_ENV['DB_HOST'] ?? 'localhost',
        'database' => $_ENV['DB_DATABASE'] ?? 'mcp_php_server',
        'username' => $_ENV['DB_USERNAME'] ?? 'root',
        'password' => $_ENV['DB_PASSWORD'] ?? ''
    ];

    echo "📊 Connecting to database: {$config['database']}@{$config['host']}\n";

    // Connect to database
    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=utf8mb4',
        $config['host'],
        $config['database']
    );

    $pdo = new PDO($dsn, $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    echo "✅ Database connected successfully!\n\n";

    // Read migration file
    $migrationFile = __DIR__ . '/migrations/002_add_user_system_tables.sql';

    if (!file_exists($migrationFile)) {
        throw new Exception("Migration file not found: {$migrationFile}");
    }

    echo "📋 Reading migration file...\n";
    $sql = file_get_contents($migrationFile);

    // Execute migration
    echo "🔄 Executing user system migration...\n";

    $pdo->exec($sql);

    echo "✅ User system tables created successfully!\n\n";

    // Verify tables were created
    echo "🔍 Verifying tables...\n";
    $tables = $pdo->query("SHOW TABLES LIKE 'user%'")->fetchAll(PDO::FETCH_COLUMN);

    foreach ($tables as $table) {
        echo "   ✅ {$table}\n";
    }

    echo "\n👥 Checking default users...\n";

    $stmt = $pdo->query("SELECT email, role, is_active FROM users ORDER BY created_at");
    $users = $stmt->fetchAll();

    foreach ($users as $user) {
        $status = $user['is_active'] ? '✅' : '❌';
        echo "   {$status} {$user['email']} ({$user['role']})\n";
    }

    echo "\n🎉 Setup completed successfully!\n\n";
    echo "📝 Default login credentials:\n";
    echo "   Root:  root@localhost  / 417096\n";
    echo "   User:  fullcv@localhost / dev123\n\n";
    echo "🚀 You can now start the server with:\n";
    echo "   php console.php server:start 2\n";
    echo "   or\n";
    echo "   ./start.sh 2\n\n";

} catch (Exception $e) {
    echo "❌ Setup failed!\n";
    echo "Error: " . $e->getMessage() . "\n\n";

    echo "🔧 Troubleshooting:\n";
    echo "1. Check database credentials in config/database.php\n";
    echo "2. Ensure database server is running\n";
    echo "3. Verify database user has CREATE TABLE permissions\n\n";

    exit(1);
}