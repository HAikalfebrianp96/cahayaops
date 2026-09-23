<?php

namespace Tests\Feature;

use App\Models\BusinessUnit;
use App\Models\Customer;
use App\Models\ProductService;
use App\Models\SalesTransaction;
use App\Models\User;
use App\Services\LabaRugiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * DISKON TRANSAKSI mengurangi Penjualan.
 *
 * Sebelumnya LabaRugiService menjumlah subtotal item saja, sehingga diskon
 * di tingkat transaksi (kolom `diskon`) tidak pernah mengurangi Penjualan
 * maupun Laba — padahal pelanggan membayar lebih sedikit. Ongkir BUKAN
 * penjualan: tidak menambah Penjualan, baik ditanggung pelanggan maupun
 * koperasi.
 */
class DiskonTransaksiLabaRugiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private BusinessUnit $unit;

    private ProductService $produk;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-07-20 09:00', 'Asia/Jakarta'));

        $this->admin = User::create([
            'name' => 'Admin Uji', 'email' => 'admin@diskon.test',
            'password' => 'password123', 'role' => 'admin', 'is_active' => true,
        ]);
        $this->unit = BusinessUnit::create(['nama' => 'Cahaya Mart', 'kode' => 'CM', 'tipe' => 'Retail', 'status' => 'Aktif']);
        $this->produk = ProductService::create([
            'unit_id' => $this->unit->id, 'nama' => 'Keripik', 'jenis' => 'Produk',
            'harga_modal' => 7_000, 'harga_jual' => 10_000, 'status' => 'Aktif', 'stock_tracking' => false,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** 10 x Rp10.000 (HPP 10 x Rp7.000), dengan diskon & ongkir transaksi. */
    private function jual(int $diskon, int $ongkir = 0, string $ditanggung = 'Pelanggan'): SalesTransaction
    {
        $this->actingAs($this->admin)
            ->post(route('koperasi.penjualan.store'), [
                'unit_id' => $this->unit->id,
                'tanggal' => '2026-07-10',
                'status_transaksi' => 'Selesai',
                'customer_id' => Customer::create(['nama' => 'Pelanggan ' . uniqid(), 'nomor_hp' => '0813'])->id,
                'items_master' => [['product_id' => $this->produk->id, 'qty' => 10]],
                'diskon' => $diskon,
                'ongkir' => $ongkir,
                'ongkir_ditanggung' => $ditanggung,
                'metode_pengiriman' => 'Ambil di Tempat',
                'status_pembayaran' => 'Lunas',
                'metode_pembayaran' => 'Cash',
            ])
            ->assertSessionHasNoErrors();

        return SalesTransaction::latest('id')->first();
    }

    public function test_diskon_transaksi_mengurangi_penjualan_dan_laba(): void
    {
        $trx = $this->jual(diskon: 5_000);

        $t = app(LabaRugiService::class)->hitung('2026', '7')['total'];

        $this->assertEquals(95_000, $t['penjualan']);           // 100.000 - 5.000
        $this->assertEquals(70_000, $t['hpp']);
        $this->assertEquals(25_000, $t['laba_kotor']);
        $this->assertEquals(25_000, $t['laba_bersih']);

        // Penjualan = uang yang ditagih ke pelanggan (tanpa ongkir).
        $this->assertEquals((float) $trx->total, $t['penjualan']);

        // Estimasi Profit per transaksi = Laba Kotor LabaRugiService.
        $this->assertEquals($t['laba_kotor'], $trx->fresh()->load('items')->estimasiProfit());

        // Dashboard & Keuangan membaca angka yang sama.
        $stats = $this->actingAs($this->admin)
            ->get(route('dashboard', ['modul' => 'koperasi', 'tahun' => '2026', 'bulan' => '7']))->viewData('stats');
        $this->assertEquals(95_000, $stats['penjualan_tahun_ini']);
        $this->assertEquals(25_000, $stats['laba_bersih_tahun_ini']);
    }

    public function test_ongkir_bukan_penjualan(): void
    {
        $this->jual(diskon: 0, ongkir: 15_000, ditanggung: 'Pelanggan');
        $this->jual(diskon: 0, ongkir: 20_000, ditanggung: 'Koperasi');

        $t = app(LabaRugiService::class)->hitung('2026', '7')['total'];

        $this->assertEquals(200_000, $t['penjualan']);
        $this->assertEquals(60_000, $t['laba_kotor']);
    }

    public function test_tren_bulanan_ikut_mengurangi_diskon(): void
    {
        $this->jual(diskon: 5_000);
        $this->jual(diskon: 2_500);

        $lr = app(LabaRugiService::class);
        $tren = $lr->penjualanPerBulan(2026);

        $this->assertEquals(192_500, $tren[7]);
        $this->assertEquals($lr->hitung('2026', 'semua')['total']['penjualan'], array_sum($tren));
    }

    public function test_laporan_penjualan_menyebut_total_tagihan(): void
    {
        $this->jual(diskon: 5_000, ongkir: 15_000);

        $this->actingAs($this->admin)
            ->get(route('koperasi.laporan.penjualan', ['tahun' => '2026', 'bulan' => '7']))
            ->assertOk()
            // Tagihan = 100.000 - 5.000 + 15.000 ongkir pelanggan.
            ->assertSeeInOrder(['Total Tagihan', 'Rp110.000']);
    }
}
