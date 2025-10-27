-- MCP PHP Server - Complete Database Setup
-- Version: 000
-- Description: Create complete database structure for MCP PHP Server

-- Enable strict mode and set timezone
SET SQL_MODE = 'STRICT_TRANS_TABLES';
SET TIME_ZONE = '+00:00';
SET FOREIGN_KEY_CHECKS = 0;

-- Drop existing tables if they exist (for clean install)
DROP TABLE IF EXISTS `user_sessions`;
DROP TABLE IF EXISTS `user_activities`;
DROP TABLE IF EXISTS `user_secrets`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `migrations`;
DROP TABLE IF EXISTS `tool_executions`;
DROP TABLE IF EXISTS `system_logs`;

-- Create migrations table for tracking migrations
CREATE TABLE `migrations` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `migration` VARCHAR(255) NOT NULL UNIQUE,
    `executed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_migration` (`migration`),
    INDEX `idx_executed_at` (`executed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create users table
CREATE TABLE `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `email` VARCHAR(255) UNIQUE NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `name` VARCHAR(255) DEFAULT '',
    `role` ENUM('admin', 'user', 'viewer') NOT NULL DEFAULT 'user',
    `is_active` BOOLEAN DEFAULT TRUE,
    `preferences` JSON,
    `reset_token` VARCHAR(64) DEFAULT '',
    `reset_token_expires` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `last_login_at` TIMESTAMP NULL DEFAULT NULL,

    -- Indexes for performance
    INDEX `idx_email` (`email`),
    INDEX `idx_role` (`role`),
    INDEX `idx_active` (`is_active`),
    INDEX `idx_created_at` (`created_at`),
    INDEX `idx_last_login` (`last_login_at`),
    INDEX `idx_reset_token` (`reset_token`, `reset_token_expires`),

    -- Constraints
    CONSTRAINT `chk_role_valid` CHECK (`role` IN ('admin', 'user', 'viewer'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create user_secrets table
CREATE TABLE `user_secrets` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `secret_key` VARCHAR(255) NOT NULL,
    `encrypted_value` TEXT NOT NULL,
    `description` TEXT,
    `category` VARCHAR(100) NOT NULL DEFAULT 'general',
    `is_deleted` BOOLEAN DEFAULT FALSE,
    `shared_by` INT NULL,
    `access_list` JSON,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `expires_at` TIMESTAMP NULL DEFAULT NULL,
    `metadata` JSON,
    `access_count` INT DEFAULT 0,
    `last_accessed` TIMESTAMP NULL DEFAULT NULL,

    -- Foreign key constraints
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`shared_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,

    -- Indexes for performance
    UNIQUE KEY `unique_user_secret` (`user_id`, `secret_key`),
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_category` (`category`),
    INDEX `idx_deleted` (`is_deleted`),
    INDEX `idx_expires_at` (`expires_at`),
    INDEX `idx_created_at` (`created_at`),
    INDEX `idx_last_accessed` (`last_accessed`),
    INDEX `idx_shared_by` (`shared_by`),

    -- Constraints
    CONSTRAINT `chk_secret_key_format` CHECK (`secret_key` REGEXP '^[a-zA-Z0-9_.-]+$'),
    CONSTRAINT `chk_category_valid` CHECK (`category` IN ('api_keys', 'database', 'credentials', 'tokens', 'ssh_keys', 'certificates', 'passwords', 'general')),
    CONSTRAINT `chk_access_count_positive` CHECK (`access_count` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create user_activities table for audit logging
CREATE TABLE `user_activities` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `session_id` VARCHAR(64) DEFAULT '',
    `action` VARCHAR(100) NOT NULL,
    `resource_type` VARCHAR(50) DEFAULT '',
    `resource_id` VARCHAR(255) DEFAULT '',
    `details` JSON,
    `ip_address` VARCHAR(45) DEFAULT '',
    `user_agent` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    -- Foreign key constraint
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,

    -- Indexes for performance and querying
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_action` (`action`),
    INDEX `idx_resource` (`resource_type`, `resource_id`),
    INDEX `idx_created_at` (`created_at`),
    INDEX `idx_session_id` (`session_id`),
    INDEX `idx_ip_address` (`ip_address`),

    -- Composite indexes for common queries
    INDEX `idx_user_action_date` (`user_id`, `action`, `created_at`),
    INDEX `idx_user_resource_date` (`user_id`, `resource_type`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create user_sessions table for active session management
CREATE TABLE `user_sessions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `session_id` VARCHAR(64) UNIQUE NOT NULL,
    `user_id` INT NOT NULL,
    `ip_address` VARCHAR(45) DEFAULT '',
    `user_agent` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `last_activity` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `expires_at` TIMESTAMP NOT NULL,
    `remember_me` BOOLEAN DEFAULT FALSE,
    `is_active` BOOLEAN DEFAULT TRUE,

    -- Foreign key constraint
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,

    -- Indexes for performance
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_session_id` (`session_id`),
    INDEX `idx_expires_at` (`expires_at`),
    INDEX `idx_last_activity` (`last_activity`),
    INDEX `idx_ip_address` (`ip_address`),

    -- Constraint
    CONSTRAINT `chk_session_id_format` CHECK (`session_id` REGEXP '^[a-f0-9]{64}$')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create tool_executions table for tracking tool usage
CREATE TABLE `tool_executions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NULL,
    `session_id` VARCHAR(64) DEFAULT '',
    `tool_name` VARCHAR(100) NOT NULL,
    `arguments` JSON,
    `result` JSON,
    `execution_time` DECIMAL(10,3) DEFAULT 0.000,
    `status` ENUM('success', 'error', 'timeout') NOT NULL DEFAULT 'success',
    `error_message` TEXT,
    `ip_address` VARCHAR(45) DEFAULT '',
    `user_agent` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    -- Foreign key constraint
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,

    -- Indexes for performance
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_tool_name` (`tool_name`),
    INDEX `idx_status` (`status`),
    INDEX `idx_created_at` (`created_at`),
    INDEX `idx_session_id` (`session_id`),

    -- Composite indexes
    INDEX `idx_user_tool_date` (`user_id`, `tool_name`, `created_at`),
    INDEX `idx_tool_status_date` (`tool_name`, `status`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create system_logs table for system-wide logging
CREATE TABLE `system_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `level` ENUM('debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency') NOT NULL,
    `message` TEXT NOT NULL,
    `context` JSON,
    `channel` VARCHAR(50) DEFAULT 'mcp-server',
    `user_id` INT NULL,
    `session_id` VARCHAR(64) DEFAULT '',
    `ip_address` VARCHAR(45) DEFAULT '',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    -- Foreign key constraint
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,

    -- Indexes for performance
    INDEX `idx_level` (`level`),
    INDEX `idx_channel` (`channel`),
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_created_at` (`created_at`),
    INDEX `idx_session_id` (`session_id`),

    -- Composite indexes
    INDEX `idx_level_date` (`level`, `created_at`),
    INDEX `idx_user_level_date` (`user_id`, `level`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default users
INSERT INTO `users` (
    `email`,
    `password_hash`,
    `name`,
    `role`,
    `is_active`,
    `preferences`
) VALUES
-- Root user (password: 417096)
(
    'root@localhost',
    '$argon2id$v=19$m=65536,t=4,p=1$alh0VTRUZ2Y1Y25ZYWxNcQ$x3nJaxVIWED2klDT9Umsph3KqM7T2y3IwU216e+Ocaw',
    'Root Administrator',
    'admin',
    TRUE,
    '{"theme": "dark", "timezone": "Europe/Warsaw", "language": "pl"}'
),
-- FullCV user (password: dev123)
(
    'fullcv@localhost',
    '$argon2id$v=19$m=65536,t=4,p=1$aG9CQ29wT1ZicmZmd0xSTQ$mapuCxXpOwOCYzo12AzwXJumb+0VUxW2FPG9PJCaprY',
    'FullCV User',
    'user',
    TRUE,
    '{"theme": "light", "timezone": "Europe/Warsaw", "language": "pl"}'
);

-- Create views for common queries
CREATE OR REPLACE VIEW `v_active_users` AS
SELECT
    u.id,
    u.email,
    u.name,
    u.role,
    u.last_login_at,
    COUNT(DISTINCT s.id) as active_sessions,
    COUNT(DISTINCT us.id) as total_secrets,
    COUNT(DISTINCT ua.id) as recent_activities,
    MAX(ua.created_at) as last_activity
FROM `users` u
LEFT JOIN `user_sessions` s ON u.id = s.user_id AND s.is_active = TRUE AND s.expires_at > NOW()
LEFT JOIN `user_secrets` us ON u.id = us.user_id AND us.is_deleted = FALSE
LEFT JOIN `user_activities` ua ON u.id = ua.user_id AND ua.created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
WHERE u.is_active = TRUE
GROUP BY u.id, u.email, u.name, u.role, u.last_login_at;

CREATE OR REPLACE VIEW `v_user_secrets` AS
SELECT
    us.id,
    us.user_id,
    u.email as owner_email,
    u.name as owner_name,
    us.secret_key,
    us.description,
    us.category,
    us.is_deleted,
    us.shared_by,
    IFNULL(sharer.name, 'System') as shared_by_name,
    us.access_list,
    us.created_at,
    us.updated_at,
    us.expires_at,
    us.access_count,
    us.last_accessed,
    CASE
        WHEN us.expires_at IS NULL THEN FALSE
        WHEN us.expires_at < NOW() THEN TRUE
        ELSE FALSE
    END as is_expired,
    CASE
        WHEN us.expires_at IS NULL THEN NULL
        ELSE DATEDIFF(us.expires_at, NOW())
    END as days_until_expiry,
    JSON_LENGTH(us.access_list) as shared_users_count,
    CASE
        WHEN us.shared_by IS NULL THEN TRUE
        ELSE FALSE
    END as is_owner_secret
FROM `user_secrets` us
LEFT JOIN `users` u ON us.user_id = u.id
LEFT JOIN `users` sharer ON us.shared_by = sharer.id
WHERE us.is_deleted = FALSE;

CREATE OR REPLACE VIEW `v_tool_statistics` AS
SELECT
    tool_name,
    COUNT(*) as total_executions,
    COUNT(CASE WHEN status = 'success' THEN 1 END) as successful_executions,
    COUNT(CASE WHEN status = 'error' THEN 1 END) as failed_executions,
    COUNT(CASE WHEN status = 'timeout' THEN 1 END) as timeout_executions,
    AVG(execution_time) as avg_execution_time,
    MAX(execution_time) as max_execution_time,
    MIN(execution_time) as min_execution_time,
    DATE(created_at) as execution_date
FROM `tool_executions`
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY tool_name, DATE(created_at);

CREATE OR REPLACE VIEW `v_system_statistics` AS
SELECT
    COUNT(*) as total_users,
    COUNT(CASE WHEN u.is_active = TRUE THEN 1 END) as active_users,
    COUNT(CASE WHEN u.role = 'admin' THEN 1 END) as admin_users,
    COUNT(CASE WHEN u.role = 'user' THEN 1 END) as regular_users,
    COUNT(CASE WHEN u.role = 'viewer' THEN 1 END) as viewer_users,
    COUNT(DISTINCT s.id) as active_sessions,
    COUNT(DISTINCT us.id) as total_secrets,
    COUNT(DISTINCT te.id) as total_tool_executions,
    COUNT(DISTINCT sl.id) as total_log_entries
FROM `users` u
LEFT JOIN `user_sessions` s ON u.id = s.user_id AND s.is_active = TRUE AND s.expires_at > NOW()
LEFT JOIN `user_secrets` us ON u.id = us.user_id AND us.is_deleted = FALSE
LEFT JOIN `tool_executions` te ON 1=1
LEFT JOIN `system_logs` sl ON 1=1;

-- Create stored procedures for maintenance
DELIMITER //
CREATE PROCEDURE IF NOT EXISTS `CleanupExpiredSessions`()
BEGIN
    DECLARE cleaned_count INT DEFAULT 0;

    DELETE FROM `user_sessions`
    WHERE `expires_at` < NOW() OR `is_active` = FALSE;

    SET cleaned_count = ROW_COUNT();

    SELECT cleaned_count as sessions_cleaned;
END //

CREATE PROCEDURE IF NOT EXISTS `CleanupOldActivities`(IN days_to_keep INT)
BEGIN
    DECLARE cleaned_count INT DEFAULT 0;

    DELETE FROM `user_activities`
    WHERE `created_at` < DATE_SUB(NOW(), INTERVAL days_to_keep DAY);

    SET cleaned_count = ROW_COUNT();

    SELECT cleaned_count as activities_cleaned;
END //

CREATE PROCEDURE IF NOT EXISTS `CleanupOldLogs`(IN days_to_keep INT)
BEGIN
    DECLARE cleaned_count INT DEFAULT 0;

    DELETE FROM `system_logs`
    WHERE `created_at` < DATE_SUB(NOW(), INTERVAL days_to_keep DAY);

    SET cleaned_count = ROW_COUNT();

    SELECT cleaned_count as logs_cleaned;
END //

CREATE PROCEDURE IF NOT EXISTS `GetUserStatistics`(IN user_id_param INT)
BEGIN
    SELECT
        u.id,
        u.email,
        u.name,
        u.role,
        u.last_login_at,
        COUNT(DISTINCT s.id) as active_sessions,
        COUNT(DISTINCT us.id) as total_secrets,
        COUNT(DISTINCT te.id) as tool_executions,
        COUNT(DISTINCT ua.id) as activities,
        MAX(ua.created_at) as last_activity
    FROM `users` u
    LEFT JOIN `user_sessions` s ON u.id = s.user_id AND s.is_active = TRUE AND s.expires_at > NOW()
    LEFT JOIN `user_secrets` us ON u.id = us.user_id AND us.is_deleted = FALSE
    LEFT JOIN `tool_executions` te ON u.id = te.user_id
    LEFT JOIN `user_activities` ua ON u.id = ua.user_id
    WHERE u.id = user_id_param
    GROUP BY u.id, u.email, u.name, u.role, u.last_login_at;
END //

CREATE PROCEDURE IF NOT EXISTS `AnalyzeSystemPerformance`()
BEGIN
    SELECT
        'Users' as metric_type,
        COUNT(*) as total_count,
        COUNT(CASE WHEN is_active = TRUE THEN 1 END) as active_count
    FROM `users`

    UNION ALL

    SELECT
        'Sessions' as metric_type,
        COUNT(*) as total_count,
        COUNT(CASE WHEN is_active = TRUE AND expires_at > NOW() THEN 1 END) as active_count
    FROM `user_sessions`

    UNION ALL

    SELECT
        'Secrets' as metric_type,
        COUNT(*) as total_count,
        COUNT(CASE WHEN is_deleted = FALSE THEN 1 END) as active_count
    FROM `user_secrets`

    UNION ALL

    SELECT
        'Tool Executions' as metric_type,
        COUNT(*) as total_count,
        COUNT(CASE WHEN status = 'success' THEN 1 END) as active_count
    FROM `tool_executions`
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)

    UNION ALL

    SELECT
        'System Logs' as metric_type,
        COUNT(*) as total_count,
        COUNT(CASE WHEN level IN ('error', 'critical', 'alert', 'emergency') THEN 1 END) as active_count
    FROM `system_logs`
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR);
END //
DELIMITER ;

-- Create triggers for automatic updates
DELIMITER //
CREATE TRIGGER IF NOT EXISTS `tr_user_session_update_activity`
BEFORE UPDATE ON `user_sessions`
FOR EACH ROW
BEGIN
    IF NEW.last_activity <> OLD.last_activity THEN
        UPDATE `users`
        SET `last_login_at` = NEW.last_activity
        WHERE `id` = NEW.user_id;
    END IF;
END //

CREATE TRIGGER IF NOT EXISTS `tr_user_secret_update_access`
BEFORE UPDATE ON `user_secrets`
FOR EACH ROW
BEGIN
    IF NEW.access_count <> OLD.access_count THEN
        UPDATE `user_secrets`
        SET `last_accessed` = NOW()
        WHERE `id` = NEW.id;
    END IF;
END //
DELIMITER ;

-- Events for automatic maintenance (commented out - can be created manually if needed)
-- CREATE EVENT IF NOT EXISTS `evt_daily_cleanup`
-- ON SCHEDULE EVERY 1 DAY STARTS CURRENT_TIMESTAMP
-- DO CALL CleanupExpiredSessions();

-- CREATE EVENT IF NOT EXISTS `evt_hourly_stats_update`
-- ON SCHEDULE EVERY 1 HOUR STARTS CURRENT_TIMESTAMP
-- DO INSERT INTO `system_logs` (level, message, channel, context)
--    VALUES ('info', 'Hourly statistics update completed', 'system', '{}');

-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;

-- Create database for configuration and store initial data
INSERT INTO `migrations` (migration) VALUES ('000_create_complete_database');

-- Database setup completed successfully
SELECT 'Complete database setup finished successfully' as status;