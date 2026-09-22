<?php

namespace Tests\Feature\Uat;

use App\Models\BusinessUnit;
use App\Models\Customer;
use App\Models\Pengeluaran;
use App\Models\ProductService;
use App\Models\SalesTransaction;
use App\Models\User;
use App\Services\LabaRugiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * UAT — PENJUALAN, SNAPSHOT HPP, TITIP JUAL, DAN PROFIT.
 *
 * ==========================================================================
 * YANG DIJAGA DI SINI, DAN KENAPA TIDAK BOLEH LOLOS
 * ==========================================================================
 *
 * 1. MASTER BOLEH BERUBAH, HISTORI TIDAK.
 *    Harga pokok naik dari Rp8.000 ke Rp9.000 adalah kejadian biasa. Yang
 *    TIDAK boleh terjadi adalah laporan bulan lalu ikut berubah karenanya.
 *    Kalau transaksi membaca HPP dari master saat laporan dicetak, seluruh
 *    laba historis koperasi akan bergeser setiap kali harga diperbarui —
 *    diam-diam, dan dengan angka yang tetap terlihat masuk akal.
 *
 * 2. TITIP JUAL BUKAN PENDAPATAN KOPERASI.
 *    Menjual barang titipan Rp500.000 dengan fee 10% berarti koperasi
 *    mendapat Rp50.000, bukan Rp500.000. Membaca gross-nya sebagai pendapatan
 *    akan menggelembungkan omzet koperasi sebesar nilai barang milik orang
 *    lain — kesalahan yang paling sulit terlihat justru karena angkanya besar
 *    dan menyenangkan.
 *
 * 3. HARGA BOLEH DINEGOSIASI, MASTER TIDAK IKUT BERUBAH.
 *    Menjual Rp14.000 untuk satu transaksi tidak boleh mengubah harga jual
 *    produk itu untuk transaksi berikutnya.
 */
