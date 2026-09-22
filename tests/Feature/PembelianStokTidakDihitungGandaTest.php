<?php

namespace Tests\Feature;

use App\Models\BusinessUnit;
use App\Models\Customer;
use App\Models\Pengeluaran;
use App\Models\ProductService;
use App\Models\User;
use App\Services\LabaRugiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * PEMBELIAN STOK TIDAK BOLEH DIHITUNG DUA KALI DENGAN HPP.
 *
 * =====================================================================
 * KEADAAN YANG DITEMUKAN (22-09-2026)
 * =====================================================================
 *
 * Laba Rugi mengurangkan biaya barang dua kali: sekali lewat HPP
 * (qty x harga_modal_snapshot setiap item terjual), sekali lagi lewat
 * pengeluaran "Re-Stok Produk". Satu Re-Stok Rp1.005.000 membuat laba
 * bersih +401.798 tampil -603.202 — dan SHU ikut menjadi 0.
 *
 * Pembelian stok tetap DICATAT (uangnya memang keluar dan tetap muncul di
 * daftar Pengeluaran), tapi tidak mengurangi laba.
 */
class PembelianStokTidakDihitungGandaTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private BusinessUnit $unit;
    private Customer $pelanggan;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-07-20 09:00', 'Asia/Jakarta'));

        $this->admin = User::create([
            'name' => 'Admin Uji', 'email' => 'admin@restok.test',
            'password' => 'password123', 'role' => 'admin', 'is_active' => true,
        ]);

        $this->unit = BusinessUnit::create([
            'nama' => 'Cahaya Mart', 'kode' => 'CM', 'tipe' => 'Retail', 'status' => 'Aktif',
        ]);

        $this->pelanggan = Customer::create(['nama' => 'Warung Bu Sri', 'nomor_hp' => '0813']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function jual(int $qty, int $hargaJual, int $hargaModal): void
    {
        $produk = ProductService::create([
            'unit_id' => $this->unit->id,
            'kode' => 'RP-' . $qty,
            'nama' => 'Rempeyek Kacang',
            'jenis' => 'Produk',
            'harga_modal' => $hargaModal,
            'harga_jual' => $hargaJual,
            'status' => 'Aktif',
            'stock_tracking' => false,
        ]);

        $this->actingAs($this->admin)
            ->post(route('koperasi.penjualan.store'), [
                'unit_id' => $this->unit->id,
                'tanggal' => now()->toDateString(),
                'status_transaksi' => 'Selesai',
                'customer_id' => $this->pelanggan->id,
                'items_master' => [['product_id' => $produk->id, 'qty' => $qty]],
                'metode_pengiriman' => 'Ambil di Tempat',
                'status_pembayaran' => 'Lunas',
                'metode_pembayaran' => 'Cash',
            ])
            ->assertSessionHasNoErrors();
    }

    private function keluar(string $kategori, int $nominal, string $status = Pengeluaran::STATUS_AKTIF): void
    {
        Pengeluaran::create([
            'tanggal' => now()->toDateString(),
            'business_unit_id' => $this->unit->id,
            'kategori' => $kategori,
            'nominal' => $nominal,
            'status' => $status,
        ]);
    }

    private function hitung(): array
    {
        return app(LabaRugiService::class)->hitung('default', 'default', null);
    }

    public function test_restok_tidak_mengurangi_laba_karena_biayanya_sudah_di_hpp(): void
    {
        // Beli 21 rempeyek @17.000, lalu 21-nya terjual @20.000.
        $this->keluar('Re-Stok Produk', 357_000);
        $this->jual(21, 20_000, 17_000);

        $t = $this->hitung()['total'];

        $this->assertEquals(420_000, $t['penjualan']);
        $this->assertEquals(357_000, $t['hpp']);
        $this->assertEquals(0, $t['pengeluaran_total']);
        $this->assertEquals(63_000, $t['laba_bersih']);
        $this->assertEquals(357_000, $t['pembelian_stok']);
    }

    public function test_pengeluaran_lain_tetap_mengurangi_laba(): void
    {
        $this->keluar('Re-Stok Produk', 357_000);
        $this->keluar('Listrik', 50_000);
        $this->jual(21, 20_000, 17_000);

        $t = $this->hitung()['total'];

        $this->assertEquals(50_000, $t['pengeluaran_total']);
        $this->assertEquals(13_000, $t['laba_bersih']);
        $this->assertEquals(357_000, $t['pembelian_stok']);
    }

    public function test_restok_yang_dibatalkan_tidak_ikut_pembelian_stok(): void
    {
        $this->keluar('Re-Stok Produk', 357_000, Pengeluaran::STATUS_DIBATALKAN);

        $this->assertEquals(0, $this->hitung()['total']['pembelian_stok']);
    }

    public function test_restok_tetap_tercatat_sebagai_pengeluaran(): void
    {
        $this->keluar('Re-Stok Produk', 357_000);

        $this->assertEquals(357_000, (float) Pengeluaran::aktif()->sum('nominal'));
        $this->assertEquals(0, (float) Pengeluaran::bebanUsaha()->sum('nominal'));
        $this->assertEquals(357_000, (float) Pengeluaran::pembelianStok()->sum('nominal'));
    }

    public function test_halaman_pengeluaran_memisahkan_operasional_dan_pembelian_stok(): void
    {
        $this->keluar('Re-Stok Produk', 357_000);
        $this->keluar('Listrik', 50_000);
        $this->keluar('Re-Stok Produk', 90_000, Pengeluaran::STATUS_DIBATALKAN);

        $halaman = $this->actingAs($this->admin)->get(route('koperasi.pengeluaran.index'))->assertOk();

        // Total uang keluar tetap utuh; keduanya tetap terlihat di daftar.
        $this->assertEquals(407_000, $halaman->viewData('totalPeriode'));
        $this->assertEquals(50_000, $halaman->viewData('totalOperasional'));
        $this->assertEquals(357_000, $halaman->viewData('totalPembelianStok'));
        $this->assertEquals(
            $halaman->viewData('totalPeriode'),
            $halaman->viewData('totalOperasional') + $halaman->viewData('totalPembelianStok'),
        );

        $halaman->assertSeeInOrder(['Pengeluaran Operasional', 'Rp50.000', 'Pembelian/Re-Stok Produk', 'Rp357.000'])
            ->assertSee('Pembelian stok');
    }

    public function test_halaman_keuangan_menerangkan_pembelian_stok(): void
    {
        $this->keluar('Re-Stok Produk', 357_000);
        $this->jual(21, 20_000, 17_000);

        $this->actingAs($this->admin)
            ->get(route('koperasi.keuangan.index'))
            ->assertOk()
            // Keuangan Usaha: Pengeluaran Operasional Rp0, Laba Bersih Rp63.000;
            // pembelian stok tampil di bagian Arus Kas Pembelian Stok.
            ->assertSeeInOrder(['Pengeluaran Operasional', 'Rp0', 'Laba Bersih', 'Rp63.000'])
            ->assertSeeInOrder(['Arus Kas Pembelian Stok', 'Pembelian/Re-Stok Produk', 'Rp357.000'])
            ->assertSee('Pembelian stok tercatat sebagai pengeluaran kas, namun tidak dibebankan langsung ke laba-rugi.')
            ->assertSee('pembelian stok — tidak mengurangi laba');
    }
}
