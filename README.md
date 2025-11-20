# License Server for WhatsApp Bot

Professional license management system with automatic 3-day trial for new installations.

## Features

- **Auto-Trial System**: New installations automatically get 3-day trial
- **Remote Management**: Web-based admin panel to manage all customer licenses
- **Manual Activation**: Activate licenses for 1-5 years
- **Suspend/Reactivate**: Control customer access
- **Automatic Expiry**: Cron job checks licenses every 12 hours
- **Online Status**: Track which installations are active
- **Hardware Binding**: License tied to server fingerprint
- **Domain Binding**: License tied to specific domain

## Quick Installation

### Requirements
- Ubuntu 22.04 (or similar)
- Root access
- Domain name pointed to your server

### One-Command Installation

```bash
git clone https://github.com/mmdelhajj/lic.git /var/www/license
cd /var/www/license
sudo ./install.sh
```

The installer will:
1. Install Nginx + PHP 8.1 + MySQL
2. Create database and import schema
3. Configure Nginx virtual host
4. Set up SSL certificate (Let's Encrypt)
5. Configure cron job for automatic license checking
6. Create admin user (username: `admin`, password: `admin123`)

### Access Admin Panel

After installation, visit:
```
https://your-domain.com/admin/installations.php
```

**Default Login:**
- Username: `admin`
- Password: `admin123`

**⚠️ IMPORTANT:** Change the admin password immediately after first login!

## Manual Installation

If you prefer manual setup:

### 1. Install Dependencies
```bash
apt update
apt install -y nginx php8.1-fpm php8.1-mysql php8.1-curl php8.1-mbstring mysql-server
```

### 2. Create Database
```bash
mysql -u root -p
```

```sql
CREATE DATABASE license_server CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'license_user'@'localhost' IDENTIFIED BY 'your_password';
GRANT ALL PRIVILEGES ON license_server.* TO 'license_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

Import schema:
```bash
mysql -u root -p license_server < database/schema.sql
```

### 3. Configure Environment
```bash
cp .env.example .env
nano .env
```

Set your database password in `.env`:
```
DB_HOST=localhost
DB_NAME=license_server
DB_USER=license_user
DB_PASS=your_password
```

### 4. Configure Nginx
```bash
nano /etc/nginx/sites-available/license
```

```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /var/www/license;
    index index.php;

    location / {
        try_files $uri $uri/ =404;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.1-fpm.sock;
    }

    location ~ /\.env {
        deny all;
    }
}
```

Enable site:
```bash
ln -s /etc/nginx/sites-available/license /etc/nginx/sites-enabled/
nginx -t
systemctl reload nginx
```

### 5. Install SSL Certificate
```bash
certbot --nginx -d your-domain.com
```

### 6. Set Up Cron Job
```bash
chmod +x bin/check-licenses.php
crontab -e
```

Add:
```
0 */12 * * * /var/www/license/bin/check-licenses.php >> /var/log/license-checker.log 2>&1
```

## API Endpoints

### Auto-Registration (Trial)
```
POST /api/register.php
Parameters:
  - domain: Installation domain
  - fingerprint: Server fingerprint
  - ip: IP address
  - version: Bot version

Returns: 3-day trial license key
```

### Validation
```
GET /api/validate.php?key={LICENSE_KEY}&domain={DOMAIN}&fingerprint={FINGERPRINT}

Returns: License status and expiry info
```

### Heartbeat
```
POST /api/heartbeat.php
Parameters:
  - license_key: License key
  - ip: IP address
  - version: Bot version

Updates: last_online timestamp
```

### Manual Activation (Admin Only)
```
POST /api/activate.php
Parameters:
  - license_id: License ID
  - duration: Years (1-5)
  - customer_name: Customer name
  - customer_email: Customer email

Returns: New paid license key
```

### Suspend/Delete (Admin Only)
```
POST /api/deactivate.php
Parameters:
  - license_id: License ID
  - action: 'suspend' or 'delete'
```

### Reactivate (Admin Only)
```
POST /api/reactivate.php
Parameters:
  - license_id: License ID
