<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: sans-serif; font-size: 11px; color: #222; }
        table.data { width: 100%; border-collapse: collapse; margin-top: 8px; }
        table.data th, table.data td { border: 1px solid #ddd; padding: 5px 8px; text-align: left; font-size: 9.5px; }
        table.data th { background: #0B4F63; color: #fff; }
        table.data tr.total td { font-weight: bold; background: #F9FBFC; }
        .box { border: 1px solid #ddd; border-radius: 4px; padding: 8px 10px; text-align: center; }
        .box p:first-child { font-size: 8px; color: #888; margin: 0 0 2px; }
        .box p:last-child { font-size: 12px; font-weight: bold; margin: 0; color: #0B4F63; }
        .grid4 { width: 100%; border-collapse: separate; border-spacing: 6px; margin-bottom: 10px; }
        .grid4 td { width: 25%; }
    </style>
</head>
<body>
    @include('koperasi.laporan.pdf._header')

    <h1 style="font-size: 16px; color: #0B4F63; text-align: center; margin: 14px 0 4px;">LAPORAN LABA RUGI KOPERASI CAHAYA</h1>
    <p style="text-align: center; font-size: 10px; color: #666; margin: 0 0 14px;">
        Periode {{ $periodeLabel }} @if($unitLabel) &middot; {{ $unitLabel }} @endif
    </p>

    @if (! $lr['ada_transaksi'] && ! $lr['ada_pengeluaran'])
        <p style="color: #999; font-style: italic;">Belum ada data pada periode ini.</p>
    @else
        <table class="grid4">
            <tr>
                <td class="box"><p>Penjualan</p><p>{{ rupiah($lr['total']['penjualan']) }}</p></td>
                <td class="box"><p>HPP</p><p>{{ rupiah($lr['total']['hpp']) }}</p></td>
                <td class="box"><p>Laba Kotor</p><p>{{ rupiah($lr['total']['laba_kotor']) }}</p></td>
                <td class="box"><p>Fee Koperasi</p><p>{{ rupiah($lr['total']['fee_koperasi']) }}</p></td>
            </tr>
            <tr>
                <td class="box"><p>Hak Mitra</p><p>{{ rupiah($lr['total']['hak_mitra']) }}</p></td>
                <td class="box"><p>Pengeluaran</p><p>{{ rupiah($lr['total']['pengeluaran_total']) }}</p></td>
                <td class="box" colspan="2" style="background: #F9FBFC;"><p>Laba Bersih</p><p style="color: {{ $lr['total']['laba_bersih'] >= 0 ? '#157347' : '#c0392b' }};">{{ rupiah($lr['total']['laba_bersih']) }} @if($lr['total']['margin'] !== null)<span style="font-size:8px; font-weight:normal; color:#999;">({{ number_format($lr['total']['margin'], 1) }}%)</span>@endif</p></td>
            </tr>
        </table>

        <table class="data">
            <thead>
                <tr><th>Unit</th><th>Penjualan</th><th>HPP</th><th>Fee</th><th>Pengeluaran</th><th>Laba Bersih</th></tr>
            </thead>
            <tbody>
                @foreach ($lr['per_unit'] as $u)
                    <tr>
                        <td>{{ $u['nama'] }}</td>
                        <td>{{ rupiah($u['penjualan']) }}</td>
                        <td>{{ rupiah($u['hpp']) }}</td>
                        <td>{{ $u['fee_koperasi'] > 0 ? 'Rp' . number_format($u['fee_koperasi'], 0, ',', '.') : '-' }}</td>
                        <td>{{ rupiah($u['pengeluaran']) }}</td>
                        <td style="color: {{ $u['laba_bersih'] >= 0 ? '#157347' : '#c0392b' }};">{{ rupiah($u['laba_bersih']) }}</td>
                    </tr>
                @endforeach
                <tr class="total">
                    <td>TOTAL KOPERASI</td>
                    <td>{{ rupiah($lr['total']['penjualan']) }}</td>
                    <td>{{ rupiah($lr['total']['hpp']) }}</td>
                    <td>{{ rupiah($lr['total']['fee_koperasi']) }}</td>
                    <td>{{ rupiah($lr['total']['pengeluaran_total']) }}</td>
                    <td style="color: {{ $lr['total']['laba_bersih'] >= 0 ? '#157347' : '#c0392b' }};">{{ rupiah($lr['total']['laba_bersih']) }}</td>
                </tr>
            </tbody>
        </table>
    @endif

    @include('koperasi.laporan.pdf._footer')
</body>
</html>
