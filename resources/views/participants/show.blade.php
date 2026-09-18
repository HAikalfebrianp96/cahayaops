@extends('layouts.app')

@section('title', 'Detail Peserta')

@section('content')
@php
    $isAdmin = auth()->user()->isAdmin();
    $isPimpinan = auth()->user()->isPimpinan();

    // ============================================================
    // RINGKASAN KPI — SEMUA dihitung dari relasi yang SUDAH DI-LOAD
    // controller (omzetReports, pendampinganRecords, trainingRecords,
    // legalitasRecords, hppCalculations). TIDAK ADA query baru,
    // TIDAK ADA rumus baru.
    // ============================================================
    $totalOmzet = $participant->omzetReports->sum('omzet');
    $omzetTertinggi = $participant->omzetReports->max('omzet');
    $omzetRataRata = $participant->omzetReports->avg('omzet');
    $jumlahPendampingan = $participant->pendampinganRecords->count();
    $jumlahPelatihan = $participant->trainingRecords->count();
    $legalitasSudahAda = $participant->legalitasRecords->where('status', 'Sudah Ada')->count();
    $legalitasTotal = $participant->legalitasRecords->count();
    $jumlahHpp = $participant->hppCalculations->count();

    $inisial = collect(explode(' ', trim($participant->nama_peserta)))->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode('');
@endphp

