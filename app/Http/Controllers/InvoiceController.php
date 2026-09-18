<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\KoperasiSetting;
use App\Models\SalesTransaction;
use App\Services\GambarCetak;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;

class InvoiceController extends Controller
{
    public function show(SalesTransaction $transaksi)
    {
        abort_unless(Auth::user()->bolehKelolaKoperasi() || Auth::user()->isPimpinan(), 403);
        abort_unless($transaksi->invoice, 404);

        $transaksi->load(['businessUnit', 'customer', 'items.productService']);

        return view('koperasi.invoice.show', [
            'transaksi' => $transaksi,
            'invoice' => $transaksi->invoice,
            'setting' => KoperasiSetting::current(),
        ]);
    }

    public function store(SalesTransaction $transaksi)
    {
        abort_unless(Auth::user()->bolehKelolaKoperasi(), 403);

        if (! $transaksi->invoice) {
            Invoice::create([
                'sales_transaction_id' => $transaksi->id,
                'tanggal_invoice' => $transaksi->tanggal,
            ]);
        }

        return redirect()
            ->route('koperasi.penjualan.show', $transaksi)
            ->with('success', 'Invoice berhasil dibuat.');
    }

    public function pdf(SalesTransaction $transaksi)
    {
        abort_unless(Auth::user()->bolehKelolaKoperasi() || Auth::user()->isPimpinan(), 403);
        abort_unless($transaksi->invoice, 404);

        $transaksi->load(['businessUnit', 'customer', 'items.productService']);
        $setting = KoperasiSetting::current();

        // Ketiga gambar identitas dilewatkan GambarCetak lebih dulu.
        // Invoice adalah berkas yang paling sering keluar dari lembaga —
        // dikirim satu per satu ke pelanggan — jadi justru di sinilah beban
        // gambar mentah paling terasa. Berkas aslinya tidak disentuh.
        $cetak = app(GambarCetak::class);

        $logoPath = $cetak->untukCetak(
            $setting->logo_koperasi
                ? public_path('images/koperasi/' . $setting->logo_koperasi)
                : public_path('images/logo-cahayaos.png')
        );

        $pdf = Pdf::loadView('koperasi.invoice.pdf', [
            'transaksi' => $transaksi,
            'invoice' => $transaksi->invoice,
            'setting' => $setting,
            'logoPath' => $logoPath,
            'ttdPath' => $cetak->untukCetak(
                $setting->ttd_koperasi ? public_path('images/koperasi/' . $setting->ttd_koperasi) : null
            ),
            'stempelPath' => $cetak->untukCetak(
                $setting->stempel_koperasi ? public_path('images/koperasi/' . $setting->stempel_koperasi) : null
            ),
        ])->setPaper('a4', 'portrait');

        return $pdf->download($transaksi->invoice->nomor_invoice . '.pdf');
    }
}
