<?php

namespace Tests\Feature;

use App\Models\BusinessUnit;
use App\Models\Customer;
use App\Models\ProductService;
use App\Models\SalesTransaction;
use App\Models\SalesTransactionItem;
use App\Models\User;
use App\Services\LabaRugiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * HPP Rp0 ADALAH DATA VALID — dari kasus nyata Kerupuk Tuiri
 * (TRX-202609-000188 & TRX-202609-000190): barangnya diperoleh gratis,
 * jadi modalnya memang Rp0.
 *
 * Yang dijaga:
 * - HPP Rp0 diterima tanpa peringatan (peringatan hanya untuk HPP > harga jual).
 * - HPP Rp0 yang diketik eksplisit TIDAK diganti otomatis dengan harga modal
 *   master; yang diisi dari master hanya kolom yang dibiarkan kosong.
 * - Penjualan, HPP Terjual (Rp0), Laba Kotor, Laba Bersih, Produk Terlaris,
 *   Dashboard, Keuangan, dan Laporan menghitungnya apa adanya.
 * - Edit transaksi mempertahankan HPP Rp0.
 * - Keterangan "barang gratis" memakai kolom Catatan item yang sudah ada.
 */
class HppNolBarangGratisTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private BusinessUnit $unit;

    private Customer $pelanggan;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-08-28 09:00', 'Asia/Jakarta'));

        $this->admin = User::create([
            'name' => 'Admin Uji', 'email' => 'admin@hppnol.test',
            'password' => 'password123', 'role' => 'admin', 'is_active' => true,
        ]);
        $this->unit = BusinessUnit::create(['nama' => 'Cahaya Mart', 'kode' => 'CM', 'tipe' => 'Retail', 'status' => 'Aktif']);
        $this->pelanggan = Customer::create(['nama' => 'Warung Bu Sri', 'nomor_hp' => '0813']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function payload(array $items): array
    {
        return [
            'unit_id' => $this->unit->id,
            'tanggal' => '2026-08-27',
            'status_transaksi' => 'Selesai',
            'customer_id' => $this->pelanggan->id,
            'metode_pengiriman' => 'Ambil di Tempat',
            'status_pembayaran' => 'Lunas',
            'metode_pembayaran' => 'Cash',
        ] + $items;
    }

    /** Item manual seperti Kerupuk Tuiri: 3 pcs x Rp12.000, HPP Rp0. */
    private function jualTuiri(int $qty = 3): SalesTransaction
    {
        $this->actingAs($this->admin)
            ->post(route('koperasi.penjualan.store'), $this->payload(['items_manual' => [[
                'nama' => 'Kerupuk Tuiri', 'jenis' => 'Produk', 'qty' => $qty,
                'harga_jual' => 12_000, 'harga_modal' => 0,
                'catatan' => 'Barang diperoleh gratis / HPP Rp0',
            ]]]))
            ->assertSessionHasNoErrors()
            ->assertSessionMissing('warning');

        return SalesTransaction::latest('id')->first();
    }

    public function test_hpp_nol_tidak_memicu_peringatan(): void
    {
        $this->assertNull(SalesTransactionItem::peringatanHpp('Kerupuk Tuiri', 0, 12_000));
        $this->assertNull(SalesTransactionItem::peringatanHpp('Kerupuk Tuiri', 0, 0));

        // Peringatan untuk HPP yang memang tidak wajar tetap berjalan.
        $this->assertNotNull(SalesTransactionItem::peringatanHpp('Paper Bag', 9_500_000, 10_000));
    }

    public function test_item_manual_hpp_nol_tersimpan_apa_adanya_dengan_catatan(): void
    {
        $trx = $this->jualTuiri();
        $item = $trx->items()->first();

        $this->assertEquals(0, (float) $item->harga_modal_snapshot);
        $this->assertEquals(36_000, (float) $item->subtotal);
        $this->assertSame('Barang diperoleh gratis / HPP Rp0', $item->catatan);
        $this->assertEquals(0, $trx->totalHpp());

        $this->actingAs($this->admin)->get(route('koperasi.penjualan.show', $trx))
            ->assertOk()
            ->assertSee('Barang diperoleh gratis / HPP Rp0')
            ->assertDontSee('Periksa kembali nilai modal');
    }

    public function test_hpp_nol_eksplisit_tidak_diganti_harga_modal_master(): void
    {
        $produk = ProductService::create([
            'unit_id' => $this->unit->id, 'nama' => 'Kerupuk Tuiri', 'jenis' => 'Produk',
            'harga_modal' => 9_000, 'harga_jual' => 12_000, 'status' => 'Aktif', 'stock_tracking' => false,
        ]);

        $this->actingAs($this->admin)
            ->post(route('koperasi.penjualan.store'), $this->payload(['items_master' => [[
                'product_id' => $produk->id, 'qty' => 2, 'harga_modal' => '0',
            ]]]))
            ->assertSessionHasNoErrors();

        $this->assertEquals(0, (float) SalesTransaction::latest('id')->first()->items()->first()->harga_modal_snapshot);
    }

    public function test_hpp_nol_masuk_laba_rugi_dashboard_keuangan_dan_laporan(): void
    {
        $this->jualTuiri(3);
        $this->jualTuiri(2);

        $lr = app(LabaRugiService::class)->hitung('2026', '8', null)['total'];
        $this->assertEquals(60_000, $lr['penjualan']);
        $this->assertEquals(0, $lr['hpp']);
        $this->assertEquals(60_000, $lr['laba_kotor']);
        $this->assertEquals(60_000, $lr['laba_bersih']);

        $terlaris = app(LabaRugiService::class)->produkTerlaris('2026', '8');
        $this->assertSame('Kerupuk Tuiri', $terlaris[0]['nama']);
        $this->assertSame(5, $terlaris[0]['qty']);
        $this->assertEquals(0, $terlaris[0]['hpp']);
        $this->assertEquals(60_000, $terlaris[0]['profit']);

        $filter = ['tahun' => '2026', 'bulan' => '8'];

        $stats = $this->actingAs($this->admin)
            ->get(route('dashboard', ['modul' => 'koperasi'] + $filter))->assertOk()->viewData('stats');
        $this->assertEquals(60_000, $stats['penjualan_tahun_ini']);
        $this->assertEquals(0, $stats['hpp_tahun_ini']);
        $this->assertEquals(60_000, $stats['laba_kotor_tahun_ini']);
        $this->assertEquals(60_000, $stats['laba_bersih_tahun_ini']);

        $keuangan = $this->actingAs($this->admin)
            ->get(route('koperasi.keuangan.index', $filter))->assertOk()->viewData('lr')['total'];
        $this->assertEquals($lr, $keuangan);

        $this->actingAs($this->admin)
            ->get(route('koperasi.laporan.laba-rugi', $filter))->assertOk()
            ->assertSee('Rp60.000');
    }

    public function test_edit_transaksi_mempertahankan_hpp_nol(): void
    {
        $trx = $this->jualTuiri(1);

        $this->actingAs($this->admin)
            ->put(route('koperasi.penjualan.update', $trx), $this->payload(['items_manual' => [[
                'nama' => 'Kerupuk Tuiri', 'jenis' => 'Produk', 'qty' => 2,
                'harga_jual' => 12_000, 'harga_modal' => 0,
            ]]]))
            ->assertSessionHasNoErrors()
            ->assertSessionMissing('warning');

        $item = $trx->fresh()->items()->first();
        $this->assertSame(2, (int) $item->qty);
        $this->assertEquals(0, (float) $item->harga_modal_snapshot);
    }
}
