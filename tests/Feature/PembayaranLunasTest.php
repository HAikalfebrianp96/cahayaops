<?php

namespace Tests\Feature;

use App\Models\BusinessUnit;
use App\Models\SalesTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Penjagaan di SalesTransaction: status "Lunas" WAJIB berarti terbayar penuh.
 *
 * Tanpa ini, transaksi "Lunas" dengan dp_nominal kurang dari total membuat
 * sisaPembayaran() > 0, sehingga INVOICE menampilkan badge "LUNAS" bersamaan
 * dengan "Sisa Pembayaran Rp sekian" — dokumen yang diserahkan ke pelanggan
 * jadi saling bertentangan.
 */
class PembayaranLunasTest extends TestCase
{
    use RefreshDatabase;

    private function unit(): BusinessUnit
    {
        return BusinessUnit::firstOrCreate(
            ['kode' => 'UJI-01'],
            ['nama' => 'Unit Uji', 'tipe' => 'Barang', 'status' => 'Aktif']
        );
    }

    private function transaksi(string $status, float $total, float $dibayar): SalesTransaction
    {
        return SalesTransaction::create([
            'unit_id' => $this->unit()->id,
            'tanggal' => now()->toDateString(),
            'status_transaksi' => 'Selesai',
            'subtotal' => $total,
            'diskon' => 0,
            'ongkir' => 0,
            'total' => $total,
            'status_pembayaran' => $status,
            'dp_nominal' => $dibayar,
        ]);
    }

    public function test_lunas_dengan_nominal_kurang_otomatis_dinaikkan_ke_total(): void
    {
        $trx = $this->transaksi('Lunas', 300000, 0);

        $this->assertSame(300000.0, (float) $trx->dp_nominal);
        $this->assertSame(0.0, $trx->sisaPembayaran(), 'Invoice tidak boleh menagih sisa pada transaksi Lunas');
    }

    public function test_berlaku_juga_saat_status_diubah_jadi_lunas(): void
    {
        $trx = $this->transaksi('Belum Dibayar', 500000, 0);
        $this->assertSame(0.0, (float) $trx->dp_nominal);

        $trx->update(['status_pembayaran' => 'Lunas']);

        $this->assertSame(500000.0, (float) $trx->fresh()->dp_nominal);
        $this->assertSame(0.0, $trx->fresh()->sisaPembayaran());
    }

    public function test_status_dp_tidak_disentuh(): void
    {
        $trx = $this->transaksi('DP', 400000, 100000);

        $this->assertSame(100000.0, (float) $trx->dp_nominal, 'Nominal DP tidak boleh diubah');
        $this->assertSame(300000.0, $trx->sisaPembayaran());
    }

    public function test_status_belum_dibayar_tidak_disentuh(): void
    {
        $trx = $this->transaksi('Belum Dibayar', 250000, 0);

        $this->assertSame(0.0, (float) $trx->dp_nominal);
        $this->assertSame(250000.0, $trx->sisaPembayaran());
    }

    /**
     * Penjagaan ini hanya MENAIKKAN yang kurang. Kalau nominal tercatat
     * melebihi total (kelebihan bayar), catatannya dibiarkan utuh —
     * jangan sampai penjagaan malah menghapus bukti pembayaran.
     */
    public function test_kelebihan_bayar_tidak_dipangkas(): void
    {
        $trx = $this->transaksi('Lunas', 200000, 250000);

        $this->assertSame(250000.0, (float) $trx->dp_nominal);
        $this->assertSame(0.0, $trx->sisaPembayaran());
    }

    /** Nominal yang sudah pas tidak diubah-ubah. */
    public function test_lunas_dengan_nominal_pas_tetap_sama(): void
    {
        $trx = $this->transaksi('Lunas', 750000, 750000);

        $this->assertSame(750000.0, (float) $trx->dp_nominal);
        $this->assertSame(0.0, $trx->sisaPembayaran());
    }

    /**
     * Tidak mungkin lagi menyimpan transaksi Lunas yang bertentangan,
     * lewat jalur Eloquent mana pun.
     */
    public function test_tidak_ada_transaksi_lunas_yang_bertentangan(): void
    {
        $this->transaksi('Lunas', 300000, 0);
        $this->transaksi('Lunas', 450002, 1000);
        $this->transaksi('DP', 400000, 100000);

        $bertentangan = SalesTransaction::where('status_pembayaran', 'Lunas')
            ->whereColumn('dp_nominal', '<', 'total')
            ->count();

        $this->assertSame(0, $bertentangan);
    }
}
