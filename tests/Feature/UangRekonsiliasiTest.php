<?php

namespace Tests\Feature;

use App\Models\BusinessUnit;
use App\Models\Customer;
use App\Models\ProductService;
use App\Models\SalesTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * UANG PENJUALAN HARUS SELALU BERJUMLAH COCOK.
 *
 * =====================================================================
 * KENAPA DIKUNCI SEKARANG
 * =====================================================================
 *
 * Pembagian SHU ternyata tidak berjumlah utuh karena tiap bagian dibulatkan
 * sendiri-sendiri (diperbaiki terpisah). Jalur uang penjualan diperiksa
 * dengan cara yang sama dan hasilnya BERSIH — tapi bersih karena sengaja
 * ditulis benar, bukan karena kebetulan:
 *
 *   hak_mitra = subtotal - fee, BUKAN dihitung sendiri dari fee_rate.
 *
 * Sisa pembulatan karena itu selalu jatuh ke mitra, dan jumlahnya tidak
 * mungkin meleset. Pola itu mudah sekali "diperbaiki" jadi salah oleh orang
 * yang merasa lebih rapi menghitung keduanya dari fee_rate — dan begitu itu
 * terjadi, fee + hak mitra tidak lagi sama dengan subtotal.
 *
 * Test ini menjaga hasilnya, bukan cara menulisnya.
 */
class UangRekonsiliasiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private BusinessUnit $unit;
    private ProductService $produk;
    private Customer $pelanggan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin Uji', 'email' => 'admin@uang.test',
            'password' => 'password123', 'role' => 'admin', 'is_active' => true,
        ]);

        $this->unit = BusinessUnit::create([
            'nama' => 'Cahaya Sembako', 'kode' => 'CS',
            'tipe' => 'Retail', 'status' => 'Aktif',
        ]);

        $this->produk = ProductService::create([
            'unit_id' => $this->unit->id, 'kode' => 'BRS-001', 'nama' => 'Beras 5kg',
            'jenis' => 'Produk', 'harga_modal' => 55000, 'harga_jual' => 62000,
            'status' => 'Aktif', 'stock_tracking' => false,
        ]);

        $this->pelanggan = Customer::create(['nama' => 'Warung Bu Sri', 'nomor_hp' => '0813']);
    }

    private function simpan(array $ganti = []): SalesTransaction
    {
        $this->actingAs($this->admin)
            ->post(route('koperasi.penjualan.store'), array_merge([
                'unit_id' => $this->unit->id,
                'tanggal' => now()->toDateString(),
                'status_transaksi' => 'Selesai',
                'customer_id' => $this->pelanggan->id,
                'items_master' => [['product_id' => $this->produk->id, 'qty' => 3]],
                'metode_pengiriman' => 'Ambil di Tempat',
                'status_pembayaran' => 'Lunas',
                'metode_pembayaran' => 'Cash',
            ], $ganti))
            ->assertSessionHasNoErrors();

        return SalesTransaction::with('items')->latest('id')->firstOrFail();
    }

    // =================================================================
    // TOTAL TRANSAKSI
    // =================================================================

    public function test_subtotal_sama_dengan_jumlah_seluruh_item(): void
    {
        $trx = $this->simpan();

        $this->assertSame(
            (float) $trx->items->sum('subtotal'),
            (float) $trx->subtotal,
            'Subtotal transaksi harus sama dengan jumlah subtotal itemnya.'
        );
    }

    public function test_total_sama_dengan_subtotal_dikurangi_diskon_ditambah_ongkir(): void
    {
        $trx = $this->simpan([
            'diskon' => 17777,
            'ongkir' => 13333,
            'ongkir_ditanggung' => 'Pelanggan',
        ]);

        $this->assertSame(
            (float) $trx->subtotal - (float) $trx->diskon + (float) $trx->ongkir,
            (float) $trx->total
        );
    }

    /**
     * Ongkir yang ditanggung koperasi TETAP tercatat, tapi tidak menagih
     * pelanggan. Nilainya tidak boleh hilang dari kolomnya hanya karena
     * tidak ikut ke total.
     */
    public function test_ongkir_ditanggung_koperasi_tercatat_tapi_tidak_menagih_pelanggan(): void
    {
        $trx = $this->simpan([
            'ongkir' => 25000,
            'ongkir_ditanggung' => 'Koperasi',
        ]);

        $this->assertSame(25000.0, (float) $trx->ongkir);
        $this->assertSame((float) $trx->subtotal, (float) $trx->total);
    }

    /**
     * Diskon yang melebihi subtotal tidak boleh membuat tagihan negatif.
     */
    public function test_diskon_berlebih_tidak_membuat_total_negatif(): void
    {
        $trx = $this->simpan(['diskon' => 999999999]);

        $this->assertGreaterThanOrEqual(0, (float) $trx->total);
        $this->assertSame((float) $trx->subtotal, (float) $trx->diskon);
    }

    // =================================================================
    // TITIP JUAL
    // =================================================================

    /**
     * Fee koperasi + hak mitra harus SAMA PERSIS dengan subtotal item.
     *
     * Persentase sengaja dipilih yang tidak habis dibagi: 33,33% dan 7,77%
     * dari angka ganjil adalah bentuk yang paling mungkin memunculkan sisa.
     */
    public function test_fee_dan_hak_mitra_selalu_berjumlah_subtotal(): void
    {
        foreach ([33.33, 7.77, 12.5, 66.67, 100, 0.01] as $rate) {
            $trx = $this->simpan([
                'items_master' => [[
                    'product_id' => $this->produk->id,
                    'qty' => 3,
                    'harga_jual' => 33333,
                    'tipe_kepemilikan' => 'Titip Jual',
                    'fee_rate' => $rate,
                ]],
            ]);

            $item = $trx->items->first();

            $this->assertSame(
                (float) $item->subtotal,
                (float) $item->fee_amount + (float) $item->hak_mitra_amount,
                "Fee {$rate}% + hak mitra tidak berjumlah subtotal."
            );

            $this->assertGreaterThanOrEqual(0, (float) $item->hak_mitra_amount);
            $this->assertGreaterThanOrEqual(0, (float) $item->fee_amount);
        }
    }

    /**
     * Item milik koperasi tidak boleh punya fee maupun hak mitra.
     */
    public function test_item_milik_koperasi_tidak_punya_fee_maupun_hak_mitra(): void
    {
        $trx = $this->simpan([
            'items_master' => [[
                'product_id' => $this->produk->id,
                'qty' => 2,
                'tipe_kepemilikan' => 'Milik Koperasi',
                'fee_rate' => 20,
            ]],
        ]);

        $item = $trx->items->first();

        $this->assertSame(0.0, (float) $item->fee_amount);
        $this->assertSame(0.0, (float) $item->hak_mitra_amount);
        $this->assertSame(0.0, (float) $item->fee_rate);
    }
}
