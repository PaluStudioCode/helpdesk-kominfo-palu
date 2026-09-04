<?php

namespace App\Services;

use App\Jobs\SendTicketNotificationJob;
use App\Models\Ticket;
use App\Models\User;

class NotificationDispatcher
{
    /**
     * Dispatch notification for ticket_created event (pending_admin).
     * 1. Reporter OPD (Confirmation of Registration)
     * 2. Broadcast to all active Admins (Needs verification)
     */
    public static function ticketCreated(Ticket $ticket): void
    {
        $ticket->loadMissing(['department', 'reporter']);

        $reporter = $ticket->reporter;
        $department = $ticket->department;

        // 1. Notify OPD Reporter
        $opdPhone = !empty($reporter?->phone_number) ? $reporter->phone_number : ($department?->operator?->phone_number ?? '');
        $waReporterMessage = "*[HELPDESK DISKOMINFO KOTA PALU]*\n"
            . "*Pemberitahuan Laporan Gangguan Terdaftar*\n\n"
            . "Yth. Bapak/Ibu,\n"
            . "Laporan kendala jaringan intra pemerintah Anda telah berhasil didaftarkan ke sistem.\n\n"
            . "Nomor Tiket: {$ticket->ticket_number}\n"
            . "Instansi / OPD: " . ($department?->name ?? '-') . "\n"
            . "Judul Masalah: {$ticket->title}\n"
            . "Lokasi: {$ticket->location_details}\n"
            . "Status: Menunggu Verifikasi Admin\n\n"
            . "Tim Administrator Kominfo akan segera memeriksa kelayakan laporan dan menugaskan tim teknisi ke lokasi.\n\n"
            . "Tautan Pemantauan: " . url('/tickets/' . $ticket->id) . "\n\n"
            . "Dinas Komunikasi, Informatika, Persandian dan Statistik Kota Palu";

        if ($reporter) {
            SendTicketNotificationJob::dispatch(
                ticket: $ticket,
                recipient: $reporter,
                eventType: 'ticket_created',
                targetPhone: $opdPhone,
                waMessage: $waReporterMessage,
                emailSubject: "Laporan Gangguan Terdaftar ({$ticket->ticket_number})",
                emailHeadline: "Laporan gangguan jaringan Anda telah kami terima.",
                emailCustomMessage: "Laporan Anda sedang dalam antrean verifikasi Admin Kominfo."
            );
        }

        // 2. Broadcast to all active Admins
        $admins = User::where('role', 'admin')
            ->where('status', 'active')
            ->get();

        $waAdminMessage = "*[HELPDESK DISKOMINFO KOTA PALU]*\n"
            . "*Pemberitahuan Laporan Masuk (Perlu Verifikasi)*\n\n"
            . "Yth. Administrator,\n"
            . "Laporan kendala jaringan baru memerlukan verifikasi kelayakan dan penugasan tim:\n\n"
            . "Nomor Tiket: {$ticket->ticket_number}\n"
            . "Instansi / OPD: " . ($department?->name ?? '-') . "\n"
            . "Judul Masalah: {$ticket->title}\n"
            . "Lokasi: {$ticket->location_details}\n"
            . "Status: Menunggu Verifikasi\n\n"
            . "Silakan lakukan verifikasi melalui tautan berikut:\n"
            . url('/tickets/' . $ticket->id) . "\n\n"
            . "Dinas Komunikasi, Informatika, Persandian dan Statistik Kota Palu";

        foreach ($admins as $admin) {
            if ($reporter && $admin->id === $reporter->id) {
                continue;
            }

            SendTicketNotificationJob::dispatch(
                ticket: $ticket,
                recipient: $admin,
                eventType: 'ticket_created_admin',
                targetPhone: $admin->phone_number ?? '',
                waMessage: $waAdminMessage,
                emailSubject: "Laporan Baru Memerlukan Verifikasi ({$ticket->ticket_number})",
                emailHeadline: "Laporan baru dari " . ($department?->name ?? 'OPD') . " masuk ke antrean verifikasi.",
                emailCustomMessage: "Harap segera verifikasi kelayakan laporan dan tentukan tim teknisi penanggung jawab."
            );
        }
    }