class PenjualanDanHppSnapshotTest extends TestCase
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
            'name' => 'Admin', 'email' => 'admin@jual.test',
            'password' => bcrypt('rahasia123'), 'role' => 'admin', 'is_active' => true,
        ]);

        $this->unit = BusinessUnit::create([
            'nama' => 'Cahaya Mart', 'kode' => 'CM', 'tipe' => 'Retail', 'status' => 'Aktif',
        ]);

        $this->produk = ProductService::create([
            'unit_id' => $this->unit->id,
            'kode' => 'KRP-001', 'nama' => 'Keripik Bawang', 'jenis' => 'Produk',
            'harga_modal' => 8_000, 'harga_jual' => 15_000,
            'status' => 'Aktif', 'stock_tracking' => false,
        ]);

        $this->pelanggan = Customer::create(['nama' => 'Warung Bu Sri', 'nomor_hp' => '0812']);
    }

    // ==================================================================
    // A. SNAPSHOT HPP — master berubah, histori tidak
    // ==================================================================

    public function test_histori_transaksi_tidak_berubah_saat_hpp_master_diperbarui(): void
    {
        // Transaksi pertama saat HPP master masih Rp8.000.
        $pertama = $this->jual([['product_id' => $this->produk->id, 'qty' => 10]]);

        $this->assertSame('8000.00', $pertama->items->first()->harga_modal_snapshot);

        // HPP master naik. Ini kejadian biasa dan memang harus diizinkan.
        $this->produk->update(['harga_modal' => 9_000]);

        // Transaksi kedua memakai HPP yang baru.
        $kedua = $this->jual([['product_id' => $this->produk->id, 'qty' => 10]]);

        $this->assertSame('9000.00', $kedua->items->first()->harga_modal_snapshot);

        // INTI UJI INI: transaksi pertama TIDAK ikut berubah.
        $this->assertSame(
            '8000.00',
            $pertama->fresh()->items->first()->harga_modal_snapshot,
            'HPP transaksi lama ikut berubah saat master diperbarui — seluruh laba historis koperasi jadi salah.'
        );

        $this->assertSame(9_000.0, (float) $this->produk->fresh()->harga_modal);
    }

    public function test_laba_kotor_transaksi_lama_tetap_memakai_hpp_lamanya(): void
    {
        // 10 x (15.000 - 8.000) = 70.000 laba kotor.
        $this->jual([['product_id' => $this->produk->id, 'qty' => 10]]);

        $sebelum = app(LabaRugiService::class)->hitung((string) now()->year, 'semua');
        $labaSebelum = $sebelum['total']['laba_kotor'];

        $this->produk->update(['harga_modal' => 14_000]);

        $sesudah = app(LabaRugiService::class)->hitung((string) now()->year, 'semua');

        $this->assertSame(
            $labaSebelum,
            $sesudah['total']['laba_kotor'],
            'Laporan laba rugi periode lama bergeser hanya karena harga pokok master diperbarui.'
        );
        $this->assertSame(70_000.0, (float) $labaSebelum);
    }

    public function test_nama_produk_juga_dibekukan_di_transaksi(): void
    {
        // Kalau nama dibaca dari master, invoice lama akan berubah isinya
        // ketika produk diganti nama — dan invoice yang sudah terkirim ke
        // pelanggan tidak lagi cocok dengan yang ada di sistem.
        $trx = $this->jual([['product_id' => $this->produk->id, 'qty' => 2]]);

        $this->produk->update(['nama' => 'Keripik Bawang Pedas (Kemasan Baru)']);

        $this->assertSame('Keripik Bawang', $trx->fresh()->items->first()->nama_snapshot);
    }

    // ==================================================================
    // B. HARGA NEGOSIASI — tanpa sistem diskon
    // ==================================================================

    public function test_harga_transaksi_boleh_dinegosiasi_tanpa_mengubah_master(): void
    {
        // Master Rp15.000, transaksi ini disepakati Rp14.000.
        $trx = $this->jual([[
            'product_id' => $this->produk->id, 'qty' => 10, 'harga_jual' => 14_000,
        ]]);

        $this->assertSame('14000.00', $trx->items->first()->harga_jual_snapshot);
        $this->assertSame(140_000.0, (float) $trx->items->first()->subtotal);

        // Master TIDAK ikut turun — transaksi berikutnya kembali ke Rp15.000.
        $this->assertSame(15_000.0, (float) $this->produk->fresh()->harga_jual);
    }

    public function test_transaksi_berikutnya_kembali_ke_harga_master(): void
    {
        $this->jual([['product_id' => $this->produk->id, 'qty' => 1, 'harga_jual' => 14_000]]);
        $kedua = $this->jual([['product_id' => $this->produk->id, 'qty' => 1]]);

        $this->assertSame('15000.00', $kedua->items->first()->harga_jual_snapshot);
    }

    // ==================================================================
    // C. BARIS PRODUK DINAMIS
    // ==================================================================

    public function test_satu_transaksi_bisa_memuat_banyak_baris_produk(): void
    {
        $kedua = ProductService::create([
            'unit_id' => $this->unit->id,
            'kode' => 'BRS-001', 'nama' => 'Beras 5kg', 'jenis' => 'Produk',
            'harga_modal' => 55_000, 'harga_jual' => 62_000,
            'status' => 'Aktif', 'stock_tracking' => false,
        ]);

        $trx = $this->jual([
            ['product_id' => $this->produk->id, 'qty' => 3],
            ['product_id' => $kedua->id, 'qty' => 2],
        ]);

        $this->assertCount(2, $trx->items);
        // 3 x 15.000 + 2 x 62.000 = 45.000 + 124.000 = 169.000
        $this->assertSame(169_000.0, (float) $trx->subtotal);
    }

    public function test_baris_kosong_diabaikan_bukan_menjadi_item_nol(): void
    {
        // Form penjualan memakai baris dinamis; baris yang ditambah lalu
        // tidak diisi tidak boleh menjadi item berharga nol di invoice.
        $trx = $this->jual([
            ['product_id' => $this->produk->id, 'qty' => 2],
            ['product_id' => '', 'qty' => 0],
            ['product_id' => $this->produk->id, 'qty' => 0],
        ]);

        $this->assertCount(1, $trx->items);
    }

    public function test_produk_dari_unit_lain_ditolak(): void
    {
        $unitLain = BusinessUnit::create(['nama' => 'Cahaya Sablon', 'kode' => 'CS', 'tipe' => 'Jasa', 'status' => 'Aktif']);
        $produkLain = ProductService::create([
            'unit_id' => $unitLain->id, 'kode' => 'SBL-001', 'nama' => 'Sablon Kaos', 'jenis' => 'Jasa',
            'harga_modal' => 10_000, 'harga_jual' => 25_000, 'status' => 'Aktif', 'stock_tracking' => false,
        ]);

        $this->actingAs($this->admin)
            ->post(route('koperasi.penjualan.store'), $this->payload([
                ['product_id' => $produkLain->id, 'qty' => 1],
            ]))
            ->assertSessionHasErrors('items_master');
    }

    // ==================================================================
    // D. TITIP JUAL / KONSINYASI
    // ==================================================================

    public function test_hak_mitra_dicatat_terpisah_tanpa_persentase_fee(): void
    {
        // 10 x 50.000 = 500.000 nilai penjualan, fee koperasi 10% = 50.000,
        // hak pemilik produk = 450.000.
        $titipan = ProductService::create([
            'unit_id' => $this->unit->id,
            'kode' => 'TTP-001', 'nama' => 'Kerupuk Mitra', 'jenis' => 'Produk',
            'harga_modal' => 0, 'harga_jual' => 50_000,
            'status' => 'Aktif', 'stock_tracking' => false,
        ]);

        $trx = $this->jual([[
            'product_id' => $titipan->id, 'qty' => 10,
            'hak_mitra_per_unit' => 45_000,
        ]]);

        $item = $trx->items->first();

        // Nilai penjualannya utuh, hak mitra dicatat berdampingan, dan tidak
        // ada persentase fee yang ikut bermain.
        $this->assertSame(500_000.0, (float) $item->subtotal);
        $this->assertSame(450_000.0, (float) $item->hak_mitra_amount);
        $this->assertSame(0.0, (float) $item->fee_amount);
        $this->assertSame(0.0, (float) $item->fee_rate);
    }

    /**
     * Laporan menghitung SELURUH penjualan, dan hak mitra tidak terhitung
     * dua kali.
     *
     * Test ini dulu menjaga kebalikannya: item titipan dikeluarkan dari
     * pendapatan koperasi, dan yang masuk hanya fee-nya. Aturan itu diganti.
     *
     * Yang tetap dijaga adalah jaminan yang sesungguhnya: tidak ada uang
     * yang terhitung dua kali. Kontribusi usaha harus SAMA dengan laba kotor
     * — hak mitra tidak boleh ikut dikurangkan lagi setelah modal.
     */
    public function test_laporan_menghitung_seluruh_penjualan_tanpa_menghitung_dua_kali(): void
    {
        $titipan = ProductService::create([
            'unit_id' => $this->unit->id,
            'kode' => 'TTP-002', 'nama' => 'Kerupuk Mitra', 'jenis' => 'Produk',
            'harga_modal' => 0, 'harga_jual' => 50_000,
            'status' => 'Aktif', 'stock_tracking' => false,
        ]);

        $this->jual([[
            'product_id' => $titipan->id, 'qty' => 10,
            'hak_mitra_per_unit' => 45_000,
        ]]);

        $lr = app(LabaRugiService::class)->hitung((string) now()->year, 'semua')['total'];

        // Penjualannya utuh; modalnya 0 karena produk ini memang tidak punya
        // harga modal, jadi laba kotornya penuh.
        $this->assertSame(500_000.0, (float) $lr['penjualan']);
        $this->assertSame(0.0, (float) $lr['hpp']);
        $this->assertSame(500_000.0, (float) $lr['laba_kotor']);

        // Hak mitra tercatat berdampingan, bukan mengurangi.
        $this->assertSame(450_000.0, (float) $lr['hak_mitra']);
        $this->assertSame(0.0, (float) $lr['fee_koperasi']);

        // TIDAK DIHITUNG DUA KALI: kontribusi usaha sama dengan laba kotor.
        $this->assertSame((float) $lr['laba_kotor'], (float) $lr['kontribusi_usaha']);
    }

    /**
     * Item dengan dan tanpa hak mitra dihitung dengan cara yang SAMA.
     *
     * Dulu keduanya diperlakukan berbeda: item titipan dikeluarkan dari
     * penjualan dan HPP. Akibatnya dua barang yang terjual pada harga yang
     * sama bisa menghasilkan angka laporan yang berbeda hanya karena
     * jenisnya dipilih berbeda.
     *
     * Sekarang keduanya masuk penuh; yang membedakan hanya ada atau tidaknya
     * hak mitra, dan itu dicatat di kolomnya sendiri.
     */
    public function test_item_dengan_dan_tanpa_hak_mitra_dihitung_sama(): void
    {
        $titipan = ProductService::create([
            'unit_id' => $this->unit->id,
            'kode' => 'TTP-003', 'nama' => 'Kerupuk Mitra', 'jenis' => 'Produk',
            'harga_modal' => 0, 'harga_jual' => 50_000,
            'status' => 'Aktif', 'stock_tracking' => false,
        ]);

        $this->jual([
            ['product_id' => $this->produk->id, 'qty' => 10],                                  // tanpa hak mitra
            ['product_id' => $titipan->id, 'qty' => 4, 'hak_mitra_per_unit' => 40_000],        // ada hak mitra
        ]);

        $lr = app(LabaRugiService::class)->hitung((string) now()->year, 'semua')['total'];

        // KEDUANYA masuk: (10 x 15.000) + (4 x 50.000) = 350.000.
        $this->assertSame(350_000.0, (float) $lr['penjualan']);

        // HPP keduanya ikut: (10 x 8.000) + (4 x 0) = 80.000.
        $this->assertSame(80_000.0, (float) $lr['hpp']);
        $this->assertSame(270_000.0, (float) $lr['laba_kotor']);

        // Hak mitra hanya dari item kedua: 4 x 40.000.
        $this->assertSame(160_000.0, (float) $lr['hak_mitra']);
        $this->assertSame(0.0, (float) $lr['fee_koperasi']);

        // Tidak dihitung dua kali.
        $this->assertSame((float) $lr['laba_kotor'], (float) $lr['kontribusi_usaha']);
    }

    // ==================================================================
    // E. PROFIT — pendapatan - HPP - pengeluaran
    // ==================================================================

    public function test_rantai_profit_koperasi_dihitung_dari_angka_database(): void
    {
        // Penjualan 10 x 15.000 = 150.000
        // HPP       10 x  8.000 =  80.000
        // Laba kotor            =  70.000
        // Pengeluaran           =  25.000
        // Profit bersih         =  45.000
        $this->jual([['product_id' => $this->produk->id, 'qty' => 10]]);

        Pengeluaran::create([
            'tanggal' => now()->toDateString(),
            'kategori' => 'Operasional',
            'keterangan' => 'Listrik toko',
            'nominal' => 25_000,
            // Pengeluaran memakai status 'Aktif'/'Dibatalkan' (lihat
            // Pengeluaran::scopeAktif) — berbeda dari simpanan yang memakai
            // 'Tercatat'. Memakai nilai yang salah membuat barisnya tersimpan
            // tapi tidak pernah ikut terhitung.
            'status' => 'Aktif',
            'business_unit_id' => $this->unit->id,
        ]);

        $lr = app(LabaRugiService::class)->hitung((string) now()->year, 'semua')['total'];

        $this->assertSame(150_000.0, (float) $lr['penjualan']);
        $this->assertSame(80_000.0, (float) $lr['hpp']);
        $this->assertSame(70_000.0, (float) $lr['laba_kotor']);
        $this->assertSame(25_000.0, (float) $lr['pengeluaran_total']);
        $this->assertSame(45_000.0, (float) $lr['laba_bersih']);
    }

    public function test_transaksi_dibatalkan_tidak_ikut_menghitung_profit(): void
    {
        $trx = $this->jual([['product_id' => $this->produk->id, 'qty' => 10]]);

        $trx->update(['status_transaksi' => 'Dibatalkan']);

        $lr = app(LabaRugiService::class)->hitung((string) now()->year, 'semua')['total'];

        $this->assertSame(0.0, (float) $lr['penjualan']);
        $this->assertSame(0.0, (float) $lr['laba_kotor']);
    }

    // ==================================================================
    // PEMBANTU
    // ==================================================================

    private function jual(array $items): SalesTransaction
    {
        $this->actingAs($this->admin)
            ->post(route('koperasi.penjualan.store'), $this->payload($items))
            ->assertSessionHasNoErrors();

        return SalesTransaction::with('items')->latest('id')->firstOrFail();
    }

    private function payload(array $items): array
    {
        return [
            'unit_id' => $this->unit->id,
            'tanggal' => now()->toDateString(),
            'status_transaksi' => 'Selesai',
            'customer_id' => $this->pelanggan->id,
            'items_master' => $items,
            'metode_pengiriman' => 'Ambil di Tempat',
            'status_pembayaran' => 'Lunas',
            'metode_pembayaran' => 'Cash',
        ];
    }
}
