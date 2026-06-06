# 🔧 Troubleshooting

---

## Port Already in Use

**Gejala:** `Error: bind: address already in use`

**Solusi:**
```powershell
# Cari proses yang menggunakan port
netstat -ano | findstr :80
netstat -ano | findstr :3306

# Kill proses (ganti PID)
taskkill /PID 1234 /F

# Atau ubah port di .env
NPM_HTTP_PORT=8080
MARIADB_PORT=3307
```

**Common culprits:** Laragon, Herd, XAMPP, IIS, Apache, Skype (port 80)

---

## Container Won't Start

**Solusi:**
```powershell
# Lihat logs container yang error
docker compose logs php84
docker compose logs mariadb

# Rebuild container
docker compose up -d --build php84

# Nuclear option: rebuild semua dari awal
docker compose down
docker compose up -d --build
```

---

## PHP Error: Class Not Found

**Gejala:** `Class 'Redis' not found`, `Class 'Imagick' not found`

**Solusi:**
1. Cek extension terinstall:
   ```powershell
   docker compose exec php84 php -m
   ```
2. Jika tidak ada, tambahkan di `dockerfiles/php.Dockerfile`
3. Rebuild: `docker compose build php84 --no-cache && docker compose up -d`

---

## Database Connection Refused

**Gejala:** `SQLSTATE[HY000] [2002] Connection refused`

**Solusi:**
1. Pastikan container MariaDB running:
   ```powershell
   docker compose ps mariadb
   ```
2. Pastikan host menggunakan `mariadb` (bukan `localhost`):
   ```env
   DB_HOST=mariadb  # ✅ Benar (dari container)
   DB_HOST=localhost # ❌ Salah (dari container)
   ```
3. Tunggu MariaDB selesai startup (~10-30 detik pertama kali)

---

## Redis Connection Refused

**Solusi:**
1. Cek container: `docker compose ps redis`
2. Test koneksi: `docker compose exec redis redis-cli ping`
3. Dari PHP/Laravel, gunakan host `redis` (bukan `localhost`)

---

## File Permission Issues

**Gejala:** `Permission denied` saat write file dari PHP

**Solusi:**
```powershell
# Set ownership di container
docker compose exec php84 chown -R www-data:www-data /var/www/html/myapp.test

# Atau set permission
docker compose exec php84 chmod -R 775 /var/www/html/myapp.test/storage
docker compose exec php84 chmod -R 775 /var/www/html/myapp.test/bootstrap/cache
```

---

## Slow File Access (Windows Docker Performance)

**Gejala:** Page load lambat, file watching lambat

**Penyebab:** Bind mount Windows NTFS → Linux container memang lebih lambat dari native

**Solusi:**
1. **Pastikan WSL2 backend** (bukan Hyper-V):
   - Docker Desktop → Settings → General → ✅ Use the WSL 2 based engine

2. **Optimize WSL2 memory** — buat file `C:\Users\USERNAME\.wslconfig`:
   ```ini
   [wsl2]
   memory=4GB
   processors=4
   swap=2GB
   ```

3. **Untuk Node.js hot reload** — gunakan polling:
   ```env
   WATCHPACK_POLLING=true
   CHOKIDAR_USEPOLLING=true
   ```

---

## HTTPS Certificate Not Trusted

**Gejala:** Browser warning "Your connection is not private"

**Solusi:**
1. Jalankan ulang: `.\mkcert.exe -install`
2. **Restart browser sepenuhnya** (tutup semua window, bukan cuma tab)
3. Untuk Firefox: Settings → Privacy → Certificates → View Certificates → Import `mkcert` CA
4. Pastikan sertifikat di NPM adalah **per-domain** (bukan wildcard `*.test` — Chrome memblokir wildcard pada TLD `.test`)

---

## Dashboard: "NPM Auth Failed"

**Gejala:** Notifikasi error saat buat project di Dashboard

**Solusi:**
1. Pastikan Anda sudah **login ke NPM** di http://localhost:81 dan mengganti password default
2. Buka Dashboard → ⚙️ Settings → masukkan email & password NPM yang **sama persis**
3. Klik Save

---

## Dashboard: "NPM API Error"

**Gejala:** Project dibuat tapi SSL tidak terpasang (HTTP Only di NPM)

**Solusi:**
1. Cek apakah `config/ssl/ca/` berisi `rootCA.pem` dan `rootCA-key.pem`
2. Cek apakah `config/ssl/mkcert` ada (binary mkcert Linux)
3. Jika tidak ada, jalankan ulang `.\setup.ps1`
4. Hapus project di Dashboard, lalu buat ulang

---

## Nginx 502 Bad Gateway

**Gejala:** Browser menampilkan "502 Bad Gateway"

**Penyebab:** PHP-FPM container tidak running atau tidak merespon

**Solusi:**
```powershell
# Cek PHP container
docker compose ps php84

# Restart
docker compose restart php84 nginx

# Cek Nginx config valid
docker compose exec nginx nginx -t
```

---

## Mailpit Not Receiving Emails

**Solusi:**
1. Pastikan app menggunakan SMTP settings:
   ```env
   MAIL_MAILER=smtp
   MAIL_HOST=mailpit
   MAIL_PORT=1025
   MAIL_USERNAME=null
   MAIL_PASSWORD=null
   MAIL_ENCRYPTION=null
   ```
2. Cek container: `docker compose ps mailpit`
3. Buka Mailpit: http://localhost:8025

---

## Xdebug Not Connecting

**Solusi:**
1. Pastikan `XDEBUG_ENABLED=true` di `.env`
2. Restart: `docker compose restart php84`
3. Verifikasi: `docker compose exec php84 php -m | grep xdebug`
4. Pastikan VS Code listen di port 9003
5. Cek `pathMappings` di launch.json

---

## Node.js Hot Reload Not Working

**Solusi:**
1. Pastikan dev server bind ke `0.0.0.0`:
   ```bash
   npm run dev -- --host 0.0.0.0
   ```
2. Enable polling (lihat section "Slow File Access" di atas)

---

## Docker Desktop WSL2 Issues

**Gejala:** Docker Desktop tidak bisa start, WSL error

**Solusi:**
```powershell
# Restart WSL
wsl --shutdown
# Tunggu 5 detik, lalu buka Docker Desktop

# Update WSL
wsl --update

# Jika masih error, reset Docker Desktop
# Settings → Troubleshoot → Reset to factory defaults
```

---

## Reset Seluruh Environment

**⚠️ WARNING: Ini akan menghapus semua data (database, cache, dll)!**

```powershell
# Stop semua container
docker compose down -v

# Hapus data
Remove-Item -Recurse -Force c:\docker\data\*

# Rebuild dari awal
docker compose up -d --build
```

**Jika hanya ingin rebuild tanpa hapus data:**
```powershell
docker compose down
docker compose up -d --build
```
