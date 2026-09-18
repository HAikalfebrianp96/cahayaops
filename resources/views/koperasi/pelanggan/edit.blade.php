@extends('layouts.app')

@section('title', 'Edit Pelanggan')

@section('content')
<div class="bg-white border border-border-soft rounded-2xl shadow-sm p-6 max-w-2xl">
    <h2 class="text-lg font-semibold text-text-main mb-1">Edit Pelanggan — {{ $customer->nama }}</h2>
    <p class="text-xs text-text-secondary mb-6">
        Memperbaiki data di sini otomatis benar juga di seluruh transaksi dan invoice yang merujuk
        pelanggan ini — tidak perlu membuat pelanggan baru.
    </p>

    <form method="POST" action="{{ route('koperasi.pelanggan.update', $customer) }}">
        @method('PUT')
        @include('koperasi.pelanggan._form')
    </form>
</div>
@endsection
