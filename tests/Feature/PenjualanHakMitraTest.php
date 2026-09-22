<?php

namespace Tests\Feature;

use App\Models\BusinessUnit;
use App\Models\ProductService;
use App\Models\SalesTransaction;
use App\Models\User;
use App\Services\LabaRugiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PENJUALAN SEDERHANA: QTY x MODAL x HARGA JUAL, HAK MITRA TERPISAH.
 *
 * =====================================================================
 * YANG DIGANTI
 * =====================================================================
 *
 * Setiap baris item dulu menuntut TIGA keputusan untuk satu angka: pilih
 * jenis ("Milik Koperasi" / "Titip Jual"), isi persentase fee koperasi,
 * lalu hak mitra lahir sebagai sisanya.
 *
 * Akibat sampingnya tidak terlihat dari form: item "Titip Jual"
 * DIKELUARKAN dari Penjualan dan HPP koperasi. Dua transaksi dengan barang
 * dan harga yang sama bisa menghasilkan angka laba rugi yang berbeda hanya
 * karena jenisnya dipilih berbeda — dan selisihnya tidak muncul di mana pun
 * sebagai penjelasan.
 *
 * Sekarang: setiap item masuk Penjualan dan HPP, dan Hak Mitra cukup satu
 * isian opsional per unit.
 *
 * =====================================================================
 * YANG PALING PENTING DIJAGA
 * =====================================================================
 *
 * Hak Mitra TIDAK boleh ikut mengurangi profit. Modal item itu sudah
 * mewakili biayanya; mengurangkan hak mitra lagi membuat satu biaya
 * terhitung dua kali dan profit tampak lebih kecil dari kenyataan.
 */
class PenjualanHakMitraTest extends TestCase
{
    use RefreshDatabase;

    private User $timKoperasi;
    private BusinessUnit $unit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->timKoperasi = User::create([
            'name' => 'Tim Koperasi',
            'email' => 'koperasi@mitra.test',
            'password' => bcrypt('rahasia123'),
            'role' => User::ROLE_TIM_KOPERASI,
            'is_active' => true,
        ]);

