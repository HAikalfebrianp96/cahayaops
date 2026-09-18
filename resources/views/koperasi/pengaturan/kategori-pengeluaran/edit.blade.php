@extends('layouts.app')

@section('title', 'Edit Kategori Pengeluaran')

@section('content')
<div class="max-w-md">
    <h2 class="text-lg font-semibold text-gray-800 mb-4">Edit Kategori — {{ $kategori->nama }}</h2>
    <div class="bg-white border border-gray-100 rounded-xl shadow-sm p-6">
        <p class="text-xs text-amber-600 bg-amber-50 border border-amber-100 rounded-md px-3 py-2 mb-4">
            Mengubah nama TIDAK mengubah transaksi lama yang sudah tercatat pakai kategori ini — histori laporan periode lalu tetap konsisten.
        </p>
        <form method="POST" action="{{ route('koperasi.kategori-pengeluaran.update', $kategori) }}" class="space-y-4">
            @csrf
            @method('PUT')
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nama Kategori</label>
                <input type="text" name="nama" value="{{ old('nama', $kategori->nama) }}" autofocus
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
