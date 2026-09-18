@extends('layouts.app')

@section('title', 'SHU Koperasi')

@section('content')
<div class="space-y-4">

    <div class="bg-white border border-border-soft rounded-2xl shadow-sm p-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-lg font-semibold text-text-main">Sisa Hasil Usaha</h2>
                <p class="text-xs text-text-secondary mt-0.5 max-w-2xl">
                    Penetapan dan pembagian SHU per tahun buku. Persentase komponennya ditetapkan Rapat
                    Anggota dan disimpan per periode — penetapan tahun lalu tetap memperlihatkan aturan
                    yang berlaku waktu itu.
                </p>
            </div>
            <a href="{{ route('koperasi.shu.create') }}"
               class="bg-cahaya-primary text-white text-sm font-medium px-4 py-2 rounded-lg hover:opacity-90 transition">
                + Penetapan Baru
            </a>
        </div>
    </div>

    @include('impact.partials.notifikasi')

    @forelse ($periods as $p)
        <a href="{{ route('koperasi.shu.show', $p) }}"
           class="block bg-white border border-border-soft rounded-2xl shadow-sm p-5 hover:border-cahaya-secondary transition">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <div class="flex items-center gap-2">
                        <h3 class="text-[15px] font-semibold text-text-main">Tahun Buku {{ $p->tahun }}</h3>
                        <span class="text-[11px] font-semibold px-2 py-0.5 rounded-full
                            {{ $p->terkunci() ? 'bg-status-success/10 text-status-success' : 'bg-surface-bg text-text-secondary' }}">
                            {{ $p->status }}
                        </span>
                    </div>
                    <p class="text-xs text-text-secondary mt-1">
                        Ditetapkan {{ $p->tanggal_penetapan->format('d M Y') }}
                        &bull; {{ $p->allocations_count }} anggota menerima
                        &bull; {{ count($p->komponen ?? []) }} komponen
                    </p>
                </div>
                <div class="text-right">
                    <p class="text-lg font-bold text-cahaya-primary leading-none">{{ rupiah($p->total_shu) }}</p>
                    <p class="text-[11px] text-text-secondary mt-1">total SHU</p>
                </div>
            </div>
        </a>
    @empty
        <div class="bg-white border border-dashed border-border-soft rounded-2xl p-8 text-center">
            <p class="text-sm font-semibold text-text-main mb-1">Belum ada penetapan SHU</p>
            <p class="text-xs text-text-secondary max-w-lg mx-auto">
                Total SHU bisa diusulkan otomatis dari Laba Rugi koperasi tahun berjalan, tetapi angka
                finalnya tetap keputusan Rapat Anggota — sistem hanya menghitung pembagiannya.
            </p>
            <a href="{{ route('koperasi.shu.create') }}" class="inline-block mt-4 text-sm text-cahaya-secondary hover:underline">
                Buat penetapan pertama →
            </a>
        </div>
    @endforelse

    {{ $periods->links() }}
</div>
@endsection
