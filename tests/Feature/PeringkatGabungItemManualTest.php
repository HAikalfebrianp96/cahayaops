<?php

namespace Tests\Feature;

use App\Models\BusinessUnit;
use App\Models\Customer;
use App\Models\ProductService;
use App\Models\SalesTransactionItem;
use App\Models\User;
use App\Services\LabaRugiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Kasus nyata Kerupuk Tuiri: dua penjualan awal diinput sebagai ITEM MANUAL
 * (HPP Rp0, barang gratis), lalu produknya didaftarkan sebagai master.
 * Peringkat Produk Terlaris & Penjualan Terbesar harus menampilkannya
 * sebagai SATU produk — tanpa mengubah data transaksi.
 */
class PeringkatGabungItemManualTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private BusinessUnit $mart;

    private Customer $pelanggan;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-09-20 09:00', 'Asia/Jakarta'));

        $this->admin = User::create([
            'name' => 'Admin Uji', 'email' => 'admin@gabung.test',
            'password' => 'password123', 'role' => 'admin', 'is_active' => true,
        ]);
        $this->mart = BusinessUnit::create(['nama' => 'Cahaya Mart', 'kode' => 'CM', 'tipe' => 'Retail', 'status' => 'Aktif']);
        $this->pelanggan = Customer::create(['nama' => 'Warung Bu Sri', 'nomor_hp' => '0813']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function simpan(BusinessUnit $unit, array $items): void
    {
        $this->actingAs($this->admin)
            ->post(route('koperasi.penjualan.store'), [
                'unit_id' => $unit->id,
                'tanggal' => '2026-09-01',
                'status_transaksi' => 'Selesai',
                'customer_id' => $this->pelanggan->id,
                'metode_pengiriman' => 'Ambil di Tempat',
                'status_pembayaran' => 'Lunas',
                'metode_pembayaran' => 'Cash',
            ] + $items)
            ->assertSessionHasNoErrors();
    }

    private function manual(BusinessUnit $unit, string $nama, int $qty, int $jual = 12_000, int $modal = 0): void
    {
        $this->simpan($unit, ['items_manual' => [[
            'nama' => $nama, 'jenis' => 'Produk', 'qty' => $qty, 'harga_jual' => $jual, 'harga_modal' => $modal,
        ]]]);
    }

    private function master(BusinessUnit $unit, string $nama, int $modal = 9_000, int $jual = 12_000): ProductService
    {
        return ProductService::create([
            'unit_id' => $unit->id, 'nama' => $nama, 'jenis' => 'Produk',
            'harga_modal' => $modal, 'harga_jual' => $jual, 'status' => 'Aktif', 'stock_tracking' => true,
        ]);
    }

    public function test_item_manual_digabung_ke_produk_master_bernama_sama(): void
    {
        $this->manual($this->mart, 'Kerupuk Tuiri', 3);          // manual, gratis
        $this->manual($this->mart, ' kerupuk tuiri ', 2);        // beda huruf & spasi
        $tuiri = $this->master($this->mart, 'Kerupuk Tuiri');
        $this->simpan($this->mart, ['items_master' => [['product_id' => $tuiri->id, 'qty' => 4]]]);

        // Produk lain supaya urutannya teruji.
        $this->manual($this->mart, 'Keripik Pisang', 6, 10_000, 8_000);

        $lr = app(LabaRugiService::class);
        $terlaris = $lr->produkTerlaris('2026', 'semua');

        $this->assertSame(['Kerupuk Tuiri', 'Keripik Pisang'], array_column($terlaris, 'nama'));
        $this->assertSame(9, $terlaris[0]['qty']);                        // 3 + 2 + 4
        $this->assertEquals(9 * 12_000, $terlaris[0]['omzet']);
        $this->assertEquals(4 * 9_000, $terlaris[0]['hpp']);              // yang gratis tetap Rp0

        $terbesar = $lr->penjualanTerbesar('2026', 'semua');
        $this->assertCount(1, array_keys(array_column($terbesar, 'nama'), 'Kerupuk Tuiri'));

        // Data transaksi TIDAK diubah: item manual tetap tanpa produk master.
        $this->assertSame(2, SalesTransactionItem::whereNull('product_service_id')
            ->where('nama_snapshot', 'like', '%uiri%')->count());

        // Total peringkat tetap = Total Penjualan KPI.
        $this->assertEquals($lr->hitung('2026', 'semua')['total']['penjualan'], array_sum(array_column($lr->penjualanTerbesar('2026', 'semua', null, 50), 'omzet')));
    }

    public function test_nama_ambigu_tidak_digabung(): void
    {
        $sembako = BusinessUnit::create(['nama' => 'Cahaya Sembako', 'kode' => 'CS', 'tipe' => 'Retail', 'status' => 'Aktif']);
        $this->master($this->mart, 'Gula Aren');
        $this->master($sembako, 'Gula Aren');                    // nama sama, unit lain

        $this->manual($this->mart, 'Gula Aren', 5);

        $baris = app(LabaRugiService::class)->produkTerlaris('2026', 'semua');

        // Tetap satu baris manual; tidak dipaksakan ke salah satu master.
        $this->assertCount(1, $baris);
        $this->assertSame('Gula Aren', $baris[0]['nama']);
        $this->assertSame(5, $baris[0]['qty']);
    }

    public function test_tanpa_master_bernama_sama_tetap_per_nama(): void
    {
        $this->manual($this->mart, 'Kerupuk Tuiri', 3);
        $this->manual($this->mart, 'KERUPUK TUIRI', 2);

        $baris = app(LabaRugiService::class)->produkTerlaris('2026', 'semua');

        $this->assertCount(1, $baris);
        $this->assertSame(5, $baris[0]['qty']);
    }
}
