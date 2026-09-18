@extends('layouts.app')

@section('title', 'Tambah Pelanggan')

@section('content')
<div class="bg-white border border-border-soft rounded-2xl shadow-sm p-6 max-w-2xl">
    <h2 class="text-lg font-semibold text-text-main mb-1">Tambah Pelanggan</h2>
    <p class="text-xs text-text-secondary mb-6">
        Pelanggan yang ditambahkan di sini bisa langsung dipilih saat mencatat penjualan.
    </p>

    <form method="POST" action="{{ route('koperasi.pelanggan.store') }}">
        @include('koperasi.pelanggan._form')
    </form>
</div>
@endsection
