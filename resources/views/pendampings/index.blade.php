@extends('layouts.app')
@section('title', 'Pendamping')
@section('content')
<div class="space-y-5">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
        <div>
            <h2 class="text-[20px] font-bold text-text-main">
                Pendamping
            </h2>
            <p class="text-sm text-text-secondary mt-0.5">
                Kelola pendamping dan peserta binaan.
            </p>
        </div>
        <div class="flex items-center gap-2">
                                <a href="{{ route('pendampings.create') }}"
                   class="inline-flex items-center justify-center bg-cahaya-primary text-white text-sm font-medium px-4 py-2.5 rounded-lg hover:opacity-90 transition">
                    + Tambah Pendamping
                </a>

        </div>
    </div>

    {{-- 4. SUMMARY --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-white border border-border-soft rounded-2xl shadow-sm p-5">
            <p class="text-xs font-medium text-text-secondary mb-1.5">Total Pendamping</p>
            <p class="text-2xl font-bold text-text-main">{{ $summary['total_pendamping'] }}</p>
        </div>
        <div class="bg-white border border-border-soft rounded-2xl shadow-sm p-5">
            <p class="text-xs font-medium text-text-secondary mb-1.5">Pendamping Aktif</p>
            <p class="text-2xl font-bold text-status-success">{{ $summary['pendamping_aktif'] }}</p>
        </div>
        <div class="bg-white border border-border-soft rounded-2xl shadow-sm p-5">
            <p class="text-xs font-medium text-text-secondary mb-1.5">Total Peserta Binaan</p>
            <p class="text-2xl font-bold text-status-info">{{ $summary['total_peserta_binaan'] }}</p>
        </div>
    </div>

    {{-- 6. DAFTAR PENDAMPING --}}
    <div class="bg-white border border-border-soft rounded-2xl shadow-sm overflow-hidden">
        @if ($pendampings->isEmpty())
            <div class="text-center py-14">
                <p class="text-sm font-medium text-text-main mb-1">Belum ada pendamping</p>
                <p class="text-xs text-text-secondary mb-4">Tambahkan pendamping pertama untuk mulai mengelola binaan.</p>
                <a href="{{ route('pendampings.create') }}" class="inline-block text-sm bg-cahaya-primary text-white font-medium px-4 py-2 rounded-lg hover:opacity-90 transition">+ Tambah Pendamping</a>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-text-secondary border-b border-border-soft bg-surface-bg">
                            <th class="px-5 py-3 font-medium">Pendamping</th>
                            <th class="px-5 py-3 font-medium">Nomor HP</th>
                            <th class="px-5 py-3 font-medium">Email</th>
                            <th class="px-5 py-3 font-medium">Peserta Binaan</th>
                            <th class="px-5 py-3 font-medium">Status</th>
                            <th class="px-5 py-3 font-medium">Akun Login</th>
                            <th class="px-5 py-3 font-medium">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($pendampings as $pendamping)
                            @php
                                $inisial = collect(explode(' ', trim($pendamping->nama)))->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode('');
                            @endphp
                            <tr class="border-b border-border-soft last:border-0 hover:bg-surface-bg/60 transition">
                                <td class="px-5 py-3">
                                    <div class="flex items-center gap-3">
                                        <div class="h-8 w-8 rounded-full bg-cahaya-primary text-white flex items-center justify-center text-[11px] font-bold flex-shrink-0">
                                            {{ $inisial ?: '?' }}
                                        </div>
                                        <span class="font-medium text-text-main">{{ $pendamping->nama }}</span>
                                    </div>
                                </td>
                                <td class="px-5 py-3 text-text-secondary">{{ $pendamping->nomor_hp }}</td>
                                <td class="px-5 py-3 text-text-secondary">{{ $pendamping->email ?: '—' }}</td>
                                <td class="px-5 py-3">
                                    @php $beban = $kpi->beban($pendamping->participants_count); @endphp
                                    <span class="font-semibold text-status-info">{{ $pendamping->participants_count }}</span>
                                    {{-- Indikator beban: bahan Admin untuk menimbang
                                         redistribusi peserta antar pendamping. --}}
                                    <span class="ml-1 text-[10px] font-medium px-1.5 py-0.5 rounded-full {{ $beban['warna'] }}">
                                        {{ $beban['kategori'] }}
                                    </span>
                                    <span class="text-text-secondary text-xs">Peserta</span>
                                </td>
                                <td class="px-5 py-3">
                                    <span class="inline-block px-2 py-0.5 rounded-full text-xs font-medium
                                        {{ $pendamping->status === 'Aktif' ? 'bg-primary-light text-status-success' : 'bg-gray-100 text-text-secondary' }}">
                                        {{ $pendamping->status }}
                                    </span>
                                </td>
                                <td class="px-5 py-3">
                                    @if ($pendamping->user)
                                        <span class="text-xs text-status-success">🟢 Aktif</span>
                                    @else
                                        <span class="text-xs text-text-secondary">⚪ Belum Ada</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 whitespace-nowrap">
                                        <a href="{{ route('kpi-pendamping.show', $pendamping) }}" class="text-cahaya-accent hover:underline text-xs font-medium">KPI</a>
                                        <span class="text-border-soft mx-1">|</span>
                                        <a href="{{ route('pendampings.show', $pendamping) }}" class="text-status-info hover:underline text-xs font-medium">Lihat</a>
                                        <span class="text-border-soft mx-1">|</span>
                                        <a href="{{ route('pendampings.edit', $pendamping) }}" class="text-cahaya-primary hover:underline text-xs font-medium">Edit</a>
                                        <span class="text-border-soft mx-1">|</span>
                                        {{-- Soft Delete: binaannya TIDAK kehilangan pemilik.
                                             Yang dilepas hanya tautan akun loginnya, supaya
                                             akun itu langsung berhenti bisa membuka data
                                             binaan manapun. --}}
                                        <form method="POST" action="{{ route('pendampings.destroy', $pendamping) }}" class="inline"
                                              onsubmit="return confirm('Hapus Permanen?\n\nData yang dihapus tidak dapat dipulihkan. Pastikan data ini memang sudah tidak diperlukan.');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-status-danger hover:underline text-xs font-medium">Hapus Permanen</button>
                                        </form>

                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    @if ($pendampings->isNotEmpty())
        <div>{{ $pendampings->links() }}</div>
    @endif
</div>
@endsection
