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
                <th>Pendamping</th>
                <th>Status</th>
                <th>Binaan</th>
                <th>Aktif</th>
                <th>Pendampingan</th>
                <th>Kunjungan</th>
                <th>Konsultasi</th>
                <th>Evaluasi</th>
                <th>Follow Up</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($baris as $b)
                <tr>
                    <td>{{ $b['pendamping']->nama }}</td>
                    <td>{{ $b['pendamping']->status }}</td>
                    <td>{{ $b['total_binaan'] }}</td>
                    <td>{{ $b['binaan_aktif'] }}</td>
                    <td>{{ $b['pendampingan'] }}</td>
                    <td>{{ $b['kunjungan'] }}</td>
                    <td>{{ $b['konsultasi'] }}</td>
                    <td>{{ $b['evaluasi'] }}</td>
                    <td>{{ $b['follow_up'] }}</td>
                </tr>
            @empty
                <tr><td colspan="9" style="text-align:center; font-style:italic;">Tidak ada data.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="ringkasan">
        <strong>Ringkasan:</strong>
        {{ $ringkasan['jumlah_pendamping'] }} pendamping &middot;
        {{ $ringkasan['total_binaan'] }} binaan &middot;
        {{ $ringkasan['total_aktivitas'] }} aktivitas pendampingan &middot;
        {{ $ringkasan['tanpa_aktivitas'] }} pendamping tanpa aktivitas pada periode ini.
    </div>
</body>
</html>
