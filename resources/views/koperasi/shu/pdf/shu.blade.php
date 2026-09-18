<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: sans-serif; font-size: 10px; color: #222; }
        h3 { font-size: 12px; color: #0B4F63; margin: 16px 0 4px 0; }
        p.sub { font-size: 8.5px; color: #888; margin: 0 0 6px 0; }
        table.data { width: 100%; border-collapse: collapse; margin-top: 4px; }
        table.data th, table.data td { border: 1px solid #ccc; padding: 4px 6px; text-align: left; }
        table.data th { background: #0B4F63; color: #fff; font-size: 8.5px; }
        table.data td { font-size: 8.5px; }
        table.data tr:nth-child(even) { background: #f7f7f7; }
        td.num, th.num { text-align: right; }
        .kotak { border: 1px solid #ccc; padding: 8px 10px; margin-top: 6px; font-size: 9px; }
        .kotak-tinjau { border: 1px solid #FEB900; background: #fffaf0; padding: 8px 10px; margin-top: 6px; }
        ul { margin: 4px 0 0 14px; padding: 0; }
        li { margin-bottom: 3px; font-size: 8.5px; }
    </style>
</head>
<body>
    @include('koperasi.laporan.pdf._header')

    <h3 style="margin-top: 14px;">Laporan Pembagian Sisa Hasil Usaha</h3>

    <table class="data">
        <tr><th style="width: 28%;">Nama Koperasi</th><td>{{ $setting->nama }}</td></tr>
        <tr><th>Tahun Buku</th><td>{{ $periode->tahun }}</td></tr>
        <tr><th>Tanggal Penetapan</th><td>{{ $periode->tanggal_penetapan->translatedFormat('d F Y') }}</td></tr>
        <tr><th>Sumber Angka SHU</th><td>{{ \App\Models\ShuPeriod::SUMBER_OPTIONS[$periode->sumber] ?? $periode->sumber }}</td></tr>
        <tr><th>Total SHU</th><td class="num" style="text-align: left;"><strong>{{ rupiah($periode->total_shu) }}</strong></td></tr>
        <tr><th>Status Penetapan</th><td>
            {{ $periode->status }}
            @if ($periode->terkunci())
                — terkunci, tidak dapat diubah lagi
            @endif
        </td></tr>
        <tr><th>Ditetapkan Oleh</th><td>{{ $periode->penetap->name ?? 'Tidak tercatat' }}</td></tr>
    </table>

    {{--
        Peringatan ikut TERCETAK, bukan hanya muncul di layar.

        Dokumen ini dibawa ke Rapat Anggota. Kalau total persentasenya bukan
        100%, atau sebuah komponen tidak bisa dibagikan karena dasarnya kosong,
        yang mengesahkan berhak melihatnya di kertas yang sedang ia tandatangani
        — bukan hanya di layar orang yang menyusunnya.
    --}}
    @if (! empty($peringatan))
        <div class="kotak-tinjau">
            <p style="margin: 0 0 4px; font-size: 9px; font-weight: bold; color: #0B4F63;">Perlu diperhatikan</p>
            <ul>
                @foreach ($peringatan as $w)
                    <li>{{ $w }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- ================= KOMPONEN PEMBAGIAN ================= --}}
    <h3>Dasar Pembagian</h3>
    <p class="sub">
        Persentase berikut adalah yang BERLAKU pada penetapan ini dan dibekukan bersamanya — perubahan
        aturan pada tahun berikutnya tidak mengubah dokumen ini.
    </p>
    <table class="data">
        <thead>
            <tr>
                <th>No</th><th>Komponen</th><th class="num">Persen</th>
                <th class="num">Nominal</th><th>Dibagikan ke</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($periode->komponen ?? [] as $i => $k)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $k['nama'] }}</td>
                    <td class="num">{{ rtrim(rtrim(number_format($k['persen'], 2, ',', '.'), '0'), ',') }}%</td>
                    <td class="num">{{ rupiah($periode->nominalKomponenKe($i)) }}</td>
                    <td>
                        @if (! empty($k['ke_anggota']))
                            Anggota — menurut {{ $k['dasar'] === 'simpanan' ? 'simpanan pokok & wajib' : 'nilai transaksi' }}
                        @else
                            Dana koperasi
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" style="text-align: center; color: #888;">Belum ada komponen ditetapkan.</td></tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <th colspan="2" style="text-align: right;">TOTAL</th>
                <th class="num">{{ rtrim(rtrim(number_format($periode->totalPersen(), 2, ',', '.'), '0'), ',') }}%</th>
                <th class="num">{{ rupiah($periode->total_shu) }}</th>
                <th></th>
            </tr>
        </tfoot>
    </table>

    {{-- ================= ALOKASI ANGGOTA ================= --}}
    <h3>Pembagian per Anggota</h3>
    <p class="sub">
        Dasar simpanan dan transaksi DIBEKUKAN pada saat penetapan — bukan dihitung ulang saat dokumen
        ini dicetak. Tanpa itu, pembagian yang sudah disahkan Rapat Anggota akan bergeser sendiri begitu
        ada simpanan atau transaksi baru masuk. Simpanan sukarela sengaja tidak menjadi dasar jasa modal
        karena sifatnya titipan yang dapat ditarik sewaktu-waktu, bukan modal yang disertakan.
    </p>
    <table class="data">
        <thead>
            <tr>
                <th>No</th><th>No. Anggota</th><th>Nama Anggota</th>
                <th class="num">Dasar Simpanan</th><th class="num">Dasar Transaksi</th>
                <th class="num">Jasa Modal</th><th class="num">Jasa Usaha</th><th class="num">Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($periode->allocations as $i => $a)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $a->member->nomor_anggota ?? '-' }}</td>
                    <td>{{ $a->member->nama ?? '-' }}</td>
                    <td class="num">{{ rupiah($a->dasar_simpanan) }}</td>
                    <td class="num">{{ rupiah($a->dasar_transaksi) }}</td>
                    <td class="num">{{ rupiah($a->jasa_modal) }}</td>
                    <td class="num">{{ rupiah($a->jasa_usaha) }}</td>
                    <td class="num"><strong>{{ rupiah($a->total) }}</strong></td>
                </tr>
            @empty
                <tr><td colspan="8" style="text-align: center; color: #888;">
                    Belum ada anggota yang mendapat alokasi pada penetapan ini.
                </td></tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <th colspan="5" style="text-align: right;">TOTAL DIBAGIKAN KE ANGGOTA</th>
                <th class="num">{{ rupiah($periode->allocations->sum('jasa_modal')) }}</th>
                <th class="num">{{ rupiah($periode->allocations->sum('jasa_usaha')) }}</th>
                <th class="num">{{ rupiah($periode->totalKeAnggota()) }}</th>
            </tr>
        </tfoot>
    </table>

    <div class="kotak">
        <strong>Ringkasan:</strong>
        dari total SHU {{ rupiah($periode->total_shu) }},
        sebesar {{ rupiah($periode->totalKeAnggota()) }} dibagikan kepada
        {{ $periode->allocations->count() }} anggota, dan sisanya dialokasikan ke dana koperasi
        sesuai komponen di atas.
    </div>

    @if ($periode->catatan)
        <div class="kotak">
            <strong>Catatan:</strong> {{ $periode->catatan }}
        </div>
    @endif

    @include('koperasi.laporan.pdf._footer')
</body>
</html>
