<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Eksekutif Rekapitulasi Pelayanan Helpdesk</title>
    <style>
        @page {
            margin: 24px 30px 24px 30px;
        }
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 9.5px;
            color: #0f172a;
            line-height: 1.35;
            margin: 0;
            padding: 0;
        }

        /* Kop Surat Resmi Kedinasan Standard Hitam-Putih */
        .kop-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 2px;
        }
        .kop-text {
            text-align: center;
        }
        .kop-text .instansi-1 {
            font-size: 13px;
            font-weight: bold;
            color: #000000;
            letter-spacing: 0.8px;
            text-transform: uppercase;
            margin: 0;
        }
        .kop-text .instansi-2 {
            font-size: 11.5px;
            font-weight: bold;
            color: #000000;
            text-transform: uppercase;
            margin: 2px 0 0 0;
            letter-spacing: 0.5px;
        }
        .kop-text .alamat {
            font-size: 8px;
            color: #475569;
            margin: 2px 0 0 0;
        }
        .kop-line {
            border-top: 2px solid #000000;
            border-bottom: 0.8px solid #000000;
            height: 2px;
            margin-top: 6px;
            margin-bottom: 12px;
        }

        /* Judul Dokumen */
        .doc-title-box {
            text-align: center;
            margin-bottom: 14px;
        }
        .doc-title {
            font-size: 11px;
            font-weight: bold;
            color: #000000;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 0;
        }
        .doc-meta {
            font-size: 8.5px;
            color: #475569;
            margin-top: 3px;
        }

        /* 4 KPI Cards Grid - Formal Bersih */
        .kpi-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 6px 0;
            margin-bottom: 14px;
        }
        .kpi-card {
            background-color: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 3px;
            padding: 8px 6px;
            text-align: center;
            vertical-align: top;
            width: 25%;
        }
        .kpi-title {
            font-size: 7.5px;
            font-weight: bold;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            margin-bottom: 3px;
        }
        .kpi-value {
            font-size: 13.5px;
            font-weight: bold;
            color: #000000;
            margin: 0;
        }
        .kpi-sub {
            font-size: 7.5px;
            color: #64748b;
            margin-top: 2px;
        }

        /* Section Layout: 2 Box Side-by-Side */
        .section-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 8px 0;
            margin-bottom: 14px;
        }
        .section-box {
            background-color: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 3px;
            padding: 8px 10px;
            vertical-align: top;
            width: 50%;
        }
        .section-header {
            font-size: 8.5px;
            font-weight: bold;
            color: #000000;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 4px;
            margin-bottom: 8px;
        }

        /* Visual Bar CSS Charts - Formal Grayscale */
        .chart-row {
            margin-bottom: 6px;
        }
        .chart-label-table {
            width: 100%;
            margin-bottom: 2px;
            font-size: 8px;
        }
        .chart-bar-bg {
            background-color: #f1f5f9;
            height: 6px;
            border-radius: 2px;
            overflow: hidden;
            width: 100%;
        }
        .chart-bar-fill {
            height: 6px;
            border-radius: 2px;
        }

        /* Data Tables - Formal Kedinasan */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
            font-size: 8.5px;
        }
        .data-table th, .data-table td {
            border: 1px solid #94a3b8;
            padding: 4px 6px;
        }
        .data-table th {
            background-color: #e2e8f0;
            color: #000000;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 8px;
            letter-spacing: 0.2px;
            text-align: left;
        }
        .data-table tbody tr:nth-child(even) {
            background-color: #f8fafc;
        }

        /* Lembar Tanda Tangan */
        .signature-table {
            width: 100%;
            margin-top: 16px;
            page-break-inside: avoid;
        }
        .signature-box {
            text-align: center;
            width: 230px;
            font-size: 9px;
            float: right;
        }
        .signature-space {
            height: 48px;
        }
    </style>
