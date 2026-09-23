@extends('layouts.app')

@section('title', 'Laporan Penjualan')

@section('content')
<div class="space-y-4">
    <div class="flex items-center justify-between flex-wrap gap-2">
        <div>
            <h2 class="text-lg font-semibold text-text-main">Laporan Penjualan</h2>
            <p class="text-sm text-text-secondary">Periode: {{ $periodeLabel }}</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('koperasi.laporan.penjualan.pdf', request()->query()) }}" class="bg-koperasi-primary text-white text-sm font-medium px-4 py-2 rounded-md hover:opacity-90 transition">Export PDF</a>
            <a href="{{ route('koperasi.laporan.penjualan.excel', request()->query()) }}" class="bg-koperasi-accent text-white text-sm font-medium px-4 py-2 rounded-md hover:opacity-90 transition">Export Excel</a>
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
            @foreach ($units as $u)
                <option value="{{ $u->id }}" {{ $filters['unit_id'] == $u->id ? 'selected' : '' }}>{{ $u->nama }}</option>
            @endforeach
        </select>
        <select name="status_pembayaran" class="rounded-md border border-gray-300 px-2 py-1.5 text-sm">
            <option value="">Semua Status</option>
            @foreach (['Belum Dibayar','DP','Lunas'] as $s)
                <option value="{{ $s }}" {{ $filters['status_pembayaran'] === $s ? 'selected' : '' }}>{{ $s }}</option>
            @endforeach
        </select>
        <button type="submit" class="bg-gray-100 text-text-main text-sm px-3 py-1.5 rounded-md hover:bg-gray-200 transition">Filter</button>
    </form>

    <div class="grid grid-cols-2 sm:grid-cols-5 gap-3">
        <div class="bg-white border border-border-soft rounded-lg p-3"><p class="text-xs text-text-secondary">Jumlah Transaksi</p><p class="font-bold">{{ $jumlahTransaksi }}</p></div>
        <div class="bg-white border border-border-soft rounded-lg p-3"><p class="text-xs text-text-secondary" title="Yang harus dibayar pelanggan: setelah diskon, termasuk ongkir yang ditanggung pelanggan. Penjualan untuk laba rugi ada di Laporan Laba Rugi.">Total Tagihan</p><p class="font-bold">{{ rupiah($totalPenjualan) }}</p></div>
        <div class="bg-white border border-border-soft rounded-lg p-3"><p class="text-xs text-text-secondary">Total Dibayar</p><p class="font-bold">{{ rupiah($totalDibayar) }}</p></div>
        <div class="bg-white border border-border-soft rounded-lg p-3"><p class="text-xs text-text-secondary">Total Sisa</p><p class="font-bold">{{ rupiah($totalSisa) }}</p></div>
        <div class="bg-white border border-border-soft rounded-lg p-3"><p class="text-xs text-text-secondary">Total Fee Koperasi</p><p class="font-bold">{{ rupiah($totalFee) }}</p></div>
    </div>

    <div class="bg-white border border-border-soft rounded-2xl shadow-sm overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-text-secondary border-b bg-surface-bg">
                    <th class="py-2 px-3">Tanggal</th><th class="py-2 px-3">Invoice</th><th class="py-2 px-3">Unit</th>
                    <th class="py-2 px-3">Customer</th><th class="py-2 px-3">Total</th><th class="py-2 px-3">Dibayar</th>
                    <th class="py-2 px-3">Sisa</th><th class="py-2 px-3">Status</th><th class="py-2 px-3">Model Usaha</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $r)
                    <tr class="border-b last:border-0">
                        <td class="py-2 px-3">{{ $r['tanggal'] }}</td>
                        <td class="py-2 px-3">{{ $r['invoice'] }}</td>
                        <td class="py-2 px-3">{{ $r['unit'] }}</td>
                        <td class="py-2 px-3">{{ $r['customer'] }}</td>
                        <td class="py-2 px-3">{{ rupiah($r['total']) }}</td>
                        <td class="py-2 px-3">{{ rupiah($r['dibayar']) }}</td>
                        <td class="py-2 px-3">{{ rupiah($r['sisa']) }}</td>
                        <td class="py-2 px-3">{{ $r['status'] }}</td>
                        <td class="py-2 px-3">{{ $r['model_usaha'] }}</td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="py-8 text-center text-text-secondary italic">Belum ada data pada periode ini.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
