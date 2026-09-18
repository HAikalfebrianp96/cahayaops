@extends('layouts.app')

@section('title', 'Laporan Legalitas & Sertifikasi')

@section('content')
<div class="space-y-5">
    <div class="flex items-center justify-between no-print">
        <h2 class="text-[20px] font-bold text-text-main">Laporan Legalitas &amp; Sertifikasi</h2>
        <div class="flex gap-2">
            <a href="{{ route('laporan.legalitas.export-pdf', request()->query()) }}" class="bg-cahaya-primary text-white text-sm font-medium px-4 py-2 rounded-lg hover:opacity-90 transition">
                Export PDF
            </a>
            <a href="{{ route('laporan.legalitas.export', request()->query()) }}" class="bg-cahaya-accent text-white text-sm font-medium px-4 py-2 rounded-lg hover:opacity-90 transition">
                Export Excel
            </a>
        </div>
    </div>

    <form method="GET" action="{{ route('laporan.legalitas') }}"
          class="bg-white border border-border-soft rounded-2xl shadow-sm p-4 grid grid-cols-1 md:grid-cols-5 gap-3 no-print">
        <select name="jenis_dokumen" class="rounded-lg border border-border-soft px-3 py-2 text-sm">
            <option value="">Semua Jenis</option>
            @foreach ($jenisList as $jenis)
                <option value="{{ $jenis }}" @selected(($filters['jenis_dokumen'] ?? '') === $jenis)>{{ $jenis }}</option>
            @endforeach
        </select>
        <select name="status" class="rounded-lg border border-border-soft px-3 py-2 text-sm">
            <option value="">Semua Status</option>
            @foreach ($statusOptions as $status)
                <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ $status }}</option>
            @endforeach
        </select>
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
        <div class="flex gap-2">
            <button type="submit" class="bg-status-info text-white text-sm font-medium px-4 py-2 rounded-lg hover:opacity-90 transition">Terapkan Filter</button>
            <a href="{{ route('laporan.legalitas') }}" class="text-sm text-text-secondary px-4 py-2 hover:text-text-main">Reset</a>
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
                            <th class="px-4 py-3 font-medium">Peserta</th>
                            <th class="px-4 py-3 font-medium">Usaha</th>
                            <th class="px-4 py-3 font-medium">Pendamping</th>
                            <th class="px-4 py-3 font-medium">Nama Legalitas</th>
                            <th class="px-4 py-3 font-medium">Nomor</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                            <th class="px-4 py-3 font-medium">Terbit</th>
                            <th class="px-4 py-3 font-medium">Berlaku</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($records as $r)
                            <tr class="border-b border-border-soft last:border-0 hover:bg-surface-bg/60 transition">
                                <td class="px-4 py-3 font-medium text-text-main">{{ $r->participantTermasukArsip?->nama_peserta ?? '-' }}</td>
                                <td class="px-4 py-3 text-text-secondary">{{ $r->participantTermasukArsip?->nama_usaha ?? '-' }}</td>
                                <td class="px-4 py-3 text-text-secondary">{{ $r->participantTermasukArsip?->pendamping->nama ?? '—' }}</td>
                                <td class="px-4 py-3 text-text-main">{{ $r->jenis_dokumen }}</td>
                                <td class="px-4 py-3 text-text-secondary">{{ $r->nomor_dokumen ?: '—' }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium {{ $r->status === 'Sudah Ada' ? 'bg-primary-light text-status-success' : 'bg-gray-100 text-text-secondary' }}">{{ $r->status }}</span>
                                </td>
                                <td class="px-4 py-3 text-text-secondary">{{ optional($r->tanggal_terbit)->format('d-m-Y') ?: '—' }}</td>
                                <td class="px-4 py-3 text-text-secondary">{{ optional($r->tanggal_berlaku)->format('d-m-Y') ?: '—' }}</td>
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
