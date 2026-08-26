<p align="center">
  <img src="public/storage/logo.png" width="120" alt="Logo Kominfo">
</p>

# Helpdesk Kominfo Kota Palu

Sistem Informasi Helpdesk Terpadu Dinas Komunikasi, Informatika, Persandian dan Statistik (Diskominfo) Kota Palu. Aplikasi ini digunakan untuk melaporkan, memonitor, dan merekapitulasi penanganan gangguan jaringan intra-pemerintah (Fiber Optic, LAN, WiFi) di seluruh lingkungan Organisasi Perangkat Daerah (OPD) Kota Palu.

🌟 **Live Deployment URL:** [http://202.155.13.65:8080/](http://202.155.13.65:8080/)

---

## Fitur Utama

### 🏢 Untuk OPD (Pelapor)
* **Lapor Gangguan Mandiri**: Pembuatan tiket gangguan secara terstruktur berdasarkan tipe infrastruktur jaringan.
* **Isolasi Data Aman**: Masing-masing instansi hanya dapat melihat data laporannya sendiri.
* **Tracking Real-time**: Memonitor proses penanganan dari berstatus *Open* hingga *Resolved*.
* **Diskusi Langsung**: Fitur pesan dan unggah foto kondisi lapangan secara langsung tanpa perlu reload halaman (WebSockets).
* **Konfirmasi Selesai & Buka Ulang (Reopen)**: Hak penuh untuk menyetujui hasil kerja teknisi atau komplain ulang jika kendala masih terjadi.

### 🛠️ Untuk Teknisi Jaringan
* **Klaim Tiket Mandiri**: Fitur *Assign to me* untuk mengerjakan laporan gangguan yang masuk.
* **Catatan Internal**: Kolom diskusi rahasia antar teknisi dan admin yang tidak dapat dilihat oleh pihak OPD pelapor.
* **Manajemen Penyelesaian**: Input solusi perbaikan dan unggah foto dokumentasi penyelesaian (*resolution proofs*).

### 👑 Untuk Administrator
* **Dashboard Analitik**: Pantau KPI kinerja penanganan, jumlah tiket terbuka, pelanggaran batas target penyelesaian (SLA), dan aktivitas terbaru.
* **On-Behalf Ticketing**: Bantuan pelaporan tiket mewakili OPD tertentu untuk kasus gawat darurat (prioritas *Emergency*).
* **Master Data Management**: Kelola data dinas/OPD, klasifikasi kategori gangguan, target batas waktu penyelesaian (SLA), dan manajemen pengguna.
* **Export & Rekapitulasi**: Ekspor riwayat data gangguan ke dalam format cetak **PDF** atau **Excel/CSV**.

---

## Panduan Pengujian (Manual Testing)

Kami menyediakan panduan skenario uji (*User Acceptance Testing*) interaktif untuk memastikan fitur-fitur pada antarmuka berjalan baik:
- 📖 [Panduan Pengujian Admin & Teknisi](TESTING_ADMIN_TEKNISI.md)
- 📖 [Panduan Pengujian Pelapor / OPD](TESTING_PELAPOR.md)

---

## Tech Stack

Sistem Helpdesk Kominfo dibangun menggunakan kombinasi arsitektur modern (VILT Stack) untuk memastikan performa yang cepat dan interaktif:

- **Framework Backend**: Laravel 11.x (PHP 8.2+)
- **Frontend / UI**: Vue.js 3 + Inertia.js
- **Styling**: Tailwind CSS 3
- **Komponen UI**: shadcn-vue / Radix UI
- **Database**: MySQL / MariaDB
- **WebSockets / Realtime**: Laravel Reverb + Laravel Echo
- **Export Data**: barryvdh/laravel-dompdf (PDF) & maatwebsite/excel (Spreadsheet)
- **Background Jobs**: Laravel Queues (Database Driver)

---

## Panduan Instalasi (Development)

Untuk menjalankan proyek ini di lingkungan lokal Anda (*Localhost*):

1. **Kloning Repositori**
```bash
git clone https://github.com/PaluStudioCode/helpdesk-kominfo-palu.git
cd helpdesk-kominfo-palu
```

2. **Instalasi Dependensi**
```bash
composer install
npm install
```

3. **Konfigurasi Environment**
Salin file `.env.example` ke `.env` (Jika belum ada, buat manual). Pastikan pengaturan database Anda sudah benar.
```bash
cp .env.example .env
php artisan key:generate
```

4. **Jalankan Migrasi Database dan Seeder**
*(Menyiapkan tabel dan mengisi akun pengguna awal serta Master Data)*
```bash
php artisan migrate:fresh --seed
```

5. **Kompilasi Frontend Assets**
```bash
npm run build
```

6. **Menjalankan Server Lokal**
Gunakan terminal / tab yang berbeda untuk menjalankan perintah di bawah ini secara bersamaan:
- Menjalankan Web Server: `php artisan serve`
- Menjalankan WebSocket Reverb: `php artisan reverb:start`
- Menjalankan Queue Worker (Notifikasi): `php artisan queue:listen`
- *(Opsional)* Vite Hot Reload: `npm run dev`

### Kredensial Login Default (Development)
- **Admin**: `admin@kominfo.go.id` | Password: `password`
- **Teknisi**: `teknisi@example.com` | Password: `password`
- **Operator Dinas Kesehatan**: `operator@dinkes.palukota.go.id` | Password: `password`

---

## Lisensi
Sistem ini merupakan perangkat lunak tertutup (Properti Pemerintah Kota Palu). 
