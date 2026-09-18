@extends('layouts.app')

@section('title', 'Dashboard Pimpinan')

@section('content')
{{--
    DASHBOARD PIMPINAN — SUMMARY + NAVIGATION.

    Halaman ini adalah TITIK MASUK, bukan halaman laporan. Isinya hanya dua
    kartu ekosistem yang setara — Cahaya Preneur dan Koperasi Cahaya — masing
    -masing dengan ringkasan KPI dan satu pintu ke modulnya. Rincian
    operasional (tabel transaksi, invoice, pelanggan, pengeluaran, rincian
    unit usaha) TIDAK ditampilkan di sini; semuanya tetap ada di modul
    masing-masing.

    Kedua kartu memakai struktur yang SAMA PERSIS — judul, subjudul, 3 KPI
    utama, 3 KPI pendukung, satu baris penutup, lalu CTA yang dikunci di dasar
    kartu (mt-auto). Itulah yang membuat keduanya sama tinggi dan terbaca
    sebagai dua pintu yang setara, bukan satu yang lebih penting.

    SELURUH ANGKA memakai variabel yang sudah disediakan DashboardController.
    Tidak ada perhitungan baru di view ini, dan controller tidak diubah.
--}}
<div class="space-y-5">

    {{-- ================= 1. HEADER + FILTER (SATU KARTU) ================= --}}
    {{--
        Header dan filter digabung dalam SATU kartu putih. Ini menyelesaikan
        dua hal sekaligus:

        1) Logo. Latar logo putih menyatu di dalam file JPEG-nya (tidak ada
           aset transparan - public/images/logo-cahayaos.png ternyata JPEG
           yang diganti ekstensi). Sebelumnya logo dibungkus LINGKARAN PUTIH
           di atas latar halaman abu muda, jadi yang terlihat justru cakram
           putih mengambang. Di atas kartu putih, pembungkusnya tidak
           diperlukan lagi: logo dipasang polos tanpa container, tanpa border,
           tanpa padding, dan latarnya benar-benar menyatu. Sumbernya persegi
           (2951x2951) di kotak persegi, jadi object-contain hanya menskala -
           proporsinya utuh dan tidak ada artefak. Berkas asetnya TIDAK
           disentuh karena dipakai juga di sidebar.

        2) Ruang. Dua kartu terpisah yang bertumpuk memakan tinggi tanpa
           menambah makna. Digabung, judul dan filter terbaca sebagai satu
           kepala halaman dan kedua kartu ekosistem naik lebih dekat ke atas.
    --}}
    <div class="bg-white border border-border-soft rounded-xl px-6 py-5">

        <div class="flex items-center gap-4">
            <img src="{{ asset('images/logo-cahayaos.jpg') }}"
                 alt="CahayaOS"
                 class="h-12 w-12 object-contain flex-shrink-0">
            <div class="min-w-0">
                <p class="text-[10px] font-semibold text-text-secondary tracking-[0.18em] uppercase">CahayaOS</p>
                <h2 class="text-2xl font-bold text-text-main leading-tight mt-0.5">Dashboard Pimpinan</h2>
                <p class="text-sm text-text-secondary mt-1">Ringkasan kinerja ekosistem CahayaOS</p>
            </div>
        </div>

        {{-- FILTER PERIODE (GLOBAL) - satu filter untuk KEDUA ekosistem.
             Parameter & logic perhitungannya tidak diubah; keduanya membaca
             $tahun/$bulan yang sama. --}}
        <form method="GET" action="{{ route('dashboard') }}"
              class="mt-5 pt-5 border-t border-border-soft flex flex-wrap items-end gap-x-3 gap-y-3">
            <div>
                <label class="block text-[11px] font-medium text-text-secondary mb-1.5">Tahun</label>
                <select name="tahun" class="h-9 rounded-lg border border-border-soft bg-white px-3 text-sm text-text-main">
                    @for ($t = now()->year - 1; $t <= now()->year; $t++)
                        <option value="{{ $t }}" @selected($tahun === $t)>{{ $t }}</option>
                    @endfor
                </select>
            </div>
            <div>
                <label class="block text-[11px] font-medium text-text-secondary mb-1.5">Bulan</label>
                <select name="bulan" class="h-9 rounded-lg border border-border-soft bg-white px-3 text-sm text-text-main">
                    <option value="">Semua Bulan</option>
                    @foreach ($bulanOptions as $num => $nama)
                        <option value="{{ $num }}" @selected($bulan === $num)>{{ $nama }}</option>
                    @endforeach
                </select>
            </div>

            <button type="submit"
                    class="h-9 bg-cahaya-primary text-white text-sm font-medium px-5 rounded-lg hover:opacity-90 transition">
                Terapkan
            </button>
            <a href="{{ route('dashboard') }}"
               class="h-9 inline-flex items-center text-sm text-text-secondary px-2 hover:text-text-main transition">Reset</a>

            <p class="w-full sm:w-auto sm:ml-auto text-xs text-text-secondary sm:self-end sm:pb-2">
                Periode <span class="font-medium text-text-main">{{ $bulan ? $bulanOptions[$bulan] . ' ' . $tahun : 'seluruh bulan ' . $tahun }}</span>
            </p>
        </form>
    </div>

    {{-- ================= 2b. MENUNGGU PERSETUJUAN ANDA =================

         Satu-satunya bagian halaman ini yang meminta TINDAKAN; sisanya
         bacaan. Karena itu ia ditaruh sebelum ringkasan kedua ekosistem.

         Hanya muncul kalau memang ada yang menunggu. Kartu kosong bertuliskan
         "tidak ada" setiap hari akan berhenti dibaca, dan pada hari ia benar-
         benar berisi, tidak ada yang menyadarinya.
         ================================================================= --}}
    @if ($menungguPersetujuan->isNotEmpty())
        <div class="bg-white border-2 border-cahaya-accent/40 rounded-2xl shadow-sm p-6">
            <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
                <div>
                    <h3 class="text-[15px] font-semibold text-text-main">
                        <span aria-hidden="true">📋</span> Menunggu Persetujuan Anda
                        <span class="text-text-secondary font-normal">({{ $menungguPersetujuan->count() }})</span>
                    </h3>
                    <p class="text-xs text-text-secondary mt-0.5">
                        Hasil perhitungan SROI yang sudah selesai dihitung dan menunggu ditetapkan sebagai Final.
                    </p>
                </div>
            </div>

            <div class="space-y-3">
                @foreach ($menungguPersetujuan as $hitung)
                    <div class="border border-border-soft rounded-xl p-4">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="text-[15px] font-semibold text-text-main">
                                    {{ $hitung->program->nama ?? 'Program tidak diketahui' }}
                                </p>
                                <p class="text-xs text-text-secondary mt-0.5">
                                    Dihitung {{ $hitung->created_at?->translatedFormat('d F Y') ?? '—' }}
                                </p>
                            </div>
                            <span class="flex-none text-[11px] font-medium px-2 py-0.5 rounded-full bg-gold-light text-cahaya-accent">
                                {{ $hitung->status }}
                            </span>
                        </div>

                        <div class="mt-4">
                            {{-- Menuju halaman programnya, tempat seluruh angka
                                 dan asumsinya bisa diperiksa SEBELUM disetujui.
                                 Tombol "Setujui" sengaja TIDAK ditaruh di sini:
                                 menyetujui angka dampak tanpa membukanya dulu
                                 bukan persetujuan, hanya klik. --}}
                            <a href="{{ route('impact.show', $hitung->impact_program_id) }}"
                               class="inline-flex items-center justify-center rounded-lg bg-cahaya-primary px-4 py-2.5 text-sm font-semibold text-white hover:opacity-90 transition">
                                Periksa &amp; Setujui
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- ================= 3. DUA EKOSISTEM ================= --}}
    {{--
        items-stretch + h-full + flex-col membuat kedua kartu sama tinggi
        berapa pun panjang isinya, dan mt-auto menahan CTA tetap sejajar di
        dasar keduanya. Di bawah md kartu otomatis menumpuk vertikal.

        Aksen dipakai HANYA sebagai penanda identitas (garis tipis di atas
        kartu), bukan mewarnai seluruh kartu: cahaya-secondary untuk Cahaya
        Preneur, koperasi-accent untuk Koperasi — keduanya token yang memang
        sudah dipakai CahayaOS.
    --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-5 items-stretch">

        {{-- ---------- CAHAYA PRENEUR ---------- --}}
        <section class="h-full bg-white rounded-xl border border-border-soft border-t-4 border-t-cahaya-secondary p-6 flex flex-col">
            <h3 class="text-[13px] font-bold text-cahaya-secondary tracking-[0.12em] uppercase">Cahaya Preneur</h3>
            <p class="text-sm text-text-secondary mt-2">Program pemberdayaan dan pengembangan UMKM.</p>

            {{-- KPI UTAMA --}}
            <div class="grid grid-cols-3 gap-4 mt-6">
                <div class="min-w-0">
                    <p class="text-[26px] font-bold text-cahaya-primary leading-none tabular-nums">{{ $ringkasan['total_peserta'] }}</p>
                    <p class="text-xs text-text-secondary mt-2 leading-snug">Total Peserta</p>
                </div>
                <div class="min-w-0">
                    <p class="text-[26px] font-bold text-cahaya-primary leading-none tabular-nums">{{ $ringkasan['peserta_aktif'] }}</p>
                    <p class="text-xs text-text-secondary mt-2 leading-snug">Peserta Aktif</p>
                </div>
                <div class="min-w-0">
                    <p class="text-[19px] lg:text-[22px] font-bold text-cahaya-primary leading-none tabular-nums break-words">{{ rupiah($ringkasan['total_omzet']) }}</p>
                    <p class="text-xs text-text-secondary mt-2 leading-snug">Total Omzet</p>
                </div>
            </div>

            {{-- KPI PENDUKUNG — "Omzet Meningkat" adalah satu-satunya indikator
                 Dampak Program yang ikut ditampilkan sebagai angka; sisanya
                 diringkas jadi satu baris penutup di bawah. --}}
            <div class="grid grid-cols-3 gap-4 mt-6 pt-5 border-t border-border-soft">
                <div class="min-w-0">
                    <p class="text-lg font-semibold text-text-main leading-none tabular-nums">{{ $ringkasan['total_pendamping'] }}</p>
                    <p class="text-xs text-text-secondary mt-2 leading-snug">Pendamping</p>
                </div>
                <div class="min-w-0">
                    <p class="text-lg font-semibold text-text-main leading-none tabular-nums">{{ $ringkasan['total_pendampingan'] }}</p>
                    <p class="text-xs text-text-secondary mt-2 leading-snug">Kegiatan Pendampingan</p>
                </div>
                <div class="min-w-0">
                    <p class="text-lg font-semibold text-status-success leading-none tabular-nums">{{ $impactProgram['omzet_meningkat'] }}</p>
                    <p class="text-xs text-text-secondary mt-2 leading-snug">Omzet Meningkat</p>
                </div>
            </div>

            {{-- BARIS PENUTUP --}}
            <p class="text-xs text-text-secondary mt-6">
                Kenaikan omzet terukur:
                @if ($impactProgram['ada_data_kenaikan'])
                    <span class="font-semibold {{ $impactProgram['kenaikan_omzet'] >= 0 ? 'text-status-success' : 'text-status-danger' }}">
                        {{ $impactProgram['kenaikan_omzet'] >= 0 ? '+' : '-' }}{{ rupiah(abs($impactProgram['kenaikan_omzet'])) }}
                    </span>
                    @if ($impactProgram['persen_kenaikan'] !== null)
                        ({{ $impactProgram['persen_kenaikan'] >= 0 ? '+' : '' }}{{ $impactProgram['persen_kenaikan'] }}%)
                    @endif
                @else
                    <span class="font-medium text-text-main">belum cukup data</span>
                @endif
            </p>

            {{-- CTA — route EXISTING. Monitoring Omzet adalah halaman Cahaya
                 Preneur yang memang boleh dibuka Pimpinan. Halaman Laporan &
                 Peserta sengaja TIDAK ditautkan: otorisasinya menolak Pimpinan
                 (403), jadi tautannya akan buntu. --}}
            <div class="mt-auto pt-6">
                <a href="{{ route('omzet.index') }}"
                   class="inline-flex items-center text-[13px] font-semibold text-cahaya-secondary hover:underline">
                    Lihat Detail &rarr;
                </a>
            </div>
        </section>

        {{-- ---------- KOPERASI CAHAYA ---------- --}}
        <section class="h-full bg-white rounded-xl border border-border-soft border-t-4 border-t-koperasi-accent p-6 flex flex-col">
            <h3 class="text-[13px] font-bold text-koperasi-primary tracking-[0.12em] uppercase">Koperasi Cahaya</h3>
            <p class="text-sm text-text-secondary mt-2">Ekosistem usaha dan pengelolaan Koperasi Cahaya.</p>

            {{-- KPI UTAMA --}}
            <div class="grid grid-cols-3 gap-4 mt-6">
                <div class="min-w-0">
                    <p class="text-[26px] font-bold text-koperasi-primary leading-none tabular-nums">{{ $koperasiRingkasan['total_anggota'] }}</p>
                    <p class="text-xs text-text-secondary mt-2 leading-snug">Total Anggota</p>
                </div>
                <div class="min-w-0">
                    <p class="text-[26px] font-bold text-koperasi-primary leading-none tabular-nums">{{ $koperasiRingkasan['anggota_aktif'] }}</p>
                    <p class="text-xs text-text-secondary mt-2 leading-snug">Anggota Aktif</p>
                </div>
                <div class="min-w-0">
                    <p class="text-[19px] lg:text-[22px] font-bold text-koperasi-primary leading-none tabular-nums break-words">{{ rupiah($koperasiRingkasan['total_penjualan']) }}</p>
                    <p class="text-xs text-text-secondary mt-2 leading-snug">Total Penjualan</p>
                </div>
            </div>

            {{-- KPI PENDUKUNG
                 Simpanan Anggota ditambahkan di sini, bukan sebagai kartu
                 besar: yang dinilai Pimpinan lebih dulu tetap anggota dan
                 penjualan. Kolomnya menjadi empat, dan dua-per-baris di
                 ponsel supaya angkanya tidak tergencet. --}}
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mt-6 pt-5 border-t border-border-soft">
                <div class="min-w-0">
                    <p class="text-lg font-semibold text-text-main leading-none tabular-nums">{{ $koperasiRingkasan['total_unit'] }}</p>
                    <p class="text-xs text-text-secondary mt-2 leading-snug">Unit Usaha</p>
                </div>
                <div class="min-w-0">
                    <p class="text-lg font-semibold text-text-main leading-none tabular-nums break-words">{{ rupiah($kinerjaKoperasi['laba_kotor']) }}</p>
                    <p class="text-xs text-text-secondary mt-2 leading-snug">Laba Kotor</p>
                </div>
                <div class="min-w-0">
                    <p class="text-lg font-semibold text-text-main leading-none tabular-nums break-words">{{ rupiah($kinerjaKoperasi['pengeluaran_total']) }}</p>
                    <p class="text-xs text-text-secondary mt-2 leading-snug">Pengeluaran</p>
                </div>
                <div class="min-w-0">
                    <p class="text-lg font-semibold text-text-main leading-none tabular-nums break-words">{{ rupiah($koperasiRingkasan['total_simpanan']) }}</p>
                    <p class="text-xs text-text-secondary mt-2 leading-snug">Simpanan Anggota</p>
                </div>
            </div>

            @if (($koperasiRingkasan['simpanan_terarsip'] ?? 0) > 0)
                {{-- Keterangan yang sama dengan halaman Keuangan dan Laporan
                     Koperasi. Angka simpanan yang berdiri tanpa penjelasan
                     akan dibaca Pimpinan sebagai modal anggota yang masih
                     ada — padahal sebagian pemiliknya sudah tidak lagi
                     menjadi anggota. --}}
                {{-- Pemenggalan barisnya sengaja dijaga: pergantian baris di
                     Blade menjadi spasi sungguhan di HTML, sehingga frasa yang
                     terbelah dua baris tidak lagi bisa dicari sebagai satu
                     kalimat — dan keterangan ini justru dijaga oleh test yang
                     mencarinya. --}}
                <p class="text-xs text-text-secondary mt-3">
                    Termasuk {{ rupiah($koperasiRingkasan['simpanan_terarsip']) }} milik anggota yang sudah diarsipkan
                    — dananya masih dipegang koperasi, tetapi pemiliknya tidak lagi terhitung sebagai anggota.
                </p>
            @endif

            {{-- BARIS PENUTUP --}}
            <p class="text-xs text-text-secondary mt-6">
                @if ($adaAktivitasKoperasi)
                    Laba bersih:
                    <span class="font-semibold {{ $koperasiRingkasan['laba_bersih'] >= 0 ? 'text-status-success' : 'text-status-danger' }}">
                        {{ rupiah($koperasiRingkasan['laba_bersih']) }}
                    </span>
                @else
                    Belum ada transaksi pada periode ini.
                @endif
            </p>

            {{-- CTA — route EXISTING. Laporan Koperasi dijaga
                 isAdmin() || isPimpinan(), jadi aman untuk Pimpinan. Dashboard
                 Koperasi & Keuangan SENGAJA tidak ditautkan: keduanya
                 Admin-only (403). --}}
            <div class="mt-auto pt-6 flex flex-wrap items-center gap-x-4 gap-y-2">
                <a href="{{ route('koperasi.laporan.index') }}"
                   class="inline-flex items-center text-[13px] font-semibold text-koperasi-primary hover:underline">
                    Lihat Detail &rarr;
                </a>
                <span class="h-3.5 w-px bg-border-soft" aria-hidden="true"></span>
                {{-- Pintasan tambahan. Penjualan Koperasi memang tidak lagi ada
                     di sidebar Pimpinan, tapi route, controller, dan
                     otorisasinya tidak pernah dihapus - jadi ini memakai route
                     EXISTING yang sama, bukan jalur baru. --}}
                <a href="{{ route('koperasi.penjualan.index') }}"
                   class="inline-flex items-center text-[13px] font-semibold text-koperasi-primary hover:underline">
                    Penjualan Koperasi &rarr;
                </a>
            </div>
        </section>

    </div>

    {{--
        DAMPAK & SROI — ditaruh DI BAWAH kedua kartu ekosistem, bukan di
        dalamnya. Dampak adalah kesimpulan atas keduanya, bukan salah satu
        cabangnya, dan Pimpinan-lah yang menyetujui angkanya sebagai Final.

        Rasio SROI di sini SELALU membawa lencana statusnya. Tanpa itu, angka
        yang terlihat sekilas di dashboard akan dikutip sebagai kesimpulan
        padahal mungkin baru hitungan pertama yang belum ditinjau siapa pun.
    --}}
    @includeWhen(isset($ringkasanDampak), 'impact.partials.ringkasan-dashboard')
</div>
@endsection
