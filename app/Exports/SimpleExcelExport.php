<?php

namespace App\Exports;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SimpleExcelExport
{
    /**
     * Generate a compact, formal .xlsx Excel spreadsheet response
     */
    public static function download(Collection $tickets, string $fileName, ?string $startDate = null, ?string $endDate = null): StreamedResponse
    {
        $headers = [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return response()->stream(function () use ($tickets, $startDate, $endDate) {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Rekapitulasi Tiket');

            // 1. Metadata Kop Laporan (Row 1 - 4)
            $sheet->setCellValue('A1', 'PEMERINTAH KOTA PALU');
            $sheet->setCellValue('A2', 'DINAS KOMUNIKASI DAN INFORMATIKA - HELPDESK & LAYANAN JARINGAN');
            $sheet->setCellValue('A3', 'Laporan Rekapitulasi Penanganan Gangguan & Tiket Masuk');

            $periodText = 'Semua Periode';
            if ($startDate && $endDate) {
                $periodText = Carbon::parse($startDate)->format('d/m/Y') . ' s.d ' . Carbon::parse($endDate)->format('d/m/Y');
            } elseif ($startDate) {
                $periodText = 'Mulai ' . Carbon::parse($startDate)->format('d/m/Y');
            } elseif ($endDate) {
                $periodText = 'Sampai ' . Carbon::parse($endDate)->format('d/m/Y');
            }

            $printedAt = Carbon::now('Asia/Makassar')->format('d/m/Y H:i') . ' WITA';
            $sheet->setCellValue('A4', "Periode: {$periodText} | Dicetak pada: {$printedAt}");

            // Styling Kop Laporan
            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13)->getColor()->setRGB('1E3A8A');
            $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(10.5)->getColor()->setRGB('334155');
            $sheet->getStyle('A3')->getFont()->setItalic(true)->setSize(9.5)->getColor()->setRGB('64748B');
            $sheet->getStyle('A4')->getFont()->setSize(9)->getColor()->setRGB('64748B');

            // 2. Header Tabel Data Kompak (Row 6 - 12 Kolom Terstruktur)
            $headerRow = 6;
            $columns = [
                'A' => ['title' => 'No', 'width' => 6, 'align' => Alignment::HORIZONTAL_CENTER, 'wrap' => false],
                'B' => ['title' => 'No. Tiket', 'width' => 18, 'align' => Alignment::HORIZONTAL_CENTER, 'wrap' => false],
                'C' => ['title' => 'Instansi / OPD', 'width' => 26, 'align' => Alignment::HORIZONTAL_LEFT, 'wrap' => true],
                'D' => ['title' => 'Jaringan & Kategori', 'width' => 24, 'align' => Alignment::HORIZONTAL_LEFT, 'wrap' => true],
                'E' => ['title' => 'Judul Masalah', 'width' => 32, 'align' => Alignment::HORIZONTAL_LEFT, 'wrap' => true],
                'F' => ['title' => 'Prioritas', 'width' => 13, 'align' => Alignment::HORIZONTAL_CENTER, 'wrap' => false],
                'G' => ['title' => 'Status', 'width' => 18, 'align' => Alignment::HORIZONTAL_CENTER, 'wrap' => false],
                'H' => ['title' => 'Teknisi', 'width' => 20, 'align' => Alignment::HORIZONTAL_LEFT, 'wrap' => true],
                'I' => ['title' => 'Waktu Lapor', 'width' => 17, 'align' => Alignment::HORIZONTAL_CENTER, 'wrap' => false],
                'J' => ['title' => 'Waktu Selesai', 'width' => 17, 'align' => Alignment::HORIZONTAL_CENTER, 'wrap' => false],
                'K' => ['title' => 'Kinerja SLA & Durasi', 'width' => 22, 'align' => Alignment::HORIZONTAL_CENTER, 'wrap' => true],
                'L' => ['title' => 'Tindakan / Solusi', 'width' => 36, 'align' => Alignment::HORIZONTAL_LEFT, 'wrap' => true],
            ];

            foreach ($columns as $col => $config) {
                $sheet->setCellValue("{$col}{$headerRow}", $config['title']);
                $sheet->getColumnDimension($col)->setWidth($config['width']);
            }

            // Style Header Row
            $sheet->getRowDimension($headerRow)->setRowHeight(26);
            $headerRange = "A{$headerRow}:L{$headerRow}";
            $sheet->getStyle($headerRange)->applyFromArray([
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'FFFFFF'],
                    'size' => 10,
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '1E40AF'], // Kominfo Royal Blue
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                    'wrapText' => true,
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => '1E3A8A'],
                    ],
                ],
            ]);

            $networkMap = [
                'fiber_optic' => 'Fiber Optic',
                'lan' => 'LAN',
                'wifi' => 'WiFi',
            ];

            $priorityMap = [
                'emergency' => 'Darurat',
                'high' => 'Tinggi',
                'medium' => 'Sedang',
                'low' => 'Rendah',
            ];

            $statusMap = [
                'pending_admin' => 'Menunggu Verifikasi',
                'in_progress' => 'Sedang Dikerjakan',
                'pending_approval' => 'Menunggu Review',
                'closed' => 'Selesai',
                'cancelled' => 'Ditolak',
            ];

            // 3. Populate Data Rows (Starting Row 7)
            $currentRow = 7;
            foreach ($tickets as $index => $ticket) {
                $durationText = '-';
                $slaStatus = '-';
                $endTime = $ticket->resolved_at ?? $ticket->closed_at;
                $startPoint = $ticket->assigned_at ?? $ticket->created_at;

                if ($startPoint && $ticket->status !== 'cancelled') {
                    $start = Carbon::parse($startPoint);
                    $end = in_array($ticket->status, ['resolved', 'closed']) && $endTime
                        ? Carbon::parse($endTime)
                        : now();

                    $diffMinutes = max(0, $start->diffInMinutes($end));
                    $days = floor($diffMinutes / (60 * 24));
                    $hours = floor(($diffMinutes % (60 * 24)) / 60);
                    $minutes = $diffMinutes % 60;

                    $durParts = [];
                    if ($days > 0) $durParts[] = "{$days}h";
                    if ($hours > 0) $durParts[] = "{$hours}j";
                    if ($minutes > 0 || empty($durParts)) $durParts[] = "{$minutes}m";

                    $durationText = implode(' ', $durParts);
                    if (!in_array($ticket->status, ['resolved', 'closed'])) {
                        $durationText .= ' (berjalan)';
                    }
                }

                if ($ticket->status === 'cancelled') {
                    $slaStatus = 'Ditolak';
                } elseif ($ticket->status === 'pending_admin') {
                    $slaStatus = 'Menunggu Verifikasi';
                } elseif ($ticket->due_at) {
                    $due = Carbon::parse($ticket->due_at);
                    if (in_array($ticket->status, ['resolved', 'closed']) && $endTime) {
                        $end = Carbon::parse($endTime);
                        if ($end->lte($due)) {
                            $slaStatus = 'Tepat Waktu';
                        } else {
                            $slaStatus = 'Terlambat';
                        }
                    } else {
                        if (now()->gt($due)) {
                            $slaStatus = 'Overdue SLA';
                        } elseif (now()->diffInHours($due, false) <= 2) {
                            $slaStatus = 'Mendekati Batas';
                        } else {
                            $slaStatus = 'Dalam Target';
                        }
                    }
                }

                $techNames = $ticket->technicians && $ticket->technicians->count() > 0
                    ? $ticket->technicians->pluck('name')->implode(', ')
                    : ($ticket->assignee?->name ?? '-');

                // Gabungan Jaringan & Kategori
                $netName = $networkMap[$ticket->network_type] ?? ($ticket->network_type ? ucfirst($ticket->network_type) : '-');
                $catName = $ticket->category?->name ?? '-';
                $netCategoryCombined = ($netName !== '-' && $catName !== '-') 
                    ? "{$netName} - {$catName}" 
                    : ($netName !== '-' ? $netName : $catName);

                // Gabungan Kinerja SLA & Durasi
                $performanceCombined = "{$durationText}\n({$slaStatus})";

                $sheet->setCellValue("A{$currentRow}", $index + 1);
                $sheet->setCellValueExplicit("B{$currentRow}", $ticket->ticket_number, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValue("C{$currentRow}", $ticket->department?->name ?? '-');
                $sheet->setCellValue("D{$currentRow}", $netCategoryCombined);
                $sheet->setCellValue("E{$currentRow}", $ticket->title ?? '-');
                $sheet->setCellValue("F{$currentRow}", $priorityMap[$ticket->priority] ?? ($ticket->priority ? ucfirst($ticket->priority) : '-'));
                $sheet->setCellValue("G{$currentRow}", $statusMap[$ticket->status] ?? $ticket->status);
                $sheet->setCellValue("H{$currentRow}", $techNames);
                $sheet->setCellValue("I{$currentRow}", $ticket->created_at ? Carbon::parse($ticket->created_at)->timezone('Asia/Makassar')->format('d/m/Y H:i') : '-');
                $sheet->setCellValue("J{$currentRow}", $endTime ? Carbon::parse($endTime)->timezone('Asia/Makassar')->format('d/m/Y H:i') : '-');
                $sheet->setCellValue("K{$currentRow}", $performanceCombined);
                $sheet->setCellValue("L{$currentRow}", $ticket->resolution_note ?? '-');

                // Row borders & Zebra Striping
                $rowRange = "A{$currentRow}:L{$currentRow}";
                $sheet->getStyle($rowRange)->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => 'E2E8F0'],
                        ],
                    ],
                    'alignment' => [
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);

                if ($currentRow % 2 === 1) {
                    $sheet->getStyle($rowRange)->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setRGB('F8FAFC');
                }

                // Apply per-column alignments & text-wrapping
                foreach ($columns as $col => $config) {
                    $sheet->getStyle("{$col}{$currentRow}")->getAlignment()
                        ->setHorizontal($config['align'])
                        ->setWrapText($config['wrap']);
                }

                $currentRow++;
            }

            // Write output to php://output
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, 200, $headers);
    }
}
