<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $emailSubject }}</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background-color: #f1f5f9;
            margin: 0;
            padding: 24px;
            color: #0f172a;
        }
        .card {
            max-width: 600px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .header {
            background-color: #0284c7;
            color: #ffffff;
            padding: 20px 24px;
            text-align: left;
        }
        .header h1 {
            margin: 0;
            font-size: 18px;
            font-weight: 600;
        }
        .header p {
            margin: 4px 0 0;
            font-size: 13px;
            opacity: 0.9;
        }
        .body {
            padding: 24px;
        }
        .greeting {
            font-size: 15px;
            font-weight: 600;
            margin-bottom: 12px;
        }
        .message {
            font-size: 14px;
            line-height: 1.6;
            color: #334155;
            margin-bottom: 20px;
        }
        .ticket-info {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 16px;
            margin-bottom: 24px;
        }
        .ticket-info table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        .ticket-info td {
            padding: 6px 0;
            vertical-align: top;
        }
        .ticket-info td.label {
            width: 140px;
            color: #64748b;
            font-weight: 500;
        }
        .ticket-info td.value {
            color: #0f172a;
            font-weight: 600;
        }
        .btn-wrapper {
            text-align: center;
            margin: 28px 0 16px;
        }
        .btn {
            display: inline-block;
            background-color: #0284c7;
            color: #ffffff !important;
            text-decoration: none;
            padding: 10px 22px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
        }
        .footer {
            background-color: #f8fafc;
            border-top: 1px solid #e2e8f0;
            padding: 16px 24px;
            text-align: center;
            font-size: 12px;
            color: #94a3b8;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="header">
            <h1>Layanan Helpdesk Jaringan</h1>
            <p>Dinas Komunikasi dan Informatika Kota Palu</p>
        </div>
        <div class="body">
            <div class="greeting">Halo, {{ $recipient->name }}</div>
            <p class="message">{{ $headline }}</p>
            <p class="message">{{ $customMessage }}</p>

            <div class="ticket-info">
                <table>
                    <tr>
                        <td class="label">Nomor Tiket</td>
                        <td class="value">{{ $ticket->ticket_number }}</td>
                    </tr>
                    <tr>
                        <td class="label">Judul Masalah</td>
                        <td class="value">{{ $ticket->title }}</td>
                    </tr>
                    <tr>
                        <td class="label">Instansi / OPD</td>
                        <td class="value">{{ $ticket->department?->name ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td class="label">Jenis Infrastruktur</td>
                        <td class="value">{{ strtoupper($ticket->infrastructure_type ?? $ticket->network_type ?? '-') }}</td>
                    </tr>
                    <tr>
                        <td class="label">Prioritas / SLA</td>
                        <td class="value">{{ ucfirst($ticket->priority ?? 'normal') }} (Target: {{ $ticket->due_at ? $ticket->due_at->translatedFormat('d M Y, H:i') : '-' }} WITA)</td>
                    </tr>
                    <tr>
                        <td class="label">Status Saat Ini</td>
                        <td class="value">{{ strtoupper(str_replace('_', ' ', $ticket->status)) }}</td>
                    </tr>
                </table>
            </div>

            <div class="btn-wrapper">
                <a href="{{ url('/tickets/' . $ticket->id) }}" class="btn">Lihat Detail Tiket di Portal</a>
            </div>
        </div>
        <div class="footer">
            &copy; {{ date('Y') }} Dinas Komunikasi dan Informatika Kota Palu. Pesan ini dikirim otomatis oleh sistem.
        </div>
    </div>
</body>
</html>
