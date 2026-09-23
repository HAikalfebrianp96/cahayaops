<?php

namespace Tests\Feature;

use App\Models\BusinessUnit;
use App\Models\Customer;
use App\Models\Pengeluaran;
use App\Models\ProductService;
use App\Models\SalesTransaction;
use App\Models\User;
use App\Services\LabaRugiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Filter Unit Usaha di Dashboard Koperasi & Keuangan harus berarti hal yang
 * SAMA dengan filter unit di halaman Pengeluaran:
 *
 * - Unit tertentu  : hanya penjualan & pengeluaran unit itu. Pengeluaran
 *   Operasional Koperasi (tanpa unit) TIDAK ikut dikurangkan — sebelumnya
 *   ikut, sehingga Laba Bersih "Unit A" di KPI berbeda dari baris Unit A di
 *   tabel per unit dan dari halaman Pengeluaran dengan filter yang sama.
 * - Operasional Koperasi : hanya pengeluaran tanpa unit; tidak ada penjualan
 *   (penjualan selalu milik suatu unit). Sebelumnya filter ini tetap
 *   menampilkan penjualan SEMUA unit.
 * - Semua Unit     : semuanya, termasuk pengeluaran operasional koperasi.
 */
class DashboardKoperasiFilterUnitTest extends TestCase
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
            'name' => 'Admin Uji', 'email' => 'admin@filterunit.test',
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

    private function jual(BusinessUnit $unit, string $tanggal, int $qty, int $jual, int $modal, string $status = 'Lunas', int $dp = 0): void
    {
        $produk = ProductService::create([
            'unit_id' => $unit->id, 'nama' => 'Produk ' . $unit->kode . $qty . $jual,
            'jenis' => 'Produk', 'harga_modal' => $modal, 'harga_jual' => $jual,
            'status' => 'Aktif', 'stock_tracking' => false,
        ]);

        $this->actingAs($this->admin)
            ->post(route('koperasi.penjualan.store'), [
                'unit_id' => $unit->id,
                'tanggal' => $tanggal,
                'status_transaksi' => 'Selesai',
                'customer_id' => $this->pelanggan->id,
                'items_master' => [['product_id' => $produk->id, 'qty' => $qty, 'hak_mitra_per_unit' => 0]],
                'metode_pengiriman' => 'Ambil di Tempat',
                'status_pembayaran' => $status,
                'nominal_dibayar' => $dp,
                'metode_pembayaran' => 'Cash',
            ])
            ->assertSessionHasNoErrors();
    }

    private function keluar(?BusinessUnit $unit, string $tanggal, int $nominal, string $kategori = 'Listrik'): void
    {
        Pengeluaran::create([
            'tanggal' => $tanggal, 'business_unit_id' => $unit?->id,
            'kategori' => $kategori, 'nominal' => $nominal, 'status' => Pengeluaran::STATUS_AKTIF,
        ]);
    }

    private function dashboard(array $filter = [])
    {
        return $this->actingAs($this->admin)->get(route('dashboard', ['modul' => 'koperasi'] + $filter))->assertOk();
    }

    private function isiData(): void
    {
        $this->jual($this->unitA, '2026-07-01', 10, 20_000, 17_000);  // A: 200.000 / HPP 170.000
        $this->jual($this->unitB, '2026-07-02', 1, 50_000, 30_000);   // B:  50.000 / HPP  30.000
        $this->keluar($this->unitA, '2026-07-03', 5_000);             // beban A
        $this->keluar($this->unitB, '2026-07-03', 7_000);             // beban B
        $this->keluar(null, '2026-07-04', 11_000);                    // operasional koperasi
    }

    public function test_filter_unit_tidak_ikut_mengurangi_pengeluaran_operasional_koperasi(): void
    {
        $this->isiData();
        $filter = ['tahun' => '2026', 'bulan' => '7', 'unit_id' => (string) $this->unitA->id];

        $stats = $this->dashboard($filter)->viewData('stats');
        $lr = app(LabaRugiService::class)->hitung('2026', '7', (string) $this->unitA->id);

        $this->assertEquals(200_000, $stats['penjualan_tahun_ini']);
        $this->assertEquals(5_000, $stats['pengeluaran_tahun_ini']);
        $this->assertEquals(0, $lr['total']['pengeluaran_operasional']);
        $this->assertEquals(200_000 - 170_000 - 5_000, $stats['laba_bersih_tahun_ini']);

        // KPI = baris unit yang sama di tabel per unit.
        $this->assertEquals($lr['per_unit'][$this->unitA->id]['laba_bersih'], $stats['laba_bersih_tahun_ini']);

        // KPI Pengeluaran = halaman Pengeluaran dengan filter yang sama.
        $halaman = $this->actingAs($this->admin)->get(route('koperasi.pengeluaran.index', $filter))->assertOk();
        $this->assertEquals($halaman->viewData('totalOperasional'), $stats['pengeluaran_tahun_ini']);
    }

    public function test_filter_operasional_hanya_pengeluaran_tanpa_unit(): void
    {
        $this->isiData();
        $filter = ['tahun' => '2026', 'bulan' => '7', 'unit_id' => 'operasional'];

        $stats = $this->dashboard($filter)->viewData('stats');

        $this->assertEquals(0, $stats['penjualan_tahun_ini']);
        $this->assertEquals(0, $stats['hpp_tahun_ini']);
        $this->assertEquals(11_000, $stats['pengeluaran_tahun_ini']);
        $this->assertEquals(-11_000, $stats['laba_bersih_tahun_ini']);
        $this->assertSame([], app(LabaRugiService::class)->produkTerlaris('2026', '7', 'operasional'));

        $halaman = $this->actingAs($this->admin)->get(route('koperasi.pengeluaran.index', $filter))->assertOk();
        $this->assertEquals($halaman->viewData('totalOperasional'), $stats['pengeluaran_tahun_ini']);
    }

    public function test_semua_unit_menjumlahkan_seluruh_pengeluaran_operasional(): void
    {
        $this->isiData();

        $stats = $this->dashboard(['tahun' => '2026', 'bulan' => '7'])->viewData('stats');

        $this->assertEquals(250_000, $stats['penjualan_tahun_ini']);
        $this->assertEquals(5_000 + 7_000 + 11_000, $stats['pengeluaran_tahun_ini']);
        $this->assertEquals(250_000 - 200_000 - 23_000, $stats['laba_bersih_tahun_ini']);
    }

    public function test_ringkasan_piutang_mengikuti_periode_dan_mengabaikan_batal(): void
    {
        $this->jual($this->unitA, '2026-07-01', 1, 100_000, 60_000);                  // lunas
        $this->jual($this->unitA, '2026-07-02', 1, 100_000, 60_000, 'DP', 40_000);    // sisa 60.000
        $this->jual($this->unitB, '2026-07-03', 1, 100_000, 60_000, 'Belum Dibayar'); // sisa 100.000
        $this->jual($this->unitA, '2026-06-03', 1, 100_000, 60_000, 'Belum Dibayar'); // bulan lain
        $this->jual($this->unitA, '2026-07-04', 1, 100_000, 60_000, 'Belum Dibayar'); // dibatalkan
        SalesTransaction::latest('id')->first()->forceFill(['status_transaksi' => 'Dibatalkan'])->save();

        $respon = $this->dashboard(['tahun' => '2026', 'bulan' => '7']);
        $stats = $respon->viewData('stats');

        $this->assertEquals(160_000, $stats['piutang_aktif']);
        $this->assertSame(2, $stats['transaksi_belum_lunas']);
        $respon->assertSeeInOrder(['Piutang', 'Transaksi belum lunas', '2', 'Total piutang aktif', 'Rp160.000']);
    }

    public function test_tampilan_ringkas_pengeluaran_dan_hpp_per_unit(): void
    {
        $this->isiData();
        $this->keluar($this->unitA, '2026-07-05', 340_000, 'Re-Stok Produk');

        $this->dashboard(['tahun' => '2026', 'bulan' => '7'])
            // Pengeluaran dipisah: operasional (mengurangi laba) vs re-stok (tidak).
            ->assertSeeInOrder(['Pengeluaran Operasional', 'Rp23.000', 'Pembelian/Re-Stok Produk', 'Rp340.000', 'Total uang keluar', 'Rp363.000'])
            // Ringkasan per unit: Penjualan, HPP, Laba Bersih.
            ->assertSeeInOrder(['Performa Unit Usaha', 'Penjualan', 'HPP', 'Laba Bersih', 'Unit A', 'Rp200.000', 'Rp170.000', 'Rp25.000']);
    }

    public function test_laba_bersih_tetap_tampil_saat_hanya_ada_pengeluaran(): void
    {
        $this->keluar(null, '2026-07-04', 11_000);

        $this->dashboard(['tahun' => '2026', 'bulan' => '7'])
            ->assertDontSee('Belum ada transaksi</p>', false)
            ->assertSeeInOrder(['Laba Bersih', '-Rp11.000']);
    }
}
