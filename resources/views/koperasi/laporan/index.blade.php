@extends('layouts.app')

@section('title', 'Laporan Koperasi')

@section('content')
<div class="space-y-4">
    <div>
        <h2 class="text-lg font-semibold text-text-main">Laporan</h2>
        <p class="text-sm text-text-secondary">Laporan Koperasi Cahaya — satu sumber data dengan halaman Keuangan.</p>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <a href="{{ route('koperasi.laporan.ringkasan') }}" class="bg-white border border-border-soft rounded-2xl shadow-sm p-5 hover:border-koperasi-primary transition">
            <p class="font-semibold text-text-main">Laporan Ringkasan Koperasi</p>
            <p class="text-xs text-text-secondary mt-1">Satu halaman utuh: anggota, unit usaha, laba rugi, dana sosial.</p>
        </a>
        <a href="{{ route('koperasi.laporan.laba-rugi') }}" class="bg-white border border-border-soft rounded-2xl shadow-sm p-5 hover:border-koperasi-primary transition">
            <p class="font-semibold text-text-main">Laporan Laba Rugi</p>
            <p class="text-xs text-text-secondary mt-1">Per unit & total koperasi, bisa difilter tahun/bulan/unit.</p>
        </a>
        <a href="{{ route('koperasi.laporan.penjualan') }}" class="bg-white border border-border-soft rounded-2xl shadow-sm p-5 hover:border-koperasi-primary transition">
            <p class="font-semibold text-text-main">Laporan Penjualan</p>
            <p class="text-xs text-text-secondary mt-1">Daftar transaksi, filter periode/unit/status pembayaran.</p>
        </a>
        <a href="{{ route('koperasi.laporan.pengeluaran') }}" class="bg-white border border-border-soft rounded-2xl shadow-sm p-5 hover:border-koperasi-primary transition">
            <p class="font-semibold text-text-main">Laporan Pengeluaran</p>
            <p class="text-xs text-text-secondary mt-1">Breakdown per unit & kategori.</p>
        </a>
        <a href="{{ route('koperasi.laporan.unit-usaha') }}" class="bg-white border border-border-soft rounded-2xl shadow-sm p-5 hover:border-koperasi-primary transition">
            <p class="font-semibold text-text-main">Laporan Unit Usaha</p>
            <p class="text-xs text-text-secondary mt-1">Detail per unit: laba rugi, transaksi, pengeluaran.</p>
        </a>
        <a href="{{ route('koperasi.laporan.keanggotaan') }}" class="bg-white border border-border-soft rounded-2xl shadow-sm p-5 hover:border-koperasi-primary transition">
            <p class="font-semibold text-text-main">Laporan Keanggotaan</p>
            <p class="text-xs text-text-secondary mt-1">Daftar anggota, status aktif/tidak aktif.</p>
        </a>
        <a href="{{ route('koperasi.laporan.simpanan') }}" class="bg-white border border-border-soft rounded-2xl shadow-sm p-5 hover:border-koperasi-primary transition">
            <p class="font-semibold text-text-main">Laporan Simpanan</p>
            <p class="text-xs text-text-secondary mt-1">Simpanan Pokok & Wajib — dana anggota, bukan laba.</p>
        </a>
        <a href="{{ route('koperasi.laporan.dana-sosial') }}" class="bg-white border border-border-soft rounded-2xl shadow-sm p-5 hover:border-koperasi-primary transition">
            <p class="font-semibold text-text-main">Laporan Dana Sosial</p>
            <p class="text-xs text-text-secondary mt-1">Dana Sukarela & Dana Hibah — terpisah dari laba usaha.</p>
        </a>
    </div>
</div>
@endsection