    /**
     * Dispatch notification when OPD resubmits a rejected ticket.
     */
    public static function ticketResubmitted(Ticket $ticket): void
    {
        $ticket->loadMissing(['department', 'reporter']);

        $admins = User::where('role', 'admin')
            ->where('status', 'active')
            ->get();

        $waAdminMessage = "*[HELPDESK DISKOMINFO KOTA PALU]*\n"
            . "*Pemberitahuan Pengajuan Ulang Laporan*\n\n"
            . "Yth. Administrator,\n"
            . "Laporan kendala jaringan berikut telah diperbaiki oleh pihak OPD dan diajukan kembali:\n\n"
            . "Nomor Tiket: {$ticket->ticket_number}\n"
            . "Instansi / OPD: " . ($ticket->department?->name ?? '-') . "\n"
            . "Judul Masalah: {$ticket->title}\n"
            . "Lokasi: {$ticket->location_details}\n\n"
            . "Silakan periksa kembali laporan melalui tautan berikut:\n"
            . url('/tickets/' . $ticket->id) . "\n\n"
            . "Dinas Komunikasi, Informatika, Persandian dan Statistik Kota Palu";

        foreach ($admins as $admin) {
            SendTicketNotificationJob::dispatch(
                ticket: $ticket,
                recipient: $admin,
                eventType: 'ticket_resubmitted',
                targetPhone: $admin->phone_number ?? '',
                waMessage: $waAdminMessage,
                emailSubject: "Pengajuan Ulang Laporan Tiket ({$ticket->ticket_number})",
                emailHeadline: "Pihak OPD telah memperbaiki data laporan dan mengajukan kembali.",
                emailCustomMessage: "Harap periksa kembali detail perbaikan laporan untuk penugasan tim."
            );
        }
    }

