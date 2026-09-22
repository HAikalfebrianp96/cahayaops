<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: sans-serif; font-size: 11px; color: #222; }
        table.data { width: 100%; border-collapse: collapse; margin-top: 8px; }
        table.data th, table.data td { border: 1px solid #ddd; padding: 5px 8px; text-align: left; font-size: 9.5px; }
        table.data th { background: #0B4F63; color: #fff; }
        .box { border: 1px solid #ddd; border-radius: 4px; padding: 8px 10px; text-align: center; }
        .box p:first-child { font-size: 8px; color: #888; margin: 0 0 2px; }
        .box p:last-child { font-size: 12px; font-weight: bold; margin: 0; color: #0B4F63; }
        .grid4 { width: 100%; border-collapse: separate; border-spacing: 6px; }
        .grid4 td { width: 25%; }
        h2 { font-size: 15px; color: #0B4F63; margin: 18px 0 6px; }
    </style>
</head>
<body>
    @include('koperasi.laporan.pdf._header')

    <h1 style="font-size: 16px; color: #0B4F63; text-align: center; margin: 14px 0 4px;">LAPORAN RINGKASAN KOPERASI CAHAYA</h1>
    <p style="text-align: center; font-size: 10px; color: #666; margin: 0 0 14px;">Periode {{ $periodeLabel }}</p>

    <table class="grid4">
        <tr>
            <td class="box"><p>Total Anggota</p><p>{{ $totalAnggota }}</p></td>
            <td class="box"><p>Anggota Aktif</p><p>{{ $anggotaAktif }}</p></td>
            <td class="box"><p>Unit Usaha</p><p>{{ $totalUnit }}</p></td>
            <td class="box"><p>Laba Bersih</p><p style="color: {{ $lr['total']['laba_bersih'] >= 0 ? '#157347' : '#c0392b' }};">{{ rupiah($lr['total']['laba_bersih']) }}</p></td>
        </tr>
    </table>

    @if (! $lr['ada_transaksi'] && ! $lr['ada_pengeluaran'])
        <p style="color: #999; font-style: italic; margin-top: 16px;">Belum ada data pada periode ini.</p>
    @else
        <h2>Laba Rugi Koperasi</h2>
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
                <td class="box" colspan="2"><p>Margin</p><p>{{ $lr['total']['margin'] !== null ? number_format($lr['total']['margin'], 1) . '%' : '-' }}</p></td>
            </tr>
        </table>

        <h2>Performa Unit Usaha</h2>
        <table class="data">
            <thead><tr><th>Unit</th><th>Penjualan</th><th>Laba Bersih</th></tr></thead>
            <tbody>
                @foreach ($lr['per_unit'] as $u)
                    <tr>
                        <td>{{ $u['nama'] }}</td>
                        <td>{{ rupiah($u['penjualan']) }}</td>
                        <td style="color: {{ $u['laba_bersih'] >= 0 ? '#157347' : '#c0392b' }};">{{ rupiah($u['laba_bersih']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <h2>Dana Anggota &amp; Dana Sosial <span style="font-size: 9px; color: #999; font-weight: normal;">(bukan pendapatan usaha)</span></h2>
    <table class="grid4">
        <tr>
            <td class="box"><p>Simpanan Pokok</p><p>{{ rupiah($totalSimpananPokok) }}</p></td>
            <td class="box"><p>Simpanan Wajib</p><p>{{ rupiah($totalSimpananWajib) }}</p></td>
            <td class="box"><p>Saldo Dana Sukarela</p><p>{{ rupiah($saldoDanaSosial) }}</p></td>
            <td class="box"><p>Saldo Dana Hibah</p><p>{{ rupiah($saldoDanaHibah) }}</p></td>
        </tr>
    </table>

    @if (($simpananAnggotaTidakAktif ?? 0) > 0)
        {{-- Keterangan ini WAJIB ikut tercetak. Lembar inilah yang dibawa ke
             Rapat Anggota; kalau angkanya berdiri tanpa penjelasan, peserta
             rapat akan membacanya sebagai simpanan anggota yang masih ada. --}}
        <p style="font-size: 9px; color: #555; margin-top: 6px;">
            Termasuk <strong>{{ rupiah($simpananAnggotaTidakAktif) }}</strong> simpanan pokok/wajib milik anggota
            yang sudah diarsipkan. Dananya masih dipegang koperasi, tetapi pemiliknya tidak lagi terhitung
            sebagai anggota dan tidak menerima pembagian SHU.
        </p>
    @endif

    @include('koperasi.laporan.pdf._footer')
</body>
</html>
