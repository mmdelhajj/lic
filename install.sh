#!/bin/bash

#=============================================================================
# License Server - Installation Script
# Automatically installs license server on Ubuntu 22.04
#=============================================================================

set -e

echo "============================================"
echo "  License Server - Installation Script"
echo "============================================"
echo ""

# Check if running as root
if [ "$EUID" -ne 0 ]; then
    echo "❌ Please run as root (use sudo)"
    exit 1
fi

# Get configuration
read -p "Enter domain name (e.g., lic.example.com): " DOMAIN
read -p "Enter MySQL root password: " -s DB_ROOT_PASS
echo ""
read -p "Enter new database password for license_server: " -s DB_PASS
echo ""

echo ""
echo "Configuration:"
echo "  Domain: $DOMAIN"
echo "  Database: license_server"
echo ""
read -p "Continue with installation? (y/n) " -n 1 -r
echo ""
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    exit 1
fi

echo ""
echo "[1/7] Installing packages..."
apt update
apt install -y nginx php8.1-fpm php8.1-mysql php8.1-curl php8.1-mbstring mysql-server certbot python3-certbot-nginx

echo ""
echo "[2/7] Creating database..."
mysql -u root -p"$DB_ROOT_PASS" << EOF
CREATE DATABASE IF NOT EXISTS license_server CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'license_user'@'localhost' IDENTIFIED BY '$DB_PASS';
GRANT ALL PRIVILEGES ON license_server.* TO 'license_user'@'localhost';
FLUSH PRIVILEGES;
EOF

echo ""
echo "[3/7] Importing database schema..."
mysql -u root -p"$DB_ROOT_PASS" license_server < database/schema.sql

echo ""
echo "[4/7] Creating environment file..."
cat > .env << ENVEOF
DB_HOST=localhost
DB_NAME=license_server
DB_USER=license_user
DB_PASS=$DB_PASS
ENVEOF

chmod 600 .env

echo ""
echo "[5/7] Configuring Nginx..."
cat > /etc/nginx/sites-available/license << NGINXEOF
server {
    listen 80;
    server_name $DOMAIN;
    root /var/www/license;
    index index.php;

    location / {
        try_files \$uri \$uri/ =404;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.1-fpm.sock;
    }

    location ~ /\.env {
        deny all;
    }

    access_log /var/log/nginx/license_access.log;
    error_log /var/log/nginx/license_error.log;
}
NGINXEOF

ln -sf /etc/nginx/sites-available/license /etc/nginx/sites-enabled/
rm -f /etc/nginx/sites-enabled/default
nginx -t
systemctl reload nginx

echo ""
echo "[6/7] Setting up cron job for license checking..."
chmod +x bin/check-licenses.php
echo "0 */12 * * * /var/www/license/bin/check-licenses.php >> /var/log/license-checker.log 2>&1" | crontab -

echo ""
echo "[7/7] Installing SSL certificate..."
certbot --nginx -d $DOMAIN --non-interactive --agree-tos --email admin@$DOMAIN

echo ""
echo "============================================"
echo "  ✅ Installation Complete!"
echo "============================================"
echo ""
echo "License server is now running at:"
echo "  https://$DOMAIN/admin/installations.php"
echo ""
echo "Default login:"
echo "  Username: admin"
echo "  Password: admin123"
echo ""
echo "⚠️  IMPORTANT: Change the admin password immediately!"
echo ""
echo "Database password saved in .env file"
echo "Cron job runs every 12 hours to check licenses"
echo ""
