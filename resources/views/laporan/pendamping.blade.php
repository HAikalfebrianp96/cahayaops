@extends('layouts.app')

@section('title', 'Laporan Kinerja Pendamping')

@section('content')
{{-- ============================================================
     LAPORAN KINERJA PENDAMPING.

     Satu-satunya laporan yang memandang program dari sisi PENDAMPING, bukan
     peserta. Untuk Pendamping sendiri, isinya HANYA barisnya sendiri —
     membandingkan kinerja antar pendamping adalah kewenangan Admin.
     ============================================================ --}}
<div class="space-y-5">

    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
        <div>
            <h2 class="text-[20px] font-bold text-text-main">Laporan Kinerja Pendamping</h2>
            <p class="text-sm text-text-secondary mt-0.5">
                Beban binaan dan aktivitas pendampingan dalam periode yang dipilih.
            </p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('laporan.pendamping.export', request()->query()) }}"
               class="text-sm text-text-main border border-border-soft px-4 py-2.5 rounded-lg hover:bg-surface-bg transition">
                Export Excel
            </a>
            <a href="{{ route('laporan.pendamping.export-pdf', request()->query()) }}"
               class="text-sm bg-cahaya-primary text-white font-medium px-4 py-2.5 rounded-lg hover:opacity-90 transition">
                Download PDF
            </a>
        </div>
    </div>

    {{-- FILTER --}}
    <form method="GET" action="{{ route('laporan.pendamping') }}"
          class="bg-white border border-border-soft rounded-2xl shadow-sm p-4 grid grid-cols-1 md:grid-cols-4 gap-3">
        @if ($isAdmin)
            <div>
                <label class="block text-[11px] text-text-secondary mb-1">Pendamping</label>
                <select name="pendamping_id" class="w-full rounded-xl border border-border-soft px-3 py-2.5 text-sm">
                    <option value="">Semua Pendamping</option>
                    @foreach ($pendampings as $p)
                        <option value="{{ $p->id }}" @selected((string) ($filters['pendamping_id'] ?? '') === (string) $p->id)>{{ $p->nama }}</option>
                    @endforeach
                </select>
            </div>
        @endif

        <div>
            <label class="block text-[11px] text-text-secondary mb-1">Dari Tanggal</label>
            <input type="date" name="dari" value="{{ $filters['dari'] ?? '' }}"
                   class="w-full rounded-xl border border-border-soft px-3 py-2.5 text-sm">
        </div>
        <div>
            <label class="block text-[11px] text-text-secondary mb-1">Sampai Tanggal</label>
            <input type="date" name="sampai" value="{{ $filters['sampai'] ?? '' }}"
                   class="w-full rounded-xl border border-border-soft px-3 py-2.5 text-sm">
        </div>

        <div class="flex items-end gap-2">
            <button type="submit" class="bg-cahaya-secondary text-white text-sm font-medium px-4 py-2.5 rounded-lg hover:opacity-90 transition">
                Terapkan
            </button>
            <a href="{{ route('laporan.pendamping') }}" class="text-sm text-text-secondary px-4 py-2.5 hover:text-text-main">Reset</a>
        </div>
    </form>

    {{-- RINGKASAN --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
        @foreach ([
            ['label' => 'Pendamping', 'nilai' => $ringkasan['jumlah_pendamping']],
            ['label' => 'Total Binaan', 'nilai' => $ringkasan['total_binaan']],
            ['label' => 'Total Aktivitas', 'nilai' => $ringkasan['total_aktivitas']],
            ['label' => 'Tanpa Aktivitas', 'nilai' => $ringkasan['tanpa_aktivitas']],
        ] as $kartu)
            <div class="bg-white border border-border-soft rounded-xl p-4">
                <p class="text-[11px] text-text-secondary mb-1">{{ $kartu['label'] }}</p>
                <p class="text-xl font-bold text-text-main">{{ $kartu['nilai'] }}</p>
            </div>
        @endforeach
    </div>

    {{-- TABEL --}}
    <div class="bg-white border border-border-soft rounded-2xl shadow-sm overflow-hidden">
        @if ($baris->isEmpty())
            <p class="text-center py-14 text-sm text-text-secondary italic">
                Tidak ada data pendamping untuk filter ini.
            </p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-text-secondary border-b border-border-soft bg-surface-bg">
                            <th class="px-5 py-3 font-medium">Pendamping</th>
                            <th class="px-5 py-3 font-medium">Binaan</th>
                            <th class="px-5 py-3 font-medium">Aktif</th>
                            <th class="px-5 py-3 font-medium">Pendampingan</th>
                            <th class="px-5 py-3 font-medium">Kunjungan</th>
                            <th class="px-5 py-3 font-medium">Konsultasi</th>
                            <th class="px-5 py-3 font-medium">Evaluasi</th>
                            <th class="px-5 py-3 font-medium">Follow Up</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($baris as $b)
                            <tr class="border-b border-border-soft last:border-0">
                                <td class="px-5 py-3">
                                    <p class="font-medium text-text-main">{{ $b['pendamping']->nama }}</p>
                                    <p class="text-[11px] text-text-secondary">{{ $b['pendamping']->status }}</p>
                                </td>
                                <td class="px-5 py-3 text-text-secondary">{{ $b['total_binaan'] }}</td>
                                <td class="px-5 py-3 text-text-secondary">{{ $b['binaan_aktif'] }}</td>
                                <td class="px-5 py-3">
                                    <span class="font-medium {{ $b['pendampingan'] > 0 ? 'text-status-success' : 'text-status-warning' }}">
                                        {{ $b['pendampingan'] }}
                                    </span>
                                </td>
                                <td class="px-5 py-3 text-text-secondary">{{ $b['kunjungan'] }}</td>
                                <td class="px-5 py-3 text-text-secondary">{{ $b['konsultasi'] }}</td>
                                <td class="px-5 py-3 text-text-secondary">{{ $b['evaluasi'] }}</td>
                                <td class="px-5 py-3 text-text-secondary">{{ $b['follow_up'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection
