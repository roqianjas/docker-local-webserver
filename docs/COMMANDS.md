# ⚡ Quick Reference — Semua Command Penting

---

## Docker Compose

```powershell
# Start semua containers
docker compose up -d

# Stop semua containers
docker compose down

# Restart semua
docker compose restart

# Restart container spesifik
docker compose restart php84
docker compose restart nginx

# Lihat status semua containers
docker compose ps

# Lihat logs (real-time)
docker compose logs -f
docker compose logs -f php84
docker compose logs -f mariadb

# Rebuild images
docker compose build --no-cache
docker compose build php84 --no-cache

# Rebuild + restart
docker compose up -d --build
```

---

## Masuk ke Container

```powershell
docker compose exec php84 sh       # PHP 8.4
docker compose exec php83 sh       # PHP 8.3
docker compose exec php74 sh       # PHP 7.4
docker compose exec node sh        # Node.js
docker compose exec mariadb sh     # MariaDB
docker compose exec redis sh       # Redis
docker compose exec nginx sh       # Nginx
```

---

## PHP / Composer / Artisan

```powershell
# Cek PHP version
docker compose exec php84 php -v

# Cek extensions
docker compose exec php84 php -m

# Composer
docker compose exec php84 sh -c "cd /var/www/html/myapp.test && composer install"
docker compose exec php84 sh -c "cd /var/www/html/myapp.test && composer require package/name"

# Artisan (Laravel)
docker compose exec php84 sh -c "cd /var/www/html/myapp.test && php artisan migrate"
docker compose exec php84 sh -c "cd /var/www/html/myapp.test && php artisan make:model User -mcr"
docker compose exec php84 sh -c "cd /var/www/html/myapp.test && php artisan tinker"
docker compose exec php84 sh -c "cd /var/www/html/myapp.test && php artisan queue:work"
docker compose exec php84 sh -c "cd /var/www/html/myapp.test && php artisan test"
```

---

## Node.js / npm / pm2

```powershell
# Cek version
docker compose exec node node -v
docker compose exec node npm -v

# npm commands (dari dalam container)
docker compose exec node sh -c "cd /var/www/html/nextapp.test && npm install"
docker compose exec node sh -c "cd /var/www/html/nextapp.test && npm run dev -- --host 0.0.0.0"
docker compose exec node sh -c "cd /var/www/html/nextapp.test && npm run build"

# pm2
docker compose exec node pm2 status
docker compose exec node pm2 logs
docker compose exec node pm2 restart all
```

---

## MariaDB

```powershell
# Masuk ke MySQL CLI
docker compose exec mariadb mariadb -u root -psecret

# Jalankan SQL langsung
docker compose exec mariadb mariadb -u root -psecret -e "SHOW DATABASES;"
docker compose exec mariadb mariadb -u root -psecret -e "CREATE DATABASE newdb;"

# Import SQL file
docker compose exec -T mariadb mariadb -u root -psecret dbname < dump.sql

# Export/Backup
docker compose exec mariadb mariadb-dump -u root -psecret dbname > backup.sql
docker compose exec mariadb mariadb-dump -u root -psecret --all-databases > all.sql
```

---

## Redis

```powershell
# Redis CLI
docker compose exec redis redis-cli

# Test connection
docker compose exec redis redis-cli ping

# Monitor real-time
docker compose exec redis redis-cli monitor

# Flush cache
docker compose exec redis redis-cli FLUSHALL

# Lihat semua keys
docker compose exec redis redis-cli KEYS "*"
```

---

## Nginx

```powershell
# Reload config (tanpa restart)
docker compose exec nginx nginx -s reload

# Test config valid
docker compose exec nginx nginx -t

# Lihat active connections
docker compose exec nginx nginx -s status
```

---

## Site Management

### Via Dashboard (Direkomendasikan)
1. Buka http://localhost:8000
2. Klik **New Project** → isi nama → Create
3. HTTPS otomatis terpasang via NPM API ✅
4. Hapus project: klik 🗑️ di card project

### Via Terminal
```powershell
# Buat site PHP baru
.\create-site.ps1 -Name "myapp" -PhpVersion "8.4"
.\create-site.ps1 -Name "legacy" -PhpVersion "7.4"
.\create-site.ps1 -Name "laravel" -PhpVersion "8.3" -Type "laravel"

# Buat site Node.js baru
.\create-node-site.ps1 -Name "nextapp" -Framework "nextjs"
.\create-node-site.ps1 -Name "viteapp" -Framework "vite"
```

> **Note:** Script terminal hanya membuat folder & Nginx config. Untuk SSL otomatis, gunakan Dashboard.

---

## Cron & Worker

```powershell
# Restart cron
docker compose restart php-cron

# Cek crontab
docker compose exec php-cron crontab -l

# Worker status
docker compose exec php-worker supervisorctl status

# Restart workers
docker compose exec php-worker supervisorctl restart all
```

---

## Xdebug

```powershell
# Enable (edit .env: XDEBUG_ENABLED=true, lalu:)
docker compose restart php84

# Disable (edit .env: XDEBUG_ENABLED=false, lalu:)
docker compose restart php84

# Verify
docker compose exec php84 php -m | grep xdebug
```

---

## Maintenance

```powershell
# Update semua images
docker compose pull
docker compose up -d

# Cleanup unused images/volumes
docker system prune -a
docker volume prune

# Lihat disk usage Docker
docker system df

# Restart Docker environment
docker compose down && docker compose up -d
```

---

## Emergency

```powershell
# Force restart semua
docker compose down && docker compose up -d --force-recreate

# Rebuild dari awal (TANPA hapus data)
docker compose down
docker compose build --no-cache
docker compose up -d

# RESET TOTAL (⚠️ HAPUS SEMUA DATA!)
docker compose down -v
Remove-Item -Recurse -Force data\*
docker compose up -d --build
```
