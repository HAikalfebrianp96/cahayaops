@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
@php
    // Panel aktif ditentukan controller ($modul: 'semua' | 'preneur' | 'koperasi').
    // Switcher HANYA muncul untuk Admin — Pendamping tidak punya akses modul
    // Koperasi, jadi baginya halaman ini tetap Dashboard Cahaya Preneur biasa
    // dan $modul selalu 'preneur'.
    $kop = $modul === 'koperasi';
    $semua = $modul === 'semua';

    // Tab switcher: 'Semua Kegiatan' paling kiri sebagai default netral.
    $tabs = [
        ['modul' => 'semua', 'label' => 'Semua Kegiatan', 'query' => []],
        ['modul' => 'preneur', 'label' => 'Cahaya Preneur', 'query' => ['modul' => 'preneur']],
        ['modul' => 'koperasi', 'label' => 'Koperasi Cahaya', 'query' => ['modul' => 'koperasi']],
    ];
@endphp
<div class="space-y-6">

    {{-- 1. HEADER — TANPA tombol aksi. Dashboard murni halaman ringkasan
         (switcher + kartu statistik/grafik); pekerjaannya dilakukan di
         halaman modul masing-masing lewat sidebar. --}}
    <div>
        <h2 class="text-[22px] font-bold text-text-main">Selamat datang, {{ explode(' ', auth()->user()->name)[0] ?? 'Admin' }} 👋</h2>

        {{-- IDENTITAS SISTEM — SELALU tampil, tidak bergantung tab manapun.
             Dibuat lebih kecil dari subtitle di bawahnya supaya jelas ini cuma
             penanda identitas, bukan keterangan modul yang sedang dibuka. --}}
        <p class="text-[11px] text-text-secondary mt-1">
            CahayaOS — Sistem Terpadu Cahaya Preneur &amp; Koperasi Cahaya
        </p>

        {{-- Subtitle mengikuti tab modul yang aktif. Di tab netral "Semua
             Kegiatan" sengaja TIDAK ada subtitle modul — kalau ada, salah satu
             modul jadi terlihat "terpilih", padahal itu justru yang mau dihindari.
             Baris identitas di atas sudah cukup mewakili.
             Keterangan periode untuk tab Koperasi TIDAK di sini — sudah pindah
             ke dalam panel Koperasi, menempel pada angka yang memang dibatasi
             periode itu (lihat dashboard/panel-koperasi.blade.php). --}}
        @unless ($semua)
            <p class="text-sm text-text-secondary mt-0.5">
                @if ($kop)
                    Koperasi Cahaya — Usaha Bersama, Maju Bersama
                @else
                    Cahaya Preneur — Program Ekonomi YBM PLN UP3 Bengkulu
                @endif
            </p>
        @endunless
    </div>

    {{-- 2. SWITCHER MODUL — khusus Admin. Segmented control, pakai token warna
         CahayaOS yang sudah ada (cahaya-primary / surface-bg / border-soft). --}}
    @if ($isAdmin)
        <div class="inline-flex items-center gap-1 bg-white border border-border-soft rounded-xl p-1 shadow-sm">
            @foreach ($tabs as $tab)
                <a href="{{ route('dashboard', $tab['query']) }}"
                   class="px-4 py-2 rounded-lg text-[13px] font-semibold transition
                          {{ $modul === $tab['modul'] ? 'bg-cahaya-primary text-white' : 'text-text-secondary hover:bg-surface-bg' }}">
                    {{ $tab['label'] }}
                </a>
            @endforeach
        </div>
    @endif

    {{-- 3. PANEL --}}
    @if ($semua)
        @include('dashboard.panel-semua')
    @elseif ($kop)
        @include('dashboard.panel-koperasi')
    @else
        @include('dashboard.panel-preneur')
    @endif
</div>
@endsection
