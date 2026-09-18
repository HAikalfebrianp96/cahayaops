@extends('layouts.app')

@section('title', 'Prioritas Pendampingan')

@section('content')
{{-- ============================================================
     PRIORITAS PENDAMPINGAN.

     Berbeda dari halaman Follow Up: yang itu mengelompokkan berdasarkan
     JADWAL, yang ini berdasarkan KEADAAN PESERTA (omzet, legalitas,
     kelengkapan data, lamanya tanpa aktivitas).

     Alasan SELALU ditampilkan. Daftar prioritas tanpa alasan hanya
     memindahkan keputusan dari pendamping ke rumus yang tidak bisa
     dibantah — padahal pendamping yang tahu keadaan sebenarnya.
     ============================================================ --}}
<div class="space-y-5">

    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
        <div>
            <h2 class="text-[20px] font-bold text-text-main">Prioritas Pendampingan</h2>
            <p class="text-sm text-text-secondary mt-0.5">
                {{ $isAdmin
                    ? 'Seluruh peserta aktif, diurutkan dari yang paling perlu ditindaklanjuti.'
                    : 'Binaan Anda yang paling perlu ditindaklanjuti, diurutkan dari yang paling mendesak.' }}
            </p>
        </div>
        <a href="{{ route('follow-up.index') }}"
           class="text-sm text-text-main border border-border-soft px-4 py-2.5 rounded-lg hover:bg-surface-bg transition whitespace-nowrap">
            Lihat Jadwal Follow Up
        </a>
    </div>

    @if ($isAdmin && $pendampings->isNotEmpty())
        <form method="GET" action="{{ route('prioritas.index') }}"
              class="bg-white border border-border-soft rounded-2xl shadow-sm p-4 flex flex-col sm:flex-row gap-3">
            <select name="pendamping_id" class="flex-1 rounded-xl border border-border-soft px-3 py-2.5 text-sm">
                <option value="">Semua Pendamping</option>
                @foreach ($pendampings as $p)
                    <option value="{{ $p->id }}" @selected((string) ($filters['pendamping_id'] ?? '') === (string) $p->id)>{{ $p->nama }}</option>
                @endforeach
            </select>
            <div class="flex gap-2">
                <button type="submit" class="bg-cahaya-secondary text-white text-sm font-medium px-4 py-2.5 rounded-lg hover:opacity-90 transition">
                    Terapkan
                </button>
                <a href="{{ route('prioritas.index') }}" class="text-sm text-text-secondary px-4 py-2.5 hover:text-text-main">Reset</a>
            </div>
        </form>
    @endif

    {{-- RINGKASAN --}}
    <div class="grid grid-cols-3 gap-3">
        @foreach ([
            ['label' => 'Perlu Segera', 'nilai' => $jumlah['tinggi'], 'warna' => $jumlah['tinggi'] > 0 ? 'text-status-danger' : 'text-text-main'],
            ['label' => 'Perlu Ditindaklanjuti', 'nilai' => $jumlah['sedang'], 'warna' => 'text-status-warning'],
            ['label' => 'Jadwal Hari Ini', 'nilai' => $jumlah['hari_ini'], 'warna' => 'text-status-info'],
            ['label' => 'Normal', 'nilai' => $jumlah['rendah'], 'warna' => 'text-status-success'],
        ] as $kartu)
            <div class="bg-white border border-border-soft rounded-xl p-4">
                <p class="text-[11px] text-text-secondary mb-1 leading-tight">{{ $kartu['label'] }}</p>
                <p class="text-xl font-bold {{ $kartu['warna'] }}">{{ $kartu['nilai'] }}</p>
            </div>
        @endforeach
    </div>

    {{-- ============================================================
         EMPAT STATUS, DENGAN NAMA YANG MENYEBUT TINDAKAN

         "Prioritas Tinggi/Sedang/Rendah" menamai PERINGKAT, bukan apa yang
         harus dilakukan. Pendamping yang membuka halaman ini sedang mencari
         jawaban satu pertanyaan — "siapa yang harus saya tindak lanjuti?" —
         dan nama peringkat tidak menjawabnya.

         Kunci kelompoknya tidak berubah; yang berubah sebutannya di layar.
         ============================================================ --}}
    @foreach ([
        'tinggi' => ['Perlu Segera', 'Belum pernah didampingi, tanpa aktivitas lama, follow up terlambat, omzet belum diperbarui, atau legalitas belum selesai.'],
        'sedang' => ['Perlu Ditindaklanjuti', 'Mendekati batas waktu pendampingan, follow up akan jatuh tempo, atau data belum lengkap.'],
        'hari_ini' => ['Jadwal Hari Ini', 'Follow up yang memang dijadwalkan hari ini. Belum terlambat — tinggal dikerjakan.'],
        'rendah' => ['Normal', 'Peserta aktif dan datanya rutin diperbarui. Tidak perlu tindakan khusus.'],
    ] as $kunci => [$judul, $keterangan])
        <div class="bg-white border border-border-soft rounded-2xl shadow-sm overflow-hidden">
            <div class="p-6 pb-4">
                <h3 class="text-[15px] font-semibold text-text-main">
                    {{ $judul }}
                    <span class="text-text-secondary font-normal">({{ $kelompok[$kunci]->count() }})</span>
                </h3>
                <p class="text-xs text-text-secondary mt-0.5">{{ $keterangan }}</p>
            </div>

            @if ($kelompok[$kunci]->isEmpty())
                <p class="px-6 pb-6 text-sm text-text-secondary italic">Tidak ada.</p>
            @else
                <div class="border-t border-border-soft divide-y divide-border-soft">
                    @foreach ($kelompok[$kunci]->take(25) as $baris)
                        <div class="p-4 sm:px-6 flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
                            <div class="min-w-0">
                                <a href="{{ route('participants.show', $baris['peserta']) }}"
                                   class="font-medium text-text-main hover:underline">
                                    {{ $baris['peserta']->nama_peserta }}
                                </a>
                                <p class="text-xs text-text-secondary mt-0.5">
                                    {{ $baris['peserta']->nama_usaha }}
                                    @if ($isAdmin)
                                        &bull; Pendamping: {{ $baris['peserta']->pendamping->nama ?? '—' }}
                                    @endif
                                </p>
                                <ul class="mt-1.5 space-y-0.5">
                                    @foreach ($baris['alasan'] as $alasan)
                                        <li class="text-[11px] text-text-secondary">&bull; {{ $alasan }}</li>
                                    @endforeach
                                </ul>
                            </div>

                            {{-- Tombol mengikuti ALASANNYA.

                                 Sebelumnya setiap baris menawarkan satu tombol
                                 yang sama — "Catat Pendampingan" — walau yang
                                 tertulis di alasannya "Omzet bulan ini belum
                                 diperbarui". Pendamping harus menebak sendiri
                                 lewat mana mengerjakannya. --}}
                            <div class="flex sm:flex-col items-start sm:items-end gap-2 flex-shrink-0">
                                <span class="text-[11px] font-medium px-2 py-0.5 rounded-full {{ $baris['keaktifan']['warna'] }}">
                                    {{ $baris['keaktifan']['status'] }}
                                </span>

                                @include('partials.tindakan-peserta', [
                                    'tindakan' => $baris['tindakan'],
                                    'peserta' => $baris['peserta'],
                                ])
                            </div>
                        </div>
                    @endforeach
                </div>

                @if ($kelompok[$kunci]->count() > 25)
                    <p class="px-6 py-3 border-t border-border-soft text-xs text-text-secondary">
                        Menampilkan 25 teratas dari {{ $kelompok[$kunci]->count() }} peserta.
                    </p>
                @endif
            @endif
        </div>
    @endforeach

    {{-- Ditulis terang-terangan supaya urutan di halaman ini tidak dibaca
         sebagai penilaian atas peserta. Yang tahu keadaan sebenarnya tetap
         pendamping yang datang ke lapangan. --}}
    <p class="text-[11px] text-text-secondary">
        Daftar ini alat bantu mengurutkan pekerjaan, bukan kesimpulan tentang kondisi peserta.
    </p>

    <p class="text-[11px] text-text-secondary">
        Keaktifan peserta: Aktif &le; {{ $ambangKeaktifan['aktif'] }} hari,
        Perlu Follow Up sampai {{ $ambangKeaktifan['perlu_follow_up'] }} hari, di atas itu Tidak Aktif.
        Angka ini dapat diubah Admin lewat Pengaturan.
    </p>
</div>
@endsection
