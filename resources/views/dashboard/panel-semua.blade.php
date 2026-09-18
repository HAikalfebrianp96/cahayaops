{{-- PANEL: SEMUA KEGIATAN — tab netral default.
     SENGAJA ringkas: cuma jumlah data pokok kedua modul berdampingan, tanpa
     angka keuangan. Rinciannya ada di panel modul masing-masing, jadi di sini
     tidak ada angka yang perlu dijaga konsistensinya dengan LabaRugiService. --}}
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
                <p class="text-xl font-bold text-text-main">{{ $ringkasanSemua['total_pendamping'] }}</p>
                <p class="text-[11px] text-text-secondary mt-1">Pendamping</p>
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
                <p class="text-xl font-bold text-text-main">{{ $ringkasanSemua['anggota_aktif'] }}</p>
                <p class="text-[11px] text-text-secondary mt-1">Anggota Aktif</p>
            </div>
            <div class="bg-surface-bg rounded-xl p-4">
                <p class="text-xl font-bold text-text-main">{{ $ringkasanSemua['total_unit'] }}</p>
                <p class="text-[11px] text-text-secondary mt-1">Unit Usaha</p>
            </div>
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
