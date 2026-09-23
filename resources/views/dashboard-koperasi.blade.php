@extends('layouts.app')

@section('title', 'Dashboard Koperasi')

@section('content')
{{-- ============================================================
     DASHBOARD TIM KOPERASI.

     Kondisi usaha (enam KPI laba rugi — angka yang sama dengan halaman
     Keuangan yang memang boleh dibuka Tim Koperasi), lalu keanggotaan &
     integrasi dengan Cahaya Preneur. SENGAJA tidak memuat arus dana
     sosial/hibah maupun ringkasan program Cahaya Preneur.
     ============================================================ --}}
<div class="space-y-6">

    <div>
        <h2 class="text-[22px] font-bold text-text-main">
            Selamat datang, {{ explode(' ', auth()->user()->name)[0] ?? 'Tim Koperasi' }} 👋
        </h2>
        <p class="text-[11px] text-text-secondary mt-1">
            CahayaOS — Sistem Terpadu Cahaya Preneur &amp; Koperasi Cahaya
        </p>
        <p class="text-sm text-text-secondary mt-1">Koperasi Cahaya — Keanggotaan &amp; Unit Usaha</p>
    </div>

    {{-- ============================================================
         🎯 TUGAS KOPERASI — blok pertama.

         Dashboard ini sebelumnya hanya berisi angka: berapa anggota, berapa
         simpanan, siapa yang terbaru. Semuanya benar, tapi tidak satu pun
         menjawab pertanyaan yang dibawa Tim Koperasi saat membukanya pagi
         hari — "apa yang harus saya kerjakan?".

         Angkanya dari TugasKoperasiService, sumber yang sama dengan halaman
         Tugas Koperasi, jadi keduanya tidak mungkin bercerita lain.
         ============================================================ --}}
    <a href="{{ route('koperasi.tugas.index') }}"
       class="block bg-white border border-border-soft rounded-2xl shadow-sm p-5 hover:border-cahaya-primary/40 transition">
        <div class="flex items-center justify-between gap-3 mb-4">
            <h3 class="text-[15px] font-semibold text-text-main">🎯 Tugas Koperasi</h3>
            <span class="text-xs font-medium text-status-info whitespace-nowrap">Buka daftar &rarr;</span>
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
            @foreach ([
                ['🔴', 'Perlu Segera', $ringkasTugas['segera'],
                 $ringkasTugas['segera'] > 0 ? 'text-status-danger' : 'text-text-main'],
                ['🟠', 'Perlu Dilengkapi', $ringkasTugas['perhatian'],
                 $ringkasTugas['perhatian'] > 0 ? 'text-status-warning' : 'text-text-main'],
                ['👥', 'Calon Anggota', $ringkasTugas['calon_anggota'], 'text-status-info'],
                ['🟢', 'Transaksi Beres', $ringkasTugas['beres'], 'text-status-success'],
            ] as [$tanda, $label, $nilai, $warna])
                <div class="border border-border-soft rounded-xl p-3">
                    <p class="text-[11px] text-text-secondary leading-tight mb-1">
                        <span aria-hidden="true">{{ $tanda }}</span> {{ $label }}
                    </p>
                    <p class="text-2xl font-bold {{ $warna }}">{{ $nilai }}</p>
                </div>
            @endforeach
        </div>

        @if ($ringkasTugas['segera'] === 0 && $ringkasTugas['perhatian'] === 0)
            <p class="text-xs text-text-secondary mt-3">
                Tidak ada yang perlu dilengkapi — catatan koperasi sudah rapi.
            </p>
        @endif
    </a>

    {{-- KONDISI USAHA — partial & sumber yang SAMA dengan Dashboard Koperasi
         milik Admin (LabaRugiService): enam KPI, Produk Terlaris, Penjualan
         Terbesar, Transaksi Terbaru, Pengeluaran & Piutang. --}}
    @include('dashboard._ringkasan-usaha')

    {{-- AKSI CEPAT --}}
    <div class="flex flex-wrap gap-2">
        <a href="{{ route('koperasi.anggota.create') }}"
           class="inline-flex items-center bg-cahaya-primary text-white text-sm font-medium px-4 py-2.5 rounded-lg hover:opacity-90 transition">
            + Tambah Anggota
        </a>
        <a href="{{ route('koperasi.anggota.pilih-peserta') }}"
           class="inline-flex items-center bg-white border border-border-soft text-text-main text-sm font-medium px-4 py-2.5 rounded-lg hover:bg-surface-bg transition">
            Pilih dari Peserta Cahaya Preneur
        </a>
        <a href="{{ route('koperasi.anggota.index') }}"
           class="inline-flex items-center bg-white border border-border-soft text-text-main text-sm font-medium px-4 py-2.5 rounded-lg hover:bg-surface-bg transition">
            Daftar Anggota
        </a>
        <a href="{{ route('koperasi.laporan.index') }}"
           class="inline-flex items-center bg-white border border-border-soft text-text-main text-sm font-medium px-4 py-2.5 rounded-lg hover:bg-surface-bg transition">
            Laporan Koperasi
        </a>
    </div>

    {{-- STATISTIK --}}
    <div class="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-3">
        @foreach ([
            ['label' => 'Total Anggota', 'nilai' => $stats['total_anggota'], 'warna' => 'text-text-main'],
            ['label' => 'Anggota Aktif', 'nilai' => $stats['anggota_aktif'], 'warna' => 'text-status-success'],
            ['label' => 'Anggota Baru Bulan Ini', 'nilai' => $stats['anggota_baru'], 'warna' => 'text-status-info'],
            ['label' => 'Dari Cahaya Preneur', 'nilai' => $stats['dari_preneur'], 'warna' => 'text-cahaya-accent'],
            ['label' => 'Unit Usaha', 'nilai' => $stats['total_unit'], 'warna' => 'text-text-main'],
        ] as $kartu)
            <div class="bg-white border border-border-soft rounded-xl p-4">
                <p class="text-[11px] text-text-secondary mb-1 leading-tight">{{ $kartu['label'] }}</p>
                <p class="text-xl font-bold {{ $kartu['warna'] }}">{{ $kartu['nilai'] }}</p>
            </div>
        @endforeach
    </div>

    <div class="bg-white border border-border-soft rounded-2xl shadow-sm p-5">
        <p class="text-[11px] text-text-secondary mb-1">Total Simpanan Anggota</p>
        <p class="text-2xl font-bold text-cahaya-primary">{{ rupiah($stats['total_simpanan']) }}</p>
        <p class="text-[11px] text-text-secondary mt-1">
            Entri yang dibatalkan tidak ikut dihitung, tapi tetap tersimpan sebagai jejak koreksi.
        </p>
        @if (($stats['simpanan_anggota_tidak_aktif'] ?? 0) > 0)
            {{-- Tanpa keterangan ini, kartu "Total Anggota" dan kartu ini bisa
                 saling bertentangan di layar yang sama: jumlah anggotanya
                 sedikit, simpanannya banyak, dan tidak ada yang menjelaskan
                 kenapa. --}}
            <p class="text-[11px] text-status-warning mt-1">
                Termasuk {{ rupiah($stats['simpanan_anggota_tidak_aktif']) }} milik anggota yang statusnya tidak lagi aktif
                — dananya masih dipegang koperasi, tetapi pemiliknya tidak lagi terhitung sebagai anggota.
            </p>
        @endif
    </div>

    @isset($kasBank)
        @include('koperasi.kas-bank._kartu-dashboard')
    @endisset

    {{-- CALON ANGGOTA — integrasi Cahaya Preneur --}}
    <div class="bg-white border border-border-soft rounded-2xl shadow-sm overflow-hidden">
        <div class="p-6 pb-4">
            <h3 class="text-[15px] font-semibold text-text-main">
                Peserta Cahaya Preneur Potensial Menjadi Anggota
                <span class="text-text-secondary font-normal">({{ $jumlahCalon }})</span>
            </h3>
            <p class="text-xs text-text-secondary mt-0.5">
                Peserta aktif yang belum menjadi anggota koperasi. Keanggotaan TIDAK otomatis —
                Tim Koperasi yang menentukan siapa yang didaftarkan. Data dasarnya langsung terpakai,
                jadi tidak ada identitas yang perlu diketik ulang.
            </p>
        </div>

        @if ($calonAnggota->isEmpty())
            <p class="px-6 pb-6 text-sm text-text-secondary italic">
                Semua peserta aktif sudah terdaftar sebagai anggota koperasi.
            </p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-text-secondary border-y border-border-soft bg-surface-bg">
                            <th class="px-6 py-3 font-medium">Nama Peserta</th>
                            <th class="px-6 py-3 font-medium">Nama Usaha</th>
                            <th class="px-6 py-3 font-medium">Jenis Usaha</th>
                            <th class="px-6 py-3 font-medium">Nomor HP</th>
                            <th class="px-6 py-3 font-medium">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($calonAnggota as $p)
                            <tr class="border-b border-border-soft last:border-0">
                                <td class="px-6 py-3 font-medium text-text-main">{{ $p->nama_peserta }}</td>
                                <td class="px-6 py-3 text-text-secondary">{{ $p->nama_usaha }}</td>
                                <td class="px-6 py-3 text-text-secondary">{{ $p->kategori_usaha }}</td>
                                <td class="px-6 py-3 text-text-secondary">{{ $p->nomor_hp }}</td>
                                <td class="px-6 py-3 whitespace-nowrap">
                                    <a href="{{ route('koperasi.anggota.jadikan', $p) }}"
                                       class="inline-flex items-center rounded-lg px-3 py-1.5 text-xs font-medium bg-cahaya-secondary text-white hover:opacity-90 transition">
                                        Jadikan Anggota
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($jumlahCalon > $calonAnggota->count())
                <div class="px-6 py-3 border-t border-border-soft">
                    <a href="{{ route('koperasi.anggota.pilih-peserta', ['belum_anggota' => 1]) }}"
                       class="text-xs text-status-info hover:underline">
                        Lihat {{ $jumlahCalon - $calonAnggota->count() }} calon lainnya &rarr;
                    </a>
                </div>
            @endif
        @endif
    </div>

    {{-- TRANSAKSI SIMPANAN TERBARU --}}
    <div class="bg-white border border-border-soft rounded-2xl shadow-sm overflow-hidden">
        <div class="flex items-center justify-between p-6 pb-4">
            <h3 class="text-[15px] font-semibold text-text-main">Simpanan Terbaru</h3>
            <a href="{{ route('koperasi.laporan.simpanan') }}" class="text-xs text-status-info hover:underline">Laporan Simpanan &rarr;</a>
        </div>

        @if ($simpananTerbaru->isEmpty())
            <p class="px-6 pb-6 text-sm text-text-secondary italic">Belum ada transaksi simpanan.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-text-secondary border-y border-border-soft bg-surface-bg">
                            <th class="px-6 py-3 font-medium">Tanggal</th>
                            <th class="px-6 py-3 font-medium">Anggota</th>
                            <th class="px-6 py-3 font-medium">Jenis</th>
                            <th class="px-6 py-3 font-medium">Nominal</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($simpananTerbaru as $s)
                            <tr class="border-b border-border-soft last:border-0">
                                <td class="px-6 py-3 text-text-secondary whitespace-nowrap">{{ $s->tanggal->format('d-m-Y') }}</td>
                                {{-- Nama penyetor tetap tampil walau keanggotaannya
                                     sudah diarsipkan — tapi DITANDAI.

                                     Baris ini merekam setoran yang benar-benar terjadi;
                                     namanya bagian dari fakta itu. Penandanya menjawab
                                     pertanyaan berikutnya: kenapa orang ini tidak ada di
                                     daftar anggota. Pola yang sama dengan kolom pelanggan
                                     di daftar Penjualan. --}}
                                <td class="px-6 py-3 text-text-main">
                                    {{ $s->koperasiMemberTermasukArsip?->namaTampil() ?? '—' }}
                                    @if ($s->koperasiMemberTermasukArsip && $s->koperasiMemberTermasukArsip->status !== 'Aktif')
                                        <span class="text-[10px] text-text-secondary/70">(tidak aktif)</span>
                                    @endif
                                </td>
                                <td class="px-6 py-3 text-text-secondary">{{ $s->jenis_simpanan }}</td>
                                <td class="px-6 py-3 font-medium text-text-main">{{ rupiah($s->nominal) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- ANGGOTA TERBARU --}}
    <div class="bg-white border border-border-soft rounded-2xl shadow-sm p-6">
        <h3 class="text-[15px] font-semibold text-text-main mb-4">Anggota Terbaru</h3>

        @if ($anggotaTerbaru->isEmpty())
            <p class="text-sm text-text-secondary italic">Belum ada anggota terdaftar.</p>
        @else
            <div class="space-y-2">
                @foreach ($anggotaTerbaru as $a)
                    <div class="flex items-start justify-between gap-3 border-b border-border-soft last:border-0 pb-2 last:pb-0 text-sm">
                        <div class="min-w-0">
                            <a href="{{ route('koperasi.anggota.show', $a) }}" class="font-medium text-text-main hover:underline">
                                {{ $a->namaTampil() }}
                            </a>
                            <p class="text-xs text-text-secondary">
                                {{ $a->nomor_anggota }} &bull; {{ $a->sumberTampil() }}
                            </p>
                        </div>
                        <span class="text-[11px] font-medium px-2 py-0.5 rounded-full flex-shrink-0
                            {{ $a->aktif() ? 'bg-primary-light text-status-success' : 'bg-gray-100 text-text-secondary' }}">
                            {{ $a->status }}
                        </span>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
@endsection
