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
        .grid { width: 100%; border-collapse: separate; border-spacing: 5px; margin-bottom: 8px; }
        h2 { font-size: 12px; color: #0B4F63; margin: 12px 0 4px; }
    </style>
</head>
<body>
    @include('koperasi.laporan.pdf._header')

    <h1 style="font-size: 15px; color: #0B4F63; text-align: center; margin: 12px 0 2px;">LAPORAN UNIT USAHA — {{ strtoupper($unit?->nama ?? '-') }}</h1>
    <p style="text-align: center; font-size: 9px; color: #666; margin: 0 0 10px;">Periode {{ $periodeLabel }}</p>

    @if ($ringkasanUnit)
        <table class="grid">
            <tr>
                <td class="box"><p>Penjualan</p><p>{{ rupiah($ringkasanUnit['penjualan']) }}</p></td>
                <td class="box"><p>HPP</p><p>{{ rupiah($ringkasanUnit['hpp']) }}</p></td>
                <td class="box"><p>Fee</p><p>{{ rupiah($ringkasanUnit['fee_koperasi']) }}</p></td>
                <td class="box"><p>Pengeluaran</p><p>{{ rupiah($ringkasanUnit['pengeluaran']) }}</p></td>
                <td class="box" style="background:#F9FBFC;"><p>Laba Bersih</p><p style="color: {{ $ringkasanUnit['laba_bersih'] >= 0 ? '#157347' : '#c0392b' }};">{{ rupiah($ringkasanUnit['laba_bersih']) }}</p></td>
            </tr>
        </table>
    @endif

    <h2>Daftar Transaksi</h2>
    <table class="data">
        <thead><tr><th>Tanggal</th><th>Customer</th><th>Total</th></tr></thead>
        <tbody>
            @forelse ($transaksi as $t)
                <tr><td>{{ $t->tanggal->format('d-m-Y') }}</td><td>{{ $t->customer->nama ?? '-' }}</td><td>{{ rupiah($t->total) }}</td></tr>
            @empty
                <tr><td colspan="3" style="text-align:center; color:#999; font-style:italic;">Belum ada transaksi.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>Daftar Pengeluaran</h2>
    <table class="data">
        <thead><tr><th>Tanggal</th><th>Kategori</th><th>Nominal</th></tr></thead>
        <tbody>
            @forelse ($pengeluaran as $p)
                <tr><td>{{ $p->tanggal->format('d-m-Y') }}</td><td>{{ $p->kategori }}</td><td>{{ rupiah($p->nominal) }}</td></tr>
            @empty
                <tr><td colspan="3" style="text-align:center; color:#999; font-style:italic;">Belum ada pengeluaran.</td></tr>
            @endforelse
        </tbody>
    </table>

    @include('koperasi.laporan.pdf._footer')
</body>
</html>
