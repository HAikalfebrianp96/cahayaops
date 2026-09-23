<?php

namespace App\Http\Controllers;

use App\Models\AkunKas;
use App\Models\PembayaranPenjualan;
use App\Models\SalesTransaction;
use App\Services\PembayaranPenjualanService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * TERIMA PEMBAYARAN & BATALKAN PEMBAYARAN dari halaman detail penjualan.
 *
 * Inilah jalan untuk DP susulan, cicilan, dan pelunasan piutang — masing-
 * masing dengan tanggal dan akunnya sendiri. Sebelumnya pelunasan hanya bisa
 * dicatat dengan mengedit transaksi, dan tanggal uang masuknya hilang.
 *
 * Hak aksesnya sama dengan mengelola transaksi (bolehKelolaKoperasi).
 */
class PembayaranPenjualanController extends Controller
{
    public function store(Request $request, SalesTransaction $transaksi, PembayaranPenjualanService $layanan)
    {
        abort_unless(Auth::user()->bolehKelolaKoperasi(), 403);
        abort_if(
            $transaksi->status_transaksi === SalesTransaction::STATUS_DIBATALKAN,
            403,
            'Transaksi yang dibatalkan tidak bisa menerima pembayaran.'
        );

        $sisa = $transaksi->sisaPembayaran();

        $validated = $request->validateWithBag('pembayaran', [
            'tanggal' => ['required', 'date', 'before_or_equal:today'],
            'nominal' => ['required', 'numeric', 'min:1', 'max:' . $sisa],
            'akun_kas_id' => ['required', Rule::exists('akun_kas', 'id')->where('status', AkunKas::STATUS_AKTIF)],
            'metode' => ['required', Rule::in(SalesTransaction::METODE_PEMBAYARAN_OPTIONS)],
            'catatan' => ['nullable', 'string', 'max:1000'],
        ], [
            'nominal.max' => 'Nominal melebihi sisa tagihan (' . rupiah($sisa) . '). Uang di atas sisa tagihan adalah kembalian.',
            'tanggal.before_or_equal' => 'Tanggal pembayaran tidak boleh di masa depan.',
            'akun_kas_id.required' => 'Pilih akun tempat uang ini diterima.',
        ]);

        DB::transaction(fn () => $layanan->terima(
            $transaksi,
            $validated['tanggal'],
            (float) $validated['nominal'],
            (int) $validated['akun_kas_id'],
            $validated['metode'],
            $validated['catatan'] ?? null,
        ));

        $transaksi->refresh();

        return redirect()
            ->route('koperasi.penjualan.show', $transaksi)
            ->with('success', 'Pembayaran ' . rupiah($validated['nominal']) . ' dicatat. Status pembayaran sekarang: '
                . $transaksi->status_pembayaran . '.');
    }

    public function batalkan(Request $request, PembayaranPenjualan $pembayaran, PembayaranPenjualanService $layanan)
    {
        abort_unless(Auth::user()->bolehKelolaKoperasi(), 403);
        abort_if($pembayaran->dibatalkan(), 403, 'Pembayaran ini sudah dibatalkan.');

        $transaksi = $pembayaran->transaksi;
        abort_if(
            $transaksi->status_transaksi === SalesTransaction::STATUS_DIBATALKAN,
            403,
            'Pulihkan transaksinya dulu sebelum mengoreksi pembayarannya.'
        );

        $validated = $request->validateWithBag('batalPembayaran', [
            'alasan_pembatalan' => ['required', 'string', 'max:1000'],
        ], [
            'alasan_pembatalan.required' => 'Alasan pembatalan pembayaran wajib diisi.',
        ]);

        DB::transaction(fn () => $layanan->batalkan($pembayaran, $validated['alasan_pembatalan']));

        return redirect()
            ->route('koperasi.penjualan.show', $transaksi)
            ->with('success', 'Pembayaran ' . rupiah($pembayaran->nominal) . ' dibatalkan. Barisnya tetap tersimpan sebagai jejak.');
    }
}
