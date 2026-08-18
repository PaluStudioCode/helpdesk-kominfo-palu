<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Rekapitulasi Laporan Gangguan Jaringan</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            color: #1e293b;
            margin: 0;
            padding: 10px;
        }
        .header-table {
            width: 100%;
            border-bottom: 2px solid #0284c7;
            padding-bottom: 12px;
            margin-bottom: 16px;
        }
        .header-title {
            text-align: center;
        }
        .header-title h2 {
            margin: 0;
            font-size: 15px;
            text-transform: uppercase;
            color: #0f172a;
        }
        .header-title h3 {
            margin: 4px 0;
            font-size: 13px;
            color: #0284c7;
        }
        .header-title p {
            margin: 0;
            font-size: 10px;
            color: #64748b;
        }
        .meta-table {
            width: 100%;
            margin-bottom: 14px;
            font-size: 10px;
        }
        .meta-table td {
            padding: 2px 0;
        }
        .report-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .report-table th, .report-table td {
            border: 1px solid #cbd5e1;
            padding: 6px 8px;
            text-align: left;
        }
        .report-table th {
            background-color: #f1f5f9;
            font-weight: bold;
            color: #334155;
            text-transform: uppercase;
            font-size: 9px;
        }
        .badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .footer {
            margin-top: 20px;
            text-align: right;
            font-size: 10px;
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
                <h3>Dinas Komunikasi dan Informatika</h3>
                <p>Laporan Rekapitulasi Pelayanan & Penanganan Gangguan Jaringan</p>
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
                <th style="width: 25px; text-align: center;">No</th>
                <th style="width: 105px;">No. Tiket</th>
                <th>OPD / Instansi</th>
                <th style="width: 50px;">Jaringan</th>
                <th>Judul Gangguan & Lokasi</th>
                <th style="width: 55px;">Prioritas</th>
                <th style="width: 65px;">Status</th>
                <th style="width: 85px;">Teknisi</th>
                <th style="width: 80px;">Waktu Dibuat</th>
            </tr>
        </thead>
        <tbody>
            @forelse($tickets as $index => $ticket)
                <tr>
                    <td style="text-align: center;">{{ $index + 1 }}</td>
                    <td><strong>{{ $ticket->ticket_number }}</strong></td>
                    <td>{{ $ticket->department?->name ?? '-' }}</td>
                    <td>{{ strtoupper($ticket->network_type) }}</td>
                    <td>
                        <strong>{{ $ticket->title }}</strong><br>
                        <span style="color: #64748b; font-size: 9px;">Lokasi: {{ $ticket->location_details }}</span>
                    </td>
                    <td>{{ ucfirst($ticket->priority) }}</td>
                    <td>{{ strtoupper(str_replace('_', ' ', $ticket->status)) }}</td>
                    <td>{{ $ticket->assignee?->name ?? '-' }}</td>
                    <td>{{ $ticket->created_at ? $ticket->created_at->format('d/m/Y H:i') : '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" style="text-align: center; color: #94a3b8; padding: 20px;">
                        Tidak ada data tiket laporan pada filter yang dipilih.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        <div class="signature-box">
            <p>Kota Palu, {{ date('d F Y') }}</p>
            <p>Kepala Dinas Komunikasi dan Informatika<br>Kota Palu</p>
            <br><br><br>
            <p><strong>( ____________________________ )</strong><br>NIP. .................................................</p>
        </div>
    </div>
</body>
</html>
