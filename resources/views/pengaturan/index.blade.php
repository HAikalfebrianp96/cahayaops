@extends('layouts.app')

@section('title', 'Pengaturan')

@section('content')
@php
    // Tab aktif ditentukan controller ($tab: 'semua' | 'preneur' | 'koperasi').
    // Nilai ?tab= yang tidak dikenal sudah dinormalkan jadi 'semua' di sana,
    // jadi refresh browser dengan parameter apa pun tidak akan error.
    $tabs = [
        ['key' => 'semua', 'label' => 'Semua'],
        ['key' => 'preneur', 'label' => 'Cahaya Preneur'],
        ['key' => 'koperasi', 'label' => 'Koperasi Cahaya'],
    ];

    // $bolehLihatPreneur diisi controller (true HANYA untuk Admin). Panel
    // Cahaya Preneur ikut dikunci di sini supaya visibility-nya tidak
    // bergantung pada nilai ?tab= saja.
    $tampilPreneur = $bolehLihatPreneur && in_array($tab, ['semua', 'preneur'], true);
    $tampilKoperasi = in_array($tab, ['semua', 'koperasi'], true);
@endphp

<div class="space-y-6">

    {{-- HEADER --}}
    <div>
        <h2 class="text-[20px] font-bold text-text-main">Pengaturan</h2>
        <p class="text-sm text-text-secondary mt-0.5">Kelola identitas dan konfigurasi CahayaOS.</p>
    </div>

    {{-- SWITCHER TAB — pola sama dengan switcher Dashboard supaya terasa satu
         aplikasi. Di layar sempit bisa digeser mendatar, tidak terpotong.

         Hanya ditampilkan untuk Admin: non-Admin cuma punya satu panel, jadi
         switcher-nya tidak ada gunanya dan tidak boleh menawarkan tab
         Cahaya Preneur yang memang tertutup untuk mereka. --}}
    @if ($bolehLihatPreneur)
        <div class="-mx-1 px-1 overflow-x-auto">
            <div class="inline-flex items-center gap-1 bg-white border border-border-soft rounded-xl p-1 shadow-sm">
                @foreach ($tabs as $t)
                    <a href="{{ route('pengaturan.index', ['tab' => $t['key']]) }}"
                       class="whitespace-nowrap px-4 py-2 rounded-lg text-[13px] font-semibold transition
                              {{ $tab === $t['key'] ? 'bg-cahaya-primary text-white' : 'text-text-secondary hover:bg-surface-bg' }}">
                        {{ $t['label'] }}
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    {{-- ==========================================================
         PANEL CAHAYA PRENEUR — data SystemSetting, submit ke pengaturan.update
         ========================================================== --}}
    @if ($tampilPreneur)
        <div class="space-y-5">
            @if ($tab === 'semua')
                <div class="flex items-center gap-2.5">
                    <div class="h-8 w-8 rounded-lg bg-primary-light flex items-center justify-center flex-shrink-0">
                        <svg class="w-4 h-4 text-cahaya-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-1.13a4 4 0 10-4-4 4 4 0 004 4zm6 0a4 4 0 10-4-4" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-[15px] font-semibold text-text-main leading-tight">Cahaya Preneur</h3>
                        <p class="text-[11px] text-text-secondary">Identitas sistem, logo program, dan data program</p>
                    </div>
                </div>
            @endif

            @include('pengaturan.panel-preneur')
        </div>
    @endif

    {{-- ==========================================================
         PANEL KOPERASI CAHAYA — data KoperasiSetting, submit ke
         koperasi.pengaturan.update.
         Panel-nya dipindahkan APA ADANYA dari view lama yang memakai
         variabel $setting, jadi di sini $koperasiSetting dipetakan ke
         nama itu lewat @include — supaya file panelnya tidak perlu diubah
         sama sekali dan tidak ada risiko salah ganti nama field.
         ========================================================== --}}
    @if ($tampilKoperasi)
        <div class="space-y-5 {{ $tab === 'semua' ? 'pt-2 border-t border-border-soft' : '' }}">
            @if ($tab === 'semua')
                <div class="flex items-center gap-2.5 pt-4">
                    <div class="h-8 w-8 rounded-lg bg-primary-light flex items-center justify-center flex-shrink-0">
                        <svg class="w-4 h-4 text-cahaya-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 21h18M5 21V7l8-4v18M19 21V11l-6-4M9 9h.01M9 12h.01M9 15h.01" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-[15px] font-semibold text-text-main leading-tight">Koperasi Cahaya</h3>
                        <p class="text-[11px] text-text-secondary">Identitas, logo, penandatangan, dan invoice</p>
                    </div>
                </div>
            @endif

            @include('pengaturan.panel-koperasi', ['setting' => $koperasiSetting])
        </div>
    @endif
</div>
@endsection
