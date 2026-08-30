<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SimpleExcelExport
{
    /**
     * Generate an XML-based Excel (.xls / .xlsx compatible SpreadsheetML) or CSV streamed response
     * without requiring ext-gd.
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
                'Teknisi Penanggung Jawab',
                'Waktu Pelaporan',
                'Target SLA',
                'Waktu Selesai',
                'Lama Penanganan',
                'Status SLA',
                'Catatan Solusi Perbaikan'
            ], ';');

            // Data rows
            foreach ($tickets as $index => $ticket) {
                $duration = '-';
                $slaStatus = '-';
                $endTime = $ticket->resolved_at ?? $ticket->closed_at;

                if ($ticket->created_at && $ticket->status !== 'cancelled') {
                    $start = \Carbon\Carbon::parse($ticket->created_at);
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
                    $slaStatus = 'Dibatalkan';
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

                fputcsv($handle, [
                    $index + 1,
                    $ticket->ticket_number,
                    $ticket->department?->name ?? '-',
                    strtoupper($ticket->network_type),
                    $ticket->category?->name ?? '-',
                    $ticket->title,
                    $ticket->location_details,
                    ucfirst($ticket->priority),
                    strtoupper(str_replace('_', ' ', $ticket->status)),
                    $ticket->assignee?->name ?? '-',
                    $ticket->created_at ? $ticket->created_at->format('d/m/Y H:i') : '-',
                    $ticket->due_at ? $ticket->due_at->format('d/m/Y H:i') : '-',
                    $endTime ? \Carbon\Carbon::parse($endTime)->format('d/m/Y H:i') : '-',
                    $duration,
                    $slaStatus,
                    $ticket->resolution_note ?? '-'
                ], ';');
            }

            fclose($handle);
        }, 200, $headers);
    }
}
