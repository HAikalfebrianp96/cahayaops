@extends('layouts.app')

@section('title', 'Dampak Program')

@section('content')
<div class="space-y-4">

    {{-- ============================================================
         "DAMPAK PROGRAM", BUKAN "DAMPAK & SROI"

         Nama lama memajang metodologinya di depan pintu. SROI adalah CARA
         menghitung, bukan hal yang dicari — yang dicari adalah perubahan
         pada peserta. Halaman ini dibaca Pimpinan, dan istilah teknis di
         judul membuatnya terbaca sebagai urusan analis.

         Rute, nama rute, tabel, dan mesin perhitungannya TIDAK berubah
         sama sekali. Yang berubah hanya sebutannya di layar.
         ============================================================ --}}
    <div class="bg-white border border-border-soft rounded-2xl shadow-sm p-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-lg font-semibold text-text-main">Dampak Program</h2>
                <p class="text-xs text-text-secondary mt-0.5">
                    Mengukur perubahan yang terjadi pada peserta akibat program — dihitung dari data
                    yang sudah dicatat pendamping, tanpa input ulang.
                </p>
            </div>
            @if ($bisaKelola)
                <a href="{{ route('impact.create') }}"
                   class="bg-cahaya-primary text-white text-sm font-medium px-4 py-2 rounded-lg hover:opacity-90 transition">
                    + Program Baru
                </a>
            @endif
        </div>

        {{-- Nilai acuan rupiah adalah PENGATURAN, bukan tujuan halaman ini.
             Sebelumnya ia berdiri sejajar dengan "Program Baru" di deretan
             tombol utama, sehingga hal paling teknis di modul ini justru
             paling menonjol. Sekarang ia turun jadi tautan pengaturan, dan
             hanya untuk yang memang berwenang mengubahnya — Pimpinan
             membaca hasil, tidak menyusun nilai acuan. --}}
        @if ($bisaKelola)
            <p class="text-xs text-text-secondary mt-3 pt-3 border-t border-border-soft">
                Pengaturan:
                <a href="{{ route('impact.proxy.index') }}" class="text-cahaya-secondary hover:underline">Nilai acuan rupiah</a>
                <span class="text-text-secondary/70">— dipakai menerjemahkan outcome menjadi nilai rupiah.</span>
            </p>
        @endif
    </div>

    @include('impact.partials.notifikasi')

    {{--
        KEADAAN KONFIGURASI — ditampilkan sebelum daftar program.

        Proxy finansial tidak menghalangi pembuatan program, tapi menghentikan
        langkah outcome. Mengetahuinya sekarang jauh lebih murah daripada
        menemukannya setelah program dan investasi terlanjur disusun.

        Kalau sudah ada proxy, blok ini tidak muncul sama sekali — pengingat
        yang tetap tampil setelah dikerjakan akan berhenti dibaca.
    --}}
    {{-- Peringatan ini hanya untuk yang BISA menindaklanjutinya.

         Pimpinan tidak menyusun nilai acuan; menampilkan peringatan
         konfigurasi kepadanya hanya memindahkan kebingungan, bukan
         menyelesaikan apa pun. --}}
    @if ($jumlahProxy === 0 && $bisaKelola)
        <div class="rounded-xl bg-gold-light border border-status-warning/40 px-4 py-3">
            <p class="text-sm font-semibold text-cahaya-primary">Nilai acuan rupiah belum diisi</p>
            <p class="text-xs text-text-secondary mt-1">
                Outcome baru bisa dinilai dalam rupiah kalau ada nilai acuannya. Selama belum ada,
                dampak program tidak akan dihitung. Nilai acuan harus datang dari sumber yang bisa
                diperiksa — statistik resmi, studi, atau kebijakan lembaga — beserta tahunnya.
                <a href="{{ route('impact.proxy.create') }}" class="text-cahaya-secondary hover:underline">Isi nilai acuan pertama &rarr;</a>
            </p>
        </div>
    @endif

    @forelse ($programs as $p)
        @php $terakhir = $p->calculations()->first(); @endphp
        <a href="{{ route('impact.show', $p) }}"
           class="block bg-white border border-border-soft rounded-2xl shadow-sm p-5 hover:border-cahaya-secondary transition">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <h3 class="text-[15px] font-semibold text-text-main">{{ $p->nama }}</h3>
                        <span class="text-[11px] font-semibold px-2 py-0.5 rounded-full
                            @if ($p->status === \App\Models\ImpactProgram::STATUS_FINAL) bg-status-success/10 text-status-success
                            @elseif ($p->status === \App\Models\ImpactProgram::STATUS_REVIEW) bg-gold-light text-cahaya-primary
                            @else bg-surface-bg text-text-secondary @endif">
                            {{ $p->status }}
                        </span>
                    </div>
                    <p class="text-xs text-text-secondary mt-1">
                        {{ $p->periodeLabel() }}
                        @if ($p->pendamping) &bull; {{ $p->pendamping->nama }} @endif
                        &bull; {{ $jumlahPeserta[$p->id] ?? 0 }} peserta
                        &bull; {{ $p->outcomes_count }} outcome
                        &bull; investasi {{ rupiah($p->totalInvestasi()) }}
                    </p>
                </div>

                {{-- Nilai dampak ditampilkan MENDAHULUI rasio.

                     Rasio adalah angka yang paling gampang salah dibaca kalau
                     berdiri sendiri: "2,4" tidak berarti apa-apa tanpa tahu
                     2,4 dari investasi berapa. Nilai rupiahnya konkret, dan
                     rasio menyusul sebagai penjelasnya. --}}
                <div class="text-right shrink-0">
                    @if ($terakhir && $terakhir->rasio !== null)
                        <p class="text-lg font-bold text-cahaya-primary leading-tight">
                            {{ rupiah($terakhir->total_nilai_dampak) }}
                        </p>
                        <p class="text-[11px] text-text-secondary mt-1">
                            nilai dampak &bull; rasio {{ number_format($terakhir->rasio, 2, ',', '.') }}
                            &bull; {{ $terakhir->status }}
                        </p>
                    @else
                        {{-- Program yang belum dihitung TIDAK ditampilkan sebagai
                             rasio 0. Nol berarti program tanpa hasil sama sekali;
                             "belum cukup data" berarti pertanyaannya belum bisa
                             dijawab. Bagi pembaca laporan dua hal itu jauh
                             berbeda artinya.

                             Yang kurang disebutkan, karena itulah satu-satunya
                             bagian yang bisa ditindaklanjuti. --}}
                        <p class="text-sm font-medium text-text-secondary">Belum cukup data</p>
                        <p class="text-[11px] text-text-secondary mt-0.5">
                            @if ($p->outcomes_count === 0)
                                outcome belum disusun
                            @elseif ($p->totalInvestasi() === 0)
                                investasi belum dicatat
                            @else
                                belum dihitung
                            @endif
                        </p>
                    @endif
                </div>
            </div>
        </a>
    @empty
        <div class="bg-white border border-dashed border-border-soft rounded-2xl p-8 text-center">
            <p class="text-sm font-semibold text-text-main mb-1">Belum ada program dampak</p>
            <p class="text-xs text-text-secondary max-w-lg mx-auto">
                Program adalah cara membatasi pertanyaan &ldquo;dampaknya berapa&rdquo;: peserta yang mana,
                rentang waktu kapan, investasi yang mana. Tanpa batas itu, angka dampak akan bergeser
                sendiri setiap kali ada peserta baru masuk.
            </p>
            @if ($bisaKelola)
                <a href="{{ route('impact.create') }}" class="inline-block mt-4 text-sm text-cahaya-secondary hover:underline">
                    Buat program pertama →
                </a>
            @endif
        </div>
    @endforelse

    {{ $programs->links() }}
</div>
@endsection
