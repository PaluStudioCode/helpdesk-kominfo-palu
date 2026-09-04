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

        // Realistic Issue Templates
        $issueTemplates = [
            'Fiber optic' => [
                [
                    'title' => 'Koneksi FO Dropcore Utama Terputus',
                    'desc' => 'Kabel dropcore yang masuk ke rack server utama OPD terputus akibat perbaikan plafon gedung. Seluruh koneksi internet dan intranet gedung mati.',
                    'loc' => 'Ruang Server & Ruang Kepala Bagian',
                    'res' => 'Penyambungan ulang kabel dropcore menggunakan fusion splicer dan penggantian pigtail SC-APC. Redaman kembali normal di -18 dBm.',
                ],
                [
                    'title' => 'Internet Putus Total / Backbone Down',
                    'desc' => 'Layanan internet gedung kantor tidak dapat diakses sejak pagi hari. Lampu indikator LOS pada modem/converter berkedip merah.',
                    'loc' => 'Ruang Pelayanan Terpadu Lantai 1',
                    'res' => 'Pengecekan jalur distribusi kabel FO dari ODP terdekat, perbaikan konektor patchcord yang patah di OTB.',
                ],
                [
                    'title' => 'Redaman Fiber Optic Tinggi (Koneksi Lambat & ROP)',
                    'desc' => 'Kecepatan internet drop drastis dan sering request timed out (RTO). Aktivitas input data ke server Pemkot terhambat.',
                    'loc' => 'Ruang Bidang Perencanaan & Anggaran',
                    'res' => 'Pembersihan ferrule konektor optic dengan optical cleaner dan pelurusan kabel patchcord yang mengalami bending.',
                ],
                [
                    'title' => 'Core FO Joint Closure Bermasalah di Luar Gedung',
                    'desc' => 'Koneksi ke server induk Pemkot putus setelah hujan lebat dan angin kencang di sekitar tiang distribusi.',
                    'loc' => 'Tiang Distribusi Samping Kantor',
                    'res' => 'Rekonstruksi sambungan core di joint closure dan proteksi isolasi waterproof.',
                ],
                [
                    'title' => 'Kabel FO Dropcore Terjepit di Pintu Ruang Rapat',
                    'desc' => 'Kabel optik dropcore terjepit pintu hingga jaket pelindung terkoyak dan koneksi ruangan terputus.',
                    'loc' => 'Ruang Rapat VIP',
                    'res' => 'Penyambungan ulang core dan penataan jalur kabel dengan protective spiral wrapping.',
                ],
            ],
            'Perangkat/Akses' => [
                [
                    'title' => 'Kabel UTP / LAN Ruang Staf Putus',
                    'desc' => 'Kabel jaringan di bawah meja staf terjepit kursi dan terkelupas sehingga komputer tidak mendapatkan koneksi internet.',
                    'loc' => 'Ruang Bidang Keuangan & Pembukuan',
                    'res' => 'Penarikan kembali kabel UTP Cat6 baru sepanjang 15 meter dan pemasangan ducting kabel pelindung.',
                ],
                [
                    'title' => 'Krimpingan RJ45 Longgar / Port Rusak',
                    'desc' => 'Koneksi sering terputus-putus (intermittent) saat kabel jaringan tersenggol di PC kerja staf.',
                    'loc' => 'Ruang Sekretariat & Tata Usaha',
                    'res' => 'Rekrimping konektor RJ45 modular Cat6 baru dan pengetesan dengan cable tester, pinout normal 8/8.',
                ],
                [
                    'title' => 'Switch Distribusi Gedung Hang / Mati Total',
                    'desc' => 'Satu lantai kantor tidak bisa terhubung ke jaringan LAN maupun printer sharing bersama.',
                    'loc' => 'Rack Switch Lantai 2',
                    'res' => 'Restart hard-reboot switch distribusi, pergantian port uplink, dan penataan kabel patch panel.',
                ],
                [
                    'title' => 'Port Patch Panel Bermasalah',
                    'desc' => 'Wallplate jaringan nomor 04 di meja rapat tidak mengeluarkan sinyal konektivitas LAN.',
                    'loc' => 'Ruang Rapat Utama',
                    'res' => 'Punchdown ulang kabel UTP ke keystone jack wallplate dan pengecekan koneksi end-to-end.',
                ],
                [
                    'title' => 'Access Point Mati Total / Indikator Merah',
                    'desc' => 'Access point yang terpasang di lorong tengah kantor mati dan tidak memancarkan sinyal SSID.',
                    'loc' => 'Lorong Lantai 1 Dekat Ruang Rapat',
                    'res' => 'Pemeriksaan port access point, restart perangkat, dan konfigurasi ulang profil SSID.',
                ],
                [
                    'title' => 'Sinyal Wi-Fi Lemah / Blind Spot Ruangan',
                    'desc' => 'Sinyal Wi-Fi di ruangan pimpinan sangat lemah (hanya 1 bar) dan sering terputus saat zoom meeting.',
                    'loc' => 'Ruang Pimpinan & Staf Ahli',
                    'res' => 'Reposisi letak Access Point ke area tengah ruangan dan optimalisasi daya transmisi RF (Tx Power).',
                ],
            ],
            'Power/poe' => [
                [
                    'title' => 'Adaptor / PoE Injector Access Point Mati Total',
                    'desc' => 'Access point di lantai 2 tidak menyala sama sekali, adaptor PoE injector tidak mengeluarkan lampu indikator hijau.',
                    'loc' => 'Gedung B Lantai 2',
                    'res' => 'Penggantian unit adaptor PoE injector 24V 1A baru dan pengetesan voltase output normal.',
                ],
                [
                    'title' => 'Gangguan Pasokan Listrik Rack Server / UPS Drop',
                    'desc' => 'UPS pada rak server utama sering berbunyi beep panjang dan switch distribusi sempat restart tiba-tiba.',
                    'loc' => 'Ruang Rack Server OPD',
                    'res' => 'Pengecekan beban daya UPS, penggantian stopkontak panel listrik, dan kalibrasi baterai cadangan.',
                ],
                [
                    'title' => 'Switch PoE Overload / Port PoE Drop',
                    'desc' => 'Beberapa access point yang terhubung ke switch PoE mati bersamaan saat jam kerja sibuk.',
                    'loc' => 'Ruang Distribusi Jaringan Lantai 1',
                    'res' => 'Penataan ulang alokasi power budget PoE per port dan pembagian beban ke switch sekunder.',
                ],
                [
                    'title' => 'Kabel Power Steker Perangkat Jaringan Lepas',
                    'desc' => 'Router utama mati karena kabel power steker terlepas di belakang meja server.',
                    'loc' => 'Ruang Administrasi',
                    'res' => 'Pemasangan kabel ties pengunci steker power dan penataan jalur kelistrikan rak.',
                ],
            ],
            'Converter' => [
                [
                    'title' => 'Kerusakan Media Converter / SFP Transceiver',
                    'desc' => 'Lampu indikator Link/Act pada media converter mati, port LAN tidak terdeteksi oleh switch distribusi.',
                    'loc' => 'Ruang Operator Jaringan',
                    'res' => 'Penggantian 1 unit Media Converter Gigabit Single Mode dan adaptor power suplai.',
                ],
                [
                    'title' => 'SFP Transceiver Optic Modul Error',
                    'desc' => 'Port SFP pada switch backbone tidak mendeteksi link optik dari core switch Diskominfo.',
                    'loc' => 'Ruang Server OPD',
                    'res' => 'Penggantian modul SFP 1.25G 1310nm 20km dan link uplink kembali UP 1 Gbps.',
                ],
                [
                    'title' => 'Lampu Indikator FX / Link Media Converter Mati',
                    'desc' => 'Koneksi optik masuk tapi converter tidak mengubah sinyal ke ethernet LAN.',
                    'loc' => 'Ruang Pelayanan Kasir',
                    'res' => 'Cleaning konektor optik SC converter dan penggantian jumper kabel LAN Cat6.',
                ],
                [
                    'title' => 'Adaptor Media Converter Rusak / Drop Tegangan',
                    'desc' => 'Media converter sering restart berulang kali menyebabkan koneksi intranet terputus-putus.',
                    'loc' => 'Gedung Sayap Timur',
                    'res' => 'Penggantian unit adaptor switching 5V 2A pada media converter.',
                ],
            ],
            'Layanan/jaringan' => [
                [
                    'title' => 'Masalah IP Conflict & DHCP Gateway Unreachable',
                    'desc' => 'Beberapa PC menampilkan notifikasi IP address conflict dan tidak bisa mencetak ke printer jaringan.',
                    'loc' => 'Ruang Pelayanan Masyarakat & Kasir',
                    'res' => 'Pengecekan static IP yang bentrok pada salah satu printer, konfigurasi ulang DHCP scope dan static binding.',
                ],
                [
                    'title' => 'Wi-Fi Terhubung tetapi Tidak Ada Akses Internet',
                    'desc' => 'Perangkat smartphone dan laptop staf dapat terhubung ke SSID kantor namun status "No Internet Access".',
                    'loc' => 'Ruang Pertemuan & Ruang Tamu',
                    'res' => 'Pembaruan lease DHCP pool pada VLAN Wi-Fi tamu dan restart service DNS caching di gateway.',
                ],
                [
                    'title' => 'Gagal Login / Captive Portal Error',
                    'desc' => 'Halaman login web portal Wi-Fi tidak muncul saat pertama kali connect ke jaringan Wi-Fi publik OPD.',
                    'loc' => 'Lobby Pelayanan Umum',
                    'res' => 'Pembersihan cache redirect captive portal controller dan restart daemon authentication.',
                ],
                [
                    'title' => 'Koneksi Intranet Lambat & Akses Aplikasi Pemkot RTO',
                    'desc' => 'Akses ke aplikasi SIMDA dan website resmi Pemkot mengalami perlambatan signifikan saat jam kerja.',
                    'loc' => 'Ruang Keuangan dan Aset',
                    'res' => 'Pengecekan routing tabel intranet dan optimalisasi bandwidth management QoS per departemen.',
                ],
                [
                    'title' => 'Gagal Akses DNS Server Pemkot Palu',
                    'desc' => 'Komputer di ruang sekretariat tidak dapat me-resolve nama domain internal palukota.go.id.',
                    'loc' => 'Ruang Sekretariat Utama',
                    'res' => 'Pembaruan setting primary dan secondary DNS server pada DHCP server lokal.',
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

        // Shuffle to distribute naturally across 2025
        shuffle($allTicketPlan);
        $totalTickets = count($allTicketPlan); // Expected ~260 - 280

        $this->command->info("Memulai seeding {$totalTickets} tiket gangguan untuk tahun 2025 (100% tanpa gambar)...");

        // Distribute months from 1 to 12 in 2025
        // Month 1-10: 70% of tickets -> mostly closed, few cancelled
        // Month 11: 12% of tickets -> closed, pending_approval, cancelled
        // Month 12: 18% of tickets -> pending_admin, in_progress, pending_approval, closed, cancelled

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
                    $dueAt = (clone $assignedAt)->addHours($category->sla_hours);

                    if ($status === 'in_progress') {
                        $rSla = rand(1, 4);
                        if ($rSla === 1) {
                            $dueAt = (clone $assignedAt)->addHours(2);
                        }
                    } elseif ($status === 'pending_approval') {
                        $resolvedAt = (clone $assignedAt)->addMinutes(rand(30, $category->sla_hours * 50));
                        $resolutionNote = $tmpl['res'];
                    } elseif ($status === 'closed') {
                        $resolvedAt = (clone $assignedAt)->addMinutes(rand(30, $category->sla_hours * 50));
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
                    'resolution_note' => $resolutionNote,
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
