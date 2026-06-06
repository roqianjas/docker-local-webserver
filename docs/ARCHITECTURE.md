# 🏗️ Arsitektur Sistem

Dokumen ini menjelaskan arsitektur lengkap Docker Local Webserver.

---

## Container Map

| # | Container | Image | Port | Peran |
|---|-----------|-------|------|-------|
| 1 | `npm` | `jc21/nginx-proxy-manager` | 80, 443, 81 | Reverse proxy, SSL termination, domain routing |
| 2 | `nginx` | `nginx:stable-alpine` | 8000 | Web server PHP (FastCGI ke PHP-FPM) |
| 3 | `php84` | Custom PHP 8.4-FPM | 9000 (internal) | PHP processor (default) + mkcert cert generator |
| 4 | `php83` | Custom PHP 8.3-FPM | 9000 (internal) | PHP processor |
| 5 | `php82` | Custom PHP 8.2-FPM | 9000 (internal) | PHP processor |
| 6 | `php81` | Custom PHP 8.1-FPM | 9000 (internal) | PHP processor |
| 7 | `php74` | Custom PHP 7.4-FPM | 9000 (internal) | PHP processor (legacy) |
| 8 | `php-cron` | Same as php84 | - | Cron daemon (scheduled tasks) |
| 9 | `php-worker` | Same as php84 | - | Queue worker (Supervisor) |
| 10 | `node` | Custom Node.js 22 | 3000-3005 | Node.js dev server (Next.js, Vite, Nuxt) |
| 11 | `mariadb` | `mariadb:11` | 3306 | Database server |
| 12 | `phpmyadmin` | `phpmyadmin/phpmyadmin` | 8080 | Database management GUI |
| 13 | `redis` | `redis:7-alpine` | 6379 | Cache & session store |
| 14 | `redisinsight` | `redis/redisinsight` | 8001 | Redis visual management GUI |
| 15 | `mailpit` | `axllent/mailpit` | 8025, 1025 | Email catcher & testing GUI |
| 16 | `dockge` | `louislam/dockge:1` | 5001 | Docker stack management GUI |
| 17 | `dozzle` | `amir20/dozzle` | 9999 | Real-time Docker log viewer GUI |

---

## Network Topology

Semua container berada dalam satu Docker network bernama `webserver` (bridge mode).

```
                         ┌─────────────────┐
                         │   Windows Host   │
                         │ (Browser/Editor) │
                         └────────┬─────────┘
                                  │
                    ┌─────────────┼─────────────┐
                    │ Port 80/443 │  Port 3000+  │
                    ▼             │              ▼
            ┌──────────────┐     │     ┌──────────────┐
            │     NPM      │     │     │    Node.js    │
            │ (Reverse     │     │     │  Dev Server   │
            │  Proxy + SSL)│     │     │  (Next/Vite)  │
            └──────┬───────┘     │     └──────────────┘
                   │             │
                   ▼             │
            ┌──────────────┐     │
            │    Nginx     │     │
            │ (PHP Server) │     │
            └──────┬───────┘     │
                   │             │
         ┌────┬────┼────┬────┐   │
         ▼    ▼    ▼    ▼    ▼   │
       PHP  PHP  PHP  PHP  PHP   │
       8.4  8.3  8.2  8.1  7.4   │
         │    │    │    │    │    │
         └────┴────┼────┴────┘   │
                   │             │
         ┌─────────┼─────────────┤
         ▼         ▼             ▼
    ┌─────────┐ ┌───────┐ ┌──────────┐
    │ MariaDB │ │ Redis │ │ Mailpit  │
    │  :3306  │ │ :6379 │ │ SMTP:1025│
    └─────────┘ └───────┘ └──────────┘
```

---

## Flow Request — PHP Site (Dengan SSL Otomatis)

