#!/usr/bin/env php
<?php

/**
 * MCP PHP Server - Complete Database Builder
 *
 * This script creates a new database and runs all migrations
 */

require_once __DIR__ . '/vendor/autoload.php';

echo "🏗️  MCP PHP Server - Complete Database Builder\n";
echo "==============================================\n\n";

try {
    // Database configuration
    $config = [
        'host' => $_ENV['DB_HOST'] ?? 'localhost',
        'database' => $_ENV['DB_DATABASE'] ?? 'mcp_php_server',
        'username' => $_ENV['DB_USERNAME'] ?? 'root',
        'password' => $_ENV['DB_PASSWORD'] ?? '',
        'charset' => 'utf8mb4'
    ];

    echo "📊 Database Configuration:\n";
    echo "   Host: {$config['host']}\n";
    echo "   Database: {$config['database']}\n";
    echo "   Username: {$config['username']}\n";
    echo "   Charset: {$config['charset']}\n\n";

    // Connect to MySQL server (without selecting database)
    echo "🔌 Connecting to MySQL server...\n";
    $dsn = sprintf(
        'mysql:host=%s;charset=%s',
        $config['host'],
        $config['charset']
    );

    $pdo = new PDO($dsn, $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    echo "✅ Connected to MySQL server successfully!\n\n";

    // Check if database exists
    echo "🔍 Checking database existence...\n";
    $stmt = $pdo->query("SHOW DATABASES LIKE '{$config['database']}'");
    $databaseExists = $stmt->rowCount() > 0;

    if ($databaseExists) {
        echo "⚠️  Database '{$config['database']}' already exists!\n";

        // Ask user what to do
        echo "Options:\n";
        echo "1. Drop and recreate database (RECOMMENDED)\n";
        echo "2. Keep existing database\n";
        echo "3. Exit\n\n";

        echo "Choose option (1-3): ";
        $choice = trim(fgets(STDIN));

        switch ($choice) {
            case '1':
                echo "🗑️  Dropping existing database...\n";
                $pdo->exec("DROP DATABASE `{$config['database']}`");
                echo "✅ Database dropped successfully!\n\n";
                break;
            case '2':
                echo "📝 Keeping existing database...\n";
                echo "⚠️  Note: Tables will be recreated if they exist\n\n";
                break;
            case '3':
                echo "👋 Exiting...\n";
                exit(0);
            default:
                echo "❌ Invalid choice. Exiting...\n";
                exit(1);
        }
    }

    // Create database if it doesn't exist
    if (!$databaseExists || (isset($choice) && $choice == '1')) {
        echo "🏗️  Creating database '{$config['database']}'...\n";
        $pdo->exec("CREATE DATABASE `{$config['database']}` CHARACTER SET {$config['charset']} COLLATE {$config['charset']}_unicode_ci");
        echo "✅ Database created successfully!\n\n";
    }

    // Connect to the specific database
    echo "🔌 Connecting to database '{$config['database']}'...\n";
    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=%s',
        $config['host'],
        $config['database'],
        $config['charset']
    );

    $pdo = new PDO($dsn, $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    echo "✅ Connected to database successfully!\n\n";

    // Read and execute complete database setup
    echo "📋 Reading complete database setup file...\n";
    $migrationFile = __DIR__ . '/migrations/000_create_complete_database.sql';

    if (!file_exists($migrationFile)) {
        throw new Exception("Database setup file not found: {$migrationFile}");
    }

    $sql = file_get_contents($migrationFile);

    echo "🔄 Executing complete database setup...\n";
    $pdo->exec($sql);

    echo "✅ Database structure created successfully!\n\n";

    // Verify tables were created
    echo "🔍 Verifying created tables...\n";
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

    $expectedTables = [
        'migrations',
        'users',
        'user_secrets',
        'user_activities',
        'user_sessions',
        'tool_executions',
        'system_logs'
    ];

    echo "\n📊 Database Tables:\n";
    foreach ($expectedTables as $table) {
        if (in_array($table, $tables)) {
            $count = $pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
            echo "   ✅ $table ($count records)\n";
        } else {
            echo "   ❌ $table (missing)\n";
        }
    }

    // Verify views were created
    echo "\n👁️  Database Views:\n";
    $views = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'VIEW'")->fetchAll(PDO::FETCH_COLUMN);

    $expectedViews = [
        'v_active_users',
        'v_user_secrets',
        'v_tool_statistics',
        'v_system_statistics'
    ];

    foreach ($expectedViews as $view) {
        if (in_array($view, $views)) {
            echo "   ✅ $view\n";
        } else {
            echo "   ❌ $view (missing)\n";
        }
    }

    // Verify stored procedures
    echo "\n⚙️  Stored Procedures:\n";
    $procedures = $pdo->query("SHOW PROCEDURE STATUS WHERE Db = '{$config['database']}'")->fetchAll(PDO::FETCH_COLUMN);

    $expectedProcedures = [
        'CleanupExpiredSessions',
        'CleanupOldActivities',
        'CleanupOldLogs',
        'GetUserStatistics',
        'AnalyzeSystemPerformance'
    ];

    foreach ($expectedProcedures as $procedure) {
        if (in_array($procedure, $procedures)) {
            echo "   ✅ $procedure\n";
        } else {
            echo "   ❌ $procedure (missing)\n";
        }
    }

    // Verify users were created
    echo "\n👥 Default Users:\n";
    $stmt = $pdo->query("SELECT email, role, is_active, created_at FROM users ORDER BY created_at");
    $users = $stmt->fetchAll();

    foreach ($users as $user) {
        $status = $user['is_active'] ? '✅' : '❌';
        $created = date('Y-m-d H:i:s', strtotime($user['created_at']));
        echo "   {$status} {$user['email']} ({$user['role']}) - Created: {$created}\n";
    }

    // Test database functionality
    echo "\n🧪 Testing database functionality...\n";

    // Test user authentication
    $stmt = $pdo->prepare("SELECT id, email, password_hash, role FROM users WHERE email = ?");
    $stmt->execute(['root@localhost']);
    $rootUser = $stmt->fetch();

    if ($rootUser && password_verify('417096', $rootUser['password_hash'])) {
        echo "   ✅ Root user authentication test passed\n";
    } else {
        echo "   ❌ Root user authentication test failed\n";
    }

    $stmt->execute(['fullcv@localhost']);
    $fullcvUser = $stmt->fetch();

    if ($fullcvUser && password_verify('dev123', $fullcvUser['password_hash'])) {
        echo "   ✅ FullCV user authentication test passed\n";
    } else {
        echo "   ❌ FullCV user authentication test failed\n";
    }

    // Test logging
    $stmt = $pdo->prepare("INSERT INTO system_logs (level, message, channel, context) VALUES (?, ?, ?, ?)");
    $stmt->execute(['info', 'Database setup completed successfully', 'setup', '{"test": true}']);
    echo "   ✅ System logging test passed\n";

    // Show database statistics
    echo "\n📈 Database Statistics:\n";
    $stats = $pdo->query("SELECT * FROM v_system_statistics")->fetch();

    echo "   Total Users: {$stats['total_users']}\n";
    echo "   Active Users: {$stats['active_users']}\n";
    echo "   Admin Users: {$stats['admin_users']}\n";
    echo "   Regular Users: {$stats['regular_users']}\n";
    echo "   Total Secrets: {$stats['total_secrets']}\n";
    echo "   Total Log Entries: {$stats['total_log_entries']}\n";

    echo "\n🎉 Database setup completed successfully!\n\n";
    echo "📝 Login Credentials:\n";
    echo "   Root:  root@localhost  / 417096\n";
    echo "   User:  fullcv@localhost / dev123\n\n";
    echo "🚀 Next steps:\n";
    echo "   1. Start the server: php console.php server:start 2\n";
    echo "   2. Or use: ./start.sh 2\n";
    echo "   3. Access web interface: http://localhost:8888 or 8889\n";
    echo "   4. Login with credentials above\n\n";
    echo "💾 Database: {$config['database']} @ {$config['host']}\n";

} catch (Exception $e) {
    echo "❌ Database setup failed!\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n\n";

    echo "🔧 Troubleshooting:\n";
    echo "1. Check MySQL server is running\n";
    echo "2. Verify database credentials\n";
    echo "3. Ensure database user has CREATE, DROP, ALTER privileges\n";
    echo "4. Check if MySQL port (3306) is accessible\n";
    echo "5. Verify MySQL version supports required features\n\n";

    exit(1);
}