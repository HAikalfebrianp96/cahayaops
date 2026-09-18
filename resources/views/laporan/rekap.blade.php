@extends('layouts.app')

@section('title', 'Rekap Program')

@section('content')
<div class="space-y-5">
    <div class="flex items-center justify-between no-print">
        <h2 class="text-[20px] font-bold text-text-main">Rekap Program Cahaya Preneur</h2>
        <a href="{{ route('laporan.rekap.export-pdf') }}" class="bg-cahaya-primary text-white text-sm font-medium px-4 py-2 rounded-lg hover:opacity-90 transition">
            Export PDF
        </a>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <div class="bg-white rounded-2xl shadow-sm border border-border-soft p-5">
            <p class="text-sm text-text-secondary">Total Peserta</p>
            <p class="text-2xl font-bold text-cahaya-primary mt-1">{{ $totalPeserta }}</p>
        </div>
        <div class="bg-white rounded-2xl shadow-sm border border-border-soft p-5">
            <p class="text-sm text-text-secondary">Total Pendamping</p>
            <p class="text-2xl font-bold text-cahaya-primary mt-1">{{ $totalPendamping }}</p>
        </div>
        <div class="bg-white rounded-2xl shadow-sm border border-border-soft p-5">
            <p class="text-sm text-text-secondary">Total Omzet (semua periode)</p>
            <p class="text-2xl font-bold text-cahaya-primary mt-1">{{ rupiah($totalOmzet) }}</p>
        </div>
        <div class="bg-white rounded-2xl shadow-sm border border-border-soft p-5">
            <p class="text-sm text-text-secondary">Rata-rata Omzet per Laporan</p>
            <p class="text-2xl font-bold text-cahaya-primary mt-1">{{ $rataRataOmzet !== null ? rupiah($rataRataOmzet) : '—' }}</p>
        </div>
        <div class="bg-white rounded-2xl shadow-sm border border-border-soft p-5">
            <p class="text-sm text-text-secondary">Total Kegiatan Pendampingan</p>
            <p class="text-2xl font-bold text-cahaya-primary mt-1">{{ $totalPendampingan }}</p>
        </div>
        <div class="bg-white rounded-2xl shadow-sm border border-border-soft p-5">
            <p class="text-sm text-text-secondary">Total Pelatihan</p>
            <p class="text-2xl font-bold text-cahaya-primary mt-1">{{ $totalPelatihan }}</p>
        </div>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-border-soft p-6">
        <h3 class="text-sm font-semibold text-text-main mb-4">Legalitas &amp; Sertifikasi — Ringkasan per Jenis</h3>
        @if ($legalitasPerJenis->isEmpty())
            <p class="text-sm text-text-secondary italic">Belum ada data.</p>
        @else
            <div class="space-y-2">
                @foreach ($legalitasPerJenis as $item)
                    <div class="flex items-center justify-between text-sm border-b border-border-soft pb-2 last:border-0">
                        <span class="text-text-main">{{ $item->jenis_dokumen }}</span>
                        <span class="font-bold text-cahaya-primary">{{ $item->jumlah }}</span>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
@endsection
