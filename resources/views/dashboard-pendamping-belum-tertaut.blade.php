@extends('layouts.app')

@section('title', 'Dashboard Pendamping')

@section('content')
{{-- ============================================================
     PENDAMPING YANG AKUNNYA BELUM TERTAUT KE DATA PENDAMPING.

     Sebelumnya akun seperti ini jatuh ke panel Cahaya Preneur milik Admin
     dan melihat rekap SELURUH peserta, pendampingan semua pendamping, dan
     ringkasan SROI. Akun tanpa tautan memang tidak punya binaan, jadi yang
     benar adalah memberi tahu langkah berikutnya — bukan data orang lain.
     ============================================================ --}}
<div class="space-y-6">
    <div>
        <h2 class="text-[22px] font-bold text-text-main">
            Selamat datang, {{ explode(' ', auth()->user()->name)[0] ?? 'Pendamping' }} 👋
        </h2>
        <p class="text-[11px] text-text-secondary mt-1">
            CahayaOS — Sistem Terpadu Cahaya Preneur &amp; Koperasi Cahaya
        </p>
        <p class="text-sm text-text-secondary mt-1">
            Cahaya Preneur — Program Ekonomi YBM PLN UP3 Bengkulu
        </p>
    </div>

    <div class="bg-white border border-border-soft rounded-2xl shadow-sm p-6 max-w-2xl">
        <h3 class="text-sm font-semibold text-text-main mb-2">Akun belum terhubung ke data Pendamping</h3>
        <p class="text-sm text-text-secondary">
            Akun Anda sudah aktif, tetapi belum ditautkan ke profil Pendamping, sehingga belum ada binaan
            yang bisa ditampilkan. Silakan hubungi Admin untuk menautkan akun ini ke profil Pendamping Anda.
        </p>
    </div>
</div>
@endsection
