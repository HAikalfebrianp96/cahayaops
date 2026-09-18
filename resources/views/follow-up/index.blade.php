@extends('layouts.app')

@section('title', 'Follow Up')

@section('content')
{{-- ============================================================
     FOLLOW UP SAYA — reminder center pendamping.

     Empat keranjang yang SALING LEPAS: satu peserta hanya muncul di satu
     tempat, jadi daftarnya bisa dikerjakan dari atas ke bawah tanpa
     mengerjakan orang yang sama dua kali.

     Seluruh isinya diturunkan dari catatan pendampingan yang sudah ada —
     tidak ada tabel follow up terpisah yang harus diisi ulang.
     ============================================================ --}}
<div class="space-y-5">

    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
        <div>
            <h2 class="text-[20px] font-bold text-text-main">
                {{ $isAdmin ? 'Monitoring Follow Up' : '🎯 Tugas Saya' }}
            </h2>
            <p class="text-sm text-text-secondary mt-0.5">
                {{ $isAdmin
                    ? 'Tindak lanjut seluruh pendamping, dihitung dari catatan pendampingan yang tersimpan.'
                    : 'Yang perlu Anda kerjakan, diurutkan dari yang paling mendesak.' }}
            </p>
        </div>
        <p class="text-[11px] text-text-secondary bg-surface-bg border border-border-soft rounded-lg px-3 py-2">
            Ambang: perhatian {{ $ambang['kuning'] }} hari &bull; terlambat {{ $ambang['merah'] }} hari
        </p>
    </div>

    {{-- ============================================================
         DUA TAMPILAN, SATU SUMBER DATA.

         Pendamping MENGERJAKAN daftar ini, sering dari ponsel di lapangan:
         ia butuh antrean kerja dengan satu tombol jelas per baris.

         Admin MEMANTAU pekerjaan banyak orang: tabel padat yang bisa
         disaring memang bentuk yang tepat, dan tampilannya TIDAK diubah.

         Keduanya membaca FollowUpService::kelompokkan() yang sama — yang
         berbeda hanya susunannya, bukan angkanya.
         ============================================================ --}}
    @if (! $isAdmin)
        @include('follow-up.partials.tugas-pendamping')
    @else
    @if ($isAdmin && $pendampings->isNotEmpty())
        <form method="GET" action="{{ route('follow-up.index') }}"
              class="bg-white border border-border-soft rounded-2xl shadow-sm p-4 flex flex-col sm:flex-row gap-3">
            <select name="pendamping_id" class="flex-1 rounded-xl border border-border-soft px-3 py-2.5 text-sm">
                <option value="">Semua Pendamping</option>
                @foreach ($pendampings as $p)
                    <option value="{{ $p->id }}" @selected((string) ($filters['pendamping_id'] ?? '') === (string) $p->id)>
                        {{ $p->nama }}
                    </option>
                @endforeach
            </select>
            <div class="flex gap-2">
                <button type="submit" class="bg-cahaya-secondary text-white text-sm font-medium px-4 py-2.5 rounded-lg hover:opacity-90 transition">
                    Terapkan
                </button>
                <a href="{{ route('follow-up.index') }}" class="text-sm text-text-secondary px-4 py-2.5 hover:text-text-main">Reset</a>
            </div>
        </form>
    @endif

    {{-- RINGKASAN --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
        @foreach ([
            ['label' => 'Jatuh Tempo Hari Ini', 'nilai' => $jumlah['hari_ini'], 'warna' => 'text-status-info'],
            ['label' => 'Terlambat', 'nilai' => $jumlah['terlambat'], 'warna' => $jumlah['terlambat'] > 0 ? 'text-status-danger' : 'text-text-main'],
            ['label' => 'Akan Datang', 'nilai' => $jumlah['akan_datang'], 'warna' => 'text-text-main'],
            ['label' => 'Belum Pernah Didampingi', 'nilai' => $jumlah['belum_pernah'], 'warna' => $jumlah['belum_pernah'] > 0 ? 'text-status-warning' : 'text-text-main'],
        ] as $kartu)
            <div class="bg-white border border-border-soft rounded-xl p-4">
                <p class="text-[11px] text-text-secondary mb-1 leading-tight">{{ $kartu['label'] }}</p>
                <p class="text-xl font-bold {{ $kartu['warna'] }}">{{ $kartu['nilai'] }}</p>
            </div>
        @endforeach
    </div>

    {{-- EMPAT KERANJANG --}}
    @foreach ([
        'terlambat' => ['Terlambat Follow Up', 'Sudah melewati jadwal tindak lanjut, atau melewati ambang terlambat tanpa jadwal.'],
        'hari_ini' => ['Jatuh Tempo Hari Ini', 'Rencana tindak lanjut yang dijadwalkan untuk hari ini.'],
        'belum_pernah' => ['Belum Pernah Didampingi', 'Binaan aktif yang sama sekali belum punya catatan pendampingan.'],
        'akan_datang' => ['Akan Datang', 'Tindak lanjut yang sudah dijadwalkan untuk hari-hari berikutnya.'],
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
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-text-secondary border-y border-border-soft bg-surface-bg">
                                <th class="px-6 py-3 font-medium">Peserta</th>
                                <th class="px-6 py-3 font-medium">Aktivitas Terakhir</th>
                                <th class="px-6 py-3 font-medium">Follow Up</th>
                                <th class="px-6 py-3 font-medium">Status</th>
                                <th class="px-6 py-3 font-medium">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($kelompok[$kunci] as $baris)
                                @php $r = $baris['ringkas']; $p = $baris['peserta']; @endphp
                                <tr class="border-b border-border-soft last:border-0">
                                    <td class="px-6 py-3">
                                        <a href="{{ route('participants.show', $p) }}" class="font-medium text-text-main hover:underline">
                                            {{ $p->nama_peserta }}
                                        </a>
                                        <p class="text-xs text-text-secondary">{{ $p->nama_usaha }}</p>
                                        @if ($isAdmin)
                                            <p class="text-[11px] text-text-secondary">Pendamping: {{ $p->pendamping->nama ?? '—' }}</p>
                                        @endif
                                    </td>
                                    <td class="px-6 py-3 text-text-secondary whitespace-nowrap">
                                        @if ($r['terakhir'])
                                            {{ $r['jenis'] }}<br>
                                            <span class="text-[11px]">{{ $r['terakhir']->format('d-m-Y') }} &bull; {{ $r['hari'] }} hari lalu</span>
                                        @else
                                            <span class="italic">Belum pernah</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-3 text-text-secondary whitespace-nowrap">
                                        {{ $r['follow_up']?->format('d-m-Y') ?? '—' }}
                                    </td>
                                    <td class="px-6 py-3">
                                        <span class="text-[11px] font-medium px-2 py-0.5 rounded-full {{ $r['warna'] }}">
                                            {{ $r['status'] }}
                                        </span>
                                    </td>
                                    {{-- Tombol yang sama persis dengan daftar Prioritas,
                                         dari satu sumber — FollowUpService::tindakan(). --}}
                                    <td class="px-6 py-3 whitespace-nowrap">
                                        @include('partials.tindakan-peserta', [
                                            'tindakan' => $baris['tindakan'],
                                            'peserta' => $p,
                                        ])
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @endforeach
    @endif
</div>
@endsection
