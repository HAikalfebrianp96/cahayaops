<?php

namespace Tests\Feature;

use App\Models\AkunKas;
use App\Models\BusinessUnit;
use App\Models\Customer;
use App\Models\Participant;
use App\Models\ProductService;
use App\Models\SalesTransaction;
use App\Models\User;
use App\Services\LabaRugiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * PENYEMPURNAAN DASHBOARD (23 Sep 2026).
 *
 * - Produk Terlaris (jumlah unit) dan Penjualan Terbesar (Rupiah) adalah dua
 *   peringkat berbeda dari satu sumber (LabaRugiService).
 * - Dashboard Koperasi (Admin) dan Dashboard Tim Koperasi memakai partial &
 *   angka yang sama; Transaksi Terbaru memuat pelanggan dan tanpa transaksi batal.
 * - Dashboard Admin "semua" = pusat monitoring, Keuangan Koperasi sama
 *   dengan Keuangan; Dashboard Pimpinan: hasil, bukan operasional.
 * - Uang Tersedia tidak ditampilkan bila titik awalnya belum lengkap.
 * - HPP Rp0 valid; hanya pengingat agar alasannya tercatat.
 */
class DashboardFinalTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private BusinessUnit $unit;

    private Customer $pelanggan;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-07-20 09:00', 'Asia/Jakarta'));

        $this->admin = $this->user('admin', 'admin@final.test');
        $this->unit = BusinessUnit::create(['nama' => 'Cahaya Mart', 'kode' => 'CM', 'tipe' => 'Retail', 'status' => 'Aktif']);
        $this->pelanggan = Customer::create(['nama' => 'Warung Bu Sri', 'nomor_hp' => '0813']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function user(string $role, string $email): User
    {
        return User::create([
            'name' => ucfirst($role) . ' Uji', 'email' => $email,
            'password' => 'password123', 'role' => $role, 'is_active' => true,
        ]);
    }

    private function produk(string $nama, int $modal, int $jual): ProductService
    {
        return ProductService::create([
            'unit_id' => $this->unit->id, 'nama' => $nama, 'jenis' => 'Produk',
            'harga_modal' => $modal, 'harga_jual' => $jual, 'status' => 'Aktif', 'stock_tracking' => false,
        ]);
    }

    private function jual(ProductService $produk, int $qty, string $tanggal = '2026-07-01', string $status = 'Lunas', array $ekstra = []): SalesTransaction
    {
        $this->actingAs($this->admin)
            ->post(route('koperasi.penjualan.store'), [
                'unit_id' => $this->unit->id,
                'tanggal' => $tanggal,
                'status_transaksi' => 'Selesai',
                'customer_id' => $this->pelanggan->id,
                'items_master' => [array_merge(['product_id' => $produk->id, 'qty' => $qty], $ekstra)],
                'metode_pengiriman' => 'Ambil di Tempat',
                'status_pembayaran' => $status,
                'metode_pembayaran' => 'Cash',
            ])
            ->assertSessionHasNoErrors();

        return SalesTransaction::latest('id')->first();
    }

    /** Kerupuk: murah, laku banyak. Kopi: mahal, laku sedikit. */
    private function isiPenjualan(): void
    {
        $this->jual($this->produk('Kerupuk', 8_000, 10_000), 50);   // 50 unit,  Rp500.000
        $this->jual($this->produk('Kopi Bubuk', 60_000, 80_000), 10); // 10 unit, Rp800.000
        $batal = $this->jual($this->produk('Madu', 90_000, 100_000), 99); // dibatalkan
        $batal->forceFill(['status_transaksi' => 'Dibatalkan'])->save();
    }

    public function test_produk_terlaris_dan_penjualan_terbesar_berbeda_urutan(): void
    {
        $this->isiPenjualan();
        $lr = app(LabaRugiService::class);

        $terlaris = $lr->produkTerlaris('2026', 'semua');
        $terbesar = $lr->penjualanTerbesar('2026', 'semua');

        $this->assertSame(['Kerupuk', 'Kopi Bubuk'], array_column($terlaris, 'nama'));
        $this->assertSame(['Kopi Bubuk', 'Kerupuk'], array_column($terbesar, 'nama'));
        $this->assertEquals(800_000, $terbesar[0]['omzet']);

        // Transaksi batal tidak ikut; jumlah omzet = Total Penjualan KPI.
        $this->assertNotContains('Madu', array_column($terbesar, 'nama'));
        $this->assertEquals($lr->hitung('2026', 'semua')['total']['penjualan'], array_sum(array_column($terbesar, 'omzet')));
    }

    public function test_dashboard_koperasi_urutan_dan_transaksi_terbaru(): void
    {
        $this->isiPenjualan();

        $respon = $this->actingAs($this->admin)->get(route('dashboard', ['modul' => 'koperasi']))->assertOk();

        $respon->assertSeeInOrder([
            'Total Penjualan', 'HPP Terjual', 'Laba Kotor', 'Pengeluaran Operasional', 'Piutang Aktif', 'Laba Bersih',
            'Produk Terlaris', 'Kerupuk', '50 terjual',
            'Penjualan Terbesar', 'Kopi Bubuk', 'Rp800.000',
            'Transaksi Terbaru', 'Warung Bu Sri',
        ]);

        // Transaksi Terbaru tanpa transaksi batal.
        $terbaru = $respon->viewData('usaha')['transaksiTerbaru'];
        $this->assertCount(2, $terbaru);
        $this->assertNotContains('Dibatalkan', $terbaru->pluck('status_transaksi')->all());
    }

    public function test_dashboard_tim_koperasi_sama_dengan_admin(): void
    {
        $this->isiPenjualan();
        $tim = $this->user('tim_koperasi', 'tim@final.test');
        $filter = ['tahun' => '2026', 'bulan' => '7'];

        $admin = $this->actingAs($this->admin)->get(route('dashboard', ['modul' => 'koperasi'] + $filter))->viewData('usaha');
        $timUsaha = $this->actingAs($tim)->get(route('dashboard', $filter))->assertOk()->viewData('usaha');

        $this->assertEquals($admin['stats'], $timUsaha['stats']);
        $this->assertEquals($admin['produkTerlaris'], $timUsaha['produkTerlaris']);
        $this->assertEquals($admin['penjualanTerbesar'], $timUsaha['penjualanTerbesar']);
    }

    public function test_dashboard_admin_semua_sama_dengan_keuangan(): void
    {
        $this->isiPenjualan();

        $respon = $this->actingAs($this->admin)->get(route('dashboard'))->assertOk();
        $ringkas = $respon->viewData('ringkasanSemua');
        $keuangan = $this->actingAs($this->admin)->get(route('koperasi.keuangan.index'))->viewData('lr')['total'];

        $this->assertEquals($keuangan, $ringkas['keuangan']);
        $this->assertSame(3, $ringkas['total_produk']);
        $this->assertSame('Kerupuk', $ringkas['produk_terlaris'][0]['nama']);
        $respon->assertSee('Rp1.300.000');
    }

    public function test_dashboard_pimpinan_tanpa_hpp_dan_rincian(): void
    {
        $this->isiPenjualan();

        $respon = $this->actingAs($this->user('pimpinan', 'pim@final.test'))->get(route('dashboard'))->assertOk();

        $respon->assertSeeInOrder(['Produk Terlaris', 'Kerupuk', '50 terjual', 'Tren Penjualan']);
        $this->assertEquals(1_300_000, array_sum($respon->viewData('koperasiChart')['data']));
        $respon->assertDontSee('HPP')->assertDontSee('Warung Bu Sri')->assertDontSee('<table', false);
    }

    public function test_uang_tersedia_belum_tersedia_saat_saldo_awal_belum_lengkap(): void
    {
        // Akun bawaan dari migration + satu akun baru — semuanya tanpa saldo awal.
        AkunKas::create(['nama' => 'Kas Kecil', 'jenis' => 'Kas', 'saldo_awal' => 0, 'status' => 'Aktif']);
        $belum = AkunKas::whereNull('tanggal_saldo_awal')->where('status', 'Aktif')->count();

        $this->actingAs($this->admin)->get(route('dashboard', ['modul' => 'koperasi']))
            ->assertOk()
            ->assertSeeInOrder(['Posisi Uang Hari Ini', 'Uang Tersedia', 'Belum tersedia', $belum . ' akun belum punya saldo awal.'])
            ->assertSee('Laba Bersih bukan uang tersedia.');

        // Setelah semua akun punya saldo awal, angkanya tampil.
        AkunKas::query()->update(['tanggal_saldo_awal' => '2026-07-01', 'saldo_awal' => 100_000]);
        $this->actingAs($this->admin)->get(route('dashboard', ['modul' => 'koperasi']))
            ->assertDontSee('Belum tersedia')
            ->assertSee('Rp' . number_format(100_000 * AkunKas::count(), 0, ',', '.'));
    }

    public function test_keterangan_hpp_nol_hanya_pengingat(): void
    {
        // Produk biasa (modal master > 0) tapi snapshot Rp0 -> peringatan.
        $trx = $this->jual($this->produk('Keripik', 9_000, 12_000), 2, ekstra: ['harga_modal' => '0']);
        $item = $trx->items()->first();
        $this->assertEquals(0, (float) $item->harga_modal_snapshot); // data tidak diubah
        $this->assertSame('peringatan', $item->keteranganHppNol()['tingkat']);

        $this->actingAs($this->admin)->get(route('koperasi.penjualan.show', $trx))
            ->assertOk()
            ->assertSee('padahal harga modal di master produk Rp9.000');

        // Item manual tanpa catatan -> info; dengan catatan -> diam.
        $item->forceFill(['product_service_id' => null])->save();
        $this->assertSame('info', $item->fresh()->keteranganHppNol()['tingkat']);

        $item->forceFill(['catatan' => 'Barang diperoleh gratis'])->save();
        $this->assertNull($item->fresh()->keteranganHppNol());

        // HPP > 0 tidak pernah memicu keterangan ini.
        $item->forceFill(['harga_modal_snapshot' => 5_000, 'catatan' => null])->save();
        $this->assertNull($item->fresh()->keteranganHppNol());
    }
}
