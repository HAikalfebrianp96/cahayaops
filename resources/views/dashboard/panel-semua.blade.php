{{-- PANEL: SEMUA KEGIATAN — tab default Admin, pusat monitoring.
     Keuangan Koperasi tahun berjalan (enam angka dari LabaRugiService, sama
     dengan Dashboard Koperasi & Keuangan), lalu hitungan pokok kedua modul.
     Rinciannya ada di panel/modul masing-masing. --}}
@php $keu = $ringkasanSemua['keuangan']; @endphp
<div class="space-y-3">
    <div class="flex items-baseline justify-between flex-wrap gap-x-3 gap-y-1">
        <h3 class="text-[15px] font-semibold text-text-main">Keuangan Koperasi</h3>
        <p class="text-xs text-text-secondary">Periode: Tahun {{ now()->year }}, semua bulan ·
            <a href="{{ route('dashboard', ['modul' => 'koperasi']) }}" class="text-status-info hover:underline">Rincian &amp; filter</a></p>
    </div>
    <div class="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-3">
        @foreach ([
            ['Total Penjualan', $keu['penjualan'], 'text-text-main'],
            ['HPP Terjual', $keu['hpp'], 'text-text-main'],
            ['Laba Kotor', $keu['laba_kotor'], 'text-status-info'],
            ['Pengeluaran Operasional', $keu['pengeluaran_total'], 'text-status-danger'],
            ['Piutang Aktif', $keu['piutang'], 'text-cahaya-accent'],
            ['Laba Bersih', $keu['laba_bersih'], $keu['laba_bersih'] >= 0 ? 'text-status-success' : 'text-status-danger'],
        ] as [$label, $nilai, $warna])
            <div class="bg-white rounded-2xl border border-border-soft shadow-sm p-4 min-w-0">
                <p class="text-[11px] font-medium text-text-secondary mb-1.5">{{ $label }}</p>
                <p class="text-base font-bold {{ $warna }} break-words">{{ rupiah($nilai) }}</p>
            </div>
        @endforeach
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-5">

    {{-- CAHAYA PRENEUR --}}
    <div class="bg-white rounded-2xl border border-border-soft shadow-sm p-6">
        <div class="flex items-center gap-2.5 mb-4">
            <div class="h-8 w-8 rounded-lg bg-primary-light flex items-center justify-center flex-shrink-0">
                <svg class="w-4 h-4 text-cahaya-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-1.13a4 4 0 10-4-4 4 4 0 004 4zm6 0a4 4 0 10-4-4" />
                </svg>
            </div>
            <div>
                <h3 class="text-[15px] font-semibold text-text-main leading-tight">Cahaya Preneur</h3>
                <p class="text-[11px] text-text-secondary">Program Ekonomi YBM PLN UP3 Bengkulu</p>
            </div>
        </div>

        <div class="grid grid-cols-3 gap-3 text-center">
            <div class="bg-surface-bg rounded-xl p-4">
                <p class="text-xl font-bold text-text-main">{{ $ringkasanSemua['total_peserta'] }}</p>
                <p class="text-[11px] text-text-secondary mt-1">Peserta</p>
            </div>
            <div class="bg-surface-bg rounded-xl p-4">
                <p class="text-xl font-bold text-text-main">{{ $ringkasanSemua['peserta_aktif'] }}</p>
                <p class="text-[11px] text-text-secondary mt-1">Peserta Aktif</p>
            </div>
            <div class="bg-surface-bg rounded-xl p-4">
                <p class="text-xl font-bold text-text-main">{{ $ringkasanSemua['peserta_berkembang'] }}</p>
                <p class="text-[11px] text-text-secondary mt-1">Peserta Berkembang</p>
            </div>
        </div>
    </div>

    {{-- KOPERASI CAHAYA --}}
    <div class="bg-white rounded-2xl border border-border-soft shadow-sm p-6">
        <div class="flex items-center gap-2.5 mb-4">
            <div class="h-8 w-8 rounded-lg bg-primary-light flex items-center justify-center flex-shrink-0">
                <svg class="w-4 h-4 text-cahaya-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 21h18M5 21V7l8-4v18M19 21V11l-6-4M9 9h.01M9 12h.01M9 15h.01" />
                </svg>
            </div>
            <div>
                <h3 class="text-[15px] font-semibold text-text-main leading-tight">Koperasi Cahaya</h3>
                <p class="text-[11px] text-text-secondary">Usaha Bersama, Maju Bersama</p>
            </div>
        </div>

        <div class="grid grid-cols-3 gap-3 text-center">
            <div class="bg-surface-bg rounded-xl p-4">
                <p class="text-xl font-bold text-text-main">{{ $ringkasanSemua['total_anggota'] }}</p>
                <p class="text-[11px] text-text-secondary mt-1">Anggota</p>
            </div>
            <div class="bg-surface-bg rounded-xl p-4">
                <p class="text-xl font-bold text-text-main">{{ $ringkasanSemua['total_produk'] }}</p>
                <p class="text-[11px] text-text-secondary mt-1">Produk</p>
            </div>
            <div class="bg-surface-bg rounded-xl p-4">
                <p class="text-xl font-bold text-text-main">{{ $ringkasanSemua['total_unit'] }}</p>
                <p class="text-[11px] text-text-secondary mt-1">Unit Usaha</p>
            </div>
        </div>

        {{-- PRODUK TERLARIS tahun berjalan — jumlah unit terjual. --}}
        <div class="mt-4">
            <p class="text-[13px] font-semibold text-text-main mb-2">Produk Terlaris <span class="text-[11px] font-normal text-text-secondary">· Tahun {{ now()->year }}</span></p>
            @if (empty($ringkasanSemua['produk_terlaris']))
                <p class="text-xs text-text-secondary italic">Belum ada data penjualan.</p>
            @else
                <ol class="space-y-1.5 text-sm">
                    @foreach ($ringkasanSemua['produk_terlaris'] as $i => $p)
                        <li class="flex items-baseline justify-between gap-3">
                            <span class="min-w-0 text-text-main"><span class="text-text-secondary mr-1">{{ $i + 1 }}.</span>{{ $p['nama'] }}</span>
                            <span class="font-semibold text-text-main whitespace-nowrap">{{ number_format($p['qty'], 0, ',', '.') }} terjual</span>
                        </li>
                    @endforeach
                </ol>
            @endif
        </div>
    </div>
</div>

{{-- DAMPAK & SROI — kesimpulan atas kedua ekosistem, jadi tempatnya di
     panel gabungan ini, bukan di salah satu tabnya. --}}
@includeWhen(isset($ringkasanDampak), 'impact.partials.ringkasan-dashboard')

<p class="text-xs text-text-secondary">
    Pilih tab <span class="font-medium text-text-main">Cahaya Preneur</span> atau
    <span class="font-medium text-text-main">Koperasi Cahaya</span> di atas untuk melihat statistik lengkap tiap modul.
</p>
