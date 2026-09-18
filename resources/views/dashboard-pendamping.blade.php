@extends('layouts.app')

@section('title', 'Dashboard Pendamping')

@section('content')
{{-- ============================================================
     DASHBOARD PENDAMPING.

     SELURUH angka di halaman ini berasal dari binaan Pendamping yang sedang
     login — controller sudah menyaringnya di level query, bukan di tampilan.
     Tidak ada satu pun data Pendamping lain di sini, dan tidak ada tautan ke
     modul Koperasi (Pendamping memang tidak berwenang di sana).
     ============================================================ --}}
<div class="space-y-6">

    {{-- HEADER --}}
    <div>
        <h2 class="text-[22px] font-bold text-text-main">
            Selamat datang, {{ explode(' ', auth()->user()->name)[0] ?? 'Pendamping' }} 👋
        </h2>
        <p class="text-[11px] text-text-secondary mt-1">
            CahayaOS — Sistem Terpadu Cahaya Preneur &amp; Koperasi Cahaya
        </p>
        <p class="text-sm text-text-secondary mt-1">
            Cahaya Preneur — Program Ekonomi YBM PLN UP3 Bengkulu
        </p>
    </div>

    {{-- ============================================================
         🎯 TUGAS SAYA — blok pertama setelah sapaan.

         Pertanyaan pertama seorang pendamping setiap pagi bukan "berapa
         jumlah binaan saya", melainkan "siapa yang harus saya temui hari
         ini". Karena itu jawabannya ditaruh sebelum apa pun yang lain, dan
         seluruh baloknya bisa ditekan langsung menuju daftar kerjanya.

         Angkanya dihitung FollowUpService::kelompokkan() — fungsi yang SAMA
         dengan halaman Tugas Saya, jadi keduanya tidak mungkin berbeda.
         ============================================================ --}}
    <a href="{{ route('follow-up.index') }}"
       class="block bg-white border border-border-soft rounded-2xl shadow-sm p-5 hover:border-cahaya-primary/40 transition">
        <div class="flex items-center justify-between gap-3 mb-4">
            <h3 class="text-[15px] font-semibold text-text-main">🎯 Tugas Saya</h3>
            <span class="text-xs font-medium text-status-info whitespace-nowrap">Buka daftar &rarr;</span>
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
            @foreach ([
                ['🔴', 'Perlu Segera', $ringkasTugas['perlu_segera'],
                 $ringkasTugas['perlu_segera'] > 0 ? 'text-status-danger' : 'text-text-main'],
                ['🟠', 'Perlu Ditindaklanjuti', $ringkasTugas['perlu_perhatian'],
                 $ringkasTugas['perlu_perhatian'] > 0 ? 'text-status-warning' : 'text-text-main'],
                ['📅', 'Jadwal Hari Ini', $ringkasTugas['hari_ini'],
                 $ringkasTugas['hari_ini'] > 0 ? 'text-status-info' : 'text-text-main'],
                ['🟢', 'Kondisi Normal', $ringkasTugas['normal'], 'text-status-success'],
            ] as [$tanda, $label, $nilai, $warna])
                <div class="border border-border-soft rounded-xl p-3">
                    <p class="text-[11px] text-text-secondary leading-tight mb-1">
                        <span aria-hidden="true">{{ $tanda }}</span> {{ $label }}
                    </p>
                    <p class="text-2xl font-bold {{ $warna }}">{{ $nilai }}</p>
                </div>
            @endforeach
        </div>

        @if ($ringkasTugas['perlu_segera'] === 0 && $ringkasTugas['perlu_perhatian'] === 0 && $ringkasTugas['hari_ini'] === 0)
            <p class="text-xs text-text-secondary mt-3">
                Tidak ada yang mendesak hari ini — seluruh binaan dalam kondisi normal.
            </p>
        @endif
    </a>

    {{-- TOMBOL CEPAT — pekerjaan yang paling sering dilakukan di lapangan --}}
    <div class="flex flex-wrap gap-2">
        <a href="{{ route('participants.create') }}"
           class="inline-flex items-center bg-cahaya-primary text-white text-sm font-medium px-4 py-2.5 rounded-lg hover:opacity-90 transition">
            + Tambah Binaan
        </a>
        <a href="{{ route('binaan.index') }}"
           class="inline-flex items-center bg-white border border-border-soft text-text-main text-sm font-medium px-4 py-2.5 rounded-lg hover:bg-surface-bg transition">
            Lihat Semua Binaan
        </a>
        <a href="{{ route('follow-up.index') }}"
           class="inline-flex items-center bg-white border border-border-soft text-text-main text-sm font-medium px-4 py-2.5 rounded-lg hover:bg-surface-bg transition">
            Follow Up Saya
            @if ($stats['prioritas'] > 0)
                <span class="ml-2 text-[11px] px-1.5 py-0.5 rounded-full bg-gold-light text-status-warning">{{ $stats['prioritas'] }}</span>
            @endif
        </a>
        <a href="{{ route('omzet.index') }}"
           class="inline-flex items-center bg-white border border-border-soft text-text-main text-sm font-medium px-4 py-2.5 rounded-lg hover:bg-surface-bg transition">
            Update Omzet
        </a>
        <a href="{{ route('laporan') }}"
           class="inline-flex items-center bg-white border border-border-soft text-text-main text-sm font-medium px-4 py-2.5 rounded-lg hover:bg-surface-bg transition">
            Laporan Saya
        </a>
        {{-- Tombol "Arsip Data" dilepas bersama menunya di sidebar.
             Dashboard Pendamping adalah papan pekerjaan hari ini; mengelola
             arsip bukan pekerjaan lapangan. Route dan izinnya tidak diubah. --}}
    </div>

    {{-- A. RINGKASAN --}}
    <div class="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-3">
        @foreach ([
            ['label' => 'Total Binaan', 'nilai' => $stats['total_binaan'], 'warna' => 'text-text-main'],
            ['label' => 'Binaan Aktif', 'nilai' => $stats['binaan_aktif'], 'warna' => 'text-status-success'],
            ['label' => 'Binaan Baru Bulan Ini', 'nilai' => $stats['binaan_baru'], 'warna' => 'text-status-info'],
            ['label' => 'Pendampingan Bulan Ini', 'nilai' => $stats['pendampingan_bulan_ini'], 'warna' => 'text-status-info'],
            ['label' => 'Kunjungan Usaha Bulan Ini', 'nilai' => $stats['kunjungan_bulan_ini'], 'warna' => 'text-cahaya-primary'],
            ['label' => 'Binaan Nonaktif', 'nilai' => $stats['binaan_nonaktif'], 'warna' => 'text-text-secondary'],
            ['label' => 'Perlu Ditindaklanjuti', 'nilai' => $stats['perlu_ditindaklanjuti'], 'warna' => $stats['perlu_ditindaklanjuti'] > 0 ? 'text-status-warning' : 'text-text-main'],
        ] as $kartu)
            <div class="bg-white border border-border-soft rounded-xl p-4">
                <p class="text-[11px] text-text-secondary mb-1 leading-tight">{{ $kartu['label'] }}</p>
                <p class="text-xl font-bold {{ $kartu['warna'] }}">{{ $kartu['nilai'] }}</p>
            </div>
        @endforeach
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <div class="bg-white border border-border-soft rounded-2xl shadow-sm p-5">
            <p class="text-[11px] text-text-secondary mb-1">Total Omzet Binaan Bulan Ini</p>
            <p class="text-2xl font-bold text-cahaya-primary">{{ rupiah($stats['omzet_bulan_ini']) }}</p>
            <p class="text-[11px] text-text-secondary mt-1">
                Dari laporan omzet binaan Anda pada {{ \App\Models\OmzetReport::namaBulan(now()->month) }} {{ now()->year }}.
            </p>
        </div>
        <div class="bg-white border border-border-soft rounded-2xl shadow-sm p-5">
            <p class="text-[11px] text-text-secondary mb-1">Binaan yang Sudah Jadi Anggota Koperasi</p>
            <p class="text-2xl font-bold text-cahaya-accent">{{ $stats['anggota_koperasi'] }}</p>
            <p class="text-[11px] text-text-secondary mt-1">
                Keanggotaan koperasi bersifat opsional — binaan tetap di Cahaya Preneur walau tidak menjadi anggota.
            </p>
        </div>
    </div>

    {{-- ============================================================
         TARGET BULAN INI.

         Memakai KpiService yang SAMA dengan halaman "KPI Saya", jadi angka
         di sini dan di sana tidak mungkin berbeda. Pesan otomatisnya dibuat
         dari jumlah indikator yang benar-benar ditargetkan — bukan kalimat
         umum yang selalu sama.
         ============================================================ --}}
    <div class="bg-white border border-border-soft rounded-2xl shadow-sm p-6">
        <div class="flex items-center justify-between mb-4 flex-wrap gap-2">
            <div>
                <h3 class="text-[15px] font-semibold text-text-main">Target Bulan Ini</h3>
                <p class="text-xs text-text-secondary mt-0.5">
                    {{ now()->translatedFormat('F Y') }} &bull;
                    {{-- Satu kosakata di seluruh sistem: "bawaan sistem", dan
                         disebutkan bahwa itu belum tentu kesepakatan. --}}
                    {{ $raporKpi['pakai_default']
                        ? 'memakai target bawaan sistem — belum ada target yang ditetapkan untuk Anda'
                        : 'target khusus untuk Anda' }}
                </p>
            </div>
            <a href="{{ route('kpi-pendamping.index') }}" class="text-xs text-status-info hover:underline">Lihat Rincian KPI &rarr;</a>
        </div>

        @if ($raporKpi['kpi'] === null)
            <p class="text-sm text-text-secondary italic">
                Belum ada target yang ditetapkan untuk bulan ini, jadi KPI belum bisa dihitung.
            </p>
        @else
            <div class="mb-5">
                <div class="flex items-center justify-between mb-1.5">
                    <span class="text-xs text-text-secondary">Progress KPI</span>
                    <span class="text-lg font-bold text-cahaya-primary">{{ $raporKpi['kpi'] }}%</span>
                </div>
                <div class="h-3 rounded-full bg-surface-bg overflow-hidden">
                    <div class="h-full rounded-full bg-cahaya-primary transition-all" style="width: {{ $raporKpi['kpi'] }}%"></div>
                </div>
                <p class="text-[11px] mt-2 {{ $raporKpi['belum_tercapai'] === 0 ? 'text-status-success' : 'text-text-secondary' }}">
                    @if ($raporKpi['belum_tercapai'] === 0)
                        Seluruh target bulan ini sudah tercapai.
                    @else
                        Anda sudah mencapai {{ $raporKpi['kpi'] }}% target bulan ini.
                        Masih ada {{ $raporKpi['belum_tercapai'] }} target yang belum tercapai.
                    @endif
                </p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                @foreach ($raporKpi['indikator'] as $i)
                    @continue(! $i['dihitung'])
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-xs text-text-main">{{ $i['label'] }}</span>
                            <span class="text-xs font-semibold text-text-main">{{ $i['aktual'] }} / {{ $i['target'] }}</span>
                        </div>
                        <div class="h-2 rounded-full bg-surface-bg overflow-hidden">
                            <div class="h-full rounded-full {{ $i['progress'] >= 100 ? 'bg-status-success' : 'bg-cahaya-primary' }}"
                                 style="width: {{ $i['progress'] }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- ============================================================
         DAFTAR PRIORITAS.

         SATU daftar kerja yang sudah diurutkan, bukan lima kartu terpisah
         yang harus dibaca satu-satu. Alasannya ikut ditampilkan supaya
         pendamping tahu KENAPA seseorang ada di atas — daftar prioritas
         tanpa alasan hanya memindahkan keputusan ke rumus yang tidak bisa
         dibantah.
         ============================================================ --}}
    <div class="bg-white border border-border-soft rounded-2xl shadow-sm p-6">
        <div class="flex items-center justify-between mb-1 flex-wrap gap-2">
            <h3 class="text-[15px] font-semibold text-text-main">
                Daftar Prioritas
                <span class="text-text-secondary font-normal">({{ $prioritas->count() }})</span>
            </h3>
            <a href="{{ route('follow-up.index') }}" class="text-xs text-status-info hover:underline">Buka Follow Up Saya &rarr;</a>
        </div>
        <p class="text-xs text-text-secondary mb-4">
            Binaan aktif yang punya pekerjaan tertinggal, diurutkan dari yang paling mendesak.
        </p>

        @if ($prioritas->isEmpty())
            <p class="text-sm text-text-secondary italic">
                Tidak ada yang tertinggal. Seluruh binaan aktif sudah didampingi, datanya lengkap,
                dan omzetnya terlaporkan.
            </p>
        @else
            <div class="space-y-2">
                @foreach ($prioritas->take(8) as $baris)
                    <div class="flex items-start justify-between gap-3 border border-border-soft rounded-xl p-3.5 text-sm">
                        <div class="min-w-0">
                            <a href="{{ route('participants.show', $baris['peserta']) }}"
                               class="font-medium text-text-main hover:underline">
                                {{ $baris['peserta']->nama_peserta }}
                            </a>
                            <p class="text-text-secondary text-xs mt-0.5">{{ $baris['peserta']->nama_usaha }}</p>
                            <ul class="mt-1.5 space-y-0.5">
                                @foreach ($baris['alasan'] as $alasan)
                                    <li class="text-[11px] text-text-secondary">&bull; {{ $alasan }}</li>
                                @endforeach
                            </ul>
                        </div>
                        <div class="flex flex-col items-end gap-1.5 flex-shrink-0">
                            <span class="text-[11px] font-medium px-2 py-0.5 rounded-full {{ $baris['follow_up']['warna'] }}">
                                {{ $baris['follow_up']['status'] }}
                            </span>
                            <a href="{{ route('participants.show', $baris['peserta']) }}#catat-pendampingan"
                               class="text-[11px] text-cahaya-primary hover:underline whitespace-nowrap">Catat Pendampingan</a>
                        </div>
                    </div>
                @endforeach
            </div>

            @if ($prioritas->count() > 8)
                <p class="text-xs text-text-secondary mt-3">
                    dan {{ $prioritas->count() - 8 }} binaan lainnya —
                    <a href="{{ route('follow-up.index') }}" class="text-status-info hover:underline">lihat semuanya</a>.
                </p>
            @endif
        @endif
    </div>

    {{-- D. PERLU TINDAK LANJUT --}}
    <div class="bg-white border border-border-soft rounded-2xl shadow-sm p-6">
        <h3 class="text-[15px] font-semibold text-text-main mb-1">Perlu Ditindaklanjuti</h3>
        <p class="text-xs text-text-secondary mb-4">
            Binaan aktif yang belum pernah didampingi, atau pendampingan terakhirnya lebih dari 30 hari lalu.
            Dihitung dari tanggal catatan pendampingan yang tersimpan — bukan dari jadwal atau perkiraan.
        </p>

        @if ($perluDitindaklanjuti->isEmpty())
            <p class="text-sm text-text-secondary italic">
                Semua binaan aktif sudah didampingi dalam 30 hari terakhir.
            </p>
        @else
            <div class="space-y-2">
                @foreach ($perluDitindaklanjuti->take(10) as $baris)
                    <div class="flex items-start justify-between gap-3 border border-border-soft rounded-xl p-3.5 text-sm">
                        <div class="min-w-0">
                            <a href="{{ route('participants.show', $baris['peserta']) }}"
                               class="font-medium text-text-main hover:underline">
                                {{ $baris['peserta']->nama_peserta }}
                            </a>
                            <p class="text-text-secondary mt-0.5">{{ $baris['peserta']->nama_usaha }}</p>
                        </div>
                        <span class="text-[11px] font-medium px-2 py-0.5 rounded-full flex-shrink-0
                            {{ $baris['terakhir'] === null ? 'bg-gold-light text-status-warning' : 'bg-surface-bg text-text-secondary' }}">
                            @if ($baris['terakhir'] === null)
                                Belum pernah didampingi
                            @else
                                Terakhir {{ $baris['terakhir']->format('d-m-Y') }} &bull; {{ $baris['hari'] }} hari lalu
                            @endif
                        </span>
                    </div>
                @endforeach
            </div>

            @if ($perluDitindaklanjuti->count() > 10)
                <p class="text-xs text-text-secondary mt-3">
                    dan {{ $perluDitindaklanjuti->count() - 10 }} binaan lainnya.
                </p>
            @endif
        @endif
    </div>

    {{-- FOLLOW UP TERJADWAL — dari rencana tindak lanjut yang ditulis sendiri --}}
    <div class="bg-white border border-border-soft rounded-2xl shadow-sm p-6">
        <h3 class="text-[15px] font-semibold text-text-main mb-1">Follow Up Terjadwal</h3>
        <p class="text-xs text-text-secondary mb-4">
            Catatan pendampingan yang punya rencana tindak lanjut dengan tanggal hari ini, sudah lewat,
            atau jatuh dalam sepekan ke depan.
        </p>

        @if ($perluFollowUp->isEmpty())
            <p class="text-sm text-text-secondary italic">Tidak ada tindak lanjut yang jatuh tempo.</p>
        @else
            <div class="space-y-2">
                @foreach ($perluFollowUp as $item)
                    <div class="flex items-start justify-between gap-3 border border-border-soft rounded-xl p-3.5 text-sm">
                        <div class="min-w-0">
                            <a href="{{ route('participants.show', $item->participant_id) }}"
                               class="font-medium text-text-main hover:underline">
                                {{ $item->participant->nama_peserta ?? 'Peserta' }}
                            </a>
                            <p class="text-text-secondary mt-0.5">{{ $item->tindak_lanjut ?: $item->materi }}</p>
                        </div>
                        <span class="text-[11px] font-medium px-2 py-0.5 rounded-full flex-shrink-0
                            {{ $item->followUpTerlambat() ? 'bg-gold-light text-status-warning' : 'bg-primary-light text-status-info' }}">
                            {{ $item->followUpTerlambat() ? 'Terlambat' : 'Jatuh tempo' }}
                            &bull; {{ $item->tanggal_follow_up->format('d-m-Y') }}
                        </span>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- ============================================================
         C. AKTIVITAS TERBARU — tertutup secara bawaan.

         Ini catatan yang BARU SAJA ditulis pendamping sendiri, jadi isinya
         sudah ia ketahui. Nilainya sebagai pengingat kecil, bukan sebagai
         pekerjaan hari ini — sementara ia menempati ruang sebanyak daftar
         tugas yang justru harus dikerjakan.

         Yang tetap terbuka di atas hanyalah yang menuntut tindakan:
         Prioritas, Perlu Ditindaklanjuti, dan Follow Up Terjadwal.
         ============================================================ --}}
    <details class="group bg-white border border-border-soft rounded-2xl shadow-sm">
        <summary class="flex items-center justify-between gap-3 p-6 cursor-pointer list-none">
            <div>
                <h3 class="text-[15px] font-semibold text-text-main">Aktivitas Terbaru</h3>
                <p class="text-xs text-text-secondary mt-0.5">Catatan pendampingan yang terakhir Anda tulis.</p>
            </div>
            <span class="flex-none text-xs font-medium text-status-info whitespace-nowrap">
                <span class="group-open:hidden">Lihat &rarr;</span>
                <span class="hidden group-open:inline">Tutup</span>
            </span>
        </summary>

        <div class="px-6 pb-6">

        @if ($riwayatTerbaru->isEmpty())
            <p class="text-sm text-text-secondary italic">Belum ada catatan pendampingan.</p>
        @else
            <div class="space-y-2">
                @foreach ($riwayatTerbaru as $item)
                    <div class="flex items-start justify-between gap-3 border-b border-border-soft last:border-0 pb-2 last:pb-0 text-sm">
                        <div class="min-w-0">
                            <p class="font-medium text-text-main">
                                {{ $item->tanggal->format('d-m-Y') }} — {{ $item->jenis_kegiatan }}
                            </p>
                            <p class="text-text-secondary truncate">{{ $item->materi }}</p>
                        </div>
                        <a href="{{ route('participants.show', $item->participant_id) }}"
                           class="text-xs text-status-info hover:underline flex-shrink-0">
                            {{ $item->participant->nama_peserta ?? '—' }}
                        </a>
                    </div>
                @endforeach
            </div>
        @endif
        </div>
    </details>

    {{-- ============================================================
         B. DAFTAR BINAAN SAYA — tertutup secara bawaan.

         Tabel enam kolom berisi sepuluh baris ini adalah bagian terpanjang
         halaman, dan seluruhnya sudah tersedia utuh di halaman "Binaan Saya"
         yang punya pencarian dan penyaringnya sendiri.

         Jumlah binaannya tetap terlihat di ringkasan atas tanpa perlu dibuka,
         jadi yang disembunyikan hanya rinciannya — bukan kabarnya.
         ============================================================ --}}
    <details class="group bg-white border border-border-soft rounded-2xl shadow-sm overflow-hidden">
        <summary class="flex items-center justify-between gap-3 p-6 cursor-pointer list-none">
            <div>
                <h3 class="text-[15px] font-semibold text-text-main">Binaan Saya</h3>
                <p class="text-xs text-text-secondary mt-0.5">
                    {{ $binaan->count() }} peserta dalam pendampingan Anda.
                </p>
            </div>
            <span class="flex-none text-xs font-medium text-status-info whitespace-nowrap">
                <span class="group-open:hidden">Lihat &rarr;</span>
                <span class="hidden group-open:inline">Tutup</span>
            </span>
        </summary>

        @if ($binaan->isEmpty())
            <div class="text-center pb-10 px-6">
                <p class="text-sm font-medium text-text-main mb-1">Belum Ada Binaan</p>
                <p class="text-xs text-text-secondary mb-4">Mulai dengan menambahkan peserta yang Anda dampingi.</p>
                <a href="{{ route('participants.create') }}"
                   class="inline-block text-sm bg-cahaya-primary text-white font-medium px-4 py-2 rounded-lg hover:opacity-90 transition">
                    + Tambah Binaan
                </a>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-text-secondary border-y border-border-soft bg-surface-bg">
                            <th class="px-6 py-3 font-medium">Nama Peserta</th>
                            <th class="px-6 py-3 font-medium">Nama Usaha</th>
                            <th class="px-6 py-3 font-medium">Jenis Usaha</th>
                            <th class="px-6 py-3 font-medium">Status</th>
                            <th class="px-6 py-3 font-medium">Pendampingan Terakhir</th>
                            <th class="px-6 py-3 font-medium">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($binaan->take(10) as $peserta)
                            @php $terakhir = $pendampinganTerakhir[$peserta->id] ?? null; @endphp
                            <tr class="border-b border-border-soft last:border-0">
                                <td class="px-6 py-3 text-text-main">
                                    {{ $peserta->nama_peserta }}
                                    @if ($peserta->koperasiMember)
                                        <span class="ml-1 text-[10px] px-1.5 py-0.5 rounded-full bg-cahaya-secondary/20 text-cahaya-primary">Koperasi</span>
                                    @endif
                                </td>
                                <td class="px-6 py-3 text-text-secondary">{{ $peserta->nama_usaha }}</td>
                                <td class="px-6 py-3 text-text-secondary">{{ $peserta->kategori_usaha }}</td>
                                <td class="px-6 py-3">
                                    <span class="text-[11px] font-medium px-2 py-0.5 rounded-full
                                        {{ $peserta->masihBerjalan() ? 'bg-primary-light text-status-success' : 'bg-gray-100 text-text-secondary' }}">
                                        {{ $peserta->status }}
                                    </span>
                                </td>
                                <td class="px-6 py-3 text-text-secondary whitespace-nowrap">
                                    {{ $terakhir ? \Illuminate\Support\Carbon::parse($terakhir)->format('d-m-Y') : 'Belum pernah' }}
                                </td>
                                <td class="px-6 py-3 space-x-2 whitespace-nowrap">
                                    <a href="{{ route('participants.show', $peserta) }}" class="text-xs text-status-info hover:underline">Detail</a>
                                    <a href="{{ route('participants.edit', $peserta) }}" class="text-xs text-cahaya-primary hover:underline">Edit</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($binaan->count() > 10)
                <div class="px-6 py-3 border-t border-border-soft">
                    <a href="{{ route('binaan.index') }}" class="text-xs text-status-info hover:underline">
                        Lihat {{ $binaan->count() - 10 }} binaan lainnya &rarr;
                    </a>
                </div>
            @endif
        @endif
    </details>
</div>
@endsection