```
1. User membuat project "myapp" di Dashboard (http://localhost:8000)
2. Dashboard (PHP 8.4):
   a. Membuat folder sites/myapp.test/ dan Nginx config
   b. Menjalankan mkcert (di dalam container) → generate myapp.test.pem
   c. Memanggil NPM API → upload certificate + buat Proxy Host
   d. Menambahkan 127.0.0.1 myapp.test ke Windows hosts file
3. Browser → https://myapp.test
4. DNS → Windows hosts file → 127.0.0.1
5. NPM (port 443) → SSL termination (per-domain cert) → Forward ke nginx:8000
6. Nginx → Baca config myapp.test.conf → fastcgi_pass php84:9000
7. PHP 8.4-FPM → Process PHP → Return response
8. Nginx → NPM → Browser (HTTPS hijau ✅)
```

## Flow Request — Node.js Site

```
1. Browser → https://nextapp.test
2. DNS → Windows hosts file → 127.0.0.1
3. NPM (port 443) → SSL termination → Forward ke node:3000
4. Node.js dev server → Process request → Return response
5. NPM → Browser
```

---

## Volume Mapping

| Host (Windows) | Container | Digunakan oleh |
|----------------|-----------|----------------|
| `c:\docker\sites\` | `/var/www/html` | nginx, php*, node, php-cron, php-worker |
| `c:\docker\config\nginx\` | `/etc/nginx/conf.d` | nginx, php84 (untuk buat config baru) |
| `c:\docker\config\php\php.ini` | `/usr/local/etc/php/conf.d/99-custom.ini` | php* |
| `c:\docker\config\php\xdebug.ini` | `/usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini` | php84-php81 |
| `c:\docker\config\cron\crontab` | `/var/spool/cron/crontabs/www-data` | php-cron |
| `c:\docker\config\supervisor\worker.conf` | `/etc/supervisor/conf.d/worker.conf` | php-worker |
| `c:\docker\config\ssl\` | `/etc/ssl/custom` (npm), `/var/www/ssl` (php84) | npm, php84 |
| `c:\docker\data\mariadb\` | `/var/lib/mysql` | mariadb |
| `c:\docker\data\redis\` | `/data` | redis |
| `c:\docker\data\npm\` | `/data` | npm |
| `c:\docker\logs\nginx\` | `/var/log/nginx` | nginx |
| `c:\docker\logs\cron\` | `/var/log/cron` | php-cron |
| `c:\docker\logs\worker\` | `/var/log/worker` | php-worker |

### SSL Directory Structure

```
config/ssl/
├── ca/                     ← Root CA (copied from mkcert CAROOT)
│   ├── rootCA.pem
│   └── rootCA-key.pem
├── mkcert                  ← mkcert Linux binary (untuk generate cert di container)
├── wildcard.test.pem       ← Wildcard cert (fallback, tidak dipakai Chrome)
└── wildcard.test-key.pem
```

---

## Dashboard — NPM API Integration

Dashboard (`sites/default/index.php`) terintegrasi dengan NPM API untuk otomasi penuh:

| Aksi Dashboard | NPM API Call |
|----------------|-------------|
| Create Project | `POST /api/tokens` → `POST /api/nginx/certificates` → `POST /api/nginx/certificates/:id/upload` → `POST /api/nginx/proxy-hosts` |
| Delete Project | `POST /api/tokens` → `GET /api/nginx/proxy-hosts` → `DELETE /api/nginx/proxy-hosts/:id` → `DELETE /api/nginx/certificates/:id` |

Kredensial NPM disimpan di `sites/config.json` (gitignored).

---

## Resource Estimates

| Container | RAM (idle) | RAM (aktif) |
|-----------|-----------|-------------|
| NPM | ~50MB | ~80MB |
| Nginx | ~5MB | ~15MB |
| PHP-FPM (per version) | ~20MB | ~50-100MB |
| php-cron | ~15MB | ~30MB |
| php-worker | ~30MB | ~60MB |
| Node.js | ~40MB | ~150-300MB |
| MariaDB | ~100MB | ~200-500MB |
| phpMyAdmin | ~30MB | ~50MB |
| Redis | ~5MB | ~20-256MB |
| RedisInsight | ~50MB | ~80MB |
| Mailpit | ~15MB | ~30MB |
| Dockge | ~50MB | ~80MB |
| Dozzle | ~10MB | ~20MB |
| **Total** | **~500MB** | **~1-2GB** |

> **Note:** Docker Desktop sendiri memakan ~1-2GB RAM. Total keseluruhan ~2-4GB RAM saat aktif development.
