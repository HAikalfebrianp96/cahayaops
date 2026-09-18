@extends('layouts.app')

@section('title', 'Detail Pendamping')

@section('content')
@php
    $inisial = collect(explode(' ', trim($pendamping->nama)))->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode('');
@endphp

<div class="space-y-5 max-w-3xl">

    {{-- HEADER --}}
    <div class="bg-white border border-border-soft rounded-2xl shadow-sm p-6">
        <div class="flex items-start justify-between flex-wrap gap-4">
            <div class="flex items-center gap-4">
                <div class="h-14 w-14 rounded-full bg-cahaya-primary text-white flex items-center justify-center text-lg font-bold flex-shrink-0">
                    {{ $inisial ?: '?' }}
                </div>
                <div>
                    <h2 class="text-xl font-bold text-text-main">{{ $pendamping->nama }}</h2>
                    <p class="text-sm text-text-secondary">{{ $pendamping->email ?: 'Belum ada email' }}</p>
                    <div class="flex items-center gap-2 mt-2 flex-wrap">
                        <span class="text-[11px] font-medium px-2 py-0.5 rounded-full {{ $pendamping->status === 'Aktif' ? 'bg-primary-light text-status-success' : 'bg-gray-100 text-text-secondary' }}">
                            {{ $pendamping->status }}
                        </span>
                        <span class="text-[11px] text-text-secondary">&bull; {{ $pendamping->nomor_hp }}</span>
                        @if ($pendamping->user)
                            <span class="text-[11px] text-status-success">&bull; 🟢 Akun Login Aktif</span>
                        @else
                            <span class="text-[11px] text-text-secondary">&bull; ⚪ Belum Ada Akun Login</span>
                        @endif
                    </div>
                </div>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('pendampings.edit', $pendamping) }}"
                   class="bg-cahaya-primary text-white text-[13px] font-medium px-4 py-2 rounded-lg hover:opacity-90 transition">
                    Edit
                </a>
                <a href="{{ route('pendampings.index') }}" class="text-[13px] text-text-secondary px-3 py-2 hover:text-text-main">← Daftar</a>
            </div>
        </div>
    </div>

    {{-- SUMMARY --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-white border border-border-soft rounded-xl p-4">
            <p class="text-[11px] text-text-secondary mb-1">Peserta Binaan</p>
            <p class="text-xl font-bold text-text-main">{{ $pendamping->participants->count() }}</p>
        </div>
        <div class="bg-white border border-border-soft rounded-xl p-4">
            <p class="text-[11px] text-text-secondary mb-1">Aktivitas Pendampingan</p>
            <p class="text-xl font-bold text-text-main">{{ $jumlahAktivitasPendampingan }}</p>
        </div>
        <div class="bg-white border border-border-soft rounded-xl p-4">
            <p class="text-[11px] text-text-secondary mb-1">Omzet Binaan (Kumulatif)</p>
            <p class="text-xl font-bold text-text-main">{{ rupiah($omzetBinaan) }}</p>
        </div>
    </div>

    {{-- DAFTAR PESERTA BINAAN --}}
    <div class="bg-white border border-border-soft rounded-2xl shadow-sm p-6">
        <h3 class="text-[15px] font-semibold text-text-main mb-4">Daftar Peserta Binaan</h3>

        @if ($pendamping->participants->isEmpty())
            <p class="text-sm text-text-secondary italic">Belum ada peserta yang didampingi.</p>
        @else
            <div class="space-y-2">
                @foreach ($pendamping->participants as $p)
                    <a href="{{ route('participants.show', $p) }}" class="flex items-center justify-between border border-border-soft rounded-xl px-4 py-3 text-sm hover:bg-surface-bg transition">
                        <div>
                            <p class="font-medium text-text-main">{{ $p->nama_peserta }}</p>
                            <p class="text-xs text-text-secondary">{{ $p->nama_usaha }}</p>
                        </div>
                        <span class="text-[11px] font-medium px-2 py-0.5 rounded-full
                            {{ $p->status === 'Aktif' ? 'bg-primary-light text-status-success' : 'bg-gray-100 text-text-secondary' }}">
                            {{ $p->status }}
                        </span>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</div>
@endsection
