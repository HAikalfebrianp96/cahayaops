@extends('layouts.app')

@section('title', 'Pengeluaran')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-lg font-semibold text-text-main">Pengeluaran</h2>
            <p class="text-sm text-text-secondary">Catat & pantau pengeluaran Koperasi per unit usaha maupun operasional umum.</p>
        </div>
        <a href="{{ route('koperasi.pengeluaran.create') }}" class="bg-koperasi-primary text-white text-sm font-medium px-4 py-2 rounded-md hover:opacity-90 transition whitespace-nowrap">
            + Tambah Pengeluaran
        </a>
    </div>

    {{-- FILTER --}}
    <form method="GET" class="bg-white border border-border-soft rounded-2xl shadow-sm p-4 grid grid-cols-2 sm:grid-cols-5 gap-3">
        <div>
            <label class="block text-xs text-text-secondary mb-1">Tahun</label>
            <select name="tahun" class="w-full rounded-md border border-gray-300 px-2 py-1.5 text-sm">
                <option value="default" {{ ($filters['tahun'] ?? 'default') === 'default' ? 'selected' : '' }}>Tahun Ini</option>
                <option value="semua" {{ ($filters['tahun'] ?? '') === 'semua' ? 'selected' : '' }}>Semua Tahun</option>
                @for ($y = now()->year; $y >= now()->year - 3; $y--)
                    <option value="{{ $y }}" {{ ($filters['tahun'] ?? '') == $y ? 'selected' : '' }}>{{ $y }}</option>
                @endfor
            </select>
        </div>
        <div>
            <label class="block text-xs text-text-secondary mb-1">Bulan</label>
            <select name="bulan" class="w-full rounded-md border border-gray-300 px-2 py-1.5 text-sm">
                <option value="default" {{ ($filters['bulan'] ?? 'default') === 'default' ? 'selected' : '' }}>Bulan Ini</option>
                <option value="semua" {{ ($filters['bulan'] ?? '') === 'semua' ? 'selected' : '' }}>Semua Bulan</option>
                @foreach (['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'] as $i => $nm)
                    <option value="{{ $i + 1 }}" {{ ($filters['bulan'] ?? '') == ($i + 1) ? 'selected' : '' }}>{{ $nm }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs text-text-secondary mb-1">Unit Usaha</label>
            <select name="unit_id" class="w-full rounded-md border border-gray-300 px-2 py-1.5 text-sm">
                <option value="">Semua Unit</option>
                <option value="operasional" {{ ($filters['unit_id'] ?? '') === 'operasional' ? 'selected' : '' }}>Operasional Koperasi</option>
                @foreach ($units as $u)
                    <option value="{{ $u->id }}" {{ ($filters['unit_id'] ?? '') == $u->id ? 'selected' : '' }}>{{ $u->nama }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs text-text-secondary mb-1">Kategori</label>
            <select name="kategori" class="w-full rounded-md border border-gray-300 px-2 py-1.5 text-sm">
                <option value="">Semua Kategori</option>
                @foreach ($kategoriSaran as $k)
                    <option value="{{ $k }}" {{ ($filters['kategori'] ?? '') === $k ? 'selected' : '' }}>{{ $k }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex items-end gap-2">
            <div class="flex-1">
                <label class="block text-xs text-text-secondary mb-1">Metode</label>
                <select name="metode_pembayaran" class="w-full rounded-md border border-gray-300 px-2 py-1.5 text-sm">
                    <option value="">Semua</option>
                    @foreach ($metodeOptions as $m)
                        <option value="{{ $m }}" {{ ($filters['metode_pembayaran'] ?? '') === $m ? 'selected' : '' }}>{{ $m }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="bg-gray-100 text-text-main text-sm px-3 py-1.5 rounded-md hover:bg-gray-200 transition">Filter</button>
        </div>
    </form>

    {{-- RINGKASAN --}}
    <div class="bg-white border border-border-soft rounded-2xl shadow-sm p-6">
        <p class="text-xs text-text-secondary mb-1">Total Pengeluaran (sesuai filter di atas)</p>
        <p class="text-2xl font-bold text-koperasi-primary mb-4">{{ rupiah($totalPeriode) }}</p>

        {{-- Dua kelompok dengan perlakuan laba-rugi berbeda. Keduanya tetap
             uang keluar, dan jumlahnya sama dengan total di atas. --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm mb-4">
            <div class="border border-border-soft rounded-lg p-3">
                <p class="text-xs text-text-secondary">Pengeluaran Operasional</p>
                <p class="font-bold text-text-main">{{ rupiah($totalOperasional) }}</p>
                <p class="text-[11px] text-text-secondary mt-0.5">Mengurangi Laba Bersih.</p>
            </div>
            <div class="border border-border-soft rounded-lg p-3">
                <p class="text-xs text-text-secondary">Pembelian/Re-Stok Produk</p>
                <p class="font-bold text-text-main">{{ rupiah($totalPembelianStok) }}</p>
                <p class="text-[11px] text-text-secondary mt-0.5">Tidak langsung mengurangi laba — biayanya menjadi HPP saat produk terjual.</p>
            </div>
        </div>

        <p class="text-xs text-text-secondary mb-2">Per unit usaha</p>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-sm">
            @foreach ($units as $u)
                <div class="border border-border-soft rounded-lg p-3">
                    <p class="text-xs text-text-secondary">{{ $u->nama }}</p>
                    <p class="font-bold text-text-main">{{ rupiah($breakdown[$u->id] ?? 0) }}</p>
                </div>
            @endforeach
            <div class="border border-border-soft rounded-lg p-3">
                <p class="text-xs text-text-secondary">Operasional Koperasi</p>
                <p class="font-bold text-text-main">{{ rupiah($breakdown['operasional'] ?? 0) }}</p>
            </div>
        </div>
    </div>

    {{-- TABEL --}}
    <div class="bg-white border border-border-soft rounded-2xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto -mx-1 px-1">
            <table class="w-full text-sm min-w-[680px]">
            <thead>
                <tr class="text-left text-text-secondary border-b bg-surface-bg">
                    <th class="py-2 px-4">Tanggal</th>
                    <th class="py-2 px-4">Unit</th>
                    <th class="py-2 px-4">Kategori</th>
                    <th class="py-2 px-4">Nominal</th>
                    <th class="py-2 px-4">Metode</th>
                    <th class="py-2 px-4">Status</th>
                    <th class="py-2 px-4">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $r)
                    <tr class="border-b last:border-0 hover:bg-surface-bg cursor-pointer" onclick="window.location='{{ route('koperasi.pengeluaran.show', $r) }}'">
                        <td class="py-2 px-4">{{ $r->tanggal->format('d-m-Y') }}</td>
                        <td class="py-2 px-4">{{ $r->labelUnit() }}</td>
                        <td class="py-2 px-4">
                            {{ $r->kategori }}
                            @if ($r->isPembelianStok())
                                <span class="block text-[11px] text-text-secondary">Pembelian stok</span>
                            @endif
                        </td>
                        <td class="py-2 px-4">{{ rupiah($r->nominal) }}</td>
                        <td class="py-2 px-4">{{ $r->metode_pembayaran }}</td>
                        <td class="py-2 px-4">
                            <span class="px-2 py-0.5 rounded-full text-xs font-semibold {{ $r->status === 'Aktif' ? 'bg-primary-light text-status-success' : 'bg-red-100 text-status-danger' }}">
                                {{ $r->status }}
                            </span>
                        </td>

                        {{-- AKSI MENGIKUTI KEADAAN BARIS.

                             Pengeluaran aktif tidak menawarkan "Pulihkan", dan yang
                             sudah dibatalkan tidak menawarkan "Batalkan" — tombol yang
                             pasti ditolak server tidak pernah ditampilkan.

                             "Hapus Permanen" hanya pada yang SUDAH dibatalkan: penjaganya
                             menuntut pembatalan beserta alasannya lebih dulu, supaya uang
                             yang hilang dari laporan selalu punya keterangan kenapa.

                             onclick pada <tr> membuka detail; setiap tombol di sel ini
                             menghentikan rambatannya, kalau tidak satu klik akan
                             mengerjakan dua hal sekaligus. --}}
                        <td class="py-2 px-4 whitespace-nowrap" onclick="event.stopPropagation();">
                            <div class="flex items-center gap-3">
                                <a href="{{ route('koperasi.pengeluaran.show', $r) }}"
                                   class="text-status-info hover:underline text-xs font-medium">Detail</a>

                                @if (auth()->user()?->bolehKelolaKoperasi())
                                    @if ($r->dibatalkan())
                                        <form method="POST" action="{{ route('koperasi.pengeluaran.pulihkan', $r) }}" class="inline"
                                              onsubmit="return confirm('Pulihkan pengeluaran ini?

Nominalnya akan kembali dihitung di laporan.');">
                                            @csrf
                                            <button type="submit" class="text-cahaya-primary hover:underline text-xs font-medium">Pulihkan</button>
                                        </form>

                                        <form method="POST" action="{{ route('koperasi.pengeluaran.destroy', $r) }}" class="inline"
                                              onsubmit="return confirm('Hapus Pengeluaran Permanen?

Data yang dihapus tidak dapat dipulihkan. Pastikan data ini memang ingin dihapus.');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-status-danger hover:underline text-xs font-medium">Hapus Permanen</button>
                                        </form>
                                    @else
                                        <a href="{{ route('koperasi.pengeluaran.edit', $r) }}"
                                           class="text-cahaya-primary hover:underline text-xs font-medium">Edit</a>
                                        <a href="{{ route('koperasi.pengeluaran.show', $r) }}"
                                           class="text-status-danger hover:underline text-xs font-medium">Batalkan</a>
                                    @endif
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="py-8 text-center text-text-secondary italic">Belum ada pengeluaran untuk filter ini.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    </div>

    {{ $rows->links() }}
</div>
@endsection
