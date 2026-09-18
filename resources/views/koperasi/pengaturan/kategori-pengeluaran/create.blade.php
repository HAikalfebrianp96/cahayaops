@extends('layouts.app')

@section('title', 'Tambah Kategori Pengeluaran')

@section('content')
<div class="max-w-md">
    <h2 class="text-lg font-semibold text-gray-800 mb-4">Tambah Kategori Pengeluaran</h2>
    <div class="bg-white border border-gray-100 rounded-xl shadow-sm p-6">
        <form method="POST" action="{{ route('koperasi.kategori-pengeluaran.store') }}" class="space-y-4">
            @csrf
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nama Kategori</label>
                <input type="text" name="nama" value="{{ old('nama') }}" autofocus
                       class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                @error('nama') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
            <div class="flex gap-2">
                <button type="submit" class="bg-koperasi-primary text-white text-sm font-medium px-5 py-2.5 rounded-md hover:opacity-90 transition">
                    Simpan
                </button>
                <a href="{{ route('koperasi.kategori-pengeluaran.index') }}" class="text-sm text-gray-500 px-5 py-2.5 hover:text-gray-700">
                    Batal
                </a>
            </div>
        </form>
    </div>
</div>
@endsection
