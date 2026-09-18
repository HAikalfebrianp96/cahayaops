<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: sans-serif; font-size: 10px; color: #222; }
        table.data { width: 100%; border-collapse: collapse; margin-top: 6px; }
        table.data th, table.data td { border: 1px solid #ddd; padding: 4px 6px; text-align: left; font-size: 9px; }
        table.data th { background: #0B4F63; color: #fff; }
        .box { border: 1px solid #ddd; border-radius: 4px; padding: 6px 8px; text-align: center; }
        .box p:first-child { font-size: 8px; color: #888; margin: 0 0 2px; }
        .box p:last-child { font-size: 12px; font-weight: bold; margin: 0; color: #0B4F63; }
        .grid2 { width: 100%; border-collapse: separate; border-spacing: 6px; margin-bottom: 10px; }
        h2 { font-size: 12px; color: #0B4F63; margin: 12px 0 4px; }
    </style>
</head>
<body>
    @include('koperasi.laporan.pdf._header')

    <h1 style="font-size: 15px; color: #0B4F63; text-align: center; margin: 12px 0 4px;">LAPORAN SIMPANAN KOPERASI CAHAYA</h1>
    <p style="text-align: center; font-size: 9px; color: #666; margin: 0 0 10px;">Periode {{ $periodeLabel }} — Dana Anggota, bukan pendapatan usaha</p>

    <table class="grid2">
        <tr>
            <td class="box"><p>Total Simpanan Pokok</p><p>{{ rupiah($totalPokok) }}</p></td>
            <td class="box"><p>Total Simpanan Wajib</p><p>{{ rupiah($totalWajib) }}</p></td>
        </tr>
    </table>

    <table style="width: 100%; border-collapse: separate; border-spacing: 8px 0;">
        <tr>
            <td style="width: 50%; vertical-align: top;">
                <h2>Simpanan Pokok</h2>
                <table class="data">
                    <thead><tr><th>Tanggal</th><th>Nominal</th></tr></thead>
                    <tbody>
                        @forelse ($rowsPokok as $r)
                            <tr><td>{{ $r->tanggal->format('d-m-Y') }}</td><td>{{ rupiah($r->nominal) }}</td></tr>
                        @empty
                            <tr><td colspan="2" style="text-align:center; color:#999; font-style:italic;">Belum ada data.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </td>
            <td style="width: 50%; vertical-align: top;">
                <h2>Simpanan Wajib</h2>
                <table class="data">
                    <thead><tr><th>Tanggal</th><th>Nominal</th></tr></thead>
                    <tbody>
                        @forelse ($rowsWajib as $r)
                            <tr><td>{{ $r->tanggal->format('d-m-Y') }}</td><td>{{ rupiah($r->nominal) }}</td></tr>
                        @empty
                            <tr><td colspan="2" style="text-align:center; color:#999; font-style:italic;">Belum ada data.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </td>
        </tr>
    </table>

    @include('koperasi.laporan.pdf._footer')
</body>
</html>
