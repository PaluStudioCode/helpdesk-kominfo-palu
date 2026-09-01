<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SimpleExcelExport
{
    /**
     * Generate a CSV / Excel compatible streamed response
     */
    public static function download(Collection $tickets, string $fileName): StreamedResponse
    {
        $headers = [
            'Content-Type' => 'application/vnd.ms-excel; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return response()->stream(function () use ($tickets) {
            $handle = fopen('php://output', 'w');
            
            // UTF-8 BOM for proper Excel Indonesian character display
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            // Header row
            fputcsv($handle, [
                'No',
                'Nomor Tiket',
                'Instansi / OPD',
                'Jenis Jaringan',
                'Kategori Kendala',
                'Judul Gangguan',
                'Lokasi / Ruangan',
                'Prioritas',
                'Status',
                'Tim Teknisi Penanggung Jawab',
                'Waktu Pelaporan',
                'Waktu Disposisi (SLA Aktif)',
                'Target SLA',
                'Waktu Selesai',
                'Durasi Penanganan',
                'Kepatuhan SLA',
                'Skor CSAT (1-5)',
                'Ulasan Kepuasan OPD',
                'Catatan Solusi Perbaikan'
            ], ';');

            // Data rows
            foreach ($tickets as $index => $ticket) {
                $duration = '-';
                $slaStatus = '-';
                $endTime = $ticket->resolved_at ?? $ticket->closed_at;

                $startPoint = $ticket->assigned_at ?? $ticket->created_at;

                if ($startPoint && $ticket->status !== 'cancelled') {
                    $start = \Carbon\Carbon::parse($startPoint);
                    $end = in_array($ticket->status, ['resolved', 'closed']) && $endTime
                        ? \Carbon\Carbon::parse($endTime)
                        : now();

                    $diffMinutes = max(0, $start->diffInMinutes($end));
                    $days = floor($diffMinutes / (60 * 24));
                    $hours = floor(($diffMinutes % (60 * 24)) / 60);
                    $minutes = $diffMinutes % 60;

                    $durParts = [];
                    if ($days > 0) $durParts[] = "{$days} hari";
                    if ($hours > 0) $durParts[] = "{$hours} jam";
                    if ($minutes > 0 || empty($durParts)) $durParts[] = "{$minutes} menit";

                    $duration = implode(' ', $durParts);
                    if (!in_array($ticket->status, ['resolved', 'closed'])) {
                        $duration .= ' (berjalan)';
                    }
                }

                if ($ticket->status === 'cancelled') {
                    $slaStatus = 'Ditolak / Dibatalkan';
                } elseif ($ticket->status === 'pending_admin') {
                    $slaStatus = 'Menunggu Verifikasi (SLA Belum Aktif)';
                } elseif ($ticket->due_at) {
                    $due = \Carbon\Carbon::parse($ticket->due_at);
                    if (in_array($ticket->status, ['resolved', 'closed']) && $endTime) {
                        $end = \Carbon\Carbon::parse($endTime);
                        if ($end->lte($due)) {
                            $slaStatus = 'Tepat Waktu (Sesuai SLA)';
                        } else {
                            $slaStatus = 'Terlambat (Overdue SLA)';
                        }
                    } else {
                        if (now()->gt($due)) {
                            $slaStatus = 'Overdue SLA';
                        } elseif (now()->diffInHours($due, false) <= 2) {
                            $slaStatus = 'Mendekati Batas SLA';
                        } else {
                            $slaStatus = 'Dalam Target SLA';
                        }
                    }
                }

                $techNames = $ticket->technicians && $ticket->technicians->count() > 0
                    ? $ticket->technicians->pluck('name')->implode(', ')
                    : ($ticket->assignee?->name ?? '-');

                fputcsv($handle, [
                    $index + 1,
                    $ticket->ticket_number,
                    $ticket->department?->name ?? '-',
                    $ticket->network_type ? strtoupper($ticket->network_type) : '-',
                    $ticket->category?->name ?? '-',
                    $ticket->title,
                    $ticket->location_details,
                    $ticket->priority ? ucfirst($ticket->priority) : '-',
                    strtoupper(str_replace('_', ' ', $ticket->status)),
                    $techNames,
                    $ticket->created_at ? $ticket->created_at->format('d/m/Y H:i') : '-',
                    $ticket->assigned_at ? $ticket->assigned_at->format('d/m/Y H:i') : '-',
                    $ticket->due_at ? $ticket->due_at->format('d/m/Y H:i') : '-',
                    $endTime ? \Carbon\Carbon::parse($endTime)->format('d/m/Y H:i') : '-',
                    $duration,
                    $slaStatus,
                    $ticket->rating ? "{$ticket->rating} Bintang" : '-',
                    $ticket->feedback_comment ?? '-',
                    $ticket->resolution_note ?? '-'
                ], ';');
            }

            fclose($handle);
        }, 200, $headers);
    }
}
