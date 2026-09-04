<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketReply;
use App\Models\TicketStatusHistory;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TicketSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admin = User::where('role', 'admin')->first();
        $technicians = User::where('role', 'technician')->get();
        $tech1 = $technicians->first();
        $tech2 = $technicians->count() > 1 ? $technicians->skip(1)->first() : $tech1;

        $opdUsers = User::where('role', 'opd_user')->get()->keyBy('department_id');
        $departments = Department::all();
        $categoriesByNet = TicketCategory::all()->groupBy('infrastructure_type');

        if ($departments->isEmpty() || $categoriesByNet->isEmpty()) {
            $this->command->warn('Department atau TicketCategory belum di-seed. Harap jalankan DepartmentSeeder dan TicketCategorySeeder terlebih dahulu.');
            return;
        }

        // Realistic Issue Templates with Full Structured Resolution Details
        $issueTemplates = [
            'Fiber optic' => [
                [
                    'title' => 'Koneksi FO Dropcore Utama Terputus',
                    'desc' => 'Kabel dropcore yang masuk ke rack server utama OPD terputus akibat perbaikan plafon gedung. Seluruh koneksi internet dan intranet gedung mati.',
                    'loc' => 'Ruang Server & Ruang Kepala Bagian',
                    'res' => 'Penyambungan ulang kabel dropcore menggunakan fusion splicer dan penggantian pigtail SC-APC. Redaman kembali normal di -18 dBm.',
                    'affected_device' => 'Kabel Fiber Optic (Drop Core / Feeder)',
                    'inspection' => 'Kabel dropcore yang masuk ke rack server utama terputus fisik. Redaman optik tak terhingga (LOS menyala merah).',
                    'cause' => 'Kabel dropcore terpotong saat aktivitas renovasi dan perbaikan plafon gedung OPD.',
                    'action' => 'Melakukan penarikan ulang kabel dropcore 25 meter, penyambungan core kaca dengan fusion splicer, dan terminasi ulang ke OTB.',
                    'materials' => 'Kabel Drop Core Fiber Optic (1-Core / 2-Core) (25 meter), Protection Sleeve FO (Splicing) (2 pcs), Pigtail Fiber Optic SC/UPC (1 pcs)',
                    'test_result' => 'Stabil / Link Up',
                    'test_param' => 'Rx Optical Power: -18.2 dBm (Normal). Ping gateway 10.10.1.1 RTT=1ms loss 0%.',
                    'notes' => 'Jalur kabel telah dimasukkan ke dalam pelindung pipa conduit agar aman dari renovasi.',
                ],
                [
                    'title' => 'Internet Putus Total / Backbone Down',
                    'desc' => 'Layanan internet gedung kantor tidak dapat diakses sejak pagi hari. Lampu indikator LOS pada modem/converter berkedip merah.',
                    'loc' => 'Ruang Pelayanan Terpadu Lantai 1',
                    'res' => 'Pengecekan jalur distribusi kabel FO dari ODP terdekat, perbaikan konektor patchcord yang patah di OTB.',
                    'affected_device' => 'Optical Termination Box (OTB) / Joint Closure',
                    'inspection' => 'Pemeriksaan sinyal optik dari ODP terdekat normal (-17 dBm), namun port OTB tidak meneruskan sinyal ke switch.',
                    'cause' => 'Konektor patchcord FO patah di bagian ferrule dalam box OTB.',
                    'action' => 'Mengganti patchcord fiber optic baru SC-SC 2 meter dan membersihkan barel adaptor OTB.',
                    'materials' => 'Patch Cord Fiber Optic (SC-SC / LC-SC) (1 pcs), Adaptor Fiber Optic SC/UPC (1 pcs)',
                    'test_result' => 'Normal / Berfungsi Baik',
                    'test_param' => 'Rx Power: -17.8 dBm. Ping 8.8.8.8 latency 18ms, loss 0%.',
                    'notes' => 'OTB telah dikunci dan diberi label penomoran port.',
                ],
                [
                    'title' => 'Redaman Fiber Optic Tinggi (Koneksi Lambat & ROP)',
                    'desc' => 'Kecepatan internet drop drastis dan sering request timed out (RTO). Aktivitas input data ke server Pemkot terhambat.',
                    'loc' => 'Ruang Bidang Perencanaan & Anggaran',
                    'res' => 'Pembersihan ferrule konektor optic dengan optical cleaner dan pelurusan kabel patchcord yang mengalami bending.',
                    'affected_device' => 'Kabel Fiber Optic (Drop Core / Feeder)',
                    'inspection' => 'Redaman kabel dropcore sangat tinggi mencapai -29.5 dBm, terjadi packet loss hingga 28%.',
                    'cause' => 'Kabel patchcord FO tertekuk (macro-bending) tajam di belakang lemari server.',
                    'action' => 'Meluruskan jalur kabel optic, membersihkan ujung ferrule dengan optic cleaner pen, dan menata radius bending.',
                    'materials' => 'Kabel Ties / Velcro (1 pack)',
                    'test_result' => 'Optimal',
                    'test_param' => 'Redaman turun ke -17.4 dBm. Ping gateway RTT < 1ms, throughput internet 95 Mbps.',
                    'notes' => 'Diberikan slack kabel spiral wrapping agar tidak mudah tertekuk kembali.',
                ],
                [
                    'title' => 'Core FO Joint Closure Bermasalah di Luar Gedung',
                    'desc' => 'Koneksi ke server induk Pemkot putus setelah hujan lebat dan angin kencang di sekitar tiang distribusi.',
                    'loc' => 'Tiang Distribusi Samping Kantor',
                    'res' => 'Rekonstruksi sambungan core di joint closure dan proteksi isolasi waterproof.',
                    'affected_device' => 'Optical Termination Box (OTB) / Joint Closure',
                    'inspection' => 'Sambungan core di tiang distribusi terputus intermiten saat terkena getaran angin.',
                    'cause' => 'Pengunci tray sambungan pada joint closure longgar dan kemasukan rembesan air hujan.',
                    'action' => 'Membuka joint closure, splicing ulang core putus, mengganti protection sleeve, dan sealing waterproof.',
                    'materials' => 'Protection Sleeve FO (Splicing) (2 pcs), Isolasi Listrik / Heat Shrink (1 roll)',
                    'test_result' => 'Stabil / Link Up',
                    'test_param' => 'Redaman sambungan 0.02 dB per splice, redaman total end-to-end -18.1 dBm.',
                    'notes' => 'Joint closure ditutup rapat dengan seal karet baru dan diikat kuat pada tiang.',
                ],
                [
                    'title' => 'Kabel FO Dropcore Terjepit di Pintu Ruang Rapat',
                    'desc' => 'Kabel optik dropcore terjepit pintu hingga jaket pelindung terkoyak dan koneksi ruangan terputus.',
                    'loc' => 'Ruang Rapat VIP',
                    'res' => 'Penyambungan ulang core dan penataan jalur kabel dengan protective spiral wrapping.',
                    'affected_device' => 'Kabel Fiber Optic (Drop Core / Feeder)',
                    'inspection' => 'Jaket kabel pelindung terkoyak dan core optik retak di kusen pintu ruang rapat.',
                    'cause' => 'Kabel ditarik melintasi bawah pintu tanpa pelindung kabel lantai.',
                    'action' => 'Menyambung ulang core dengan fast connector SC/UPC dan memasang kabel protector duct di lantai.',
                    'materials' => 'Fast Connector SC/UPC (2 pcs), Pipa Conduit / Cable Protector Duct (2 batang)',
                    'test_result' => 'Normal / Berfungsi Baik',
                    'test_param' => 'Ping gateway 10.10.1.1 (1ms, loss 0%). Koneksi stabil.',
                    'notes' => 'Jalur kabel dialihkan ke atas kusen pintu agar tidak terinjak.',
                ],
            ],
            'Perangkat/Akses' => [
                [
                    'title' => 'Kabel UTP / LAN Ruang Staf Putus',
                    'desc' => 'Kabel jaringan di bawah meja staf terjepit kursi dan terkelupas sehingga komputer tidak mendapatkan koneksi internet.',
                    'loc' => 'Ruang Bidang Keuangan & Pembukuan',
                    'res' => 'Penarikan kembali kabel UTP Cat6 baru sepanjang 15 meter dan pemasangan ducting kabel pelindung.',
                    'affected_device' => 'Kabel UTP / Patch Cord / LAN RJ45',
                    'inspection' => 'Kabel LAN di bawah meja staf terputus 4 pin out of 8 saat diuji dengan LAN cable tester.',
                    'cause' => 'Kabel terjepit roda kursi kerja staf hingga tembaga kabel putus di dalam jaket.',
                    'action' => 'Menarik kabel UTP Cat6 baru sepanjang 15 meter dari wallplate ke meja staf dan memasang spiral ducting.',
                    'materials' => 'Kabel UTP / LAN Cat6 (15 meter), Konektor RJ-45 Cat6 (2 pcs), Pipa Conduit / Cable Protector Duct (1 batang)',
                    'test_result' => 'Normal',
                    'test_param' => 'Cable tester pinout 1-8 lurus (straight-through). Speed LAN gigabit 1000 Mbps full duplex.',
                    'notes' => 'Kabel telah diikat rapi di belakang meja kerja.',
                ],
                [
                    'title' => 'Krimpingan RJ45 Longgar / Port Rusak',
                    'desc' => 'Koneksi sering terputus-putus (intermittent) saat kabel jaringan tersenggol di PC kerja staf.',
                    'loc' => 'Ruang Sekretariat & Tata Usaha',
                    'res' => 'Rekrimping konektor RJ45 modular Cat6 baru dan pengetesan dengan cable tester, pinout normal 8/8.',
                    'affected_device' => 'Kabel UTP / Patch Cord / LAN RJ45',
                    'inspection' => 'Pengait konektor RJ-45 patah sehingga jack mudah lepas dari port LAN komputer staf.',
                    'cause' => 'Konektor RJ-45 aus dan pengait plastik pengunci patah.',
                    'action' => 'Memotong ujung kabel yang aus dan melakukan rekrimping menggunakan konektor RJ-45 Cat6 modular baru.',
                    'materials' => 'Konektor RJ-45 Cat6 (2 pcs)',
                    'test_result' => 'Normal / Berfungsi Baik',
                    'test_param' => 'Koneksi gigabit 1 Gbps aktif, ping ke DNS server 1ms tanpa loss.',
                    'notes' => 'Konektor baru terpasang rapat dengan klik pengunci kuat.',
                ],
                [
                    'title' => 'Switch Distribusi Gedung Hang / Mati Total',
                    'desc' => 'Satu lantai kantor tidak bisa terhubung ke jaringan LAN maupun printer sharing bersama.',
                    'loc' => 'Rack Switch Lantai 2',
                    'res' => 'Restart hard-reboot switch distribusi, pergantian port uplink, dan penataan kabel patch panel.',
                    'affected_device' => 'Switch Access',
                    'inspection' => 'Semua port switch mati total dan tidak merespon tombol reboot.',
                    'cause' => 'Switch mengalami overheat pada unit power switching setelah lonjakan listrik ruang arsip.',
                    'action' => 'Mengganti unit switch access 24-port dengan unit cadangan Kominfo, konfigurasi VLAN port uplink.',
                    'materials' => 'Switch Hub (8-Port / 16-Port / 24-Port) (1 unit), Patch Cord UTP Cat6 (2 pcs)',
                    'test_result' => 'Stabil / Link Up',
                    'test_param' => 'Seluruh 24 port aktif, VLAN ID 10 (Data) dan VLAN ID 20 (Voice) tersinkronisasi.',
                    'notes' => 'Unit lama dibawa ke bengkel Diskominfo untuk diagnosis power supply.',
                ],
                [
                    'title' => 'Port Patch Panel Bermasalah',
                    'desc' => 'Wallplate jaringan nomor 04 di meja rapat tidak mengeluarkan sinyal konektivitas LAN.',
                    'loc' => 'Ruang Rapat Utama',
                    'res' => 'Punchdown ulang kabel UTP ke keystone jack wallplate dan pengecekan koneksi end-to-end.',
                    'affected_device' => 'Keystone Jack RJ-45 / Wallplate',
                    'inspection' => 'Wallplate nomor 04 tidak menghantarkan sinyal link LAN ke PC ruangan.',
                    'cause' => 'Kawat pin nomor 3 dan 6 pada keystone jack terlepas dari terminal punchdown.',
                    'action' => 'Melakukan re-punchdown kabel UTP ke keystone jack Cat6 dan mengganti patchcord LAN penghubung.',
                    'materials' => 'Modular Jack / Keystone RJ-45 (1 pcs), Patch Cord UTP Cat6 (1 pcs)',
                    'test_result' => 'Optimal',
                    'test_param' => 'Pengetesan link pass 1000BASE-T, ping ke router gateway 0.5ms.',
                    'notes' => 'Wallplate telah diberi label nomor identifikasi port baru.',
                ],
                [
                    'title' => 'Access Point Mati Total / Indikator Merah',
                    'desc' => 'Access point yang terpasang di lorong tengah kantor mati dan tidak memancarkan sinyal SSID.',
                    'loc' => 'Lorong Lantai 1 Dekat Ruang Rapat',
                    'res' => 'Pemeriksaan port access point, restart perangkat, dan konfigurasi ulang profil SSID.',
                    'affected_device' => 'Access Point (AP) / WiFi Indoor',
                    'inspection' => 'AP di lorong tidak menyala, tidak ada indikator LED link pada switch PoE.',
                    'cause' => 'Unit access point rusak akibat induksi petir pada kabel tarikan luar.',
                    'action' => 'Mengganti 1 unit Access Point indoor dual-band baru dan mengadopsi kembali ke Cloud Controller Kominfo.',
                    'materials' => 'Access Point (AP) (1 unit), Patch Cord UTP Cat6 (1 pcs)',
                    'test_result' => 'Normal / Berfungsi Baik',
                    'test_param' => 'SSID "Pemkot-Palu" aktif di frekuensi 2.4 GHz & 5 GHz, Tx Power 20 dBm, 35 user terhubung.',
                    'notes' => 'Konfigurasi SSID dan roaming telah disesuaikan dengan AP sekitarnya.',
                ],
                [
                    'title' => 'Sinyal Wi-Fi Lemah / Blind Spot Ruangan',
                    'desc' => 'Sinyal Wi-Fi di ruangan pimpinan sangat lemah (hanya 1 bar) dan sering terputus saat zoom meeting.',
                    'loc' => 'Ruang Pimpinan & Staf Ahli',
                    'res' => 'Reposisi letak Access Point ke area tengah ruangan dan optimalisasi daya transmisi RF (Tx Power).',
                    'affected_device' => 'Access Point (AP) / WiFi Indoor',
                    'inspection' => 'Kuat sinyal di meja pimpinan hanya -82 dBm (lemah/1 bar) karena terhalang dinding partisi tebal.',
                    'cause' => 'Posisi AP terlalu jauh di sudut koridor dan terhalang struktur beton.',
                    'action' => 'Reposisi titik AP ke plafon koridor bagian tengah dan optimalisasi power transmisi 5GHz.',
                    'materials' => 'Patch Cord UTP Cat6 (1 pcs), Kabel Ties / Velcro (1 pack)',
                    'test_result' => 'Optimal',
                    'test_param' => 'Kuat sinyal di ruang pimpinan meningkat drastis ke -54 dBm (sangat baik), throughput 85 Mbps.',
                    'notes' => 'Staf pimpinan telah menguji video conference dan berjalan lancar tanpa buffer.',
                ],
            ],
            'Power/poe' => [
                [
                    'title' => 'Adaptor / PoE Injector Access Point Mati Total',
                    'desc' => 'Access point di lantai 2 tidak menyala sama sekali, adaptor PoE injector tidak mengeluarkan lampu indikator hijau.',
                    'loc' => 'Gedung B Lantai 2',
                    'res' => 'Penggantian unit adaptor PoE injector 24V 1A baru dan pengetesan voltase output normal.',
                    'affected_device' => 'Power Supply / PoE Injector / UPS',
                    'inspection' => 'LED daya adaptor PoE mati dan output tegangan multimeter 0 Volt (drop).',
                    'cause' => 'Komponen kapasitor pada adaptor PoE meletus karena fluktuasi voltase PLN.',
                    'action' => 'Mengganti unit PoE Injector Gigabit 24V 1A baru dan pengetesan daya ke AP.',
                    'materials' => 'PoE Injector (24V / 48V) (1 unit)',
                    'test_result' => 'Stabil / Link Up',
                    'test_param' => 'Tegangan output stabil di 24.1V DC. Access Point booting normal dan link UP 1 Gbps.',
                    'notes' => 'Disarankan penambahan stabilizer pada jalur stopkontak perangkat jaringan.',
                ],
                [
                    'title' => 'Gangguan Pasokan Listrik Rack Server / UPS Drop',
                    'desc' => 'UPS pada rak server utama sering berbunyi beep panjang dan switch distribusi sempat restart tiba-tiba.',
                    'loc' => 'Ruang Rack Server OPD',
                    'res' => 'Pengecekan beban daya UPS, penggantian stopkontak panel listrik, dan kalibrasi baterai cadangan.',
                    'affected_device' => 'Power Supply / PoE Injector / UPS',
                    'inspection' => 'UPS rak server terus berbunyi alarm warning dan tidak mampu membackup beban server saat listrik turun.',
                    'cause' => 'Stopkontak input UPS terbakar dan steker listrik longgar.',
                    'action' => 'Mengganti stopkontak steker panel listrik dan merapikan instalasi kabel grounding.',
                    'materials' => 'Stop Kontak / Steker Listrik (1 pcs), Isolasi Listrik / Heat Shrink (1 roll)',
                    'test_result' => 'Normal / Berfungsi Baik',
                    'test_param' => 'Voltase input 220V AC stabil, kapasitas beban UPS 42%, waktu backup estimasi 35 menit.',
                    'notes' => 'Instalasi grounding telah diperbaiki dan diuji tahanan tanah aman.',
                ],
                [
                    'title' => 'Switch PoE Overload / Port PoE Drop',
                    'desc' => 'Beberapa access point yang terhubung ke switch PoE mati bersamaan saat jam kerja sibuk.',
                    'loc' => 'Ruang Distribusi Jaringan Lantai 1',
                    'res' => 'Penataan ulang alokasi power budget PoE per port dan pembagian beban ke switch sekunder.',
                    'affected_device' => 'Power Supply / PoE Injector / UPS',
                    'inspection' => 'Total konsumsi daya PoE switch mencapai batas maksimal 120W sehingga beberapa port mati.',
                    'cause' => 'Penambahan 3 unit IP Camera baru di switch PoE yang sama melampaui kapasitas power budget.',
                    'action' => 'Memindahkan 2 unit AP ke injektor PoE terpisah untuk membagi beban daya switch.',
                    'materials' => 'PoE Injector (24V / 48V) (2 unit), Patch Cord UTP Cat6 (2 pcs)',
                    'test_result' => 'Optimal',
                    'test_param' => 'Power budget switch turun menjadi 65W (aman < 70%). Seluruh AP dan CCTV menyala stabil.',
                    'notes' => 'Beban daya switch kini termonitor dalam batas aman.',
                ],
                [
                    'title' => 'Kabel Power Steker Perangkat Jaringan Lepas',
                    'desc' => 'Router utama mati karena kabel power steker terlepas di belakang meja server.',
                    'loc' => 'Ruang Administrasi',
                    'res' => 'Pemasangan kabel ties pengunci steker power dan penataan jalur kelistrikan rak.',
                    'affected_device' => 'Power Supply / PoE Injector / UPS',
                    'inspection' => 'Router gateway mati mendadak karena steker power di belakang rak server terlepas.',
                    'cause' => 'Kabel power tidak memiliki pengunci dan tertarik saat pembersihan ruang server.',
                    'action' => 'Memasang kembali steker power dan mengikatnya dengan velcro cable ties khusus pengaman rack.',
                    'materials' => 'Kabel Ties / Velcro (1 pack)',
                    'test_result' => 'Normal',
                    'test_param' => 'Router booting normal, link internet kembali UP, latency ke gateway 1ms.',
                    'notes' => 'Seluruh kabel power di dalam rak server telah diikat rapi dengan cable management.',
                ],
            ],
            'Converter' => [
                [
                    'title' => 'Kerusakan Media Converter / SFP Transceiver',
                    'desc' => 'Lampu indikator Link/Act pada media converter mati, port LAN tidak terdeteksi oleh switch distribusi.',
                    'loc' => 'Ruang Operator Jaringan',
                    'res' => 'Penggantian 1 unit Media Converter Gigabit Single Mode dan adaptor power suplai.',
                    'affected_device' => 'Media Converter / SFP Transceiver',
                    'inspection' => 'Lampu LED Link/Act dan FX pada media converter tidak menyala meski kabel FO terpasang.',
                    'cause' => 'IC transceiver pada media converter optik rusak terbakar panas.',
                    'action' => 'Mengganti sepasang unit Media Converter Gigabit Single Mode baru dan adaptor dayanya.',
                    'materials' => 'Media Converter FO to LAN (1 unit), Patch Cord Fiber Optic (SC-SC / LC-SC) (1 pcs)',
                    'test_result' => 'Stabil / Link Up',
                    'test_param' => 'Lampu FX dan Link/Act menyala hijau pekat, link 1000 Mbps full duplex aktif.',
                    'notes' => 'Converter ditempatkan di lokasi dengan ventilasi udara baik di dalam rack.',
                ],
                [
                    'title' => 'SFP Transceiver Optic Modul Error',
                    'desc' => 'Port SFP pada switch backbone tidak mendeteksi link optik dari core switch Diskominfo.',
                    'loc' => 'Ruang Server OPD',
                    'res' => 'Penggantian modul SFP 1.25G 1310nm 20km dan link uplink kembali UP 1 Gbps.',
                    'affected_device' => 'Media Converter / SFP Transceiver',
                    'inspection' => 'Switch backbone menampilkan status port "SFP Module Not Detected".',
                    'cause' => 'Modul SFP optik 1.25G mengalami degradasi laser diode.',
                    'action' => 'Mengganti modul SFP 1.25G Single Mode 1310nm 20km baru pada port uplink switch.',
                    'materials' => 'SFP Transceiver Module (1.25G / 10G) (1 unit)',
                    'test_result' => 'Optimal',
                    'test_param' => 'Port uplink SFP terdeteksi UP 1.25 Gbps, Optical Rx Power: -16.5 dBm.',
                    'notes' => 'Link optik switch kembali terhubung langsung ke Core Switch Kominfo.',
                ],
                [
                    'title' => 'Lampu Indikator FX / Link Media Converter Mati',
                    'desc' => 'Koneksi optik masuk tapi converter tidak mengubah sinyal ke ethernet LAN.',
                    'loc' => 'Ruang Pelayanan Kasir',
                    'res' => 'Cleaning konektor optik SC converter dan penggantian jumper kabel LAN Cat6.',
                    'affected_device' => 'Media Converter / SFP Transceiver',
                    'inspection' => 'Sinyal optik masuk tapi converter tidak melakukan konversi data ke interface LAN RJ-45.',
                    'cause' => 'Konektor patchcord FO kotor dan debu menutupi sensor optik converter.',
                    'action' => 'Membersihkan ferrule dan port optik dengan optic cleaner swab dan mengganti kabel patchcord LAN.',
                    'materials' => 'Patch Cord UTP Cat6 (1 pcs)',
                    'test_result' => 'Normal / Berfungsi Baik',
                    'test_param' => 'Lampu FX menyala normal, pengujian ping ke server lokal lancar tanpa drop.',
                    'notes' => 'Disarankan penutup port debu selalu dipasang jika port tidak digunakan.',
                ],
                [
                    'title' => 'Adaptor Media Converter Rusak / Drop Tegangan',
                    'desc' => 'Media converter sering restart berulang kali menyebabkan koneksi intranet terputus-putus.',
                    'loc' => 'Gedung Sayap Timur',
                    'res' => 'Penggantian unit adaptor switching 5V 2A pada media converter.',
                    'affected_device' => 'Media Converter / SFP Transceiver',
                    'inspection' => 'Converter restart terus-menerus setiap 30 detik (indikator berkedip tidak stabil).',
                    'cause' => 'Adaptor daya 5V 2A mengalami penurunan tegangan menjadi 3.2V saat diberi beban.',
                    'action' => 'Mengganti unit adaptor power supply 5V 2A switching baru.',
                    'materials' => 'Power Supply / Adaptor (12V / 24V) (1 unit)',
                    'test_result' => 'Normal',
                    'test_param' => 'Tegangan input 5.05V DC stabil, converter beroperasi normal tanpa reboot.',
                    'notes' => 'Adaptor lama telah dibuang sesuai prosedur penanganan limbah elektronik.',
                ],
            ],
            'Layanan/jaringan' => [
                [
                    'title' => 'Masalah IP Conflict & DHCP Gateway Unreachable',
                    'desc' => 'Beberapa PC menampilkan notifikasi IP address conflict dan tidak bisa mencetak ke printer jaringan.',
                    'loc' => 'Ruang Pelayanan Masyarakat & Kasir',
                    'res' => 'Pengecekan static IP yang bentrok pada salah satu printer, konfigurasi ulang DHCP scope dan static binding.',
                    'affected_device' => 'Router / Gateway',
                    'inspection' => 'Terdapat dua perangkat menggunakan IP address yang sama (192.168.10.15) di segmen jaringan LAN.',
                    'cause' => 'Staf menghubungkan printer baru dengan settingan IP manual yang menabrak pool DHCP.',
                    'action' => 'Melakukan rekonfigurasi DHCP static reservation di router untuk printer dan release IP pada PC staf.',
                    'materials' => null,
                    'test_result' => 'Normal / Berfungsi Baik',
                    'test_param' => 'Ping ke IP printer dan gateway 192.168.10.1 RTT < 1ms, tidak ada bentrok IP.',
                    'notes' => 'IP address printer telah didaftarkan ke DHCP reservation router agar tidak bentrok.',
                ],
                [
                    'title' => 'Wi-Fi Terhubung tetapi Tidak Ada Akses Internet',
                    'desc' => 'Perangkat smartphone dan laptop staf dapat terhubung ke SSID kantor namun status "No Internet Access".',
                    'loc' => 'Ruang Pertemuan & Ruang Tamu',
                    'res' => 'Pembaruan lease DHCP pool pada VLAN Wi-Fi tamu dan restart service DNS caching di gateway.',
                    'affected_device' => 'Router / Gateway',
                    'inspection' => 'Perangkat staf terhubung ke Wi-Fi tetapi tidak mendapatkan alokasi gateway dan DNS.',
                    'cause' => 'Pool DHCP VLAN Wi-Fi telah penuh (exhausted) karena durasi lease time terlalu panjang.',
                    'action' => 'Memperpendek lease time DHCP menjadi 2 jam dan memperluas subnet DHCP pool /23.',
                    'materials' => null,
                    'test_result' => 'Optimal',
                    'test_param' => 'Perangkat langsung menerima IP baru dalam 2 detik, browsing internet normal 75 Mbps.',
                    'notes' => 'Konfigurasi DHCP telah di-backup ke server sentral Kominfo.',
                ],
                [
                    'title' => 'Gagal Login / Captive Portal Error',
                    'desc' => 'Halaman login web portal Wi-Fi tidak muncul saat pertama kali connect ke jaringan Wi-Fi publik OPD.',
                    'loc' => 'Lobby Pelayanan Umum',
                    'res' => 'Pembersihan cache redirect captive portal controller dan restart daemon authentication.',
                    'affected_device' => 'Router / Gateway',
                    'inspection' => 'Halaman login captive portal tidak terbuka otomatis saat staf connect ke SSID publik.',
                    'cause' => 'SSL certificate pada web server redirect captive portal kadaluarsa (expired).',
                    'action' => 'Memperbarui SSL certificate captive portal dan me-restart service hotspot daemon.',
                    'materials' => null,
                    'test_result' => 'Normal',
                    'test_param' => 'Redirect halaman login SSL sukses, otentikasi akun ASN berhasil.',
                    'notes' => 'Auto-renewal certificate telah dijadwalkan via cron job di controller.',
                ],
                [
                    'title' => 'Koneksi Intranet Lambat & Akses Aplikasi Pemkot RTO',
                    'desc' => 'Akses ke aplikasi SIMDA dan website resmi Pemkot mengalami perlambatan signifikan saat jam kerja.',
                    'loc' => 'Ruang Keuangan dan Aset',
                    'res' => 'Pengecekan routing tabel intranet dan optimalisasi bandwidth management QoS per departemen.',
                    'affected_device' => 'Router / Gateway',
                    'inspection' => 'Traffic bandwidth link uplink mencapai 100% kapasitas karena adanya download file berukuran besar.',
                    'cause' => 'Salah satu komputer melakukan download update OS tanpa limitasi bandwidth per client.',
                    'action' => 'Menerapkan queue simple QoS bandwidth limiter 10 Mbps per host pada router gateway OPD.',
                    'materials' => null,
                    'test_result' => 'Optimal',
                    'test_param' => 'Akses aplikasi SIMDA kembali responsif (< 100ms), utilisasi bandwidth turun ke 60%.',
                    'notes' => 'Kebijakan alokasi prioritas traffic telah diaktifkan di gateway.',
                ],
                [
                    'title' => 'Gagal Akses DNS Server Pemkot Palu',
                    'desc' => 'Komputer di ruang sekretariat tidak dapat me-resolve nama domain internal palukota.go.id.',
                    'loc' => 'Ruang Sekretariat Utama',
                    'res' => 'Pembaruan setting primary dan secondary DNS server pada DHCP server lokal.',
                    'affected_device' => 'Router / Gateway',
                    'inspection' => 'Komputer di ruangan tidak dapat membuka website .palukota.go.id karena DNS resolving gagal.',
                    'cause' => 'IP DNS lokal pada router OPD tidak dapat dihubungi akibat cache DNS korup.',
                    'action' => 'Flushing cache DNS router, update DNS forwarding ke IP Anycast Kominfo 10.0.0.1.',
                    'materials' => null,
                    'test_result' => 'Normal / Berfungsi Baik',
                    'test_param' => 'Resolving domain palukota.go.id memakan waktu 2ms, ping DNS loss 0%.',
                    'notes' => 'Secondary DNS diisi IP failover publik 1.1.1.1 sebagai redundansi.',
                ],
            ],
        ];

        $rejectionReasons = [
            'Laporan merupakan pengajuan perangkat pribadi dan bukan infrastruktur jaringan resmi Pemkot Palu.',
            'Deskripsi kendala tidak spesifik dan pelapor tidak dapat dihubungi untuk konfirmasi lokasi unit.',
            'Permintaan penambahan titik jaringan baru harus melalui surat permohonan dinas resmi ke Dinas Kominfo.',
            'Kendala disebabkan oleh gangguan pemadaman listrik internal gedung OPD, bukan pada jaringan.',
            'Laporan duplikat dengan tiket yang sudah dalam antrean verifikasi sebelumnya.',
        ];

        // Group departments for weighted distribution
        $vitalDepts = $departments->filter(function($d) {
            return in_array($d->code, [
                'DINKES', 'DISDIK', 'DISKOMINFO-PALU', 'SETDA-PALU', 'BPKAD-PALU', 
                'BAPPEDA-PALU', 'RSU-ANUTAPURA', 'DISHUB-PALU', 'SATPOLPP-PALU', 
                'DPMPTSP-PALU', 'BKPSDM-PALU', 'DLH-PALU', 'DISPERKIM-PALU', 
                'DAMKAR-PALU', 'DINSOS-PALU', 'INSPEKTORAT-PALU'
            ]);
        });

        $mediumDepts = $departments->filter(function($d) {
            return str_starts_with($d->code, 'PKM-') || str_starts_with($d->code, 'KEC-') || str_starts_with($d->code, 'BAG-');
        });

        $otherDepts = $departments->diff($vitalDepts)->diff($mediumDepts);

        // Build list of target tickets
        $deptAllocation = [];

        foreach ($vitalDepts as $dept) {
            $deptAllocation[$dept->id] = rand(5, 7);
        }
        foreach ($mediumDepts as $dept) {
            $deptAllocation[$dept->id] = rand(2, 4);
        }
        foreach ($otherDepts as $dept) {
            $deptAllocation[$dept->id] = rand(1, 2);
        }

        $allTicketPlan = [];
        foreach ($deptAllocation as $deptId => $count) {
            for ($i = 0; $i < $count; $i++) {
                $allTicketPlan[] = $deptId;
            }
        }

        if (Ticket::count() > 0) {
            $this->command->info("Data tiket sudah ada (" . Ticket::count() . " tiket). Memperbarui rincian berita acara resolusi untuk tiket pending_approval & closed...");

            $flatTemplates = [];
            foreach ($issueTemplates as $netType => $tmpls) {
                foreach ($tmpls as $t) {
                    $flatTemplates[$t['title']] = $t;
                }
            }

            $updatedCount = 0;
            $ticketsToUpdate = Ticket::whereIn('status', ['pending_approval', 'closed'])->get();
            foreach ($ticketsToUpdate as $t) {
                $tmpl = $flatTemplates[$t->title] ?? null;
                if (!$tmpl) {
                    $netType = $t->infrastructure_type ?? 'Fiber optic';
                    $tmpls = $issueTemplates[$netType] ?? $issueTemplates['Fiber optic'];
                    $tmpl = $tmpls[array_rand($tmpls)];
                }

                $t->update([
                    'affected_device' => $t->affected_device ?: $tmpl['affected_device'],
                    'inspection_result' => $t->inspection_result ?: $tmpl['inspection'],
                    'root_cause' => $t->root_cause ?: $tmpl['cause'],
                    'action_taken' => $t->action_taken ?: $tmpl['action'],
                    'materials_used' => $t->materials_used ?: $tmpl['materials'],
                    'test_result' => $t->test_result ?: $tmpl['test_result'],
                    'test_parameters' => $t->test_parameters ?: $tmpl['test_param'],
                    'resolution_note' => $t->resolution_note ?: ($tmpl['notes'] ?? $tmpl['res'] ?? $tmpl['action']),
                ]);
                $updatedCount++;
            }

            $this->command->info("Berhasil memperbarui {$updatedCount} tiket dengan data resolusi dan berita acara!");
            return;
        }

        // Shuffle to distribute naturally across 2025
        shuffle($allTicketPlan);
        $totalTickets = count($allTicketPlan); // Expected ~260 - 280

        $this->command->info("Memulai seeding {$totalTickets} tiket gangguan untuk tahun 2025...");

        $ticketSequencePerDay = [];

        DB::beginTransaction();
        try {
            foreach ($allTicketPlan as $index => $deptId) {
                $department = $departments->firstWhere('id', $deptId);
                $reporter = $opdUsers->get($deptId);

                if (!$reporter) {
                    continue;
                }

                // Determine month and day in 2025
                $progressRatio = $index / (float) $totalTickets;
                if ($progressRatio < 0.70) {
                    // Jan - Oct 2025
                    $month = (int) floor($progressRatio / 0.70 * 10) + 1; // 1 to 10
                    $day = rand(1, 28);
                    $hour = rand(8, 16);
                    $minute = rand(0, 59);
                    $createdAt = Carbon::create(2025, $month, $day, $hour, $minute, 0);

                    // 90% closed, 10% cancelled
                    $status = (rand(1, 10) <= 9) ? 'closed' : 'cancelled';
                } elseif ($progressRatio < 0.82) {
                    // Nov 2025
                    $month = 11;
                    $day = rand(1, 30);
                    $hour = rand(8, 16);
                    $minute = rand(0, 59);
                    $createdAt = Carbon::create(2025, $month, $day, $hour, $minute, 0);

                    // 80% closed, 10% cancelled, 10% pending_approval
                    $r = rand(1, 10);
                    if ($r <= 8) {
                        $status = 'closed';
                    } elseif ($r === 9) {
                        $status = 'cancelled';
                    } else {
                        $status = 'pending_approval';
                    }
                } else {
                    // Dec 2025
                    $month = 12;
                    $day = rand(1, 31);
                    $hour = rand(8, 16);
                    $minute = rand(0, 59);
                    $createdAt = Carbon::create(2025, $month, $day, $hour, $minute, 0);

                    // Dec status distribution:
                    // 35% in_progress, 30% pending_admin, 15% pending_approval, 12% closed, 8% cancelled
                    $r = rand(1, 100);
                    if ($r <= 35) {
                        $status = 'in_progress';
                    } elseif ($r <= 65) {
                        $status = 'pending_admin';
                    } elseif ($r <= 80) {
                        $status = 'pending_approval';
                    } elseif ($r <= 92) {
                        $status = 'closed';
                    } else {
                        $status = 'cancelled';
                    }
                }

                // Choose network type & issue template
                $netTypes = ['Fiber optic', 'Perangkat/Akses', 'Power/poe', 'Converter', 'Layanan/jaringan'];
                $networkType = $netTypes[array_rand($netTypes)];
                $templates = $issueTemplates[$networkType];
                $tmpl = $templates[array_rand($templates)];

                // Choose category for network type
                $cats = $categoriesByNet->get($networkType) ?? $categoriesByNet->first();
                $category = $cats->random();

                // Format Ticket Number
                $dayKey = $createdAt->format('Ymd');
                $ticketSequencePerDay[$dayKey] = ($ticketSequencePerDay[$dayKey] ?? 0) + 1;
                $sequenceNumber = str_pad((string) $ticketSequencePerDay[$dayKey], 4, '0', STR_PAD_LEFT);
                $ticketNumber = "TKT-{$dayKey}-{$sequenceNumber}";

                // Priority
                $priorities = ['low', 'medium', 'medium', 'high', 'emergency'];
                $priority = $priorities[array_rand($priorities)];

                // Assignee selection (Team of 2, 3, or 4 technicians from 10 available)
                $leadTech = $technicians->random();
                $teamSize = rand(2, 4); // Tim multi-teknisi (> 2 teknisi)
                $otherTechs = $technicians->where('id', '!=', $leadTech->id)->random(min($teamSize - 1, $technicians->count() - 1));
                $assignedTechnicians = array_values(array_unique(array_merge([$leadTech->id], $otherTechs->pluck('id')->toArray())));

                // Timestamps and status specifics
                $assignedAt = null;
                $dueAt = null;
                $resolvedAt = null;
                $closedAt = null;
                $cancelledAt = null;
                $resolutionNote = null;
                $rating = null;
                $feedbackComment = null;
                $ratedAt = null;

                if ($status === 'pending_admin') {
                    $assignedTo = null;
                } elseif ($status === 'cancelled') {
                    $assignedTo = null;
                    $cancelledAt = (clone $createdAt)->addHours(rand(1, 8));
                } else {
                    // in_progress, pending_approval, closed
                    $assignedTo = $leadTech->id;
                    $assignedAt = (clone $createdAt)->addMinutes(rand(15, 120));
                    $slaHours = match ($priority) {
                        'emergency' => 4,
                        'high' => 8,
                        'medium' => 24,
                        'low' => 48,
                        default => 24,
                    };
                    $dueAt = (clone $assignedAt)->addHours($slaHours);

                    if ($status === 'in_progress') {
                        $rSla = rand(1, 4);
                        if ($rSla === 1) {
                            $dueAt = (clone $assignedAt)->addHours(2);
                        }
                    } elseif ($status === 'pending_approval') {
                        $resolvedAt = (clone $assignedAt)->addMinutes(rand(30, $slaHours * 45));
                        $resolutionNote = $tmpl['res'];
                    } elseif ($status === 'closed') {
                        $resolvedAt = (clone $assignedAt)->addMinutes(rand(30, $slaHours * 45));
                        $closedAt = (clone $resolvedAt)->addMinutes(rand(10, 180));
                        $resolutionNote = $tmpl['res'];
                        
                        // 85% of closed tickets get rating
                        if (rand(1, 10) <= 9) {
                            $rating = rand(4, 5);
                            $feedbacks = [
                                'Penanganan cepat dan teknisi sangat ramah. Terima kasih!',
                                'Koneksi kembali lancar untuk pelayanan masyarakat.',
                                'Respon cepat dan perbaikan tuntas.',
                                'Sangat membantu kelancaran kerja dinas kami.',
                                'Pelayanan memuaskan.',
                            ];
                            $feedbackComment = $feedbacks[array_rand($feedbacks)];
                            $ratedAt = (clone $closedAt)->addMinutes(rand(5, 60));
                        }
                    }
                }

                $isResolvedOrClosed = in_array($status, ['pending_approval', 'closed']);
                $affectedDevice = $isResolvedOrClosed ? $tmpl['affected_device'] : null;
                $actualRepairLocation = $isResolvedOrClosed ? ($tmpl['actual_loc'] ?? $tmpl['loc']) : null;
                $inspectionResult = $isResolvedOrClosed ? $tmpl['inspection'] : null;
                $rootCause = $isResolvedOrClosed ? $tmpl['cause'] : null;
                $actionTaken = $isResolvedOrClosed ? $tmpl['action'] : null;
                $materialsUsed = $isResolvedOrClosed ? $tmpl['materials'] : null;
                $testResult = $isResolvedOrClosed ? $tmpl['test_result'] : null;
                $testParameters = $isResolvedOrClosed ? $tmpl['test_param'] : null;
                $finalResNote = $isResolvedOrClosed ? ($tmpl['notes'] ?? $tmpl['res'] ?? $actionTaken) : $resolutionNote;

                // Create Ticket
                $ticket = Ticket::create([
                    'ticket_number' => $ticketNumber,
                    'department_id' => $department->id,
                    'reporter_id' => $reporter->id,
                    'assigned_to' => $assignedTo,
                    'category_id' => $category->id,
                    'infrastructure_type' => $networkType,
                    'title' => $tmpl['title'],
                    'location_details' => $tmpl['loc'],
                    'description' => $tmpl['desc'],
                    'priority' => $priority,
                    'status' => $status,
                    'resolution_note' => $finalResNote,
                    'affected_device' => $affectedDevice,
                    'actual_repair_location' => $actualRepairLocation,
                    'inspection_result' => $inspectionResult,
                    'root_cause' => $rootCause,
                    'action_taken' => $actionTaken,
                    'materials_used' => $materialsUsed,
                    'test_result' => $testResult,
                    'test_parameters' => $testParameters,
                    'assigned_at' => $assignedAt,
                    'cancelled_at' => $cancelledAt,
                    'due_at' => $dueAt,
                    'resolved_at' => $resolvedAt,
                    'closed_at' => $closedAt,
                    'rating' => $rating,
                    'feedback_comment' => $feedbackComment,
                    'rated_at' => $ratedAt,
                    'created_at' => $createdAt,
                    'updated_at' => $closedAt ?? $resolvedAt ?? $cancelledAt ?? $assignedAt ?? $createdAt,
                ]);

                // Sync technicians for assigned tickets (Multi-Technicians: 2-4 members)
                if (in_array($status, ['in_progress', 'pending_approval', 'closed'])) {
                    $ticket->technicians()->sync($assignedTechnicians);
                }

                // Create Status Histories
                // 1. Initial creation
                TicketStatusHistory::create([
                    'ticket_id' => $ticket->id,
                    'changed_by' => $reporter->id,
                    'previous_status' => null,
                    'new_status' => 'pending_admin',
                    'comment' => "Laporan gangguan dibuat oleh {$reporter->name}.",
                    'created_at' => $createdAt,
                ]);

                $reason = $rejectionReasons[array_rand($rejectionReasons)];
                if ($status === 'cancelled') {
                    TicketStatusHistory::create([
                        'ticket_id' => $ticket->id,
                        'changed_by' => $admin->id,
                        'previous_status' => 'pending_admin',
                        'new_status' => 'cancelled',
                        'comment' => "Laporan ditolak oleh Admin. Alasan: {$reason}",
                        'created_at' => $cancelledAt,
                    ]);
                }

                if (in_array($status, ['in_progress', 'pending_approval', 'closed'])) {
                    $techTeamNames = $technicians->whereIn('id', $assignedTechnicians)->pluck('name')->join(', ');
                    TicketStatusHistory::create([
                        'ticket_id' => $ticket->id,
                        'changed_by' => $admin->id,
                        'previous_status' => 'pending_admin',
                        'new_status' => 'in_progress',
                        'comment' => "Laporan diverifikasi oleh Admin dan ditugaskan ke Tim Teknisi ({$techTeamNames}).",
                        'created_at' => $assignedAt,
                    ]);
                }

                if (in_array($status, ['pending_approval', 'closed'])) {
                    TicketStatusHistory::create([
                        'ticket_id' => $ticket->id,
                        'changed_by' => $leadTech->id,
                        'previous_status' => 'in_progress',
                        'new_status' => 'pending_approval',
                        'comment' => 'Teknisi menyelesaikan perbaikan di lokasi dan mengajukan review hasil kerja.',
                        'created_at' => $resolvedAt,
                    ]);
                }

                if ($status === 'closed') {
                    TicketStatusHistory::create([
                        'ticket_id' => $ticket->id,
                        'changed_by' => $admin->id,
                        'previous_status' => 'pending_approval',
                        'new_status' => 'closed',
                        'comment' => 'Admin memverifikasi mutu hasil perbaikan dan menutup tiket secara resmi.',
                        'created_at' => $closedAt,
                    ]);
                }

                // 100% Tickets get rich text-only chat replies
                if ($status === 'pending_admin') {
                    TicketReply::create([
                        'ticket_id' => $ticket->id,
                        'user_id' => $reporter->id,
                        'message' => 'Laporan kendala jaringan telah kami kirimkan. Mohon bantuannya segera ya tim Kominfo karena pelayanan masyarakat sedang berlangsung.',
                        'is_internal' => false,
                        'created_at' => (clone $createdAt)->addMinutes(5),
                        'updated_at' => (clone $createdAt)->addMinutes(5),
                    ]);
                } elseif ($status === 'in_progress') {
                    TicketReply::create([
                        'ticket_id' => $ticket->id,
                        'user_id' => $reporter->id,
                        'message' => 'Mohon bantuan segera ya tim, sedang ada rekap data pelaporan yang harus dikirim hari ini.',
                        'is_internal' => false,
                        'created_at' => (clone $createdAt)->addMinutes(5),
                        'updated_at' => (clone $createdAt)->addMinutes(5),
                    ]);

                    $techTeamNames = $technicians->whereIn('id', $assignedTechnicians)->pluck('name')->join(', ');
                    TicketReply::create([
                        'ticket_id' => $ticket->id,
                        'user_id' => $leadTech->id,
                        'message' => "Baik bapak/ibu, tim teknisi ({$techTeamNames}) sudah menerima laporan dan segera meluncur ke lokasi membawa perlengkapan.",
                        'is_internal' => false,
                        'created_at' => (clone $assignedAt)->addMinutes(10),
                        'updated_at' => (clone $assignedAt)->addMinutes(10),
                    ]);

                    if (count($assignedTechnicians) > 1) {
                        $helperTechId = $assignedTechnicians[1];
                        TicketReply::create([
                            'ticket_id' => $ticket->id,
                            'user_id' => $helperTechId,
                            'message' => 'Catatan internal tim: Toolset tester, patchcord cadangan, dan crimping tools sudah dipersiapkan di mobil dinas.',
                            'is_internal' => true,
                            'created_at' => (clone $assignedAt)->addMinutes(15),
                            'updated_at' => (clone $assignedAt)->addMinutes(15),
                        ]);
                    }
                } elseif ($status === 'pending_approval') {
                    TicketReply::create([
                        'ticket_id' => $ticket->id,
                        'user_id' => $reporter->id,
                        'message' => 'Koneksi terputus di ruangan kami, mohon bantuan perbaikan tim teknisi.',
                        'is_internal' => false,
                        'created_at' => (clone $createdAt)->addMinutes(5),
                        'updated_at' => (clone $createdAt)->addMinutes(5),
                    ]);

                    TicketReply::create([
                        'ticket_id' => $ticket->id,
                        'user_id' => $leadTech->id,
                        'message' => 'Tim teknisi sedang berada di lokasi melakukan pengecekan fisik perangkat dan jalur kabel.',
                        'is_internal' => false,
                        'created_at' => (clone $assignedAt)->addMinutes(10),
                        'updated_at' => (clone $assignedAt)->addMinutes(10),
                    ]);

                    TicketReply::create([
                        'ticket_id' => $ticket->id,
                        'user_id' => $leadTech->id,
                        'message' => 'Pekerjaan perbaikan telah selesai dilakukan di lokasi dan koneksi kembali stabil. Laporan hasil kerja diajukan ke Admin untuk QC.',
                        'is_internal' => false,
                        'created_at' => (clone $resolvedAt)->addMinutes(5),
                        'updated_at' => (clone $resolvedAt)->addMinutes(5),
                    ]);
                } elseif ($status === 'closed') {
                    TicketReply::create([
                        'ticket_id' => $ticket->id,
                        'user_id' => $reporter->id,
                        'message' => 'Selamat pagi tim, mohon bantuan ada kendala jaringan di unit kami.',
                        'is_internal' => false,
                        'created_at' => (clone $createdAt)->addMinutes(5),
                        'updated_at' => (clone $createdAt)->addMinutes(5),
                    ]);

                    TicketReply::create([
                        'ticket_id' => $ticket->id,
                        'user_id' => $leadTech->id,
                        'message' => 'Laporan diterima. Tim teknisi segera meluncur ke lokasi untuk penanganan kendala.',
                        'is_internal' => false,
                        'created_at' => (clone $assignedAt)->addMinutes(10),
                        'updated_at' => (clone $assignedAt)->addMinutes(10),
                    ]);

                    TicketReply::create([
                        'ticket_id' => $ticket->id,
                        'user_id' => $leadTech->id,
                        'message' => 'Penanganan di lokasi telah tuntas. Jaringan sudah diuji kembali bersama staf OPD dan berjalan normal.',
                        'is_internal' => false,
                        'created_at' => (clone $resolvedAt)->addMinutes(5),
                        'updated_at' => (clone $resolvedAt)->addMinutes(5),
                    ]);

                    TicketReply::create([
                        'ticket_id' => $ticket->id,
                        'user_id' => $admin->id,
                        'message' => 'Hasil perbaikan telah diverifikasi oleh Admin. Tiket resmi dinyatakan selesai dan ditutup.',
                        'is_internal' => false,
                        'created_at' => (clone $closedAt)->addMinutes(2),
                        'updated_at' => (clone $closedAt)->addMinutes(2),
                    ]);

                    TicketReply::create([
                        'ticket_id' => $ticket->id,
                        'user_id' => $reporter->id,
                        'message' => 'Terima kasih banyak atas respon cepat dan perbaikan dari seluruh tim teknisi Kominfo!',
                        'is_internal' => false,
                        'created_at' => (clone $closedAt)->addMinutes(10),
                        'updated_at' => (clone $closedAt)->addMinutes(10),
                    ]);
                } elseif ($status === 'cancelled') {
                    TicketReply::create([
                        'ticket_id' => $ticket->id,
                        'user_id' => $reporter->id,
                        'message' => 'Mohon bantuannya untuk perbaikan jaringan di ruangan kami.',
                        'is_internal' => false,
                        'created_at' => (clone $createdAt)->addMinutes(5),
                        'updated_at' => (clone $createdAt)->addMinutes(5),
                    ]);

                    TicketReply::create([
                        'ticket_id' => $ticket->id,
                        'user_id' => $admin->id,
                        'message' => "Laporan ditolak oleh Admin. Alasan: {$reason}. Silakan perbaiki rincian laporan jika ingin mengajukan kembali.",
                        'is_internal' => false,
                        'created_at' => (clone $cancelledAt)->addMinutes(2),
                        'updated_at' => (clone $cancelledAt)->addMinutes(2),
                    ]);
                }
            }

            DB::commit();
            $this->command->info("Berhasil melakukan seeding {$totalTickets} tiket gangguan untuk tahun 2025!");
        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error("Gagal melakukan seeding tiket: " . $e->getMessage());
            throw $e;
        }
    }
}
