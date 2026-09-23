<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: sans-serif; font-size: 10px; color: #222; }
        table.data { width: 100%; border-collapse: collapse; margin-top: 8px; }
        table.data th, table.data td { border: 1px solid #ddd; padding: 4px 6px; text-align: left; font-size: 9px; }
        table.data th { background: #0B4F63; color: #fff; }
        table.data tr.total td { font-weight: bold; background: #F9FBFC; }
        .box { border: 1px solid #ddd; border-radius: 4px; padding: 6px 8px; text-align: center; }
        .box p:first-child { font-size: 8px; color: #888; margin: 0 0 2px; }
        .box p:last-child { font-size: 11px; font-weight: bold; margin: 0; color: #0B4F63; }
        .grid5 { width: 100%; border-collapse: separate; border-spacing: 5px; margin-bottom: 8px; }
    </style>
</head>
<body>
    @include('koperasi.laporan.pdf._header')

    <h1 style="font-size: 15px; color: #0B4F63; text-align: center; margin: 12px 0 4px;">LAPORAN PENJUALAN KOPERASI CAHAYA</h1>
    <p style="text-align: center; font-size: 9px; color: #666; margin: 0 0 10px;">Periode {{ $periodeLabel }}</p>

    <table class="grid5">
        <tr>
            <td class="box"><p>Jumlah Transaksi</p><p>{{ $jumlahTransaksi }}</p></td>
            <td class="box"><p>Total Tagihan</p><p>{{ rupiah($totalPenjualan) }}</p></td>
            <td class="box"><p>Total Dibayar</p><p>{{ rupiah($totalDibayar) }}</p></td>
            <td class="box"><p>Total Sisa</p><p>{{ rupiah($totalSisa) }}</p></td>
            <td class="box"><p>Total Fee</p><p>{{ rupiah($totalFee) }}</p></td>
        </tr>
    </table>

    <table class="data">
        <thead>
            <tr><th>Tanggal</th><th>Invoice</th><th>Unit</th><th>Customer</th><th>Total</th><th>Dibayar</th><th>Sisa</th><th>Status</th><th>Model Usaha</th></tr>
        </thead>
        <tbody>
            @forelse ($rows as $r)
                <tr>
                    <td>{{ $r['tanggal'] }}</td><td>{{ $r['invoice'] }}</td><td>{{ $r['unit'] }}</td><td>{{ $r['customer'] }}</td>
                    <td>{{ rupiah($r['total']) }}</td>
                    <td>{{ rupiah($r['dibayar']) }}</td>
                    <td>{{ rupiah($r['sisa']) }}</td>
                    <td>{{ $r['status'] }}</td><td>{{ $r['model_usaha'] }}</td>
                </tr>
            @empty
                <tr><td colspan="9" style="text-align:center; color:#999; font-style: italic;">Belum ada data pada periode ini.</td></tr>
            @endforelse
            @if ($rows->isNotEmpty())
                <tr class="total">
                    <td colspan="4">TOTAL</td>
                    <td>{{ rupiah($totalPenjualan) }}</td>
                    <td>{{ rupiah($totalDibayar) }}</td>
                    <td>{{ rupiah($totalSisa) }}</td>
                    <td colspan="2"></td>
                </tr>
            @endif
        </tbody>
    </table>

    @include('koperasi.laporan.pdf._footer')
</body>
</html>
