# PANDUAN PENGUJIAN MANUAL SISTEM (TESTING MANUAL FRONTEND)
## ROLE: PENGGUNA OPD / PELAPOR

Dokumen ini berisi panduan skenario pengujian manual (*end-to-end user interaction*) secara langsung pada antarmuka pengguna (*frontend*) untuk pengguna dari instansi pemerintah dengan peran **Pengguna OPD (Pelapor / *Reporter*)**.

---

### Informasi Kredensial Akun Pengujian
| Role | Instansi | Email | Password |
| :--- | :--- | :--- | :--- |
| **Operator OPD 1** | Dinas Kesehatan | `operator@dinkes.palukota.go.id` | `password` |
| **Operator OPD 2** | Dinas Pendidikan | `operator@disdik.palukota.go.id` | `password` |

---

### Prasyarat Menjalankan Sistem
Pastikan layanan-layanan berikut telah dijalankan di terminal masing-masing sebelum memulai pengujian:
1. **Web Server**: `php artisan serve` (http://localhost:8000)
2. **WebSocket Reverb**: `php artisan reverb:start --debug`
3. **Frontend Assets**: `npm run dev`
4. **Queue Worker**: `php artisan queue:listen --tries=1 --timeout=0`

---

# BAGIAN 1: PENGUJIAN ISOLASI DATA & DASHBOARD

### TC-OPD-01: Autentikasi & Isolasi Data Laporan
- **Tujuan**: Memastikan pengguna OPD berhasil login dan tidak bisa melihat data laporan dari instansi lain (keamanan isolasi data).
- **Langkah Pengujian**:
  1. Login menggunakan akun OPD 1 (`operator@dinkes.palukota.go.id`).
  2. Perhatikan menu navigasi sebelah kiri, pastikan hanya ada dua menu utama: **Dashboard** dan **Tiket Saya**. Menu Admin harus disembunyikan.
  3. Buka menu **Tiket Saya**. Perhatikan daftar tiket yang ada, catat nomor tiketnya.
  4. Logout.
  5. Login kembali menggunakan akun OPD 2 (`operator@disdik.palukota.go.id`).
  6. Buka menu **Tiket Saya**.
- **Hasil yang Diharapkan**:
  - OPD 1 hanya melihat tiket milik Dinas Kesehatan.
  - OPD 2 memiliki tabel antrean yang kosong atau hanya menampilkan tiket milik Dinas Pendidikan. Tiket OPD 1 tidak boleh terlihat.

### TC-OPD-02: Dashboard Ringkasan OPD
- **Tujuan**: Memverifikasi kesesuaian metrik data laporan yang ada di *Dashboard* masing-masing instansi.
- **Langkah Pengujian**:
  1. Pada akun OPD 1, klik menu **Dashboard**.
  2. Periksa tiga kartu metrik ringkasan:
     - **Tiket Aktif** (Gangguan sedang ditangani).
     - **Selesai Menunggu Konfirmasi** (Perlu divrefikasi).
     - **Total Seluruh Laporan** (Riwayat keseluruhan).
  3. Periksa tabel **Aktivitas Tiket Terkini** di bagian bawah.
- **Hasil yang Diharapkan**:
  - Angka pada kartu metrik sesuai dengan total tiket Dinas Kesehatan.
  - Tabel aktivitas menampilkan tiket milik Dinas Kesehatan dengan format status dan SLA yang rapi.

---

# BAGIAN 2: MODUL PELAPORAN TIKET GANGGUAN

### TC-OPD-03: Buat Laporan Tiket Baru via Formulir Penuh
- **Tujuan**: Menguji validasi form, pilihan dinamis bertingkat, dan pengunggahan file opsional.
- **Langkah Pengujian**:
  1. Buka menu **Tiket Saya**.
  2. Klik tombol **"+ Buat Laporan Baru"** (Anda akan diarahkan ke halaman `/tickets/create`).
  3. **Tipe Infrastruktur**: Klik kartu `Jaringan WiFi` (Pilihan kategori di bawahnya akan muncul secara otomatis).
  4. **Kategori Masalah**: Pilih `Wi-Fi Terhubung tetapi Tidak Ada Akses Internet`.
  5. Isi **Subjek Singkat**: `Koneksi WiFi Ruang Rapat Lt.2 Tidak Ada Internet`.
  6. Isi **Lokasi Spesifik**: `Ruang Rapat Utama Lantai 2`.
  7. Isi **Deskripsi Rinci**: `Ponsel dan laptop bisa terhubung ke SSID 'Pemkot_Palu', namun browser menampilkan status 'No Internet' sejak pagi hari saat rapat berlangsung.`
  8. Pilih **Tingkat Urgensi**: `Sedang`.
  9. Perhatikan area **Lampiran Bukti Foto**. Terdapat tulisan kecil *"(Opsional / Tidak Wajib)"*.
  10. Seret (*drag & drop*) 1 file gambar bukti error koneksi ke kotak area unggah, atau klik kotak tersebut.
  11. Klik tombol **"Kirim Laporan Tiket"**.
- **Hasil yang Diharapkan**:
  - Tiket berhasil disimpan. Notifikasi toast hijau muncul.
  - Pengguna dikembalikan ke tabel daftar tiket dan tiket baru muncul paling atas dengan status `Open` abu-abu.

### TC-OPD-04: Buat Tiket via Modal Cepat (Tanpa Lampiran)
- **Tujuan**: Memastikan form popup cepat berfungsi dan tiket tetap bisa dikirim walau lampiran dikosongkan.
- **Langkah Pengujian**:
  1. Tetap di halaman antrean **Tiket Saya**.
  2. Klik tombol merah muda **"+ Lapor Masalah Jaringan"** di sudut kanan atas layar (atau melayang di sudut layar).
  3. Modal popup akan muncul.
  4. Pilih **Jaringan LAN**, lalu kategori `Kabel UTP / LAN Gedung Putus atau Terkelupas`.
  5. Pilih Prioritas `Tinggi (High)`.
  6. Isi judul, lokasi, dan deskripsi gangguan dengan data bebas.
  7. Pada bagian **Lampiran Bukti Foto**, biarkan kotak unggah tetap kosong (tidak perlu melampirkan gambar).
  8. Klik tombol biru **"Kirim Laporan Tiket"**.
- **Hasil yang Diharapkan**:
  - Modal otomatis tertutup. Toast berhasil muncul.
  - Tiket masuk ke dalam daftar tabel antrean tanpa ada validasi *error* soal lampiran file.

---

# BAGIAN 3: MODUL INTERAKSI & SIKLUS HIDUP TIKET

### TC-OPD-05: Filter Pencarian & Monitoring SLA
- **Langkah Pengujian**:
  1. Di tabel antrean tiket, perhatikan kolom **SLA/Target**.
  2. Cari kotak pencarian, ketikkan: `Koneksi WiFi Ruang Rapat`.
  3. Klik filter status di sebelah kanan, pilih `Open`.
- **Hasil yang Diharapkan**:
  - Baris SLA menampilkan indikator sisa waktu jam dengan warna hijau (Aman), kuning (Peringatan), atau merah (Terlambat).
  - Tabel menyaring hasil dengan cepat sesuai kata kunci pencarian.

### TC-OPD-06: Ruang Diskusi Tiket Publik & Lampiran Pesan
- **Langkah Pengujian**:
  1. Klik tombol **"Detail"** pada salah satu tiket di tabel.
  2. Gulir ke bagian bawah untuk menemukan kotak isian balas pesan.
  3. Ketikkan pesan: `Mohon segera ditangani pak, karena ruangan akan segera digunakan jam 2 siang ini.`
  4. Klik tombol klip kertas **"Lampirkan (0/3)"**, lalu pilih 1 gambar.
  5. Klik tombol pesawat kertas **"Kirim Pesan"**.
- **Hasil yang Diharapkan**:
  - Pesan yang dikirim langsung muncul di deretan riwayat diskusi tanpa me-*refresh* seluruh halaman.
  - Terdapat tombol `[X]` (Silang merah) untuk menghapus pratinjau gambar jika batal mengunggah di kolom balasan.

### TC-OPD-07: Batalkan Laporan Tiket
- **Tujuan**: Memastikan OPD bisa membatalkan laporannya sendiri bila kendala sudah pulih atau salah input, asalkan status tiket masih `Open`.
- **Langkah Pengujian**:
  1. Buka salah satu tiket yang masih berstatus `Open`.
  2. Klik tombol merah **"Batalkan Laporan"** di pojok kanan atas.
  3. Dialog konfirmasi akan muncul. Masukkan alasan: `Jaringan sudah pulih dengan sendirinya tanpa intervensi teknisi.`
  4. Klik **"Ya, Batalkan Laporan"**.
- **Hasil yang Diharapkan**:
  - Status tiket dan label berubah menjadi `Cancelled`.
  - Kolom diskusi di bawahnya otomatis menghilang dan ditutup.
  - Tombol batal sudah tidak ada lagi di atas.

### TC-OPD-08: Konfirmasi Penyelesaian Tiket (Tutup Tiket / *Closed*)
- **Tujuan**: OPD menerima hasil perbaikan dari teknisi dan menutup tiket secara permanen.
- **Langkah Pengujian**:
  *(Catatan: Anda bisa masuk menggunakan akun Teknisi di tab Incognito untuk mengubah status tiket menjadi "Resolved" terlebih dahulu, kemudian kembali ke akun OPD)*.
  1. Buka tiket milik OPD Anda yang berstatus `Resolved` (badge hijau).
  2. Di bagian atas detail, akan muncul kartu hijau berisi keterangan **Catatan Solusi Teknis** dari teknisi.
  3. Jika ada foto teknisi, klik foto tersebut untuk memastikan pratinjau besar (*Lightbox*) muncul.
  4. Klik tombol hitam **"Konfirmasi Selesai (Tutup Tiket)"** di pojok kanan atas layar.
  5. Klik tombol konfirmasi penutupan tiket di modal.
- **Hasil yang Diharapkan**:
  - Status tiket berubah menjadi `Closed` secara permanen.
  - Tidak ada lagi tombol interaksi di pojok kanan atas.

### TC-OPD-09: Ajukan Buka Kembali (*Reopen Ticket*)
- **Tujuan**: OPD dapat menolak hasil perbaikan teknisi jika ternyata masalah masih muncul, dan mengajukan buka ulang.
- **Langkah Pengujian**:
  1. Buka tiket lain yang sedang berstatus `Resolved` (badge hijau).
  2. Klik tombol kuning **"Ajukan Buka Kembali"** di pojok kanan atas.
  3. Masukkan keluhan/alasan: `Halo kak, internetnya hidup sebentar tapi 5 menit kemudian lampu PON di modem merah dan mati lagi.`
  4. Klik **"Buka Kembali Tiket"**.
- **Hasil yang Diharapkan**:
  - Status tiket kembali menjadi `In Progress`.
  - Di linimasa riwayat status dan ruang diskusi, muncul riwayat dan pesan komplain Anda secara otomatis.
  - Penugasan tiket tidak terhapus (nama teknisi yang menanganinya masih sama).

---

# BAGIAN 4: MODUL PROFIL PENGGUNA

### TC-OPD-10: Pengaturan Profil Operator & Kontak WhatsApp
- **Langkah Pengujian**:
  1. Buka profil di bagian pojok kiri bawah -> klik logo profil/nama pengguna Anda.
  2. Di kolom **Informasi Profil**, ubah format Nomor Telepon Anda menjadi format penulisan normal seperti: `0852-1111-2222`.
  3. Klik tombol **"Simpan"**.
- **Hasil yang Diharapkan**:
  - Profil berhasil disimpan dengan memunculkan alert sukses.
  - Sistem otomatis membersihkan format nomor telepon (*auto-normalize*) ke format standar (misal: membuang karakter strip `-` dan mengubah prefix `08` menjadi `628` secara otomatis di *database*, namun input layar tetap menampilkan format pengguna).

---

### Lembar Checklist Evaluasi Pengujian Pengguna OPD
| ID Test Case | Modul / Fitur | Status (Pass/Fail) | Catatan Penguji |
| :--- | :--- | :--- | :--- |
| **TC-OPD-01** | Autentikasi & Isolasi Data OPD | [ ] Pass &nbsp; [ ] Fail | |
| **TC-OPD-02** | Kesesuaian Metrik Dashboard OPD | [ ] Pass &nbsp; [ ] Fail | |
| **TC-OPD-03** | Formulir Tiket Baru + Lampiran Gambar | [ ] Pass &nbsp; [ ] Fail | |
| **TC-OPD-04** | Popup Tiket Tanpa Lampiran (Opsional) | [ ] Pass &nbsp; [ ] Fail | |
| **TC-OPD-05** | Filter & Indikator Batas Waktu SLA | [ ] Pass &nbsp; [ ] Fail | |
| **TC-OPD-06** | Diskusi Tiket Publik & Hapus Pratinjau | [ ] Pass &nbsp; [ ] Fail | |
| **TC-OPD-07** | Pembatalan Laporan (Cancel Ticket) | [ ] Pass &nbsp; [ ] Fail | |
| **TC-OPD-08** | Konfirmasi Perbaikan (Close Ticket) | [ ] Pass &nbsp; [ ] Fail | |
| **TC-OPD-09** | Buka Ulang Tiket Komplain (Reopen) | [ ] Pass &nbsp; [ ] Fail | |
| **TC-OPD-10** | Profil Pengguna & Format Nomor Telepon | [ ] Pass &nbsp; [ ] Fail | |
