# VerityVote — Production Deployment Guide

This guide provides step-by-step instructions for deploying the **VerityVote** Institutional College Voting System into a production Linux (Ubuntu/Debian/RHEL) or enterprise hosting environment.

---

## 1. Production Architecture Overview

VerityVote follows a multi-tier MVC architecture engineered in native PHP 8.2+ and MariaDB/MySQL 8.0+.

```
                    Internet / Campus WAN
                             │
                             ▼ (HTTPS / 443)
                 ┌───────────────────────┐
                 │ Nginx or Apache 2.4   │  TLS Termination, Security Headers
                 └───────────┬───────────┘
                             │
                             ▼ FastCGI / mod_php
                 ┌───────────────────────┐
                 │ PHP 8.2+ FPM Runtime  │  Front Controller (public/index.php)
                 └───────────┬───────────┘
                             │
                             ▼ MySQLi (TCP / Socket)
                 ┌───────────────────────┐
                 │ MariaDB 10.4+ / MySQL │  ACID Engine, InnoDB, utf8mb4
                 └───────────────────────┘
```

---

## 2. Server & Environment Prerequisites

| Component | Minimum Specification | Recommended Production Spec |
|---|---|---|
| **OS** | Linux (Ubuntu 22.04 LTS / Debian 12 / RHEL 9) | Ubuntu 22.04 LTS |
| **PHP** | PHP 8.2.0+ with `php-mysqli`, `php-mbstring`, `php-curl`, `php-json` | PHP 8.2 or 8.3 FPM |
| **Database** | MariaDB 10.4.32+ or MySQL 8.0.30+ | MariaDB 10.11 LTS or MySQL 8.0 |
| **Web Server** | Apache 2.4 (with `mod_rewrite`, `mod_headers`) or Nginx 1.22+ | Apache 2.4 or Nginx |
| **Storage** | 20 GB SSD | 50 GB NVMe SSD |
| **Memory** | 2 GB RAM | 4 GB+ RAM |

---

## 3. Database Provisioning & Least-Privilege Setup

Never connect the application using the database `root` superuser. Create a dedicated database user with least-privilege grants.

### 3.1 Create Database and User
Log into MariaDB/MySQL as `root`:
```sql
CREATE DATABASE `verityvote_prod` 
  CHARACTER SET utf8mb4 
  COLLATE utf8mb4_unicode_ci;

CREATE USER 'verityvote_app'@'127.0.0.1' IDENTIFIED BY 'STRONG_RANDOM_PASSWORD_HERE';

-- Grant required operational privileges only:
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, INDEX, ALTER, LOCK TABLES 
  ON `verityvote_prod`.* TO 'verityvote_app'@'127.0.0.1';

FLUSH PRIVILEGES;
```

### 3.2 Initialize Core Schema and Migrations
Run the schema setup script or import via CLI:
```bash
mysql -u verityvote_app -p verityvote_prod < database/schema.sql
```
Then verify table status using the database diagnostic utility:
```bash
php bin/check_db.php verityvote_prod
```

---

## 4. Application Configuration

Copy the example configuration to the live production configuration file:
```bash
cp config/config.example.php config/config.php
chmod 640 config/config.php
```

Edit `config/config.php` with production values:
```php
return [
    'app' => [
        'name' => 'VerityVote',
        'tagline' => 'Institutional College Voting System',
        'env' => 'production',
        'debug' => false,                 // STRICTLY false in production
        'base_url' => '',                 // Empty string when DocumentRoot points to public/
        'timezone' => 'Asia/Kolkata',     // Adjust to your campus timezone
    ],

    'database' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'database' => 'verityvote_prod',
        'username' => 'verityvote_app',
        'password' => 'STRONG_RANDOM_PASSWORD_HERE',
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
    ],

    'session' => [
        'cookie_name' => '__Host-VERITYVOTE_SESSID',
        'idle_timeout' => 1800,           // 30 minutes idle timeout
        'absolute_timeout' => 14400,      // 4 hours absolute expiration
        'cookie_lifetime' => 0,           // Session cookie
        'cookie_path' => '/',
        'cookie_domain' => '',
        'cookie_secure' => true,          // Enforce HTTPS-only transmission
        'cookie_httponly' => true,        // Prevent JavaScript access
        'cookie_samesite' => 'Lax',       // CSRF mitigation
    ],

    'security' => [
        'max_login_attempts' => 5,
        'lockout_duration_seconds' => 900, // 15 minutes lockout
        'temp_password_expiry_hours' => 24,
        'hmac_secret' => 'GENERATE_A_64_CHAR_HEX_KEY_HERE_USING_OPENSSL',
        'allowed_image_mime_types' => ['image/jpeg', 'image/png', 'image/webp'],
        'allowed_image_extensions' => ['jpg', 'jpeg', 'png', 'webp'],
        'max_upload_bytes' => 2097152,     // 2MB max image upload
    ],

    'storage' => [
        'logs_path' => dirname(__DIR__) . '/storage/logs',
        'uploads_path' => dirname(__DIR__) . '/storage/uploads',
        'candidates_dir' => dirname(__DIR__) . '/storage/uploads/candidates',
    ]
];
```

