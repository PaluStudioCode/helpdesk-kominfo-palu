<?php

namespace App\Services;

use App\Jobs\SendTicketNotificationJob;
use App\Models\Ticket;
use App\Models\User;

class NotificationDispatcher
{
    /**
     * Dispatch notification for ticket_created event.
     * Recipients:
     * 1. Reporter / PIC OPD
     * 2. Broadcast to all active Admins & Technicians
     */
    public static function ticketCreated(Ticket $ticket): void
    {
        $ticket->loadMissing(['department', 'reporter', 'category']);

        $reporter = $ticket->reporter;
        $department = $ticket->department;

        // 1. Notify OPD Reporter / PIC
        $opdPhone = !empty($reporter->phone_number) ? $reporter->phone_number : ($department?->pic_phone ?? '');
        $waReporterMessage = "*[Helpdesk Kominfo Palu]*\n\n"
            . "Tiket laporan gangguan Anda telah berhasil didaftarkan ke sistem.\n\n"
            . "📌 *Nomor Tiket:* {$ticket->ticket_number}\n"
            . "🏛 *Instansi:* " . ($department?->name ?? '-') . "\n"
            . "🌐 *Jenis Jaringan:* " . strtoupper($ticket->network_type) . "\n"
            . "📝 *Judul:* {$ticket->title}\n"
            . "⚡ *Prioritas:* " . ucfirst($ticket->priority) . "\n\n"
            . "Tim teknisi Kominfo akan segera memeriksa dan menindaklanjuti laporan Anda.\n"
            . "Pantau status: " . url('/tickets/' . $ticket->id);

        if ($reporter) {
            SendTicketNotificationJob::dispatch(
                ticket: $ticket,
                recipient: $reporter,
                eventType: 'ticket_created',
                targetPhone: $opdPhone,
                waMessage: $waReporterMessage,
                emailSubject: "Tiket Baru Terdaftar ({$ticket->ticket_number})",
                emailHeadline: "Tiket laporan gangguan jaringan Anda telah kami terima.",
                emailCustomMessage: "Laporan akan segera diverifikasi dan ditugaskan kepada tim teknisi."
            );
        }

        // 2. Broadcast to all active Admins & Technicians
        $staffUsers = User::whereIn('role', ['admin', 'technician'])
            ->where('status', 'active')
            ->get();

        $waStaffMessage = "*[Helpdesk Kominfo Palu - Tiket Baru]*\n\n"
            . "Laporan gangguan jaringan baru memerlukan tindak lanjut:\n\n"
            . "📌 *Nomor Tiket:* {$ticket->ticket_number}\n"
            . "🏛 *Instansi:* " . ($department?->name ?? '-') . "\n"
            . "🌐 *Jenis Jaringan:* " . strtoupper($ticket->network_type) . "\n"
            . "📝 *Judul:* {$ticket->title}\n"
            . "📍 *Lokasi:* {$ticket->location_details}\n"
            . "⚡ *Prioritas:* " . ucfirst($ticket->priority) . "\n\n"
            . "Buka tiket: " . url('/tickets/' . $ticket->id);

        foreach ($staffUsers as $staff) {
            // Avoid duplicate sending if reporter is also an admin (e.g. on-behalf creation)
            if ($reporter && $staff->id === $reporter->id) {
                continue;
            }

            SendTicketNotificationJob::dispatch(
                ticket: $ticket,
                recipient: $staff,
                eventType: 'ticket_created',
                targetPhone: $staff->phone_number ?? '',
                waMessage: $waStaffMessage,
                emailSubject: "Tiket Baru Memerlukan Penanganan ({$ticket->ticket_number})",
                emailHeadline: "Laporan baru dari " . ($department?->name ?? 'OPD') . " telah masuk ke sistem antrean.",
                emailCustomMessage: "Harap segera periksa antrean tiket untuk melakukan pendelegasian atau klaim penanganan."
            );
        }
    }

