# 📚 Docker Local Webserver — Dokumentasi

Selamat datang di dokumentasi Docker Local Webserver. Semua dokumen ditulis dalam Bahasa Indonesia dan dirancang untuk dibaca oleh **manusia maupun AI agent**.

---

## 📋 Daftar Dokumen

| Dokumen | Isi |
|---------|-----|
| [ARCHITECTURE.md](./ARCHITECTURE.md) | Arsitektur sistem, container map, network, flow request, volume mapping |
| [PHP-GUIDE.md](./PHP-GUIDE.md) | PHP: versions, extensions, Composer, Artisan, Xdebug |
| [NODEJS-GUIDE.md](./NODEJS-GUIDE.md) | Node.js: pm2, Next.js, Vite, Nuxt, dev server |
| [DATABASE-GUIDE.md](./DATABASE-GUIDE.md) | MariaDB: connect, import, backup, phpMyAdmin |
| [SSL-HTTPS-GUIDE.md](./SSL-HTTPS-GUIDE.md) | SSL: mkcert, NPM API, auto-SSL per-domain |
| [CRON-WORKERS-GUIDE.md](./CRON-WORKERS-GUIDE.md) | Cron, queue workers, Supervisor, pm2 |
| [TROUBLESHOOTING.md](./TROUBLESHOOTING.md) | Common issues & fixes |
| [COMMANDS.md](./COMMANDS.md) | Quick reference — semua command penting |

---

## 🚀 Quick Start

```powershell
# 1. Clone & setup (sekali saja, jalankan sebagai Administrator)
git clone https://github.com/USERNAME/docker-local-webserver.git c:\docker
cd c:\docker
Copy-Item .env.example .env
.\setup.ps1

# 2. Buka Dashboard → buat project baru
# http://localhost:8000
```

### Membuat Project via Dashboard (Cara Utama)

1. Buka **Dashboard**: http://localhost:8000
2. Klik ⚙️ **Settings** → masukkan email & password NPM Anda → Save
3. Klik **New Project** → isi nama → pilih PHP version → **Create**
4. Buka `https://namaproject.test` → HTTPS hijau otomatis! ✅

### Membuat Project via Terminal

```powershell
# Buat site PHP baru
.\create-site.ps1 -Name "myapp" -PhpVersion "8.4"

# Buat site Node.js baru
.\create-node-site.ps1 -Name "nextapp" -Framework "nextjs"

# Edit file langsung di Windows
# c:\docker\sites\myapp.test\
```

---

## 🎛️ Panel Management

| Panel | URL | Login |
|-------|-----|-------|
| **Dashboard** | http://localhost:8000 | — |
| Nginx Proxy Manager | http://localhost:81 | `admin@example.com` / `changeme` |
| Dockge | http://localhost:5001 | Set saat pertama buka |
| Dozzle | http://localhost:9999 | — |
| phpMyAdmin | http://localhost:8080 | `root` / `secret` |
| RedisInsight | http://localhost:8001 | — |
| Mailpit | http://localhost:8025 | — |

---

## 📁 Struktur Folder

```
c:\docker\
├── docker-compose.yml      ← Konfigurasi semua container
├── .env.example             ← Template environment variables
├── .env                     ← Environment variables (copy dari .env.example)
├── setup.ps1                ← Setup script (1x run)
├── create-site.ps1          ← Buat site PHP baru (via terminal)
├── create-node-site.ps1     ← Buat site Node.js baru (via terminal)
├── dockerfiles\             ← Custom Docker images
├── config\                  ← Konfigurasi nginx, PHP, cron, supervisor, SSL
├── data\                    ← Data persistent (DB, cache, dll)
├── logs\                    ← Log files
├── sites\                   ← 🌟 PROJECT FILES (edit langsung dari Windows!)
│   └── default\             ← Dashboard web + NPM API integration
└── docs\                    ← Dokumentasi (Anda di sini)
```
