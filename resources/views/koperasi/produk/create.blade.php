@extends('layouts.app')

@section('title', 'Tambah Produk/Jasa')

@section('content')
<div class="bg-white border border-border-soft rounded-2xl shadow-sm p-6 max-w-2xl">
    <h2 class="text-lg font-semibold text-text-main mb-1">Tambah Produk/Jasa</h2>
    <p class="text-xs text-text-secondary mb-6">Unit: {{ $unit->nama }}</p>

    <form method="POST" action="{{ route('koperasi.produk.store', $unit) }}" class="space-y-4">
        @csrf
        @include('koperasi.produk._form')

        <div class="pt-2 flex gap-2">
            <button type="submit" class="bg-cahaya-primary text-white text-sm font-medium px-5 py-2.5 rounded-lg hover:opacity-90 transition">
                Simpan
            </button>
            <a href="{{ route('koperasi.unit-usaha.show', $unit) }}" class="text-sm text-text-secondary px-5 py-2.5 hover:text-text-main">
                Batal
            </a>
        </div>
    </form>
</div>
@endsection
