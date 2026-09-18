@extends('layouts.app')

@section('title', 'Buat Penjualan')

@section('content')
<div class="space-y-4">
    <div>
        <h2 class="text-lg font-semibold text-gray-800">Buat Penjualan</h2>
        <p class="text-sm text-gray-500">Pilih unit usaha untuk transaksi ini</p>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        @forelse ($units as $unit)
            <a href="{{ route('koperasi.penjualan.create', ['unit' => $unit->id]) }}"
               class="bg-white border border-gray-100 rounded-xl shadow-sm p-5 hover:shadow-md transition block">
                <h3 class="font-semibold text-gray-800">{{ $unit->nama }}</h3>
                <p class="text-xs text-gray-500 mt-1">{{ $unit->kode }} · {{ $unit->tipe }}</p>
            </a>
        @empty
            <p class="text-sm text-gray-400 italic col-span-full">Belum ada unit usaha aktif. Tambahkan unit usaha dulu.</p>
        @endforelse
    </div>

    <a href="{{ route('koperasi.penjualan.index') }}" class="inline-block text-sm text-gray-500 hover:text-gray-700">
        ← Kembali ke daftar penjualan
    </a>
</div>
@endsection
