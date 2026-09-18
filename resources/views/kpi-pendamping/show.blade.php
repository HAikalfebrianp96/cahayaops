@extends('layouts.app')

@section('title', 'Kinerja ' . $pendamping->nama)

@section('content')
{{-- ============================================================
     DETAIL KINERJA PENDAMPING.

     Pendamping hanya bisa membuka halaman ini untuk DIRINYA SENDIRI —
     diperiksa controller terhadap pendamping_id akunnya, bukan role.

     Riwayat 12 bulan dihitung ULANG setiap kali halaman dibuka, tidak
     disimpan. Konsekuensinya: kalau catatan lapangan bulan lalu dikoreksi,
     riwayat KPI-nya ikut benar tanpa proses perhitungan ulang apa pun.
     ============================================================ --}}
<div class="space-y-5">

    {{-- HEADER --}}
    <div class="bg-white border border-border-soft rounded-2xl shadow-sm p-6">
        <div class="flex items-start justify-between gap-4 flex-wrap">
            <div>
                <h2 class="text-xl font-bold text-text-main">{{ $pendamping->nama }}</h2>
                <p class="text-sm text-text-secondary mt-0.5">
                    {{ $pendamping->status }} &bull; {{ $pendamping->participants_count }} binaan
                    ({{ $pendamping->binaan_aktif_count }} masih berjalan)
                </p>
                <p class="text-[11px] text-text-secondary mt-1">Periode {{ $labelPeriode }}</p>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                <span class="text-[11px] font-medium px-2.5 py-1 rounded-full {{ $rapor['warna'] }}">
                    {{ $rapor['status'] ?? 'Belum ada target' }}
                </span>

                {{-- Dasar penilaiannya ikut berdiri di samping vonisnya.
                     Keterangan ini sebenarnya sudah lama ada, tapi sebagai
                     ekor kalimat 11px di bawah grafik — sementara kata
                     "Rendah" tampil besar di kepala halaman. Yang membaca
                     lebih dulu selalu lencananya. --}}
                @if ($rapor['pakai_default'])
                    <span class="text-[11px] font-medium px-2.5 py-1 rounded-full bg-surface-bg text-text-secondary">
                        Target bawaan sistem
                    </span>
                @endif
                <span class="text-[11px] font-medium px-2.5 py-1 rounded-full {{ $rapor['beban']['warna'] }}">
                    Beban {{ $rapor['beban']['kategori'] }}
                </span>
                @if ($isAdmin)
                    <a href="{{ route('kpi-pendamping.index') }}" class="text-[13px] text-text-secondary px-3 py-2 hover:text-text-main">← Daftar KPI</a>
                @endif
            </div>
        </div>

        @if ($rapor['kpi'] !== null)
            <div class="mt-5">
                <div class="flex items-center justify-between mb-1.5">
                    <span class="text-xs text-text-secondary">Progress KPI {{ $labelPeriode }}</span>
                    <span class="text-lg font-bold text-cahaya-primary">{{ $rapor['kpi'] }}%</span>
                </div>
                <div class="h-3 rounded-full bg-surface-bg overflow-hidden">
                    <div class="h-full rounded-full bg-cahaya-primary transition-all" style="width: {{ $rapor['kpi'] }}%"></div>
                </div>
                <p class="text-[11px] text-text-secondary mt-2">
                    @if ($rapor['belum_tercapai'] === 0)
                        Seluruh target periode ini sudah tercapai.
                    @else
                        Masih ada {{ $rapor['belum_tercapai'] }} target yang belum tercapai dari
                        {{ $rapor['jumlah_indikator'] }} indikator yang ditargetkan.
                    @endif
                    &bull; Sumber target: {{ $rapor['pakai_default'] ? 'bawaan sistem' : 'target individual' }}.
                </p>
            </div>

            @if ($rapor['pakai_default'])
                {{-- ============================================================
                     ANGKA INI BELUM TENTU KESEPAKATAN SIAPA PUN

                     Kalau tidak ada baris target untuk periode ini, KpiService
                     memakai angka bawaan dari pengaturan/config. Perhitungannya
                     memang begitu dirancang dan itu tidak diubah di sini.

                     Yang diperbaiki cara menyampaikannya: menyebut seseorang
                     "Rendah" terhadap angka yang tidak pernah ia sepakati
                     adalah penilaian yang terbaca seperti vonis, padahal
                     dasarnya baru asumsi.
                     ============================================================ --}}
                <div class="mt-4 rounded-xl bg-surface-bg border border-border-soft px-4 py-3">
                    <p class="text-xs text-text-main">
                        Belum ada target yang ditetapkan untuk periode ini.
                        Angka di atas dihitung memakai target bawaan sistem, bukan target yang disepakati.
                    </p>
                    @if ($isAdmin)
                        <p class="text-xs text-text-secondary mt-1">
                            <a href="{{ route('target-pendamping.create', ['pendamping_id' => $pendamping->id]) }}"
                               class="text-cahaya-secondary hover:underline">Tetapkan target untuk pendamping ini &rarr;</a>
                        </p>
                    @else
                        <p class="text-xs text-text-secondary mt-1">
                            Hubungi Admin kalau target periode ini perlu disepakati lebih dulu.
                        </p>
                    @endif
                </div>
            @endif
        @else
            <p class="mt-4 text-sm text-text-secondary italic">
                Belum ada target yang ditetapkan untuk periode ini, jadi KPI belum bisa dihitung.
            </p>
        @endif
    </div>

    {{-- RINCIAN INDIKATOR --}}
    <div class="bg-white border border-border-soft rounded-2xl shadow-sm p-6">
        <h3 class="text-[15px] font-semibold text-text-main mb-1">Rincian Indikator</h3>
        <p class="text-xs text-text-secondary mb-5">
            Indikator tanpa target ditandai "tidak ditargetkan" dan TIDAK ikut dihitung ke KPI.
        </p>

        {{-- ============================================================
             DIKELOMPOKKAN, BUKAN DIJAJAR SAMA RATA

             Delapan indikator berjajar setara membuat semuanya terbaca
             sama pentingnya. Padahal "Jumlah Binaan" adalah KAPASITAS:
             pendamping dengan 40 binaan bukan lebih baik daripada yang
             punya 15 — ia lebih berat. Menampilkannya berdampingan dengan
             indikator prestasi mendorong pembacaan yang keliru.

             Angkanya tidak berubah sama sekali; yang berubah urutan dan
             penjelasannya.
             ============================================================ --}}
        @php
            $kelompokIndikator = collect($rapor['indikator'])
                ->groupBy(fn ($i, $kolom) => \App\Models\PendampingTarget::kelompokIndikator($kolom));
        @endphp

        <div class="space-y-6">
            @foreach ([
                \App\Models\PendampingTarget::KELOMPOK_UTAMA,
                \App\Models\PendampingTarget::KELOMPOK_PENDUKUNG,
                \App\Models\PendampingTarget::KELOMPOK_BEBAN,
            ] as $kelompok)
                @php $isi = $kelompokIndikator->get($kelompok); @endphp
                @continue (! $isi || $isi->isEmpty())

                <div>
                    <div class="flex flex-wrap items-baseline gap-2 mb-1">
                        <h4 class="text-[13px] font-semibold text-text-main">
                            {{ \App\Models\PendampingTarget::KELOMPOK_JUDUL[$kelompok] }}
                        </h4>
                        @if ($kelompok === \App\Models\PendampingTarget::KELOMPOK_BEBAN)
                            <span class="text-[11px] px-2 py-0.5 rounded-full bg-surface-bg text-text-secondary">
                                kapasitas, bukan prestasi
                            </span>
                        @endif
                    </div>
                    <p class="text-[11px] text-text-secondary mb-3">
                        {{ \App\Models\PendampingTarget::KELOMPOK_KETERANGAN[$kelompok] }}
                    </p>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @foreach ($isi as $i)
                            <div class="border border-border-soft rounded-xl p-4">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-sm font-medium text-text-main">{{ $i['label'] }}</span>
                                    @if ($i['dihitung'])
                                        <span class="text-sm font-semibold text-text-main">{{ $i['aktual'] }} / {{ $i['target'] }}</span>
                                    @else
                                        <span class="text-[11px] text-text-secondary">{{ $i['aktual'] }} (tidak ditargetkan)</span>
                                    @endif
                                </div>

                                @if ($i['dihitung'])
                                    <div class="h-2 rounded-full bg-surface-bg overflow-hidden">
                                        <div class="h-full rounded-full {{ $i['progress'] >= 100 ? 'bg-status-success' : 'bg-cahaya-primary' }}"
                                             style="width: {{ $i['progress'] }}%"></div>
                                    </div>
                                    <p class="text-[11px] text-text-secondary mt-1">{{ $i['progress'] }}% tercapai</p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- ============================================================
         GRAFIK PERKEMBANGAN KINERJA

         TINGGINYA DIKUNCI DI PEMBUNGKUS, BUKAN DI KANVASNYA.

         Chart.js dipakai dengan maintainAspectRatio:false, dan dalam mode
         itu kanvas mengambil tinggi dari elemen induknya. Induknya dulu
         tidak punya tinggi sama sekali (height: auto), jadi grafiknya
         memanjang tak terkendali dan mendorong seluruh halaman ke bawah.
         Atribut height="90" pada kanvas tidak pernah berpengaruh apa pun
         di mode ini — hanya terlihat seolah mengatur sesuatu.

         Tingginya bertingkat: cukup pendek agar muat di layar ponsel
         bersama ringkasan di atasnya, cukup tinggi di desktop agar tiga
         garisnya masih terbaca.
         ============================================================ --}}
    <div class="bg-white border border-border-soft rounded-2xl shadow-sm p-6">
        <h3 class="text-[15px] font-semibold text-text-main mb-1">Perkembangan Kinerja</h3>
        <p class="text-xs text-text-secondary mb-4">12 bulan terakhir, dihitung dari data yang tersimpan.</p>

        {{-- Gulir mendatar DI DALAM kartu, supaya dua belas bulan tetap
             terbaca di layar kecil tanpa membuat seluruh halaman bisa
             digeser ke samping. --}}
        <div class="overflow-x-auto">
            <div class="h-[260px] sm:h-[300px] lg:h-[330px] min-w-[560px] sm:min-w-0">
                <canvas id="grafikKpi"></canvas>
            </div>
        </div>
    </div>

    {{-- ============================================================
         RIWAYAT KINERJA BULANAN — tertutup secara bawaan.

         Isinya persis sama dengan yang digambar grafik di atas, hanya dalam
         bentuk angka. Dibiarkan terbuka, dua belas baris tabel ini membuat
         halaman ringkasan kinerja menjadi halaman tabel — padahal yang
         dicari orang saat membukanya adalah gambaran cepat.

         Memakai <details>, bukan JavaScript: tetap bisa dibuka walau skrip
         gagal dimuat, dan ikut terbuka sendiri saat halamannya dicetak.
         ============================================================ --}}
    <details class="group bg-white border border-border-soft rounded-2xl shadow-sm overflow-hidden">
        <summary class="flex items-center justify-between gap-3 p-6 cursor-pointer list-none">
            <div>
                <h3 class="text-[15px] font-semibold text-text-main">Riwayat Kinerja Bulanan</h3>
                <p class="text-xs text-text-secondary mt-0.5">Angka rinci di balik grafik di atas.</p>
            </div>
            <span class="flex-none text-xs font-medium text-status-info whitespace-nowrap">
                <span class="group-open:hidden">Lihat Detail &rarr;</span>
                <span class="hidden group-open:inline">Tutup Detail</span>
            </span>
        </summary>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-text-secondary border-y border-border-soft bg-surface-bg">
                        <th class="px-6 py-3 font-medium">Bulan</th>
                        <th class="px-6 py-3 font-medium">Pendampingan</th>
                        <th class="px-6 py-3 font-medium">Follow Up</th>
                        <th class="px-6 py-3 font-medium">Kunjungan</th>
                        <th class="px-6 py-3 font-medium">KPI</th>
                        <th class="px-6 py-3 font-medium">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($riwayat->reverse() as $r)
                        <tr class="border-b border-border-soft last:border-0">
                            <td class="px-6 py-3 text-text-main whitespace-nowrap">{{ $r['label'] }}</td>
                            <td class="px-6 py-3 text-text-secondary">{{ $r['indikator']['target_pendampingan']['aktual'] }}</td>
                            <td class="px-6 py-3 text-text-secondary">{{ $r['indikator']['target_follow_up']['aktual'] }}</td>
                            <td class="px-6 py-3 text-text-secondary">{{ $r['indikator']['target_kunjungan']['aktual'] }}</td>
                            <td class="px-6 py-3 font-medium text-text-main">{{ $r['kpi'] !== null ? $r['kpi'] . '%' : '—' }}</td>
                            <td class="px-6 py-3">
                                <span class="text-[11px] font-medium px-2 py-0.5 rounded-full {{ $r['warna'] }}">
                                    {{ $r['status'] ?? 'Tanpa target' }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </details>

    {{-- TARGET YANG DITETAPKAN --}}
    <div class="bg-white border border-border-soft rounded-2xl shadow-sm overflow-hidden">
        <div class="flex items-center justify-between p-6 pb-4">
            <h3 class="text-[15px] font-semibold text-text-main">Target yang Ditetapkan</h3>
            @if ($isAdmin)
                <a href="{{ route('target-pendamping.create', ['pendamping_id' => $pendamping->id]) }}"
                   class="text-xs text-status-info hover:underline">+ Tetapkan Target</a>
            @endif
        </div>

        @if ($targets->isEmpty())
            <p class="px-6 pb-6 text-sm text-text-secondary italic">
                Belum ada target khusus — KPI memakai target bawaan sistem.
            </p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-text-secondary border-y border-border-soft bg-surface-bg">
                            <th class="px-6 py-3 font-medium">Periode</th>
                            <th class="px-6 py-3 font-medium">Binaan</th>
                            <th class="px-6 py-3 font-medium">Kunjungan</th>
                            <th class="px-6 py-3 font-medium">Pendampingan</th>
                            <th class="px-6 py-3 font-medium">Follow Up</th>
                            @if ($isAdmin) <th class="px-6 py-3 font-medium">Aksi</th> @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($targets as $t)
                            <tr class="border-b border-border-soft last:border-0">
                                <td class="px-6 py-3 text-text-main whitespace-nowrap">{{ $t->periodeLabel() }}</td>
                                <td class="px-6 py-3 text-text-secondary">{{ $t->target_jumlah_binaan ?? '—' }}</td>
                                <td class="px-6 py-3 text-text-secondary">{{ $t->target_kunjungan ?? '—' }}</td>
                                <td class="px-6 py-3 text-text-secondary">{{ $t->target_pendampingan ?? '—' }}</td>
                                <td class="px-6 py-3 text-text-secondary">{{ $t->target_follow_up ?? '—' }}</td>
                                @if ($isAdmin)
                                    <td class="px-6 py-3">
                                        <a href="{{ route('target-pendamping.edit', $t) }}" class="text-xs text-cahaya-primary hover:underline">Edit</a>
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>

@vite('resources/js/chart.js')
<script>
    /* Chart.js kini dibundel Vite dan dimuat sebagai modul, yang selalu
       ditunda sampai halaman selesai diurai. Tanpa pembungkus ini, kode di
       bawah berjalan sebelum pustakanya ada dan grafiknya hilang tanpa
       pesan apa pun di halaman. */
    document.addEventListener('DOMContentLoaded', function () {

        (function () {
            const el = document.getElementById('grafikKpi');
            if (!el || typeof Chart === 'undefined') return;

            const labelPenuh = @json($grafik['labels']);

            // Sengaja SATU grafik dengan tiga garis, bukan tiga grafik terpisah:
            // di ponsel tiga kanvas bertumpuk membuat halaman panjang tanpa
            // menambah pemahaman.
            new Chart(el, {
                type: 'line',
                data: {
                    // Label penuh ("Sep 2026") dipendekkan jadi "Sep" untuk
                    // sumbu X — dua belas label berikut tahunnya saling
                    // bertindih di layar ponsel. Tahunnya tidak hilang:
                    // tooltip di bawah tetap menampilkan label utuhnya.
                    labels: labelPenuh.map(function (l) { return l.split(' ')[0]; }),
                    datasets: [
                        {
                            label: 'KPI (%)',
                            data: @json($grafik['kpi']),
                            borderColor: '#03586D',
                            backgroundColor: 'rgba(3, 88, 109, 0.08)',
                            tension: 0.3,
                            fill: true,
                            yAxisID: 'y',
                        },
                        {
                            label: 'Pendampingan',
                            data: @json($grafik['pendampingan']),
                            borderColor: '#0292B9',
                            tension: 0.3,
                            yAxisID: 'y1',
                        },
                        {
                            label: 'Follow Up',
                            data: @json($grafik['follow_up']),
                            borderColor: '#FEB900',
                            tension: 0.3,
                            yAxisID: 'y1',
                        },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } },
                        tooltip: {
                            callbacks: {
                                // Judul tooltip memakai label UTUH berikut
                                // tahunnya — itulah gunanya memendekkan
                                // sumbunya saja.
                                title: function (item) { return labelPenuh[item[0].dataIndex]; },
                            },
                        },
                    },
                    scales: {
                        y: { beginAtZero: true, max: 100, title: { display: true, text: 'KPI (%)' }, ticks: { font: { size: 10 } } },
                        y1: { beginAtZero: true, position: 'right', grid: { drawOnChartArea: false }, title: { display: true, text: 'Kegiatan' }, ticks: { font: { size: 10 } } },
                        x: { ticks: { font: { size: 10 } } },
                    },
                },
            });
        })();
    });
</script>
@endsection