    /**
     * Dispatch notification for ticket_assigned / in_progress event.
     * 1. Broadcast to ALL assigned team technicians
     * 2. Notify OPD Reporter
     */
    public static function ticketAssigned(Ticket $ticket): void
    {
        $ticket->loadMissing(['department', 'reporter', 'technicians', 'category', 'assignee']);
        $technicians = $ticket->technicians;
        $reporter = $ticket->reporter;

        $techNames = $technicians->pluck('name')->implode(', ');
        if (empty($techNames) && $ticket->assignee) {
            $techNames = $ticket->assignee->name;
        }

        // 1. Broadcast to all assigned technicians
        $waTechMessage = "*[HELPDESK DISKOMINFO KOTA PALU]*\n"
            . "*Surat Tugas Penanganan Kendala Jaringan*\n\n"
            . "Yth. Tim Teknisi,\n"
            . "Anda telah ditugaskan dalam penanganan laporan kendala jaringan berikut:\n\n"
            . "Nomor Tiket: {$ticket->ticket_number}\n"
            . "Instansi / OPD: " . ($ticket->department?->name ?? '-') . "\n"
            . "Jenis Infrastruktur: " . strtoupper($ticket->infrastructure_type ?? $ticket->network_type ?? 'Jaringan') . "\n"
            . "Judul Masalah: {$ticket->title}\n"
            . "Lokasi: {$ticket->location_details}\n"
            . "Prioritas: " . ucfirst($ticket->priority ?? 'Medium') . "\n"
            . "Anggota Tim: " . ($techNames ?: 'Tim Teknisi') . "\n"
            . "Target Batas Waktu (SLA): " . ($ticket->due_at ? $ticket->due_at->translatedFormat('d M Y H:i') . ' WITA' : '-') . "\n\n"
            . "Buka detail tiket: " . url('/tickets/' . $ticket->id) . "\n\n"
            . "Dinas Komunikasi, Informatika, Persandian dan Statistik Kota Palu";

        foreach ($technicians as $tech) {
            SendTicketNotificationJob::dispatch(
                ticket: $ticket,
                recipient: $tech,
                eventType: 'ticket_assigned',
                targetPhone: $tech->phone_number ?? '',
                waMessage: $waTechMessage,
                emailSubject: "Penugasan Tiket Gangguan ({$ticket->ticket_number})",
                emailHeadline: "Anda telah ditugaskan untuk menangani laporan gangguan jaringan.",
                emailCustomMessage: "Segera lakukan pemeriksaan lapangan dan perbaikan sesuai target SLA."
            );
        }

        // 2. Notify Reporter OPD
        if ($reporter) {
            $waOpdMessage = "*[HELPDESK DISKOMINFO KOTA PALU]*\n"
                . "*Pemberitahuan Progres Penanganan*\n\n"
                . "Yth. Bapak/Ibu,\n"
                . "Laporan kendala jaringan Anda telah diverifikasi dan tim teknisi telah ditugaskan ke lokasi:\n\n"
                . "Nomor Tiket: {$ticket->ticket_number}\n"
                . "Tim Teknisi: " . ($techNames ?: 'Teknisi Jaringan Kominfo') . "\n"
                . "Judul Masalah: {$ticket->title}\n"
                . "Status: Sedang Dikerjakan (In Progress)\n"
                . "Estimasi Target Selesai: " . ($ticket->due_at ? $ticket->due_at->translatedFormat('d M Y H:i') . ' WITA' : '-') . "\n\n"
                . "Pantau progres penanganan melalui tautan berikut:\n"
                . url('/tickets/' . $ticket->id) . "\n\n"
                . "Dinas Komunikasi, Informatika, Persandian dan Statistik Kota Palu";

            SendTicketNotificationJob::dispatch(
                ticket: $ticket,
                recipient: $reporter,
                eventType: 'status_in_progress',
                targetPhone: $reporter->phone_number ?? ($ticket->department?->operator?->phone_number ?? ''),
                waMessage: $waOpdMessage,
                emailSubject: "Tim Teknisi Telah Ditugaskan ({$ticket->ticket_number})",
                emailHeadline: "Laporan Anda sedang ditangani oleh tim teknisi ({$techNames}).",
                emailCustomMessage: "Tim teknisi Diskominfo sedang menuju lokasi untuk melakukan pemeriksaan dan perbaikan."
            );
        }
    }

    /**
     * Dispatch notification for ticket_rejected event.
     */
    public static function ticketRejected(Ticket $ticket, string $reason): void
    {
        $ticket->loadMissing(['department', 'reporter']);
        $reporter = $ticket->reporter;

        if ($reporter) {
            $waMessage = "*[HELPDESK DISKOMINFO KOTA PALU]*\n"
                . "*Pemberitahuan Penolakan Laporan*\n\n"
                . "Yth. Bapak/Ibu,\n"
                . "Laporan kendala jaringan Anda tidak dapat diproses lebih lanjut:\n\n"
                . "Nomor Tiket: {$ticket->ticket_number}\n"
                . "Judul Masalah: {$ticket->title}\n"
                . "Alasan Penolakan: {$reason}\n\n"
                . "Catatan: Anda dapat memperbaiki data laporan atau bukti pendukung dan mengajukan kembali dalam batas waktu maksimal 3x24 jam (72 jam) melalui portal:\n"
                . url('/tickets/' . $ticket->id) . "\n\n"
                . "Dinas Komunikasi, Informatika, Persandian dan Statistik Kota Palu";

            SendTicketNotificationJob::dispatch(
                ticket: $ticket,
                recipient: $reporter,
                eventType: 'ticket_rejected',
                targetPhone: $reporter->phone_number ?? ($ticket->department?->operator?->phone_number ?? ''),
                waMessage: $waMessage,
                emailSubject: "Laporan Tiket Ditolak ({$ticket->ticket_number})",
                emailHeadline: "Laporan tiket gangguan Anda ditolak dengan alasan tertentu.",
                emailCustomMessage: "Alasan penolakan: {$reason}. Anda dapat melakukan perbaikan data dalam batas 72 jam."
            );
        }
    }

