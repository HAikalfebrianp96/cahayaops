@extends('layouts.app')

@section('title', 'Buat Penjualan')

@section('content')
<div>
    <div class="mb-4">
        <h2 class="text-[20px] font-bold text-text-main">Buat Penjualan — {{ $unit->nama }}</h2>
        <p class="text-xs text-text-secondary mt-0.5">{{ $unit->kode }} &middot; {{ $unit->tipe }}</p>
    </div>

    @error('customer_nama')
        <div class="mb-4 rounded-xl bg-red-50 border border-status-danger/20 px-4 py-3 text-sm text-status-danger">{{ $message }}</div>
    @enderror

    @include('koperasi.penjualan._form')
</div>
@endsection
