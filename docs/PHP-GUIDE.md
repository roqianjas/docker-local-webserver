# 🐘 Panduan PHP

---

## PHP Versions yang Tersedia

| Container | Version | Keterangan |
|-----------|---------|------------|
| `php84` | PHP 8.4 | **Default** — Latest, recommended |
| `php83` | PHP 8.3 | Stable, widely supported |
| `php82` | PHP 8.2 | LTS compatible |
| `php81` | PHP 8.1 | Older projects |
| `php74` | PHP 7.4 | Legacy only (EOL) |

---

## PHP Extensions Terinstall

Semua versi PHP (kecuali 7.4 yang sedikit berbeda) memiliki extensions:

| Extension | Fungsi |
|-----------|--------|
| `pdo_mysql` | Database PDO driver |
| `mysqli` | MySQL improved driver |
| `gd` | Image processing (freetype, jpeg, webp) |
| `zip` | ZIP compression |
| `intl` | Internationalization |
| `opcache` | Bytecode caching |
| `mbstring` | Multibyte string |
| `xml` | XML parsing |
| `bcmath` | Arbitrary precision math |
| `soap` | SOAP web services |
| `exif` | Image metadata |
| `pcntl` | Process control |
| `sodium` | Cryptography |
| `sockets` | Socket communication |
| `redis` | Redis client (PECL) |
| `xdebug` | Debugger (PECL, disabled by default) |

---

## Menjalankan Composer

```powershell
# Dari Windows — jalankan di container PHP
docker compose exec php84 composer install
docker compose exec php84 composer require laravel/framework
docker compose exec php84 composer update

# Untuk project spesifik
docker compose exec php84 sh -c "cd /var/www/html/myapp.test && composer install"

# Menggunakan PHP version berbeda
docker compose exec php82 composer install
```

---

## Menjalankan Artisan (Laravel)

```powershell
# Dari Windows
docker compose exec php84 sh -c "cd /var/www/html/myapp.test && php artisan migrate"
docker compose exec php84 sh -c "cd /var/www/html/myapp.test && php artisan make:model User -m"
docker compose exec php84 sh -c "cd /var/www/html/myapp.test && php artisan tinker"

# Atau masuk ke container dulu
docker compose exec php84 sh
cd /var/www/html/myapp.test
php artisan migrate
```

---

## Switch PHP Version per Site

Edit file Nginx config untuk site tersebut:

```powershell
# Buka config
notepad c:\docker\config\nginx\sites\myapp.test.conf
```

Ubah baris `fastcgi_pass`:
```nginx
# Dari PHP 8.4:
fastcgi_pass php84:9000;

# Ke PHP 8.2:
fastcgi_pass php82:9000;

# Atau PHP 7.4:
fastcgi_pass php74:9000;
```

Lalu reload Nginx:
```powershell
docker compose exec nginx nginx -s reload
```

---

## Xdebug — Step-by-step Debugging

### Enable Xdebug

1. Edit `.env`:
   ```env
   XDEBUG_ENABLED=true
   ```

2. Restart PHP container:
   ```powershell
   docker compose restart php84
   ```

3. Verifikasi:
   ```powershell
   docker compose exec php84 php -m | grep xdebug
   ```

### VS Code Setup

1. Install extension: **PHP Debug** (xdebug.php-debug)

2. Buat `.vscode/launch.json` di project Anda:
   ```json
   {
     "version": "0.2.0",
     "configurations": [
       {
         "name": "Listen for Xdebug (Docker)",
         "type": "php",
         "request": "launch",
         "port": 9003,
         "pathMappings": {
           "/var/www/html/myapp.test": "${workspaceFolder}"
         }
       }
     ]
   }
   ```

3. Set breakpoint di VS Code → Tekan F5 → Refresh browser

### Disable Xdebug (saat tidak debugging)

```env
XDEBUG_ENABLED=false
```
```powershell
docker compose restart php84
```

> **Penting:** Xdebug memperlambat PHP ~30-50%. Selalu disable saat tidak debugging!

---

## Menambah PHP Extension Baru

1. Edit `c:\docker\dockerfiles\php.Dockerfile`

2. Tambahkan extension:
   ```dockerfile
   # Untuk extension bawaan PHP
   RUN docker-php-ext-install imagick
   
   # Untuk extension PECL
   RUN pecl install mongodb && docker-php-ext-enable mongodb
   ```

3. Rebuild:
   ```powershell
   docker compose build php84 --no-cache
   docker compose up -d php84
   ```

---

## php.ini Settings

File: `c:\docker\config\php\php.ini`

Setelah edit, restart PHP container:
```powershell
docker compose restart php84 php83 php82 php81
```

Settings penting untuk development:
```ini
display_errors = On          # Tampilkan error di browser
error_reporting = E_ALL      # Report semua error
memory_limit = 512M          # Batas memory per-request
upload_max_filesize = 100M   # Max upload file
max_execution_time = 300     # Max waktu eksekusi (5 menit)
date.timezone = Asia/Jakarta # Timezone
```