    /**
     * Dispatch notification when OPD cancels their own pending ticket.
     */
    public static function ticketCancelledByReporter(Ticket $ticket, string $reason): void
    {
        $ticket->loadMissing(['department', 'reporter']);
        $department = $ticket->department;

        $admins = User::where('role', 'admin')
            ->where('status', 'active')
            ->get();

        $waAdminMessage = "*[HELPDESK DISKOMINFO KOTA PALU]*\n"
            . "*Pemberitahuan Pembatalan Laporan oleh OPD*\n\n"
            . "Yth. Administrator,\n"
            . "Laporan kendala jaringan berikut telah dibatalkan secara mandiri oleh pelapor OPD:\n\n"
            . "Nomor Tiket: {$ticket->ticket_number}\n"
            . "Instansi / OPD: " . ($department?->name ?? '-') . "\n"
            . "Judul Masalah: {$ticket->title}\n"
            . "Alasan Pembatalan: {$reason}\n"
            . "Status: Dibatalkan (Keluar dari Antrean Verifikasi)\n\n"
            . "Tautan: " . url('/tickets/' . $ticket->id) . "\n\n"
            . "Dinas Komunikasi, Informatika, Persandian dan Statistik Kota Palu";

        foreach ($admins as $admin) {
            SendTicketNotificationJob::dispatch(
                ticket: $ticket,
                recipient: $admin,
                eventType: 'ticket_cancelled_by_reporter',
                targetPhone: $admin->phone_number ?? '',
                waMessage: $waAdminMessage,
                emailSubject: "Laporan Dibatalkan oleh OPD ({$ticket->ticket_number})",
                emailHeadline: "Pelapor dari " . ($department?->name ?? 'OPD') . " telah membatalkan laporannya.",
                emailCustomMessage: "Alasan pembatalan: {$reason}. Laporan telah dikeluarkan dari antrean verifikasi."
            );
        }
    }

    /**
     * Dispatch notification for pending_approval event (Technician completed fieldwork).
     */
    public static function pendingApproval(Ticket $ticket): void
    {
        $ticket->loadMissing(['department', 'category', 'technicians']);

        $admins = User::where('role', 'admin')
            ->where('status', 'active')
            ->get();

        $techNames = $ticket->technicians->pluck('name')->implode(', ');

        $waAdminMessage = "*[HELPDESK DISKOMINFO KOTA PALU]*\n"
            . "*Pemberitahuan Pekerjaan Selesai (Menunggu Review Mutu)*\n\n"
            . "Yth. Administrator,\n"
            . "Tim teknisi telah menyelesaikan perbaikan lapangan dan mengajukan peninjauan mutu hasil kerja:\n\n"
            . "Nomor Tiket: {$ticket->ticket_number}\n"
            . "Instansi / OPD: " . ($ticket->department?->name ?? '-') . "\n"
            . "Teknisi Penanggung Jawab: " . ($techNames ?: 'Tim Teknisi') . "\n"
            . "Kategori Riil: " . ($ticket->category?->name ?? '-') . "\n"
            . "Catatan Solusi Teknis: {$ticket->resolution_note}\n\n"
            . "Silakan tinjau bukti foto dan lakukan persetujuan melalui tautan berikut:\n"
            . url('/tickets/' . $ticket->id) . "\n\n"
            . "Dinas Komunikasi, Informatika, Persandian dan Statistik Kota Palu";

        foreach ($admins as $admin) {
            SendTicketNotificationJob::dispatch(
                ticket: $ticket,
                recipient: $admin,
                eventType: 'pending_approval',
                targetPhone: $admin->phone_number ?? '',
                waMessage: $waAdminMessage,
                emailSubject: "Pekerjaan Tiket Selesai - Menunggu Review ({$ticket->ticket_number})",
                emailHeadline: "Tim teknisi telah menyelesaikan perbaikan dan siap ditinjau mutunya.",
                emailCustomMessage: "Harap periksa catatan solusi teknis dan foto bukti hasil pekerjaan."
            );
        }
    }

