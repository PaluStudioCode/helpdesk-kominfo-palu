# Analisis & Rincian Data Seeder Helpdesk Kominfo Palu

Dokumen ini memuat rangkuman lengkap struktur data yang di-generate oleh seluruh seeder yang ada pada proyek **Helpdesk Kominfo Palu**.

---

## 1. Ikhtisar File Seeder

Saat ini terdapat 3 file seeder utama pada direktori `database/seeders/`:

- `DatabaseSeeder.php` -> Menginisialisasi Akun Admin & Teknisi, lalu memanggil seeder lainnya.
- `TicketCategorySeeder.php` -> Menginisialisasi 18 Kategori Tiket & SLA Jaringan.
- `DepartmentSeeder.php` -> Menginisialisasi 102 Instansi / OPD Pemkot Palu & 102 Akun Operator OPD.

---

## 2. Rincian Data Masing-Masing Seeder

### A. `DatabaseSeeder.php`
Menginisialisasi akun pengguna inti untuk role **Admin** dan **Teknisi**:

| Role | Nama | Email Login | Password Default | No. WhatsApp |
| :--- | :--- | :--- | :--- | :--- |
| **Admin** | Administrator Kominfo | `admin@kominfo.go.id` | `password` | `6280011112222` |
| **Technician** | Ahmad Teknisi | `teknisi@example.com` | `password` | `6280033334444` |
| **Technician** | Budi Teknisi | `teknisi2@example.com` | `password` | `6280055556666` |

---

### B. `TicketCategorySeeder.php`
Menyediakan **18 Kategori Tiket Gangguan** yang dikelompokkan ke dalam 3 jenis infrastruktur jaringan (`network_type`) dengan standar SLA (Service Level Agreement):

#### 1. Fiber Optic (`fiber_optic`) — 6 Kategori
- `Internet Putus Total / Backbone Down (Emergency)` — SLA: **4 Jam**
- `Kabel FO Utama / Backbone Putus Fisik` — SLA: **6 Jam**
- `Kerusakan / Masalah SFP / Media Converter` — SLA: **6 Jam**
- `Redaman Fiber Optic Tinggi (High Attenuation / Bending)` — SLA: **8 Jam**
- `Core FO Rusak / Sambungan Joint Closure Bermasalah` — SLA: **8 Jam**
- `Koneksi FO Dropcore Putus / Terjepit di Lokasi OPD` — SLA: **12 Jam**

#### 2. Jaringan LAN (`lan`) — 6 Kategori
- `Krimpingan RJ45 Longgar / Konektor Rusak` — SLA: **4 Jam**
- `Masalah IP Conflict / DHCP / Gateway Not Reachable` — SLA: **4 Jam**
- `Kabel UTP / LAN Gedung Putus atau Terkelupas` — SLA: **6 Jam**
- `Switch Distribusi Gedung Hang / Mati Listrik` — SLA: **6 Jam**
- `Koneksi Port Switch / Patch Panel Bermasalah` — SLA: **8 Jam**
- `Koneksi LAN Antar Ruang / Server Lokal Lambat` — SLA: **8 Jam**

#### 3. Jaringan Wi-Fi (`wifi`) — 6 Kategori
- `Wi-Fi Terhubung tetapi Tidak Ada Akses Internet` — SLA: **4 Jam**
- `Gagal Login / Otentikasi Captive Portal Wi-Fi` — SLA: **4 Jam**
- `Access Point Mati Total / Indikator Merah` — SLA: **6 Jam**
- `Overload Pengguna / Kapasitas AP Penuh` — SLA: **6 Jam**
- `Interferensi Sinyal Wi-Fi / Kanal Padat` — SLA: **8 Jam**
- `Sinyal Wi-Fi Lemah / Blind Spot Ruangan` — SLA: **12 Jam**

---

### C. `DepartmentSeeder.php`
Menghasilkan **102 Instansi / Unit Kerja Pemerintah Kota Palu**, meliputi:
- Sekretariat Daerah (Setda) & Ruang Pimpinan (Walikota, Wakil Walikota, Sekda, Asisten, Staf Ahli)
- 18 Bagian Setda (Keuangan, Organisasi, Hukum, Kesra, Prokopim, PBJ, dll.)
- Sekretariat DPRD (Setwan)
- Seluruh Dinas & Badan Daerah (Dinkes, Disdik, Diskominfo, Dishub, Bappeda, Bapenda, BPKAD, BKPSDM, dll.)
- 14 Puskesmas (PKM Singgani, Nosarara, Mabelopura, Birobuli, Talise, dll.)
- Rumah Sakit Umum Anutapura
- 8 Kantor Kecamatan (Palu Barat, Palu Selatan, Palu Timur, Palu Utara, Tatanga, Mantikulore, Tawaeli, Ulujadi)
- 18+ Kantor Kelurahan

#### Otomasi Akun Operator OPD:
Untuk setiap departemen yang dibuat, seeder langsung membuat **1 akun Operator OPD (`role: opd_user`)**:
- **Format Email**:
  - Dinkes: `operator@dinkes.palukota.go.id`
  - Disdik: `operator@disdik.palukota.go.id`
  - Lainnya: `operator.{slug_code}@palukota.go.id` (contoh: `operator.setda.palu@palukota.go.id`, `operator.diskominfo.palu@palukota.go.id`, dsb.)
- **Password Default**: `password`
- **Format Nomor Telepon**: `081234560001` s/d `081234560102`
- **Status**: `active`

---

## 3. Catatan Penting
- Saat ini **belum ada Seeder Tiket Dummy (`TicketSeeder`)** bawaan. Database setelah `db:seed` hanya memiliki master data (Users, Departments, Categories) dan belum memiliki data tiket awal.
- Jika Anda membutuhkan seeder untuk data dummy tiket (misalnya tiket dengan status `pending_admin`, `in_progress`, `pending_approval`, `closed`, dan `cancelled` agar dashboard langsung terisi data simulasi), kita dapat membuatkan `DummyTicketSeeder`.
