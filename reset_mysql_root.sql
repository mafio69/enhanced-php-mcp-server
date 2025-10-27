-- MySQL Root Password Reset Script
-- Usage: sudo mysql -u root < reset_mysql_root.sql

FLUSH PRIVILEGES;

-- Drop any existing root user
DROP USER IF EXISTS 'root'@'localhost';
DROP USER IF EXISTS 'root'@'127.0.0.1';

-- Create new root user with password 'mcp_root_2024'
CREATE USER 'root'@'localhost' IDENTIFIED BY 'mcp_root_2024';
CREATE USER 'root'@'127.0.0.1' IDENTIFIED BY 'mcp_root_2024';

-- Grant all privileges
GRANT ALL PRIVILEGES ON *.* TO 'root'@'localhost' WITH GRANT OPTION;
GRANT ALL PRIVILEGES ON *.* TO 'root'@'127.0.0.1' WITH GRANT OPTION;

-- Create debian-sys-maint user (Ubuntu default)
DROP USER IF EXISTS 'debian-sys-maint'@'localhost';
CREATE USER 'debian-sys-maint'@'localhost' IDENTIFIED BY 'debian_maint_2024';
GRANT ALL PRIVILEGES ON *.* TO 'debian-sys-maint'@'localhost' WITH GRANT OPTION;

FLUSH PRIVILEGES;