</head>
<body>

    <!-- KOP SURAT DINAS RESMI -->
    <table class="kop-table">
        <tr>
            <td class="kop-text">
                <div class="instansi-1">Pemerintah Kota Palu</div>
                <div class="instansi-2">Dinas Komunikasi dan Informatika</div>
                <div class="alamat">Jl. Pemuda No. 1, Kel. Besusu Tengah, Kec. Palu Timur, Kota Palu, Sulawesi Tengah 94111</div>
                <div class="alamat">Layanan Helpdesk & Jaringan: helpdesk.palukota.go.id | Email: kominfo@palukota.go.id</div>
            </td>
        </tr>
    </table>
    <div class="kop-line"></div>

    <!-- JUDUL DOKUMEN & PERIODE -->
    <div class="doc-title-box">
        <h1 class="doc-title">Laporan Eksekutif Rekapitulasi Pelayanan & Kinerja Helpdesk</h1>
        <div class="doc-meta">
            Periode: <strong>{{ $startDate ? \Carbon\Carbon::parse($startDate)->translatedFormat('d F Y') : 'Awal' }} s.d {{ $endDate ? \Carbon\Carbon::parse($endDate)->translatedFormat('d F Y') : 'Sekarang' }}</strong>
            &nbsp;•&nbsp;
            Dicetak: <strong>{{ \Carbon\Carbon::now('Asia/Makassar')->translatedFormat('d F Y, H:i') }} WITA</strong>
        </div>
    </div>

    <!-- BAGIAN 1: 4 KARTU KPI KUNCI (FORMAL BERSIH) -->
    <table class="kpi-table">
        <tr>
            <td class="kpi-card">
                <div class="kpi-title">Total Insiden Masuk</div>
                <div class="kpi-value">{{ $totalTickets }}</div>
                <div class="kpi-sub">{{ $inProgressTickets }} Dalam Penanganan</div>
            </td>
            <td class="kpi-card">
                <div class="kpi-title">Tingkat Penyelesaian</div>
                <div class="kpi-value">{{ $resolutionRate }}%</div>
                <div class="kpi-sub">{{ $resolvedTickets }} Insiden Tuntas</div>
            </td>
            <td class="kpi-card">
                <div class="kpi-title">Rata-Rata Penanganan</div>
                <div class="kpi-value">{{ $avgDurationText }}</div>
                <div class="kpi-sub">Waktu Respons Efektif</div>
            </td>
            <td class="kpi-card">
                <div class="kpi-title">Kepatuhan SLA & CSAT</div>
                <div class="kpi-value">{{ $slaPercentage }}%</div>
                <div class="kpi-sub">
                    @if($csatCount > 0)
                        ⭐ {{ $avgCsat }} / 5.0 ({{ $csatCount }} Ulasan)
                    @else
                        Target SLA Tercapai
                    @endif
                </div>
            </td>
        </tr>
    </table>

    <!-- BAGIAN 2: 2 KARTU VISUAL GRAFIK (FORMAL MONOKROM) -->
    <table class="section-table">
        <tr>
            <!-- Grafik 1: Sebaran Infrastruktur Jaringan -->
            <td class="section-box">
                <div class="section-header">Distribusi Infrastruktur Jaringan</div>
                @foreach($networkStats as $net)
                    <div class="chart-row">
                        <table class="chart-label-table">
                            <tr>
                                <td style="text-align: left; font-weight: bold; color: #000000;">{{ $net['label'] }}</td>
                                <td style="text-align: right; color: #475569;">{{ $net['count'] }} Tiket ({{ $net['percentage'] }}%)</td>
                            </tr>
                        </table>
                        <div class="chart-bar-bg">
                            <div class="chart-bar-fill" style="width: {{ max(2, $net['percentage']) }}%; background-color: {{ $net['color'] }};"></div>
                        </div>
                    </div>
                @endforeach
            </td>

            <!-- Grafik 2: Kinerja Kepatuhan SLA & Status -->
            <td class="section-box">
                <div class="section-header">Kinerja Kepatuhan SLA</div>
                
                <div class="chart-row">
                    <table class="chart-label-table">
                        <tr>
                            <td style="text-align: left; font-weight: bold; color: #000000;">Tepat Waktu (Sesuai SLA)</td>
                            <td style="text-align: right; font-weight: bold; color: #000000;">{{ $slaPercentage }}%</td>
                        </tr>
                    </table>
                    <div class="chart-bar-bg">
                        <div class="chart-bar-fill" style="width: {{ max(2, $slaPercentage) }}%; background-color: #1e293b;"></div>
                    </div>
                </div>

                <div class="chart-row" style="margin-top: 6px;">
                    <table class="chart-label-table">
                        <tr>
                            <td style="text-align: left; font-weight: bold; color: #475569;">Terlambat (Overdue SLA)</td>
                            <td style="text-align: right; font-weight: bold; color: #475569;">{{ max(0, 100 - $slaPercentage) }}%</td>
                        </tr>
                    </table>
                    <div class="chart-bar-bg">
                        <div class="chart-bar-fill" style="width: {{ max(2, 100 - $slaPercentage) }}%; background-color: #94a3b8;"></div>
                    </div>
                </div>
            </td>
        </tr>
    </table>

    <!-- BAGIAN 3: TABEL REKAPITULASI KINERJA PER OPD -->
    <div style="font-size: 9px; font-weight: bold; color: #000000; margin-bottom: 4px; text-transform: uppercase; letter-spacing: 0.3px;">
        Rekapitulasi Pelayanan per Instansi / OPD
    </div>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 25px; text-align: center;">No</th>
                <th>Nama Instansi / OPD</th>
                <th style="width: 70px; text-align: center;">Total Insiden</th>
                <th style="width: 70px; text-align: center;">Dituntaskan</th>
                <th style="width: 70px; text-align: center;">Penanganan</th>
                <th style="width: 75px; text-align: center;">Kepatuhan SLA</th>
            </tr>
        </thead>
        <tbody>
            @forelse($departmentBreakdown as $index => $dept)
                <tr>
                    <td style="text-align: center;">{{ $index + 1 }}</td>
                    <td style="font-weight: bold;">{{ $dept['name'] }}</td>
                    <td style="text-align: center;">{{ $dept['total'] }}</td>
                    <td style="text-align: center; font-weight: bold;">{{ $dept['resolved'] }}</td>
                    <td style="text-align: center;">{{ $dept['in_progress'] }}</td>
                    <td style="text-align: center; font-weight: bold;">
                        {{ $dept['sla_rate'] }}%
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" style="text-align: center; color: #64748b; padding: 10px;">
                        Tidak ada data tiket laporan pada filter periode yang dipilih.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <!-- BAGIAN 4: TOP 5 KATEGORI GANGGUAN TERBANYAK -->
    @if(count($topCategories) > 0)
        <div style="font-size: 9px; font-weight: bold; color: #000000; margin-bottom: 4px; text-transform: uppercase; letter-spacing: 0.3px;">
            5 Kategori Gangguan Lapangan Terbanyak
        </div>
        <table class="data-table" style="margin-bottom: 12px;">
            <thead>
                <tr>
                    <th style="width: 25px; text-align: center;">No</th>
                    <th>Kategori Kendala Teknis</th>
                    <th style="width: 100px; text-align: center;">Frekuensi Insiden</th>
                    <th style="width: 100px; text-align: center;">Persentase Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($topCategories as $i => $cat)
                    <tr>
                        <td style="text-align: center;">{{ $i + 1 }}</td>
                        <td style="font-weight: bold;">{{ $cat['name'] }}</td>
                        <td style="text-align: center; font-weight: bold;">{{ $cat['count'] }} Kasus</td>
                        <td style="text-align: center; font-weight: bold;">{{ $cat['percentage'] }}%</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <!-- BAGIAN 5: LEMBAR PENGESAHAN TANDA TANGAN -->
    <table class="signature-table">
        <tr>
            <td style="width: 60%;"></td>
            <td style="width: 40%;">
                <div class="signature-box">
                    <div>Kota Palu, {{ \Carbon\Carbon::now('Asia/Makassar')->translatedFormat('d F Y') }}</div>
                    <div style="font-weight: bold; margin-top: 2px;">Kepala Dinas Komunikasi dan Informatika<br>Kota Palu</div>
                    <div class="signature-space"></div>
                    <div style="font-weight: bold; text-decoration: underline;">( ____________________________ )</div>
                    <div style="color: #475569; font-size: 8px; margin-top: 2px;">NIP. .................................................</div>
                </div>
            </td>
        </tr>
    </table>

</body>
</html>
