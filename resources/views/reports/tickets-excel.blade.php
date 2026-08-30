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
                <th colspan="15" style="font-size: 14pt; font-weight: bold; text-align: center;">REKAPITULASI LAPORAN GANGGUAN JARINGAN KOTA PALU</th>
            </tr>
            <tr>
                <th colspan="15" style="font-size: 10pt; text-align: center;">Dinas Komunikasi, Informatika, Persandian dan statistik Kota Palu</th>
            </tr>
            <tr>
                <th colspan="15"></th>
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
                <th style="background-color: #0284c7; color: #ffffff; font-weight: bold; width: 140px;">Target SLA</th>
                <th style="background-color: #0284c7; color: #ffffff; font-weight: bold; width: 140px;">Waktu Selesai</th>
                <th style="background-color: #0284c7; color: #ffffff; font-weight: bold; width: 130px;">Lama Penanganan</th>
                <th style="background-color: #0284c7; color: #ffffff; font-weight: bold; width: 150px;">Status SLA</th>
            </tr>
        </thead>
        <tbody>
            @foreach($tickets as $index => $ticket)
                @php
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
                @endphp
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
                    <td>{{ $ticket->due_at ? $ticket->due_at->format('Y-m-d H:i') : '-' }}</td>
                    <td>{{ $endTime ? \Carbon\Carbon::parse($endTime)->format('Y-m-d H:i') : '-' }}</td>
                    <td>{{ $duration }}</td>
                    <td>{{ $slaStatus }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
