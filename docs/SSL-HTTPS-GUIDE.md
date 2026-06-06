# 🔒 Panduan SSL & HTTPS

---

## Cara Kerja SSL Lokal

```
1. setup.ps1 mendownload mkcert & install Root CA ke Windows Certificate Store
2. Browser otomatis trust semua sertifikat yang diterbitkan oleh Root CA tersebut
3. Root CA & mkcert binary di-mount ke PHP container (config/ssl/ca/, config/ssl/mkcert)
4. Saat membuat project lewat Dashboard, PHP secara otomatis:
   a. Generate sertifikat spesifik per-domain (misal: rockyanjas.test)
   b. Upload sertifikat ke Nginx Proxy Manager via API
   c. Buat Proxy Host di NPM dengan SSL terpasang
5. Browser → HTTPS hijau, tanpa warning ✅
```

> **Penting:** Sertifikat di-generate per-domain (bukan wildcard) agar kompatibel 100% dengan Google Chrome yang memblokir wildcard pada TLD `.test`.

---

## Setup Pertama (Otomatis via setup.ps1)

Script `setup.ps1` sudah otomatis:
1. Download mkcert
2. Install local CA (`mkcert -install`) ke Windows Certificate Store
3. Generate wildcard `*.test` certificate (untuk fallback)
4. Simpan di `c:\docker\config\ssl\`
5. Copy Root CA ke `c:\docker\config\ssl\ca\` (untuk digunakan oleh PHP container)
6. Download mkcert binary Linux ke `c:\docker\config\ssl\mkcert` (untuk generate cert di dalam container)

**Anda tidak perlu melakukan apapun secara manual untuk setup awal.**

---

## Setup HTTPS via Dashboard (Cara Utama — Otomatis)

Dashboard di `http://localhost:8000` sudah terintegrasi penuh dengan NPM API. Cukup:

### 1. Login ke NPM Panel (1x)
- URL: http://localhost:81
- Login pertama: `admin@example.com` / `changeme`
- **Ganti password** saat pertama login — ingat email & password baru Anda!

### 2. Simpan Kredensial NPM di Dashboard
1. Buka Dashboard: http://localhost:8000
2. Klik ikon ⚙️ **Settings** (pojok kanan atas)
3. Masukkan **Email** dan **Password** NPM Anda
4. Klik **Save**

### 3. Buat Project Baru
1. Klik **New Project** di Dashboard
2. Isi nama project (misal: `myapp`)
3. Pilih PHP Version & Type
4. Klik **Create Project**

Dashboard akan otomatis:
- Membuat folder & Nginx config
- Men-generate sertifikat SSL spesifik untuk `myapp.test` menggunakan mkcert
- Meng-upload sertifikat ke NPM via API
- Membuat Proxy Host di NPM dengan SSL enabled
- Menambahkan entry ke Windows hosts file

5. Buka `https://myapp.test` — gembok hijau! ✅

---

## Setup HTTPS Manual (via NPM Panel)

Jika Anda lebih suka setup manual tanpa Dashboard:

### 1. Generate Sertifikat per-Domain
```powershell
cd c:\docker
.\mkcert.exe myapp.test
# Output: myapp.test.pem dan myapp.test-key.pem
```

### 2. Upload ke NPM
1. Buka http://localhost:81 → **SSL Certificates** → **Add SSL Certificate** → **Custom**
2. Name: `myapp.test SSL`
3. Certificate Key: Upload `myapp.test-key.pem`
4. Certificate: Upload `myapp.test.pem`
5. Save

### 3. Buat Proxy Host
1. **Hosts** → **Proxy Hosts** → **Add Proxy Host**
2. **Details tab:**
   - Domain Names: `myapp.test`
   - Scheme: `http`
   - Forward Hostname: `nginx`
   - Forward Port: `8000`
3. **SSL tab:**
   - SSL Certificate: pilih `myapp.test SSL`
   - ✅ Force SSL
4. Save

---

## Windows Hosts File

Setiap domain lokal harus ditambahkan ke Windows hosts file:

**Lokasi:** `C:\Windows\System32\drivers\etc\hosts`

**Cara edit (Administrator):**
```powershell
notepad C:\Windows\System32\drivers\etc\hosts
```

**Tambahkan:**
```
127.0.0.1    myapp.test
127.0.0.1    nextapp.test
```

> **Tip:** Dashboard sudah otomatis menambahkan entry ini saat membuat project baru.

---

## Regenerate Certificate

Jika certificate expired atau perlu regenerate:

```powershell
cd c:\docker
.\mkcert.exe myapp.test

# Lalu upload ulang di NPM panel, atau hapus & buat ulang project di Dashboard
```

---

## Kenapa Tidak Pakai Wildcard?

Google Chrome memblokir wildcard certificate (`*.test`) pada single-level TLD. Sertifikat `*.test` akan ditolak oleh Chrome meskipun sudah di-trust oleh Windows Certificate Store.

Solusi: men-generate sertifikat **spesifik per-domain** (misal: `myapp.test`). Chrome menerima ini tanpa masalah dan gembok akan hijau.

---

## Troubleshooting SSL

### Browser masih warning "Not Secure"
1. Pastikan `mkcert -install` sudah dijalankan (otomatis via `setup.ps1`)
2. Restart browser sepenuhnya (tutup semua tab & window)
3. Cek certificate di browser: klik 🔒 → Certificate → harus dari "mkcert"
4. Pastikan sertifikat yang terpasang di NPM adalah **per-domain** (bukan wildcard)

### ERR_SSL_UNRECOGNIZED_NAME_ALERT
Proxy Host di NPM belum terpasang sertifikat SSL. Hapus project di Dashboard lalu buat ulang, atau pasang sertifikat secara manual di NPM panel.

### Firefox masih warning
Firefox punya certificate store sendiri. Jalankan:
```powershell
.\mkcert.exe -install
```
Lalu restart Firefox.

### Certificate expired
mkcert certificates berlaku **~2 tahun**. Jika expired, regenerate dan upload ulang.
