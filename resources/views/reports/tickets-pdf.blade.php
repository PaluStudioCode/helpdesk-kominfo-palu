<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Rekapitulasi Laporan Gangguan Jaringan</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 10px;
            color: #1e293b;
            margin: 0;
            padding: 8px;
        }
        .header-table {
            width: 100%;
            border-bottom: 2px solid #0284c7;
            padding-bottom: 10px;
            margin-bottom: 12px;
        }
        .header-title {
            text-align: center;
        }
        .header-title h2 {
            margin: 0;
            font-size: 14px;
            text-transform: uppercase;
            color: #0f172a;
        }
        .header-title h3 {
            margin: 3px 0;
            font-size: 12px;
            color: #0284c7;
        }
        .header-title p {
            margin: 0;
            font-size: 9px;
            color: #64748b;
        }
        .meta-table {
            width: 100%;
            margin-bottom: 12px;
            font-size: 9.5px;
        }
        .meta-table td {
            padding: 2px 0;
        }
        .report-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
        }
        .report-table th, .report-table td {
            border: 1px solid #cbd5e1;
            padding: 5px 6px;
            text-align: left;
        }
        .report-table th {
            background-color: #f1f5f9;
            font-weight: bold;
            color: #334155;
            text-transform: uppercase;
            font-size: 8.5px;
        }
        .badge {
            display: inline-block;
            padding: 2px 5px;
            border-radius: 3px;
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .footer {
            margin-top: 16px;
            text-align: right;
            font-size: 9.5px;
        }
        .signature-box {
            display: inline-block;
            text-align: center;
            width: 220px;
        }
    </style>
</head>
<body>
    <table class="header-table">
        <tr>
            <td class="header-title">
                <h2>Pemerintah Kota Palu</h2>
                <h3>Dinas Komunikasi, Informatika, Persandian dan Statistik</h3>
                <p>Laporan Rekapitulasi Pelayanan & Penanganan Gangguan Jaringan Helpdesk</p>
            </td>
        </tr>
    </table>

    <table class="meta-table">
        <tr>
            <td style="width: 120px;"><strong>Periode Laporan:</strong></td>
            <td>{{ $startDate ?? 'Awal' }} s/d {{ $endDate ?? 'Sekarang' }}</td>
            <td style="text-align: right;"><strong>Tanggal Cetak:</strong> {{ date('d M Y H:i') }} WITA</td>
        </tr>
        <tr>
            <td><strong>Total Tiket:</strong></td>
            <td>{{ count($tickets) }} Tiket Terdaftar</td>
            <td style="text-align: right;"><strong>Dicetak Oleh:</strong> {{ auth()->user()->name ?? 'Administrator' }}</td>
        </tr>
    </table>

    <table class="report-table">
        <thead>
            <tr>
                <th style="width: 18px; text-align: center;">No</th>
                <th style="width: 85px;">No. Tiket</th>
                <th>OPD / Instansi</th>
                <th style="width: 40px;">Jaringan</th>
                <th>Judul Kendala & Lokasi</th>
                <th style="width: 40px;">Prioritas</th>
                <th style="width: 55px;">Status</th>
                <th style="width: 75px;">Tim Teknisi</th>
                <th style="width: 60px;">Target SLA</th>
                <th style="width: 60px;">Waktu Selesai</th>
                <th style="width: 45px;">Durasi</th>
                <th style="width: 55px;">SLA</th>
                <th style="width: 40px; text-align: center;">CSAT</th>
            </tr>
        </thead>
        <tbody>
            @forelse($tickets as $index => $ticket)
                @php
                    $duration = '-';
                    $slaStatus = '-';
                    $slaColor = '#64748b';

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
                        if ($days > 0) $durParts[] = "{$days}h";
                        if ($hours > 0) $durParts[] = "{$hours}j";
                        if ($minutes > 0 || empty($durParts)) $durParts[] = "{$minutes}m";

                        $duration = implode(' ', $durParts);
                        if (!in_array($ticket->status, ['resolved', 'closed'])) {
                            $duration .= ' *';
                        }
                    }

                    if ($ticket->status === 'cancelled') {
                        $slaStatus = 'Ditolak';
                        $slaColor = '#e11d48';
                    } elseif ($ticket->status === 'pending_admin') {
                        $slaStatus = 'Verifikasi';
                        $slaColor = '#0284c7';
                    } elseif ($ticket->due_at) {
                        $due = \Carbon\Carbon::parse($ticket->due_at);
                        if (in_array($ticket->status, ['resolved', 'closed']) && $endTime) {
                            $end = \Carbon\Carbon::parse($endTime);
                            if ($end->lte($due)) {
                                $slaStatus = 'Tepat';
                                $slaColor = '#16a34a';
                            } else {
                                $slaStatus = 'Terlambat';
                                $slaColor = '#dc2626';
                            }
                        } else {
                            if (now()->gt($due)) {
                                $slaStatus = 'Overdue';
                                $slaColor = '#dc2626';
                            } elseif (now()->diffInHours($due, false) <= 2) {
                                $slaStatus = 'Mendekati';
                                $slaColor = '#d97706';
                            } else {
                                $slaStatus = 'Target';
                                $slaColor = '#0284c7';
                            }
                        }
                    }

                    $techNames = $ticket->technicians && $ticket->technicians->count() > 0
                        ? $ticket->technicians->pluck('name')->implode(', ')
                        : ($ticket->assignee?->name ?? '-');
                @endphp
                <tr>
                    <td style="text-align: center;">{{ $index + 1 }}</td>
                    <td><strong>{{ $ticket->ticket_number }}</strong></td>
                    <td>{{ $ticket->department?->name ?? '-' }}</td>
                    <td>{{ $ticket->network_type ? strtoupper($ticket->network_type) : '-' }}</td>
                    <td>
                        <strong>{{ $ticket->title }}</strong><br>
                        <span style="color: #64748b; font-size: 7.5px;">Lokasi: {{ $ticket->location_details }}</span>
                    </td>
                    <td>{{ $ticket->priority ? ucfirst($ticket->priority) : '-' }}</td>
                    <td>{{ strtoupper(str_replace('_', ' ', $ticket->status)) }}</td>
                    <td>{{ $techNames }}</td>
                    <td>{{ $ticket->due_at ? $ticket->due_at->format('d/m/y H:i') : '-' }}</td>
                    <td>{{ $endTime ? \Carbon\Carbon::parse($endTime)->format('d/m/y H:i') : '-' }}</td>
                    <td style="text-align: center;">{{ $duration }}</td>
                    <td style="text-align: center; color: {{ $slaColor }}; font-weight: bold; font-size: 8px;">{{ $slaStatus }}</td>
                    <td style="text-align: center; font-weight: bold;">{{ $ticket->rating ? $ticket->rating . '★' : '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="13" style="text-align: center; color: #94a3b8; padding: 18px;">
                        Tidak ada data tiket laporan pada filter yang dipilih.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        <div class="signature-box">
            <p>Kota Palu, {{ date('d F Y') }}</p>
            <p>Kepala Dinas Komunikasi, Informatika,<br>Persandian dan Statistik Kota Palu</p>
            <br><br><br>
            <p><strong>( ____________________________ )</strong><br>NIP. .................................................</p>
        </div>
    </div>
</body>
</html>
