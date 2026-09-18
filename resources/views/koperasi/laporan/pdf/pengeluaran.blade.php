<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: sans-serif; font-size: 10px; color: #222; }
        table.data { width: 100%; border-collapse: collapse; margin-top: 8px; }
        table.data th, table.data td { border: 1px solid #ddd; padding: 4px 6px; text-align: left; font-size: 9px; }
        table.data th { background: #0B4F63; color: #fff; }
        .box { border: 1px solid #ddd; border-radius: 4px; padding: 6px 8px; }
        .box p:first-child { font-size: 8px; color: #888; margin: 0 0 2px; }
        .box p:last-child { font-size: 10px; font-weight: bold; margin: 0; }
        h2 { font-size: 13px; color: #0B4F63; margin: 14px 0 4px; }
    </style>
</head>
<body>
    @include('koperasi.laporan.pdf._header')

    <h1 style="font-size: 15px; color: #0B4F63; text-align: center; margin: 12px 0 4px;">LAPORAN PENGELUARAN KOPERASI CAHAYA</h1>
    <p style="text-align: center; font-size: 9px; color: #666; margin: 0 0 10px;">Periode {{ $periodeLabel }}</p>

    <div class="box" style="text-align: center; margin-bottom: 8px;">
        <p>Total Pengeluaran Aktif</p>
        <p style="font-size: 14px; color: #0B4F63;">{{ rupiah($totalAktif) }}</p>
    </div>

    <table style="width: 100%; border-collapse: separate; border-spacing: 8px 0;">
        <tr>
            <td style="width: 50%; vertical-align: top;">
                <h2>Per Unit</h2>
                <table class="data">
                    @foreach ($perUnit as $u)
                        <tr><td>{{ $u['label'] }}</td><td>{{ rupiah($u['total']) }}</td></tr>
                    @endforeach
                </table>
            </td>
            <td style="width: 50%; vertical-align: top;">
                <h2>Per Kategori</h2>
                <table class="data">
                    @foreach ($perKategori as $k)
                        <tr><td>{{ $k['kategori'] }}</td><td>{{ rupiah($k['total']) }}</td></tr>
                    @endforeach
                </table>
            </td>
        </tr>
    </table>

    <h2>Rincian Pengeluaran</h2>
    <table class="data">
        <thead><tr><th>Tanggal</th><th>Unit</th><th>Kategori</th><th>Keterangan</th><th>Metode</th><th>Nominal</th><th>Status</th></tr></thead>
        <tbody>
            @forelse ($rows as $r)
                <tr>
                    <td>{{ $r->tanggal->format('d-m-Y') }}</td><td>{{ $r->labelUnit() }}</td><td>{{ $r->kategori }}</td>
                    <td>{{ $r->keterangan ?: '-' }}</td><td>{{ $r->metode_pembayaran }}</td>
                    <td>{{ rupiah($r->nominal) }}</td><td>{{ $r->status }}</td>
                </tr>
            @empty
                <tr><td colspan="7" style="text-align:center; color:#999; font-style:italic;">Belum ada data pada periode ini.</td></tr>
            @endforelse
        </tbody>
    </table>

    @include('koperasi.laporan.pdf._footer')
</body>
</html>
