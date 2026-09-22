@extends('layouts.app')

@section('title', 'Detail Penjualan')

@section('content')
<div class="max-w-3xl space-y-4">

    {{-- HEADER TRANSAKSI --}}
    <div class="bg-white border border-border-soft rounded-2xl shadow-sm p-6">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h2 class="text-lg font-semibold text-text-main">{{ $transaksi->nomor_transaksi }}</h2>
                <p class="text-sm text-text-secondary">{{ $transaksi->businessUnitTermasukArsip?->nama ?? '—' }} &middot; {{ $transaksi->tanggal->format('d-m-Y') }}</p>
            </div>
            <div class="text-right space-y-1.5">
                <span class="inline-block px-2 py-1 rounded-full text-xs font-medium
                    {{ $transaksi->status_transaksi === 'Dibatalkan' ? 'bg-gray-100 text-text-secondary' : 'bg-primary-light text-status-info' }}">
                    {{ $transaksi->status_transaksi }}
                </span>
                <br>
                <span class="inline-block px-2 py-1 rounded-full text-xs font-medium
                    {{ $transaksi->status_pembayaran === 'Lunas' ? 'bg-primary-light text-status-success' : ($transaksi->status_pembayaran === 'DP' ? 'bg-gold-light text-cahaya-accent' : 'bg-red-50 text-status-danger') }}">
                    {{ $transaksi->status_pembayaran }}
                </span>
            </div>
        </div>

        {{-- INFORMASI PELANGGAN --}}
        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm mb-6">
            <div>
                <dt class="text-text-secondary">Customer</dt>
                <dd class="font-medium text-text-main">{{ $transaksi->customerTermasukArsip?->nama ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-text-secondary">Nomor HP</dt>
                <dd class="font-medium text-text-main">{{ $transaksi->customerTermasukArsip?->nomor_hp ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-text-secondary">Metode Pembayaran</dt>
                <dd class="font-medium text-text-main">{{ $transaksi->metode_pembayaran ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-text-secondary">Dibuat oleh</dt>
                <dd class="font-medium text-text-main">{{ $transaksi->creator->name ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-text-secondary">Metode Pengiriman</dt>
                <dd class="font-medium text-text-main">{{ $transaksi->metode_pengiriman ?? '—' }}</dd>
            </div>
            @if ($transaksi->metode_pengiriman !== 'Ambil di Tempat' && $transaksi->alamat_pengiriman)
                <div>
                    <dt class="text-text-secondary">Alamat Pengiriman</dt>
                    <dd class="font-medium text-text-main">{{ $transaksi->alamat_pengiriman }} @if($transaksi->hp_penerima)({{ $transaksi->hp_penerima }})@endif</dd>
                </div>
            @endif
            @if ($transaksi->nama_ekspedisi)
                <div>
                    <dt class="text-text-secondary">Ekspedisi</dt>
                    <dd class="font-medium text-text-main">{{ $transaksi->nama_ekspedisi }} @if($transaksi->nomor_resi)&mdash; Resi: {{ $transaksi->nomor_resi }}@endif</dd>
                </div>
            @endif
            @if ($transaksi->catatan)
                <div class="sm:col-span-2">
                    <dt class="text-text-secondary">Catatan</dt>
                    <dd class="font-medium text-text-main">{{ $transaksi->catatan }}</dd>
                </div>
            @endif
        </dl>

        {{-- ITEM TRANSAKSI --}}
        <div class="overflow-x-auto -mx-1 px-1">
            <table class="w-full text-sm min-w-[560px] mb-5">
            <thead>
                <tr class="text-left text-text-secondary border-b border-border-soft">
                    <th class="py-2 font-medium">Produk/Jasa</th>
                    <th class="py-2 font-medium">Qty</th>
                    <th class="py-2 font-medium">Harga</th>
                    <th class="py-2 font-medium">Diskon</th>
                    <th class="py-2 font-medium">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($transaksi->items as $item)
                    <tr class="border-b border-border-soft last:border-0">
                        <td class="py-2 text-text-main">
                            {{ $item->nama_snapshot }} <span class="text-xs text-text-secondary">({{ $item->jenis_snapshot }})</span>
                            @if ((float) $item->fee_rate > 0 || (float) $item->hak_mitra_amount > 0)
                                <span class="inline-block ml-1 px-1.5 py-0.5 rounded-full text-[10px] font-medium bg-gold-light text-cahaya-accent">Titip Jual</span>
                            @endif
                            @if ($item->catatan)<br><span class="text-xs text-text-secondary">{{ $item->catatan }}</span>@endif
                        </td>
                        <td class="py-2 text-text-secondary">{{ $item->qty }}</td>
                        <td class="py-2 text-text-secondary">{{ rupiah($item->harga_jual_snapshot) }}</td>
                        <td class="py-2 text-text-secondary">{{ rupiah($item->diskon) }}</td>
                        <td class="py-2 font-medium text-text-main">{{ rupiah($item->subtotal) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        </div>

        {{-- RINGKASAN TRANSAKSI --}}
        <div class="border-t border-border-soft pt-4 space-y-1 text-sm max-w-xs ml-auto">
            <div class="flex justify-between text-text-secondary"><span>Total Produk</span><span>{{ $transaksi->items->count() }}</span></div>
            <div class="flex justify-between text-text-secondary"><span>Total Qty</span><span>{{ $transaksi->totalQty() }}</span></div>
            <div class="flex justify-between"><span class="text-text-secondary">Subtotal</span><span>{{ rupiah($transaksi->subtotal) }}</span></div>
            <div class="flex justify-between"><span class="text-text-secondary">Diskon</span><span>{{ rupiah($transaksi->diskon) }}</span></div>
            <div class="flex justify-between"><span class="text-text-secondary">Ongkir</span><span>{{ rupiah($transaksi->ongkir) }}</span></div>
            <div class="flex justify-between font-bold text-cahaya-primary text-base border-t border-border-soft pt-1"><span>Grand Total</span><span>{{ rupiah($transaksi->total) }}</span></div>
            <div class="flex justify-between pt-2"><span class="text-text-secondary">Dibayar</span><span>{{ rupiah($transaksi->nominalDibayar()) }}</span></div>
            <div class="flex justify-between {{ $transaksi->sisaPembayaran() > 0 ? 'text-status-danger' : '' }}"><span>Sisa</span><span>{{ rupiah($transaksi->sisaPembayaran()) }}</span></div>
        </div>

        {{-- TITIP JUAL — hanya tampil kalau ada itemnya --}}
        @if ($transaksi->totalHakMitra() > 0)
            <div class="border-t border-dashed border-border-soft mt-4 pt-4 max-w-xs ml-auto">
                <p class="text-xs text-text-secondary mb-1">Titip Jual</p>
                <div class="flex justify-between text-sm text-text-secondary"><span>Fee Koperasi</span><span class="font-medium text-cahaya-accent">{{ rupiah($transaksi->totalFeeKoperasi()) }}</span></div>
                <div class="flex justify-between text-sm text-text-secondary"><span>Hak Mitra</span><span class="font-medium text-text-main">{{ rupiah($transaksi->totalHakMitra()) }}</span></div>
            </div>
        @endif

        {{-- INTERNAL/ADMIN SAJA — TIDAK PERNAH ditampilkan di invoice customer --}}
        @if (auth()->user()->isAdmin())
            <div class="border-t border-dashed border-border-soft mt-4 pt-4 max-w-xs ml-auto">
                <p class="text-xs text-text-secondary mb-1">Internal (tidak tampil di invoice)</p>
                <div class="flex justify-between text-sm text-text-secondary"><span>Total HPP</span><span>{{ rupiah($transaksi->totalHpp()) }}</span></div>
                <div class="flex justify-between text-sm font-semibold text-cahaya-accent"><span>Estimasi Profit</span><span>{{ rupiah($transaksi->estimasiProfit()) }}</span></div>
            </div>
        @endif

        <div class="mt-6 flex gap-2 flex-wrap">
            {{-- Syaratnya disamakan dengan authorizeManage() di controller
                 (bolehKelolaKoperasi), bukan isAdmin(). Sebelumnya Tim
                 Koperasi tidak melihat tombol ini padahal controllernya
                 memang mengizinkan — tombolnya hilang, kewenangannya tidak. --}}
            @if (auth()->user()->bolehKelolaKoperasi() && $transaksi->status_transaksi !== 'Dibatalkan')
                <a href="{{ route('koperasi.penjualan.edit', $transaksi) }}" class="border-2 border-cahaya-primary text-cahaya-primary text-sm font-medium px-5 py-2.5 rounded-lg hover:bg-cahaya-primary/5 transition">
                    Edit Transaksi
                </a>
            @endif
            @if ($transaksi->invoice)
                <a href="{{ route('koperasi.invoice.show', $transaksi) }}" class="bg-cahaya-primary text-white text-sm font-medium px-5 py-2.5 rounded-lg hover:opacity-90 transition">
                    Lihat Invoice
                </a>
                <a href="{{ route('koperasi.invoice.pdf', $transaksi) }}" class="border-2 border-cahaya-primary text-cahaya-primary text-sm font-medium px-5 py-2.5 rounded-lg hover:bg-cahaya-primary/5 transition">
                    Download PDF
                </a>
            {{-- bolehKelolaKoperasi(), BUKAN isAdmin().

                 Kesalahan yang sama persis dengan tombol Edit di atas, dan
                 komentarnya sudah memperingatkannya — tapi hanya tombol Edit
                 yang ikut diperbaiki waktu itu.

                 Akibatnya nyata: InvoiceController@store mengizinkan
                 bolehKelolaKoperasi(), tetapi tombolnya hanya muncul untuk
                 Admin. Tim Koperasi — yang memasukkan seluruh transaksi —
                 tidak pernah melihat jalan membuat invoice, dan sembilan
                 transaksi berjalan tanpa satu pun invoice terbit. --}}
            @elseif (auth()->user()->bolehKelolaKoperasi())
                <form method="POST" action="{{ route('koperasi.invoice.store', $transaksi) }}">
                    @csrf
                    <button type="submit" class="bg-cahaya-primary text-white text-sm font-medium px-5 py-2.5 rounded-lg hover:opacity-90 transition">
                        Buat Invoice
                    </button>
                </form>
            @endif
            <a href="{{ route('koperasi.penjualan.index') }}" class="text-sm text-text-secondary px-5 py-2.5 hover:text-text-main">
                Kembali
            </a>
        </div>

        {{-- ============================================================
             PEMBATALAN TRANSAKSI — bukan penghapusan.

             SENGAJA tidak ada tombol Hapus di sini. Transaksi penjualan
             sudah masuk laba rugi, laporan penjualan, dan mungkin invoice
             yang sudah dicetak untuk pembeli; menghapusnya akan mengubah
             semua itu tanpa jejak. Pembatalan membuat barisnya berhenti
             dihitung TANPA menghilangkannya, lengkap dengan alasannya.
             ============================================================ --}}
        @if ($transaksi->status_transaksi === 'Dibatalkan')
            <div class="mt-6 pt-5 border-t border-border-soft">
                <p class="text-[13px] font-semibold text-text-main mb-1">Transaksi Dibatalkan</p>
                <p class="text-xs text-text-secondary">
                    Dibatalkan {{ $transaksi->dibatalkan_at?->format('d-m-Y H:i') ?: '—' }}
                    @if ($transaksi->pembatal) oleh {{ $transaksi->pembatal->name }} @endif
                </p>
                @if ($transaksi->alasan_pembatalan)
                    <p class="text-xs text-status-warning mt-1">Alasan: {{ $transaksi->alasan_pembatalan }}</p>
                @endif
                <p class="text-[11px] text-text-secondary mt-2">
                    Datanya tetap tersimpan sebagai jejak, tapi sudah tidak dihitung di laporan manapun.
                </p>

                @if (auth()->user()->bolehKelolaKoperasi())
                    <form method="POST" action="{{ route('koperasi.penjualan.pulihkan', $transaksi) }}" class="mt-4">
                        @csrf
                        <button type="submit"
                                class="border-2 border-cahaya-primary text-cahaya-primary text-sm font-medium px-5 py-2.5 rounded-lg hover:bg-cahaya-primary/5 transition"
                                onclick="return confirm('Pulihkan transaksi ini?

Transaksi akan kembali dihitung di laba rugi dan laporan penjualan.');">
                            Pulihkan Transaksi
                        </button>
                        <p class="text-[11px] text-text-secondary mt-2">
                            Dipakai kalau pembatalannya keliru. Transaksi kembali dihitung di laporan aktif,
                            dan pemulihannya ikut tercatat di Activity Log.
                        </p>
                    </form>
                @endif
            </div>
        @elseif (auth()->user()->bolehKelolaKoperasi())
            <details class="mt-6 pt-5 border-t border-border-soft">
                <summary class="text-sm font-medium text-status-danger cursor-pointer">Batalkan Transaksi</summary>

                <form method="POST" action="{{ route('koperasi.penjualan.batalkan', $transaksi) }}" class="mt-4 space-y-3 max-w-xl">
                    @csrf
                    <p class="text-xs text-text-secondary">
                        Transaksi TIDAK dihapus — barisnya tetap ada beserta seluruh rinciannya, hanya berhenti
                        dihitung di laba rugi dan laporan penjualan. Alasannya ikut tersimpan.
                    </p>
                    <div>
                        <label class="block text-xs font-medium text-text-main mb-1">Alasan Pembatalan</label>
                        <input type="text" name="alasan_pembatalan" required
                               placeholder="Contoh: pesanan dibatalkan pembeli, barang tidak jadi dikirim"
                               class="w-full rounded-lg border border-border-soft px-3 py-2 text-sm">
                        @error('alasan_pembatalan') <p class="text-xs text-status-danger mt-1">{{ $message }}</p> @enderror
                    </div>
                    <button type="submit"
                            class="bg-status-danger text-white text-sm font-medium px-5 py-2.5 rounded-lg hover:opacity-90 transition"
                            onclick="return confirm('Batalkan transaksi ini? Datanya tetap tersimpan sebagai jejak.');">
                        Batalkan Transaksi
                    </button>
                </form>
            </details>
        @endif
    </div>
</div>
@endsection
