<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: sans-serif; font-size: 10px; color: #222; }
        table.data { width: 100%; border-collapse: collapse; margin-top: 6px; }
        table.data th, table.data td { border: 1px solid #ccc; padding: 5px 6px; text-align: left; }
        table.data th { background: #0B4F63; color: #fff; font-size: 9px; }
        table.data td { font-size: 9px; }
        table.data tr:nth-child(even) { background: #f7f7f7; }
        .ringkasan { margin-top: 14px; font-size: 10px; }
        .ringkasan td { padding: 4px 10px 4px 0; }
    </style>
</head>
<body>
    @include('laporan.pdf._header')

    <table class="data">
        <thead>
            <tr>
                <th>No</th>
                <th>Peserta</th>
                <th>Usaha</th>
                <th>Periode</th>
                <th>Omzet</th>
                <th>Catatan</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $i => $r)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $r->participantTermasukArsip?->nama_peserta ?? '-' }}</td>
                    <td>{{ $r->participantTermasukArsip?->nama_usaha ?? '-' }}</td>
                    <td>{{ $r->periodeLabel() }}</td>
                    <td>{{ rupiah($r->omzet) }}</td>
                    <td>{{ $r->catatan ?: '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="6" style="text-align: center; color: #888;">Belum ada data.</td></tr>
            @endforelse
        </tbody>
    </table>

    <table class="ringkasan">
        <tr>
            <td><strong>Total Omzet:</strong></td>
            <td>{{ rupiah($totalOmzet) }}</td>
        </tr>
        <tr>
            <td><strong>Rata-rata Omzet:</strong></td>
            <td>{{ $rataRata !== null ? rupiah($rataRata) : '—' }}</td>
        </tr>
    </table>
</body>
</html>
