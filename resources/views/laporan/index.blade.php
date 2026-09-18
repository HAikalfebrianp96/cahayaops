@extends('layouts.app')
@section('title', 'Laporan')
@section('content')
@php $isAdmin = auth()->user()->isAdmin(); @endphp
<div class="space-y-5">
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-[20px] font-bold text-text-main">Laporan</h2>
            <p class="text-sm text-text-secondary mt-0.5">
                {{ $isAdmin ? 'Laporan seluruh peserta Program Cahaya Preneur.' : 'Laporan untuk peserta binaan Anda.' }}
            </p>
        </div>
        <a href="{{ route('laporan.download-semua') }}" class="bg-cahaya-primary text-white text-sm font-medium px-4 py-2.5 rounded-lg hover:opacity-90 transition whitespace-nowrap">
            Download Semua (ZIP)
        </a>
    </div>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <a href="{{ route('laporan.peserta') }}" class="bg-white border border-border-soft rounded-2xl shadow-sm p-5 hover:shadow-md hover:border-status-info/40 transition">
            <p class="font-semibold text-text-main">Laporan Peserta</p>
            <p class="text-xs text-text-secondary mt-1">Daftar peserta, usaha, kategori, dan status.</p>
        </a>
        <a href="{{ route('laporan.omzet') }}" class="bg-white border border-border-soft rounded-2xl shadow-sm p-5 hover:shadow-md hover:border-status-info/40 transition">
            <p class="font-semibold text-text-main">Laporan Omzet</p>
            <p class="text-xs text-text-secondary mt-1">Rekap laporan omzet per periode.</p>
        </a>
        <a href="{{ route('laporan.perkembangan') }}" class="bg-white border border-border-soft rounded-2xl shadow-sm p-5 hover:shadow-md hover:border-status-info/40 transition">
            <p class="font-semibold text-text-main">Laporan Perkembangan Usaha</p>
            <p class="text-xs text-text-secondary mt-1">Perubahan baseline &rarr; endline per peserta.</p>
        </a>
        <a href="{{ route('laporan.pendampingan') }}" class="bg-white border border-border-soft rounded-2xl shadow-sm p-5 hover:shadow-md hover:border-status-info/40 transition">
            <p class="font-semibold text-text-main">Laporan Pendampingan</p>
            <p class="text-xs text-text-secondary mt-1">Riwayat kegiatan pendampingan peserta.</p>
        </a>
        <a href="{{ route('laporan.pendamping') }}" class="bg-white border border-border-soft rounded-2xl shadow-sm p-5 hover:shadow-md hover:border-status-info/40 transition">
            <p class="font-semibold text-text-main">Kinerja Pendamping</p>
            <p class="text-xs text-text-secondary mt-1">Beban binaan &amp; aktivitas tiap pendamping.</p>
        </a>
        <a href="{{ route('laporan.pelatihan') }}" class="bg-white border border-border-soft rounded-2xl shadow-sm p-5 hover:shadow-md hover:border-status-info/40 transition">
            <p class="font-semibold text-text-main">Laporan Pelatihan</p>
            <p class="text-xs text-text-secondary mt-1">Riwayat pelatihan yang diikuti peserta.</p>
        </a>
        <a href="{{ route('laporan.legalitas') }}" class="bg-white border border-border-soft rounded-2xl shadow-sm p-5 hover:shadow-md hover:border-status-info/40 transition">
            <p class="font-semibold text-text-main">Laporan Legalitas &amp; Sertifikasi</p>
            <p class="text-xs text-text-secondary mt-1">Status legalitas/sertifikasi seluruh jenis (dinamis).</p>
        </a>
        @if ($isAdmin)
            <a href="{{ route('laporan.rekap') }}" class="bg-white border border-border-soft rounded-2xl shadow-sm p-5 hover:shadow-md hover:border-status-info/40 transition">
                <p class="font-semibold text-text-main">Rekap Program</p>
                <p class="text-xs text-text-secondary mt-1">Ringkasan keseluruhan Program Cahaya Preneur.</p>
            </a>
        @endif
    </div>
</div>
@endsection
