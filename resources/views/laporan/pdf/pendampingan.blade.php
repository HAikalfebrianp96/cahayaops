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
    </style>
</head>
<body>
    @include('laporan.pdf._header')

    <table class="data">
        <thead>
            <tr>
                <th>Tanggal</th>
                <th>Peserta</th>
                <th>Pendamping</th>
                <th>Jenis</th>
                <th>Materi</th>
                <th>Kendala</th>
                <th>Tindak Lanjut</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $r)
                <tr>
                    <td>{{ $r->tanggal->format('d-m-Y') }}</td>
                    <td>{{ $r->participantTermasukArsip?->nama_peserta ?? '-' }}</td>
                    <td>{{ $r->pendamping->nama ?? '-' }}</td>
                    <td>{{ $r->jenis_kegiatan }}</td>
                    <td>{{ $r->materi }}</td>
                    <td>{{ $r->kendala ?: '-' }}</td>
                    <td>{{ $r->tindak_lanjut ?: '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="7" style="text-align: center; color: #888;">Belum ada data.</td></tr>
            @endforelse
        </tbody>
    </table>

    <p class="ringkasan"><strong>Total Kegiatan Pendampingan:</strong> {{ $totalKegiatan }}</p>
</body>
</html>
