@extends('layouts.app')

@section('title', 'Laporan Unit Usaha')

@section('content')
<div class="space-y-4">
    <div class="flex items-center justify-between flex-wrap gap-2">
        <div>
            <h2 class="text-lg font-semibold text-text-main">Laporan Unit Usaha</h2>
            <p class="text-sm text-text-secondary">Periode: {{ $periodeLabel }}</p>
        </div>
        @if ($unit)
            <div class="flex gap-2">
                <a href="{{ route('koperasi.laporan.unit-usaha.pdf', request()->query()) }}" class="bg-koperasi-primary text-white text-sm font-medium px-4 py-2 rounded-md hover:opacity-90 transition">Export PDF</a>
                <a href="{{ route('koperasi.laporan.unit-usaha.excel', request()->query()) }}" class="bg-koperasi-accent text-white text-sm font-medium px-4 py-2 rounded-md hover:opacity-90 transition">Export Excel</a>
            </div>
        @endif
    </div>

    <form method="GET" class="bg-white border border-border-soft rounded-2xl shadow-sm p-4 flex flex-wrap gap-3">
        <select name="unit_id" class="rounded-md border border-gray-300 px-2 py-1.5 text-sm">
            @foreach ($units as $u)
                <option value="{{ $u->id }}" {{ $filters['unit_id'] == $u->id ? 'selected' : '' }}>{{ $u->nama }}</option>
            @endforeach
        </select>
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
        <button type="submit" class="bg-gray-100 text-text-main text-sm px-3 py-1.5 rounded-md hover:bg-gray-200 transition">Tampilkan</button>
    </form>

    @if (! $unit)
        <p class="text-sm text-text-secondary italic">Belum ada unit usaha.</p>
    @else
        <div class="bg-white border border-border-soft rounded-2xl shadow-sm p-6">
            <h3 class="text-base font-semibold text-text-main">{{ $unit->nama }}</h3>
            <p class="text-xs text-text-secondary mb-4">{{ $unit->kode }} &middot; {{ $unit->tipe ?: '-' }}</p>

            @if ($ringkasanUnit)
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    <div class="border border-border-soft rounded-lg p-3"><p class="text-xs text-text-secondary">Penjualan</p><p class="font-bold">{{ rupiah($ringkasanUnit['penjualan']) }}</p></div>
                    <div class="border border-border-soft rounded-lg p-3"><p class="text-xs text-text-secondary">HPP</p><p class="font-bold">{{ rupiah($ringkasanUnit['hpp']) }}</p></div>
                    <div class="border border-border-soft rounded-lg p-3"><p class="text-xs text-text-secondary">Fee</p><p class="font-bold">{{ rupiah($ringkasanUnit['fee_koperasi']) }}</p></div>
                    <div class="border border-border-soft rounded-lg p-3"><p class="text-xs text-text-secondary">Hak Mitra</p><p class="font-bold">{{ rupiah($ringkasanUnit['hak_mitra']) }}</p></div>
                    <div class="border border-border-soft rounded-lg p-3"><p class="text-xs text-text-secondary">Pengeluaran</p><p class="font-bold">{{ rupiah($ringkasanUnit['pengeluaran']) }}</p></div>
                    <div class="border border-border-soft rounded-lg p-3 bg-surface-bg col-span-2"><p class="text-xs text-text-secondary">Laba Bersih</p><p class="font-bold text-lg {{ $ringkasanUnit['laba_bersih'] >= 0 ? 'text-status-success' : 'text-status-danger' }}">{{ rupiah($ringkasanUnit['laba_bersih']) }}</p></div>
                </div>
            @endif
        </div>

        <div class="bg-white border border-border-soft rounded-2xl shadow-sm p-6">
            <h4 class="text-sm font-semibold text-text-main mb-3">Daftar Transaksi <span class="text-xs text-text-secondary font-normal">(50 terbaru)</span></h4>
            <div class="overflow-x-auto -mx-1 px-1">
                <table class="w-full text-sm min-w-[440px]">
                <thead><tr class="text-left text-text-secondary border-b"><th class="py-1.5">Tanggal</th><th class="py-1.5">Customer</th><th class="py-1.5">Total</th></tr></thead>
                <tbody>
                    @forelse ($transaksi as $t)
                        <tr class="border-b last:border-0">
                            <td class="py-1.5">{{ $t->tanggal->format('d-m-Y') }}</td>
                            <td class="py-1.5">{{ $t->customer->nama ?? '-' }}</td>
                            <td class="py-1.5">{{ rupiah($t->total) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="py-3 text-center text-text-secondary italic">Belum ada transaksi.</td></tr>
                    @endforelse
                </tbody>
            </table>
            </div>
        </div>

        <div class="bg-white border border-border-soft rounded-2xl shadow-sm p-6">
            <h4 class="text-sm font-semibold text-text-main mb-3">Daftar Pengeluaran <span class="text-xs text-text-secondary font-normal">(50 terbaru)</span></h4>
            <div class="overflow-x-auto -mx-1 px-1">
                <table class="w-full text-sm min-w-[440px]">
                <thead><tr class="text-left text-text-secondary border-b"><th class="py-1.5">Tanggal</th><th class="py-1.5">Kategori</th><th class="py-1.5">Nominal</th></tr></thead>
                <tbody>
                    @forelse ($pengeluaran as $p)
                        <tr class="border-b last:border-0">
                            <td class="py-1.5">{{ $p->tanggal->format('d-m-Y') }}</td>
                            <td class="py-1.5">{{ $p->kategori }}</td>
                            <td class="py-1.5">{{ rupiah($p->nominal) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="py-3 text-center text-text-secondary italic">Belum ada pengeluaran.</td></tr>
                    @endforelse
                </tbody>
            </table>
            </div>
        </div>
    @endif
</div>
@endsection