    /**
     * Dispatch notification for ticket_revision event (Admin requested rework).
     */
    public static function ticketRevision(Ticket $ticket, string $instruction): void
    {
        $ticket->loadMissing(['department', 'technicians']);
        $technicians = $ticket->technicians;

        $waTechMessage = "*[HELPDESK DISKOMINFO KOTA PALU]*\n"
            . "*Instruksi Perbaikan Lanjutan / Revisi*\n\n"
            . "Yth. Tim Teknisi,\n"
            . "Administrator meminta tindak lanjut perbaikan tambahan pada tiket berikut:\n\n"
            . "Nomor Tiket: {$ticket->ticket_number}\n"
            . "Instansi / OPD: " . ($ticket->department?->name ?? '-') . "\n"
            . "Catatan Instruksi Revisi: {$instruction}\n\n"
            . "Harap segera menindaklanjuti perbaikan di lokasi:\n"
            . url('/tickets/' . $ticket->id) . "\n\n"
            . "Dinas Komunikasi, Informatika, Persandian dan Statistik Kota Palu";

        foreach ($technicians as $tech) {
            SendTicketNotificationJob::dispatch(
                ticket: $ticket,
                recipient: $tech,
                eventType: 'ticket_revision',
                targetPhone: $tech->phone_number ?? '',
                waMessage: $waTechMessage,
                emailSubject: "Permintaan Revisi Penanganan Tiket ({$ticket->ticket_number})",
                emailHeadline: "Admin meminta perbaikan lanjutan untuk tiket ini.",
                emailCustomMessage: "Catatan instruksi revisi: {$instruction}"
            );
        }
    }

    /**
     * Dispatch notification for status_closed event.
     */
    public static function ticketClosed(Ticket $ticket): void
    {
        $ticket->loadMissing(['department', 'reporter']);
        $reporter = $ticket->reporter;

        if ($reporter) {
            $waMessage = "*[HELPDESK DISKOMINFO KOTA PALU]*\n"
                . "*Pemberitahuan Tiket Selesai & Resmi Ditutup*\n\n"
                . "Yth. Bapak/Ibu,\n"
                . "Perbaikan kendala jaringan pada laporan Anda telah diverifikasi dan resmi ditutup:\n\n"
                . "Nomor Tiket: {$ticket->ticket_number}\n"
                . "Instansi / OPD: " . ($ticket->department?->name ?? '-') . "\n"
                . "Subjek Masalah: {$ticket->title}\n"
                . "Solusi Perbaikan: {$ticket->resolution_note}\n\n"
                . "Mohon kesediaan Bapak/Ibu untuk memberikan penilaian rating dan ulasan atas pelayanan teknisi kami melalui tautan berikut:\n"
                . url('/tickets/' . $ticket->id) . "\n\n"
                . "Terima kasih atas kerja sama Anda.\n\n"
                . "Dinas Komunikasi, Informatika, Persandian dan Statistik Kota Palu";

            SendTicketNotificationJob::dispatch(
                ticket: $ticket,
                recipient: $reporter,
                eventType: 'status_closed',
                targetPhone: $reporter->phone_number ?? ($ticket->department?->operator?->phone_number ?? ''),
                waMessage: $waMessage,
                emailSubject: "Tiket Resmi Ditutup - Beri Penilaian ({$ticket->ticket_number})",
                emailHeadline: "Perbaikan kendala jaringan telah selesai dan diverifikasi resmi.",
                emailCustomMessage: "Silakan buka tautan untuk melihat dokumentasi perbaikan dan memberikan ulasan kepuasan layanan."
            );
        }
    }

