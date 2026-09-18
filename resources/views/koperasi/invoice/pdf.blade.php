<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: sans-serif; font-size: 11px; color: #222; }
        .box { border: 1px solid #ddd; border-radius: 6px; padding: 10px 14px; background: #F9FBFC; }
        .box-label { font-size: 9px; font-weight: bold; color: #888; letter-spacing: 0.5px; margin-bottom: 4px; }
        table.data { width: 100%; border-collapse: collapse; margin-top: 14px; }
        table.data th, table.data td { border: 1px solid #ddd; padding: 6px 8px; text-align: left; }
        table.data th { background: #0B4F63; color: #fff; font-size: 9px; }
        table.data td { font-size: 9.5px; }
        table.data tr:nth-child(even) td { background: #F9FBFC; }
        .total-box { border-radius: 6px; overflow: hidden; margin-top: 14px; width: 260px; margin-left: auto; border: 1px solid #ddd; }
        .total-box .head { background: #0B4F63; color: #fff; font-size: 10px; font-weight: bold; padding: 6px 10px; }
        .total-box .body { padding: 8px 10px; background: #F9FBFC; }
        .total-box .body table { width: 100%; font-size: 11px; }
        .total-box .body td { padding: 3px 0; }
        .total-box .grand { font-weight: bold; font-size: 14px; color: #0B4F63; border-top: 1px solid #0B4F63; padding-top: 6px; }
        .status-pill { display: inline-block; background: #FDF3D8; color: #A5730A; font-size: 9px; font-weight: bold; padding: 3px 10px; border-radius: 10px; }
        .status-lunas { background: #E1F5EA; color: #157347; }
        .footer-box { background: #0B4F63; color: #fff; border-radius: 6px; padding: 14px 18px; margin-top: 40px; text-align: center; }
        .footer-box p { margin: 3px 0; }
    </style>
</head>
<body>
@if ($transaksi->status_transaksi === 'Dibatalkan')
    {{-- Lembar yang dicetak bisa beredar tanpa konteks apa pun. Kalau
         transaksinya sudah dibatalkan, itu harus terbaca di kertasnya
         sendiri — bukan cuma di layar aplikasi. --}}
    <div style="border: 2px solid #B91C1C; background: #FEF2F2; padding: 10px; margin-bottom: 14px;">
        <p style="margin: 0; font-size: 13px; font-weight: bold; color: #B91C1C; letter-spacing: 1px;">
            TRANSAKSI DIBATALKAN
        </p>
        <p style="margin: 4px 0 0; font-size: 10px; color: #B91C1C;">
            Invoice ini tetap disimpan sebagai jejak, tetapi transaksinya sudah dibatalkan
            dan TIDAK lagi berlaku sebagai tagihan.
        </p>
        @if ($transaksi->alasan_pembatalan)
            <p style="margin: 3px 0 0; font-size: 10px; color: #B91C1C;">Alasan: {{ $transaksi->alasan_pembatalan }}</p>
        @endif
    </div>
@endif

    <table style="width: 100%;">
        <tr>
            <td style="width: 60px; vertical-align: top;">
                <img src="{{ $logoPath }}" style="height: 42px;">
            </td>
            <td style="vertical-align: top;">
                <p style="margin: 0; font-size: 15px; font-weight: bold; color: #0B4F63;">{{ $setting->nama }}</p>
                <p style="margin: 0; font-size: 10px; color: #333;">{{ $transaksi->businessUnitTermasukArsip?->nama ?? '-' }}</p>
                <p style="margin: 0; font-size: 9px; color: #F5C518;">{{ $setting->tagline }}</p>
            </td>
            <td style="text-align: right; vertical-align: top;">
                <p style="margin: 0; font-size: 20px; font-weight: bold; color: #0B4F63; letter-spacing: 1px;">INVOICE</p>
                <p style="margin: 2px 0 0; font-size: 9px; color: #777;">No. <strong style="color:#222;">{{ $invoice->nomor_invoice }}</strong></p>
                <p style="margin: 0; font-size: 9px; color: #777;">Tanggal: <strong style="color:#222;">{{ $invoice->tanggal_invoice->format('d M Y') }}</strong></p>
                <p style="margin: 0 0 4px; font-size: 9px; color: #777;">Metode: <strong style="color:#222;">{{ $transaksi->metode_pembayaran }}</strong></p>
                <span class="status-pill {{ $transaksi->status_pembayaran === 'Lunas' ? 'status-lunas' : '' }}">{{ strtoupper($transaksi->status_pembayaran) }}</span>
            </td>
        </tr>
    </table>

    <table style="width: 100%; margin-top: 16px; border-collapse: separate; border-spacing: 8px 0;">
        <tr>
            <td style="width: 50%; vertical-align: top;">
                <div class="box">
                    <p class="box-label">DATA PELANGGAN</p>
                    <p style="margin: 0; font-weight: bold; font-size: 11px;">{{ $transaksi->customerTermasukArsip?->nama ?? '-' }}</p>
                    <p style="margin: 0;">{{ $transaksi->customerTermasukArsip?->nomor_hp ?? '' }}</p>
                    <p style="margin: 0;">{{ $transaksi->customerTermasukArsip?->alamat ?? '' }}</p>
                </div>
            </td>
            <td style="width: 50%; vertical-align: top;">
                <div class="box">
                    <p class="box-label">INFORMASI PENGIRIMAN</p>
                    <p style="margin: 0;">Jenis: <strong>{{ $transaksi->metode_pengiriman }}</strong></p>
                    @if ($transaksi->metode_pengiriman !== 'Ambil di Tempat' && $transaksi->alamat_pengiriman)
                        <p style="margin: 0;">{{ $transaksi->alamat_pengiriman }} @if($transaksi->hp_penerima)({{ $transaksi->hp_penerima }})@endif</p>
                    @endif
                    <p style="margin: 0;">Ongkir: <strong>{{ rupiah($transaksi->ongkir) }}</strong></p>
                </div>
            </td>
        </tr>
    </table>

    <table class="data">
        <thead>
            <tr>
                <th>No</th>
                <th>Produk/Jasa</th>
                <th>Satuan</th>
                <th>Qty</th>
                <th>Harga</th>
                <th>Diskon</th>
                <th>Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($transaksi->items as $i => $item)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $item->nama_snapshot }}</td>
                    <td>{{ $item->productService->satuan ?? '-' }}</td>
                    <td>{{ $item->qty }}</td>
                    <td>{{ rupiah($item->harga_jual_snapshot) }}</td>
                    <td>{{ rupiah($item->diskon) }}</td>
                    <td>{{ rupiah($item->subtotal) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="total-box">
        <tr><td class="head">RINGKASAN TRANSAKSI</td></tr>
        <tr><td class="body">
            <table>
                <tr><td>Subtotal</td><td style="text-align: right;">{{ rupiah($transaksi->subtotal) }}</td></tr>
                <tr><td>Diskon</td><td style="text-align: right;">{{ rupiah($transaksi->diskon) }}</td></tr>
                <tr><td>Ongkir</td><td style="text-align: right;">{{ rupiah($transaksi->ongkir) }}</td></tr>
                <tr class="grand"><td>Grand Total</td><td style="text-align: right;">{{ rupiah($transaksi->total) }}</td></tr>
                <tr><td>Dibayar</td><td style="text-align: right;">{{ rupiah($transaksi->nominalDibayar()) }}</td></tr>
                <tr><td style="font-weight: bold;">Sisa Pembayaran</td><td style="text-align: right; font-weight: bold; color: {{ $transaksi->sisaPembayaran() > 0 ? '#A5730A' : '#157347' }};">{{ rupiah($transaksi->sisaPembayaran()) }}</td></tr>
            </table>
        </td></tr>
    </table>

    @if ($invoice->catatan)
        <div class="box" style="border-left: 3px solid #F5C518; margin-top: 14px;">
            <p class="box-label">CATATAN</p>
            <p style="margin: 0;">{{ $invoice->catatan }}</p>
        </div>
    @endif

    {{-- TTD & STEMPEL --}}
    <table style="width: 100%; margin-top: 45px;">
        <tr>
            <td style="width: 60%;"></td>
            <td style="width: 40%; text-align: center;">
                <p style="margin: 0; font-size: 10px; color: #666;">Hormat kami,</p>

                <div style="height: 75px; position: relative;">
                    @if ($stempelPath)
                        <img src="{{ $stempelPath }}" style="height: 70px; position: absolute; left: 50%; top: 0; margin-left: -35px; opacity: 0.85;">
                    @endif
                    @if ($ttdPath)
                        <img src="{{ $ttdPath }}" style="height: 45px; position: absolute; left: 50%; top: 15px; margin-left: -22px;">
                    @endif
                </div>

                <p style="margin: 4px 0 0; font-size: 11px; font-weight: bold; border-top: 1px solid #333; padding-top: 4px; display: inline-block; min-width: 150px; color: #0B4F63;">
                    {{ $setting->nama_penandatangan ?: '(.....................)' }}
                </p>
                @if ($setting->jabatan_penandatangan)
                    <p style="margin: 0; font-size: 9px; color: #666;">{{ $setting->jabatan_penandatangan }}</p>
                @endif
                <p style="margin: 0; font-size: 9px; color: #999;">{{ $setting->nama }}</p>
            </td>
        </tr>
    </table>

    <div class="footer-box">
        <p style="font-size: 11px; font-weight: bold;">Terima kasih telah berbelanja di {{ $transaksi->businessUnitTermasukArsip?->nama ?? $setting->nama }}.</p>
        <p style="font-size: 9px; opacity: 0.9;">
            {{ $setting->footer_invoice ?: 'Setiap pembelian Anda turut mendukung usaha dan kesejahteraan anggota ' . $setting->nama . '.' }}
        </p>
        @if ($setting->tagline)
            <p style="font-size: 9px; opacity: 0.75; margin-top: 4px; font-weight: bold;">{{ $setting->tagline }}</p>
        @endif
    </div>
</body>
</html>
