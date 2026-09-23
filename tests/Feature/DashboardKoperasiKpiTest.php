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
 * DASHBOARD KOPERASI — ENAM KPI DARI SATU SUMBER, SATU FILTER.
 *
 * Total Penjualan, HPP Terjual, Laba Kotor, Pengeluaran, Piutang Aktif, dan
 * Laba Bersih dibaca dari SATU hasil LabaRugiService — sumber yang sama
 * dengan halaman Keuangan — dan tunduk pada filter Tahun/Bulan/Unit yang
 * sama. Tidak ada kartu yang boleh memakai dataset lain.
 *
 * Hak Mitra & Fee Koperasi bukan KPI: hak mitra dihitung koperasi di luar
 * sistem. Datanya tetap tersimpan dan tetap ada di Keuangan.
 */
class DashboardKoperasiKpiTest extends TestCase
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
            'name' => 'Admin Uji', 'email' => 'admin@kpi.test',
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

    private function jual(BusinessUnit $unit, string $tanggal, int $qty, int $jual, int $modal, string $status = 'Lunas', int $dp = 0, int $hakMitra = 0): void
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
                'items_master' => [['product_id' => $produk->id, 'qty' => $qty, 'hak_mitra_per_unit' => $hakMitra]],
                'metode_pengiriman' => 'Ambil di Tempat',
                'status_pembayaran' => $status,
                'nominal_dibayar' => $dp,
                'metode_pembayaran' => 'Cash',
            ])
            ->assertSessionHasNoErrors();
    }

    private function keluar(BusinessUnit $unit, string $tanggal, int $nominal): void
    {
        Pengeluaran::create([
            'tanggal' => $tanggal, 'business_unit_id' => $unit->id,
            'kategori' => 'Listrik', 'nominal' => $nominal, 'status' => Pengeluaran::STATUS_AKTIF,
        ]);
    }

    private function dashboard(array $filter = [])
    {
        return $this->actingAs($this->admin)->get(route('dashboard', ['modul' => 'koperasi'] + $filter))->assertOk();
    }

    public function test_enam_kpi_sama_dengan_halaman_keuangan_dan_rumusnya_utuh(): void
    {
        $this->jual($this->unitA, '2026-07-01', 10, 20_000, 17_000);              // lunas
        $this->jual($this->unitA, '2026-07-02', 5, 10_000, 8_000, 'Belum Dibayar'); // piutang 50.000
        $this->keluar($this->unitA, '2026-07-03', 12_000);

        $stats = $this->dashboard()->viewData('stats');
        $keuangan = $this->actingAs($this->admin)->get(route('koperasi.keuangan.index'))->viewData('lr')['total'];

        $this->assertEquals(250_000, $stats['penjualan_tahun_ini']);
        $this->assertEquals(210_000, $stats['hpp_tahun_ini']);
        $this->assertEquals(40_000, $stats['laba_kotor_tahun_ini']);
        $this->assertEquals(12_000, $stats['pengeluaran_tahun_ini']);
        $this->assertEquals(50_000, $stats['piutang_aktif']);
        $this->assertEquals(28_000, $stats['laba_bersih_tahun_ini']);

        // Penjualan - HPP = Laba Kotor; Laba Kotor - Pengeluaran = Laba Bersih
        $this->assertEquals($stats['penjualan_tahun_ini'] - $stats['hpp_tahun_ini'], $stats['laba_kotor_tahun_ini']);
        $this->assertEquals($stats['laba_kotor_tahun_ini'] - $stats['pengeluaran_tahun_ini'], $stats['laba_bersih_tahun_ini']);

        // Satu sumber dengan Keuangan.
        $this->assertEquals($keuangan['penjualan'], $stats['penjualan_tahun_ini']);
        $this->assertEquals($keuangan['hpp'], $stats['hpp_tahun_ini']);
        $this->assertEquals($keuangan['laba_bersih'], $stats['laba_bersih_tahun_ini']);
        $this->assertEquals($keuangan['piutang'], $stats['piutang_aktif']);
    }

    public function test_filter_bulan_dan_unit_berlaku_untuk_keenam_kpi(): void
    {
        $this->jual($this->unitA, '2026-07-01', 10, 20_000, 17_000, 'Belum Dibayar'); // Juli, A
        $this->jual($this->unitA, '2026-06-10', 2, 50_000, 40_000, 'Belum Dibayar');  // Juni, A
        $this->jual($this->unitB, '2026-07-05', 1, 30_000, 25_000, 'Belum Dibayar');  // Juli, B
        $this->keluar($this->unitA, '2026-07-03', 5_000);
        $this->keluar($this->unitA, '2026-06-03', 7_000);
        $this->keluar($this->unitB, '2026-07-03', 9_000);

        $stats = $this->dashboard(['tahun' => '2026', 'bulan' => '7', 'unit_id' => (string) $this->unitA->id])->viewData('stats');

        $this->assertEquals(200_000, $stats['penjualan_tahun_ini']);
        $this->assertEquals(170_000, $stats['hpp_tahun_ini']);
        $this->assertEquals(30_000, $stats['laba_kotor_tahun_ini']);
        $this->assertEquals(5_000, $stats['pengeluaran_tahun_ini']);
        $this->assertEquals(200_000, $stats['piutang_aktif']);
        $this->assertEquals(25_000, $stats['laba_bersih_tahun_ini']);

        $lr = app(LabaRugiService::class)->hitung('2026', '7', (string) $this->unitA->id)['total'];
        $this->assertEquals($lr['laba_bersih'], $stats['laba_bersih_tahun_ini']);
        $this->assertEquals($lr['piutang'], $stats['piutang_aktif']);
    }

    public function test_piutang_hanya_sisa_tagihan_yang_belum_lunas(): void
    {
        $this->jual($this->unitA, '2026-07-01', 1, 100_000, 60_000);                  // lunas: 0
        $this->jual($this->unitA, '2026-07-02', 1, 100_000, 60_000, 'DP', 40_000);    // sisa 60.000
        $this->jual($this->unitA, '2026-07-03', 1, 100_000, 60_000, 'Belum Dibayar'); // sisa 100.000

        // Transaksi dibatalkan tidak ikut penjualan — dan tidak ikut piutang.
        $this->jual($this->unitA, '2026-07-04', 1, 100_000, 60_000, 'Belum Dibayar');
        SalesTransaction::latest('id')->first()->forceFill(['status_transaksi' => 'Dibatalkan'])->save();

        $stats = $this->dashboard()->viewData('stats');

        $this->assertEquals(160_000, $stats['piutang_aktif']);
        $this->assertEquals(300_000, $stats['penjualan_tahun_ini']);
    }

    public function test_hak_mitra_tidak_tampil_sebagai_kpi_dan_tidak_mengurangi_laba(): void
    {
        $this->jual($this->unitA, '2026-07-01', 10, 20_000, 17_000, 'Lunas', 0, 1_000);

        $halaman = $this->dashboard();
        $stats = $halaman->viewData('stats');

        $halaman->assertDontSee('Hak Mitra')->assertDontSee('Fee Titip Jual')->assertDontSee('Fee Koperasi');
        $this->assertEquals(30_000, $stats['laba_bersih_tahun_ini']);

        // Datanya tetap tersimpan & tetap ada di Keuangan.
        $this->assertEquals(10_000, app(LabaRugiService::class)->hitung()['total']['hak_mitra']);
    }

    public function test_dashboard_dan_keuangan_sama_saat_ada_re_stok(): void
    {
        $this->jual($this->unitA, '2026-07-01', 10, 20_000, 17_000);
        $this->jual($this->unitA, '2026-07-02', 1, 50_000, 30_000, 'DP', 20_000);
        $this->keluar($this->unitA, '2026-07-03', 12_000);
        Pengeluaran::create([
            'tanggal' => '2026-07-04', 'business_unit_id' => $this->unitA->id,
            'kategori' => 'Re-Stok Produk', 'nominal' => 340_000, 'status' => Pengeluaran::STATUS_AKTIF,
        ]);

        $filter = ['tahun' => '2026', 'bulan' => '7', 'unit_id' => (string) $this->unitA->id];
        $dashboard = $this->dashboard($filter);
        $stats = $dashboard->viewData('stats');
        $lr = $this->actingAs($this->admin)->get(route('koperasi.keuangan.index', $filter))->viewData('lr')['total'];

        // Pengeluaran Operasional tanpa Re-Stok; Re-Stok tetap terlihat terpisah.
        $this->assertEquals(12_000, $stats['pengeluaran_tahun_ini']);
        $this->assertEquals(340_000, $stats['pembelian_stok']);
        $this->assertEquals(30_000, $stats['piutang_aktif']);
        $this->assertEquals(250_000 - 200_000 - 12_000, $stats['laba_bersih_tahun_ini']);

        // Tujuh angka, dua halaman, satu sumber.
        foreach ([
            'penjualan_tahun_ini' => 'penjualan', 'hpp_tahun_ini' => 'hpp', 'laba_kotor_tahun_ini' => 'laba_kotor',
            'pengeluaran_tahun_ini' => 'pengeluaran_total', 'piutang_aktif' => 'piutang',
            'laba_bersih_tahun_ini' => 'laba_bersih', 'pembelian_stok' => 'pembelian_stok',
        ] as $kunciDashboard => $kunciKeuangan) {
            $this->assertEquals($lr[$kunciKeuangan], $stats[$kunciDashboard], $kunciDashboard);
        }

        $dashboard->assertSeeInOrder(['Pembelian/Re-Stok Produk', 'Rp340.000'])
            ->assertSee('Pembelian stok tidak mengurangi laba saat pembelian. Biayanya menjadi HPP ketika produk terjual.');
    }

    public function test_label_periode_mengikuti_filter(): void
    {
        $this->dashboard()->assertSee('Periode: Tahun 2026, semua bulan');
        $this->dashboard(['tahun' => '2026', 'bulan' => '7', 'unit_id' => (string) $this->unitB->id])
            ->assertSee('Periode: Tahun 2026, Juli, Unit B');
    }
}
