@extends('layouts.app')

@section('title', 'HPP Produk')

@section('content')
@php
    $isPimpinan = auth()->user()->isPimpinan();
@endphp

<div class="space-y-4 max-w-4xl">
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-lg font-semibold text-text-main">HPP Produk — {{ $participant->nama_peserta }}</h2>
            <p class="text-sm text-text-secondary">{{ $participant->nama_usaha }}</p>
        </div>
        @unless ($isPimpinan)
            <a href="{{ route('hpp.create', $participant) }}" class="bg-cahaya-primary text-white text-sm font-medium px-4 py-2 rounded-md hover:bg-cahaya-primary/90 transition">
                + Buat Perhitungan Baru
            </a>
        @endunless
    </div>

    <div class="bg-white border border-border-soft rounded-2xl shadow-sm overflow-x-auto">
        @if ($calculations->isEmpty())
            <p class="text-sm text-text-secondary italic p-6">Belum ada perhitungan HPP untuk peserta ini.</p>
        @else
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-text-secondary border-b bg-surface-bg">
                        <th class="px-4 py-3">Produk</th>
                        <th class="px-4 py-3">Tanggal</th>
                        <th class="px-4 py-3">Jumlah Produksi</th>
                        <th class="px-4 py-3">HPP/Unit</th>
                        <th class="px-4 py-3">Harga Jual</th>
                        <th class="px-4 py-3">Laba/Unit</th>
                        <th class="px-4 py-3">Margin</th>
                        <th class="px-4 py-3">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($calculations as $c)
                        <tr class="border-b last:border-0">
                            <td class="px-4 py-3 font-medium">{{ $c->nama_produk }}</td>
                            <td class="px-4 py-3 text-text-secondary">{{ $c->created_at->format('d-m-Y') }}</td>
                            <td class="px-4 py-3">{{ number_format($c->jumlah_produksi, 0, ',', '.') }} {{ $c->satuan_produksi }}</td>
                            <td class="px-4 py-3">{{ rupiah($c->hpp_per_unit) }}</td>
                            <td class="px-4 py-3">{{ rupiah($c->harga_jual) }}</td>
                            <td class="px-4 py-3 {{ $c->laba_per_unit >= 0 ? 'text-status-success' : 'text-status-danger' }} font-medium">{{ rupiah($c->laba_per_unit) }}</td>
                            <td class="px-4 py-3">{{ $c->margin_persen !== null ? number_format($c->margin_persen, 1, ',', '.') . '%' : '-' }}</td>
                            <td class="px-4 py-3">
                                <a href="{{ route('hpp.show', $c) }}" class="text-cahaya-primary hover:underline">Detail</a>
                                {{-- HPP sengaja TIDAK punya Edit: mengubah angka
                                     perhitungan lama berarti menulis ulang fakta yang
                                     sudah dipakai mengambil keputusan harga. Koreksinya
                                     dengan membuat perhitungan baru. Yang tersedia di
                                     sini adalah membuang perhitungan yang salah input —
                                     tetap tanpa menghilangkan barisnya. --}}
                                <form method="POST" action="{{ route('hpp.destroy', $c) }}" class="inline"
                                      onsubmit="return confirm('Arsipkan perhitungan HPP ini?\n\nData akan dipindahkan ke Arsip Data dan masih dapat dipulihkan Admin.');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-status-danger hover:underline text-xs">Arsipkan</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <a href="{{ route('participants.show', $participant) }}" class="text-sm text-status-info hover:underline">← Kembali ke Detail Peserta</a>
</div>
@endsection
