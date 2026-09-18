@extends('layouts.app')

@section('title', 'Pelanggan')

@section('content')
<div class="space-y-5">
    <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
            <h2 class="text-[20px] font-bold text-text-main">
                {{ $arsip ? 'Arsip Pelanggan' : 'Pelanggan' }}
            </h2>
            <p class="text-sm text-text-secondary mt-0.5">
                {{ $arsip
                    ? 'Pelanggan yang dihapus tidak hilang — riwayat penjualannya tetap utuh dan bisa dipulihkan.'
                    : 'Pembeli produk & jasa unit usaha Koperasi Cahaya' }}
            </p>
        </div>
        <div class="flex items-center gap-2">
            @if ($arsip)
                <a href="{{ route('koperasi.pelanggan.index') }}"
                   class="text-sm text-text-secondary px-4 py-2.5 hover:text-text-main">← Daftar Pelanggan</a>
            @else
                @if ($jumlahTerhapus > 0)
                    <a href="{{ route('koperasi.pelanggan.index', ['arsip' => 1]) }}"
                       class="text-sm text-text-secondary px-4 py-2.5 hover:text-text-main">Arsip ({{ $jumlahTerhapus }})</a>
                @endif
                <a href="{{ route('koperasi.pelanggan.create') }}"
                   class="inline-flex items-center justify-center bg-cahaya-primary text-white text-sm font-medium px-4 py-2.5 rounded-lg hover:opacity-90 transition">
                    + Tambah Pelanggan
                </a>
            @endif
        </div>
    </div>

    <form method="GET" action="{{ route('koperasi.pelanggan.index') }}"
          class="bg-white border border-border-soft rounded-2xl shadow-sm p-4 flex flex-col sm:flex-row gap-3">
        @if ($arsip) <input type="hidden" name="arsip" value="1"> @endif
        <input type="text" name="q" value="{{ $filters['q'] ?? '' }}"
               placeholder="Cari nama atau nomor HP pelanggan..."
               class="flex-1 rounded-xl border border-border-soft px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-status-info/30">
        <div class="flex gap-2">
            <button type="submit"
                    class="bg-cahaya-secondary text-white text-sm font-medium px-4 py-2.5 rounded-lg hover:opacity-90 transition">
                Cari
            </button>
            <a href="{{ route('koperasi.pelanggan.index', $arsip ? ['arsip' => 1] : []) }}"
               class="text-sm text-text-secondary px-4 py-2.5 hover:text-text-main">Reset</a>
        </div>
    </form>

    <div class="bg-white border border-border-soft rounded-2xl shadow-sm overflow-hidden">
        @if ($customers->isEmpty())
            <div class="text-center py-14">
                <p class="text-sm font-medium text-text-main mb-1">
                    {{ $arsip ? 'Arsip Kosong' : 'Belum Ada Pelanggan' }}
                </p>
                <p class="text-xs text-text-secondary mb-4">
                    {{ $arsip
                        ? 'Tidak ada pelanggan yang dihapus.'
                        : 'Pelanggan juga bisa ditambahkan langsung dari halaman Penjualan.' }}
                </p>
                @unless ($arsip)
                    <a href="{{ route('koperasi.pelanggan.create') }}"
                       class="inline-block text-sm bg-cahaya-primary text-white font-medium px-4 py-2 rounded-lg hover:opacity-90 transition">
                        + Tambah Pelanggan
                    </a>
                @endunless
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-text-secondary border-b border-border-soft bg-surface-bg">
                            <th class="px-4 py-3 font-medium">Nama</th>
                            <th class="px-4 py-3 font-medium">Nomor HP</th>
                            <th class="px-4 py-3 font-medium">Email</th>
                            <th class="px-4 py-3 font-medium">Transaksi</th>
                            @if ($arsip)
                                <th class="px-4 py-3 font-medium">Dihapus</th>
                                <th class="px-4 py-3 font-medium">Oleh</th>
                            @endif
                            <th class="px-4 py-3 font-medium">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($customers as $c)
                            <tr class="border-b border-border-soft last:border-0 hover:bg-surface-bg/60 transition">
                                <td class="px-4 py-3 font-medium text-text-main">{{ $c->nama }}</td>
                                <td class="px-4 py-3 text-text-secondary">{{ $c->nomor_hp ?: '—' }}</td>
                                <td class="px-4 py-3 text-text-secondary">{{ $c->email ?: '—' }}</td>
                                <td class="px-4 py-3 text-text-secondary">{{ $c->sales_transactions_count }}</td>
                                @if ($arsip)
                                    <td class="px-4 py-3 text-text-secondary">{{ $c->deleted_at?->format('d-m-Y H:i') }}</td>
                                    <td class="px-4 py-3 text-text-secondary">{{ $c->namaPenghapus() }}</td>
                                @endif
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <div class="flex gap-1.5 items-center">
                                        @if ($arsip)
                                            <form method="POST" action="{{ route('koperasi.pelanggan.restore', $c->id) }}"
                                                  onsubmit="return confirm('Pulihkan pelanggan ini?');">
                                                @csrf
                                                <button type="submit"
                                                        class="inline-flex items-center rounded-lg px-3 py-1.5 text-xs font-medium bg-primary-light text-status-success hover:opacity-80 transition">
                                                    Pulihkan
                                                </button>
                                            </form>
                                        @else
                                            <a href="{{ route('koperasi.pelanggan.edit', $c) }}"
                                               class="inline-flex items-center rounded-lg px-3 py-1.5 text-xs font-medium bg-primary-light text-cahaya-primary hover:opacity-80 transition">
                                                Edit
                                            </a>
                                            <form method="POST" action="{{ route('koperasi.pelanggan.destroy', $c) }}"
                                                  onsubmit="return confirm('Arsipkan pelanggan ini?\n\nData akan dipindahkan ke Arsip Data dan masih dapat dipulihkan. Riwayat penjualannya tidak terpengaruh.');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit"
                                                        class="inline-flex items-center rounded-lg px-3 py-1.5 text-xs font-medium bg-surface-bg text-status-danger hover:bg-status-danger/10 transition">
                                                    Hapus
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <div>{{ $customers->links() }}</div>
</div>
@endsection
