# Yfindir — Local Development Setup Guide

## Prerequisites Installation

### 1. Install mise (manages PHP + Node versions)

```bash
curl https://mise.jdx.dev/install.sh | sh
```

Add to your shell:
```bash
echo 'eval "$(~/.local/bin/mise activate bash)"' >> ~/.bashrc
echo 'export PATH="$HOME/.local/share/mise/shims:$PATH"' >> ~/.bashrc
source ~/.bashrc
```

Verify:
```bash
mise --version
```

### 2. Install PHP 8.3 + extensions

```bash
# Install PHP build dependencies
sudo apt update
sudo apt install -y autoconf automake bison freetype-dev gettext \
    libfreetype6 libjpeg-dev libpng-dev libxml2-dev libzip-dev libssl-dev \
    libicu-dev libcurl4-openssl-dev pkg-config re2c zlib1g-dev libonig-dev \
    libgd-dev libwebp-dev

# Install PHP 8.3 via mise
mise install php@8.3
mise use --global php@8.3

# Install required PHP extensions via pecl
pecl install redis imagick

# Add extensions to php.ini
PHP_INI=$(mise where php@8.3)/conf.d/extensions.ini
cat << 'EOF' > "$PHP_INI"
extension=intl
extension=gd
extension=zip
extension=curl
extension=mbstring
extension=mysqli
extension=pdo_mysql
extension=bcmath
extension=redis
extension=imagick
zend_extension=opcache
opcache.enable=1
opcache.enable_cli=1
opcache.jit=tracing
opcache.jit_buffer_size=64M
upload_max_filesize=64M
post_max_size=64M
EOF

# Verify
php -v
php -m | grep -E "intl|gd|zip|curl|mbstring|pdo_mysql|bcmath|redis|imagick|opcache"
```

### 3. Install Node.js 20

```bash
mise install node@20
mise use --global node@20
node -v
npm -v
```

### 4. Install MariaDB

```bash
sudo apt update
sudo apt install -y mariadb-server mariadb-client
sudo systemctl start mariadb
sudo systemctl enable mariadb

# Secure installation
sudo mariadb-secure-installation
```

Create database:
```bash
sudo mariadb -u root -p
```
```sql
CREATE DATABASE yfindir CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'yfindir'@'localhost' IDENTIFIED BY 'your_password';
GRANT ALL PRIVILEGES ON yfindir.* TO 'yfindir'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### 5. Install Caddy

```bash
# On elementary OS / Ubuntu-based
sudo apt install -y snapd
sudo snap install caddy

# Or via official repo:
# See https://caddyserver.com/docs/install

# Verify
caddy version
```

### 6. Install Composer (already installed, verify)

```bash
composer --version
```

---

## Project Creation

```bash
cd ~/Projects  # or wherever you keep projects

# Create Laravel 12 project
composer create-project laravel/laravel yfindir

cd yfindir

# Configure .env
cp .env.example .env
php artisan key:generate
```

Edit `.env`:
```env
APP_NAME="Yfindir"
APP_ENV=local
APP_URL=http://yfindir.test
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=yfindir
DB_USERNAME=yfindir
DB_PASSWORD=your_password
```

### Install packages

```bash
# Filament v5
composer require filament/filament:"^5.0"

# Livewire (comes with Filament, but verify)
composer require livewire/livewire:"^4.0"

# Spatie Permission
composer require spatie/laravel-permission:"^6.0"
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"

# Spatie Translatable
composer require spatie/laravel-translatable:"^6.0"

# Filament Translatable Plugin
composer require filament/spatie-laravel-translatable-plugin:"^5.0"

# Laravel Scout (database driver for search)
composer require laravel/scout:"^10.0"

# Laravel Breeze (auth scaffolding)
composer require laravel/breeze:"^2.0" --dev
php artisan breeze:install blade

# Intervention Image (for upload compression)
composer require intervention/image:"^3.0"
```

### Frontend

```bash
npm install
npm run dev  # leave running in a terminal tab
```

---

## Caddy Configuration

Create `/etc/caddy/Caddyfile` (or wherever your Caddy config lives):

```caddyfile
yfindir.test {
    root * /home/$USER/Projects/yfindir/public
    php_fastcgi unix//run/php/php8.3-fpm.sock
    file_server
    encode gzip zstd
    header Cache-Control "no-cache"
}
```

**Important:** The PHP-FPM socket path depends on how you installed PHP. With mise,
you may need to run PHP-FPM manually. Alternative Caddyfile using TCP:

```caddyfile
yfindir.test {
    root * /home/$USER/Projects/yfindir/public
    php_fastcgi 127.0.0.1:9000
    file_server
    encode gzip zstd
    header {
        Cache-Control "no-cache"
    }
}
```

### Start PHP-FPM (if not using a system socket)

```bash
# Find your php-fpm binary
PHP_FPM=$(mise where php@8.3)/bin/php-fpm

# Run it
$PHP_FPM -F -d listen=127.0.0.1:9000

# Or as a background service:
$PHP_FPM -d listen=127.0.0.1:9000
```

### Start Caddy

```bash
sudo caddy run --config /etc/caddy/Caddyfile
```

Caddy auto-generates SSL certs for `yfindir.test` so you can access your site at:

```
https://yfindir.test
```

---

## Install Filament

```bash
php artisan filament:install --panels
```

This creates:
- `app/Providers/Filament/AdminPanelProvider.php`
- Filament panel accessible at `/admin`

The installer will ask:
- Path: `admin` (keep default)
- You can run `php artisan filament:install --panels` again to create the member panel at `dashboard`

---

## Verify Everything Works

```bash
# Run migrations
php artisan migrate

# Run the seeder (once we create it)
php artisan db:seed

# Test the site
# Visit https://yfindir.test — should show Laravel welcome page
# Visit https://yfindir.test/admin — should show Filament login
```

---

## Daily Workflow

```bash
# Terminal 1: Vite hot-reload
cd ~/Projects/yfindir && npm run dev

# Terminal 2: PHP-FPM (if running manually via mise)
PHP_FPM=$(mise where php@8.3)/bin/php-fpm
$PHP_FPM -d listen=127.0.0.1:9000

# Terminal 3: Caddy
sudo caddy run --config /etc/caddy/Caddyfile

# Terminal 4: Your main working terminal
cd ~/Projects/yfindir
php artisan tinker
php artisan make:model Company -mfsc  # model + migration + factory + seeder + controller
```

---

## .gitignore Additions

Add these to your project `.gitignore`:

```
# Caddy
Caddyfile.local

# mise
.mise.toml

# Deploy
.env.production
```

---

## Directory Structure After Setup

```
~/Projects/yfindir/
├── app/
├── bootstrap/
├── config/
├── database/
├── lang/
├── public/
├── resources/
├── routes/
├── storage/
├── tests/
├── .env               ← local dev config
├── .env.example
├── Caddyfile          ← committed to repo for reference
├── composer.json
├── package.json
├── phpunit.xml
└── artisan
```

---

## Quick Reference Card

| Task | Command |
|---|---|
| Start dev environment | `npm run dev` + PHP-FPM + Caddy |
| Run migrations | `php artisan migrate` |
| Create model + migration | `php artisan make:model Company -mfsc` |
| Create Filament resource | `php artisan make:filament-resource Company` |
| Create Livewire component | `php artisan make:livewire CatalogSearch` |
| Clear all caches | `php artisan optimize:clear` |
| Tinker | `php artisan tinker` |
| Run tests | `php artisan test` |
| Generate fake data | `php artisan db:seed --class=DemoDataSeeder` |