        $this->unit = BusinessUnit::create([
            'nama' => 'Cahaya Mart', 'kode' => 'UNIT-HM', 'tipe' => 'Barang', 'status' => 'Aktif',
        ]);
    }

    private function produk(string $nama, float $modal, float $jual, string $jenis = 'Produk'): ProductService
    {
        return ProductService::create([
            'unit_id' => $this->unit->id,
            'nama' => $nama,
            'jenis' => $jenis,
            'satuan' => $jenis === 'Jasa' ? 'Jasa' : 'Pcs',
            'harga_modal' => $modal,
            'harga_jual' => $jual,
            'status' => 'Aktif',
        ]);
    }

    /** @param list<array{produk: ProductService, qty: int, hak_mitra?: float}> $baris */
    private function simpan(array $baris): SalesTransaction
    {
        $items = [];

        foreach ($baris as $b) {
            $items[] = array_filter([
                'product_id' => $b['produk']->id,
                'qty' => $b['qty'],
                'harga_jual' => (float) $b['produk']->harga_jual,
                'harga_modal' => (float) $b['produk']->harga_modal,
                'hak_mitra_per_unit' => $b['hak_mitra'] ?? null,
            ], fn ($v) => $v !== null);
        }

        $this->actingAs($this->timKoperasi)
            ->post(route('koperasi.penjualan.store'), [
                'unit_id' => $this->unit->id,
                'tanggal' => now()->toDateString(),
                'status_transaksi' => 'Aktif',
                'status_pembayaran' => 'Lunas',
                'metode_pembayaran' => 'Cash',
                'metode_pengiriman' => 'Ambil di Tempat',
                'customer_nama' => 'Budi',
                'items_master' => $items,
            ])
            ->assertSessionHasNoErrors();

        return SalesTransaction::with('items')->latest('id')->first();
    }

    // =================================================================
    // HITUNGAN DASAR
    // =================================================================

    /** Qty x Harga Jual, Qty x Modal, dan selisihnya. */
    public function test_total_modal_penjualan_dan_profit(): void
    {
        $trx = $this->simpan([
            ['produk' => $this->produk('Pop Corn', 12_000, 15_000), 'qty' => 12],
        ]);

        $this->assertSame(180_000.0, (float) $trx->items->sum('subtotal'));
        $this->assertSame(144_000.0, $trx->totalHpp());
        $this->assertSame(36_000.0, $trx->estimasiProfit());
    }

    /** Satu transaksi boleh memuat banyak item, barang maupun jasa. */
    public function test_barang_dan_jasa_dalam_satu_transaksi(): void
    {
        $trx = $this->simpan([
            ['produk' => $this->produk('Pop Corn', 12_000, 15_000), 'qty' => 12],
            ['produk' => $this->produk('Kaos', 40_000, 50_000), 'qty' => 10],
            ['produk' => $this->produk('Servis AC', 50_000, 150_000, 'Jasa'), 'qty' => 1],
        ]);

        $this->assertCount(3, $trx->items);

        // 180.000 + 500.000 + 150.000
        $this->assertSame(830_000.0, (float) $trx->items->sum('subtotal'));

        // 144.000 + 400.000 + 50.000
        $this->assertSame(594_000.0, $trx->totalHpp());
        $this->assertSame(236_000.0, $trx->estimasiProfit());
    }

    // =================================================================
    // HAK MITRA
    // =================================================================

    public function test_hak_mitra_dihitung_per_unit(): void
    {
        $trx = $this->simpan([
            ['produk' => $this->produk('Pop Corn', 12_000, 15_000), 'qty' => 12, 'hak_mitra' => 12_000],
        ]);

        $this->assertSame(144_000.0, $trx->totalHakMitra());
    }

    /**
     * HAK MITRA TIDAK MENGURANGI PROFIT — dan tidak menghapus item itu dari
     * penjualan maupun HPP.
     *
     * Inilah inti perubahannya. Dua transaksi identik, satu diberi hak mitra:
     * angka penjualan, HPP, dan profitnya harus SAMA PERSIS.
     */
    public function test_hak_mitra_tidak_mengubah_penjualan_hpp_maupun_profit(): void
    {
        $tanpa = $this->simpan([
            ['produk' => $this->produk('Pop Corn A', 12_000, 15_000), 'qty' => 12],
        ]);

        $dengan = $this->simpan([
            ['produk' => $this->produk('Pop Corn B', 12_000, 15_000), 'qty' => 12, 'hak_mitra' => 12_000],
        ]);

        $this->assertSame(
            (float) $tanpa->items->sum('subtotal'),
            (float) $dengan->items->sum('subtotal'),
            'Hak mitra tidak boleh mengubah nilai penjualan.'
        );

        $this->assertSame($tanpa->totalHpp(), $dengan->totalHpp(), 'Hak mitra tidak boleh mengubah HPP.');
        $this->assertSame($tanpa->estimasiProfit(), $dengan->estimasiProfit(), 'Hak mitra tidak boleh mengurangi profit.');

        $this->assertSame(0.0, $tanpa->totalHakMitra());
        $this->assertSame(144_000.0, $dengan->totalHakMitra());
    }

    /** Tanpa isian, hak mitra nol — bukan nilai tebakan. */
    public function test_tanpa_isian_hak_mitra_bernilai_nol(): void
    {
        $trx = $this->simpan([
            ['produk' => $this->produk('Pop Corn', 12_000, 15_000), 'qty' => 12],
        ]);

        $this->assertSame(0.0, $trx->totalHakMitra());
        $this->assertSame(0.0, (float) $trx->items->sum('fee_amount'));
        $this->assertSame(0.0, (float) $trx->items->sum('fee_rate'));
    }

    /**
     * Laba Rugi memakai aturan yang SAMA dengan halaman transaksi.
     *
     * Kalau keduanya berbeda, tidak ada satu pun dari keduanya yang bisa
     * dipakai — dan yang dibawa ke rapat justru laporannya.
     */
    public function test_laba_rugi_sepakat_dengan_halaman_transaksi(): void
    {
        $trx = $this->simpan([
            ['produk' => $this->produk('Pop Corn', 12_000, 15_000), 'qty' => 12, 'hak_mitra' => 12_000],
        ]);

        $lr = app(LabaRugiService::class)->hitung('semua', 'semua', null);

        $this->assertSame(180_000.0, (float) $lr['total']['penjualan']);
        $this->assertSame(144_000.0, (float) $lr['total']['hpp']);
        $this->assertSame(36_000.0, (float) $lr['total']['laba_kotor']);
        $this->assertSame(144_000.0, (float) $lr['total']['hak_mitra']);

        $this->assertSame($trx->estimasiProfit(), (float) $lr['total']['laba_kotor']);
    }

    // =================================================================
    // FORM TIDAK LAGI MENYEBUT TITIP JUAL
    // =================================================================

    public function test_form_tidak_menawarkan_jenis_penjualan_atau_fee(): void
    {
        $halaman = $this->actingAs($this->timKoperasi)
            ->get(route('koperasi.penjualan.create', ['unit' => $this->unit->id]))
            ->assertOk();

        $halaman->assertDontSee('Titip Jual');
        $halaman->assertDontSee('Fee Koperasi');
        $halaman->assertSee('Hak Mitra');
    }

    // =================================================================
    // EDIT MENGHITUNG ULANG
    // =================================================================

    public function test_edit_menghitung_ulang_seluruh_angka(): void
    {
        $produk = $this->produk('Pop Corn', 12_000, 15_000);
        $trx = $this->simpan([['produk' => $produk, 'qty' => 12]]);

        $this->actingAs($this->timKoperasi)
            ->put(route('koperasi.penjualan.update', $trx), [
                'unit_id' => $this->unit->id,
                'tanggal' => now()->toDateString(),
                'status_transaksi' => 'Aktif',
                'status_pembayaran' => 'Lunas',
                'metode_pembayaran' => 'Cash',
                'metode_pengiriman' => 'Ambil di Tempat',
                'customer_nama' => 'Budi',
                'items_master' => [[
                    'product_id' => $produk->id,
                    'qty' => 20,
                    'harga_jual' => 14_000,
                    'harga_modal' => 12_000,
                    'hak_mitra_per_unit' => 1_000,
                ]],
            ])
            ->assertSessionHasNoErrors();

        $segar = $trx->fresh()->load('items');

        $this->assertSame(280_000.0, (float) $segar->items->sum('subtotal'));
        $this->assertSame(240_000.0, $segar->totalHpp());
        $this->assertSame(40_000.0, $segar->estimasiProfit());
        $this->assertSame(20_000.0, $segar->totalHakMitra());
    }

    // =================================================================
    // OTORISASI
    // =================================================================

    public function test_peran_lain_tidak_bisa_membuat_penjualan(): void
    {
        foreach ([User::ROLE_PIMPINAN, User::ROLE_PENDAMPING] as $role) {
            $pelaku = User::create([
                'name' => 'Akun ' . $role,
                'email' => $role . '@mitra.test',
                'password' => bcrypt('rahasia123'),
                'role' => $role,
                'is_active' => true,
            ]);

            $this->actingAs($pelaku)
                ->get(route('koperasi.penjualan.create', ['unit' => $this->unit->id]))
                ->assertForbidden();
        }
    }
}
