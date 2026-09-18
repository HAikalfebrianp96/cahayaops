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
                <th>Pendamping</th>
                <th>Nama Legalitas</th>
                <th>Nomor</th>
                <th>Status</th>
                <th>Terbit</th>
                <th>Berlaku</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $i => $r)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $r->participantTermasukArsip?->nama_peserta ?? '-' }}</td>
                    <td>{{ $r->participantTermasukArsip?->nama_usaha ?? '-' }}</td>
                    <td>{{ $r->participantTermasukArsip?->pendamping->nama ?? '-' }}</td>
                    <td>{{ $r->jenis_dokumen }}</td>
                    <td>{{ $r->nomor_dokumen ?: '-' }}</td>
                    <td>{{ $r->status }}</td>
                    <td>{{ optional($r->tanggal_terbit)->format('d-m-Y') ?: '-' }}</td>
                    <td>{{ optional($r->tanggal_berlaku)->format('d-m-Y') ?: '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="9" style="text-align: center; color: #888;">Belum ada data.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