```

## Database Schema

### `licenses` Table
- `id` - Auto increment primary key
- `license_key` - Unique license key (TRIAL-xxx or PAID-xxx)
- `customer_name` - Customer name
- `customer_email` - Customer email
- `domain` - Installation domain
- `ip_address` - Last known IP
- `fingerprint` - Server hardware fingerprint
- `product_name` - Product name (default: "WhatsApp Bot")
- `bot_version` - Bot version
- `status` - active, suspended, expired
- `installation_type` - trial, paid
- `activated_by_admin` - Boolean
- `trial_ends_at` - Trial expiry date
- `expires_at` - License expiry date
- `first_seen` - First registration timestamp
- `last_online` - Last heartbeat timestamp
- `created_at` - Creation timestamp
- `updated_at` - Update timestamp

### `admin_users` Table
- `id` - Auto increment primary key
- `username` - Admin username
- `password_hash` - Bcrypt password hash
- `email` - Admin email
- `last_login` - Last login timestamp

### `license_logs` Table (Optional)
- `id` - Auto increment primary key
- `license_id` - Foreign key to licenses
- `action` - Action type (activated, suspended, expired, etc.)
- `details` - Action details
- `ip_address` - IP address of action
- `created_at` - Timestamp

## Admin Panel Features

### Dashboard Statistics
- Total installations
- Active licenses
- Trial licenses
- Paid licenses

### Installation Management
- View all installations with:
  - Status (active, suspended, expired)
  - Customer info
  - Domain and IP
  - License type (trial/paid)
  - Expiry date and days left
  - Online/offline status (green/red indicator)

### Actions
- **Activate**: Convert trial to paid (1-5 years)
- **Suspend**: Temporarily disable license
- **Reactivate**: Restore suspended license
- **Delete**: Permanently remove installation

## Security

- `.env` file is blocked from web access via Nginx
- Admin sessions use PHP sessions
- Passwords hashed with bcrypt
- License keys are random 32-character hex strings
- Hardware fingerprint prevents license sharing
- Domain binding prevents unauthorized transfers

## Changing Admin Password

### Via MySQL
```bash
mysql -u root -p license_server
```

```sql
UPDATE admin_users
SET password_hash = '$2y$10$your_new_hash_here'
WHERE username = 'admin';
```

To generate hash:
```bash
php -r "echo password_hash('your_new_password', PASSWORD_DEFAULT);"
```

### Via Admin Panel
Create a password change page at `admin/change-password.php` (not included by default).

## Monitoring

### Check License Logs
```bash
tail -f /var/log/license-checker.log
```

### Check Nginx Logs
```bash
tail -f /var/log/nginx/license_access.log
tail -f /var/log/nginx/license_error.log
```

### Check Cron Status
```bash
crontab -l
```

## Troubleshooting

### License check not running
```bash
# Check if cron is running
systemctl status cron

# Manually run license checker
/var/www/license/bin/check-licenses.php

# Check cron logs
grep CRON /var/log/syslog
```

### Database connection failed
```bash
# Verify database credentials in .env
cat .env

# Test MySQL connection
mysql -u license_user -p license_server
```

### Nginx 502 Bad Gateway
```bash
# Check PHP-FPM status
systemctl status php8.1-fpm

# Restart PHP-FPM
systemctl restart php8.1-fpm
```

## Integration with WhatsApp Bot

The WhatsApp Bot automatically integrates with this license server.

Bot configuration in `/var/www/whatsbot/.env`:
```bash
LICENSE_SERVER_URL=https://your-license-domain.com
LICENSE_CHECK_ENABLED=true
SITE_DOMAIN=customer-domain.com
```

On first run, the bot will:
1. Auto-register with the license server
2. Receive 3-day trial license
3. Display trial banner in admin panel
4. Send heartbeat every hour

## Moving to New Server

To move the license server to a new server:

1. **Backup database:**
```bash
mysqldump -u root -p license_server > license_backup.sql
```

2. **Clone repository on new server:**
```bash
git clone https://github.com/mmdelhajj/lic.git /var/www/license
cd /var/www/license
```

3. **Run installation:**
```bash
sudo ./install.sh
```

4. **Import backup:**
```bash
mysql -u root -p license_server < license_backup.sql
```

5. **Update DNS** to point to new server

## License

Proprietary - All rights reserved

## Support

For support, contact: [your-email@example.com]

---

**Built with Claude Code**
