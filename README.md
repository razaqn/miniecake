# 🍰 Miniecake — E-Catalog & Admin Management

Web E-Catalog modern, responsif, dinamis, dan bertema minimalis **Pink & White** untuk patisserie & cake shop **"miniecake"**. 

Aplikasi ini menggunakan **PHP, HTML5, CSS3, dan Vanilla JavaScript** dengan backend **100% Google Sheets (Spreadsheet)** sebagai database dan **Google Drive** sebagai media penyimpanan foto & video produk — **100% tanpa Service Account** (menggunakan OAuth 2.0 Client / Refresh Token / Access Token) dan **100% Vercel Serverless Ready**!

---

## ✨ Fitur-Fitur Utama

### 1. Halaman Publik (Katalog & Interaksi Pelanggan)
- **Desain Minimalis Pink & White**: Elegan, lembut, bersih, dan memanjakan mata, cocok untuk toko kue/patisserie.
- **Banner Promosi Slider / Carousel**:
  - Banner dinamis di bagian atas beranda yang dikelola langsung dari tab `Banners` Google Sheets / Panel Admin.
  - Menampilkan foto banner, judul promosi, subjudul, teks tombol, dan tautan aksi dengan navigasi slide otomatis/manual.
- **Fitur Love / Favorit Produk (Tersimpan di Browser Tanpa Login)**:
  - Tombol hati (love) pada setiap kartu produk dan di dalam modal detail.
  - Disimpan menggunakan `localStorage` di browser pelanggan tanpa perlu registrasi/login akun.
  - Tombol **"❤️ Favorit"** di navbar menampilkan jumlah kue favorit. Sekali klik akan menyaring katalog hanya menampilkan produk yang disukai!
- **Dukungan Video Produk**:
  - Kartu produk menampilkan lencana khusus **"▶ Video"** jika kue memiliki video.
  - Di dalam modal detail, pengunjung dapat memutar video produk (proses dekorasi kue, tampilan 360°, tekstur kue) langsung melalui pemutar video yang terintegrasi.
- **Carousel Foto Produk**:
  - Tiap kartu produk yang memiliki lebih dari satu foto dilengkapi tombol geser dan indikator titik (*dot indicator*).
- **Filter Kategori Dinamis & Live Search**:
  - Kategori tersinkronisasi otomatis dengan tab `Categories` Google Sheet.
  - Kolom pencarian realtime (mencari berdasarkan nama, deskripsi, atau kategori kue).
- **Modal Detail Produk di Tengah Layar**:
  - Tampilan popup responsif dengan backdrop blur modern di tengah halaman.
  - Galeri foto & pemutar video interaktif dengan thumbnail yang bisa diklik.
  - **Tombol WhatsApp Auto-Chat**:
    - Sekali klik langsung membuka chat WhatsApp ke nomor toko dengan format pesan otomatis menanyakan ketersediaan barang lengkap dengan tautan foto & video produk dan link katalog.
- **Tombol Media Sosial Sticky**:
  - Floating bar melayang tetap di pojok layar: WhatsApp, Instagram, Facebook, dan TikTok.

### 2. Panel Admin Lengkap (4 Tab Manajemen)
Akses melalui URL `/admin` (Default: `admin` / `password123`).

- **Tab 1: 🎂 Katalog Produk**:
  - **📋 Fitur Copy / Duplikat Produk**: Sekali klik tombol salin pada tabel, semua isian produk (kategori, harga, status, deskripsi, video) otomatis terisi di form. Anda **hanya perlu mengganti nama dan fotonya saja**!
  - **Upload Foto & Video Produk**: Unggah file foto dan video langsung ke Google Drive (tersimpan permanen di cloud).
  - **CRUD Produk**: Tambah, edit, dan hapus produk yang langsung memperbarui tab `Products` di Google Sheet.
- **Tab 2: 🏷️ Manajemen Kategori**:
  - Tambah kategori baru dengan mudah (langsung masuk ke tab `Categories` Google Sheet).
  - Ubah/rename nama kategori (secara otomatis memperbarui semua kue yang menggunakan kategori lama tersebut).
  - Hapus kategori dan lihat jumlah kue yang terdaftar pada tiap kategori.
- **Tab 3: 🖼️ Banner Promo**:
  - Kelola banner promosi beranda: upload gambar banner ke Google Drive, atur judul promo, deskripsi singkat, teks tombol, dan tautan (tersimpan di tab `Banners` Google Sheet).
- **Tab 4: ⚙️ Kontak & Logo Web**:
  - **Manajemen Logo Web**: Upload logo baru (PNG transparan, SVG, JPG, WEBP) ke Google Drive atau gunakan URL langsung (tersimpan di tab `Settings` Google Sheet).
  - **Manajemen Kontak**: Atur nomor WhatsApp auto-chat, link Instagram, Facebook, TikTok, alamat fisik toko, dan jam operasional.

