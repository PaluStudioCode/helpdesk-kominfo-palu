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
        $waReporterMessage = "*[Helpdesk Kominfo Palu - Laporan Terdaftar]*\n\n"
            . "Laporan gangguan jaringan Anda telah berhasil didaftarkan ke sistem.\n\n"
            . "📌 *Nomor Tiket:* {$ticket->ticket_number}\n"
            . "🏛 *Instansi:* " . ($department?->name ?? '-') . "\n"
            . "📝 *Subjek:* {$ticket->title}\n"
            . "📍 *Lokasi:* {$ticket->location_details}\n"
            . "🔄 *Status:* Menunggu Verifikasi Admin\n\n"
            . "Admin Kominfo akan segera memeriksa kelayakan laporan dan menugaskan tim teknisi ke lokasi.\n"
            . "Pantau status: " . url('/tickets/' . $ticket->id);

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

        $waAdminMessage = "*[Helpdesk Kominfo Palu - Laporan Baru Masuk]*\n\n"
            . "Laporan gangguan jaringan baru memerlukan verifikasi kelayakan & penugasan tim:\n\n"
            . "📌 *Nomor Tiket:* {$ticket->ticket_number}\n"
            . "🏛 *Instansi:* " . ($department?->name ?? '-') . "\n"
            . "📝 *Judul:* {$ticket->title}\n"
            . "📍 *Lokasi:* {$ticket->location_details}\n"
            . "🔄 *Status:* Menunggu Verifikasi\n\n"
            . "Buka tiket untuk verifikasi: " . url('/tickets/' . $ticket->id);

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

        $waAdminMessage = "*[Helpdesk Kominfo Palu - Pengajuan Ulang Laporan]*\n\n"
            . "Laporan gangguan berikut telah diperbaiki oleh pihak OPD dan diajukan kembali:\n\n"
            . "📌 *Nomor Tiket:* {$ticket->ticket_number}\n"
            . "🏛 *Instansi:* " . ($ticket->department?->name ?? '-') . "\n"
            . "📝 *Judul:* {$ticket->title}\n"
            . "📍 *Lokasi:* {$ticket->location_details}\n\n"
            . "Harap periksa kembali laporan: " . url('/tickets/' . $ticket->id);

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
        $ticket->loadMissing(['department', 'reporter', 'technicians', 'category']);
        $technicians = $ticket->technicians;
        $reporter = $ticket->reporter;

        $techNames = $technicians->pluck('name')->implode(', ');
        if (empty($techNames) && $ticket->assignee) {
            $techNames = $ticket->assignee->name;
        }

        // 1. Broadcast to all assigned technicians
        $waTechMessage = "*[Helpdesk Kominfo Palu - Penugasan Tim Teknisi]*\n\n"
            . "Anda telah ditugaskan dalam tim penanganan tiket gangguan berikut:\n\n"
            . "📌 *Nomor Tiket:* {$ticket->ticket_number}\n"
            . "🏛 *Instansi:* " . ($ticket->department?->name ?? '-') . "\n"
            . "🌐 *Jenis Jaringan:* " . strtoupper($ticket->network_type ?? 'Jaringan') . "\n"
            . "📝 *Judul:* {$ticket->title}\n"
            . "📍 *Lokasi:* {$ticket->location_details}\n"
            . "⚡ *Prioritas:* " . ucfirst($ticket->priority ?? 'Medium') . "\n"
            . "👥 *Anggota Tim:* " . ($techNames ?: 'Tim Teknisi') . "\n"
            . "⏱ *Target SLA:* " . ($ticket->due_at ? $ticket->due_at->translatedFormat('d M Y H:i') . ' WITA' : '-') . "\n\n"
            . "Buka detail tiket: " . url('/tickets/' . $ticket->id);

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
            $waOpdMessage = "*[Helpdesk Kominfo Palu - Progres Penanganan]*\n\n"
                . "Laporan gangguan Anda telah diverifikasi dan tim teknisi telah ditugaskan:\n\n"
                . "📌 *Nomor Tiket:* {$ticket->ticket_number}\n"
                . "👨‍🔧 *Tim Teknisi:* " . ($techNames ?: 'Teknisi Jaringan Kominfo') . "\n"
                . "📝 *Judul:* {$ticket->title}\n"
                . "🔄 *Status:* Sedang Dikerjakan (In Progress)\n"
                . "⏱ *Estimasi Selesai:* " . ($ticket->due_at ? $ticket->due_at->translatedFormat('d M Y H:i') . ' WITA' : '-') . "\n\n"
                . "Pantau penanganan: " . url('/tickets/' . $ticket->id);

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
            $waMessage = "*[Helpdesk Kominfo Palu - Laporan Ditolak]*\n\n"
                . "Laporan gangguan jaringan Anda tidak dapat diproses lebih lanjut:\n\n"
                . "📌 *Nomor Tiket:* {$ticket->ticket_number}\n"
                . "📝 *Judul:* {$ticket->title}\n"
                . "❌ *Alasan Penolakan:* {$reason}\n\n"
                . "ℹ️ *Masa Perbaikan:* Anda dapat memperbaiki deskripsi/foto bukti dan mengajukan kembali dalam waktu *3x24 jam (72 jam)* melalui portal:\n"
                . url('/tickets/' . $ticket->id);

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
     * Dispatch notification for pending_approval event (Technician completed fieldwork).
     */
    public static function pendingApproval(Ticket $ticket): void
    {
        $ticket->loadMissing(['department', 'category', 'technicians']);

        $admins = User::where('role', 'admin')
            ->where('status', 'active')
            ->get();

        $techNames = $ticket->technicians->pluck('name')->implode(', ');

        $waAdminMessage = "*[Helpdesk Kominfo Palu - Menunggu Review Mutu]*\n\n"
            . "Tim teknisi telah menyelesaikan perbaikan dan mengajukan review hasil kerja:\n\n"
            . "📌 *Nomor Tiket:* {$ticket->ticket_number}\n"
            . "🏛 *Instansi:* " . ($ticket->department?->name ?? '-') . "\n"
            . "👨‍🔧 *Teknisi:* " . ($techNames ?: 'Tim Teknisi') . "\n"
            . "🌐 *Kategori Riil:* " . ($ticket->category?->name ?? '-') . "\n"
            . "📝 *Solusi Perbaikan:* {$ticket->resolution_note}\n\n"
            . "Tinjau bukti dan lakukan verifikasi mutu: " . url('/tickets/' . $ticket->id);

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

        $waTechMessage = "*[Helpdesk Kominfo Palu - Permintaan Perbaikan Ulang]*\n\n"
            . "Admin meminta tindak lanjut perbaikan tambahan pada tiket berikut:\n\n"
            . "📌 *Nomor Tiket:* {$ticket->ticket_number}\n"
            . "🏛 *Instansi:* " . ($ticket->department?->name ?? '-') . "\n"
            . "⚠️ *Catatan Instruksi Revisi:* {$instruction}\n\n"
            . "Harap segera tindak lanjuti di lokasi: " . url('/tickets/' . $ticket->id);

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
            $waMessage = "*[Helpdesk Kominfo Palu - Tiket Resmi Ditutup]*\n\n"
                . "Perbaikan kendala jaringan pada laporan Anda telah diverifikasi dan resmi ditutup:\n\n"
                . "📌 *Nomor Tiket:* {$ticket->ticket_number}\n"
                . "🏛 *Instansi:* " . ($ticket->department?->name ?? '-') . "\n"
                . "📝 *Subjek:* {$ticket->title}\n"
                . "🛠 *Solusi Perbaikan:* {$ticket->resolution_note}\n\n"
                . "⭐ *Evaluasi Kepuasan:* Mohon luangkan waktu sejenak untuk memberikan penilaian rating & ulasan atas pelayanan teknisi kami di tautan berikut:\n"
                . url('/tickets/' . $ticket->id) . "\n\n"
                . "Terima kasih atas kerja sama Anda.";

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
}
