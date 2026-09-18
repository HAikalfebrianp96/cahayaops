@extends('layouts.app')

@section('title', 'Edit Penjualan')

@section('content')
<div>
    <div class="mb-4">
        <h2 class="text-[20px] font-bold text-text-main">Edit Penjualan — {{ $transaksi->nomor_transaksi }}</h2>
        <p class="text-xs text-text-secondary mt-0.5">
            {{ $unit?->nama ?? 'Unit usaha belum ditentukan' }}@if ($unit) ({{ $unit->kode }})@endif
        </p>
    </div>

    @error('customer_nama')
        <div class="mb-4 rounded-xl bg-red-50 border border-status-danger/20 px-4 py-3 text-sm text-status-danger">{{ $message }}</div>
    @enderror

    @include('koperasi.penjualan._form')
</div>
@endsection