> [!IMPORTANT]
> Generate a cryptographically strong HMAC secret for receipt verification:
> `php -r "echo bin2hex(random_bytes(32)) . PHP_EOL;"`

---

## 5. Web Server Configuration

### 5.1 Apache 2.4 VirtualHost (Recommended)
Set the Apache `DocumentRoot` directly to the `public/` directory:

```apache
<VirtualHost *:80>
    ServerName vote.yourcollege.edu
    ServerAdmin sysadmin@yourcollege.edu

    # Redirect all HTTP requests to HTTPS
    RewriteEngine On
    RewriteCond %{HTTPS} off
    RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
</VirtualHost>

<VirtualHost *:443>
    ServerName vote.yourcollege.edu
    ServerAdmin sysadmin@yourcollege.edu
    DocumentRoot /var/www/verityvote/public

    SSLEngine on
    SSLCertificateFile /etc/letsencrypt/live/vote.yourcollege.edu/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/vote.yourcollege.edu/privkey.pem
    SSLProtocol all -SSLv3 -TLSv1 -TLSv1.1
    SSLCipherSuite HIGH:!aNULL:!MD5:!3DES

    <Directory /var/www/verityvote/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    # Block access to internal directories as defense-in-depth
    <Directory /var/www/verityvote/config>
        Require all denied
    </Directory>
    <Directory /var/www/verityvote/storage>
        Require all denied
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/verityvote_error.log
    CustomLog ${APACHE_LOG_DIR}/verityvote_access.log combined
</VirtualHost>
```

### 5.2 Nginx Configuration Alternative
```nginx
server {
    listen 80;
    server_name vote.yourcollege.edu;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name vote.yourcollege.edu;
    root /var/www/verityvote/public;
    index index.php;

    ssl_certificate /etc/letsencrypt/live/vote.yourcollege.edu/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/vote.yourcollege.edu/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;

    # Security Headers
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-Frame-Options "DENY" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    add_header Permissions-Policy "camera=(), microphone=(), geolocation=()" always;

    # Deny direct access to hidden files and internal directories
    location ~ /\.(?!well-known) {
        deny all;
    }

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

---

## 6. Filesystem Permissions

Set ownership to the web server process user (`www-data` or `apache`):

```bash
cd /var/www/verityvote

# Set base permissions
chown -R www-data:www-data .
find . -type d -exec chmod 755 {} \;
find . -type f -exec chmod 644 {} \;

# Protect sensitive configuration
chmod 640 config/config.php

# Storage and logs require write access
chmod -R 775 storage/logs
chmod -R 775 storage/uploads
chmod -R 770 database/backups
```

---

## 7. Provision Initial Administrator Account

Run the secure CLI administrator creation utility:
```bash
php bin/create_admin.php
```
Follow the interactive prompt to define:
- **Administrator ID** (e.g., `ADMIN_OFFICE_01`)
- **Full Name** (e.g., `Chief Election Officer`)
- **Institutional Email** (e.g., `election.officer@yourcollege.edu`)

The utility will generate a secure one-time temporary password. Log into `/login/admin` to complete the mandatory first-time password rotation.

---

## 8. Post-Deployment Verification Steps

1. **Run PHP Linter**:
   ```bash
   php bin/lint_all.php
   ```
2. **Verify Database Health**:
   ```bash
   php bin/check_db.php
   ```
3. **Execute Integration Test Suite**:
   ```bash
   php tests/run_tests.php
   ```
4. **Test Media Gateway & Security**:
   Ensure `http://vote.yourcollege.edu/config/config.php` returns **403 Forbidden**.
5. **Verify HTTPS Redirection**:
   Confirm all HTTP requests redirect to HTTPS with valid TLS certificates.
6. **Set up Scheduled Database Backups**:
   Configure cron job as detailed in [backup-and-recovery.md](backup-and-recovery.md).