    /**
     * Dispatch notification for ticket_held event (on_hold).
     */
    public static function ticketHeld(Ticket $ticket, string $categoryLabel, string $reasonNote): void
    {
        $ticket->loadMissing(['department', 'reporter']);
        $reporter = $ticket->reporter;

        if ($reporter) {
            $waMessage = "*[HELPDESK DISKOMINFO KOTA PALU]*\n"
                . "*Pemberitahuan Penundaan Sementara Penanganan Gangguan*\n\n"
                . "Yth. Bapak/Ibu,\n"
                . "Penanganan kendala jaringan untuk laporan Anda sedang dijeda sementara karena adanya kendala di lapangan:\n\n"
                . "Nomor Tiket: {$ticket->ticket_number}\n"
                . "Instansi / OPD: " . ($ticket->department?->name ?? '-') . "\n"
                . "Kategori Kendala: {$categoryLabel}\n"
                . "Catatan Penjelasan: {$reasonNote}\n\n"
                . "Tim teknisi akan segera melanjutkan pekerjaan setelah kendala eksternal/logistik teratasi. Anda dapat memantau status terkini di:\n"
                . url('/tickets/' . $ticket->id) . "\n\n"
                . "Dinas Komunikasi, Informatika, Persandian dan Statistik Kota Palu";

            SendTicketNotificationJob::dispatch(
                ticket: $ticket,
                recipient: $reporter,
                eventType: 'ticket_held',
                targetPhone: $reporter->phone_number ?? ($ticket->department?->operator?->phone_number ?? ''),
                waMessage: $waMessage,
                emailSubject: "Pemberitahuan Penundaan Sementara Tiket ({$ticket->ticket_number})",
                emailHeadline: "Penanganan gangguan sedang dijeda sementara karena kendala lapangan.",
                emailCustomMessage: "Kategori kendala: {$categoryLabel}. Catatan: {$reasonNote}"
            );
        }
    }

    /**
     * Dispatch notification for ticket_resumed event (in_progress).
     */
    public static function ticketResumed(Ticket $ticket): void
    {
        $ticket->loadMissing(['department', 'reporter']);
        $reporter = $ticket->reporter;

        if ($reporter) {
            $waMessage = "*[HELPDESK DISKOMINFO KOTA PALU]*\n"
                . "*Pemberitahuan Kelanjutan Penanganan Gangguan*\n\n"
                . "Yth. Bapak/Ibu,\n"
                . "Tim teknisi Diskominfo telah melanjutkan kembali perbaikan jaringan untuk laporan Anda:\n\n"
                . "Nomor Tiket: {$ticket->ticket_number}\n"
                . "Instansi / OPD: " . ($ticket->department?->name ?? '-') . "\n"
                . "Status: Sedang Dikerjakan (In Progress)\n\n"
                . "Pantau progres di: " . url('/tickets/' . $ticket->id) . "\n\n"
                . "Dinas Komunikasi, Informatika, Persandian dan Statistik Kota Palu";

            SendTicketNotificationJob::dispatch(
                ticket: $ticket,
                recipient: $reporter,
                eventType: 'ticket_resumed',
                targetPhone: $reporter->phone_number ?? ($ticket->department?->operator?->phone_number ?? ''),
                waMessage: $waMessage,
                emailSubject: "Pekerjaan Penanganan Tiket Dilanjutkan ({$ticket->ticket_number})",
                emailHeadline: "Tim teknisi telah melanjutkan kembali proses perbaikan.",
                emailCustomMessage: "Tim teknisi sedang berada di lokasi untuk menyelesaikan kendala jaringan Anda."
            );
        }
    }
}