    /**
     * Dispatch notification for ticket_assigned / in_progress event.
     */
    public static function ticketAssigned(Ticket $ticket, ?User $assignee = null): void
    {
        $ticket->loadMissing(['department', 'reporter', 'assignee']);
        $assigneeUser = $assignee ?? $ticket->assignee;
        $reporter = $ticket->reporter;

        // 1. Notify Assignee Technician
        if ($assigneeUser) {
            $waTechMessage = "*[Helpdesk Kominfo Palu - Penugasan Tiket]*\n\n"
                . "Anda telah ditugaskan untuk menangani tiket berikut:\n\n"
                . "📌 *Nomor Tiket:* {$ticket->ticket_number}\n"
                . "🏛 *Instansi:* " . ($ticket->department?->name ?? '-') . "\n"
                . "📝 *Judul:* {$ticket->title}\n"
                . "📍 *Lokasi:* {$ticket->location_details}\n"
                . "⚡ *Target SLA:* " . ($ticket->due_at ? $ticket->due_at->translatedFormat('d M Y H:i') : '-') . "\n\n"
                . "Detail tiket: " . url('/tickets/' . $ticket->id);

            SendTicketNotificationJob::dispatch(
                ticket: $ticket,
                recipient: $assigneeUser,
                eventType: 'ticket_assigned',
                targetPhone: $assigneeUser->phone_number ?? '',
                waMessage: $waTechMessage,
                emailSubject: "Penugasan Tiket Baru ({$ticket->ticket_number})",
                emailHeadline: "Anda telah ditugaskan untuk menangani laporan gangguan jaringan.",
                emailCustomMessage: "Segera lakukan penanganan sesuai target SLA yang telah ditentukan."
            );
        }

        // 2. Notify Reporter OPD that ticket is in progress
        if ($reporter) {
            $techName = $assigneeUser?->name ?? 'Teknisi Jaringan';
            $waOpdMessage = "*[Helpdesk Kominfo Palu - Progres Penanganan]*\n\n"
                . "Tiket Anda saat ini sedang ditangani oleh teknisi kami:\n\n"
                . "📌 *Nomor Tiket:* {$ticket->ticket_number}\n"
                . "👨‍🔧 *Teknisi Bertugas:* {$techName}\n"
                . "📝 *Judul:* {$ticket->title}\n"
                . "🔄 *Status:* In Progress (Sedang Dikerjakan)\n\n"
                . "Pantau status: " . url('/tickets/' . $ticket->id);

            SendTicketNotificationJob::dispatch(
                ticket: $ticket,
                recipient: $reporter,
                eventType: 'status_in_progress',
                targetPhone: $reporter->phone_number ?? ($ticket->department?->pic_phone ?? ''),
                waMessage: $waOpdMessage,
                emailSubject: "Pembaruan Status Tiket ({$ticket->ticket_number})",
                emailHeadline: "Tiket Anda sedang ditangani oleh teknisi ({$techName}).",
                emailCustomMessage: "Teknisi kami sedang melakukan pengecekan dan perbaikan kendala jaringan Anda."
            );
        }
    }

    /**
     * Dispatch notification for status_resolved event.
     */
    public static function ticketResolved(Ticket $ticket): void
    {
        $ticket->loadMissing(['department', 'reporter', 'assignee']);
        $reporter = $ticket->reporter;

        if ($reporter) {
            $waMessage = "*[Helpdesk Kominfo Palu - Tiket Selesai Dikerjakan]*\n\n"
                . "Laporan gangguan Anda telah dinyatakan selesai oleh tim teknisi:\n\n"
                . "📌 *Nomor Tiket:* {$ticket->ticket_number}\n"
                . "📝 *Solusi Perbaikan:* {$ticket->resolution_note}\n\n"
                . "Mohon untuk memverifikasi jaringan Anda dan melakukan konfirmasi selesai di portal:\n"
                . url('/tickets/' . $ticket->id) . "\n\n"
                . "_Catatan: Tiket akan ditutup otomatis oleh sistem dalam 3x24 jam jika tidak ada sanggahan._";

            SendTicketNotificationJob::dispatch(
                ticket: $ticket,
                recipient: $reporter,
                eventType: 'status_resolved',
                targetPhone: $reporter->phone_number ?? ($ticket->department?->pic_phone ?? ''),
                waMessage: $waMessage,
                emailSubject: "Perbaikan Tiket Telah Selesai ({$ticket->ticket_number})",
                emailHeadline: "Teknisi telah menyelesaikan perbaikan pada laporan Anda.",
                emailCustomMessage: "Silakan periksa koneksi Anda dan konfirmasi penyelesaian tiket di portal helpdesk."
            );
        }
    }

