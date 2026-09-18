@extends('layouts.app')

@section('title', 'Edit Peserta')

@section('content')
<div class="bg-white border border-gray-100 rounded-xl shadow-sm p-6 max-w-3xl">
    <h2 class="text-lg font-semibold text-gray-800 mb-1">Edit Data Peserta — {{ $participant->nama_peserta }}</h2>
    <p class="text-xs text-gray-500 mb-6">
        Perbaiki data yang keliru langsung di sini. Tidak perlu menghapus lalu membuat ulang —
        seluruh riwayat omzet, pendampingan, pelatihan, dan legalitas tetap menempel pada peserta ini.
    </p>

    <form method="POST" action="{{ route('participants.update', $participant) }}">
        @method('PUT')
        @include('participants._form')
    </form>
</div>
@endsection
