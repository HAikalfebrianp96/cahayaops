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
    </style>
</head>
<body>
    @include('laporan.pdf._header')

    <table class="data">
        <thead>
            <tr>
                <th>No</th>
                <th>Nama Peserta</th>
                <th>Nama Usaha</th>
                <th>Kategori</th>
                <th>Pendamping</th>
                <th>Status</th>
                <th>Tanggal Masuk</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $i => $p)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $p->nama_peserta }}</td>
                    <td>{{ $p->nama_usaha }}</td>
                    <td>{{ $p->kategori_usaha }}</td>
                    <td>{{ $p->pendamping->nama ?? '-' }}</td>
                    <td>{{ $p->status }}</td>
                    <td>{{ $p->tanggal_mulai_program->format('d-m-Y') }}</td>
                </tr>
            @empty
                <tr><td colspan="7" style="text-align: center; color: #888;">Belum ada data.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
