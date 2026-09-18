<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: sans-serif; font-size: 10px; color: #222; }
        table.data { width: 100%; border-collapse: collapse; margin-top: 6px; }
        table.data th, table.data td { border: 1px solid #ccc; padding: 5px 6px; text-align: left; }
        table.data th { background: #0B4F63; color: #fff; font-size: 9px; }
        table.data td { font-size: 8.5px; }
        table.data tr:nth-child(even) { background: #f7f7f7; }
        .ringkasan { margin-top: 14px; font-size: 10px; }
        .kop { width: 100%; border-collapse: collapse; border-bottom: 2px solid #0B4F63; padding-bottom: 8px; }
    </style>
</head>
<body>
    {{-- Kop laporan: logo, judul, periode, filter, tanggal cetak, dan NAMA
         PENCETAK — laporan kinerja yang dibagikan harus bisa ditelusuri
         siapa yang mengeluarkannya. --}}
    <div style="width: 100%; text-align: center; margin-bottom: 8px;">
        <img src="{{ $logoPath }}" style="height: 44px;">
    </div>

    <table class="kop">
        <tr>
            <td style="vertical-align: bottom; padding-bottom: 8px;">
                <p style="margin: 0; font-size: 12px; font-weight: bold; color: #0B4F63;">Program Cahaya Preneur</p>
                <p style="margin: 0; font-size: 9px; color: #666;">YBM PLN UP3 Bengkulu</p>
            </td>
            <td style="vertical-align: bottom; text-align: right; font-size: 9px; color: #666; padding-bottom: 8px;">
                <p style="margin: 0;">Periode: {{ $periodeLabel }}</p>
                <p style="margin: 0;">Dicetak: {{ $tanggalCetak }}</p>
                <p style="margin: 0;">Oleh: {{ $dicetakOleh }}</p>
            </td>
        </tr>
    </table>

    <h2 style="font-size: 14px; color: #0B4F63; margin: 14px 0 4px 0;">{{ $judulLaporan }}</h2>
    @if ($filterRingkasan)
        <p style="font-size: 9px; color: #888; margin: 0 0 12px 0;">Filter: {{ $filterRingkasan }}</p>
    @else
        <p style="font-size: 9px; color: #888; margin: 0 0 12px 0;">Menampilkan seluruh pendamping (tanpa filter status)</p>
    @endif

    <table class="data">
        <thead>
            <tr>
                <th>Pendamping</th>
                <th>Sumber Target</th>
                <th>Binaan</th>
                <th>Kunjungan</th>
                <th>Pendampingan</th>
                <th>Follow Up</th>
                <th>Input Omzet</th>
                <th>Beban</th>
                <th>KPI</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rapor as $r)
                <tr>
                    <td>{{ $r['pendamping']->nama }}</td>
                    <td>{{ $r['pakai_default'] ? 'Default' : 'Individual' }}</td>
                    @foreach (['target_jumlah_binaan', 'target_kunjungan', 'target_pendampingan', 'target_follow_up', 'target_input_omzet'] as $kolom)
                        <td>{{ $r['indikator'][$kolom]['aktual'] }} / {{ $r['indikator'][$kolom]['target'] ?? '-' }}</td>
                    @endforeach
                    <td>{{ $r['beban']['kategori'] }}</td>
                    <td>{{ $r['kpi'] !== null ? $r['kpi'] . '%' : '-' }}</td>
                    <td>{{ $r['status'] ?? 'Belum ada target' }}</td>
                </tr>
            @empty
                <tr><td colspan="10" style="text-align:center; font-style:italic;">Tidak ada data.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="ringkasan">
        <strong>Ringkasan:</strong>
        {{ $ringkasan['total_pendamping'] }} pendamping &middot;
        rata-rata KPI {{ $ringkasan['rata_rata'] !== null ? $ringkasan['rata_rata'] . '%' : '-' }} &middot;
        Sangat Baik {{ $ringkasan['sangat_baik'] }} &middot;
        Baik {{ $ringkasan['baik'] }} &middot;
        Perlu Perhatian {{ $ringkasan['perlu_perhatian'] }} &middot;
        Rendah {{ $ringkasan['rendah'] }}.
    </div>

    <p style="margin-top: 10px; font-size: 8.5px; color: #888;">
        Catatan: indikator yang tidak ditargetkan (bertanda "-") TIDAK ikut dihitung ke KPI.
        Angka realisasi diambil dari data pendampingan yang tersimpan pada periode ini;
        data yang sudah diarsipkan tidak ikut dihitung.
    </p>
</body>
</html>
