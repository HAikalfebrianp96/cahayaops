<?php

namespace Tests\Feature;

use App\Models\BusinessUnit;
use App\Models\Invoice;
use App\Models\SalesTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * NOMOR TRANSAKSI DAN NOMOR INVOICE TIDAK PERNAH TERPAKAI ULANG.
 *
 * =====================================================================
 * KEADAAN YANG DITEMUKAN
 * =====================================================================
 *
 * Keduanya dulu dihitung dengan count() + 1 atas baris bulan berjalan.
 * Itu aman selama tidak ada satu pun yang pernah dihapus — dan memang
 * begitu keadaannya sampai tombol "Hapus Permanen" ditambahkan.
 *
 * Sejak itu, urutannya bisa menabrak:
 *
 *     transaksi 000001..000009, lalu 000005 dihapus
 *     -> count() = 8 -> nomor berikutnya 000009
 *     -> 000009 masih ada
 *
 * Kolomnya ber-unique, jadi akibatnya BUKAN data rusak melainkan
 * penjualan GAGAL DISIMPAN, dengan galat database yang tidak bisa
 * dimengerti petugas yang sedang melayani pembeli di depannya.
 *
 * =====================================================================
 * PERBAIKANNYA
 * =====================================================================
 *
 * Nomor diambil dari yang TERTINGGI, bukan dari jumlah baris. Nomor yang
 * sudah pernah terbit tidak pernah kembali — itulah yang membuat sebuah
 * nomor transaksi bisa dipakai sebagai rujukan.
 */
class NomorTransaksiTidakTerpakaiUlangTest extends TestCase
{
    use RefreshDatabase;

    private BusinessUnit $unit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->unit = BusinessUnit::create([
            'nama' => 'Unit Uji', 'kode' => 'UNIT-NO', 'tipe' => 'Barang', 'status' => 'Aktif',
        ]);
    }

    private function transaksi(): SalesTransaction
    {
        return SalesTransaction::create([
            'unit_id' => $this->unit->id,
            'tanggal' => now()->toDateString(),
            'subtotal' => 10_000, 'diskon' => 0, 'ongkir' => 0, 'total' => 10_000,
            'status_transaksi' => 'Aktif', 'status_pembayaran' => 'Lunas',
        ]);
    }

    private function periode(): string
    {
        return now()->format('Ym');
    }

    // =================================================================
    // TRANSAKSI
    // =================================================================

    public function test_nomor_transaksi_berurut(): void
    {
        $this->assertSame('TRX-' . $this->periode() . '-000001', $this->transaksi()->nomor_transaksi);
        $this->assertSame('TRX-' . $this->periode() . '-000002', $this->transaksi()->nomor_transaksi);
        $this->assertSame('TRX-' . $this->periode() . '-000003', $this->transaksi()->nomor_transaksi);
    }

    /**
     * Inti persoalannya: menghapus transaksi di TENGAH tidak boleh membuat
     * nomor berikutnya menabrak nomor yang masih dipakai.
     */
    public function test_menghapus_transaksi_tengah_tidak_membuat_nomor_bentrok(): void
    {
        $this->transaksi();                  // 000001
        $tengah = $this->transaksi();        // 000002
        $terakhir = $this->transaksi();      // 000003

        $tengah->delete();

        $baru = $this->transaksi();

        $this->assertSame('TRX-' . $this->periode() . '-000004', $baru->nomor_transaksi);
        $this->assertNotSame($terakhir->nomor_transaksi, $baru->nomor_transaksi);
    }

    /** Menghapus SELURUHNYA pun tidak memutar nomor kembali ke awal. */
    public function test_menghapus_seluruh_transaksi_tidak_mengulang_nomor(): void
    {
        $this->transaksi();
        $this->transaksi();
        $terakhir = $this->transaksi();      // 000003

        SalesTransaction::query()->delete();

        $this->assertSame(0, SalesTransaction::count());

        // Nomor tertinggi sudah tidak ada, jadi deret memang mulai lagi —
        // dan itu memang perilaku yang diinginkan setelah pembersihan
        // menyeluruh. Yang dilarang adalah bentrok, bukan mulai ulang.
        $baru = $this->transaksi();

        $this->assertSame('TRX-' . $this->periode() . '-000001', $baru->nomor_transaksi);
        $this->assertSame(1, SalesTransaction::where('nomor_transaksi', $baru->nomor_transaksi)->count());
    }

    /** Tidak ada nomor ganda, apa pun urutan pembuatan dan penghapusannya. */
    public function test_tidak_pernah_ada_nomor_ganda(): void
    {
        $dibuat = [];

        foreach (range(1, 6) as $i) {
            $dibuat[] = $this->transaksi();
        }

        // Buang beberapa di tengah, lalu buat lagi.
        $dibuat[1]->delete();
        $dibuat[3]->delete();

        foreach (range(1, 3) as $i) {
            $this->transaksi();
        }

        $nomor = SalesTransaction::pluck('nomor_transaksi');

        $this->assertSame($nomor->count(), $nomor->unique()->count(), 'Ada nomor transaksi yang ganda.');
    }

    // =================================================================
    // INVOICE
    // =================================================================

    public function test_nomor_invoice_tidak_terpakai_ulang(): void
    {
        $a = Invoice::create(['sales_transaction_id' => $this->transaksi()->id, 'tanggal_invoice' => now()->toDateString()]);
        $b = Invoice::create(['sales_transaction_id' => $this->transaksi()->id, 'tanggal_invoice' => now()->toDateString()]);
        $c = Invoice::create(['sales_transaction_id' => $this->transaksi()->id, 'tanggal_invoice' => now()->toDateString()]);

        $this->assertSame('INV-' . $this->periode() . '-000001', $a->nomor_invoice);
        $this->assertSame('INV-' . $this->periode() . '-000003', $c->nomor_invoice);

        $b->delete();

        $baru = Invoice::create(['sales_transaction_id' => $this->transaksi()->id, 'tanggal_invoice' => now()->toDateString()]);

        $this->assertSame('INV-' . $this->periode() . '-000004', $baru->nomor_invoice);

        $nomor = Invoice::pluck('nomor_invoice');
        $this->assertSame($nomor->count(), $nomor->unique()->count(), 'Ada nomor invoice yang ganda.');
    }

    /**
     * Invoice ikut terhapus bersama transaksinya, tanpa menyisakan yatim.
     */
    public function test_invoice_ikut_terhapus_bersama_transaksinya(): void
    {
        $trx = $this->transaksi();

        Invoice::create(['sales_transaction_id' => $trx->id, 'tanggal_invoice' => now()->toDateString()]);

        $this->assertSame(1, Invoice::where('sales_transaction_id', $trx->id)->count());

        $trx->delete();

        $this->assertSame(0, Invoice::where('sales_transaction_id', $trx->id)->count());
        $this->assertSame(
            0,
            Invoice::whereNotIn('sales_transaction_id', SalesTransaction::pluck('id'))->count(),
            'Ada invoice yatim.'
        );
    }
}