<div class="space-y-5 max-w-5xl">

    {{-- 1. PROFILE HEADER --}}
    <div class="bg-white border border-border-soft rounded-2xl shadow-sm p-6">
        <div class="flex items-start justify-between flex-wrap gap-4">
            <div class="flex items-center gap-4">
                <div class="h-14 w-14 rounded-full bg-cahaya-primary text-white flex items-center justify-center text-lg font-bold flex-shrink-0">
                    {{ $inisial ?: '?' }}
                </div>
                <div>
                    <h2 class="text-xl font-bold text-text-main">{{ $participant->nama_peserta }}</h2>
                    <p class="text-sm text-text-secondary">{{ $participant->nama_usaha }}</p>
                    <div class="flex items-center gap-2 mt-2 flex-wrap">
                        <span class="text-[11px] font-medium px-2 py-0.5 rounded-full
                            {{ match($participant->status) {
                                'Aktif' => 'bg-primary-light text-status-success',
                                'Graduasi' => 'bg-gold-light text-cahaya-accent',
                                default => 'bg-gray-100 text-text-secondary',
                            } }}">
                            {{ $participant->status }}
                        </span>
                        {{-- Lencana tindak lanjut: alasan peserta ini muncul di
                             Tugas Saya, terbaca langsung di kepala halaman. --}}
                        <span class="text-[11px] font-medium px-2 py-0.5 rounded-full {{ $statusTindakLanjut['warna'] }}"
                              title="{{ $statusTindakLanjut['alasan'] }}">
                            {{ $statusTindakLanjut['status'] }}
                        </span>
                        <span class="text-[11px] text-text-secondary">&bull; Pendamping: {{ $participant->pendamping->nama ?? '—' }}</span>
                        <span class="text-[11px] text-text-secondary">&bull; {{ $participant->kategori_usaha }}</span>
                    </div>
                    {{-- Ditulis sebagai teks, bukan hanya title/hover: di ponsel
                         tidak ada hover, dan alasan inilah yang menentukan apa
                         yang harus dilakukan pendamping berikutnya. --}}
                    <div class="mt-1.5">
                        <span class="text-[11px] text-text-secondary">{{ $statusTindakLanjut['alasan'] }}</span>
                    </div>
                </div>
            </div>

            <div class="flex gap-2 items-center flex-wrap">
                {{-- Aksi ditentukan Policy di backend ($bolehUbahPeserta /
                     $bolehHapusPeserta), BUKAN oleh role yang ditebak di
                     Blade — jadi tombol yang tampil selalu sama persis dengan
                     yang benar-benar diizinkan controller. --}}
                @if ($bolehUbahPeserta)
                    <a href="{{ route('participants.edit', $participant) }}"
                       class="bg-cahaya-primary text-white text-[13px] font-medium px-4 py-2 rounded-lg hover:opacity-90 transition">
                        Edit Data Peserta
                    </a>
                @endif
                @if ($bolehHapusPeserta)
                    <form method="POST" action="{{ route('participants.destroy', $participant) }}"
                          onsubmit="return confirm('Pindahkan peserta ini ke arsip? Data tidak hilang dan bisa dipulihkan.');">
                        @csrf
                        @method('DELETE')
                        <button type="submit"
                                class="text-[13px] font-medium text-status-danger border border-status-danger/30 px-4 py-2 rounded-lg hover:bg-status-danger/5 transition">
                            Hapus
                        </button>
                    </form>
                @endif
                @if ($isAdmin)
                    <a href="{{ route('participants.index') }}" class="text-[13px] text-text-secondary px-3 py-2 hover:text-text-main">← Daftar</a>
                @elseif ($isPimpinan)
                    <a href="{{ route('dashboard') }}" class="text-[13px] text-status-info px-3 py-2 hover:underline">← Dashboard Pimpinan</a>
                @else
                    <a href="{{ route('binaan.index') }}" class="text-[13px] text-status-info px-3 py-2 hover:underline">← Binaan Saya</a>
                @endif
            </div>
        </div>
    </div>

    {{-- 2. RINGKASAN PESERTA (KPI) --}}
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-3">
        <div class="bg-white border border-border-soft rounded-xl p-4">
            <p class="text-[11px] text-text-secondary mb-1">Total Omzet</p>
            <p class="text-base font-bold text-text-main">{{ rupiah($totalOmzet) }}</p>
        </div>
        <div class="bg-white border border-border-soft rounded-xl p-4">
            <p class="text-[11px] text-text-secondary mb-1">Pendampingan</p>
            <p class="text-base font-bold text-text-main">{{ $jumlahPendampingan }}</p>
        </div>
        <div class="bg-white border border-border-soft rounded-xl p-4">
            <p class="text-[11px] text-text-secondary mb-1">Pelatihan</p>
            <p class="text-base font-bold text-text-main">{{ $jumlahPelatihan }}</p>
        </div>
        <div class="bg-white border border-border-soft rounded-xl p-4">
            <p class="text-[11px] text-text-secondary mb-1">Legalitas</p>
            <p class="text-base font-bold text-text-main">{{ $legalitasSudahAda }}/{{ $legalitasTotal }}</p>
        </div>
        <div class="bg-white border border-border-soft rounded-xl p-4">
            <p class="text-[11px] text-text-secondary mb-1">Perhitungan HPP</p>
            <p class="text-base font-bold text-text-main">{{ $jumlahHpp }}</p>
        </div>
    </div>

    {{-- ============================================================
         STATUS PERKEMBANGAN.

         Ditaruh DI LUAR tab dengan sengaja: ini kesimpulan yang perlu
         terlihat lebih dulu, sebelum orang memutuskan tab mana yang mau
         dibuka.

         Statusnya SELALU disertai alasan dalam kalimat biasa. Angka atau
         label yang tidak bisa dijelaskan ke pendamping di lapangan hanya
         memindahkan tanggung jawab menilai dari manusia ke rumus yang tidak
         bisa dibantah — itulah sebabnya tidak ada skor tersembunyi di sini.
         ============================================================ --}}
    <div class="bg-white border border-border-soft rounded-2xl shadow-sm p-6">
        <div class="flex items-start justify-between gap-3 flex-wrap mb-4">
            <div>
                <h3 class="text-[15px] font-semibold text-text-main">Status Perkembangan</h3>
                <p class="text-xs text-text-secondary mt-0.5">
                    Dihitung dari data yang tersimpan — tidak ada penilaian yang disembunyikan.
                </p>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                <span class="text-[11px] font-medium px-2.5 py-1 rounded-full {{ $rapor['warna'] }}">
                    {{ $rapor['status'] }}
                </span>
                <span class="text-[11px] font-medium px-2.5 py-1 rounded-full {{ $rapor['follow_up']['warna'] }}">
                    Follow up: {{ $rapor['follow_up']['status'] }}
                </span>
            </div>
        </div>

        {{-- ALASAN — inilah yang membuat statusnya bisa dipertanggungjawabkan. --}}
        <div class="bg-surface-bg border border-border-soft rounded-xl p-4 mb-5">
            <p class="text-[11px] font-medium text-text-secondary mb-2">Alasan status ini:</p>
            <ul class="space-y-1">
                @foreach ($rapor['alasan'] as $alasan)
                    <li class="text-sm text-text-main">&bull; {{ $alasan }}</li>
                @endforeach
            </ul>
        </div>

        {{-- INDIKATOR --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 text-sm">
            <div>
                <p class="text-[11px] text-text-secondary mb-1">Kelengkapan Data</p>
                <p class="font-semibold text-text-main">
                    {{ $rapor['kelengkapan']['terisi'] }}/{{ $rapor['kelengkapan']['total'] }}
                    <span class="text-text-secondary font-normal">({{ $rapor['kelengkapan']['persen'] }}%)</span>
                </p>
                @if (! $rapor['kelengkapan']['lengkap'])
                    <p class="text-[11px] text-status-warning mt-0.5">
                        Belum: {{ implode(', ', $rapor['kelengkapan']['kurang']) }}
                    </p>
                @endif
            </div>

            <div>
                <p class="text-[11px] text-text-secondary mb-1">Pengembangan Kapasitas</p>
                <p class="font-semibold text-text-main">
                    {{ $rapor['kapasitas']['pendampingan'] }} pendampingan
                </p>
                <p class="text-[11px] text-text-secondary mt-0.5">
                    {{ $rapor['kapasitas']['kunjungan'] }} kunjungan &bull;
                    {{ $rapor['kapasitas']['konsultasi'] }} konsultasi &bull;
                    {{ $rapor['kapasitas']['pelatihan'] }} pelatihan
                </p>
            </div>

            <div>
                <p class="text-[11px] text-text-secondary mb-1">Perkembangan Usaha</p>
                @if ($rapor['usaha']['omzet_terbaru'] === null)
                    <p class="font-semibold text-text-secondary">Belum ada laporan omzet</p>
                @else
                    <p class="font-semibold text-text-main">{{ rupiah($rapor['usaha']['omzet_terbaru']) }}</p>
                    <p class="text-[11px] text-text-secondary mt-0.5">
                        {{ $rapor['usaha']['periode_terbaru'] }}
                        @if ($rapor['usaha']['pertumbuhan_persen'] !== null)
                            &bull;
                            <span class="{{ $rapor['usaha']['pertumbuhan_persen'] >= 0 ? 'text-status-success' : 'text-status-danger' }}">
                                {{ $rapor['usaha']['pertumbuhan_persen'] > 0 ? '+' : '' }}{{ $rapor['usaha']['pertumbuhan_persen'] }}%
                            </span>
                        @endif
                    </p>
                @endif
            </div>

            <div>
                <p class="text-[11px] text-text-secondary mb-1">Legalitas</p>
                @if (! $rapor['legalitas']['relevan'])
                    {{-- Tidak semua UMKM butuh seluruh legalitas. Tanpa dokumen
                         terdaftar BUKAN berarti bermasalah. --}}
                    <p class="font-semibold text-text-secondary">Belum ada yang didaftarkan</p>
                    <p class="text-[11px] text-text-secondary mt-0.5">Tidak semua usaha membutuhkannya.</p>
                @else
                    <p class="font-semibold text-text-main">
                        {{ $rapor['legalitas']['sudah_ada'] }}/{{ $rapor['legalitas']['terdaftar'] }} selesai
                    </p>
                    <p class="text-[11px] text-text-secondary mt-0.5">
                        {{ collect($rapor['legalitas']['daftar'])->pluck('jenis')->implode(', ') }}
                    </p>
                @endif
            </div>
        </div>
    </div>

    {{-- ============================================================
         TAB NAVIGATION.

         SELURUH panel dirender ke HTML, yang berpindah hanya visibilitasnya.
         Ini disengaja: isi halaman tetap bisa dicari lewat Ctrl+F browser,
         tetap terbawa saat halaman dicetak, dan tidak ada satu pun data yang
         hilang dari DOM hanya karena tabnya sedang tidak terbuka.

         Tab aktif disimpan di localStorage per peserta, jadi setelah menyimpan
         perubahan (yang selalu kembali ke halaman ini) operator mendarat di
         tab yang sama — bukan terlempar ke Profil setiap kali.
         ============================================================ --}}
    <div class="bg-white border border-border-soft rounded-2xl shadow-sm p-2 overflow-x-auto">
        <div class="flex gap-1 min-w-max" id="tabPeserta">
            @foreach ([
                'profil' => 'Profil',
                'usaha' => 'Data Usaha',
                'omzet' => 'Omzet',
                'perkembangan' => 'Perkembangan',
                'pendampingan' => 'Pendampingan',
                'pelatihan' => 'Pelatihan',
                'legalitas' => 'Legalitas',
                'hpp' => 'HPP',
                'koperasi' => 'Koperasi',
                'riwayat' => 'Riwayat Perubahan',
            ] as $kunci => $judul)
                <button type="button" data-tab="{{ $kunci }}"
                        class="tab-tombol inline-flex items-center px-3.5 py-2 rounded-lg text-[13px] font-medium whitespace-nowrap transition text-text-secondary hover:bg-surface-bg">
                    {{ $judul }}
                </button>
            @endforeach
        </div>
    </div>

    {{-- ===================== TAB: PROFIL ===================== --}}
    <div data-panel="profil" class="tab-panel space-y-5">
    <div class="bg-white border border-border-soft rounded-2xl shadow-sm p-6">
        <h3 class="text-[15px] font-semibold text-text-main mb-4">Data Peserta</h3>

        <dl class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-3 text-sm">
            @foreach ([
                'Nomor Identitas' => $participant->nomor_identitas,
                'Nomor HP' => $participant->nomor_hp,
                'Email' => $participant->email,
                'Alamat' => $participant->alamat,
                'Kecamatan' => $participant->kecamatan,
                'Kelurahan/Desa' => $participant->kelurahan,
                'Mulai Program' => $participant->tanggal_mulai_program?->format('d-m-Y'),
                'Status Peserta' => $participant->status,
            ] as $label => $nilai)
                <div>
                    <dt class="text-[11px] text-text-secondary">{{ $label }}</dt>
                    <dd class="text-text-main">{{ $nilai ?: '—' }}</dd>
                </div>
            @endforeach
        </dl>

        {{-- CATATAN INTERNAL — hanya sampai ke halaman ini kalau penggunanya
             Admin atau Pendamping peserta ini; Pimpinan (read-only) sengaja
             tidak melihatnya. --}}
        @if ($participant->catatan_internal && $bolehUbahPeserta)
            <div class="mt-4 pt-4 border-t border-border-soft">
                <p class="text-[11px] text-text-secondary mb-1">
                    Catatan Internal <span class="text-text-secondary">(tidak tampil di laporan yang dibagikan)</span>
                </p>
                <p class="text-sm text-text-main whitespace-pre-line">{{ $participant->catatan_internal }}</p>
            </div>
        @endif
    </div>
    </div>

    {{-- ===================== TAB: DATA USAHA ===================== --}}
    <div data-panel="usaha" class="tab-panel space-y-5">
    <div class="bg-white border border-border-soft rounded-2xl shadow-sm p-6">
        <h3 class="text-[15px] font-semibold text-text-main mb-4">Data Usaha</h3>

        <dl class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-3 text-sm">
            @foreach ([
                'Nama Usaha' => $participant->nama_usaha,
                'Alamat Usaha' => $participant->alamat_usaha,
                'Jenis Usaha' => $participant->kategori_usaha,
                'Produk' => $participant->produk,
                'Tahun Berdiri' => $participant->tahun_berdiri,
                'Status Usaha' => $participant->status_usaha,
            ] as $label => $nilai)
                <div>
                    <dt class="text-[11px] text-text-secondary">{{ $label }}</dt>
                    <dd class="text-text-main">{{ $nilai ?: '—' }}</dd>
                </div>
            @endforeach
        </dl>

        @if ($bolehUbahPeserta)
            <div class="mt-5 pt-4 border-t border-border-soft">
                <a href="{{ route('participants.edit', $participant) }}"
                   class="text-xs text-status-info hover:underline">Perbaiki data usaha &rarr;</a>
            </div>
        @endif
    </div>
    </div>

    {{-- ===================== TAB: KOPERASI ===================== --}}
    <div data-panel="koperasi" class="tab-panel space-y-5">
    <div class="bg-white border border-border-soft rounded-2xl shadow-sm p-6">
        <h3 class="text-[15px] font-semibold text-text-main mb-4">Status Koperasi Cahaya</h3>

        {{-- ============================================================
             STATUS KOPERASI.

             Keanggotaan bersifat OPSIONAL: peserta Cahaya Preneur TIDAK
             otomatis menjadi anggota koperasi.

             Tombol "Jadikan Anggota Koperasi" hanya dirender untuk yang
             memang berwenang mendaftarkan anggota (Admin & Tim Koperasi).
             Ia membawa peserta ini ke form pendaftaran dengan data dasar
             SUDAH terisi — tidak ada identitas yang diketik ulang.
             ============================================================ --}}
        <div class="mt-5 pt-4 border-t border-border-soft">
            <div class="flex items-center justify-between gap-3 flex-wrap">
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="text-[11px] text-text-secondary">Keanggotaan Koperasi Cahaya:</span>
                    @if ($participant->koperasiMember)
                        <span class="text-[11px] font-medium px-2 py-0.5 rounded-full bg-primary-light text-status-success">
                            Anggota &bull; {{ $participant->koperasiMember->nomor_anggota }}
                        </span>
                        <span class="text-[11px] text-text-secondary">
                            &bull; Bergabung {{ optional($participant->koperasiMember->tanggal_bergabung)->format('d-m-Y') ?: '—' }}
                            &bull; {{ $participant->koperasiMember->status }}
                        </span>
                    @else
                        <span class="text-[11px] font-medium px-2 py-0.5 rounded-full bg-gray-100 text-text-secondary">
                            Belum menjadi anggota koperasi
                        </span>
                    @endif
                </div>

                @can('create', \App\Models\KoperasiMember::class)
                    @if ($participant->koperasiMember)
                        <a href="{{ route('koperasi.anggota.show', $participant->koperasiMember) }}"
                           class="text-xs text-status-info hover:underline">Lihat Data Keanggotaan &rarr;</a>
                    @else
                        <a href="{{ route('koperasi.anggota.jadikan', $participant) }}"
                           class="text-xs font-medium bg-cahaya-secondary text-white px-3 py-1.5 rounded-lg hover:opacity-90 transition">
                            Jadikan Anggota Koperasi
                        </a>
                    @endif
                @endcan
            </div>
        </div>

    </div>
    </div>

    {{-- ===================== TAB: OMZET ===================== --}}
    <div data-panel="omzet" class="tab-panel space-y-5">

    {{-- LINK LAPORAN OMZET --}}
    <div class="bg-white border border-border-soft rounded-2xl shadow-sm p-6">
        <h3 class="text-[13px] font-semibold text-text-main mb-2">Link Laporan Omzet</h3>
        <p class="text-xs text-text-secondary mb-3">
            Kirimkan link ini ke {{ $participant->nama_peserta }} lewat WhatsApp. Peserta bisa mengisi laporan omzet tanpa perlu login.
        </p>
        <div class="flex flex-col sm:flex-row gap-2">
            <input id="linkLaporanOmzet" type="text" readonly
                   value="{{ route('omzet.public.show', $participant->report_token) }}"
                   onclick="this.select();"
                   class="flex-1 rounded-lg border border-border-soft px-3 py-2 text-sm bg-surface-bg text-text-secondary">
            <button type="button" onclick="salinLinkLaporan()"
                    class="bg-status-info text-white text-sm font-medium px-4 py-2 rounded-lg hover:opacity-90 transition whitespace-nowrap">
                Salin Link
            </button>
            @if ($tautanWa = $participant->tautanWhatsappLaporanOmzet())
                {{--
                    DEEP-LINK, bukan integrasi API. Tombol ini MEMBUKA WhatsApp
                    dengan pesan yang sudah tertulis; yang menekan kirim tetap
                    petugas. Labelnya sengaja berbunyi "Kirim lewat WhatsApp"
                    dan bukan "Kirim WhatsApp" — sistem tidak boleh terdengar
                    seolah pesannya sudah sampai, karena ia tidak tahu.
                --}}
                <a href="{{ $tautanWa }}" target="_blank" rel="noopener"
                   class="bg-status-success text-white text-sm font-medium px-4 py-2 rounded-lg hover:opacity-90 transition whitespace-nowrap text-center">
                    Kirim lewat WhatsApp
                </a>
            @endif
        </div>
        <p id="salinBerhasil" class="text-xs text-status-success mt-2 hidden">Link berhasil disalin!</p>
        <p id="salinGagal" class="text-xs text-status-warning mt-2 hidden">
            Tidak bisa menyalin otomatis di browser ini — link sudah kami pilih (highlight), tekan lama lalu pilih "Salin"/"Copy" secara manual.
        </p>
    </div>

    {{-- 5. OMZET / PERKEMBANGAN USAHA --}}
    @php
        // Izin mencatat & memperbaiki perkembangan usaha diambil dari Policy
        // yang SAMA dengan yang dipakai OmzetMonitoringController — bukan
        // tebakan role di Blade.
        $bolehKelolaOmzet = auth()->user()->can('createFor', [\App\Models\OmzetReport::class, $participant]);
    @endphp

    <div class="bg-white border border-border-soft rounded-2xl shadow-sm p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-[15px] font-semibold text-text-main">Perkembangan Omzet</h3>
            @if ($bolehKelolaOmzet)
                <a href="{{ route('omzet.create', $participant) }}" class="text-sm text-cahaya-primary font-medium hover:underline">
                    + Catat Omzet
                </a>
            @endif
        </div>

        @if ($participant->omzetReports->isEmpty())
            <p class="text-sm text-text-secondary italic">Peserta ini belum pernah mengirim laporan omzet.</p>
        @else
            <canvas id="riwayatOmzetChart" height="80" class="mb-5"></canvas>

            <div class="grid grid-cols-3 gap-4 mb-5 text-sm">
                <div class="bg-surface-bg rounded-xl p-3 text-center">
                    <p class="text-[11px] text-text-secondary mb-1">Omzet Terbaru</p>
                    <p class="font-bold text-text-main">{{ rupiah($participant->omzetReports->first()->omzet ?? 0) }}</p>
                </div>
                <div class="bg-surface-bg rounded-xl p-3 text-center">
                    <p class="text-[11px] text-text-secondary mb-1">Omzet Tertinggi</p>
                    <p class="font-bold text-text-main">{{ rupiah($omzetTertinggi) }}</p>
                </div>
                <div class="bg-surface-bg rounded-xl p-3 text-center">
                    <p class="text-[11px] text-text-secondary mb-1">Rata-rata Omzet</p>
                    <p class="font-bold text-text-main">{{ rupiah($omzetRataRata) }}</p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-text-secondary border-b border-border-soft">
                            <th class="py-2 font-medium">Periode</th>
                            <th class="py-2 font-medium">Omzet</th>
                            <th class="py-2 font-medium">Catatan</th>
                            @if ($bolehKelolaOmzet) <th class="py-2 font-medium">Aksi</th> @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($participant->omzetReports as $report)
                            <tr class="border-b border-border-soft last:border-0">
                                <td class="py-2">{{ $report->periodeLabel() }}</td>
                                <td class="py-2">{{ rupiah($report->omzet) }}</td>
                                <td class="py-2 text-text-secondary">{{ $report->catatan ?: '—' }}</td>
                                @if ($bolehKelolaOmzet)
                                    <td class="py-2 space-x-2 whitespace-nowrap">
                                        <a href="{{ route('omzet.edit', $report) }}" class="text-xs text-status-info hover:underline">Edit</a>
                                        <form method="POST" action="{{ route('omzet.destroy', $report) }}" class="inline"
                                              onsubmit="return confirm('Arsipkan laporan omzet periode ini? Periodenya bisa diisi ulang nanti.');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-xs text-status-danger hover:underline">Arsipkan</button>
                                        </form>
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <a href="{{ route('omzet.index') }}" class="text-xs text-status-info hover:underline mt-4 inline-block">Lihat Monitoring Omzet &rarr;</a>
    </div>

    </div>

    {{-- ===================== TAB: PENDAMPINGAN ===================== --}}
    <div data-panel="pendampingan" class="tab-panel space-y-5">

    {{-- 6. PENDAMPINGAN --}}
    <div class="bg-white border border-border-soft rounded-2xl shadow-sm p-6">
        <h3 class="text-[15px] font-semibold text-text-main mb-4">Riwayat Pendampingan</h3>

        @if ($participant->pendampinganRecords->isEmpty())
            <p class="text-sm text-text-secondary italic mb-4">Belum ada catatan pendampingan.</p>
        @else
            <div class="space-y-3 mb-5">
                @foreach ($participant->pendampinganRecords as $record)
                    {{-- Aksi per catatan ditentukan PendampinganRecordPolicy —
                         Admin semua catatan, Pendamping hanya catatan milik
                         binaannya sendiri, Pimpinan tidak sama sekali. --}}
                    @php $bolehUbahCatatan = auth()->user()->can('update', $record); @endphp

                    <div class="border border-border-soft rounded-xl p-3.5 text-sm">
                        <div class="flex items-start justify-between gap-3">
                            <p class="font-medium text-text-main">{{ $record->tanggal->format('d-m-Y') }} — {{ $record->jenis_kegiatan }}</p>
                            <div class="flex items-center gap-2 flex-shrink-0">
                                <span class="text-xs text-text-secondary">{{ $record->pendamping->nama ?? '—' }}</span>
                                @if ($bolehUbahCatatan)
                                    <a href="{{ route('pendampingan.edit', $record) }}"
                                       class="text-xs text-status-info hover:underline">Edit</a>
                                    <form method="POST" action="{{ route('pendampingan.destroy', $record) }}" class="inline"
                                          onsubmit="return confirm('Arsipkan catatan pendampingan ini?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-xs text-status-danger hover:underline">Arsipkan</button>
                                    </form>
                                @endif
                            </div>
                        </div>
                        <p class="text-text-secondary mt-1">{{ $record->materi }}</p>
                        @if ($record->catatan)
                            <p class="text-xs text-text-secondary mt-1">Catatan: {{ $record->catatan }}</p>
                        @endif

                        {{-- SIKLUS TINDAK LANJUT:
                             Permasalahan -> Tindakan -> Hasil -> Rencana + tanggalnya. --}}
                        @if ($record->kendala)
                            <p class="text-xs text-status-warning mt-1">Permasalahan: {{ $record->kendala }}</p>
                        @endif
                        @if ($record->tindakan)
                            <p class="text-xs text-text-secondary mt-1">Tindakan: {{ $record->tindakan }}</p>
                        @endif
                        @if ($record->hasil)
                            <p class="text-xs text-status-success mt-1">Hasil: {{ $record->hasil }}</p>
                        @endif
                        @if ($record->tindak_lanjut)
                            <p class="text-xs text-status-info mt-1">Rencana tindak lanjut: {{ $record->tindak_lanjut }}</p>
                        @endif
                        @if ($record->tanggal_follow_up)
                            <p class="text-xs mt-1 {{ $record->followUpTerlambat() ? 'text-status-warning font-medium' : 'text-text-secondary' }}">
                                Follow up: {{ $record->tanggal_follow_up->format('d-m-Y') }}
                                @if ($record->followUpTerlambat()) &bull; terlambat @endif
                            </p>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif

        {{-- Izin menambah catatan diambil dari Policy yang SAMA dengan yang
             dipakai PendampinganController::store() — bukan tebakan role. --}}
        @can('createFor', [\App\Models\PendampinganRecord::class, $participant])
            {{-- id dipakai tautan "Catat Pendampingan" dari halaman Tugas Saya:
                 /participants/5#catat-pendampingan membuka tab yang benar DAN
                 membentangkan formnya sekaligus, jadi pendamping mendarat
                 langsung di kolom isian — bukan di halaman yang masih harus
                 dicari dulu bagian mananya. --}}
            <details id="catat-pendampingan" class="border-t border-border-soft pt-4">
                <summary class="text-sm font-medium text-cahaya-primary cursor-pointer">+ Tambah Catatan Pendampingan</summary>

                <form method="POST" action="{{ route('pendampingan.store', $participant) }}" class="mt-4 space-y-3">
                    @csrf
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-text-main mb-1">Tanggal</label>
                            <input type="date" name="tanggal" required class="w-full rounded-lg border border-border-soft px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-status-info/30">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-text-main mb-1">Jenis Kegiatan</label>
                            <select name="jenis_kegiatan" required class="w-full rounded-lg border border-border-soft px-3 py-2 text-sm">
                                <option value="">— Pilih —</option>
                                @foreach ($jenisKegiatanOptions as $jenis)
                                    <option value="{{ $jenis }}">{{ $jenis }}</option>
                                @endforeach
                            </select>
                        </div>

                        @if ($isAdmin)
                            <div class="md:col-span-2">
                                <label class="block text-xs font-medium text-text-main mb-1">Pendamping yang Melakukan</label>
                                <select name="pendamping_id" class="w-full rounded-lg border border-border-soft px-3 py-2 text-sm">
                                    <option value="">— Tidak ditentukan —</option>
                                    @foreach ($pendampings as $p)
                                        <option value="{{ $p->id }}" @selected($participant->pendamping_id === $p->id)>{{ $p->nama }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-text-main mb-1">Materi/Kegiatan</label>
                        <textarea name="materi" required rows="2" class="w-full rounded-lg border border-border-soft px-3 py-2 text-sm">{{ old('materi') }}</textarea>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-text-main mb-1">Catatan <span class="text-text-secondary">(boleh kosong)</span></label>
                        <textarea name="catatan" rows="2" class="w-full rounded-lg border border-border-soft px-3 py-2 text-sm">{{ old('catatan') }}</textarea>
                    </div>
                    {{-- SIKLUS TINDAK LANJUT — semuanya boleh kosong, tapi kalau
                         diisi akan membentuk alur kerja yang bisa dipantau:
                         Permasalahan -> Tindakan -> Hasil -> Rencana + tanggal. --}}
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-text-main mb-1">Permasalahan <span class="text-text-secondary">(boleh kosong)</span></label>
                            <textarea name="kendala" rows="2" placeholder="Contoh: penjualan menurun" class="w-full rounded-lg border border-border-soft px-3 py-2 text-sm">{{ old('kendala') }}</textarea>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-text-main mb-1">Tindakan <span class="text-text-secondary">(boleh kosong)</span></label>
                            <textarea name="tindakan" rows="2" placeholder="Contoh: konsultasi pemasaran digital" class="w-full rounded-lg border border-border-soft px-3 py-2 text-sm">{{ old('tindakan') }}</textarea>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-text-main mb-1">Hasil <span class="text-text-secondary">(boleh kosong)</span></label>
                            <textarea name="hasil" rows="2" placeholder="Contoh: peserta mulai memakai WhatsApp Business" class="w-full rounded-lg border border-border-soft px-3 py-2 text-sm">{{ old('hasil') }}</textarea>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-text-main mb-1">Rencana Tindak Lanjut <span class="text-text-secondary">(boleh kosong)</span></label>
                            <textarea name="tindak_lanjut" rows="2" class="w-full rounded-lg border border-border-soft px-3 py-2 text-sm">{{ old('tindak_lanjut') }}</textarea>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-text-main mb-1">Tanggal Follow Up <span class="text-text-secondary">(boleh kosong)</span></label>
                            <input type="date" name="tanggal_follow_up" value="{{ old('tanggal_follow_up') }}"
                                   class="w-full rounded-lg border border-border-soft px-3 py-2 text-sm">
                            <p class="text-[11px] text-text-secondary mt-1">Diisi kalau perlu dikunjungi lagi — akan muncul di Dashboard Anda.</p>
                        </div>
                    </div>

                    <button type="submit" class="bg-cahaya-primary text-white text-sm font-medium px-5 py-2 rounded-lg hover:opacity-90 transition">
                        Simpan Catatan
                    </button>
                </form>
            </details>
        @endcan
    </div>

    </div>

    {{-- ===================== TAB: PELATIHAN ===================== --}}
    <div data-panel="pelatihan" class="tab-panel space-y-5">

    {{-- 7. PELATIHAN --}}
    @php
        // Pelatihan kini boleh dikelola Pendamping untuk binaannya sendiri,
        // bukan lagi Admin-only. Izinnya diambil dari TrainingRecordPolicy —
        // Policy yang sama dengan yang menjaga controllernya.
        $bolehKelolaPelatihan = auth()->user()->can('createFor', [\App\Models\TrainingRecord::class, $participant]);
    @endphp

    <div class="bg-white border border-border-soft rounded-2xl shadow-sm p-6">
        <h3 class="text-[15px] font-semibold text-text-main mb-4">Riwayat Pelatihan</h3>

        @if ($participant->trainingRecords->isEmpty())
            <p class="text-sm text-text-secondary italic mb-4">Belum ada data pelatihan.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm mb-4">
                    <thead>
                        <tr class="text-left text-text-secondary border-b border-border-soft">
                            <th class="py-2 font-medium">Tanggal</th>
                            <th class="py-2 font-medium">Nama Pelatihan</th>
                            <th class="py-2 font-medium">Penyelenggara</th>
                            <th class="py-2 font-medium">Status</th>
                            @if ($bolehKelolaPelatihan) <th class="py-2 font-medium">Aksi</th> @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($participant->trainingRecords as $training)
                            <tr class="border-b border-border-soft last:border-0">
                                <td class="py-2">{{ $training->tanggal->format('d-m-Y') }}</td>
                                <td class="py-2">{{ $training->nama_pelatihan }}</td>
                                <td class="py-2">{{ $training->penyelenggara ?: '—' }}</td>
                                <td class="py-2">
                                    @if ($bolehKelolaPelatihan)
                                        {{-- Dropdown ini menembak route 'training.status' yang
                                             TERPISAH dari form Edit lengkap: ia cuma mengirim satu
                                             field, jadi kalau diarahkan ke 'training.update'
                                             validasi form lengkap akan menolaknya. --}}
                                        <form method="POST" action="{{ route('training.status', $training) }}" class="inline-flex items-center gap-1">
                                            @csrf
                                            @method('PUT')
                                            <select name="status" onchange="this.form.submit()" class="text-xs rounded-lg border border-border-soft px-1 py-1">
                                                @foreach ($statusPelatihanOptions as $status)
                                                    <option value="{{ $status }}" @selected($training->status === $status)>{{ $status }}</option>
                                                @endforeach
                                            </select>
                                        </form>
                                    @else
                                        <span class="text-xs px-2 py-0.5 rounded-full {{ $training->status === 'Selesai' ? 'bg-primary-light text-status-success' : 'bg-gray-100 text-text-secondary' }}">{{ $training->status }}</span>
                                    @endif
                                </td>
                                @if ($bolehKelolaPelatihan)
                                    <td class="py-2 space-x-2 whitespace-nowrap">
                                        <a href="{{ route('training.edit', $training) }}" class="text-xs text-status-info hover:underline">Edit</a>
                                        <form method="POST" action="{{ route('training.destroy', $training) }}" class="inline"
                                              onsubmit="return confirm('Arsipkan data pelatihan ini?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-status-danger hover:underline text-xs">Arsipkan</button>
                                        </form>
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        @if ($bolehKelolaPelatihan)
            <details class="border-t border-border-soft pt-4">
                <summary class="text-sm font-medium text-cahaya-primary cursor-pointer">+ Tambah Pelatihan</summary>
                <form method="POST" action="{{ route('training.store', $participant) }}" class="mt-4 space-y-3">
                    @csrf
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-text-main mb-1">Tanggal</label>
                            <input type="date" name="tanggal" required class="w-full rounded-lg border border-border-soft px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-text-main mb-1">Status</label>
                            <select name="status" required class="w-full rounded-lg border border-border-soft px-3 py-2 text-sm">
                                @foreach ($statusPelatihanOptions as $status)
                                    <option value="{{ $status }}">{{ $status }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-text-main mb-1">Nama Pelatihan</label>
                            <input type="text" name="nama_pelatihan" required placeholder="Contoh: Pelatihan Pembuatan NIB" class="w-full rounded-lg border border-border-soft px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-text-main mb-1">Penyelenggara <span class="text-text-secondary">(boleh kosong)</span></label>
                            <input type="text" name="penyelenggara" class="w-full rounded-lg border border-border-soft px-3 py-2 text-sm">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-text-main mb-1">Catatan <span class="text-text-secondary">(boleh kosong)</span></label>
                        <textarea name="catatan" rows="2" class="w-full rounded-lg border border-border-soft px-3 py-2 text-sm"></textarea>
                    </div>
                    <button type="submit" class="bg-cahaya-primary text-white text-sm font-medium px-5 py-2 rounded-lg hover:opacity-90 transition">
                        Simpan Pelatihan
                    </button>
                </form>
            </details>
        @endif
    </div>

    </div>

    {{-- ===================== TAB: LEGALITAS ===================== --}}
    <div data-panel="legalitas" class="tab-panel space-y-5">

    {{-- 8. LEGALITAS --}}
    <div class="bg-white border border-border-soft rounded-2xl shadow-sm p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-[15px] font-semibold text-text-main">Legalitas &amp; Sertifikasi</h3>
            @unless ($isPimpinan)
                <a href="{{ route('legalitas.create', $participant) }}" class="text-sm text-cahaya-primary font-medium hover:underline">
                    + Tambah Legalitas / Sertifikasi
                </a>
            @endunless
        </div>

        @if ($participant->legalitasRecords->isEmpty())
            <p class="text-sm text-text-secondary italic">Belum ada legalitas/sertifikasi yang tercatat untuk peserta ini.</p>
        @else
            <div class="space-y-2">
                @foreach ($participant->legalitasRecords as $rec)
                    <div class="flex items-center justify-between border border-border-soft rounded-xl px-4 py-3 text-sm">
                        <div>
                            <div class="flex items-center gap-2">
                                <span>{{ $rec->badgeIcon() }}</span>
                                <span class="font-medium text-text-main">{{ $rec->jenis_dokumen }}</span>
                                <span class="inline-block px-2 py-0.5 rounded-full text-xs {{ $rec->badgeClass() }}">
                                    {{ $rec->status }}
                                </span>
                            </div>
                            @if ($rec->nomor_dokumen)
                                <p class="text-xs text-text-secondary mt-1">Nomor: {{ $rec->nomor_dokumen }}</p>
                            @endif
                        </div>
                        <div class="flex items-center gap-3 whitespace-nowrap">
                            @if ($rec->file_path && ! $isPimpinan)
                                <a href="{{ route('legalitas.download', $rec) }}" class="text-xs text-status-info hover:underline">Lihat</a>
                            @endif
                            @unless ($isPimpinan)
                                <a href="{{ route('legalitas.edit', $rec) }}" class="text-xs text-cahaya-primary hover:underline">Edit</a>
                            @endunless
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    </div>

    {{-- ===================== TAB: HPP ===================== --}}
    <div data-panel="hpp" class="tab-panel space-y-5">

    {{-- 9. HPP --}}
    <div class="bg-white border border-border-soft rounded-2xl shadow-sm p-6">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-[15px] font-semibold text-text-main">HPP Produk</h3>
            @unless ($isPimpinan)
                <a href="{{ route('hpp.create', $participant) }}" class="text-sm text-cahaya-primary font-medium hover:underline">+ Tambah HPP</a>
            @endunless
        </div>

        @if ($participant->hppCalculations->isEmpty())
            <p class="text-sm text-text-secondary italic mb-3">Belum ada perhitungan HPP.</p>
        @else
            @php
                $jumlahProdukUnik = $participant->hppCalculations->pluck('nama_produk')->unique()->count();
            @endphp
            <p class="text-sm text-text-secondary mb-3">{{ $jumlahProdukUnik }} Produk &middot; {{ $participant->hppCalculations->count() }} Perhitungan</p>
        @endif

        <a href="{{ route('hpp.index', $participant) }}" class="inline-block text-sm bg-cahaya-primary text-white font-medium px-4 py-2 rounded-lg hover:opacity-90 transition">
            Lihat HPP
        </a>
    </div>

    </div>

    {{-- ===================== TAB: PERKEMBANGAN ===================== --}}
    <div data-panel="perkembangan" class="tab-panel space-y-5">

    {{--
        Ringkasan saja. Perbandingan penuh, riwayat pengukuran, dan formnya
        ada di halaman Perkembangan Usaha tersendiri — tab ini sengaja tidak
        menduplikasinya, supaya tidak ada dua tempat yang bisa menampilkan
        angka perubahan yang berbeda untuk peserta yang sama.
    --}}
    <div class="bg-white border border-border-soft rounded-2xl shadow-sm p-6">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-[15px] font-semibold text-text-main">Baseline &rarr; Endline</h3>
            @unless ($isPimpinan)
                <a href="{{ route('perkembangan.create', $participant) }}" class="text-sm text-cahaya-primary font-medium hover:underline">+ Catat Pengukuran</a>
            @endunless
        </div>

        {{-- ============================================================
             TIGA TAHAP, DIJELASKAN DENGAN BAHASA BIASA

             Sebelumnya baris ini hanya berbunyi "Baseline: ada, Endline:
             belum ada". Itu melaporkan keadaan tanpa pernah menjelaskan
             apa gunanya ketiga tahap ini atau apa langkah berikutnya —
             padahal justru itu yang menghentikan pendamping.

             Monitoring sengaja ikut ditampilkan. Di daftar pilihan form
             tahap ini memang ada, tapi di halaman peserta ia tidak pernah
             disebut, sehingga terbaca seolah cuma ada dua tahap.

             Tidak ada perhitungan baru: seluruhnya dibaca dari relasi
             measurements yang sudah dimuat.
             ============================================================ --}}
        @php
            $jumlahPengukuran = $participant->measurements->count();

            $tahapan = [
                ['jenis' => \App\Models\ParticipantMeasurement::BASELINE,
                 'arti' => 'Kondisi usaha sebelum didampingi. Jadi titik nol pembanding.'],
                ['jenis' => \App\Models\ParticipantMeasurement::MONITORING,
                 'arti' => 'Kondisi di tengah jalan. Boleh berkali-kali, boleh juga dilewati.'],
                ['jenis' => \App\Models\ParticipantMeasurement::ENDLINE,
                 'arti' => 'Kondisi setelah pendampingan. Dipakai menghitung perubahannya.'],
            ];

            foreach ($tahapan as $nomor => $tahap) {
                $tahapan[$nomor]['jumlah'] = $participant->measurements
                    ->where('jenis', $tahap['jenis'])->count();
            }

            $punyaBaseline = $tahapan[0]['jumlah'] > 0;
            $punyaEndline = $tahapan[2]['jumlah'] > 0;
        @endphp

        {{-- Satu kalimat pengantar sebelum ketiga tahapnya dirinci. Istilah
             SROI sengaja TIDAK disebut di sini: pendamping cukup mencatat
             kondisinya, sistem yang membandingkan. --}}
        <p class="text-sm text-text-secondary mb-3">
            Catat kondisi awal &rarr; perbarui perkembangan &rarr; catat kondisi akhir.
            Sistem yang membandingkan ketiganya.
        </p>

        <ol class="space-y-2.5 mb-4">
            @foreach ($tahapan as $nomor => $tahap)
                <li class="flex items-start gap-3">
                    <span class="mt-0.5 shrink-0 h-5 w-5 rounded-full text-[11px] font-semibold flex items-center justify-center
                                 {{ $tahap['jumlah'] > 0 ? 'bg-primary-light text-status-success' : 'bg-surface-bg text-text-secondary' }}">
                        {{ $tahap['jumlah'] > 0 ? '&check;' : $nomor + 1 }}
                    </span>
                    <div class="min-w-0">
                        <p class="text-sm text-text-main">
                            <span class="font-medium">{{ $tahap['jenis'] }}</span>
                            <span class="text-text-secondary">
                                &middot;
                                @if ($tahap['jumlah'] === 0)
                                    belum dicatat
                                @elseif ($tahap['jumlah'] === 1)
                                    sudah dicatat
                                @else
                                    {{ $tahap['jumlah'] }} kali dicatat
                                @endif
                            </span>
                        </p>
                        <p class="text-xs text-text-secondary leading-snug">{{ $tahap['arti'] }}</p>
                    </div>
                </li>
            @endforeach
        </ol>

        {{-- Satu kalimat langkah berikutnya, bukan daftar status. --}}
        @if (! $punyaBaseline)
            <p class="text-sm text-text-secondary mb-3">
                Mulai dari <span class="font-medium text-text-main">Baseline</span>. Tanpa titik awal,
                perubahan usaha peserta ini tidak bisa dihitung dan ia tidak ikut masuk laporan dampak.
            </p>
        @elseif (! $punyaEndline)
            <p class="text-sm text-text-secondary mb-3">
                Baseline sudah ada. <span class="font-medium text-text-main">Endline</span> dicatat saat
                pendampingan selesai — sesudah itu perubahannya baru bisa dihitung.
            </p>
        @else
            <p class="text-sm text-text-secondary mb-3">
                Baseline dan Endline lengkap &middot; {{ $jumlahPengukuran }} pengukuran tercatat.
                Perubahan usaha peserta ini sudah bisa dibaca.
            </p>
        @endif

        <a href="{{ route('perkembangan.index', $participant) }}" class="inline-block text-sm bg-cahaya-primary text-white font-medium px-4 py-2 rounded-lg hover:opacity-90 transition">
            Lihat Perkembangan
        </a>
    </div>

    </div>

    {{-- ===================== TAB: RIWAYAT PERUBAHAN ===================== --}}
    <div data-panel="riwayat" class="tab-panel space-y-5">

    {{-- 10. TIMELINE --}}
    <div class="bg-white border border-border-soft rounded-2xl shadow-sm p-6">
        <h3 class="text-[15px] font-semibold text-text-main mb-4">Timeline Perkembangan</h3>

        @if (empty($timeline))
            <p class="text-sm text-text-secondary italic">Belum ada aktivitas yang tercatat.</p>
        @else
            <ol class="relative border-l-2 border-status-info/25 pl-4 space-y-4">
                @foreach ($timeline as $event)
                    <li>
                        <span class="absolute -left-[7px] h-3 w-3 rounded-full bg-cahaya-accent border-2 border-white"></span>
                        <p class="text-xs text-text-secondary">{{ \Illuminate\Support\Carbon::parse($event['tanggal'])->translatedFormat('d F Y') }}</p>
                        <p class="text-sm text-text-main">{{ $event['judul'] }}</p>
                    </li>
                @endforeach
            </ol>
        @endif
    </div>

    {{-- ============================================================
         11. RIWAYAT PERUBAHAN.

         Dibaca dari Activity Log yang SUDAH ADA — bukan tabel histori
         kedua. Setiap perubahan peserta, catatan pendampingan, pelatihan,
         dan omzet memang sudah dicatat Observer lengkap dengan pelaku dan
         waktunya; yang kurang selama ini hanyalah cara membacanya per
         peserta.

         Hanya ditampilkan kepada yang boleh mengubah data peserta ini
         (Admin & Pendamping pemiliknya) — Pimpinan cukup melihat datanya,
         bukan siapa yang menyentuhnya.
         ============================================================ --}}
    @if ($bolehUbahPeserta)
        <div class="bg-white border border-border-soft rounded-2xl shadow-sm p-6">
            <h3 class="text-[15px] font-semibold text-text-main mb-1">Riwayat Perubahan</h3>
            <p class="text-xs text-text-secondary mb-4">
                20 perubahan terakhir pada data peserta ini beserta catatan pendampingan,
                pelatihan, dan laporan omzetnya.
            </p>

            @if ($riwayatPerubahan->isEmpty())
                <p class="text-sm text-text-secondary italic">Belum ada perubahan yang tercatat.</p>
            @else
                {{-- Dikelompokkan per TANGGAL supaya terbaca sebagai catatan
                     harian, bukan daftar teknis. Kalimatnya disusun
                     ActivityLog::kalimat() dari kolom `action` yang sudah
                     terstruktur; deskripsi teknis aslinya tetap ditampilkan
                     sebagai baris kecil di bawahnya, jadi tidak ada jejak
                     yang hilang demi kerapian. --}}
                <div class="space-y-5">
                    @foreach ($riwayatPerubahan->groupBy(fn ($log) => $log->created_at->toDateString()) as $tanggal => $logHarian)
                        <div>
                            <p class="text-[11px] font-semibold text-text-secondary uppercase tracking-wide mb-2">
                                {{ \Illuminate\Support\Carbon::parse($tanggal)->translatedFormat('d F Y') }}
                            </p>

                            <ul class="space-y-2.5">
                                @foreach ($logHarian as $log)
                                    <li class="flex items-start gap-3 text-sm border-b border-border-soft last:border-0 pb-2.5 last:pb-0">
                                        <span class="text-[11px] text-text-secondary whitespace-nowrap pt-0.5 w-12 flex-shrink-0">
                                            {{ $log->created_at->format('H:i') }}
                                        </span>
                                        <span class="min-w-0">
                                            <span class="text-text-main">{{ $log->kalimat() }}</span>
                                            <span class="block text-[11px] text-text-secondary">{{ $log->rincian() }}</span>
                                        </span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @endif
    </div>
</div>

{{-- ============================================================
     TAB SWITCHER.

     Sengaja sesederhana ini: tanpa library, tanpa route baru, tanpa state di
     server. Seluruh panel sudah ada di DOM — yang berpindah cuma kelas
     `hidden`, jadi Ctrl+F browser tetap menemukan isi tab manapun dan
     halaman ini tetap utuh saat dicetak.

     Kalau JavaScript mati, `tab-panel` TIDAK diberi `hidden` dari server,
     sehingga halaman jatuh kembali menjadi satu halaman panjang seperti
     sebelumnya — berkurang rapi, tapi tidak ada satu pun data yang hilang.
     ============================================================ --}}
<style>
    .tab-panel.tab-sembunyi { display: none; }
</style>
<script>
    (function () {
        const tombol = Array.from(document.querySelectorAll('.tab-tombol'));
        const panel = Array.from(document.querySelectorAll('.tab-panel'));
        if (!tombol.length || !panel.length) return;

        // Tab terakhir diingat PER PESERTA. Menyimpan satu nilai global akan
        // membuat membuka peserta lain mendarat di tab yang tidak relevan.
        const kunciSimpan = 'cahayaos.tabPeserta.{{ $participant->id }}';

        const KELAS_AKTIF = ['bg-cahaya-primary', 'text-white'];
        const KELAS_PASIF = ['text-text-secondary', 'hover:bg-surface-bg'];

        function buka(nama) {
            panel.forEach(p => p.classList.toggle('tab-sembunyi', p.dataset.panel !== nama));

            tombol.forEach(t => {
                const aktif = t.dataset.tab === nama;
                t.classList.toggle('bg-cahaya-primary', aktif);
                t.classList.toggle('text-white', aktif);
                t.classList.toggle('text-text-secondary', !aktif);
                t.classList.toggle('hover:bg-surface-bg', !aktif);
            });

            try { localStorage.setItem(kunciSimpan, nama); } catch (e) { /* mode privat */ }
        }

        tombol.forEach(t => t.addEventListener('click', () => buka(t.dataset.tab)));

        // Urutan penentuan tab awal: #hash di URL -> tab terakhir yang dibuka
        // -> Profil. Hash didahulukan supaya tautan seperti
        // /participants/5#pendampingan bisa langsung membuka tab yang dimaksud.
        let awal = 'profil';
        const hash = window.location.hash.replace('#', '');

        // Tautan dari Tugas Saya: buka tab Pendampingan sekaligus bentangkan
        // form pencatatannya.
        if (hash === 'catat-pendampingan') {
            awal = 'pendampingan';
            const form = document.getElementById('catat-pendampingan');
            if (form) {
                form.open = true;
                // Digulir setelah tab dibuka, kalau tidak posisinya dihitung
                // saat panelnya masih tersembunyi dan meleset.
                setTimeout(() => form.scrollIntoView({ behavior: 'smooth', block: 'center' }), 60);
            }
        } else if (hash && panel.some(p => p.dataset.panel === hash)) {
            awal = hash;
        } else {
            try {
                const tersimpan = localStorage.getItem(kunciSimpan);
                if (tersimpan && panel.some(p => p.dataset.panel === tersimpan)) awal = tersimpan;
            } catch (e) { /* mode privat */ }
        }

        buka(awal);
    })();
</script>

@vite('resources/js/chart.js')
<script>
    /* Chart.js kini dibundel Vite dan dimuat sebagai modul, yang selalu
       ditunda sampai halaman selesai diurai. Tanpa pembungkus ini, kode di
       bawah berjalan sebelum pustakanya ada dan grafiknya hilang tanpa
       pesan apa pun di halaman. */
    document.addEventListener('DOMContentLoaded', function () {

        @if ($participant->omzetReports->isNotEmpty())
            const riwayatCtx = document.getElementById('riwayatOmzetChart');
            const riwayatData = @json($participant->omzetReports->sortBy(fn($r) => $r->periode_tahun * 100 + $r->periode_bulan)->values());

            new Chart(riwayatCtx, {
                type: 'line',
                data: {
                    labels: riwayatData.map(r => r.periode_bulan + '/' + r.periode_tahun),
                    datasets: [{
                        label: 'Omzet (Rp)',
                        data: riwayatData.map(r => r.omzet),
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
        @endif

        function tampilkanSukses() {
            const info = document.getElementById('salinBerhasil');
            info.classList.remove('hidden');
            setTimeout(() => info.classList.add('hidden'), 2500);
        }

        function tampilkanFallback(input) {
            input.focus();
            input.select();
            input.setSelectionRange(0, 99999);

            try {
                const berhasil = document.execCommand('copy');
                if (berhasil) {
                    tampilkanSukses();
                    return;
                }
            } catch (e) {
                // lanjut ke pesan manual
            }

            const gagal = document.getElementById('salinGagal');
            gagal.classList.remove('hidden');
            setTimeout(() => gagal.classList.add('hidden'), 4000);
        }

        function salinLinkLaporan() {
            const input = document.getElementById('linkLaporanOmzet');

            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(input.value)
                    .then(tampilkanSukses)
                    .catch(() => tampilkanFallback(input));
            } else {
                tampilkanFallback(input);
            }
        }
    });
</script>
@endsection
