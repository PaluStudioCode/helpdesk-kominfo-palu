<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Rekapitulasi Pelayanan Helpdesk & Jaringan TIK</title>
    <style>
        @page {
            margin: 28px 34px 28px 34px;
        }
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 9pt;
            color: #111111;
            line-height: 1.4;
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
            font-size: 13pt;
            font-weight: bold;
            color: #000000;
            letter-spacing: 0.8px;
            text-transform: uppercase;
            margin: 0;
        }
        .kop-text .instansi-2 {
            font-size: 11.5pt;
            font-weight: bold;
            color: #000000;
            text-transform: uppercase;
            margin: 2px 0 0 0;
            letter-spacing: 0.5px;
        }
        .kop-text .alamat {
            font-size: 8pt;
            color: #333333;
            margin: 2px 0 0 0;
        }
        .kop-line {
            border-top: 2px solid #000000;
            border-bottom: 0.8px solid #000000;
            height: 2px;
            margin-top: 6px;
            margin-bottom: 12px;
        }

        /* Judul Dokumen & Metadata */
        .doc-title-box {
            text-align: center;
            margin-bottom: 14px;
        }
        .doc-title {
            font-size: 11pt;
            font-weight: bold;
            color: #000000;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 0;
        }
        .doc-meta {
            font-size: 8.5pt;
            color: #333333;
            margin-top: 3px;
        }

        /* Judul Bab / Poin Laporan Kedinasan */
        .section-title {
            font-size: 9pt;
            font-weight: bold;
            color: #000000;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            margin-top: 12px;
            margin-bottom: 5px;
            border-bottom: 0.5px solid #666666;
            padding-bottom: 2px;
        }

        /* Tabel Data Format Laporan Kantor */
        .report-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
            font-size: 8.5pt;
        }
        .report-table th, .report-table td {
            border: 0.8px solid #333333;
            padding: 4px 6px;
        }
        .report-table th {
            background-color: #f1f5f9;
            color: #000000;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 8pt;
            letter-spacing: 0.2px;
            text-align: left;
        }
        .report-table tbody tr:nth-child(even) {
            background-color: #fafafa;
        }

        /* Lembar Pengesahan Tanda Tangan */
        .signature-table {
            width: 100%;
            margin-top: 18px;
            page-break-inside: avoid;
        }
        .signature-box {
            text-align: center;
            font-size: 9pt;
        }
        .signature-space {
            height: 54px;
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
                <div class="alamat">Jalan Pemuda No. 1, Kel. Besusu Tengah, Kec. Palu Timur, Kota Palu, Sulawesi Tengah 94111</div>
                <div class="alamat">Layanan Helpdesk & Jaringan: helpdesk.palukota.go.id &nbsp;|&nbsp; Pos-el: kominfo@palukota.go.id</div>
            </td>
        </tr>
    </table>
    <div class="kop-line"></div>

    <!-- JUDUL DOKUMEN & KETERANGAN PERIODE -->
    <div class="doc-title-box">
        <h1 class="doc-title">Laporan Rekapitulasi Pelayanan Helpdesk & Jaringan TIK</h1>
        <div class="doc-meta">
            Periode: <strong>{{ $startDate ? \Carbon\Carbon::parse($startDate)->translatedFormat('d F Y') : 'Semua Periode' }} s.d {{ $endDate ? \Carbon\Carbon::parse($endDate)->translatedFormat('d F Y') : 'Sekarang' }}</strong>
            @if(!empty($filterDeptName))
                &nbsp;|&nbsp; Instansi: <strong>{{ $filterDeptName }}</strong>
            @endif
            @if(!empty($filterStatusName))
                &nbsp;|&nbsp; Status: <strong>{{ $filterStatusName }}</strong>
            @endif
            &nbsp;|&nbsp;
            Dicetak: <strong>{{ \Carbon\Carbon::now('Asia/Makassar')->translatedFormat('d F Y, H:i') }} WITA</strong>
        </div>
    </div>

    <!-- BAB I: RINGKASAN INDIKATOR KINERJA UTAMA -->
    <div class="section-title">I. Ringkasan Indikator Kinerja Pelayanan</div>
    <table class="report-table">
        <thead>
            <tr>
                <th style="width: 5%; text-align: center;">No</th>
                <th style="width: 65%;">Indikator Kinerja Pelayanan</th>
                <th style="width: 30%; text-align: right;">Capaian / Realisasi</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td style="text-align: center;">1</td>
                <td>Total Laporan Gangguan Masuk</td>
                <td style="text-align: right; font-weight: bold;">{{ number_format($totalTickets, 0, ',', '.') }} Kasus</td>
            </tr>
            <tr>
                <td style="text-align: center;">2</td>
                <td>Laporan Selesai Ditangani (Tingkat Penyelesaian)</td>
                <td style="text-align: right; font-weight: bold;">{{ number_format($resolvedTickets, 0, ',', '.') }} Kasus ({{ $resolutionRate }}%)</td>
            </tr>
            <tr>
                <td style="text-align: center;">3</td>
                <td>Laporan Sedang Dalam Pengerjaan / Proses</td>
                <td style="text-align: right;">{{ number_format($inProgressTickets, 0, ',', '.') }} Kasus</td>
            </tr>
            <tr>
                <td style="text-align: center;">4</td>
                <td>Laporan Ditolak / Dibatalkan (Tidak Valid)</td>
                <td style="text-align: right;">{{ number_format($cancelledTickets, 0, ',', '.') }} Kasus</td>
            </tr>
            <tr>
                <td style="text-align: center;">5</td>
                <td>Rata-Rata Durasi Penanganan Gangguan</td>
                <td style="text-align: right; font-weight: bold;">{{ $avgDurationText }}</td>
            </tr>
            <tr>
                <td style="text-align: center;">6</td>
                <td>Tingkat Kepatuhan Standar Waktu Layanan (SLA)</td>
                <td style="text-align: right; font-weight: bold;">{{ $slaPercentage }}% Tepat Waktu</td>
            </tr>
            <tr>
                <td style="text-align: center;">7</td>
                <td>Indeks Kepuasan Pengguna Layanan (CSAT)</td>
                <td style="text-align: right;">
                    @if($csatCount > 0)
                        <strong>{{ number_format($avgCsat, 1, ',', '.') }} / 5,0</strong> (dari {{ $csatCount }} responden dinas)
                    @else
                        -
                    @endif
                </td>
            </tr>
        </tbody>
    </table>

    <!-- BAB II: REKAPITULASI SEBARAN INFRASTRUKTUR JARINGAN -->
    <div class="section-title">II. Rekapitulasi Berdasarkan Jenis Infrastruktur Jaringan</div>
    <table class="report-table">
        <thead>
            <tr>
                <th style="width: 5%; text-align: center;">No</th>
                <th style="width: 50%;">Jenis Infrastruktur Jaringan</th>
                <th style="width: 25%; text-align: center;">Jumlah Kasus</th>
                <th style="width: 20%; text-align: right;">Persentase (%)</th>
            </tr>
        </thead>
        <tbody>
            @php 
                $infraList = $infrastructureStats ?? $networkStats ?? []; 
                $no = 1;
            @endphp
            @foreach($infraList as $net)
                <tr>
                    <td style="text-align: center;">{{ $no++ }}</td>
                    <td style="font-weight: bold;">{{ $net['label'] }}</td>
                    <td style="text-align: center;">{{ number_format($net['count'], 0, ',', '.') }} Kasus</td>
                    <td style="text-align: right;">{{ number_format($net['percentage'], 1, ',', '.') }}%</td>
                </tr>
            @endforeach
            <tr style="background-color: #f1f5f9; font-weight: bold;">
                <td colspan="2" style="text-align: center;">TOTAL KESELURUHAN</td>
                <td style="text-align: center;">{{ number_format($totalTickets, 0, ',', '.') }} Kasus</td>
                <td style="text-align: right;">100,0%</td>
            </tr>
        </tbody>
    </table>

    <!-- BAB III: KINERJA TINGKAT KEPATUHAN SERVICE LEVEL AGREEMENT (SLA) -->
    <div class="section-title">III. Evaluasi Kepatuhan Target Service Level Agreement (SLA)</div>
    <table class="report-table">
        <thead>
            <tr>
                <th style="width: 5%; text-align: center;">No</th>
                <th style="width: 50%;">Kategori Kepatuhan SLA</th>
                <th style="width: 25%; text-align: center;">Jumlah Kasus</th>
                <th style="width: 20%; text-align: right;">Persentase Kepatuhan</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td style="text-align: center;">1</td>
                <td style="font-weight: bold;">Tepat Waktu (Memenuhi Standar Pelayanan)</td>
                <td style="text-align: center;">{{ number_format($slaOnTimeCount ?? $resolvedTickets, 0, ',', '.') }} Kasus</td>
                <td style="text-align: right; font-weight: bold;">{{ number_format($slaPercentage, 1, ',', '.') }}%</td>
            </tr>
            <tr>
                <td style="text-align: center;">2</td>
                <td style="font-weight: bold;">Terlambat (Melewati Target Batas SLA)</td>
                <td style="text-align: center;">{{ number_format($slaOverdueCount ?? max(0, $totalTickets - $resolvedTickets), 0, ',', '.') }} Kasus</td>
                <td style="text-align: right;">{{ number_format(max(0, 100 - $slaPercentage), 1, ',', '.') }}%</td>
            </tr>
            <tr style="background-color: #f1f5f9; font-weight: bold;">
                <td colspan="2" style="text-align: center;">TOTAL TIKET DENGAN TARGET SLA</td>
                <td style="text-align: center;">{{ number_format($completedTicketsWithSla ?? $totalTickets, 0, ',', '.') }} Kasus</td>
                <td style="text-align: right;">100,0%</td>
            </tr>
        </tbody>
    </table>

    <!-- BAB IV: REKAPITULASI PELAYANAN PER INSTANSI / PERANGKAT DAERAH (OPD) -->
    <div class="section-title">IV. Rekapitulasi Pelayanan per Instansi / Perangkat Daerah (OPD)</div>
    <table class="report-table">
        <thead>
            <tr>
                <th style="width: 5%; text-align: center;">No</th>
                <th style="width: 47%;">Nama Instansi / Perangkat Daerah</th>
                <th style="width: 12%; text-align: center;">Total</th>
                <th style="width: 12%; text-align: center;">Selesai</th>
                <th style="width: 12%; text-align: center;">Proses</th>
                <th style="width: 12%; text-align: right;">SLA (%)</th>
            </tr>
        </thead>
        <tbody>
            @forelse($departmentBreakdown as $index => $dept)
                <tr>
                    <td style="text-align: center;">{{ $index + 1 }}</td>
                    <td>{{ $dept['name'] }}</td>
                    <td style="text-align: center;">{{ $dept['total'] }}</td>
                    <td style="text-align: center; font-weight: bold;">{{ $dept['resolved'] }}</td>
                    <td style="text-align: center;">{{ $dept['in_progress'] }}</td>
                    <td style="text-align: right; font-weight: bold;">{{ $dept['sla_rate'] }}%</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" style="text-align: center; color: #666666; padding: 8px;">
                        Tidak ada data laporan pada filter periode yang dipilih.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <!-- BAB V: 5 KATEGORI KENDALA / GANGGUAN TERBANYAK -->
    @if(count($topCategories) > 0)
        <div class="section-title">V. Kategori Kendala / Gangguan Lapangan Terbanyak</div>
        <table class="report-table">
            <thead>
                <tr>
                    <th style="width: 5%; text-align: center;">No</th>
                    <th style="width: 55%;">Kategori Kendala Teknis</th>
                    <th style="width: 20%; text-align: center;">Frekuensi Kasus</th>
                    <th style="width: 20%; text-align: right;">Persentase (%)</th>
                </tr>
            </thead>
            <tbody>
                @foreach($topCategories as $i => $cat)
                    <tr>
                        <td style="text-align: center;">{{ $i + 1 }}</td>
                        <td style="font-weight: bold;">{{ $cat['name'] }}</td>
                        <td style="text-align: center; font-weight: bold;">{{ $cat['count'] }} Kasus</td>
                        <td style="text-align: right; font-weight: bold;">{{ number_format($cat['percentage'], 1, ',', '.') }}%</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <!-- BAB VI: LEMBAR PENGESAHAN TANDA TANGAN -->
    <table class="signature-table">
        <tr>
            <td style="width: 55%;"></td>
            <td style="width: 45%;">
                <div class="signature-box">
                    <div>Palu, {{ \Carbon\Carbon::now('Asia/Makassar')->translatedFormat('d F Y') }}</div>
                    <div style="font-weight: bold; margin-top: 3px;">Kepala Dinas Komunikasi dan Informatika<br>Kota Palu</div>
                    <div class="signature-space"></div>
                    <div style="font-weight: bold; text-decoration: underline;">( ___________________________________ )</div>
                    <div style="font-size: 8pt; color: #333333; margin-top: 3px;">NIP. ................................................................</div>
                </div>
            </td>
        </tr>
    </table>

</body>
</html>
