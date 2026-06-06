# ⏰ Panduan Cron Jobs & Workers

---

## Overview

| Komponen | Container | Fungsi |
|----------|-----------|--------|
| **PHP Cron** | `php-cron` | Menjalankan scheduled tasks (crontab) |
| **PHP Worker** | `php-worker` | Menjalankan queue jobs terus-menerus (Supervisor) |
| **Node.js pm2** | `node` | Process manager untuk Node.js workers & cron |

---

## PHP Cron — Scheduled Tasks

### Config File
`c:\docker\config\cron\crontab`

### Setup Laravel Scheduler

1. Edit crontab:
   ```powershell
   notepad c:\docker\config\cron\crontab
   ```

2. Uncomment/tambah baris:
   ```cron
   * * * * * cd /var/www/html/myapp.test && php artisan schedule:run >> /var/log/cron/schedule.log 2>&1
   ```

3. Restart:
   ```powershell
   docker compose restart php-cron
   ```

4. Monitor logs:
   - Via Dozzle: http://localhost:9999 → pilih container `php-cron`
   - Via file: `c:\docker\logs\cron\schedule.log`

### Multiple Projects
```cron
* * * * * cd /var/www/html/tokoku.test && php artisan schedule:run >> /var/log/cron/tokoku.log 2>&1
* * * * * cd /var/www/html/api.test && php artisan schedule:run >> /var/log/cron/api.log 2>&1
```

### Contoh Cron Jobs Lainnya
```cron
# WordPress wp-cron (setiap 5 menit)
*/5 * * * * cd /var/www/html/blog.test && php wp-cron.php >> /var/log/cron/wordpress.log 2>&1

# Backup database setiap hari jam 2 pagi
0 2 * * * cd /var/www/html/tokoku.test && php artisan backup:run >> /var/log/cron/backup.log 2>&1

# Clear expired cache setiap jam
0 * * * * cd /var/www/html/tokoku.test && php artisan cache:clear >> /var/log/cron/cache.log 2>&1

# Custom PHP script
*/10 * * * * php /var/www/html/myapp.test/scripts/check-status.php >> /var/log/cron/status.log 2>&1
```

### Format Crontab
```
┌───────────── menit (0-59)
│ ┌───────────── jam (0-23)
│ │ ┌───────────── hari bulan (1-31)
│ │ │ ┌───────────── bulan (1-12)
│ │ │ │ ┌───────────── hari minggu (0-7, 0/7 = Minggu)
│ │ │ │ │
* * * * * command
```

---

## PHP Worker — Queue Jobs

### Config File
`c:\docker\config\supervisor\worker.conf`

### Setup Laravel Queue

1. Edit `.env` di `c:\docker\`:
   ```env
   WORKER_PROJECT=myapp.test
   WORKER_QUEUE_CONNECTION=redis
   ```

2. Pastikan Laravel project sudah dikonfigurasi:
   ```env
   # Di .env Laravel project (c:\docker\sites\myapp.test\.env)
   QUEUE_CONNECTION=redis
   REDIS_HOST=redis
   REDIS_PORT=6379
   ```

3. Restart worker:
   ```powershell
   docker compose restart php-worker
   ```

### Monitoring Worker

```powershell
# Cek status Supervisor
docker compose exec php-worker supervisorctl status

# Lihat logs
docker compose exec php-worker supervisorctl tail -f queue-worker:queue-worker_00

# Restart workers
docker compose exec php-worker supervisorctl restart all

# Stop workers
docker compose exec php-worker supervisorctl stop queue-worker:*
```

Atau monitor via **Dozzle**: http://localhost:9999 → container `php-worker`

### Scaling Workers

Edit `c:\docker\config\supervisor\worker.conf`:
```ini
# Ubah jumlah proses worker
numprocs=4  ; default 2, naikkan jika banyak job
```

Restart:
```powershell
docker compose restart php-worker
```

### Restart Worker Setelah Deploy

Setelah deploy kode baru, restart worker agar load kode terbaru:
```powershell
docker compose exec php-worker supervisorctl restart all
```

---

## Node.js — pm2 Workers & Cron

### Setup pm2 Worker

```bash
# Masuk ke container
docker compose exec node sh

# Jalankan worker
cd /var/www/html/myapi.test
pm2 start worker.js --name "email-worker" --instances 2
```

### pm2 Cron

Buat `ecosystem.config.js`:
```javascript
module.exports = {
  apps: [
    {
      name: "api-server",
      script: "npm",
      args: "start",
      autorestart: true,
    },
    {
      name: "cleanup-job",
      script: "./jobs/cleanup.js",
      cron_restart: "0 */6 * * *",  // Setiap 6 jam
      autorestart: false,
    },
    {
      name: "email-worker",
      script: "./workers/email.js",
      instances: 2,
      autorestart: true,
    }
  ]
}
```

```bash
pm2 start ecosystem.config.js
pm2 save  # Simpan config agar persist
```

### pm2 Monitoring
```bash
pm2 status         # Status semua proses
pm2 logs           # Real-time logs
pm2 monit          # CPU/memory monitor
pm2 restart all    # Restart semua
```

---

## Monitoring — Dozzle

Buka **Dozzle** di http://localhost:9999 untuk melihat real-time logs dari semua container termasuk cron dan worker.

- Pilih container `php-cron` untuk lihat cron output
- Pilih container `php-worker` untuk lihat queue worker output
- Pilih container `node` untuk lihat pm2 output
