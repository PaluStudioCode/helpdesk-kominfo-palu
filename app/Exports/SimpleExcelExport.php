<?php

namespace App\Exports;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SimpleExcelExport
{
    /**
     * Generate a compact, formal .xlsx Excel spreadsheet response
     * Optimized for office reporting (10 essential columns, print-friendly, auto-filter, freeze-panes).
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

            // Set Page Setup: Landscape A4 & Fit all columns to 1 page width
            $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
            $sheet->getPageSetup()->setPaperSize(PageSetup::PAPERSIZE_A4);
            $sheet->getPageSetup()->setFitToPage(true);
            $sheet->getPageSetup()->setFitToWidth(1);
            $sheet->getPageSetup()->setFitToHeight(0);

            // 1. Metadata Kop Laporan (Row 1 - 4)
            $sheet->setCellValue('A1', 'PEMERINTAH KOTA PALU');
            $sheet->setCellValue('A2', 'DINAS KOMUNIKASI DAN INFORMATIKA - BIDANG PENGELOLAAN OP & JARINGAN');
            $sheet->setCellValue('A3', 'Laporan Rekapitulasi Penanganan Gangguan & Layanan Helpdesk TIK');

            $periodText = 'Semua Periode';
            if ($startDate && $endDate) {
                $periodText = Carbon::parse($startDate)->format('d/m/Y') . ' s.d ' . Carbon::parse($endDate)->format('d/m/Y');
            } elseif ($startDate) {
                $periodText = 'Mulai ' . Carbon::parse($startDate)->format('d/m/Y');
            } elseif ($endDate) {
                $periodText = 'Sampai ' . Carbon::parse($endDate)->format('d/m/Y');
            }

            $printedAt = Carbon::now('Asia/Makassar')->format('d/m/Y H:i') . ' WITA';
            $sheet->setCellValue('A4', "Periode: {$periodText}   |   Dicetak pada: {$printedAt}");

            // 2. Ringkasan Singkat Kantor 1 Baris (Row 5)
            $totalTickets = $tickets->count();
            $resolvedTickets = $tickets->whereIn('status', ['resolved', 'closed'])->count();
            $inProgressTickets = $tickets->whereIn('status', ['in_progress', 'pending_approval'])->count();
            $pendingAdminTickets = $tickets->where('status', 'pending_admin')->count();
            
            $completedWithSla = 0;
            $slaCompliantCount = 0;
            foreach ($tickets as $t) {
                $endTime = $t->resolved_at ?? $t->closed_at;
                if (in_array($t->status, ['resolved', 'closed']) && $endTime && $t->due_at) {
                    $completedWithSla++;
                    if (Carbon::parse($endTime)->lte(Carbon::parse($t->due_at))) {
                        $slaCompliantCount++;
                    }
                }
            }
            $slaRate = $completedWithSla > 0 ? round(($slaCompliantCount / $completedWithSla) * 100, 1) : 100;

            $summaryText = "Ringkasan Laporan:  Total Tiket: {$totalTickets}   |   Selesai: {$resolvedTickets}   |   Dalam Proses: {$inProgressTickets}   |   Kepatuhan SLA: {$slaRate}%";
            $sheet->setCellValue('A5', $summaryText);
            $sheet->mergeCells('A5:J5');

            // Styling Kop Laporan & Ringkasan Bar
            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(12)->getColor()->setRGB('1E3A8A');
            $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(10)->getColor()->setRGB('334155');
            $sheet->getStyle('A3')->getFont()->setItalic(true)->setSize(9.5)->getColor()->setRGB('475569');
            $sheet->getStyle('A4')->getFont()->setSize(9)->getColor()->setRGB('64748B');

            $sheet->getRowDimension(5)->setRowHeight(20);
            $sheet->getStyle('A5:J5')->applyFromArray([
                'font' => [
                    'bold' => true,
                    'size' => 9,
                    'color' => ['rgb' => '1E293B'],
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'F1F5F9'],
                ],
                'alignment' => [
                    'vertical' => Alignment::VERTICAL_CENTER,
                    'horizontal' => Alignment::HORIZONTAL_LEFT,
                    'indent' => 1,
                ],
                'borders' => [
                    'top' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CBD5E1']],
                    'bottom' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CBD5E1']],
                ],
            ]);

            // Row 6: Spacer
            $sheet->getRowDimension(6)->setRowHeight(8);

            // 3. Header Tabel (Row 7 - 10 Kolom Proporsional Laporan Kantor)
            $headerRow = 7;
            $columns = [
                'A' => ['title' => 'No', 'width' => 5, 'align' => Alignment::HORIZONTAL_CENTER, 'wrap' => false],
                'B' => ['title' => 'No. Tiket', 'width' => 17, 'align' => Alignment::HORIZONTAL_CENTER, 'wrap' => false],
                'C' => ['title' => 'Tanggal Lapor', 'width' => 16, 'align' => Alignment::HORIZONTAL_CENTER, 'wrap' => false],
                'D' => ['title' => 'Instansi (OPD)', 'width' => 26, 'align' => Alignment::HORIZONTAL_LEFT, 'wrap' => true],
                'E' => ['title' => 'Infrastruktur', 'width' => 18, 'align' => Alignment::HORIZONTAL_LEFT, 'wrap' => false],
                'F' => ['title' => 'Kendala / Masalah', 'width' => 28, 'align' => Alignment::HORIZONTAL_LEFT, 'wrap' => true],
                'G' => ['title' => 'Petugas Teknisi', 'width' => 22, 'align' => Alignment::HORIZONTAL_LEFT, 'wrap' => true],
                'H' => ['title' => 'Status', 'width' => 16, 'align' => Alignment::HORIZONTAL_CENTER, 'wrap' => false],
                'I' => ['title' => 'Tgl Selesai', 'width' => 16, 'align' => Alignment::HORIZONTAL_CENTER, 'wrap' => false],
                'J' => ['title' => 'Tindakan / Solusi', 'width' => 36, 'align' => Alignment::HORIZONTAL_LEFT, 'wrap' => true],
            ];

            foreach ($columns as $col => $config) {
                $sheet->setCellValue("{$col}{$headerRow}", $config['title']);
                $sheet->getColumnDimension($col)->setWidth($config['width']);
            }

            // Style Header Row
            $sheet->getRowDimension($headerRow)->setRowHeight(26);
            $headerRange = "A{$headerRow}:J{$headerRow}";
            $sheet->getStyle($headerRange)->applyFromArray([
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'FFFFFF'],
                    'size' => 9.5,
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '1E40AF'], // Royal Navy Blue Kominfo
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

            // Aktifkan Fitur Native Excel: AutoFilter & Freeze Panes
            $sheet->setAutoFilter("A{$headerRow}:J{$headerRow}");
            $sheet->freezePane('A8');

            $networkMap = [
                'Fiber optic' => 'Fiber optic',
                'Perangkat/Akses' => 'Perangkat/Akses',
                'Power/poe' => 'Power/poe',
                'Converter' => 'Converter',
                'Layanan/jaringan' => 'Layanan/jaringan',
                'fiber_optic' => 'Fiber optic',
                'lan' => 'Perangkat/Akses',
                'wifi' => 'Layanan/jaringan',
            ];

            $statusMap = [
                'pending_admin' => 'Menunggu Verifikasi',
                'in_progress' => 'Sedang Dikerjakan',
                'on_hold' => 'Tertunda (On-Hold)',
                'pending_approval' => 'Menunggu Review',
                'closed' => 'Selesai',
                'cancelled' => 'Ditolak',
            ];

            // 4. Populate Data Rows (Mulai Row 8)
            $currentRow = 8;
            foreach ($tickets as $index => $ticket) {
                $endTime = $ticket->resolved_at ?? $ticket->resolution?->created_at ?? $ticket->closed_at;

                // Nama Petugas / Tim Teknisi
                $techNames = $ticket->technicians && $ticket->technicians->count() > 0
                    ? $ticket->technicians->pluck('name')->implode(', ')
                    : ($ticket->assignee?->name ?? '-');

                // Jenis Infrastruktur
                $infraType = $ticket->resolution?->category?->infrastructure_type 
                    ?? $ticket->infrastructure_type 
                    ?? $ticket->network_type;
                $netName = $networkMap[$infraType] ?? ($infraType ? ucfirst($infraType) : '-');

                // Tindakan Penanganan Riil
                $actionTaken = $ticket->resolution?->action_taken 
                    ?? $ticket->action_taken 
                    ?? $ticket->resolution_note 
                    ?? ($ticket->status === 'in_progress' ? 'Sedang dalam pengerjaan' : '-');

                $sheet->setCellValue("A{$currentRow}", $index + 1);
                $sheet->setCellValueExplicit("B{$currentRow}", $ticket->ticket_number, DataType::TYPE_STRING);
                $sheet->setCellValue("C{$currentRow}", $ticket->created_at ? Carbon::parse($ticket->created_at)->timezone('Asia/Makassar')->format('d/m/Y H:i') : '-');
                $sheet->setCellValue("D{$currentRow}", $ticket->department?->name ?? '-');
                $sheet->setCellValue("E{$currentRow}", $netName);
                $sheet->setCellValue("F{$currentRow}", $ticket->title ?? '-');
                $sheet->setCellValue("G{$currentRow}", $techNames);
                $sheet->setCellValue("H{$currentRow}", $statusMap[$ticket->status] ?? $ticket->status);
                $sheet->setCellValue("I{$currentRow}", $endTime ? Carbon::parse($endTime)->timezone('Asia/Makassar')->format('d/m/Y H:i') : '-');
                $sheet->setCellValue("J{$currentRow}", $actionTaken);

                // Row borders & Zebra Striping
                $rowRange = "A{$currentRow}:J{$currentRow}";
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

                // Zebra striping untuk baris genap
                if ($currentRow % 2 === 0) {
                    $sheet->getStyle($rowRange)->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setRGB('F8FAFC');
                }

                // Terapkan alignment & wrapText per kolom
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
