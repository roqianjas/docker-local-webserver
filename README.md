# 🐳 Docker Local Webserver

Webserver lokal berbasis Docker untuk development di Windows. Pengganti Laragon/Herd yang lebih powerful, fleksibel, dan multi-PHP.

**17 containers • 6 GUI panels • PHP 7.4–8.4 • Node.js 22 • MariaDB 11 • Redis 7**

---

## ✨ Fitur Utama

- **Multi-PHP** — 5 versi PHP (7.4, 8.1, 8.2, 8.3, 8.4) berjalan bersamaan
- **HTTPS Otomatis** — Sertifikat SSL per-domain digenerate & dipasang otomatis via NPM API
- **Dashboard Web** — Buat/hapus project langsung dari browser, tanpa terminal
- **Node.js Ready** — Next.js, Vite, Nuxt dengan pm2 pre-installed
- **6 GUI Panels** — NPM, phpMyAdmin, RedisInsight, Mailpit, Dockge, Dozzle
- **Hot Reload** — Edit file di Windows, langsung terlihat di browser

---

## 🚀 Quick Start

### Prasyarat
- [Docker Desktop](https://www.docker.com/products/docker-desktop/) (WSL2 backend)
- Windows 10/11

### Instalasi

```powershell
# 1. Clone repository
git clone https://github.com/USERNAME/docker-local-webserver.git c:\docker

# 2. Copy environment file
cd c:\docker
Copy-Item .env.example .env

# 3. Jalankan setup (1x saja, sebagai Administrator, ~5-15 menit)
.\setup.ps1

# 4. Buka Dashboard
# http://localhost:8000
```

### Membuat Project Baru

**Via Dashboard (Direkomendasikan):**
1. Buka http://localhost:8000
2. Klik ⚙️ Settings → masukkan email & password NPM → Save
3. Klik **New Project** → isi nama → **Create**
4. Buka `https://namaproject.test` — HTTPS hijau otomatis! ✅

**Via Script:**
```powershell
# PHP site
.\create-site.ps1 -Name "myapp" -PhpVersion "8.4"

# Node.js site
.\create-node-site.ps1 -Name "nextapp" -Framework "nextjs"
```

---

## 🎛️ Panel Management

| Panel | URL | Login |
|-------|-----|-------|
| **Dashboard** | http://localhost:8000 | — |
| Nginx Proxy Manager | http://localhost:81 | `admin@example.com` / `changeme` |
| phpMyAdmin | http://localhost:8080 | `root` / `secret` |
| Dockge | http://localhost:5001 | Set saat pertama buka |
| Dozzle (Logs) | http://localhost:9999 | — |
| RedisInsight | http://localhost:8001 | — |
| Mailpit | http://localhost:8025 | — |

---

## 📚 Dokumentasi

Lihat folder [docs/](./docs/) untuk panduan lengkap:

- [Arsitektur Sistem](./docs/ARCHITECTURE.md) — Container map, network, volume mapping
- [Panduan PHP](./docs/PHP-GUIDE.md) — Multi-version, Composer, Artisan, Xdebug
- [Panduan Node.js](./docs/NODEJS-GUIDE.md) — Next.js, Vite, Nuxt, pm2
- [Panduan Database](./docs/DATABASE-GUIDE.md) — MariaDB, phpMyAdmin, import/export
- [Panduan SSL/HTTPS](./docs/SSL-HTTPS-GUIDE.md) — mkcert, NPM API, auto-SSL
- [Panduan Cron & Workers](./docs/CRON-WORKERS-GUIDE.md) — Scheduler, queue, Supervisor
- [Troubleshooting](./docs/TROUBLESHOOTING.md) — Solusi masalah umum
- [Command Reference](./docs/COMMANDS.md) — Semua command penting

---

## 📁 Struktur Project

```
c:\docker\
├── docker-compose.yml      ← Konfigurasi 17 container
├── .env.example             ← Template environment variables
├── .env                     ← Environment variables (user-specific)
├── setup.ps1                ← Setup script (1x run, sebagai Admin)
├── create-site.ps1          ← Buat site PHP baru (via terminal)
├── create-node-site.ps1     ← Buat site Node.js baru (via terminal)
├── dockerfiles\             ← Custom Docker images (PHP, Node)
├── config\                  ← Konfigurasi nginx, PHP, cron, supervisor, SSL
│   ├── nginx\sites\         ← Nginx virtual host configs
│   ├── php\                 ← php.ini, xdebug.ini
│   ├── ssl\                 ← SSL certificates & mkcert (auto-generated)
│   ├── cron\                ← Crontab config
│   └── supervisor\          ← Queue worker config
├── data\                    ← Data persistent (DB, cache, NPM — gitignored)
├── logs\                    ← Log files (gitignored)
├── sites\                   ← 🌟 PROJECT FILES (edit langsung dari Windows!)
│   └── default\             ← Dashboard & NPM API integration
└── docs\                    ← Dokumentasi lengkap
```

---

## 🔑 Default Credentials

| Service | Username | Password |
|---------|----------|----------|
| MariaDB (root) | `root` | `secret` |
| MariaDB (user) | `homestead` | `secret` |
| NPM (pertama kali) | `admin@example.com` | `changeme` |

> ⚠️ **Ganti password NPM** saat pertama kali login di http://localhost:81

---

## 📄 License

MIT License
