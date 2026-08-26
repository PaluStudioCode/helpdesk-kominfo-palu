# PANDUAN PENGUJIAN MANUAL SISTEM (TESTING MANUAL FRONTEND)
## ROLE: ADMINISTRATOR DISKOMINFO & TEKNISI JARINGAN

Dokumen ini berisi panduan skenario pengujian manual (*end-to-end user interaction*) secara langsung pada antarmuka pengguna (*frontend*) untuk pengguna dengan peran **Administrator Diskominfo** dan **Teknisi Jaringan**.

---

### Informasi Kredensial Akun Pengujian
| Role | Email | Password | Status Akun |
| :--- | :--- | :--- | :--- |
| **Administrator** | `admin@kominfo.go.id` | `password` | Aktif |
| **Teknisi Jaringan** | `teknisi@example.com` | `password` | Aktif |

---

### Prasyarat Menjalankan Sistem
Pastikan layanan-layanan berikut telah dijalankan di terminal masing-masing sebelum memulai pengujian:
1. **Web Server**: `php artisan serve` (http://localhost:8000)
2. **WebSocket Reverb**: `php artisan reverb:start --debug`
3. **Frontend Assets**: `npm run dev`
4. **Queue Worker**: `php artisan queue:listen --tries=1 --timeout=0`

---

# BAGIAN 1: PENGUJIAN ROLE ADMINISTRATOR DISKOMINFO

---

## 1. Modul Autentikasi & Navigasi Admin

### TC-ADM-01: Login Administrator & Proteksi Akses
- **Tujuan**: Memverifikasi proses masuk pengguna Administrator dan akses menu khusus Admin.
- **Langkah Pengujian**:
  1. Buka browser dan arahkan ke `http://localhost:8000/login`.
  2. Masukkan Email: `admin@kominfo.go.id` dan Password: `password`.
  3. Centang opsi *"Ingat perangkat ini"*.
  4. Klik tombol **"Masuk ke Sistem"**.
- **Hasil yang Diharapkan**:
  - Berhasil login dan dialihkan langsung ke halaman `/dashboard`.
  - Pada bilah navigasi samping (*Sidebar*) muncul 4 menu lengkap:
    1. **Dashboard**
    2. **Tiket Gangguan**
    3. **Master Data**
    4. **Laporan & Rekap**
  - Pada bagian pojok bawah sidebar, profil menampilkan nama *"Administrator Kominfo"* dengan badge peran *"Admin"*.

---

## 2. Modul Dashboard Monitoring Admin

### TC-ADM-02: Verifikasi Metrik & Statistik Gangguan
- **Tujuan**: Memastikan kartu ringkasan KPI dan grafik statistik kategori gangguan tampil akurat.
- **Langkah Pengujian**:
  1. Buka menu **Dashboard** pada sidebar.
  2. Periksa tampilan kartu metrik utama di bagian atas:
     - *Total Tiket Aktif*
     - *Total Terselesaikan*
     - *Instansi Terdaftar*
  3. Periksa tampilan kartu metrik *Statistik Gangguan Kategori*:
     - *Fiber Optic*
     - *Jaringan LAN*
     - *Jaringan WiFi*
  4. Periksa tabel kartu *Aktivitas Tiket Terkini* di bagian bawah.
- **Hasil yang Diharapkan**:
  - Angka metrik muncul sesuai dengan data database.
  - Kartu kategori terbagi dengan warna khas (*Purple* untuk FO, *Cyan* untuk LAN, *Sky* untuk WiFi).
  - 5 tiket terbaru tampil dengan label status badge, nomor tiket, instansi pelapor, dan waktu pembuatan dalam zona waktu WITA.

---

## 3. Modul Master Data Hub

### TC-ADM-03: Kelola Data Instansi / Organisasi Perangkat Daerah (OPD)
- **Tujuan**: Menguji penambahan, pengubahan data, dan penonaktifan/penghapusan OPD.
- **Langkah Pengujian**:
  1. Buka menu **Master Data** pada sidebar.
  2. Pastikan tab aktif berada pada **"Data OPD / Instansi"**.
  3. **Tambah OPD Baru**:
     - Klik tombol **"+ Tambah OPD"**.
     - Isi Kode Instansi: `DISHUB-UJI`.
     - Isi Nama OPD: `Dinas Perhubungan Pengujian Kendaraan`.
     - Isi Alamat: `Jl. Trans Sulawesi No. 12, Palu`.
     - Klik **"Simpan"**.
  4. **Pencarian OPD**:
     - Ketikkan `DISHUB-UJI` pada kolom pencarian.
  5. **Edit OPD**:
     - Klik ikon pensil (*Edit*) pada baris data yang baru dibuat.
     - Ubah Nama OPD menjadi `Dinas Perhubungan Balai Pengujian`.
     - Klik **"Perbarui"**.
  6. **Hapus OPD**:
     - Klik ikon tempat sampah (*Hapus*).
     - Pada dialog konfirmasi, klik **"Ya, Hapus OPD"**.
- **Hasil yang Diharapkan**:
  - Notifikasi Toast hijau muncul setiap aksi berhasil (*"Data OPD berhasil disimpan/diperbarui/dihapus"*).
  - Daftar tabel terupdate seketika tanpa perlu reload halaman manual.

### TC-ADM-04: Kelola Kategori Gangguan & Target SLA
- **Tujuan**: Menguji konfigurasi jenis kendala jaringan beserta target batas waktu respon/penyelesaian (*SLA Hours*).
- **Langkah Pengujian**:
  1. Pada halaman Master Data Hub, klik tab **"Kategori Gangguan & SLA"**.
  2. **Tambah Kategori**:
     - Klik tombol **"+ Tambah Kategori"**.
     - Isi Nama Kategori: `Core FO Putus Wilayah Palu Barat`.
     - Pilih Tipe Jaringan: `Fiber Optic`.
     - Isi Target Waktu Penanganan / SLA: `6` Jam.
     - Pilih Status: `Aktif`.
     - Klik **"Tambah Kategori"**.
  3. **Edit Kategori**:
     - Cari kategori yang dibuat, klik tombol *Edit*.
     - Ubah target SLA dari `6` menjadi `4` Jam.
     - Klik **"Simpan"**.
- **Hasil yang Diharapkan**:
  - Kategori baru tersimpan dengan badge jenis infrastruktur sesuai.
  - Nilai target SLA tampil dengan format `X Jam`.

### TC-ADM-05: Kelola Akun Pengguna Sistem
- **Tujuan**: Menguji manajemen akun pengguna (Admin, Teknisi, dan Operator OPD).
- **Langkah Pengujian**:
  1. Pada halaman Master Data Hub, klik tab **"Manajemen Pengguna"**.
  2. **Tambah Pengguna Teknisi Baru**:
     - Klik tombol **"+ Tambah Pengguna"**.
     - Isi Nama: `Budi Teknisi Jaringan`.
     - Isi Email: `budi.teknisi@palukota.go.id`.
     - Isi Nomor WhatsApp: `081299887766`.
     - Pilih Hak Akses (Role): `Teknisi Jaringan`.
     - Isi Password: `password123`.
     - Isi Konfirmasi Password: `password123`.
     - Pilih Status: `Aktif`.
     - Klik **"Simpan"**.
  3. **Edit Pengguna**:
     - Cari nama `Budi Teknisi Jaringan`.
     - Klik tombol *Edit*, ubah nomor telepon menjadi `081299880000`.
     - Kosongkan field password (memastikan password lama tidak berubah jika dikosongkan).
     - Klik **"Simpan Perubahan"**.
- **Hasil yang Diharapkan**:
  - Akun teknisi baru berhasil tersimpan dan langsung bisa digunakan untuk login.

---

## 4. Modul Pembuatan Tiket Darurat (*On-Behalf*) oleh Admin

### TC-ADM-06: Buat Tiket atas Nama OPD dengan Prioritas Emergency
- **Tujuan**: Menguji kemampuan Admin membuat tiket gangguan mewakili OPD tertentu (*On-Behalf*).
- **Langkah Pengujian**:
  1. Buka menu **Tiket Gangguan** pada sidebar.
  2. Klik tombol **"+ Buat Laporan Tiket"** (atau buka menu `/tickets/create`).
  3. Pada panel kotak kuning *"Pembuatan Tiket Darurat (On-Behalf)"*, pilih instansi: `Dinas Kesehatan Kota Palu`.
  4. Pilih Jenis Infrastruktur: `Fiber Optic`.
  5. Pilih Kategori Masalah: `Internet Putus Total / Backbone Down (Emergency)`.
  6. Pilih Tingkat Urgensi: `Darurat (Admin Only)`.
  7. Isi Subjek: `Jalur FO Utama Dinkes Terputus Akibat Pohon Tumbang`.
  8. Isi Lokasi: `Server Room Gedung Dinkes Palu`.
  9. Isi Deskripsi: `Kabel FO di depan gerbang tertimpa dahan pohon, seluruh sistem pelayanan terputus`.
  10. Bagian **Lampiran Bukti Foto**: Perhatikan label bertuliskan *"Lampiran Bukti Foto (Opsional / Tidak Wajib)"*. Biarkan kosong atau unggah 1 foto (.jpg/.png).
  11. Klik tombol **"Kirim Laporan Tiket"**.
- **Hasil yang Diharapkan**:
  - Tiket berhasil dibuat dengan nomor tiket unik otomatis (Format: `TKT-YYYYMMDD-XXXX`).
  - Tiket tercatat dengan reporter Admin namun berasosiasi dengan instansi *Dinas Kesehatan*.
  - Status tiket: `Open`, prioritas: `Emergency`, dan badge SLA mulai menghitung mundur (*countdown*).

---

## 5. Modul Laporan, Filter, & Ekspor Rekapitulasi

### TC-ADM-07: Filter Multi-Kriteria & Ekspor PDF / Excel
- **Tujuan**: Menguji keakuratan filter laporan dan fungsionalitas unduh file rekap PDF & CSV/Excel.
- **Langkah Pengujian**:
  1. Buka menu **Laporan & Rekap** pada sidebar (`/admin/reports`).
  2. **Uji Filter Laporan**:
     - Pilih filter *Semua Instansi / OPD* -> Pilih salah satu instansi tertentu.
     - Pilih filter *Jaringan* -> `Fiber Optic (FO)`.
     - Pilih filter *Status* -> `Semua Status`.
     - Tentukan *Tanggal Mulai* dan *Tanggal Selesai*.
     - Perhatikan data pada tabel di bawahnya langsung menyaring secara realtime.
  3. **Uji Tombol Reset Filter**:
     - Klik tombol ikon putar balik (*Reset Filter*). Semua input kembali ke `Semua / Default`.
  4. **Ekspor PDF**:
     - Klik tombol merah **"Ekspor PDF"**.
     - Tunggu indikator spinner selesai memproses.
     - Buka file PDF hasil unduhan dan periksa layout tabel, kop dokumen Kominfo, dan data tiket.
  5. **Ekspor Excel / CSV**:
     - Klik tombol hijau **"Ekspor Excel"**.
     - Buka file `.csv` hasil unduhan di Microsoft Excel / Spreadsheet.
- **Hasil yang Diharapkan**:
  - File PDF terunduh rapi dengan format siap cetak.
  - File Excel/CSV memuat kolom lengkap (No Tiket, OPD, Kategori, Prioritas, Status, Waktu Selesai, dsb).

---

# BAGIAN 2: PENGUJIAN ROLE TEKNISI JARINGAN

---

## 6. Modul Antrean & Klaim Tiket Teknisi

### TC-TEK-01: Login Teknisi & Akses Antrean Tiket
- **Langkah Pengujian**:
  1. Logout dari akun Admin.
  2. Login dengan akun Teknisi: `teknisi@example.com` / `password`.
  3. Periksa tampilan sidebar: Pastikan menu **Master Data** dan **Laporan & Rekap** *TIDAK MUNCUL* pada akun teknisi.
  4. Buka menu **Tiket Gangguan**.
- **Hasil yang Diharapkan**:
  - Teknisi hanya dapat melihat menu: *Dashboard* dan *Tiket Gangguan*.
  - Antrean tiket menampilkan seluruh tiket terbuka (*Open*) dari semua OPD se-Kota Palu.

### TC-TEK-02: Klaim Tiket Masuk (*Assign to Me*)
- **Tujuan**: Teknisi mengklaim penugasan tiket yang masih berstatus `Open`.
- **Langkah Pengujian**:
  1. Pada tabel tiket, cari tiket dengan status badge abu-abu `Open`.
  2. Klik tombol **"Detail"** pada baris tiket tersebut.
  3. Pada halaman detail tiket (`/tickets/{id}`), periksa tombol biru **"Ambil / Kerjakan Tiket"** di header atas.
  4. Klik tombol **"Ambil / Kerjakan Tiket"**.
  5. Pada dialog modal konfirmasi penugasan, klik tombol **"Ya, Saya Kerjakan"**.
- **Hasil yang Diharapkan**:
  - Status tiket otomatis berubah dari `Open` menjadi `In Progress` (badge biru).
  - Kolom Teknisi Penanggung Jawab berganti menjadi nama teknisi yang sedang login.
  - Riwayat linimasa (*Status History*) mencatat aktivitas penugasan tiket secara otomatis.

---

## 7. Modul Diskusi Tiket, Catatan Internal, & Realtime Chat Reverb

### TC-TEK-03: Kirim Tanggapan Publik ke Pelapor OPD
- **Langkah Pengujian**:
  1. Pada halaman detail tiket yang sedang dikerjakan, lihat panel sebelah kanan atau bagian bawah (Drawer Ruang Diskusi).
  2. Pada kotak teks pesan, ketik: `Tim teknisi sedang menuju ke lokasi OPD untuk pemeriksaan kabel ODP.`
  3. Klik tombol klip kertas **"Lampirkan"** -> pilih 1 foto kondisi lapangan (opsional).
  4. Pastikan kotak centang *"Catatan Internal"* **TIDAK DICENTANG**.
  5. Klik tombol **"Kirim Pesan"**.
- **Hasil yang Diharapkan**:
  - Pesan langsung muncul di gelembung obrolan (*chat bubble*).
  - Pesan dapat dibaca oleh OPD pelapor.

### TC-TEK-04: Kirim Catatan Rahasia Internal (*Internal Note*)
- **Langkah Pengujian**:
  1. Pada kotak formulir tanggapan tiket yang sama, ketik pesan: `Hasil cek OTDR: redaman tinggi di tiang no. 4 (+32 dBm), perlu penggantian pigtail.`
  2. **Centang** pilihan **"Catatan Internal"** (ikon gembok kuning).
  3. Klik tombol **"Kirim Pesan"**.
- **Hasil yang Diharapkan**:
  - Pesan muncul dengan latar belakang kuning khusus dan badge bertuliskan *"Catatan Internal / Rahasia"*.
  - Pesan ini hanya dapat dilihat oleh sesama Teknisi dan Admin, serta terisolasi dari layar OPD.

### TC-TEK-05: Verifikasi Realtime Chat via Laravel Reverb
- **Langkah Pengujian**:
  1. Buka 2 jendela browser berdampingan (*Side by Side*):
     - Browser Kiri: Login sebagai **Teknisi** di halaman detail tiket.
     - Browser Kanan: Login sebagai **Operator OPD** pada tiket yang sama.
  2. Ketik dan kirim pesan pada Browser Kiri (Teknisi).
  3. Perhatikan layar Browser Kanan (OPD) tanpa melakukan reload/refresh F5.
- **Hasil yang Diharapkan**:
  - Pesan baru dari teknisi langsung muncul secara instan (*realtime*) di layar browser OPD dalam hitungan milidetik melalui koneksi WebSocket Laravel Reverb.

---

## 8. Modul Penyelesaian Tiket (*Resolve Ticket*)

### TC-TEK-06: Form Selesaikan Masalah Gangguan (Resolve Modal)
- **Tujuan**: Teknisi menandai tiket telah selesai diperbaiki dengan mengisi catatan solusi dan bukti foto.
- **Langkah Pengujian**:
  1. Pada halaman detail tiket berstatus `In Progress`, klik tombol hijau **"Selesaikan Tiket"** di header atas.
  2. Modal popup *"Selesaikan Penanganan Tiket"* akan terbuka.
  3. Isi bidang **Catatan Solusi Perbaikan (Wajib)**: `Dilakukan splicing ulang pada joint closure tiang 4 dan penggantian patch cord FO. Redaman kembali normal di angka -18.2 dBm.`
  4. Periksa bidang **Foto Bukti Perbaikan**:
     - Perhatikan label: *"Foto Bukti Perbaikan (Opsional)"*.
     - Unggah 1 foto alat ukur / kabel yang sudah tersambung rapi.
  5. Klik tombol hijau **"Tandai Selesai"**.
- **Hasil yang Diharapkan**:
  - Tiket berubah status menjadi `Resolved` (badge hijau).
  - Waktu penyelesaian (*Resolved At*) tercatat akurat.
  - Kartu hijau *"Catatan Solusi Teknis"* tampil di bagian atas detail tiket beserta galeri foto bukti perbaikan yang dapat di-klik untuk melihat pratinjau resolusi penuh (*Image Lightbox*).

---

## 9. Modul Profil & Ganti Password Pengguna

### TC-ADM-TEK-07: Perbarui Informasi Profil & Ubah Password
- **Langkah Pengujian**:
  1. Klik profil di bagian bawah sidebar -> pilih **Profil Pengguna** (`/profile`).
  2. **Ubah Informasi Akun**:
     - Ubah Nama atau Nomor Telepon WhatsApp.
     - Klik **"Simpan Profil"**.
  3. **Ubah Password Akun**:
     - Masukkan *Password Saat Ini*: `password`.
     - Masukkan *Password Baru*: `passwordBaru123`.
     - Masukkan *Konfirmasi Password*: `passwordBaru123`.
     - Klik **"Perbarui Kata Sandi"**.
  4. Uji login kembali menggunakan kata sandi baru.
- **Hasil yang Diharapkan**:
  - Notifikasi Toast berhasil muncul.
  - Pengguna berhasil masuk dengan kata sandi baru.

---

### Lembar Checklist Evaluasi Pengujian Admin & Teknisi
| ID Test Case | Modul / Fitur | Status (Pass/Fail) | Catatan Penguji |
| :--- | :--- | :--- | :--- |
| **TC-ADM-01** | Login Admin & Proteksi Akses Sidebar | [ ] Pass &nbsp; [ ] Fail | |
| **TC-ADM-02** | Dashboard KPI & Statistik Kategori Gangguan | [ ] Pass &nbsp; [ ] Fail | |
| **TC-ADM-03** | Master Data OPD (Tambah, Edit, Hapus) | [ ] Pass &nbsp; [ ] Fail | |
| **TC-ADM-04** | Master Data Kategori & Target SLA | [ ] Pass &nbsp; [ ] Fail | |
| **TC-ADM-05** | Master Data Pengguna & Hak Akses | [ ] Pass &nbsp; [ ] Fail | |
| **TC-ADM-06** | Buat Tiket On-Behalf (Emergency) | [ ] Pass &nbsp; [ ] Fail | |
| **TC-ADM-07** | Filter Laporan & Ekspor PDF/Excel | [ ] Pass &nbsp; [ ] Fail | |
| **TC-TEK-01** | Hak Akses & Antrean Tiket Teknisi | [ ] Pass &nbsp; [ ] Fail | |
| **TC-TEK-02** | Klaim Penugasan Tiket (*Assign to Me*) | [ ] Pass &nbsp; [ ] Fail | |
| **TC-TEK-03** | Diskusi Tiket Publik ke Pelapor | [ ] Pass &nbsp; [ ] Fail | |
| **TC-TEK-04** | Catatan Rahasia Internal (*Internal Note*) | [ ] Pass &nbsp; [ ] Fail | |
| **TC-TEK-05** | Pengujian Realtime Chat via Reverb | [ ] Pass &nbsp; [ ] Fail | |
| **TC-TEK-06** | Selesaikan Tiket & Upload Bukti Opsional | [ ] Pass &nbsp; [ ] Fail | |
| **TC-ADM-TEK-07** | Pengaturan Profil & Ganti Kata Sandi | [ ] Pass &nbsp; [ ] Fail | |
