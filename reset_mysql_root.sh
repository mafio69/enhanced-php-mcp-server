#!/bin/bash

# MySQL Root Password Reset Script for Ubuntu/WSL
# Usage: sudo ./reset_mysql_root.sh

echo "🔧 MySQL Root Password Reset Script"
echo "=================================="

# Check if running as root
if [ "$EUID" -ne 0 ]; then
    echo "❌ This script must be run as root (use sudo)"
    exit 1
fi

# New password for root
NEW_PASSWORD="mcp_root_2024"

echo "📋 This will reset MySQL root password to: $NEW_PASSWORD"
echo "⚠️  Make sure MySQL/MariaDB is running"
echo ""

# Check if MySQL is running
if ! systemctl is-active --quiet mysql; then
    echo "🔄 Starting MySQL service..."
    systemctl start mysql
    sleep 3
fi

echo "🔄 Creating temporary MySQL reset script..."

# Create temporary reset script
cat > /tmp/mysql_reset.sql << 'EOF'
FLUSH PRIVILEGES;

-- Drop existing root users
DROP USER IF EXISTS 'root'@'localhost';
DROP USER IF EXISTS 'root'@'127.0.0.1';

-- Create new root user
CREATE USER 'root'@'localhost' IDENTIFIED BY 'mcp_root_2024';
CREATE USER 'root'@'127.0.0.1' IDENTIFIED BY 'mcp_root_2024';

-- Grant all privileges
GRANT ALL PRIVILEGES ON *.* TO 'root'@'localhost' WITH GRANT OPTION;
GRANT ALL PRIVILEGES ON *.* TO 'root'@'127.0.0.1' WITH GRANT OPTION;

FLUSH PRIVILEGES;
EOF

echo "🔄 Executing MySQL reset..."

# Execute the reset script
mysql -u root < /tmp/mysql_reset.sql 2>/dev/null

if [ $? -eq 0 ]; then
    echo "✅ MySQL root password reset successfully!"
    echo ""
    echo "📝 New login credentials:"
    echo "   Username: root"
    echo "   Password: mcp_root_2024"
    echo ""
    echo "🧪 Testing connection..."
    mysql -u root -pmcp_root_2024 -e "SELECT 'Connection successful!' as status;" 2>/dev/null

    if [ $? -eq 0 ]; then
        echo "✅ Connection test passed!"
    else
        echo "❌ Connection test failed"
    fi
else
    echo "❌ MySQL reset failed. Trying alternative method..."

    # Alternative: Try without password first
    mysql -u root < /tmp/mysql_reset.sql

    if [ $? -eq 0 ]; then
        echo "✅ MySQL root password reset with alternative method!"
    else
        echo "❌ All reset methods failed."
        echo ""
        echo "🔧 Manual reset required:"
        echo "1. Stop MySQL: sudo systemctl stop mysql"
        echo "2. Start safe mode: sudo mysqld_safe --skip-grant-tables &"
        echo "3. Connect: mysql -u root"
        echo "4. Run: ALTER USER 'root'@'localhost' IDENTIFIED BY 'mcp_root_2024';"
        echo "5. Flush privileges and restart MySQL"
    fi
fi

# Clean up
rm -f /tmp/mysql_reset.sql

echo ""
echo "🎉 Reset script completed!"
echo "💡 You can now test with: mysql -u root -pmcp_root_2024"