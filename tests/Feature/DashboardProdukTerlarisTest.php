<?php

namespace Tests\Feature;

use App\Models\BusinessUnit;
use App\Models\Customer;
use App\Models\ProductService;
use App\Models\SalesTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * DASHBOARD KOPERASI — PRODUK TERLARIS.
 *
 * Peringkat menurut JUMLAH UNIT TERJUAL (qty item), dari
 * LabaRugiService::produkTerlaris() dengan filter Tahun/Bulan/Unit yang
 * sama dengan keenam KPI. Draft & Dibatalkan tidak dihitung.
 */
class DashboardProdukTerlarisTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private BusinessUnit $unitA;
    private BusinessUnit $unitB;
    private Customer $pelanggan;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-07-20 09:00', 'Asia/Jakarta'));

        $this->admin = User::create([
            'name' => 'Admin Uji', 'email' => 'admin@terlaris.test',
            'password' => 'password123', 'role' => 'admin', 'is_active' => true,
        ]);

        $this->unitA = BusinessUnit::create(['nama' => 'Unit A', 'kode' => 'UA', 'tipe' => 'Retail', 'status' => 'Aktif']);
        $this->unitB = BusinessUnit::create(['nama' => 'Unit B', 'kode' => 'UB', 'tipe' => 'Retail', 'status' => 'Aktif']);
        $this->pelanggan = Customer::create(['nama' => 'Warung Bu Sri', 'nomor_hp' => '0813']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function produk(BusinessUnit $unit, string $nama, int $jual = 10_000, int $modal = 7_000): ProductService
    {
        return ProductService::create([
            'unit_id' => $unit->id, 'nama' => $nama,
            'jenis' => 'Produk', 'harga_modal' => $modal, 'harga_jual' => $jual,
            'status' => 'Aktif', 'stock_tracking' => false,
        ]);
    }

    private function jual(ProductService $produk, string $tanggal, int $qty): SalesTransaction
    {
        $this->actingAs($this->admin)
            ->post(route('koperasi.penjualan.store'), [
                'unit_id' => $produk->unit_id,
                'tanggal' => $tanggal,
                'status_transaksi' => 'Selesai',
                'customer_id' => $this->pelanggan->id,
                'items_master' => [['product_id' => $produk->id, 'qty' => $qty, 'hak_mitra_per_unit' => 0]],
                'metode_pengiriman' => 'Ambil di Tempat',
                'status_pembayaran' => 'Lunas',
                'nominal_dibayar' => 0,
                'metode_pembayaran' => 'Cash',
            ])
            ->assertSessionHasNoErrors();

        return SalesTransaction::latest('id')->first();
    }

    private function terlaris(array $filter = []): array
    {
        return $this->actingAs($this->admin)
            ->get(route('dashboard', ['modul' => 'koperasi'] + $filter))
            ->assertOk()
            ->viewData('produkTerlaris');
    }

    public function test_urut_menurut_qty_terbanyak_dan_qty_dijumlahkan_lintas_transaksi(): void
    {
        $kopi = $this->produk($this->unitA, 'Kopi', 20_000, 12_000);
        $teh = $this->produk($this->unitA, 'Teh');
        $gula = $this->produk($this->unitB, 'Gula');

        $this->jual($kopi, '2026-07-01', 3);
        $this->jual($kopi, '2026-07-05', 4);   // Kopi total 7
        $this->jual($teh, '2026-07-02', 10);   // Teh 10
        $this->jual($gula, '2026-07-03', 5);   // Gula 5

        $hasil = $this->terlaris();

        $this->assertSame(['Teh', 'Kopi', 'Gula'], array_column($hasil, 'nama'));
        $this->assertSame([10, 7, 5], array_column($hasil, 'qty'));

        // Omzet, HPP & profit ikut dijumlahkan dari snapshot item.
        $this->assertEquals(140_000, $hasil[1]['omzet']);
        $this->assertEquals(84_000, $hasil[1]['hpp']);
        $this->assertEquals(56_000, $hasil[1]['profit']);
    }

    public function test_hanya_lima_teratas(): void
    {
        foreach (range(1, 7) as $n) {
            $this->jual($this->produk($this->unitA, 'Produk ' . $n), '2026-07-01', $n);
        }

        $hasil = $this->terlaris();

        $this->assertCount(5, $hasil);
        $this->assertSame([7, 6, 5, 4, 3], array_column($hasil, 'qty'));
    }

    public function test_transaksi_dibatalkan_tidak_dihitung(): void
    {
        $kopi = $this->produk($this->unitA, 'Kopi');
        $teh = $this->produk($this->unitA, 'Teh');

        $this->jual($kopi, '2026-07-01', 2);
        $batal = $this->jual($teh, '2026-07-01', 50);

        $this->actingAs($this->admin)
            ->post(route('koperasi.penjualan.batalkan', $batal), ['alasan_pembatalan' => 'Salah input'])
            ->assertSessionHasNoErrors();
        $this->assertSame('Dibatalkan', $batal->fresh()->status_transaksi);

        $hasil = $this->terlaris();

        $this->assertSame(['Kopi'], array_column($hasil, 'nama'));
    }

    public function test_filter_periode_mengubah_peringkat(): void
    {
        $kopi = $this->produk($this->unitA, 'Kopi');
        $teh = $this->produk($this->unitA, 'Teh');

        $this->jual($kopi, '2026-06-10', 20);  // Juni
        $this->jual($teh, '2026-07-10', 5);    // Juli
        $this->jual($teh, '2025-07-10', 99);   // tahun lalu

        // Default = Tahun Ini, semua bulan (sama dengan KPI).
        $this->assertSame(['Kopi', 'Teh'], array_column($this->terlaris(), 'nama'));
        $this->assertSame([20, 5], array_column($this->terlaris(), 'qty'));

        // Bulan Juli saja.
        $juli = $this->terlaris(['tahun' => '2026', 'bulan' => '7']);
        $this->assertSame(['Teh'], array_column($juli, 'nama'));
        $this->assertSame([5], array_column($juli, 'qty'));

        // Tahun 2025.
        $this->assertSame([99], array_column($this->terlaris(['tahun' => '2025']), 'qty'));
    }

    public function test_filter_unit_usaha(): void
    {
        $this->jual($this->produk($this->unitA, 'Kopi'), '2026-07-01', 3);
        $this->jual($this->produk($this->unitB, 'Gula'), '2026-07-01', 9);

        $hasil = $this->terlaris(['unit_id' => (string) $this->unitA->id]);

        $this->assertSame(['Kopi'], array_column($hasil, 'nama'));
    }

    public function test_produk_tanpa_penjualan_tidak_muncul(): void
    {
        $this->produk($this->unitA, 'Belum Laku');
        $this->jual($this->produk($this->unitA, 'Kopi'), '2026-07-01', 1);

        $this->assertSame(['Kopi'], array_column($this->terlaris(), 'nama'));
    }

    public function test_dashboard_tetap_jalan_tanpa_data_penjualan(): void
    {
        $this->produk($this->unitA, 'Belum Laku');

        $this->actingAs($this->admin)
            ->get(route('dashboard', ['modul' => 'koperasi']))
            ->assertOk()
            ->assertViewHas('produkTerlaris', [])
            ->assertSee('Produk Terlaris')
            ->assertSee('Belum ada data penjualan.');
    }

    public function test_tampil_di_dashboard_dengan_jumlah_terjual(): void
    {
        $this->jual($this->produk($this->unitA, 'Keripik Pisang'), '2026-07-01', 125);

        $this->actingAs($this->admin)
            ->get(route('dashboard', ['modul' => 'koperasi']))
            ->assertOk()
            ->assertSeeInOrder(['Laba Bersih', 'Produk Terlaris', 'Keripik Pisang', '125 terjual']);
    }
}
