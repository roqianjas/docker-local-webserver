# 🟢 Panduan Node.js

---

## Overview

Container Node.js berjalan dalam mode **idle** (`tail -f /dev/null`). Anda masuk ke container, lalu menjalankan project secara manual. Ini memberikan kontrol penuh.

**Tools terinstall:** npm, yarn, pnpm, pm2, nodemon, ts-node, typescript

---

## Masuk ke Container

```powershell
docker compose exec node sh
```

Anda akan berada di `/var/www/html` — folder `sites\` Anda.

---

## Setup Project Baru

### Next.js
```powershell
# Dari Windows, buat site dulu
.\create-node-site.ps1 -Name "nextapp" -Framework "nextjs"

# Masuk ke container
docker compose exec node sh
cd /var/www/html/nextapp.test

# Init project
npx create-next-app@latest .

# Start dev server (penting: bind ke 0.0.0.0 agar bisa diakses dari luar container)
npm run dev -- -H 0.0.0.0 -p 3000
```

### Vite (React/Vue/Svelte)
```bash
cd /var/www/html/viteapp.test
npm create vite@latest . -- --template react
npm install
npm run dev -- --host 0.0.0.0 --port 3001
```

### Nuxt
```bash
cd /var/www/html/nuxtapp.test
npx nuxi@latest init .
npm install
npm run dev -- --host 0.0.0.0 --port 3002
```

### Express.js (API)
```bash
cd /var/www/html/api.test
npm init -y
npm install express
node index.js
```

---

## Akses dari Browser

### Langsung via Port
Setelah dev server jalan, akses langsung:
- `http://localhost:3000` (Next.js)
- `http://localhost:3001` (Vite)
- `http://localhost:3002` (Nuxt)

### Via Custom Domain (HTTPS)
1. Buka **Nginx Proxy Manager**: http://localhost:81
2. **Hosts → Proxy Hosts → Add**
3. Domain: `nextapp.test`
4. Forward Hostname: `node`
5. Forward Port: `3000`
6. SSL tab → pilih custom certificate (*.test)
7. Akses: `https://nextapp.test`

---

## pm2 — Process Manager

pm2 sudah terinstall di container dan bisa digunakan untuk:

### Menjalankan Production Server
```bash
cd /var/www/html/nextapp.test
npm run build
pm2 start npm --name "nextapp" -- start
```

### Background Workers
```bash
pm2 start worker.js --name "email-worker" --instances 2
```

### Cron/Scheduled Tasks
```javascript
// ecosystem.config.js
module.exports = {
  apps: [{
    name: "cleanup",
    script: "./scripts/cleanup.js",
    cron_restart: "0 */6 * * *",  // Setiap 6 jam
    autorestart: false
  }]
}
```
```bash
pm2 start ecosystem.config.js
```

### Monitoring
```bash
pm2 status          # Lihat semua proses
pm2 logs            # Lihat logs real-time
pm2 monit           # Monitor CPU/memory
pm2 restart all     # Restart semua
pm2 stop nextapp    # Stop spesifik
pm2 delete all      # Hapus semua
```

---

## Port Mapping

Container Node.js meng-expose port 3000-3005:

| Port | Penggunaan Contoh |
|------|-------------------|
| 3000 | Next.js project 1 |
| 3001 | Vite project |
| 3002 | Nuxt project |
| 3003 | Express API |
| 3004 | Available |
| 3005 | Available |

Jika butuh lebih, edit `docker-compose.yml` dan tambah port mapping.

---

## Tips Performa

### Hot Reload Lambat?
Docker bind mount di Windows bisa lambat untuk file watching. Solusi:

1. **Gunakan polling** (tambahkan di `package.json` atau `.env`):
   ```env
   WATCHPACK_POLLING=true
   CHOKIDAR_USEPOLLING=true
   ```

2. **Untuk Next.js**, tambahkan di `next.config.js`:
   ```javascript
   module.exports = {
     webpackDevMiddleware: config => {
       config.watchOptions = {
         poll: 1000,
         aggregateTimeout: 300,
       }
       return config
     },
   }
   ```

### node_modules di dalam container
Jika `npm install` sangat lambat, pertimbangkan install di dalam container saja:
```bash
# node_modules akan ada di container, bukan di Windows filesystem
docker compose exec node sh -c "cd /var/www/html/myapp.test && npm install"
```
