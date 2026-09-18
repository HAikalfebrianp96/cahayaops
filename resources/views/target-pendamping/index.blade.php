@extends('layouts.app')

@section('title', 'Target Pendamping')

@section('content')
<div class="space-y-5">

    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
        <div>
            <h2 class="text-[20px] font-bold text-text-main">
                {{ $arsip ? 'Arsip Target Pendamping' : 'Target Pendamping' }}
            </h2>
            <p class="text-sm text-text-secondary mt-0.5">
                {{ $arsip
                    ? 'Target yang diarsipkan tidak ikut dihitung KPI — periodenya kembali memakai target bawaan sistem.'
                    : 'Target kinerja yang ditetapkan Admin per pendamping dan per periode.' }}
            </p>
        </div>
        <div class="flex items-center gap-2">
            @if ($arsip)
                <a href="{{ route('target-pendamping.index') }}"
                   class="text-sm text-text-secondary px-4 py-2.5 hover:text-text-main">← Daftar Target</a>
            @else
                @if ($jumlahTerhapus > 0)
                    <a href="{{ route('target-pendamping.index', ['arsip' => 1]) }}"
                       class="text-sm text-text-secondary px-4 py-2.5 hover:text-text-main">Arsip ({{ $jumlahTerhapus }})</a>
                @endif
                <a href="{{ route('kpi-pendamping.index') }}"
                   class="text-sm text-text-main border border-border-soft px-4 py-2.5 rounded-lg hover:bg-surface-bg transition">
                    Lihat KPI
                </a>
                <a href="{{ route('target-pendamping.create') }}"
                   class="bg-cahaya-primary text-white text-sm font-medium px-4 py-2.5 rounded-lg hover:opacity-90 transition">
                    + Tetapkan Target
                </a>
            @endif
        </div>
    </div>

    {{-- FILTER --}}
    <form method="GET" action="{{ route('target-pendamping.index') }}"
          class="bg-white border border-border-soft rounded-2xl shadow-sm p-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
        @if ($arsip) <input type="hidden" name="arsip" value="1"> @endif

        <select name="pendamping_id" class="rounded-xl border border-border-soft px-3 py-2.5 text-sm">
            <option value="">Semua Pendamping</option>
            @foreach ($pendampings as $p)
                <option value="{{ $p->id }}" @selected((string) ($filters['pendamping_id'] ?? '') === (string) $p->id)>{{ $p->nama }}</option>
            @endforeach
        </select>

        <select name="periode" class="rounded-xl border border-border-soft px-3 py-2.5 text-sm">
            <option value="">Semua Periode</option>
            @foreach ($periodeOptions as $p)
                <option value="{{ $p }}" @selected(($filters['periode'] ?? '') === $p)>{{ $p }}</option>
            @endforeach
        </select>

        <select name="tahun" class="rounded-xl border border-border-soft px-3 py-2.5 text-sm">
            <option value="">Semua Tahun</option>
            @foreach ($tahunOptions as $t)
                <option value="{{ $t }}" @selected((string) ($filters['tahun'] ?? '') === (string) $t)>{{ $t }}</option>
            @endforeach
        </select>

        <div class="flex gap-2">
            <button type="submit" class="bg-cahaya-secondary text-white text-sm font-medium px-4 py-2.5 rounded-lg hover:opacity-90 transition">
                Terapkan
            </button>
            <a href="{{ route('target-pendamping.index', $arsip ? ['arsip' => 1] : []) }}"
               class="text-sm text-text-secondary px-4 py-2.5 hover:text-text-main">Reset</a>
        </div>
    </form>

    {{-- TABEL --}}
    <div class="bg-white border border-border-soft rounded-2xl shadow-sm overflow-hidden">
        @if ($targets->isEmpty())
            <div class="text-center py-14 px-6">
                <p class="text-sm font-medium text-text-main mb-1">
                    {{ $arsip ? 'Arsip Kosong' : 'Belum Ada Target' }}
                </p>
                <p class="text-xs text-text-secondary mb-4">
                    {{ $arsip
                        ? 'Tidak ada target yang diarsipkan.'
                        : 'Selama belum ada target khusus, KPI memakai target bawaan sistem.' }}
                </p>
                @unless ($arsip)
                    <a href="{{ route('target-pendamping.create') }}"
                       class="inline-block text-sm bg-cahaya-primary text-white font-medium px-4 py-2 rounded-lg hover:opacity-90 transition">
                        + Tetapkan Target
                    </a>
                @endunless
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-text-secondary border-b border-border-soft bg-surface-bg">
                            <th class="px-5 py-3 font-medium">Pendamping</th>
                            <th class="px-5 py-3 font-medium">Periode</th>
                            <th class="px-5 py-3 font-medium">Binaan</th>
                            <th class="px-5 py-3 font-medium">Kunjungan</th>
                            <th class="px-5 py-3 font-medium">Pendampingan</th>
                            <th class="px-5 py-3 font-medium">Follow Up</th>
                            @if ($arsip)
                                <th class="px-5 py-3 font-medium">Dihapus</th>
                                <th class="px-5 py-3 font-medium">Oleh</th>
                            @endif
                            <th class="px-5 py-3 font-medium">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($targets as $t)
                            <tr class="border-b border-border-soft last:border-0 hover:bg-surface-bg/60 transition">
                                <td class="px-5 py-3 font-medium text-text-main">{{ $t->pendamping->nama ?? '—' }}</td>
                                <td class="px-5 py-3 text-text-secondary whitespace-nowrap">{{ $t->periodeLabel() }}</td>
                                <td class="px-5 py-3 text-text-secondary">{{ $t->target_jumlah_binaan ?? '—' }}</td>
                                <td class="px-5 py-3 text-text-secondary">{{ $t->target_kunjungan ?? '—' }}</td>
                                <td class="px-5 py-3 text-text-secondary">{{ $t->target_pendampingan ?? '—' }}</td>
                                <td class="px-5 py-3 text-text-secondary">{{ $t->target_follow_up ?? '—' }}</td>
                                @if ($arsip)
                                    <td class="px-5 py-3 text-text-secondary whitespace-nowrap">{{ $t->deleted_at?->format('d-m-Y H:i') }}</td>
                                    <td class="px-5 py-3 text-text-secondary">{{ $t->namaPenghapus() }}</td>
                                @endif
                                <td class="px-5 py-3 space-x-2 whitespace-nowrap">
                                    @if ($arsip)
                                        <form method="POST" action="{{ route('target-pendamping.restore', $t->id) }}" class="inline"
                                              onsubmit="return confirm('Pulihkan target ini? KPI periode itu akan kembali memakainya.');">
                                            @csrf
                                            <button type="submit" class="text-status-success hover:underline">Pulihkan</button>
                                        </form>
                                    @else
                                        <a href="{{ route('kpi-pendamping.show', $t->pendamping_id) }}" class="text-status-info hover:underline">Lihat KPI</a>
                                        <a href="{{ route('target-pendamping.edit', $t) }}" class="text-cahaya-primary hover:underline">Edit</a>
                                        <form method="POST" action="{{ route('target-pendamping.destroy', $t) }}" class="inline"
                                              onsubmit="return confirm('Arsipkan target ini?\n\nData akan dipindahkan ke Arsip Data dan masih dapat dipulihkan. KPI periode itu kembali memakai target bawaan sistem.');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-status-danger hover:underline">Arsipkan</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <div>{{ $targets->links() }}</div>
</div>
@endsection
