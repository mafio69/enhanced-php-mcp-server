-- MCP PHP Server User System Tables Addition
-- Version: 002
-- Description: Add user management tables to existing database

-- Enable strict mode
SET SQL_MODE = 'STRICT_TRANS_TABLES';

-- Create users table
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `email` VARCHAR(255) UNIQUE NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `name` VARCHAR(255) DEFAULT '',
    `role` ENUM('admin', 'user', 'viewer') NOT NULL DEFAULT 'user',
    `is_active` BOOLEAN DEFAULT TRUE,
    `preferences` JSON DEFAULT '{}',
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

    -- Constraints
    CONSTRAINT `chk_email_format` CHECK (`email` REGEXP '^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\\.[A-Za-z]{2,}$'),
    CONSTRAINT `chk_role_valid` CHECK (`role` IN ('admin', 'user', 'viewer'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create user_secrets table
CREATE TABLE IF NOT EXISTS `user_secrets` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `secret_key` VARCHAR(255) NOT NULL,
    `encrypted_value` TEXT NOT NULL,
    `description` TEXT DEFAULT '',
    `category` VARCHAR(100) NOT NULL DEFAULT 'general',
    `is_deleted` BOOLEAN DEFAULT FALSE,
    `shared_by` INT NULL,
    `access_list` JSON DEFAULT '[]',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `expires_at` TIMESTAMP NULL DEFAULT NULL,
    `metadata` JSON DEFAULT '{}',
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

    -- Constraints
    CONSTRAINT `chk_secret_key_format` CHECK (`secret_key` REGEXP '^[a-zA-Z0-9_.-]+$'),
    CONSTRAINT `chk_category_valid` CHECK (`category` IN ('api_keys', 'database', 'credentials', 'tokens', 'ssh_keys', 'certificates', 'passwords', 'general')),
    CONSTRAINT `chk_access_count_positive` CHECK (`access_count` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create user_activities table for audit logging
CREATE TABLE IF NOT EXISTS `user_activities` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `session_id` VARCHAR(64) DEFAULT '',
    `action` VARCHAR(100) NOT NULL,
    `resource_type` VARCHAR(50) DEFAULT '',
    `resource_id` VARCHAR(255) DEFAULT '',
    `details` JSON DEFAULT '{}',
    `ip_address` VARCHAR(45) DEFAULT '',
    `user_agent` TEXT DEFAULT '',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    -- Foreign key constraint
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,

    -- Indexes for performance and querying
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_action` (`action`),
    INDEX `idx_resource` (`resource_type`, `resource_id`),
    INDEX `idx_created_at` (`created_at`),
    INDEX `idx_session_id` (`session_id`),

    -- Composite indexes for common queries
    INDEX `idx_user_action_date` (`user_id`, `action`, `created_at`),
    INDEX `idx_user_resource_date` (`user_id`, `resource_type`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create user_sessions table for active session management
CREATE TABLE IF NOT EXISTS `user_sessions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `session_id` VARCHAR(64) UNIQUE NOT NULL,
    `user_id` INT NOT NULL,
    `ip_address` VARCHAR(45) DEFAULT '',
    `user_agent` TEXT DEFAULT '',
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

    -- Constraint
    CONSTRAINT `chk_session_id_format` CHECK (`session_id` REGEXP '^[a-f0-9]{64}$')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert root user (password: 417096)
INSERT IGNORE INTO `users` (
    `email`,
    `password_hash`,
    `name`,
    `role`,
    `is_active`,
    `preferences`
) VALUES (
    'root@localhost',
    '$argon2id$v=19$m=65536,t=4,p=1$alh0VTRUZ2Y1Y25ZYWxNcQ$x3nJaxVIWED2klDT9Umsph3KqM7T2y3IwU216e+Ocaw',
    'Root Administrator',
    'admin',
    TRUE,
    '{"theme": "dark", "timezone": "Europe/Warsaw", "language": "pl"}'
);

-- Insert fullcv user (password: dev123)
INSERT IGNORE INTO `users` (
    `email`,
    `password_hash`,
    `name`,
    `role`,
    `is_active`,
    `preferences`
) VALUES (
    'fullcv@localhost',
    '$argon2id$v=19$m=65536,t=4,p=1$aG9CQ29wT1ZicmZmd0xSTQ$mapuCxXpOwOCYzo12AzwXJumb+0VUxW2FPG9PJCaprY',
    'FullCV User',
    'user',
    TRUE,
    '{"theme": "light", "timezone": "Europe/Warsaw", "language": "pl"}'
);

-- Update root user if exists with new password
UPDATE `users`
SET `password_hash` = '$argon2id$v=19$m=65536,t=4,p=1$alh0VTRUZ2Y1Y25ZYWxNcQ$x3nJaxVIWED2klDT9Umsph3KqM7T2y3IwU216e+Ocaw',
    `role` = 'admin',
    `is_active` = TRUE,
    `updated_at` = CURRENT_TIMESTAMP
WHERE `email` = 'root@localhost';

-- Create view for active users with statistics
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

-- Create view for user secrets with sharing information
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

-- User system tables created successfully
SELECT 'User system tables created successfully' as status;