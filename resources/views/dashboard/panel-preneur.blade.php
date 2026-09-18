{{-- ============================================================
     DASHBOARD CAHAYA PRENEUR — DISUSUN SEBAGAI EMPAT JAWABAN

     Halaman ini sudah lama memuat semua yang diperlukan, tapi urutannya
     mengikuti sejarah penambahan fitur, bukan cara orang membacanya.
     Akibatnya angka peserta, angka perkembangan, dan angka hasil program
     berselang-seling, dan pembacanya harus menebak blok mana menjawab apa.

     Sekarang blok yang sama dikelompokkan di bawah pertanyaan yang
     dijawabnya. TIDAK ada angka baru, tidak ada blok yang dibuang, dan
     tidak ada perhitungan yang berubah — hanya urutan dan judulnya.

     "Perlu Perhatian" tetap paling atas: ia satu-satunya bagian yang
     menuntut tindakan hari ini, sisanya laporan keadaan.

     Kinerja pendamping turun ke bawah keempat jawaban. Itu urusan
     pengelolaan tim, bukan jawaban atas pertanyaan tentang peserta —
     dan menaruhnya di tengah memutus alur bacaan.
     ============================================================ --}}
{{-- PANEL: CAHAYA PRENEUR — dipindahkan apa adanya dari dashboard.blade.php lama.
     Di-include oleh dashboard.blade.php saat modul aktif = 'preneur'. --}}


    {{-- SIAPA YANG PERLU DITINDAKLANJUTI? --}}
    <div class="pt-2">
        <h2 class="text-base font-semibold text-text-main">Siapa yang perlu ditindaklanjuti?</h2>
        <p class="text-xs text-text-secondary mt-0.5">Satu-satunya bagian yang menuntut tindakan. Klik angkanya untuk membuka daftarnya.</p>
    </div>

    {{-- ============================================================
         PERLU PERHATIAN.

         Ditaruh PALING ATAS dengan sengaja: ini satu-satunya bagian
         Dashboard yang menuntut tindakan, sisanya laporan keadaan. Setiap
         baris membawa tautan ke daftar datanya, jadi bisa langsung
         dikerjakan — bukan sekadar memberi tahu ada masalah.
         ============================================================ --}}
    @if (! empty($alert))
        <div class="bg-white rounded-2xl border border-border-soft shadow-sm p-6">
            <h3 class="text-[15px] font-semibold text-text-main mb-1">Perlu Perhatian</h3>
            <p class="text-xs text-text-secondary mb-4">
                Dihitung dari data yang tersimpan. Klik untuk membuka daftarnya.
            </p>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                @foreach ($alert as $a)
                    <a href="{{ $a['tautan'] }}"
                       class="flex items-start gap-3 border border-border-soft rounded-xl p-3.5 hover:bg-surface-bg transition">
                        <span class="text-xl font-bold {{ $a['warna'] }} leading-none">{{ $a['jumlah'] }}</span>
                        <span class="text-sm text-text-main min-w-0">{{ $a['teks'] }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    @endif


    {{-- BERAPA PESERTA YANG KITA DAMPINGI? --}}
    <div class="pt-2">
        <h2 class="text-base font-semibold text-text-main">Berapa peserta yang kita dampingi?</h2>
        <p class="text-xs text-text-secondary mt-0.5">Jumlah dan komposisi peserta yang sedang berjalan.</p>
    </div>

    {{-- 4. KPI UTAMA --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-2xl border border-border-soft shadow-sm p-5">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-medium text-text-secondary">Total Peserta</span>
                <div class="h-8 w-8 rounded-lg bg-primary-light flex items-center justify-center">
                    <svg class="w-4 h-4 text-cahaya-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-1.13a4 4 0 10-4-4 4 4 0 004 4zm6 0a4 4 0 10-4-4"/></svg>
                </div>
            </div>
            <p class="text-2xl font-bold text-text-main">{{ $stats['total_peserta'] }}</p>
        </div>

        <div class="bg-white rounded-2xl border border-border-soft shadow-sm p-5">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-medium text-text-secondary">Peserta Aktif</span>
                <div class="h-8 w-8 rounded-lg bg-primary-light flex items-center justify-center">
                    <svg class="w-4 h-4 text-status-info" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
            </div>
            <p class="text-2xl font-bold text-text-main">{{ $stats['peserta_aktif'] }}</p>
            <p class="text-[11px] text-text-secondary mt-1">dari {{ $stats['total_peserta'] }} total peserta</p>
        </div>

        <div class="bg-white rounded-2xl border border-border-soft shadow-sm p-5">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-medium text-text-secondary">Total Omzet Bulan Ini</span>
                <div class="h-8 w-8 rounded-lg bg-gold-light flex items-center justify-center">
                    <svg class="w-4 h-4 text-cahaya-accent" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V6m0 10v2"/></svg>
                </div>
            </div>
            <p class="text-2xl font-bold text-text-main">{{ rupiah($stats['omzet_bulan_ini']) }}</p>
        </div>

        <div class="bg-white rounded-2xl border border-border-soft shadow-sm p-5">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-medium text-text-secondary">Rata-rata Omzet</span>
                <div class="h-8 w-8 rounded-lg bg-primary-light flex items-center justify-center">
                    <svg class="w-4 h-4 text-cahaya-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                </div>
            </div>
            <p class="text-2xl font-bold text-text-main">{{ rupiah($programBulanIni['rata_rata_omzet']) }}</p>
            <p class="text-[11px] text-text-secondary mt-1">per peserta yang melapor bulan ini</p>
        </div>
    </div>

        {{-- 7. STATUS PESERTA (Ringkasan Peserta) --}}
    <div class="bg-white rounded-2xl border border-border-soft shadow-sm p-6">
        <h3 class="text-[15px] font-semibold text-text-main mb-4">Status Peserta</h3>
        @php
            $pesertaTidakAktif = max($stats['total_peserta'] - $stats['peserta_aktif'], 0);
            $persenAktif = $stats['total_peserta'] > 0 ? round(($stats['peserta_aktif'] / $stats['total_peserta']) * 100) : 0;
        @endphp
        <div class="mb-4">
            <div class="flex justify-between text-xs text-text-secondary mb-1.5">
                <span>Peserta Aktif</span>
                <span>{{ $persenAktif }}%</span>
            </div>
            <div class="h-2.5 w-full bg-surface-bg rounded-full overflow-hidden">
                <div class="h-full bg-cahaya-primary rounded-full" style="width: {{ $persenAktif }}%"></div>
            </div>
        </div>
        <div class="space-y-2 text-sm">
            <div class="flex justify-between"><span class="text-text-secondary">Peserta Aktif</span><span class="font-semibold text-text-main">{{ $stats['peserta_aktif'] }}</span></div>
            <div class="flex justify-between"><span class="text-text-secondary">Peserta Tidak Aktif</span><span class="font-semibold text-text-main">{{ $pesertaTidakAktif }}</span></div>
            <div class="flex justify-between border-t border-border-soft pt-2 mt-1"><span class="text-text-secondary">Total Peserta</span><span class="font-bold text-cahaya-primary">{{ $stats['total_peserta'] }}</span></div>
        </div>
    </div>

    {{-- 11. PESERTA TERBARU --}}
    <div class="bg-white rounded-2xl border border-border-soft shadow-sm p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-[15px] font-semibold text-text-main">Peserta Terbaru</h3>
            <a href="{{ route('participants.index') }}" class="text-xs text-status-info font-medium hover:underline">Lihat Semua Peserta</a>
        </div>

        @if (count($pesertaTerbaru) === 0)
            <p class="text-sm text-text-secondary italic">Belum ada data peserta.</p>
        @else
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                @foreach ($pesertaTerbaru as $p)
                    <div class="flex items-center justify-between text-sm bg-surface-bg rounded-xl p-3">
                        <div class="min-w-0">
                            <p class="font-medium text-text-main truncate">{{ $p['nama'] }}</p>
                            <p class="text-xs text-text-secondary truncate">{{ $p['usaha'] }}</p>
                        </div>
                        <span class="text-[11px] font-medium px-2 py-0.5 rounded-full flex-shrink-0 ml-2
                            {{ $p['status'] === 'Aktif' ? 'bg-primary-light text-cahaya-primary' : 'bg-gray-100 text-text-secondary' }}">
                            {{ $p['status'] }}
                        </span>
                    </div>
                @endforeach
            </div>
        @endif
    </div>


    {{-- APAKAH PESERTA BERKEMBANG? --}}
    <div class="pt-2">
        <h2 class="text-base font-semibold text-text-main">Apakah peserta berkembang?</h2>
        <p class="text-xs text-text-secondary mt-0.5">Omzet, profit, pendampingan, legalitas, dan HPP — dibaca dari data yang dicatat pendamping.</p>
    </div>

    {{-- ============================================================
         MONITORING USAHA — perbandingan omzet & peserta yang bergerak.
         ============================================================ --}}
    <div class="bg-white rounded-2xl border border-border-soft shadow-sm p-6">
        <h3 class="text-[15px] font-semibold text-text-main mb-4">Monitoring Usaha</h3>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-5">
            <div class="bg-surface-bg rounded-xl p-4">
                <p class="text-[11px] text-text-secondary mb-1">Omzet Bulan Ini</p>
                <p class="text-lg font-bold text-text-main">{{ rupiah($omzetPerbandingan['total_bulan_ini']) }}</p>
            </div>
            <div class="bg-surface-bg rounded-xl p-4">
                <p class="text-[11px] text-text-secondary mb-1">Omzet Bulan Lalu</p>
                <p class="text-lg font-bold text-text-main">{{ rupiah($omzetPerbandingan['total_bulan_lalu']) }}</p>
            </div>
            <div class="bg-surface-bg rounded-xl p-4">
                <p class="text-[11px] text-text-secondary mb-1">Pertumbuhan</p>
                @if ($omzetPerbandingan['pertumbuhan_persen'] === null)
                    <p class="text-lg font-bold text-text-secondary">—</p>
                    <p class="text-[10px] text-text-secondary">Belum ada pembanding bulan lalu.</p>
                @else
                    <p class="text-lg font-bold {{ $omzetPerbandingan['pertumbuhan_persen'] >= 0 ? 'text-status-success' : 'text-status-danger' }}">
                        {{ $omzetPerbandingan['pertumbuhan_persen'] > 0 ? '+' : '' }}{{ $omzetPerbandingan['pertumbuhan_persen'] }}%
                    </p>
                @endif
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <div>
                <p class="text-[13px] font-semibold text-text-main mb-2">Pertumbuhan Tertinggi</p>
                @if ($omzetPerbandingan['naik']->isEmpty())
                    <p class="text-xs text-text-secondary italic">
                        Belum ada peserta dengan dua laporan berurutan untuk dibandingkan.
                    </p>
                @else
                    <ul class="space-y-1.5 text-sm">
                        @foreach ($omzetPerbandingan['naik'] as $b)
                            <li class="flex items-center justify-between gap-2">
                                <a href="{{ route('participants.show', $b['peserta']) }}" class="text-text-main hover:underline truncate">
                                    {{ $b['peserta']->nama_peserta }}
                                </a>
                                <span class="text-status-success font-medium whitespace-nowrap">+{{ $b['persen'] }}%</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <div>
                <p class="text-[13px] font-semibold text-text-main mb-2">Perlu Ditinjau (Omzet Turun)</p>
                @if ($omzetPerbandingan['turun']->isEmpty())
                    <p class="text-xs text-text-secondary italic">Tidak ada peserta yang omzetnya turun.</p>
                @else
                    <ul class="space-y-1.5 text-sm">
                        @foreach ($omzetPerbandingan['turun'] as $b)
                            <li class="flex items-center justify-between gap-2">
                                <a href="{{ route('participants.show', $b['peserta']) }}" class="text-text-main hover:underline truncate">
                                    {{ $b['peserta']->nama_peserta }}
                                </a>
                                <span class="text-status-danger font-medium whitespace-nowrap">{{ $b['persen'] }}%</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>

    {{--
        PROFIT & KELENGKAPAN PENGUKURAN.

        Satu baris ringkas, bukan empat kartu tambahan. Setiap angka di sini
        ditampilkan BERSAMA pembaginya — "Rp 4,2 jt dari 3 dari 21 laporan"
        mengatakan sesuatu yang sangat berbeda dari "Rp 4,2 jt", dan pembacanya
        berhak tahu yang mana yang sedang ia lihat.
    --}}
    <div class="bg-white rounded-2xl border border-border-soft shadow-sm p-5">
        <div class="flex flex-wrap items-baseline justify-between gap-2 mb-4">
            <h3 class="text-[15px] font-semibold text-text-main">Profit &amp; Kelengkapan Data</h3>
            <a href="{{ route('omzet.index') }}" class="text-xs text-cahaya-secondary hover:underline">Monitoring Omzet →</a>
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
                <p class="text-xs text-text-secondary">Profit Bersih Bulan Ini</p>
                @if ($stats['laporan_berlaba_bulan_ini'] > 0)
                    <p class="text-lg font-bold {{ $stats['profit_bulan_ini'] < 0 ? 'text-status-danger' : 'text-cahaya-primary' }}">
                        {{ rupiah($stats['profit_bulan_ini']) }}
                    </p>
                    <p class="text-[11px] text-text-secondary mt-0.5">
                        dari {{ $stats['laporan_berlaba_bulan_ini'] }} dari {{ $stats['laporan_bulan_ini'] }} laporan ber-HPP
                    </p>
                @else
                    {{-- Nol dan "belum bisa dihitung" adalah dua pernyataan
                         berbeda tentang usaha binaan. --}}
                    <p class="text-lg font-bold text-text-secondary">—</p>
                    <p class="text-[11px] text-text-secondary mt-0.5">belum ada laporan ber-HPP bulan ini</p>
                @endif
            </div>

            <div>
                <p class="text-xs text-text-secondary">HPP Bulan Ini</p>
                <p class="text-lg font-bold text-text-main">
                    {{ $stats['laporan_berlaba_bulan_ini'] > 0 ? rupiah($stats['hpp_bulan_ini']) : '—' }}
                </p>
            </div>

            <div>
                <p class="text-xs text-text-secondary">Peserta Punya Baseline</p>
                <p class="text-lg font-bold text-text-main">{{ $stats['peserta_punya_baseline'] }}</p>
                <p class="text-[11px] text-text-secondary mt-0.5">dari {{ $stats['total_peserta'] }} peserta</p>
            </div>

            <div>
                <p class="text-xs text-text-secondary">Peserta Terukur</p>
                <p class="text-lg font-bold text-text-main">{{ $stats['peserta_terukur'] }}</p>
                <p class="text-[11px] text-text-secondary mt-0.5">baseline + pembanding, siap dihitung dampaknya</p>
            </div>
        </div>
    </div>

    {{-- 6. PERKEMBANGAN OMZET

         Dulu berbagi satu baris dengan kartu "Status Peserta". Kartu itu
         sekarang pindah ke pertanyaan yang memang dijawabnya, dan grafiknya
         memakai lebar penuh — yang justru lebih terbaca untuk data 12 bulan. --}}
    <div>
        <div class="bg-white rounded-2xl border border-border-soft shadow-sm p-6">
            <h3 class="text-[15px] font-semibold text-text-main mb-4">Perkembangan Omzet — {{ now()->year }}</h3>
            @if (array_sum($omzetChart['data']) > 0)
                <canvas id="omzetChart" height="90"></canvas>
            @else
                <div class="text-center py-10">
                    <p class="text-sm text-text-secondary">Belum ada data laporan omzet tahun ini.</p>
                </div>
            @endif
        </div>

    </div>

@if (array_sum($omzetChart['data']) > 0)
@vite('resources/js/chart.js')
<script>
    /* Chart.js kini dibundel Vite dan dimuat sebagai modul, yang selalu
       ditunda sampai halaman selesai diurai. Tanpa pembungkus ini, kode di
       bawah berjalan sebelum pustakanya ada dan grafiknya hilang tanpa
       pesan apa pun di halaman. */
    document.addEventListener('DOMContentLoaded', function () {

        new Chart(document.getElementById('omzetChart'), {
            type: 'line',
            data: {
                labels: @json($omzetChart['labels']),
                datasets: [{
                    label: 'Omzet (Rp)',
                    data: @json($omzetChart['data']),
                    borderColor: '#03586D',
                    backgroundColor: 'rgba(3, 88, 109, 0.08)',
                    pointBackgroundColor: '#FEB900',
                    tension: 0.3,
                    fill: true,
                }]
            },
            options: {
                plugins: { legend: { display: false } },
                scales: {
                    y: {
                        ticks: { callback: function (value) { return 'Rp' + value.toLocaleString('id-ID'); } },
                        grid: { color: '#DCE6E9' }
                    },
                    x: { grid: { display: false } }
                }
            }
        });
    });
</script>
@endif

    {{-- BARIS: PENDAMPINGAN + LEGALITAS --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
        {{-- 8. AKTIVITAS PENDAMPINGAN --}}
        <div class="bg-white rounded-2xl border border-border-soft shadow-sm p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-[15px] font-semibold text-text-main">Aktivitas Pendampingan</h3>
                <a href="{{ route('pendampingan.index') }}" class="text-xs text-status-info font-medium hover:underline">Lihat Semua</a>
            </div>
            @if ($pendampinganTerbaru->isEmpty())
                <p class="text-sm text-text-secondary italic">Belum ada aktivitas pendampingan tercatat.</p>
            @else
                <div class="space-y-3">
                    @foreach ($pendampinganTerbaru as $r)
                        <div class="flex items-center justify-between text-sm border-b border-border-soft last:border-0 pb-3 last:pb-0">
                            <div class="min-w-0">
                                <p class="font-medium text-text-main truncate">{{ $r->participant->nama_peserta ?? '—' }}</p>
                                <p class="text-xs text-text-secondary truncate">{{ $r->jenis_kegiatan }} &middot; {{ $r->pendamping->nama ?? '—' }}</p>
                            </div>
                            <span class="text-[11px] text-text-secondary flex-shrink-0">{{ $r->tanggal->format('d-m-Y') }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- 9. STATUS LEGALITAS --}}
        <div class="bg-white rounded-2xl border border-border-soft shadow-sm p-6">
            <h3 class="text-[15px] font-semibold text-text-main mb-4">Status Legalitas</h3>
            <div class="grid grid-cols-3 gap-3 text-center">
                <div class="bg-surface-bg rounded-xl p-4">
                    <p class="text-xl font-bold text-cahaya-primary">{{ $monitoringPerkembangan['peserta_nib'] }}</p>
                    <p class="text-[11px] text-text-secondary mt-1">Punya NIB</p>
                </div>
                <div class="bg-surface-bg rounded-xl p-4">
                    <p class="text-xl font-bold text-cahaya-primary">{{ $monitoringPerkembangan['peserta_pirt'] }}</p>
                    <p class="text-[11px] text-text-secondary mt-1">Punya PIRT</p>
                </div>
                <div class="bg-surface-bg rounded-xl p-4">
                    <p class="text-xl font-bold text-cahaya-primary">{{ $monitoringPerkembangan['peserta_halal'] }}</p>
                    <p class="text-[11px] text-text-secondary mt-1">Sertifikat Halal</p>
                </div>
            </div>
        </div>
    </div>

    {{-- 10. HPP --}}
    @if ($hppSummary['total_perhitungan'] > 0)
        <div class="bg-white rounded-2xl border border-border-soft shadow-sm p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-[15px] font-semibold text-text-main">HPP Cahaya Preneur</h3>
                <span class="text-xs text-text-secondary">{{ $hppSummary['total_perhitungan'] }} Perhitungan Tercatat</span>
            </div>
            @if ($hppSummary['terbaru'])
                <div class="flex items-center justify-between bg-surface-bg rounded-xl p-4 text-sm">
                    <div class="min-w-0">
                        <p class="font-medium text-text-main truncate">{{ $hppSummary['terbaru']->nama_produk }}</p>
                        <p class="text-xs text-text-secondary truncate">{{ $hppSummary['terbaru']->participant->nama_peserta ?? '—' }} &middot; HPP {{ rupiah($hppSummary['terbaru']->hpp_per_unit) }}/{{ $hppSummary['terbaru']->satuan_produksi }}</p>
                    </div>
                    <a href="{{ route('hpp.show', $hppSummary['terbaru']) }}" class="text-xs text-status-info font-medium hover:underline flex-shrink-0 ml-3">Detail</a>
                </div>
            @endif
        </div>
    @endif


    {{-- BAGAIMANA HASIL PROGRAM? --}}
    <div class="pt-2">
        <h2 class="text-base font-semibold text-text-main">Bagaimana hasil program?</h2>
        <p class="text-xs text-text-secondary mt-0.5">Perubahan yang terjadi pada peserta akibat program, beserta nilai dampaknya.</p>
    </div>

    {{-- DAMPAK & SROI — ringkasan, bukan angka telanjang.
         Rasionya selalu datang bersama lencana statusnya. --}}
    @includeWhen(isset($ringkasanDampak), 'impact.partials.ringkasan-dashboard')


    {{-- KERJA TIM PENDAMPING --}}
    <div class="pt-2">
        <h2 class="text-base font-semibold text-text-main">Kerja tim pendamping</h2>
        <p class="text-xs text-text-secondary mt-0.5">Pengelolaan tim — bukan jawaban tentang peserta, karena itu ditaruh setelah keempatnya.</p>
    </div>

    {{-- ============================================================
         KINERJA PENDAMPING (KPI).

         Angkanya berasal dari KpiService yang SAMA dengan halaman KPI,
         jadi Dashboard dan halaman KPI tidak mungkin menampilkan angka
         yang berbeda.
         ============================================================ --}}
    <div class="bg-white rounded-2xl border border-border-soft shadow-sm p-6">
        <div class="flex items-center justify-between mb-4 flex-wrap gap-2">
            <div>
                <h3 class="text-[15px] font-semibold text-text-main">Kinerja Pendamping</h3>
                <p class="text-xs text-text-secondary mt-0.5">
                    Periode {{ now()->translatedFormat('F Y') }}
                    @if ($kinerjaPendamping['rata_rata'] !== null)
                        &bull; rata-rata KPI {{ $kinerjaPendamping['rata_rata'] }}%
                    @endif
                </p>
            </div>
            {{-- Panel ini juga dipakai Pendamping yang akunnya BELUM tertaut
                 profil Pendamping. Syarat di bawah disalin persis dari guard
                 KpiPendampingController: tanpa itu, tautannya tampil lalu
                 menghasilkan 403 — dan pengguna akan mengira sistemnya rusak,
                 padahal yang kurang hanyalah tautan profilnya. --}}
            @if (auth()->user()->isAdmin() || auth()->user()->pendamping_id)
                <a href="{{ route('kpi-pendamping.index') }}" class="text-xs text-status-info hover:underline">Lihat KPI Lengkap &rarr;</a>
            @endif
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-5">
            @foreach ([
                ['label' => 'Total Pendamping', 'nilai' => $kinerjaPendamping['total_pendamping'], 'warna' => 'text-text-main'],
                ['label' => 'Sangat Baik', 'nilai' => $kinerjaPendamping['sangat_baik'], 'warna' => 'text-status-success'],
                ['label' => 'Baik', 'nilai' => $kinerjaPendamping['baik'], 'warna' => 'text-status-info'],
                ['label' => 'Perlu Perhatian', 'nilai' => $kinerjaPendamping['perlu_perhatian'], 'warna' => 'text-status-warning'],
                ['label' => 'Rendah', 'nilai' => $kinerjaPendamping['rendah'], 'warna' => 'text-status-danger'],
            ] as $kartu)
                <div class="bg-surface-bg rounded-xl p-4">
                    <p class="text-[11px] text-text-secondary mb-1 leading-tight">{{ $kartu['label'] }}</p>
                    <p class="text-xl font-bold {{ $kartu['warna'] }}">{{ $kartu['nilai'] }}</p>
                </div>
            @endforeach
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <div>
                <p class="text-[13px] font-semibold text-text-main mb-2">Top 5 Pendamping Bulan Ini</p>
                @if ($kinerjaPendamping['top']->isEmpty())
                    <p class="text-xs text-text-secondary italic">
                        Belum ada pendamping dengan target aktif, jadi KPI belum bisa diperingkat.
                    </p>
                @else
                    <ol class="space-y-1.5 text-sm">
                        @foreach ($kinerjaPendamping['top'] as $urut => $r)
                            <li class="flex items-center justify-between gap-2">
                                <a href="{{ route('kpi-pendamping.show', $r['pendamping']) }}"
                                   class="text-text-main hover:underline truncate">
                                    {{ $urut + 1 }}. {{ $r['pendamping']->nama }}
                                </a>
                                <span class="font-medium text-status-success whitespace-nowrap">{{ $r['kpi'] }}%</span>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </div>

            <div>
                <p class="text-[13px] font-semibold text-text-main mb-2">Pendamping Perlu Perhatian</p>
                @if ($kinerjaPendamping['perlu_diperhatikan']->isEmpty())
                    <p class="text-xs text-text-secondary italic">
                        Tidak ada pendamping dengan KPI di bawah 75%.
                    </p>
                @else
                    <ul class="space-y-2 text-sm">
                        @foreach ($kinerjaPendamping['perlu_diperhatikan']->take(5) as $r)
                            <li class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <a href="{{ route('kpi-pendamping.show', $r['pendamping']) }}"
                                       class="text-text-main hover:underline truncate block">{{ $r['pendamping']->nama }}</a>
                                    <span class="text-[11px] text-text-secondary">
                                        {{ $r['beban']['jumlah'] }} binaan &bull;
                                        {{ $r['indikator']['target_follow_up']['aktual'] }} follow up &bull;
                                        {{ $r['belum_tercapai'] }} target belum tercapai
                                    </span>
                                </div>
                                <span class="text-[11px] font-medium px-2 py-0.5 rounded-full flex-shrink-0 {{ $r['warna'] }}">
                                    {{ $r['kpi'] }}%
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>

    {{-- ============================================================
         MONITORING PENDAMPING — beban kerja & keaktifan bulan ini.
         ============================================================ --}}
    <div class="bg-white rounded-2xl border border-border-soft shadow-sm overflow-hidden">
        <div class="flex items-center justify-between p-6 pb-4">
            <h3 class="text-[15px] font-semibold text-text-main">Monitoring Pendamping</h3>
            {{-- Syarat yang sama dengan guard FollowUpController. --}}
            @if (auth()->user()->isAdmin() || auth()->user()->pendamping_id)
                <a href="{{ route('follow-up.index') }}" class="text-xs text-status-info hover:underline">Monitoring Follow Up &rarr;</a>
            @endif
        </div>

        @if ($monitoringPendamping['baris']->isEmpty())
            <p class="px-6 pb-6 text-sm text-text-secondary italic">Belum ada pendamping terdaftar.</p>
        @else
            @if ($monitoringPendamping['tanpa_aktivitas']->isNotEmpty())
                <p class="mx-6 mb-3 text-xs text-status-warning bg-gold-light border border-status-warning/30 rounded-lg px-3 py-2">
                    {{ $monitoringPendamping['tanpa_aktivitas']->count() }} pendamping belum mencatat aktivitas apa pun bulan ini.
                </p>
            @endif

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-text-secondary border-y border-border-soft bg-surface-bg">
                            <th class="px-6 py-3 font-medium">Pendamping</th>
                            <th class="px-6 py-3 font-medium">Binaan</th>
                            <th class="px-6 py-3 font-medium">Binaan Aktif</th>
                            <th class="px-6 py-3 font-medium">Aktivitas Bulan Ini</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($monitoringPendamping['baris'] as $b)
                            <tr class="border-b border-border-soft last:border-0">
                                <td class="px-6 py-3 text-text-main">
                                    <a href="{{ route('pendampings.show', $b['pendamping']) }}" class="hover:underline">
                                        {{ $b['pendamping']->nama }}
                                    </a>
                                </td>
                                <td class="px-6 py-3 text-text-secondary">{{ $b['binaan'] }}</td>
                                <td class="px-6 py-3 text-text-secondary">{{ $b['binaan_aktif'] }}</td>
                                <td class="px-6 py-3">
                                    <span class="font-medium {{ $b['aktivitas'] > 0 ? 'text-status-success' : 'text-status-warning' }}">
                                        {{ $b['aktivitas'] }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- ============================================================
         AKTIVITAS TERBARU — dari Activity Log yang SUDAH ADA.
         Disaring ke aksi program saja; log keamanan punya halamannya
         sendiri di Monitoring & Keamanan.
         ============================================================ --}}
    <div class="bg-white rounded-2xl border border-border-soft shadow-sm p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-[15px] font-semibold text-text-main">Aktivitas Terbaru</h3>
            <a href="{{ route('pengaturan-sistem.index') }}" class="text-xs text-status-info hover:underline">Activity Log &rarr;</a>
        </div>

        @if ($aktivitasTerbaru->isEmpty())
            <p class="text-sm text-text-secondary italic">Belum ada aktivitas tercatat.</p>
        @else
            <ul class="space-y-2.5">
                @foreach ($aktivitasTerbaru as $log)
                    <li class="flex items-start gap-3 text-sm border-b border-border-soft last:border-0 pb-2.5 last:pb-0">
                        <span class="text-[11px] text-text-secondary whitespace-nowrap pt-0.5 w-28 flex-shrink-0">
                            {{ $log->created_at->translatedFormat('d M H:i') }}
                        </span>
                        <span class="min-w-0">
                            <span class="text-text-main">{{ $log->kalimat() }}</span>
                            <span class="block text-[11px] text-text-secondary">{{ $log->rincian() }}</span>
                        </span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
