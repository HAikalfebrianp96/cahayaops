@extends('layouts.app')

@section('title', 'Edit Unit Usaha')

@section('content')
<div class="bg-white border border-border-soft rounded-2xl shadow-sm p-6 max-w-2xl">
    <h2 class="text-lg font-semibold text-text-main mb-6">Edit Unit Usaha — {{ $unit->nama }}</h2>

    <form method="POST" action="{{ route('koperasi.unit-usaha.update', $unit) }}" class="space-y-4">
        @csrf
        @method('PUT')
        @include('koperasi.unit-usaha._form')

        <div class="pt-2 flex gap-2">
            <button type="submit" class="bg-cahaya-primary text-white text-sm font-medium px-5 py-2.5 rounded-lg hover:opacity-90 transition">
                Simpan
            </button>
            <a href="{{ route('koperasi.unit-usaha.index') }}" class="text-sm text-text-secondary px-5 py-2.5 hover:text-text-main">
                Batal
            </a>
        </div>
    </form>
</div>
@endsection
