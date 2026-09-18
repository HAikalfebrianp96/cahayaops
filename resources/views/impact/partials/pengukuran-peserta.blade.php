{{--
    BASELINE & ENDLINE PESERTA PROGRAM INI.

    ==========================================================================
    KENAPA DAFTAR INI ADA DI HALAMAN PROGRAM
    ==========================================================================

    Baseline dicatat per peserta, di modul Peserta — jauh dari halaman ini.
    Tapi di SINILAH orang baru menyadari bahwa baselinenya kurang: pesan
    "belum ada peserta dengan baseline dan endline yang lengkap" muncul tepat
    saat SROI menolak dihitung.

    Tanpa daftar dan tautannya, Admin harus menebak sendiri peserta mana yang
    perlu dilengkapi, lalu mencarinya satu per satu di modul lain. Itulah
    jarak yang membuat baseline tidak pernah terisi.

    Daftarnya dibatasi 10 nama — yang dibutuhkan di sini adalah titik masuk,
    bukan salinan daftar peserta.
--}}
<div class="bg-white border border-border-soft rounded-2xl shadow-sm p-5">
    <h3 class="text-sm font-semibold text-text-main mb-1">Baseline &amp; Endline Peserta</h3>
    <p class="text-[11px] text-text-secondary mb-4">
        Perubahan usaha hanya bisa dinyatakan kalau ada titik awal dan titik pembanding. Keduanya dicatat
        pendamping saat kunjungan biasa — tidak ada data yang perlu diinput ulang di modul ini.
    </p>

    @if ($pengukuran['total'] === 0)
        <p class="text-sm text-text-secondary">
            Belum ada peserta yang tercakup periode program ini. Periksa kembali rentang periodenya, atau
            batasan pendamping kalau program ini memang dijalankan per wilayah.
        </p>
    @else
        <div class="grid grid-cols-3 gap-3 mb-4">
            <div class="rounded-xl bg-surface-bg p-3">
                <p class="text-lg font-bold text-text-main">{{ $pengukuran['total'] }}</p>
                <p class="text-[11px] text-text-secondary mt-0.5">Peserta program</p>
            </div>
            <div class="rounded-xl bg-surface-bg p-3">
                <p class="text-lg font-bold text-text-main">{{ $pengukuran['punya_baseline'] }}</p>
                <p class="text-[11px] text-text-secondary mt-0.5">Punya baseline</p>
            </div>
            <div class="rounded-xl {{ $pengukuran['terukur'] > 0 ? 'bg-primary-light' : 'bg-gold-light' }} p-3">
                <p class="text-lg font-bold {{ $pengukuran['terukur'] > 0 ? 'text-cahaya-primary' : 'text-status-warning' }}">
                    {{ $pengukuran['terukur'] }}
                </p>
                <p class="text-[11px] text-text-secondary mt-0.5">Terukur penuh</p>
            </div>
        </div>

        @if ($pengukuran['belum_baseline']->isNotEmpty())
            <div class="rounded-xl border border-border-soft p-4 mb-3">
                <p class="text-xs font-semibold text-text-main mb-2">
                    Belum punya baseline
                    <span class="font-normal text-text-secondary">({{ $pengukuran['belum_baseline']->count() }} pertama)</span>
                </p>
                <div class="flex flex-wrap gap-2">
                    @foreach ($pengukuran['belum_baseline'] as $p)
                        <a href="{{ route('perkembangan.create', $p) }}"
                           class="text-[11px] px-2.5 py-1 rounded-full border border-border-soft text-text-main hover:border-cahaya-secondary hover:text-cahaya-secondary transition">
                            {{ $p->nama_peserta }} +
                        </a>
                    @endforeach
                </div>
                <p class="text-[11px] text-text-secondary mt-2">
                    Klik nama untuk langsung mencatat baseline peserta tersebut.
                </p>
            </div>
        @endif

        @if ($pengukuran['belum_pembanding']->isNotEmpty())
            <div class="rounded-xl border border-border-soft p-4">
                <p class="text-xs font-semibold text-text-main mb-2">
                    Punya baseline, belum punya pembanding
                    <span class="font-normal text-text-secondary">({{ $pengukuran['belum_pembanding']->count() }} pertama)</span>
                </p>
                <div class="flex flex-wrap gap-2">
                    @foreach ($pengukuran['belum_pembanding'] as $p)
                        <a href="{{ route('perkembangan.index', $p) }}"
                           class="text-[11px] px-2.5 py-1 rounded-full border border-border-soft text-text-main hover:border-cahaya-secondary hover:text-cahaya-secondary transition">
                            {{ $p->nama_peserta }} &rarr;
                        </a>
                    @endforeach
                </div>
                <p class="text-[11px] text-text-secondary mt-2">
                    Cukup satu monitoring untuk bisa dibandingkan — endline tidak harus menunggu program selesai.
                </p>
            </div>
        @endif

        @if ($pengukuran['belum_baseline']->isEmpty() && $pengukuran['belum_pembanding']->isEmpty())
            <p class="text-sm text-text-secondary">
                Seluruh peserta program ini sudah punya baseline dan pembanding.
            </p>
        @endif
    @endif
</div>
