@extends('layouts.app')

@section('title', 'Unit Usaha')

@section('content')
<div class="space-y-5">
    <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
            <h2 class="text-[20px] font-bold text-text-main">
                Unit Usaha
            </h2>
            <p class="text-sm text-text-secondary mt-0.5">
                Daftar unit usaha Koperasi Cahaya
            </p>
        </div>
        <div class="flex items-center gap-2">
                                <a href="{{ route('koperasi.unit-usaha.create') }}"
                   class="inline-flex items-center justify-center bg-cahaya-primary text-white text-sm font-medium px-4 py-2.5 rounded-lg hover:opacity-90 transition">
                    + Tambah Unit Usaha
                </a>

        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        @forelse ($units as $unit)
            <div class="bg-white border border-border-soft rounded-2xl shadow-sm p-5 hover:shadow-md transition">
                <div class="flex items-center justify-between mb-2">
                    <h3 class="font-semibold text-text-main">{{ $unit->nama }}</h3>
                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium
                        {{ $unit->status === 'Aktif' ? 'bg-primary-light text-status-success' : 'bg-gray-100 text-text-secondary' }}">
                        {{ $unit->status }}
                    </span>
                </div>
                <p class="text-xs text-text-secondary mb-1">Kode: {{ $unit->kode }} &middot; Tipe: {{ $unit->tipe }}</p>
                <p class="text-xs text-text-secondary mb-4">{{ $unit->products_services_count }} produk/jasa</p>

                <div class="flex items-center gap-3 pt-3 border-t border-border-soft">
                        <a href="{{ route('koperasi.unit-usaha.show', $unit) }}" class="text-xs font-medium text-status-info hover:underline">Lihat</a>
                        <a href="{{ route('koperasi.unit-usaha.edit', $unit) }}" class="text-xs font-medium text-cahaya-primary hover:underline">Edit</a>
                        {{-- Soft Delete. Untuk unit yang sekadar berhenti beroperasi,
                             cara yang benar tetap mengubah statusnya jadi "Tidak Aktif"
                             lewat Edit — datanya tetap tampil di laporan. --}}
                        <form method="POST" action="{{ route('koperasi.unit-usaha.destroy', $unit) }}"
                              onsubmit="return confirm('Hapus Permanen?

Unit usaha ini akan dihapus dan TIDAK dapat dipulihkan.

Unit yang masih punya produk, penjualan, atau pengeluaran tidak bisa dihapus — sistem akan menolak dan menyebutkan apa yang menahannya.');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-xs font-medium text-status-danger hover:underline">Hapus Permanen</button>
                        </form>

                </div>
            </div>
        @empty
            <div class="col-span-full text-center py-14">
                <p class="text-sm font-medium text-text-main mb-1">
                    Belum Ada Unit Usaha
                </p>
                <p class="text-xs text-text-secondary mb-4">
                    Belum ada unit usaha yang terdaftar.
                </p>
                                    <a href="{{ route('koperasi.unit-usaha.create') }}" class="inline-block text-sm bg-cahaya-primary text-white font-medium px-4 py-2 rounded-lg hover:opacity-90 transition">+ Tambah Unit Usaha</a>

            </div>
        @endforelse
    </div>
</div>
@endsection
