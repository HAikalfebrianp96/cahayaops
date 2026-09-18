@extends('layouts.app')

@section('title', 'Penjualan')

@section('content')
<div class="space-y-5">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
        <div>
            <h2 class="text-[20px] font-bold text-text-main">Penjualan</h2>
            <p class="text-sm text-text-secondary mt-0.5">Daftar transaksi penjualan Koperasi Cahaya.</p>
        </div>
        <a href="{{ route('koperasi.penjualan.create') }}"
           class="inline-flex items-center justify-center bg-cahaya-primary text-white text-sm font-medium px-4 py-2.5 rounded-lg hover:opacity-90 transition">
            + Tambah Penjualan
        </a>
    </div>

    <form method="GET" action="{{ route('koperasi.penjualan.index') }}"
          class="bg-white border border-border-soft rounded-2xl shadow-sm p-4 grid grid-cols-1 md:grid-cols-6 gap-3">
        <input type="date" name="tanggal" value="{{ $filters['tanggal'] ?? '' }}" class="rounded-lg border border-border-soft px-3 py-2 text-sm" title="Tanggal spesifik">
        <select name="bulan" class="rounded-lg border border-border-soft px-3 py-2 text-sm">
            <option value="">Semua Bulan</option>
            @for ($b = 1; $b <= 12; $b++)
                <option value="{{ $b }}" @selected((string) ($filters['bulan'] ?? '') === (string) $b)>{{ \Carbon\Carbon::create()->month($b)->translatedFormat('F') }}</option>
            @endfor
        </select>
        <select name="tahun" class="rounded-lg border border-border-soft px-3 py-2 text-sm">
            <option value="">Semua Tahun</option>
            @for ($t = now()->year - 1; $t <= now()->year; $t++)
                <option value="{{ $t }}" @selected((string) ($filters['tahun'] ?? '') === (string) $t)>{{ $t }}</option>
            @endfor
        </select>
        <select name="unit_id" class="rounded-lg border border-border-soft px-3 py-2 text-sm">
            <option value="">Semua Unit</option>
            @foreach ($units as $u)
                <option value="{{ $u->id }}" @selected((string) ($filters['unit_id'] ?? '') === (string) $u->id)>{{ $u->nama }}</option>
            @endforeach
        </select>
        <select name="status_pembayaran" class="rounded-lg border border-border-soft px-3 py-2 text-sm">
            <option value="">Semua Status Bayar</option>
            @foreach ($statusOptions as $status)
                <option value="{{ $status }}" @selected(($filters['status_pembayaran'] ?? '') === $status)>{{ $status }}</option>
            @endforeach
        </select>
        {{-- Labelnya menyebut "termasuk dibatalkan" secara terang-terangan.
             "Semua" saja mudah disalahartikan sebagai daftar penjualan yang
             sah — persis kekeliruan yang paling mahal di layar keuangan. --}}
        <select name="keadaan" class="rounded-lg border border-border-soft px-3 py-2 text-sm">
            <option value="">Semua (termasuk dibatalkan)</option>
            <option value="aktif" @selected(($filters['keadaan'] ?? '') === 'aktif')>Hanya Aktif</option>
            <option value="dibatalkan" @selected(($filters['keadaan'] ?? '') === 'dibatalkan')>Hanya Dibatalkan</option>
        </select>
        <div class="md:col-span-6 flex gap-2">
            <button type="submit" class="bg-status-info text-white text-sm font-medium px-4 py-2 rounded-lg hover:opacity-90 transition">Terapkan Filter</button>
            <a href="{{ route('koperasi.penjualan.index') }}" class="text-sm text-text-secondary px-4 py-2 hover:text-text-main">Reset</a>
        </div>
    </form>

    <div class="bg-white border border-border-soft rounded-2xl shadow-sm overflow-hidden">
        @if ($transaksi->isEmpty())
            <div class="text-center py-14">
                <p class="text-sm font-medium text-text-main mb-1">Belum ada transaksi</p>
                <p class="text-xs text-text-secondary mb-4">Catat transaksi penjualan pertama Anda.</p>
                <a href="{{ route('koperasi.penjualan.create') }}" class="inline-block text-sm bg-cahaya-primary text-white font-medium px-4 py-2 rounded-lg hover:opacity-90 transition">+ Tambah Penjualan</a>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-text-secondary border-b border-border-soft bg-surface-bg">
                            <th class="px-4 py-3 font-medium">Nomor</th>
                            <th class="px-4 py-3 font-medium">Tanggal</th>
                            <th class="px-4 py-3 font-medium">Unit</th>
                            <th class="px-4 py-3 font-medium">Pelanggan</th>
                            <th class="px-4 py-3 font-medium">Total</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                            <th class="px-4 py-3 font-medium">Status Bayar</th>
                            <th class="px-4 py-3 font-medium">Invoice</th>
                            <th class="px-4 py-3 font-medium">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($transaksi as $t)
                            <tr class="border-b border-border-soft last:border-0 hover:bg-surface-bg/60 transition">
                                <td class="px-4 py-3 text-text-main">{{ $t->nomor_transaksi }}</td>
                                <td class="px-4 py-3 text-text-secondary">{{ $t->tanggal->format('d-m-Y') }}</td>
                                <td class="px-4 py-3 text-text-secondary">{{ $t->businessUnit->nama ?? '—' }}</td>
                                <td class="px-4 py-3 text-text-secondary">{{ $t->customer->nama ?? '—' }}</td>
                                <td class="px-4 py-3 font-medium text-text-main">{{ rupiah($t->total) }}</td>
                                <td class="px-4 py-3">
                                    {{-- Dibatalkan ditandai dengan KATA, bukan hanya warna abu —
                                         supaya terbaca juga saat dicetak hitam-putih. --}}
                                    <span class="inline-block px-2 py-0.5 rounded-full text-xs font-medium
                                        {{ $t->status_transaksi === 'Dibatalkan' ? 'bg-gray-100 text-text-secondary' : 'bg-primary-light text-status-info' }}">
                                        {{ $t->status_transaksi }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-block px-2 py-0.5 rounded-full text-xs font-medium
                                        {{ $t->status_pembayaran === 'Lunas' ? 'bg-primary-light text-status-success' : ($t->status_pembayaran === 'DP' ? 'bg-gold-light text-cahaya-accent' : 'bg-red-50 text-status-danger') }}">
                                        {{ $t->status_pembayaran }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-text-secondary">{{ $t->invoice->nomor_invoice ?? '—' }}</td>
                                {{-- AKSI MENGIKUTI KEADAAN BARIS, bukan satu tombol untuk semua
                                     kondisi. Transaksi aktif tidak menawarkan "Pulihkan", dan
                                     transaksi yang sudah dibatalkan tidak menawarkan "Batalkan"
                                     — tombol yang pasti ditolak server tidak pernah ditampilkan.

                                     "Batalkan" di sini adalah TAUTAN ke halaman detail, bukan
                                     tombol kirim. Pembatalan mewajibkan alasan, dan alasan itu
                                     tidak bisa ditulis dari dalam sebuah baris tabel. --}}
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <div class="flex items-center gap-3">
                                        <a href="{{ route('koperasi.penjualan.show', $t) }}" class="text-status-info hover:underline text-xs font-medium">Detail</a>

                                        @if (auth()->user()->bolehKelolaKoperasi())
                                            @if ($t->status_transaksi === 'Dibatalkan')
                                                <form method="POST" action="{{ route('koperasi.penjualan.pulihkan', $t) }}"
                                                      onsubmit="return confirm('Pulihkan transaksi {{ $t->nomor_transaksi }}?

Transaksi akan kembali dihitung di laba rugi dan laporan penjualan.');">
                                                    @csrf
                                                    <button type="submit" class="text-cahaya-primary hover:underline text-xs font-medium">Pulihkan</button>
                                                </form>
                                            @else
                                                <a href="{{ route('koperasi.penjualan.edit', $t) }}" class="text-cahaya-primary hover:underline text-xs font-medium">Edit</a>
                                                <a href="{{ route('koperasi.penjualan.show', $t) }}" class="text-status-danger hover:underline text-xs font-medium">Batalkan</a>
                                            @endif
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

    @if ($transaksi->isNotEmpty())
        <div>{{ $transaksi->links() }}</div>
    @endif
</div>
@endsection
