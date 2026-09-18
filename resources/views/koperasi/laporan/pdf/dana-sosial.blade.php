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
        .box p:last-child { font-size: 11px; font-weight: bold; margin: 0; color: #0B4F63; }
        .grid3 { width: 100%; border-collapse: separate; border-spacing: 6px; margin-bottom: 8px; }
        h2 { font-size: 12px; color: #0B4F63; margin: 12px 0 4px; }
    </style>
</head>
<body>
    @include('koperasi.laporan.pdf._header')

    <h1 style="font-size: 15px; color: #0B4F63; text-align: center; margin: 12px 0 4px;">LAPORAN DANA SOSIAL KOPERASI CAHAYA</h1>
    <p style="text-align: center; font-size: 9px; color: #666; margin: 0 0 10px;">Periode {{ $periodeLabel }} — tidak dihitung sebagai Laba Bersih</p>

    <h2>Dana Sukarela</h2>
    <table class="grid3">
        <tr>
            <td class="box"><p>Dana Masuk</p><p>{{ rupiah($totalMasukSukarela) }}</p></td>
            <td class="box"><p>Penggunaan</p><p>{{ rupiah($totalKeluarSukarela) }}</p></td>
            <td class="box"><p>Saldo</p><p>{{ rupiah($totalMasukSukarela - $totalKeluarSukarela) }}</p></td>
        </tr>
    </table>
    <table style="width: 100%; border-collapse: separate; border-spacing: 8px 0;">
        <tr>
            <td style="width: 50%; vertical-align: top;">
                <table class="data">
                    <thead><tr><th>Tanggal</th><th>Sumber</th><th>Nominal</th></tr></thead>
                    <tbody>
                        @forelse ($sukarelaMasuk as $r)
                            <tr><td>{{ $r->tanggal->format('d-m-Y') }}</td><td>{{ $r->sumber }}</td><td>{{ rupiah($r->nominal) }}</td></tr>
                        @empty
                            <tr><td colspan="3" style="text-align:center; color:#999; font-style:italic;">Belum ada data.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </td>
            <td style="width: 50%; vertical-align: top;">
                <table class="data">
                    <thead><tr><th>Tanggal</th><th>Keperluan</th><th>Nominal</th></tr></thead>
                    <tbody>
                        @forelse ($sukarelaKeluar as $r)
                            <tr><td>{{ $r->tanggal->format('d-m-Y') }}</td><td>{{ $r->keperluan }}</td><td>{{ rupiah($r->nominal) }}</td></tr>
                        @empty
                            <tr><td colspan="3" style="text-align:center; color:#999; font-style:italic;">Belum ada data.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </td>
        </tr>
    </table>

    <h2>Dana Hibah</h2>
    <table class="grid3">
        <tr>
            <td class="box"><p>Dana Masuk</p><p>{{ rupiah($totalMasukHibah) }}</p></td>
            <td class="box"><p>Penggunaan</p><p>{{ rupiah($totalKeluarHibah) }}</p></td>
            <td class="box"><p>Saldo</p><p>{{ rupiah($totalMasukHibah - $totalKeluarHibah) }}</p></td>
        </tr>
    </table>
    <table style="width: 100%; border-collapse: separate; border-spacing: 8px 0;">
        <tr>
            <td style="width: 50%; vertical-align: top;">
                <table class="data">
                    <thead><tr><th>Tanggal</th><th>Sumber</th><th>Nominal</th></tr></thead>
                    <tbody>
                        @forelse ($hibahMasuk as $r)
                            <tr><td>{{ $r->tanggal->format('d-m-Y') }}</td><td>{{ $r->sumber }}</td><td>{{ rupiah($r->nominal) }}</td></tr>
                        @empty
                            <tr><td colspan="3" style="text-align:center; color:#999; font-style:italic;">Belum ada data.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </td>
            <td style="width: 50%; vertical-align: top;">
                <table class="data">
                    <thead><tr><th>Tanggal</th><th>Keperluan</th><th>Nominal</th></tr></thead>
                    <tbody>
                        @forelse ($hibahKeluar as $r)
                            <tr><td>{{ $r->tanggal->format('d-m-Y') }}</td><td>{{ $r->keperluan }}</td><td>{{ rupiah($r->nominal) }}</td></tr>
                        @empty
                            <tr><td colspan="3" style="text-align:center; color:#999; font-style:italic;">Belum ada data.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </td>
        </tr>
    </table>

    @include('koperasi.laporan.pdf._footer')
</body>
</html>