    /**
     * Dispatch notification for status_closed event.
     */
    public static function ticketClosed(Ticket $ticket): void
    {
        $ticket->loadMissing(['department', 'reporter', 'assignee']);
        $reporter = $ticket->reporter;

        if ($reporter) {
            $waMessage = "*[Helpdesk Kominfo Palu - Tiket Ditutup Resmi]*\n\n"
                . "Tiket laporan gangguan jaringan berikut telah resmi ditutup:\n\n"
                . "📌 *Nomor Tiket:* {$ticket->ticket_number}\n"
                . "📝 *Judul:* {$ticket->title}\n\n"
                . "Terima kasih atas kerja sama Anda.\n"
                . url('/tickets/' . $ticket->id);

            SendTicketNotificationJob::dispatch(
                ticket: $ticket,
                recipient: $reporter,
                eventType: 'status_closed',
                targetPhone: $reporter->phone_number ?? ($ticket->department?->pic_phone ?? ''),
                waMessage: $waMessage,
                emailSubject: "Tiket Resmi Ditutup ({$ticket->ticket_number})",
                emailHeadline: "Tiket telah resmi ditutup.",
                emailCustomMessage: "Terima kasih telah menggunakan layanan Helpdesk Jaringan Diskominfo Palu."
            );
        }
    }

    /**
     * Dispatch notification for ticket_reopened event.
     */
    public static function ticketReopened(Ticket $ticket): void
    {
        $ticket->loadMissing(['department', 'reporter', 'assignee']);
        $assignee = $ticket->assignee;

        if ($assignee) {
            $waMessage = "*[Helpdesk Kominfo Palu - Tiket Dibuka Kembali]*\n\n"
                . "Tiket berikut dibuka kembali oleh OPD karena gangguan belum tuntas:\n\n"
                . "📌 *Nomor Tiket:* {$ticket->ticket_number}\n"
                . "🏛 *Instansi:* " . ($ticket->department?->name ?? '-') . "\n"
                . "📝 *Judul:* {$ticket->title}\n\n"
                . "Harap segera tindak lanjuti kembali di portal:\n"
                . url('/tickets/' . $ticket->id);

            SendTicketNotificationJob::dispatch(
                ticket: $ticket,
                recipient: $assignee,
                eventType: 'ticket_reopened',
                targetPhone: $assignee->phone_number ?? '',
                waMessage: $waMessage,
                emailSubject: "Tiket Dibuka Kembali ({$ticket->ticket_number})",
                emailHeadline: "Pihak OPD mengajukan perbaikan lanjutan untuk tiket ini.",
                emailCustomMessage: "Harap segera berkoordinasi dan melakukan pemeriksaan kembali pada kendala jaringan tersebut."
            );
        }
    }

    /**
     * Dispatch notification for ticket_cancelled event.
     */
    public static function ticketCancelled(Ticket $ticket, string $reason): void
    {
        $ticket->loadMissing(['department', 'reporter']);
        $reporter = $ticket->reporter;

        if ($reporter) {
            $waMessage = "*[Helpdesk Kominfo Palu - Tiket Dibatalkan]*\n\n"
                . "Laporan gangguan jaringan telah dibatalkan:\n\n"
                . "📌 *Nomor Tiket:* {$ticket->ticket_number}\n"
                . "📝 *Alasan:* {$reason}\n\n"
                . "Detail: " . url('/tickets/' . $ticket->id);

            SendTicketNotificationJob::dispatch(
                ticket: $ticket,
                recipient: $reporter,
                eventType: 'ticket_cancelled',
                targetPhone: $reporter->phone_number ?? ($ticket->department?->pic_phone ?? ''),
                waMessage: $waMessage,
                emailSubject: "Tiket Dibatalkan ({$ticket->ticket_number})",
                emailHeadline: "Tiket telah dibatalkan dengan alasan tertentu.",
                emailCustomMessage: "Alasan pembatalan: {$reason}"
            );
        }
    }
}
