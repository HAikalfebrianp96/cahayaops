<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: sans-serif; font-size: 11px; color: #222; }
        table.ringkasan { width: 100%; border-collapse: collapse; margin-top: 10px; }
        table.ringkasan td { border: 1px solid #ccc; padding: 10px; width: 33%; }
        table.ringkasan .label { font-size: 9px; color: #666; display: block; margin-bottom: 4px; }
        table.ringkasan .angka { font-size: 14px; font-weight: bold; color: #0B4F63; }
        table.data { width: 100%; border-collapse: collapse; margin-top: 20px; }
        table.data th, table.data td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; }
        table.data th { background: #0B4F63; color: #fff; font-size: 10px; }
        table.data td { font-size: 10px; }
    </style>
</head>
<body>
    @include('laporan.pdf._header')

    <table class="ringkasan">
        <tr>
            <td><span class="label">Total Peserta</span><span class="angka">{{ $totalPeserta }}</span></td>
            <td><span class="label">Total Pendamping</span><span class="angka">{{ $totalPendamping }}</span></td>
            <td><span class="label">Total Omzet</span><span class="angka">{{ rupiah($totalOmzet) }}</span></td>
        </tr>
        <tr>
            <td><span class="label">Rata-rata Omzet</span><span class="angka">{{ $rataRataOmzet !== null ? rupiah($rataRataOmzet) : '—' }}</span></td>
            <td><span class="label">Total Kegiatan Pendampingan</span><span class="angka">{{ $totalPendampingan }}</span></td>
            <td><span class="label">Total Pelatihan</span><span class="angka">{{ $totalPelatihan }}</span></td>
        </tr>
    </table>

    <h3 style="font-size: 12px; color: #0B4F63; margin-top: 20px;">Legalitas & Sertifikasi — Ringkasan per Jenis</h3>
    <table class="data">
        <thead>
            <tr><th>Nama Legalitas/Sertifikasi</th><th>Jumlah</th></tr>
        </thead>
        <tbody>
            @forelse ($legalitasPerJenis as $item)
                <tr><td>{{ $item->jenis_dokumen }}</td><td>{{ $item->jumlah }}</td></tr>
            @empty
                <tr><td colspan="2" style="text-align: center; color: #888;">Belum ada data.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
