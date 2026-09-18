@extends('layouts.app')

@section('title', 'Invoice ' . $invoice->nomor_invoice)

@section('content')
<div class="max-w-3xl">
    <div class="bg-white border border-gray-100 rounded-xl shadow-sm p-8">

        @if ($transaksi->status_transaksi === 'Dibatalkan')
            {{-- Ditaruh PALING ATAS: kalau lembar ini terlanjur tercetak atau
                 dikirim ke pembeli, keadaannya harus terbaca lebih dulu
                 daripada angka tagihannya. --}}
            <div class="mb-6 border-2 border-red-300 bg-red-50 rounded-md p-4">
                <p class="text-sm font-bold text-red-700 tracking-wide">TRANSAKSI DIBATALKAN</p>
                <p class="text-xs text-red-700 mt-1">
                    Invoice ini tetap disimpan sebagai jejak, tetapi transaksinya sudah dibatalkan
                    dan TIDAK lagi dihitung sebagai tagihan maupun pendapatan.
                </p>
                @if ($transaksi->alasan_pembatalan)
                    <p class="text-xs text-red-700 mt-1">Alasan: {{ $transaksi->alasan_pembatalan }}</p>
                @endif
                <p class="text-[11px] text-red-600 mt-1">
                    Dibatalkan {{ $transaksi->dibatalkan_at?->translatedFormat('d F Y H:i') ?: '—' }}
                    @if ($transaksi->pembatal) oleh {{ $transaksi->pembatal->name }} @endif
                </p>
            </div>
        @endif

        <div class="flex items-start justify-between mb-6">
            <div class="flex items-center gap-3">
                @if ($setting->logoKoperasiUrl())
                    <img src="{{ $setting->logoKoperasiUrl() }}" class="h-10">
                @endif
                <div>
                    <p class="text-base font-bold text-koperasi-primary">{{ $setting->nama }}</p>
                    <p class="text-sm text-gray-600">{{ $transaksi->businessUnitTermasukArsip?->nama ?? '-' }}</p>
                    <p class="text-xs font-medium text-koperasi-accent">{{ $setting->tagline }}</p>
                </div>
            </div>
            <div class="text-right">
                <p class="text-xl font-bold text-koperasi-primary tracking-wide">INVOICE</p>
                <p class="text-xs text-gray-500 mt-1">No. <span class="font-semibold text-gray-800">{{ $invoice->nomor_invoice }}</span></p>
                <p class="text-xs text-gray-500">Tanggal: <span class="font-semibold text-gray-800">{{ $invoice->tanggal_invoice->format('d M Y') }}</span></p>
                <p class="text-xs text-gray-500 mb-1">Metode: <span class="font-semibold text-gray-800">{{ $transaksi->metode_pembayaran }}</span></p>
                <span class="inline-block px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $transaksi->status_pembayaran === 'Lunas' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700' }}">
                    {{ strtoupper($transaksi->status_pembayaran) }}
                </span>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-4">
            <div class="border border-gray-200 rounded-md p-4 bg-gray-50">
                <p class="text-[10px] font-bold text-gray-400 tracking-wide mb-1">DATA PELANGGAN</p>
                <p class="font-semibold text-gray-800 text-sm">{{ $transaksi->customerTermasukArsip?->nama ?? '-' }}</p>
                <p class="text-sm text-gray-600">{{ $transaksi->customerTermasukArsip?->nomor_hp ?? '' }}</p>
                <p class="text-sm text-gray-600">{{ $transaksi->customerTermasukArsip?->alamat ?? '' }}</p>
            </div>
            <div class="border border-gray-200 rounded-md p-4 bg-gray-50">
                <p class="text-[10px] font-bold text-gray-400 tracking-wide mb-1">INFORMASI PENGIRIMAN</p>
                <p class="text-sm text-gray-700">Jenis: <span class="font-semibold">{{ $transaksi->metode_pengiriman }}</span></p>
                @if ($transaksi->metode_pengiriman !== 'Ambil di Tempat' && $transaksi->alamat_pengiriman)
                    <p class="text-sm text-gray-600">{{ $transaksi->alamat_pengiriman }} @if($transaksi->hp_penerima)({{ $transaksi->hp_penerima }})@endif</p>
                @endif
                <p class="text-sm text-gray-700">Ongkir: <span class="font-semibold">{{ rupiah($transaksi->ongkir) }}</span></p>
            </div>
        </div>

        <div class="overflow-x-auto -mx-1 px-1">
            <table class="w-full text-sm min-w-[600px] mt-4 mb-4">
            <thead>
                <tr class="text-left text-white text-xs bg-koperasi-primary">
                    <th class="py-2 px-2 rounded-l-md">Produk/Jasa</th>
                    <th class="py-2 px-2">Satuan</th>
                    <th class="py-2 px-2">Qty</th>
                    <th class="py-2 px-2">Harga</th>
                    <th class="py-2 px-2">Diskon</th>
                    <th class="py-2 px-2 rounded-r-md">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($transaksi->items as $item)
                    <tr class="border-b last:border-0">
                        <td class="py-2 px-2">{{ $item->nama_snapshot }}</td>
                        <td class="py-2 px-2 text-gray-500">{{ $item->productService->satuan ?? '-' }}</td>
                        <td class="py-2 px-2">{{ $item->qty }}</td>
                        <td class="py-2 px-2">{{ rupiah($item->harga_jual_snapshot) }}</td>
                        <td class="py-2 px-2">{{ rupiah($item->diskon) }}</td>
                        <td class="py-2 px-2 font-medium">{{ rupiah($item->subtotal) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        </div>

        <div class="max-w-xs ml-auto rounded-md overflow-hidden border border-gray-200 mb-6">
            <div class="bg-koperasi-primary text-white text-xs font-bold px-3 py-2">RINGKASAN TRANSAKSI</div>
            <div class="px-3 py-3 space-y-1 text-sm bg-gray-50">
                <div class="flex justify-between"><span class="text-gray-500">Subtotal</span><span>{{ rupiah($transaksi->subtotal) }}</span></div>
                <div class="flex justify-between"><span class="text-gray-500">Diskon</span><span>{{ rupiah($transaksi->diskon) }}</span></div>
                <div class="flex justify-between"><span class="text-gray-500">Ongkir</span><span>{{ rupiah($transaksi->ongkir) }}</span></div>
                <div class="flex justify-between font-bold text-koperasi-primary text-base border-t border-gray-200 pt-1"><span>Grand Total</span><span>{{ rupiah($transaksi->total) }}</span></div>
                <div class="flex justify-between pt-1"><span class="text-gray-500">Dibayar</span><span>{{ rupiah($transaksi->nominalDibayar()) }}</span></div>
                <div class="flex justify-between font-semibold {{ $transaksi->sisaPembayaran() > 0 ? 'text-koperasi-accent' : 'text-green-600' }}"><span>Sisa Pembayaran</span><span>{{ rupiah($transaksi->sisaPembayaran()) }}</span></div>
            </div>
        </div>

        @if ($invoice->catatan)
            <div class="border-l-4 border-koperasi-accent bg-gray-50 px-4 py-2 mb-6">
                <p class="text-[10px] font-bold text-gray-400 tracking-wide mb-1">CATATAN</p>
                <p class="text-sm text-gray-700">{{ $invoice->catatan }}</p>
            </div>
        @endif

        {{-- TTD & STEMPEL --}}
        <div class="flex justify-end mb-8">
            <div class="text-center text-sm w-52">
                <p class="text-gray-500 mb-1">Hormat kami,</p>
                <div class="h-16 relative flex items-center justify-center">
                    @if ($setting->stempelKoperasiUrl())
                        <img src="{{ $setting->stempelKoperasiUrl() }}" class="h-16 absolute opacity-80">
                    @endif
                    @if ($setting->ttdKoperasiUrl())
                        <img src="{{ $setting->ttdKoperasiUrl() }}" class="h-10 relative">
                    @endif
                </div>
                <p class="font-bold text-koperasi-primary border-t border-gray-300 pt-1 mt-1">{{ $setting->nama_penandatangan ?: '(.....................)' }}</p>
                @if ($setting->jabatan_penandatangan)
                    <p class="text-xs text-gray-500">{{ $setting->jabatan_penandatangan }}</p>
                @endif
                <p class="text-xs text-gray-400">{{ $setting->nama }}</p>
            </div>
        </div>

        {{-- FOOTER --}}
        <div class="bg-koperasi-primary text-white rounded-md px-5 py-4 text-center mb-6">
            <p class="text-sm font-bold">Terima kasih telah berbelanja di {{ $transaksi->businessUnitTermasukArsip?->nama ?? $setting->nama }}.</p>
            <p class="text-xs opacity-90 mt-0.5">{{ $setting->footer_invoice ?: 'Setiap pembelian Anda turut mendukung usaha dan kesejahteraan anggota ' . $setting->nama . '.' }}</p>
            @if ($setting->tagline)
                <p class="text-xs opacity-80 font-semibold mt-1">{{ $setting->tagline }}</p>
            @endif
        </div>

        <div class="flex gap-2 no-print">
            <a href="{{ route('koperasi.invoice.pdf', $transaksi) }}" class="bg-koperasi-primary text-white text-sm font-medium px-5 py-2.5 rounded-md hover:opacity-90 transition">
                Download PDF
            </a>
            <a href="{{ route('koperasi.penjualan.show', $transaksi) }}" class="text-sm text-gray-500 px-5 py-2.5 hover:text-gray-700">
                Kembali ke Transaksi
            </a>
        </div>
    </div>
</div>
@endsection
