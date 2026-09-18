<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: sans-serif; font-size: 10px; color: #222; }
        h3 { font-size: 12px; color: #0B4F63; margin: 16px 0 4px 0; }
        p.sub { font-size: 8.5px; color: #888; margin: 0 0 6px 0; }
        table.data { width: 100%; border-collapse: collapse; margin-top: 4px; }
        table.data th, table.data td { border: 1px solid #ccc; padding: 4px 6px; text-align: left; vertical-align: top; }
        table.data th { background: #0B4F63; color: #fff; font-size: 8.5px; }
        table.data td { font-size: 8.5px; }
        table.data tr:nth-child(even) { background: #f7f7f7; }
        td.num, th.num { text-align: right; }
        .kotak { border: 1px solid #ccc; padding: 8px 10px; margin-top: 6px; }
        .kotak-peringatan { border: 1px solid #C0392B; background: #fdf3f2; padding: 10px 12px; margin-top: 6px; }
        .kotak-tinjau { border: 1px solid #FEB900; background: #fffaf0; padding: 8px 10px; margin-top: 6px; }
        .rasio { font-size: 20px; font-weight: bold; color: #0B4F63; }
        ul { margin: 4px 0 0 14px; padding: 0; }
        li { margin-bottom: 3px; }
    </style>
</head>
<body>
    @include('laporan.pdf._header')

    {{-- ================= IDENTITAS PROGRAM ================= --}}
    <table class="data">
        <tr><th style="width: 26%;">Nama Program</th><td>{{ $program->nama }}</td></tr>
        <tr><th>Periode Program</th><td>{{ $program->periodeLabel() }}</td></tr>
        <tr><th>Cakupan Peserta</th><td>
            {{ $program->pendamping ? 'Binaan ' . $program->pendamping->nama : 'Seluruh peserta pada periode ini' }}
        </td></tr>
        <tr><th>Status Program</th><td>{{ $program->status }}</td></tr>
        <tr><th>Status Data SROI</th><td><strong>{{ $kesiapan['status'] }}</strong></td></tr>
    </table>

    @if ($program->deskripsi)
        <div class="kotak">{{ $program->deskripsi }}</div>
    @endif

    {{--
        ==================================================================
        PENJAGA PALING PENTING DI BERKAS INI.

        Kalau datanya belum lengkap, PDF ini TIDAK menampilkan rasio apa pun —
        bukan rasio bertanda bintang, bukan estimasi, bukan angka "sementara".
        Dokumen ini dicetak untuk dikirim ke pemberi dana; sebuah angka yang
        tercetak di atas kop resmi akan dibaca sebagai angka resmi, berapa pun
        banyaknya catatan kaki di sekelilingnya. Yang dicetak justru daftar
        apa yang masih kurang, supaya dokumennya tetap berguna: ia menjadi
        daftar kerja, bukan laporan palsu.
        ==================================================================
    --}}
    @if (! $hasil)
        <div class="kotak-peringatan">
            <p style="margin: 0 0 4px; font-size: 12px; font-weight: bold; color: #C0392B;">
                SROI BELUM DAPAT DITERBITKAN — {{ strtoupper($kesiapan['status']) }}
            </p>
            <p style="margin: 0 0 6px; font-size: 9px;">
                Rasio SROI sengaja TIDAK dicantumkan dalam dokumen ini karena data wajibnya belum
                lengkap. Menerbitkan angka di atas data yang belum cukup akan menyesatkan pihak yang
                memakainya untuk mengambil keputusan pendanaan.
            </p>

            @if (! empty($kesiapan['kurang']))
                <p style="margin: 6px 0 0; font-size: 9px; font-weight: bold;">Yang masih kurang:</p>
                <ul style="font-size: 9px;">
                    @foreach ($kesiapan['kurang'] as $k)
                        <li>{{ $k }}</li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endif

    {{-- ================= INVESTMENT ================= --}}
    <h3>Investment — Sumber Daya yang Masuk</h3>
    <p class="sub">Penyebut rasio SROI. Termasuk kontribusi in-kind (tenaga, tempat, barang).</p>
    <table class="data">
        <thead>
            <tr>
                <th>No</th><th>Kategori</th><th>Uraian</th><th>Jenis</th>
                <th>Tanggal</th><th class="num">Nominal</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($program->investments as $i => $inv)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $inv->kategori }}</td>
                    <td>{{ $inv->uraian }}{{ $inv->sumber_dana ? ' (' . $inv->sumber_dana . ')' : '' }}</td>
                    <td>{{ $inv->jenis }}</td>
                    <td>{{ $inv->tanggal->format('d/m/Y') }}</td>
                    <td class="num">{{ rupiah($inv->nominal) }}</td>
                </tr>
            @empty
                <tr><td colspan="6" style="text-align: center; color: #888;">Belum ada investasi tercatat.</td></tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <th colspan="5" style="text-align: right;">TOTAL INVESTASI</th>
                <th class="num">{{ rupiah($kesiapan['total_investasi']) }}</th>
            </tr>
        </tfoot>
    </table>

    {{-- ================= OUTPUT ================= --}}
    <h3>Output — Kegiatan &amp; Jangkauan</h3>
    <p class="sub">Diturunkan dari data operasional yang sudah tercatat, bukan diinput ulang.</p>
    <table class="data">
        @foreach ($rantai['input'] as $label => $nilai)
            <tr><th style="width: 40%;">{{ $label }}</th><td>{{ $nilai }}</td></tr>
        @endforeach
        @foreach ($rantai['activity'] as $label => $nilai)
            <tr><th>{{ $label }}</th><td>{{ $nilai }}</td></tr>
        @endforeach
        @foreach ($rantai['output'] as $label => $nilai)
            <tr><th>{{ $label }}</th><td>{{ $nilai }}</td></tr>
        @endforeach
    </table>

    {{-- ================= OUTCOME ================= --}}
    <h3>Outcome — Perubahan Terukur pada Peserta</h3>
    <p class="sub">
        Kolom &ldquo;peserta terukur&rdquo; WAJIB dibaca bersama totalnya: total perubahan dari 3 peserta
        dan dari 300 peserta adalah dua klaim yang sangat berbeda.
    </p>
    <table class="data">
        <thead>
            <tr>
                <th>Indikator</th>
                <th class="num">Peserta Terukur</th>
                <th class="num">Membaik</th>
                <th class="num">Total Perubahan</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rantai['outcome'] as $ind)
                <tr>
                    <td>{{ $ind['label'] }}</td>
                    <td class="num">{{ $ind['jumlah_peserta_terukur'] }}</td>
                    <td class="num">{{ $ind['jumlah_membaik'] }}</td>
                    <td class="num">
                        {{ $ind['format'] === 'rupiah'
                            ? rupiah((int) $ind['total_selisih'])
                            : number_format((int) $ind['total_selisih'], 0, ',', '.') . ' ' . $ind['satuan'] }}
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" style="text-align: center; color: #888;">
                    Belum ada peserta dengan baseline sekaligus endline/monitoring.
                </td></tr>
            @endforelse
        </tbody>
    </table>

    {{-- ================= PROXY & ADJUSTMENT ================= --}}
    <h3>Financial Proxy &amp; Impact Adjustment</h3>
    <p class="sub">
        Deadweight = perubahan yang tetap terjadi tanpa program. Attribution = bagian yang benar-benar
        milik program ini. Displacement = manfaat yang hanya berpindah dari pihak lain.
        Drop-off = peluruhan manfaat per tahun setelah tahun pertama.
        <strong>n/r</strong> = dinyatakan tidak relevan untuk outcome tersebut;
        <strong>&mdash;</strong> = belum ditentukan.
    </p>
    <table class="data">
        <thead>
            <tr>
                <th>Outcome</th>
                <th>Proxy Finansial (sumber, tahun)</th>
                <th class="num">Dead<br>weight</th>
                <th class="num">Attri<br>bution</th>
                <th class="num">Displace<br>ment</th>
                <th class="num">Drop<br>off</th>
                <th class="num">Durasi</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($program->outcomes as $o)
                <tr>
                    <td>{{ $o->nama }}<br><span style="color: #888;">{{ $o->indikatorLabel() ?? 'Manual' }}</span></td>
                    <td>
                        @if ($o->proxy)
                            {{ $o->proxy->nama }}<br>
                            <span style="color: #888;">{{ $o->proxy->ringkas() }}</span>
                        @else
                            <span style="color: #C0392B;">Belum tersedia</span>
                        @endif
                    </td>
                    <td class="num">{{ (float) $o->deadweight_persen }}%</td>
                    <td class="num">{{ (float) $o->attribution_persen }}%</td>
                    {{-- "Tidak relevan" dan "0%" adalah dua pernyataan berbeda.
                         Dokumen ini dibaca pemberi dana; menyamakan keduanya
                         berarti mengaku sudah memeriksa sesuatu yang mungkin
                         belum pernah dipikirkan. --}}
                    <td class="num">
                        @if ($o->displacementTidakRelevan())
                            n/r
                        @elseif ($o->displacementBelumDitentukan())
                            &mdash;
                        @else
                            {{ (float) $o->displacement_persen }}%
                        @endif
                    </td>
                    <td class="num">
                        {{ $o->dropOffRelevan() ? (float) $o->drop_off_persen . '%' : 'n/r' }}
                    </td>
                    <td class="num">{{ $o->durasi_tahun }} th</td>
                </tr>
            @empty
                <tr><td colspan="7" style="text-align: center; color: #888;">Belum ada outcome didefinisikan.</td></tr>
            @endforelse
        </tbody>
    </table>

    {{-- ================= HASIL, HANYA KALAU SUDAH DIHITUNG ================= --}}
    @if ($hasil)
        <h3>Impact — Nilai Sosial per Outcome</h3>
        <p class="sub">
            Rumus: nilai kotor &times; (1 &minus; deadweight) &times; attribution &times; (1 &minus; displacement);
            tahun ke-n dikalikan (1 &minus; drop-off)^(n&minus;1). Nilai proxy yang dipakai
            DIBEKUKAN pada saat perhitungan, sehingga dokumen ini tetap dapat dijelaskan
            meski master proxy diperbarui kemudian.
        </p>
        <table class="data">
            <thead>
                <tr>
                    <th>Outcome</th>
                    <th class="num">Perubahan</th>
                    <th class="num">Nilai Kotor</th>
                    <th class="num">Stlh Deadweight</th>
                    <th class="num">Stlh Attribution</th>
                    <th class="num">Stlh Displacement</th>
                    <th class="num">Nilai Bersih</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($hasil->rincian['outcomes'] ?? [] as $o)
                    <tr>
                        <td>{{ $o['nama'] }}</td>
                        <td class="num">{{ number_format($o['jumlah_perubahan'], 0, ',', '.') }}</td>
                        <td class="num">{{ rupiah($o['langkah']['kotor']) }}</td>
                        <td class="num">{{ rupiah($o['langkah']['setelah_deadweight']) }}</td>
                        <td class="num">{{ rupiah($o['langkah']['setelah_attribution']) }}</td>
                        <td class="num">{{ rupiah($o['langkah']['setelah_displacement']) }}</td>
                        <td class="num"><strong>{{ rupiah($o['nilai_bersih']) }}</strong></td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <h3>SROI Ratio</h3>
        <table class="data">
            <tr>
                <th style="width: 34%;">Gross Social Value (total nilai dampak)</th>
                <td class="num">{{ rupiah($hasil->total_nilai_dampak) }}</td>
            </tr>
            <tr>
                <th>Investment</th>
                <td class="num">{{ rupiah($hasil->total_investasi) }}</td>
            </tr>
            <tr>
                <th>Net Social Value (dampak &minus; investasi)</th>
                <td class="num">{{ rupiah($hasil->nilai_sosial_bersih) }}</td>
            </tr>
            <tr>
                <th>SROI Ratio</th>
                <td class="num">
                    <span class="rasio">{{ $hasil->rasio === null ? '—' : number_format($hasil->rasio, 2, ',', '.') }}</span>
                    <br><span style="font-size: 8.5px; color: #666;">{{ $hasil->rasioKalimat() }}</span>
                </td>
            </tr>
            <tr>
                <th>Status Perhitungan</th>
                <td>
                    {{ $hasil->status }}
                    @if ($hasil->sudahDisetujui())
                        — disetujui {{ $hasil->penyetuju->name ?? '-' }}, {{ $hasil->disetujui_at->format('d M Y') }}
                    @endif
                </td>
            </tr>
            <tr>
                <th>Dihitung</th>
                <td>{{ $hasil->dihitung_at->format('d F Y H:i') }} WIB oleh {{ $hasil->penghitung->name ?? 'Sistem' }}</td>
            </tr>
        </table>

        {{-- ================= SENSITIVITAS ================= --}}
        @if ($hasil->sensitivitas)
            <h3>Sensitivity Analysis</h3>
            <p class="sub">
                Bukan tiga angka untuk dipilih yang paling menguntungkan, melainkan ukuran seberapa
                rapuh rasio ini terhadap asumsi yang dipilih sendiri. Rasio yang berubah drastis hanya
                karena attribution digeser 20 poin tidak boleh dilaporkan sebagai angka tunggal.
                Nilai proxy TIDAK ikut digeser — angkanya berasal dari sumber luar.
            </p>
            <table class="data">
                <thead>
                    <tr>
                        <th>Skenario</th>
                        <th class="num">Gross Social Value</th>
                        <th class="num">Investment</th>
                        <th class="num">Net Social Value</th>
                        <th class="num">SROI Ratio</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($hasil->sensitivitas as $s)
                        <tr>
                            <td>{{ $s['label'] }}</td>
                            <td class="num">{{ rupiah($s['total_nilai_dampak']) }}</td>
                            <td class="num">{{ rupiah($s['total_investasi'] ?? $hasil->total_investasi) }}</td>
                            <td class="num">{{ rupiah($s['nilai_sosial_bersih']) }}</td>
                            <td class="num">{{ $s['rasio'] === null ? '—' : number_format($s['rasio'], 2, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        {{-- ================= EVIDENCE ================= --}}
        <h3>Evidence &amp; Sumber Data</h3>
        <table class="data">
            <thead>
                <tr><th style="width: 30%;">Outcome</th><th>Bukti / Sumber Angka</th></tr>
            </thead>
            <tbody>
                @foreach ($hasil->rincian['outcomes'] ?? [] as $o)
                    <tr>
                        <td>{{ $o['nama'] }}</td>
                        <td>
                            {{ $o['bukti'] ?: 'Diturunkan otomatis dari baseline & endline peserta yang dicatat pendamping.' }}
                            @if (! empty($o['penyesuai']['attribution_catatan']))
                                <br><span style="color: #888;">Kontribusi pihak lain: {{ $o['penyesuai']['attribution_catatan'] }}</span>
                            @endif
                            @if (! empty($o['penyesuai']['displacement_catatan']))
                                <br><span style="color: #888;">Displacement: {{ $o['penyesuai']['displacement_catatan'] }}</span>
                            @endif
                            @if (! empty($o['penyesuai']['deadweight_catatan']))
                                <br><span style="color: #888;">Dasar deadweight: {{ $o['penyesuai']['deadweight_catatan'] }}</span>
                            @endif
                            @if (! empty($o['proxy']))
                                <br><span style="color: #888;">
                                    Proxy: {{ $o['proxy']['nama'] }} — {{ rupiah($o['proxy']['nilai']) }}
                                    {{ $o['proxy']['satuan'] }} ({{ $o['proxy']['sumber'] }}, {{ $o['proxy']['tahun'] }})
                                </span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        @if (! empty($hasil->rincian['peringatan']))
            <div class="kotak-tinjau">
                <p style="margin: 0 0 4px; font-size: 9px; font-weight: bold; color: #0B4F63;">
                    Catatan yang menyertai angka ini
                </p>
                <ul style="font-size: 8.5px;">
                    @foreach ($hasil->rincian['peringatan'] as $w)
                        <li>{{ $w }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($hasil->catatan_review)
            <div class="kotak">
                <strong style="font-size: 9px;">Catatan Review:</strong>
                <span style="font-size: 9px;">{{ $hasil->catatan_review }}</span>
            </div>
        @endif
    @endif

    @if ($program->catatan)
        <div class="kotak">
            <strong style="font-size: 9px;">Catatan Program:</strong>
            <span style="font-size: 9px;">{{ $program->catatan }}</span>
        </div>
    @endif

    <p style="margin-top: 24px; font-size: 8px; color: #999; text-align: center; border-top: 1px solid #eee; padding-top: 6px;">
        Dicetak dari CahayaOS pada {{ $tanggalCetak }} — seluruh angka berasal langsung dari basis data operasional.
    </p>
</body>
</html>
