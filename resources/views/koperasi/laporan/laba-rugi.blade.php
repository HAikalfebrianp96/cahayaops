@extends('layouts.app')

@section('title', 'Laporan Laba Rugi')

@section('content')
<div class="space-y-4">
    <div class="flex items-center justify-between flex-wrap gap-2">
        <div>
            <h2 class="text-lg font-semibold text-text-main">Laporan Laba Rugi</h2>
            <p class="text-sm text-text-secondary">Periode: {{ $periodeLabel }} @if($unitLabel) &middot; {{ $unitLabel }} @endif</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('koperasi.laporan.laba-rugi.pdf', request()->query()) }}" class="bg-koperasi-primary text-white text-sm font-medium px-4 py-2 rounded-md hover:opacity-90 transition">
                Export PDF
            </a>
            <a href="{{ route('koperasi.laporan.laba-rugi.excel', request()->query()) }}" class="bg-koperasi-accent text-white text-sm font-medium px-4 py-2 rounded-md hover:opacity-90 transition">
                Export Excel
            </a>
        </div>
    </div>

    <form method="GET" class="bg-white border border-border-soft rounded-2xl shadow-sm p-4 flex flex-wrap gap-3">
        <select name="tahun" class="rounded-md border border-gray-300 px-2 py-1.5 text-sm">
            <option value="default" {{ $filters['tahun'] === 'default' ? 'selected' : '' }}>Tahun Ini</option>
            <option value="semua" {{ $filters['tahun'] === 'semua' ? 'selected' : '' }}>Semua Tahun</option>
            @for ($y = now()->year; $y >= now()->year - 3; $y--)
                <option value="{{ $y }}" {{ $filters['tahun'] == $y ? 'selected' : '' }}>{{ $y }}</option>
            @endfor
        </select>
        <select name="bulan" class="rounded-md border border-gray-300 px-2 py-1.5 text-sm">
            <option value="default" {{ $filters['bulan'] === 'default' ? 'selected' : '' }}>Semua Bulan</option>
            @foreach (['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'] as $i => $nm)
                <option value="{{ $i + 1 }}" {{ $filters['bulan'] == ($i + 1) ? 'selected' : '' }}>{{ $nm }}</option>
            @endforeach
        </select>
        <select name="unit_id" class="rounded-md border border-gray-300 px-2 py-1.5 text-sm">
            <option value="">Semua Unit</option>
            <option value="operasional" {{ $filters['unit_id'] === 'operasional' ? 'selected' : '' }}>Operasional Koperasi</option>
            @foreach ($units as $u)
                <option value="{{ $u->id }}" {{ $filters['unit_id'] == $u->id ? 'selected' : '' }}>{{ $u->nama }}</option>
            @endforeach
        </select>
        <button type="submit" class="bg-gray-100 text-text-main text-sm px-3 py-1.5 rounded-md hover:bg-gray-200 transition">Filter</button>
    </form>

    <div class="bg-white border border-border-soft rounded-2xl shadow-sm p-6">
        @if (! $lr['ada_transaksi'] && ! $lr['ada_pengeluaran'])
            <p class="text-sm text-text-secondary italic py-4">Belum ada data pada periode ini.</p>
        @else
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
                <div class="border border-border-soft rounded-lg p-3"><p class="text-xs text-text-secondary">Penjualan</p><p class="font-bold">{{ rupiah($lr['total']['penjualan']) }}</p></div>
                <div class="border border-border-soft rounded-lg p-3"><p class="text-xs text-text-secondary">HPP</p><p class="font-bold">{{ rupiah($lr['total']['hpp']) }}</p></div>
                <div class="border border-border-soft rounded-lg p-3"><p class="text-xs text-text-secondary">Laba Kotor</p><p class="font-bold">{{ rupiah($lr['total']['laba_kotor']) }}</p></div>
                <div class="border border-border-soft rounded-lg p-3"><p class="text-xs text-text-secondary">Fee Koperasi</p><p class="font-bold">{{ rupiah($lr['total']['fee_koperasi']) }}</p></div>
                <div class="border border-border-soft rounded-lg p-3"><p class="text-xs text-text-secondary">Hak Mitra</p><p class="font-bold text-text-secondary">{{ rupiah($lr['total']['hak_mitra']) }}</p></div>
                <div class="border border-border-soft rounded-lg p-3"><p class="text-xs text-text-secondary">Pengeluaran</p><p class="font-bold">{{ rupiah($lr['total']['pengeluaran_total']) }}</p></div>
                <div class="border border-border-soft rounded-lg p-3 bg-surface-bg col-span-2">
                    <p class="text-xs text-text-secondary">Laba Bersih</p>
                    <p class="font-bold text-lg {{ $lr['total']['laba_bersih'] >= 0 ? 'text-status-success' : 'text-status-danger' }}">
                        {{ rupiah($lr['total']['laba_bersih']) }}
                        @if ($lr['total']['margin'] !== null)<span class="text-xs font-normal text-text-secondary">(margin {{ number_format($lr['total']['margin'], 1) }}%)</span>@endif
                    </p>
                </div>
            </div>

            <h3 class="text-xs font-semibold text-text-secondary mb-2">Per Unit</h3>
            <div class="overflow-x-auto -mx-1 px-1">
                <table class="w-full text-sm min-w-[640px]">
                <thead>
                    <tr class="text-left text-text-secondary border-b">
                        <th class="py-1.5 pr-3">Unit</th><th class="py-1.5 pr-3">Penjualan</th><th class="py-1.5 pr-3">HPP</th>
                        <th class="py-1.5 pr-3">Fee</th><th class="py-1.5 pr-3">Pengeluaran</th><th class="py-1.5">Laba Bersih</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($lr['per_unit'] as $u)
                        <tr class="border-b last:border-0">
                            <td class="py-1.5 pr-3 font-medium">{{ $u['nama'] }}</td>
                            <td class="py-1.5 pr-3">{{ rupiah($u['penjualan']) }}</td>
                            <td class="py-1.5 pr-3">{{ rupiah($u['hpp']) }}</td>
                            <td class="py-1.5 pr-3">{{ $u['fee_koperasi'] > 0 ? 'Rp' . number_format($u['fee_koperasi'], 0, ',', '.') : '-' }}</td>
                            <td class="py-1.5 pr-3">{{ rupiah($u['pengeluaran']) }}</td>
                            <td class="py-1.5 {{ $u['laba_bersih'] >= 0 ? 'text-status-success' : 'text-status-danger' }} font-semibold">{{ rupiah($u['laba_bersih']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-3 text-center text-text-secondary italic">Belum ada unit usaha.</td></tr>
                    @endforelse
                </tbody>
            </table>
            </div>
        @endif
    </div>
</div>
@endsection
