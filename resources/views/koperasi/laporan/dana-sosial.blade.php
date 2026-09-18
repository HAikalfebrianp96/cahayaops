@extends('layouts.app')

@section('title', 'Laporan Dana Sosial')

@section('content')
<div class="space-y-4">
    <div class="flex items-center justify-between flex-wrap gap-2">
        <div>
            <h2 class="text-lg font-semibold text-text-main">Laporan Dana Sosial</h2>
            <p class="text-sm text-text-secondary">Periode: {{ $periodeLabel }} — <span class="italic">tidak dihitung sebagai Laba Bersih</span></p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('koperasi.laporan.dana-sosial.pdf', request()->query()) }}" class="bg-koperasi-primary text-white text-sm font-medium px-4 py-2 rounded-md hover:opacity-90 transition">Export PDF</a>
            <a href="{{ route('koperasi.laporan.dana-sosial.excel', request()->query()) }}" class="bg-koperasi-accent text-white text-sm font-medium px-4 py-2 rounded-md hover:opacity-90 transition">Export Excel</a>
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

    <div>
        <h3 class="text-sm font-semibold text-text-main mb-2">Dana Sukarela</h3>
        <div class="grid grid-cols-3 gap-3 mb-3">
            <div class="bg-white border border-border-soft rounded-lg p-3"><p class="text-xs text-text-secondary">Dana Masuk</p><p class="font-bold">{{ rupiah($totalMasukSukarela) }}</p></div>
            <div class="bg-white border border-border-soft rounded-lg p-3"><p class="text-xs text-text-secondary">Penggunaan</p><p class="font-bold">{{ rupiah($totalKeluarSukarela) }}</p></div>
            <div class="bg-white border border-border-soft rounded-lg p-3"><p class="text-xs text-text-secondary">Saldo (Keseluruhan)</p><p class="font-bold text-koperasi-accent">{{ rupiah($totalMasukSukarela - $totalKeluarSukarela) }}</p></div>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="bg-white border border-border-soft rounded-2xl shadow-sm p-4">
                <h4 class="text-xs font-semibold text-text-secondary mb-2">Dana Masuk</h4>
                <div class="overflow-x-auto -mx-1 px-1">
                    <table class="w-full text-sm min-w-[440px]">
                    <thead><tr class="text-left text-text-secondary border-b"><th class="py-1">Tanggal</th><th class="py-1">Sumber</th><th class="py-1">Nominal</th></tr></thead>
                    <tbody>
                        @forelse ($sukarelaMasuk as $r)
                            <tr class="border-b last:border-0"><td class="py-1">{{ $r->tanggal->format('d-m-Y') }}</td><td class="py-1">{{ $r->sumber }}</td><td class="py-1">{{ rupiah($r->nominal) }}</td></tr>
                        @empty
                            <tr><td colspan="3" class="py-2 text-center text-text-secondary italic">Belum ada data.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                </div>
            </div>
            <div class="bg-white border border-border-soft rounded-2xl shadow-sm p-4">
                <h4 class="text-xs font-semibold text-text-secondary mb-2">Penggunaan</h4>
                <div class="overflow-x-auto -mx-1 px-1">
                    <table class="w-full text-sm min-w-[440px]">
                    <thead><tr class="text-left text-text-secondary border-b"><th class="py-1">Tanggal</th><th class="py-1">Keperluan</th><th class="py-1">Nominal</th></tr></thead>
                    <tbody>
                        @forelse ($sukarelaKeluar as $r)
                            <tr class="border-b last:border-0"><td class="py-1">{{ $r->tanggal->format('d-m-Y') }}</td><td class="py-1">{{ $r->keperluan }}</td><td class="py-1">{{ rupiah($r->nominal) }}</td></tr>
                        @empty
                            <tr><td colspan="3" class="py-2 text-center text-text-secondary italic">Belum ada data.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                </div>
            </div>
        </div>
    </div>

    <div>
        <h3 class="text-sm font-semibold text-text-main mb-2 mt-4">Dana Hibah</h3>
        <div class="grid grid-cols-3 gap-3 mb-3">
            <div class="bg-white border border-border-soft rounded-lg p-3"><p class="text-xs text-text-secondary">Dana Masuk</p><p class="font-bold">{{ rupiah($totalMasukHibah) }}</p></div>
            <div class="bg-white border border-border-soft rounded-lg p-3"><p class="text-xs text-text-secondary">Penggunaan</p><p class="font-bold">{{ rupiah($totalKeluarHibah) }}</p></div>
            <div class="bg-white border border-border-soft rounded-lg p-3"><p class="text-xs text-text-secondary">Saldo (Keseluruhan)</p><p class="font-bold text-koperasi-accent">{{ rupiah($totalMasukHibah - $totalKeluarHibah) }}</p></div>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="bg-white border border-border-soft rounded-2xl shadow-sm p-4">
                <h4 class="text-xs font-semibold text-text-secondary mb-2">Dana Masuk</h4>
                <div class="overflow-x-auto -mx-1 px-1">
                    <table class="w-full text-sm min-w-[440px]">
                    <thead><tr class="text-left text-text-secondary border-b"><th class="py-1">Tanggal</th><th class="py-1">Sumber</th><th class="py-1">Nominal</th></tr></thead>
                    <tbody>
                        @forelse ($hibahMasuk as $r)
                            <tr class="border-b last:border-0"><td class="py-1">{{ $r->tanggal->format('d-m-Y') }}</td><td class="py-1">{{ $r->sumber }}</td><td class="py-1">{{ rupiah($r->nominal) }}</td></tr>
                        @empty
                            <tr><td colspan="3" class="py-2 text-center text-text-secondary italic">Belum ada data.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                </div>
            </div>
            <div class="bg-white border border-border-soft rounded-2xl shadow-sm p-4">
                <h4 class="text-xs font-semibold text-text-secondary mb-2">Penggunaan</h4>
                <div class="overflow-x-auto -mx-1 px-1">
                    <table class="w-full text-sm min-w-[440px]">
                    <thead><tr class="text-left text-text-secondary border-b"><th class="py-1">Tanggal</th><th class="py-1">Keperluan</th><th class="py-1">Nominal</th></tr></thead>
                    <tbody>
                        @forelse ($hibahKeluar as $r)
                            <tr class="border-b last:border-0"><td class="py-1">{{ $r->tanggal->format('d-m-Y') }}</td><td class="py-1">{{ $r->keperluan }}</td><td class="py-1">{{ rupiah($r->nominal) }}</td></tr>
                        @empty
                            <tr><td colspan="3" class="py-2 text-center text-text-secondary italic">Belum ada data.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
