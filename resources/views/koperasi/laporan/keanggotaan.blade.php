@extends('layouts.app')

@section('title', 'Laporan Keanggotaan')

@section('content')
<div class="space-y-4">
    <div class="flex items-center justify-between flex-wrap gap-2">
        <div>
            <h2 class="text-lg font-semibold text-text-main">Laporan Keanggotaan</h2>
            <p class="text-sm text-text-secondary">{{ $periodeLabel }}</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('koperasi.laporan.keanggotaan.pdf') }}" class="bg-koperasi-primary text-white text-sm font-medium px-4 py-2 rounded-md hover:opacity-90 transition">Export PDF</a>
            <a href="{{ route('koperasi.laporan.keanggotaan.excel') }}" class="bg-koperasi-accent text-white text-sm font-medium px-4 py-2 rounded-md hover:opacity-90 transition">Export Excel</a>
        </div>
    </div>

    <div class="grid grid-cols-3 gap-3">
        <div class="bg-white border border-border-soft rounded-lg p-3"><p class="text-xs text-text-secondary">Total Anggota</p><p class="font-bold text-lg">{{ $totalAnggota }}</p></div>
        <div class="bg-white border border-border-soft rounded-lg p-3"><p class="text-xs text-text-secondary">Aktif</p><p class="font-bold text-lg text-status-success">{{ $anggotaAktif }}</p></div>
        <div class="bg-white border border-border-soft rounded-lg p-3"><p class="text-xs text-text-secondary">Tidak Aktif</p><p class="font-bold text-lg text-text-secondary">{{ $anggotaTidakAktif }}</p></div>
    </div>

    <div class="bg-white border border-border-soft rounded-2xl shadow-sm overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-text-secondary border-b bg-surface-bg">
                    <th class="py-2 px-3">Nama</th><th class="py-2 px-3">Nomor Anggota</th><th class="py-2 px-3">Tanggal Bergabung</th><th class="py-2 px-3">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($anggota as $a)
                    <tr class="border-b last:border-0">
                        <td class="py-2 px-3">{{ $a->namaTampil() }}</td>
                        <td class="py-2 px-3">{{ $a->nomor_anggota ?? '-' }}</td>
                        <td class="py-2 px-3">{{ optional($a->tanggal_bergabung)->format('d-m-Y') ?? '-' }}</td>
                        <td class="py-2 px-3">
                            <span class="px-2 py-0.5 rounded-full text-xs font-semibold {{ $a->status === 'Aktif' ? 'bg-primary-light text-status-success' : 'bg-gray-100 text-text-secondary' }}">{{ $a->status }}</span>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="py-8 text-center text-text-secondary italic">Belum ada anggota.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
