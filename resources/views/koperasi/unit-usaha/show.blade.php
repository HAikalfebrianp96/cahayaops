@extends('layouts.app')

@section('title', 'Detail Unit Usaha')

@section('content')
<div class="space-y-4 max-w-6xl">

    {{-- INFORMASI UNIT --}}
    <div class="bg-white border border-border-soft rounded-2xl shadow-sm p-6">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h2 class="text-xl font-semibold text-text-main">{{ $unit->nama }}</h2>
                <p class="text-sm text-text-secondary">{{ $unit->kode }} &middot; {{ $unit->tipe }}</p>
            </div>
            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium
                {{ $unit->status === 'Aktif' ? 'bg-primary-light text-status-success' : 'bg-gray-100 text-text-secondary' }}">
                {{ $unit->status }}
            </span>
        </div>

        <dl class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 text-sm">
            @if ($unit->jenis_usaha)
                <div>
                    <dt class="text-text-secondary">Jenis Usaha</dt>
                    <dd class="font-medium text-text-main">{{ $unit->jenis_usaha }}</dd>
                </div>
            @endif
            @if ($unit->nomor_kontak)
                <div>
                    <dt class="text-text-secondary">Nomor Kontak</dt>
                    <dd class="font-medium text-text-main">{{ $unit->nomor_kontak }}</dd>
                </div>
            @endif
            @if ($unit->tanggal_mulai)
                <div>
                    <dt class="text-text-secondary">Tanggal Mulai</dt>
                    <dd class="font-medium text-text-main">{{ $unit->tanggal_mulai->format('d-m-Y') }}</dd>
                </div>
            @endif
            @if ($unit->alamat)
                <div class="sm:col-span-2 lg:col-span-3">
                    <dt class="text-text-secondary">Alamat</dt>
                    <dd class="font-medium text-text-main">{{ $unit->alamat }}</dd>
                </div>
            @endif
            @if ($unit->deskripsi)
                <div class="sm:col-span-2 lg:col-span-3">
                    <dt class="text-text-secondary">Deskripsi</dt>
                    <dd class="font-medium text-text-main">{{ $unit->deskripsi }}</dd>
                </div>
            @endif
        </dl>

        <div class="mt-6 flex gap-2">
            <a href="{{ route('koperasi.unit-usaha.edit', $unit) }}" class="bg-cahaya-primary text-white text-sm font-medium px-5 py-2.5 rounded-lg hover:opacity-90 transition">
                Edit
            </a>
            <a href="{{ route('koperasi.unit-usaha.index') }}" class="text-sm text-text-secondary px-5 py-2.5 hover:text-text-main">
                Kembali
            </a>
        </div>
    </div>

    {{-- MINI DASHBOARD UNIT --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-white rounded-2xl shadow-sm border border-border-soft p-5">
            <p class="text-sm text-text-secondary">Total Produk/Jasa</p>
            <p class="text-2xl font-bold text-cahaya-primary mt-1">{{ $unit->productsServices->count() }}</p>
        </div>
        <div class="bg-white rounded-2xl shadow-sm border border-border-soft p-5">
            <p class="text-sm text-text-secondary">Produk Aktif</p>
            <p class="text-2xl font-bold text-status-info mt-1">{{ $unit->productsServices->where('jenis', 'Produk')->where('status', 'Aktif')->count() }}</p>
        </div>
        <div class="bg-white rounded-2xl shadow-sm border border-border-soft p-5">
            <p class="text-sm text-text-secondary">Jasa Aktif</p>
            <p class="text-2xl font-bold text-cahaya-accent mt-1">{{ $unit->productsServices->where('jenis', 'Jasa')->where('status', 'Aktif')->count() }}</p>
        </div>
    </div>

    {{-- PRODUK & JASA (KHUSUS UNIT INI) --}}
    <div class="bg-white border border-border-soft rounded-2xl shadow-sm p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-semibold text-text-main">Produk &amp; Jasa</h3>
            <a href="{{ route('koperasi.produk.create', $unit) }}" class="inline-flex items-center rounded-lg px-3 py-1.5 text-xs font-medium bg-primary-light text-cahaya-primary hover:opacity-80 transition">
                + Tambah Produk/Jasa
            </a>
        </div>

        @if ($unit->productsServices->isEmpty())
            <div class="text-center py-10">
                <p class="text-sm font-medium text-text-main mb-1">Belum Ada Produk/Jasa</p>
                <p class="text-xs text-text-secondary">Belum ada produk atau jasa untuk unit ini.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-text-secondary border-b border-border-soft bg-surface-bg">
                            <th class="px-4 py-3 font-medium">Kode</th>
                            <th class="px-4 py-3 font-medium">Nama</th>
                            <th class="px-4 py-3 font-medium">Jenis</th>
                            <th class="px-4 py-3 font-medium">Kategori</th>
                            <th class="px-4 py-3 font-medium">HPP</th>
                            <th class="px-4 py-3 font-medium">Harga Jual</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                            <th class="px-4 py-3 font-medium">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($unit->productsServices as $p)
                            <tr class="border-b border-border-soft last:border-0 hover:bg-surface-bg/60 transition">
                                <td class="px-4 py-3 text-text-secondary">{{ $p->kode }}</td>
                                <td class="px-4 py-3 font-medium text-text-main">{{ $p->nama }}</td>
                                <td class="px-4 py-3 text-text-secondary">{{ $p->jenis }}</td>
                                <td class="px-4 py-3 text-text-secondary">{{ $p->kategori ?: '—' }}</td>
                                <td class="px-4 py-3 font-medium text-text-main">{{ rupiah($p->harga_modal) }}</td>
                                <td class="px-4 py-3 font-semibold text-text-main">{{ rupiah($p->harga_jual) }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium {{ $p->status === 'Aktif' ? 'bg-primary-light text-status-success' : 'bg-gray-100 text-text-secondary' }}">
                                        {{ $p->status }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <a href="{{ route('koperasi.produk.edit', $p) }}" class="inline-flex items-center rounded-lg px-3 py-1.5 text-xs font-medium bg-primary-light text-cahaya-primary hover:opacity-80 transition">Edit</a>
                                    {{-- Soft Delete. Untuk produk yang sekadar berhenti
                                         dijual, cara yang benar tetap mengubah statusnya
                                         lewat Edit — datanya tetap muncul di laporan lama. --}}
                                    <form method="POST" action="{{ route('koperasi.produk.destroy', $p) }}" class="inline"
                                          onsubmit="return confirm('Arsipkan produk/jasa ini?\n\nData akan dipindahkan ke Arsip Data dan masih dapat dipulihkan. Riwayat penjualannya tidak terpengaruh.');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="inline-flex items-center rounded-lg px-3 py-1.5 text-xs font-medium bg-surface-bg text-status-danger hover:bg-status-danger/10 transition">Arsipkan</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection
