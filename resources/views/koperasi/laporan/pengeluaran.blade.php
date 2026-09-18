@extends('layouts.app')

@section('title', 'Laporan Pengeluaran')

@section('content')
<div class="space-y-4">
    <div class="flex items-center justify-between flex-wrap gap-2">
        <div>
            <h2 class="text-lg font-semibold text-text-main">Laporan Pengeluaran</h2>
            <p class="text-sm text-text-secondary">Periode: {{ $periodeLabel }}</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('koperasi.laporan.pengeluaran.pdf', request()->query()) }}" class="bg-koperasi-primary text-white text-sm font-medium px-4 py-2 rounded-md hover:opacity-90 transition">Export PDF</a>
            <a href="{{ route('koperasi.laporan.pengeluaran.excel', request()->query()) }}" class="bg-koperasi-accent text-white text-sm font-medium px-4 py-2 rounded-md hover:opacity-90 transition">Export Excel</a>
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
        <select name="kategori" class="rounded-md border border-gray-300 px-2 py-1.5 text-sm">
            <option value="">Semua Kategori</option>
            @foreach ($kategoriList as $k)
                <option value="{{ $k }}" {{ $filters['kategori'] === $k ? 'selected' : '' }}>{{ $k }}</option>
            @endforeach
        </select>
        <select name="status" class="rounded-md border border-gray-300 px-2 py-1.5 text-sm">
            <option value="Aktif" {{ $filters['status'] === 'Aktif' ? 'selected' : '' }}>Aktif Saja</option>
            <option value="semua" {{ $filters['status'] === 'semua' ? 'selected' : '' }}>Semua (termasuk Dibatalkan)</option>
        </select>
        <button type="submit" class="bg-gray-100 text-text-main text-sm px-3 py-1.5 rounded-md hover:bg-gray-200 transition">Filter</button>
    </form>

    <div class="bg-white border border-border-soft rounded-2xl shadow-sm p-4">
        <p class="text-xs text-text-secondary">Total Pengeluaran Aktif</p>
        <p class="text-xl font-bold text-koperasi-primary">{{ rupiah($totalAktif) }}</p>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div class="bg-white border border-border-soft rounded-2xl shadow-sm p-4">
            <h4 class="text-xs font-semibold text-text-secondary mb-2">Per Unit</h4>
            @forelse ($perUnit as $u)
                <p class="text-sm flex justify-between"><span>{{ $u['label'] }}</span><span class="font-semibold">{{ rupiah($u['total']) }}</span></p>
            @empty
                <p class="text-sm text-text-secondary italic">Belum ada data.</p>
            @endforelse
        </div>
        <div class="bg-white border border-border-soft rounded-2xl shadow-sm p-4">
            <h4 class="text-xs font-semibold text-text-secondary mb-2">Per Kategori</h4>
            @forelse ($perKategori as $k)
                <p class="text-sm flex justify-between"><span>{{ $k['kategori'] }}</span><span class="font-semibold">{{ rupiah($k['total']) }}</span></p>
            @empty
                <p class="text-sm text-text-secondary italic">Belum ada data.</p>
            @endforelse
        </div>
    </div>

    <div class="bg-white border border-border-soft rounded-2xl shadow-sm overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-text-secondary border-b bg-surface-bg">
                    <th class="py-2 px-3">Tanggal</th><th class="py-2 px-3">Unit</th><th class="py-2 px-3">Kategori</th>
                    <th class="py-2 px-3">Keterangan</th><th class="py-2 px-3">Metode</th><th class="py-2 px-3">Nominal</th><th class="py-2 px-3">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $r)
                    <tr class="border-b last:border-0">
                        <td class="py-2 px-3">{{ $r->tanggal->format('d-m-Y') }}</td>
                        <td class="py-2 px-3">{{ $r->labelUnit() }}</td>
                        <td class="py-2 px-3">{{ $r->kategori }}</td>
                        <td class="py-2 px-3">{{ $r->keterangan ?: '-' }}</td>
                        <td class="py-2 px-3">{{ $r->metode_pembayaran }}</td>
                        <td class="py-2 px-3">{{ rupiah($r->nominal) }}</td>
                        <td class="py-2 px-3">
                            <span class="px-2 py-0.5 rounded-full text-xs font-semibold {{ $r->status === 'Aktif' ? 'bg-primary-light text-status-success' : 'bg-red-100 text-status-danger' }}">{{ $r->status }}</span>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="py-8 text-center text-text-secondary italic">Belum ada data pada periode ini.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
