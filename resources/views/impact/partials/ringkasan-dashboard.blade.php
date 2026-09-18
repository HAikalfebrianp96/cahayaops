{{--
    RINGKASAN DAMPAK & SROI DI DASHBOARD.

    ==========================================================================
    ATURAN YANG MENENTUKAN SELURUH BENTUK BLOK INI
    ==========================================================================

    Angka SROI di dashboard adalah angka yang paling mungkin dikutip tanpa
    dibaca konteksnya — ia terlihat sekilas, terlihat resmi, dan terlihat
    final. Karena itu:

      1. RASIO TIDAK PERNAH DITAMPILKAN sebagai angka telanjang. Ia selalu
         datang bersama lencana statusnya: Draft, Perlu Review, atau Final.
         Rasio tanpa status akan dikutip sebagai kesimpulan padahal mungkin
         baru hitungan pertama yang belum ditinjau siapa pun.

      2. PROGRAM YANG BELUM CUKUP DATA TIDAK MENAMPILKAN ANGKA NOL. Nol
         berarti "dihitung, hasilnya nol" — pernyataan yang sangat berbeda
         dari "belum bisa dihitung". Yang ditampilkan adalah statusnya.

    Parameter: $ringkasanDampak dari DashboardController::ringkasanDampak()
--}}
@php
    $r = $ringkasanDampak;

    $warnaStatus = [
        'Final' => 'bg-status-success/10 text-status-success',
        'Perlu Review' => 'bg-gold-light text-cahaya-primary',
        'Terhitung' => 'bg-primary-light text-cahaya-primary',
    ];
@endphp

<div class="bg-white rounded-2xl border border-border-soft shadow-sm p-5">
    <div class="flex flex-wrap items-baseline justify-between gap-2 mb-4">
        <h3 class="text-[15px] font-semibold text-text-main">Dampak Program</h3>
        <a href="{{ route('impact.index') }}" class="text-xs text-cahaya-secondary hover:underline">
            Buka Dampak Program &rarr;
        </a>
    </div>

    @if ($r['jumlah_program'] === 0)
        {{-- Belum ada program sama sekali. Tidak ada angka apa pun yang
             pantas ditampilkan di sini, termasuk nol. --}}
        <p class="text-sm text-text-secondary">
            Belum ada program dampak yang disusun. Modul ini menilai hasil program dari data yang sudah
            dicatat pendamping — peserta, pendampingan, pelatihan, omzet, dan baseline&ndash;endline.
        </p>
    @else
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
            <div>
                <p class="text-xs text-text-secondary">Program</p>
                <p class="text-lg font-bold text-text-main">{{ $r['jumlah_program'] }}</p>
                <p class="text-[11px] text-text-secondary mt-0.5">{{ $r['jumlah_final'] }} sudah final</p>
            </div>
            <div>
                <p class="text-xs text-text-secondary">Total Investasi</p>
                <p class="text-lg font-bold text-text-main">{{ rupiah($r['total_investasi']) }}</p>
                <p class="text-[11px] text-text-secondary mt-0.5">{{ $r['jumlah_outcome'] }} outcome tercatat</p>
            </div>
            <div>
                <p class="text-xs text-text-secondary">Peserta Terukur</p>
                <p class="text-lg font-bold text-text-main">{{ $r['peserta_terukur'] }}</p>
                <p class="text-[11px] text-text-secondary mt-0.5">punya baseline &amp; pembanding</p>
            </div>
            <div>
                <p class="text-xs text-text-secondary">Proxy Finansial</p>
                <p class="text-lg font-bold {{ $r['jumlah_proxy'] === 0 ? 'text-status-warning' : 'text-text-main' }}">
                    {{ $r['jumlah_proxy'] }}
                </p>
                <p class="text-[11px] text-text-secondary mt-0.5">
                    {{ $r['jumlah_proxy'] === 0 ? 'belum dikonfigurasi' : 'aktif dan bisa dipakai' }}
                </p>
            </div>
        </div>

        @if ($r['terakhir'])
            @php $h = $r['terakhir']; @endphp
            <div class="rounded-xl border border-border-soft p-4">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-xs text-text-secondary">Perhitungan terakhir</p>
                        <p class="text-sm font-medium text-text-main">{{ $h->program->nama ?? '-' }}</p>
                        <p class="text-[11px] text-text-secondary mt-0.5">
                            {{ $h->dihitung_at->translatedFormat('d M Y') }}
                            &bull; Social Value {{ rupiah($h->total_nilai_dampak) }}
                            &bull; Net {{ rupiah($h->nilai_sosial_bersih) }}
                        </p>
                    </div>
                    <div class="text-right">
                        <p class="text-2xl font-bold text-cahaya-primary leading-none">
                            {{ $h->rasio === null ? '—' : number_format($h->rasio, 2, ',', '.') }}
                        </p>
                        {{-- Lencana status MENEMPEL pada rasionya, bukan di
                             tempat lain. Yang mengutip angkanya harus ikut
                             membaca seberapa jauh angka itu sudah ditinjau. --}}
                        <span class="inline-block mt-1 text-[11px] font-semibold px-2 py-0.5 rounded-full
                                     {{ $warnaStatus[$h->status] ?? 'bg-surface-bg text-text-secondary' }}">
                            {{ $h->status }}
                        </span>
                    </div>
                </div>

                @unless ($h->sudahDisetujui())
                    <p class="text-[11px] text-text-secondary mt-3 pt-3 border-t border-border-soft">
                        Angka ini belum disetujui sebagai Final — belum layak dikutip keluar lembaga.
                    </p>
                @endunless
            </div>
        @else
            {{-- Program ada, tapi belum satu pun yang bisa dihitung. Inilah
                 tempat "Belum Cukup Data" harus muncul — bukan angka 0. --}}
            <div class="rounded-xl bg-gold-light border border-status-warning/40 p-4">
                <p class="text-sm font-semibold text-cahaya-primary">Belum Cukup Data</p>
                <p class="text-xs text-text-secondary mt-1">
                    Belum ada program yang datanya lengkap untuk dihitung, jadi tidak ada rasio SROI yang
                    bisa ditampilkan. Buka modul Dampak untuk melihat apa yang masih kurang pada
                    masing-masing program.
                </p>
            </div>
        @endif
    @endif
</div>
