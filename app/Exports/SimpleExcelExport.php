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
                'Waktu Selesai (Resolved)',
                'Catatan Solusi Perbaikan'
            ], ';');

            // Data rows
            foreach ($tickets as $index => $ticket) {
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
                    $ticket->resolved_at ? $ticket->resolved_at->format('d/m/Y H:i') : '-',
                    $ticket->resolution_note ?? '-'
                ], ';');
            }

            fclose($handle);
        }, 200, $headers);
    }
}
