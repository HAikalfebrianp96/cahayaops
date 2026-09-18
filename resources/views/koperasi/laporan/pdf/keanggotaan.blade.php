<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: sans-serif; font-size: 10px; color: #222; }
        table.data { width: 100%; border-collapse: collapse; margin-top: 8px; }
        table.data th, table.data td { border: 1px solid #ddd; padding: 4px 6px; text-align: left; font-size: 9px; }
        table.data th { background: #0B4F63; color: #fff; }
        .box { border: 1px solid #ddd; border-radius: 4px; padding: 6px 8px; text-align: center; }
        .box p:first-child { font-size: 8px; color: #888; margin: 0 0 2px; }
        .box p:last-child { font-size: 12px; font-weight: bold; margin: 0; color: #0B4F63; }
        .grid3 { width: 100%; border-collapse: separate; border-spacing: 6px; margin-bottom: 10px; }
    </style>
</head>
<body>
    @include('koperasi.laporan.pdf._header')

    <h1 style="font-size: 15px; color: #0B4F63; text-align: center; margin: 12px 0 4px;">LAPORAN KEANGGOTAAN KOPERASI CAHAYA</h1>
    <p style="text-align: center; font-size: 9px; color: #666; margin: 0 0 10px;">{{ $periodeLabel }}</p>

    <table class="grid3">
        <tr>
            <td class="box"><p>Total Anggota</p><p>{{ $totalAnggota }}</p></td>
            <td class="box"><p>Aktif</p><p style="color:#157347;">{{ $anggotaAktif }}</p></td>
            <td class="box"><p>Tidak Aktif</p><p style="color:#666;">{{ $anggotaTidakAktif }}</p></td>
        </tr>
    </table>

    <table class="data">
        <thead><tr><th>Nama</th><th>Nomor Anggota</th><th>Tanggal Bergabung</th><th>Status</th></tr></thead>
        <tbody>
            @forelse ($anggota as $a)
                <tr>
                    <td>{{ $a->namaTampil() }}</td>
                    <td>{{ $a->nomor_anggota ?? '-' }}</td>
                    <td>{{ optional($a->tanggal_bergabung)->format('d-m-Y') ?? '-' }}</td>
                    <td>{{ $a->status }}</td>
                </tr>
            @empty
                <tr><td colspan="4" style="text-align:center; color:#999; font-style:italic;">Belum ada anggota.</td></tr>
            @endforelse
        </tbody>
    </table>

    @include('koperasi.laporan.pdf._footer')
</body>
</html>