---

## ☁️ Arsitektur Full Google Sheets (Multi-Tab)

Aplikasi ini menggunakan 4 Tab di dalam 1 Google Spreadsheet:

1. **`Products`**: Menyimpan daftar kue, harga, deskripsi, tautan foto Google Drive, video, dan status.
2. **`Categories`**: Menyimpan daftar kategori aktif.
3. **`Banners`**: Menyimpan banner promosi di halaman utama.
4. **`Settings`**: Menyimpan konfigurasi logo web, nomor WhatsApp, medsos, alamat, dan jam buka.

Karena semua data disimpan di Google Sheets dan foto/video disimpan di Google Drive, aplikasi ini **100% stateless dan aman untuk dideploy ke platform serverless seperti Vercel**.

---

## 🖼️ Solusi Gambar Google Drive (`api/image.php`)

Google Drive memiliki proteksi *hotlinking* (error HTTP 429 Too Many Requests / 403 Forbidden jika gambar dibuka langsung dari domain eksternal seperti localhost atau vercel.app). 

Aplikasi Miniecake telah dilengkapi dengan **Google Drive Streaming Proxy & Cache** di file `api/image.php`:
- Saat foto diunggah ke Google Drive, sistem membuat link: `api/image.php?id={FILE_ID}`.
- Script ini mengambil file dari Google Drive API v3 secara aman menggunakan token OAuth2 Anda dan menyajikannya langsung sebagai binary image (`Content-Type: image/png` atau `image/jpeg`).
- Dilengkapi header **Vercel Edge Cache** (`s-maxage=604800` / 7 hari), sehingga gambar dimuat super cepat langsung dari CDN Vercel tanpa membebani limit Google Drive!

---

## 🚀 Panduan Deploy ke Vercel

Aplikasi sudah dilengkapi file `vercel.json` menggunakan runtime resmi komunitas PHP `vercel-php@0.7.3`.

### Cara Deploy:

#### Metode 1: Lewat Git (GitHub / GitLab) — Paling Mudah
1. Buat repository baru di GitHub/GitLab dan push project ini:
   ```bash
   git init
   git add .
   git commit -m "Deploy miniecake e-catalog"
   git branch -M main
   git remote add origin <URL_REPOSITORY_ANDA>
   git push -u origin main
   ```
2. Buka dashboard [Vercel](https://vercel.com/) & klik **"Add New Project"** > **Import**.
3. Pada halaman konfigurasi project di Vercel, buka bagian **Environment Variables** dan tambahkan variabel berikut (salin nilainya dari file `.env` Anda):
   - `ADMIN_USERNAME` = `admin`
   - `ADMIN_PASSWORD` = `password123`
   - `WHATSAPP_NUMBER` = `6281234567890`
   - `INSTAGRAM_URL` = `https://instagram.com/miniecake`
   - `FACEBOOK_URL` = `https://facebook.com/miniecake`
   - `TIKTOK_URL` = `https://tiktok.com/@miniecake`
   - `GOOGLE_CLIENT_ID` = `...`
   - `GOOGLE_CLIENT_SECRET` = `...`
   - `GOOGLE_REFRESH_TOKEN` = `...`
   - `GOOGLE_ACCESS_TOKEN` = `...`
   - `GOOGLE_SPREADSHEET_ID` = `...`
   - `GOOGLE_SPREADSHEET_SHEET_NAME` = `Products`
   - `GOOGLE_DRIVE_FOLDER_ID` = `...`
4. Klik **Deploy**! Vercel akan otomatis meng-compile dan menerbitkan website Anda secara gratis dengan domain HTTPS (contoh: `https://miniecake.vercel.app`).

#### Metode 2: Lewat Vercel CLI
1. Pasang Vercel CLI jika belum ada:
   ```bash
   npm i -g vercel
   ```
2. Jalankan perintah di folder project:
   ```bash
   vercel
   ```
3. Ikuti panduan di layar untuk menghubungkan ke akun Vercel Anda.
4. Jangan lupa tambahkan Environment Variables di dashboard Vercel pada project settings.

---

## 💻 Menjalankan di Lokal (Local Development)

```bash
cd "/Users/razaq/Documents/Project/folder tanpa judul/miniecake"
php -S localhost:8000
```

- **Katalog Utama**: [http://localhost:8000](http://localhost:8000)
- **Panel Admin**: [http://localhost:8000/admin](http://localhost:8000/admin) (User: `admin`, Pass: `password123`)
