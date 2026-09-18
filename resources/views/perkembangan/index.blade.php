@extends('layouts.app')

@section('title', 'Perkembangan Usaha')

@section('content')
@php
    /**
     * Format satu angka indikator sesuai jenisnya. Ditaruh di sini, bukan di
     * service, karena ini murni urusan tampilan — service-nya tetap
     * mengembalikan angka mentah supaya bisa dipakai Impact & SROI apa adanya.
     */
    $tampil = function ($nilai, string $format, string $satuan = '') {
        if ($format === 'rupiah') {
            return rupiah((int) $nilai);
        }

        return number_format((int) $nilai, 0, ',', '.') . ($satuan ? ' ' . $satuan : '');
    };
@endphp

<div class="space-y-4">

    {{-- KEPALA --}}
    <div class="bg-white border border-border-soft rounded-2xl shadow-sm p-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-lg font-semibold text-text-main">Perkembangan Usaha</h2>
                <p class="text-xs text-text-secondary mt-0.5">
                    {{ $participant->nama_peserta }} &bull; {{ $participant->nama_usaha }}
                    @if ($participant->pendamping)
                        &bull; Pendamping: {{ $participant->pendamping->nama }}
                    @endif
                </p>
            </div>
            <div class="flex gap-2">
                @if ($bisaKelola)
                    <a href="{{ route('perkembangan.create', $participant) }}"
                       class="bg-cahaya-primary text-white text-sm font-medium px-4 py-2 rounded-lg hover:opacity-90 transition">
                        + Catat Pengukuran
                    </a>
                @endif
                <a href="{{ route('participants.show', $participant) }}"
                   class="text-sm text-text-secondary px-4 py-2 hover:text-text-main">Detail Peserta</a>
            </div>
        </div>
    </div>

    @if (session('success'))
        <div class="rounded-xl bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-700">
            {{ session('success') }}
        </div>
    @endif

    {{--
        PERBANDINGAN — inti halaman ini.

        Kalau belum bisa dibandingkan, yang ditampilkan adalah ALASANNYA,
        bukan tabel kosong atau angka nol. Nol dan "belum diukur" adalah dua
        pernyataan yang sangat berbeda tentang sebuah usaha.
    --}}
    @if (! $rapor['siap_dibandingkan'])
        <div class="bg-white border border-dashed border-border-soft rounded-2xl p-6">
            <p class="text-sm font-semibold text-text-main mb-2">Perubahan belum bisa dihitung</p>
            <ul class="text-sm text-text-secondary space-y-1 list-disc list-inside">
                @foreach ($rapor['alasan_belum_siap'] as $alasan)
                    <li>{{ $alasan }}</li>
                @endforeach
            </ul>
            @if ($bisaKelola)
                <a href="{{ route('perkembangan.create', $participant) }}"
                   class="inline-block mt-4 text-sm text-cahaya-secondary hover:underline">
                    Catat pengukuran sekarang →
                </a>
            @endif
        </div>
    @else
        <div class="bg-white border border-border-soft rounded-2xl shadow-sm p-5">
            <div class="flex flex-wrap items-baseline justify-between gap-2 mb-4">
                <h3 class="text-sm font-semibold text-text-main">
                    Baseline &rarr; {{ $rapor['sumber_akhir'] }}
                </h3>
                <p class="text-xs text-text-secondary">
                    {{ $rapor['baseline']->tanggal->format('d M Y') }}
                    &rarr; {{ $rapor['akhir']->tanggal->format('d M Y') }}
                    @if ($rapor['sumber_akhir'] === \App\Models\ParticipantMeasurement::MONITORING)
                        <span class="ml-1 text-status-warning">(monitoring berjalan, belum endline)</span>
                    @endif
                </p>
            </div>

            @if (empty($rapor['perubahan']) && empty($rapor['perubahan_status']))
                <p class="text-sm text-text-secondary">
                    Kedua pengukuran sudah ada, tetapi belum ada satu pun indikator yang terisi di
                    keduanya — jadi belum ada perubahan yang bisa dinyatakan.
                </p>
            @else
                <div class="overflow-x-auto -mx-5 px-5">
                    <table class="w-full text-sm min-w-[520px]">
                        <thead>
                            <tr class="text-left text-xs text-text-secondary border-b border-border-soft">
                                <th class="py-2 font-medium">Indikator</th>
                                <th class="py-2 font-medium text-right">Awal</th>
                                <th class="py-2 font-medium text-right">Sekarang</th>
                                <th class="py-2 font-medium text-right">Perubahan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border-soft">
                            @foreach ($rapor['perubahan'] as $p)
                                <tr>
                                    <td class="py-2 text-text-main">{{ $p['label'] }}</td>
                                    <td class="py-2 text-right text-text-secondary">{{ $tampil($p['sebelum'], $p['format'], $p['satuan']) }}</td>
                                    <td class="py-2 text-right text-text-main">{{ $tampil($p['sesudah'], $p['format'], $p['satuan']) }}</td>
                                    <td class="py-2 text-right font-medium
                                        @if ($p['membaik'] === true) text-status-success
                                        @elseif ($p['membaik'] === false) text-status-danger
                                        @else text-text-secondary @endif">
                                        {{ $p['selisih'] > 0 ? '+' : '' }}{{ $tampil($p['selisih'], $p['format'], $p['satuan']) }}
                                        @if ($p['persen'] !== null)
                                            <span class="block text-[11px] font-normal">
                                                {{ $p['persen'] > 0 ? '+' : '' }}{{ number_format($p['persen'], 1, ',', '.') }}%
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach

                            @foreach ($rapor['perubahan_status'] as $p)
                                <tr>
                                    <td class="py-2 text-text-main">{{ $p['label'] }}</td>
                                    <td class="py-2 text-right text-text-secondary">{{ $p['sebelum'] ? 'Sudah' : 'Belum' }}</td>
                                    <td class="py-2 text-right text-text-main">{{ $p['sesudah'] ? 'Sudah' : 'Belum' }}</td>
                                    <td class="py-2 text-right font-medium
                                        @if ($p['membaik']) text-status-success
                                        @elseif ($p['berubah']) text-status-danger
                                        @else text-text-secondary @endif">
                                        {{ $p['membaik'] ? 'Tercapai' : ($p['berubah'] ? 'Menurun' : 'Tetap') }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Indikator netral tidak dinilai baik/buruk; alasannya ditulis
                     supaya kolom abu-abu tidak dikira kesalahan sistem. --}}
                <p class="text-[11px] text-text-secondary mt-3">
                    HPP dan biaya operasional ditampilkan tanpa penilaian baik/buruk — naiknya biaya bisa
                    berarti usaha membesar, bisa juga berarti boros. Penilaiannya ada pada pendamping.
                </p>
            @endif
        </div>
    @endif

    {{-- RIWAYAT PENGUKURAN --}}
    <div class="bg-white border border-border-soft rounded-2xl shadow-sm p-5">
        <h3 class="text-sm font-semibold text-text-main mb-4">
            Riwayat Pengukuran
            <span class="text-xs font-normal text-text-secondary">({{ $participant->measurements->count() }})</span>
        </h3>

        @forelse ($participant->measurements as $m)
            <div class="border border-border-soft rounded-xl p-4 mb-3 last:mb-0">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <span class="inline-block text-[11px] font-semibold px-2 py-0.5 rounded-full
                            @if ($m->jenis === \App\Models\ParticipantMeasurement::BASELINE) bg-primary-light text-cahaya-primary
                            @elseif ($m->jenis === \App\Models\ParticipantMeasurement::ENDLINE) bg-gold-light text-cahaya-primary
                            @else bg-surface-bg text-text-secondary @endif">
                            {{ $m->jenis }}
                        </span>
                        <span class="text-sm text-text-main ml-2">{{ $m->tanggal->format('d M Y') }}</span>
                        <p class="text-[11px] text-text-secondary mt-1">
                            Dicatat oleh {{ $m->pencatat->name ?? 'Tidak tercatat' }}
                            @unless ($m->adaIsinya())
                                &bull; <span class="text-status-warning">belum ada indikator terisi</span>
                            @endunless
                        </p>
                    </div>

                    <div class="flex gap-2 text-xs">
                        @can('update', $m)
                            <a href="{{ route('perkembangan.edit', $m) }}" class="text-cahaya-secondary hover:underline">Edit</a>
                        @endcan
                        @can('delete', $m)
                            <form method="POST" action="{{ route('perkembangan.destroy', $m) }}"
                                  onsubmit="return confirm('Hapus Permanen?\n\nData yang dihapus tidak dapat dipulihkan. Pastikan data ini memang sudah tidak diperlukan.')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-status-danger hover:underline">Hapus Permanen</button>
                            </form>
                        @endcan
                    </div>
                </div>

                <dl class="grid grid-cols-2 sm:grid-cols-3 gap-x-4 gap-y-1 text-xs mt-3">
                    @foreach (\App\Models\ParticipantMeasurement::INDIKATOR as $kolom => $meta)
                        @if ($m->{$kolom} !== null)
                            <div>
                                <dt class="text-text-secondary">{{ $meta['label'] }}</dt>
                                <dd class="text-text-main font-medium">{{ $tampil($m->{$kolom}, $meta['format'], $meta['satuan']) }}</dd>
                            </div>
                        @endif
                    @endforeach
                    @if ($m->jangkauan_pasar)
                        <div>
                            <dt class="text-text-secondary">Jangkauan pasar</dt>
                            <dd class="text-text-main font-medium">{{ \App\Models\ParticipantMeasurement::PASAR_OPTIONS[$m->jangkauan_pasar] ?? $m->jangkauan_pasar }}</dd>
                        </div>
                    @endif
                    @foreach (\App\Models\ParticipantMeasurement::INDIKATOR_BOOLEAN as $kolom => $label)
                        @if ($m->{$kolom} !== null)
                            <div>
                                <dt class="text-text-secondary">{{ $label }}</dt>
                                <dd class="text-text-main font-medium">{{ $m->{$kolom} ? 'Sudah' : 'Belum' }}</dd>
                            </div>
                        @endif
                    @endforeach
                </dl>

                @if ($m->catatan)
                    <p class="text-xs text-text-secondary mt-3 pt-3 border-t border-border-soft">{{ $m->catatan }}</p>
                @endif
                @if ($m->sumber_data)
                    <p class="text-[11px] text-text-secondary mt-1">Sumber data: {{ $m->sumber_data }}</p>
                @endif
            </div>
        @empty
            <p class="text-sm text-text-secondary">
                Belum ada pengukuran untuk peserta ini. Mulai dari <span class="font-medium">Baseline</span> —
                kondisi usaha sebelum didampingi.
            </p>
        @endforelse
    </div>
</div>
@endsection
