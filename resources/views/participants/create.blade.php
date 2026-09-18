@extends('layouts.app')

@section('title', auth()->user()->isAdmin() ? 'Tambah Peserta' : 'Tambah Binaan')

@section('content')
<div class="bg-white border border-gray-100 rounded-xl shadow-sm p-6 max-w-3xl">
    <h2 class="text-lg font-semibold text-gray-800 mb-1">
        {{ auth()->user()->isAdmin() ? 'Tambah Peserta' : 'Tambah Binaan Baru' }}
    </h2>
    <p class="text-xs text-gray-500 mb-6">
        @if (auth()->user()->isAdmin())
            Peserta baru akan langsung tercatat di database peserta Cahaya Preneur.
        @else
            Peserta yang Anda tambahkan otomatis menjadi binaan Anda.
        @endif
    </p>

    <form method="POST" action="{{ route('participants.store') }}">
        @include('participants._form')
    </form>
</div>
@endsection
