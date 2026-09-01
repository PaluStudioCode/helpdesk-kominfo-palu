# Rencana Implementasi: Penghapusan Distribusi Infrastruktur Jaringan & Aktivitas Tiket Terkini

Dokumen ini memuat rencana penghapusan komponen **Distribusi Infrastruktur Jaringan** dan **Aktivitas Tiket Terkini** dari halaman Dashboard beserta seluruh query backend terkait.

---

## 1. Ringkasan Perubahan

Sesuai permintaan, dua komponen berikut akan dihapus secara menyeluruh:
1. ❌ **Distribusi Infrastruktur Gangguan** (Card Fiber Optic, LAN, WiFi).
2. ❌ **Aktivitas Tiket Terkini** (Daftar tiket terbaru).
3. ❌ **Backend Query Terkait** (Penghitungan `fiber_optic`, `lan`, `wifi`, `total_departments`, dan query `$recentTickets`).

---

## 2. File yang Akan Diubah

### A. Backend Controller (`app/Http/Controllers/DashboardController.php`)
- **Hapus** query `$recentTickets` untuk seluruh role.
- **Hapus** kalkulasi `fiber_optic`, `lan`, `wifi`, `total_departments` pada role `admin`.
- Hanya mengirimkan `$stats` bersih ke Inertia view.

### B. Frontend Page (`resources/js/Pages/Dashboard.vue`)
- Hapus prop `recentTickets` dari `defineProps`.
- Hapus import icon yang tidak lagi terpakai (`Cable`, `Network`, `Wifi`, dll).
- Hapus blok HTML Distribusi Infrastruktur Gangguan.
- Hapus blok HTML Aktivitas Tiket Terkini.

### C. Automated Test (`tests/Feature/DashboardTest.php`)
- Hapus assertion untuk `stats.total_departments`, `stats.fiber_optic`, dan `recentTickets`.
- Pastikan assertion fokus pada 6 card metrik utama Admin dan metrik OPD user.

---

## 3. Rencana Verifikasi

1. Menjalankan PHPUnit test:
   ```bash
   php artisan test --filter=DashboardTest
   ```
2. Menjalankan full test suite:
   ```bash
   php artisan test
   ```
3. Membangun frontend:
   ```bash
   cmd /c npm run build
   ```
