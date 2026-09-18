@extends('layouts.app')

@section('title', 'Laporan Simpanan')

@section('content')
<div class="space-y-4">
    <div class="flex items-center justify-between flex-wrap gap-2">
        <div>
            <h2 class="text-lg font-semibold text-text-main">Laporan Simpanan</h2>
            <p class="text-sm text-text-secondary">Periode: {{ $periodeLabel }} — <span class="italic">Dana Anggota, bukan pendapatan usaha</span></p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('koperasi.laporan.simpanan.pdf', request()->query()) }}" class="bg-koperasi-primary text-white text-sm font-medium px-4 py-2 rounded-md hover:opacity-90 transition">Export PDF</a>
            <a href="{{ route('koperasi.laporan.simpanan.excel', request()->query()) }}" class="bg-koperasi-accent text-white text-sm font-medium px-4 py-2 rounded-md hover:opacity-90 transition">Export Excel</a>
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
        <button type="submit" class="bg-gray-100 text-text-main text-sm px-3 py-1.5 rounded-md hover:bg-gray-200 transition">Filter</button>
    </form>

    <div class="grid grid-cols-2 gap-3">
        <div class="bg-white border border-border-soft rounded-lg p-3"><p class="text-xs text-text-secondary">Total Simpanan Pokok</p><p class="font-bold text-lg">{{ rupiah($totalPokok) }}</p></div>
        <div class="bg-white border border-border-soft rounded-lg p-3"><p class="text-xs text-text-secondary">Total Simpanan Wajib</p><p class="font-bold text-lg">{{ rupiah($totalWajib) }}</p></div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div class="bg-white border border-border-soft rounded-2xl shadow-sm p-4">
            <h4 class="text-xs font-semibold text-text-secondary mb-2">Riwayat Simpanan Pokok</h4>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead><tr class="text-left text-text-secondary border-b"><th class="py-1.5">Tanggal</th><th class="py-1.5">Nominal</th></tr></thead>
                    <tbody>
                        @forelse ($rowsPokok as $r)
                            <tr class="border-b last:border-0"><td class="py-1.5">{{ $r->tanggal->format('d-m-Y') }}</td><td class="py-1.5">{{ rupiah($r->nominal) }}</td></tr>
                        @empty
                            <tr><td colspan="2" class="py-3 text-center text-text-secondary italic">Belum ada data.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="bg-white border border-border-soft rounded-2xl shadow-sm p-4">
            <h4 class="text-xs font-semibold text-text-secondary mb-2">Riwayat Simpanan Wajib</h4>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead><tr class="text-left text-text-secondary border-b"><th class="py-1.5">Tanggal</th><th class="py-1.5">Nominal</th></tr></thead>
                    <tbody>
                        @forelse ($rowsWajib as $r)
                            <tr class="border-b last:border-0"><td class="py-1.5">{{ $r->tanggal->format('d-m-Y') }}</td><td class="py-1.5">{{ rupiah($r->nominal) }}</td></tr>
                        @empty
                            <tr><td colspan="2" class="py-3 text-center text-text-secondary italic">Belum ada data.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
