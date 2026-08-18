<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Rekapitulasi Laporan Gangguan Jaringan</title>
    <style>
        table, th, td {
            border: 1px solid #000000;
        }
        th {
            background-color: #0284c7;
            color: #ffffff;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <table>
        <thead>
            <tr>
                <th colspan="11" style="font-size: 14pt; font-weight: bold; text-align: center;">REKAPITULASI LAPORAN GANGGUAN JARINGAN KOTA PALU</th>
            </tr>
            <tr>
                <th colspan="11" style="font-size: 10pt; text-align: center;">Dinas Komunikasi dan Informatika Kota Palu</th>
            </tr>
            <tr>
                <th colspan="11"></th>
            </tr>
            <tr>
                <th style="background-color: #0284c7; color: #ffffff; font-weight: bold; width: 50px;">No</th>
                <th style="background-color: #0284c7; color: #ffffff; font-weight: bold; width: 140px;">No. Tiket</th>
                <th style="background-color: #0284c7; color: #ffffff; font-weight: bold; width: 220px;">OPD / Instansi</th>
                <th style="background-color: #0284c7; color: #ffffff; font-weight: bold; width: 100px;">Jenis Jaringan</th>
                <th style="background-color: #0284c7; color: #ffffff; font-weight: bold; width: 160px;">Kategori Masalah</th>
                <th style="background-color: #0284c7; color: #ffffff; font-weight: bold; width: 240px;">Judul Gangguan</th>
                <th style="background-color: #0284c7; color: #ffffff; font-weight: bold; width: 180px;">Lokasi / Ruangan</th>
                <th style="background-color: #0284c7; color: #ffffff; font-weight: bold; width: 90px;">Prioritas</th>
                <th style="background-color: #0284c7; color: #ffffff; font-weight: bold; width: 110px;">Status</th>
                <th style="background-color: #0284c7; color: #ffffff; font-weight: bold; width: 160px;">Teknisi Penanggung Jawab</th>
                <th style="background-color: #0284c7; color: #ffffff; font-weight: bold; width: 140px;">Waktu Dibuat</th>
            </tr>
        </thead>
        <tbody>
            @foreach($tickets as $index => $ticket)
                <tr>
                    <td style="text-align: center;">{{ $index + 1 }}</td>
                    <td>{{ $ticket->ticket_number }}</td>
                    <td>{{ $ticket->department?->name ?? '-' }}</td>
                    <td>{{ strtoupper($ticket->network_type) }}</td>
                    <td>{{ $ticket->category?->name ?? '-' }}</td>
                    <td>{{ $ticket->title }}</td>
                    <td>{{ $ticket->location_details }}</td>
                    <td>{{ ucfirst($ticket->priority) }}</td>
                    <td>{{ strtoupper(str_replace('_', ' ', $ticket->status)) }}</td>
                    <td>{{ $ticket->assignee?->name ?? '-' }}</td>
                    <td>{{ $ticket->created_at ? $ticket->created_at->format('Y-m-d H:i') : '-' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
