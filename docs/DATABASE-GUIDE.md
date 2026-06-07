# 🗄️ Panduan Database (MariaDB)

---

## Connection Details

| Property | Value |
|----------|-------|
| **Host** (dari Windows) | `localhost` atau `127.0.0.1` |
| **Host** (dari container) | `mariadb` |
| **Port** | `3306` |
| **Root User** | `root` |
| **Root Password** | `secret` (lihat `.env`) |
| **Default User** | `homestead` |
| **Default Password** | `secret` (lihat `.env`) |
| **Default Database** | `homestead` |

---

## Connect dari PHP

### Laravel (.env)
```env
DB_CONNECTION=mysql
DB_HOST=mariadb
DB_PORT=3306
DB_DATABASE=homestead
DB_USERNAME=homestead
DB_PASSWORD=secret
```

### PDO Manual
```php
$pdo = new PDO('mysql:host=mariadb;port=3306;dbname=homestead', 'homestead', 'secret');
```

### WordPress (wp-config.php)
```php
define('DB_NAME', 'wordpress');
define('DB_USER', 'homestead');
define('DB_PASSWORD', 'secret');
define('DB_HOST', 'mariadb');
```

---

## Connect dari Node.js

### mysql2 (Promise)
```javascript
import mysql from 'mysql2/promise';
const connection = await mysql.createConnection({
  host: 'mariadb',
  port: 3306,
  user: 'homestead',
  password: 'secret',
  database: 'homestead'
});
```

### Prisma (schema.prisma)
```prisma
datasource db {
  provider = "mysql"
  url      = "mysql://homestead:secret@mariadb:3306/homestead"
}
```

### Knex.js
```javascript
const knex = require('knex')({
  client: 'mysql2',
  connection: {
    host: 'mariadb',
    port: 3306,
    user: 'homestead',
    password: 'secret',
    database: 'homestead'
  }
});
```

---

## Connect dari Tools External (Windows)

Untuk DBeaver, DataGrip, VS Code, HeidiSQL, dll:

| Property | Value |
|----------|-------|
| Host | `127.0.0.1` atau `localhost` |
| Port | `3306` |
| User | `root` (atau `homestead`) |
| Password | `secret` |

> **🛡️ WINDOWS 11 / VPN USERS:**
> Jika Anda menggunakan VPN (seperti **Cloudflare WARP**, VeePN) atau fitur *WSL2 Mirrored Networking*, aplikasi *database manager* seperti Beekeeper/DBeaver akan mengalami **Timeout (ETIMEDOUT)** saat mengakses `127.0.0.1`.
> 
> **Solusi Permanen:** Buat Adapter Jaringan Virtual (Loopback) dengan IP statis (misal `10.10.10.10`) dan gunakan IP tersebut sebagai Host. Lihat panduan lengkapnya di [TROUBLESHOOTING.md](TROUBLESHOOTING.md#dbeaverbeekeeper-timeout-karena-vpn-cloudflare-warp).

---

## phpMyAdmin

**URL:** http://localhost:8080

| Login | Value |
|-------|-------|
| Server | `mariadb` (sudah auto-filled) |
| Username | `root` |
| Password | `secret` |

### Tips phpMyAdmin:
- **Import SQL**: Tab "Import" → pilih file `.sql` → Go
- **Export/Backup**: Tab "Export" → Quick → Go (download `.sql`)
- **Upload limit**: 100MB (sudah dikonfigurasi)

---

## Buat Database Baru

### Via phpMyAdmin (GUI)
1. Buka http://localhost:8080
2. Klik "New" di sidebar kiri
3. Isi nama database → Create

### Via CLI
```powershell
docker compose exec mariadb mariadb -u root -psecret -e "CREATE DATABASE nama_database;"
```

---

## Buat User Baru

```powershell
docker compose exec mariadb mariadb -u root -psecret -e "
  CREATE USER 'newuser'@'%' IDENTIFIED BY 'password123';
  GRANT ALL PRIVILEGES ON nama_database.* TO 'newuser'@'%';
  FLUSH PRIVILEGES;
"
```

---

## Import Database

### Via CLI (lebih cepat untuk file besar)
```powershell
# Copy file SQL ke container lalu import
docker compose exec -T mariadb mariadb -u root -psecret nama_database < c:\path\to\dump.sql
```

### Via phpMyAdmin
1. Buka http://localhost:8080
2. Pilih database di sidebar
3. Tab "Import" → Choose File → Go

---

## Export/Backup Database

### Export satu database
```powershell
docker compose exec mariadb mariadb-dump -u root -psecret nama_database > c:\docker\backup.sql
```

### Export semua database
```powershell
docker compose exec mariadb mariadb-dump -u root -psecret --all-databases > c:\docker\all_databases.sql
```

---

## MariaDB vs MySQL Compatibility

MariaDB 11 adalah **100% kompatibel** dengan MySQL untuk keperluan development:

| Feature | MariaDB | MySQL |
|---------|---------|-------|
| SQL Syntax | ✅ Sama | ✅ Sama |
| PDO Driver | `pdo_mysql` | `pdo_mysql` |
| mysqli | ✅ Support | ✅ Support |
| .sql Import/Export | ✅ Compatible | ✅ Compatible |
| Laravel Migration | ✅ `mysql` driver | ✅ `mysql` driver |
| WordPress | ✅ Full support | ✅ Full support |

> **Jika project production Anda pakai MySQL**, Anda tetap bisa develop di MariaDB tanpa masalah. SQL, schema, dan data saling kompatibel.
