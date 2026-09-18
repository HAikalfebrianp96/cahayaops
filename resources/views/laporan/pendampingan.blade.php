@extends('layouts.app')

@section('title', 'Laporan Pendampingan')

@section('content')
<div class="space-y-5">
    <div class="flex items-center justify-between no-print">
        <h2 class="text-[20px] font-bold text-text-main">Laporan Pendampingan</h2>
        <div class="flex gap-2">
            <a href="{{ route('laporan.pendampingan.export-pdf', request()->query()) }}" class="bg-cahaya-primary text-white text-sm font-medium px-4 py-2 rounded-lg hover:opacity-90 transition">
                Export PDF
            </a>
            <a href="{{ route('laporan.pendampingan.export', request()->query()) }}" class="bg-cahaya-accent text-white text-sm font-medium px-4 py-2 rounded-lg hover:opacity-90 transition">
                Export Excel
            </a>
        </div>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-border-soft p-5 max-w-xs">
        <p class="text-sm text-text-secondary">Total Kegiatan Pendampingan (sesuai filter)</p>
        <p class="text-xl font-bold text-cahaya-primary mt-1">{{ $totalKegiatan }}</p>
    </div>

    <form method="GET" action="{{ route('laporan.pendampingan') }}"
          class="bg-white border border-border-soft rounded-2xl shadow-sm p-4 grid grid-cols-1 md:grid-cols-5 gap-3 no-print">
        <select name="pendamping_id" class="rounded-lg border border-border-soft px-3 py-2 text-sm">
            <option value="">Semua Pendamping</option>
            @foreach ($pendampings as $p)
                <option value="{{ $p->id }}" @selected((string) ($filters['pendamping_id'] ?? '') === (string) $p->id)>{{ $p->nama }}</option>
            @endforeach
        </select>
        <select name="participant_id" class="rounded-lg border border-border-soft px-3 py-2 text-sm">
            <option value="">Semua Peserta</option>
            @foreach ($participants as $p)
                <option value="{{ $p->id }}" @selected((string) ($filters['participant_id'] ?? '') === (string) $p->id)>{{ $p->nama_peserta }}</option>
            @endforeach
        </select>
        <select name="jenis_kegiatan" class="rounded-lg border border-border-soft px-3 py-2 text-sm">
            <option value="">Semua Jenis</option>
            @foreach ($jenisOptions as $jenis)
                <option value="{{ $jenis }}" @selected(($filters['jenis_kegiatan'] ?? '') === $jenis)>{{ $jenis }}</option>
            @endforeach
        </select>
        <input type="date" name="dari" value="{{ $filters['dari'] ?? '' }}" class="rounded-lg border border-border-soft px-3 py-2 text-sm" title="Dari tanggal">
        <input type="date" name="sampai" value="{{ $filters['sampai'] ?? '' }}" class="rounded-lg border border-border-soft px-3 py-2 text-sm" title="Sampai tanggal">
        <div class="md:col-span-5 flex gap-2">
            <button type="submit" class="bg-status-info text-white text-sm font-medium px-4 py-2 rounded-lg hover:opacity-90 transition">Terapkan Filter</button>
            <a href="{{ route('laporan.pendampingan') }}" class="text-sm text-text-secondary px-4 py-2 hover:text-text-main">Reset</a>
        </div>
    </form>

    <div class="bg-white border border-border-soft rounded-2xl shadow-sm overflow-hidden">
        @if ($records->isEmpty())
            <p class="text-sm text-text-secondary italic text-center py-10">Tidak ada data.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-text-secondary border-b border-border-soft bg-surface-bg">
                            <th class="px-4 py-3 font-medium">Tanggal</th>
                            <th class="px-4 py-3 font-medium">Peserta</th>
                            <th class="px-4 py-3 font-medium">Pendamping</th>
                            <th class="px-4 py-3 font-medium">Jenis Kegiatan</th>
                            <th class="px-4 py-3 font-medium">Materi</th>
                            <th class="px-4 py-3 font-medium">Kendala</th>
                            <th class="px-4 py-3 font-medium">Tindak Lanjut</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($records as $r)
                            <tr class="border-b border-border-soft last:border-0 align-top hover:bg-surface-bg/60 transition">
                                <td class="px-4 py-3 whitespace-nowrap text-text-secondary">{{ $r->tanggal->format('d-m-Y') }}</td>
                                <td class="px-4 py-3 font-medium text-text-main">{{ $r->participantTermasukArsip?->nama_peserta ?? '-' }}</td>
                                <td class="px-4 py-3 text-text-secondary">{{ $r->pendamping->nama ?? '—' }}</td>
                                <td class="px-4 py-3 text-text-secondary">{{ $r->jenis_kegiatan }}</td>
                                <td class="px-4 py-3 max-w-xs text-text-main">{{ $r->materi }}</td>
                                <td class="px-4 py-3 max-w-xs text-text-secondary">{{ $r->kendala ?: '—' }}</td>
                                <td class="px-4 py-3 max-w-xs text-text-secondary">{{ $r->tindak_lanjut ?: '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <div class="no-print">{{ $records->links() }}</div>
</div>
@endsection
