<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\BusinessUnit;
use App\Models\ProductService;
use App\Models\SalesTransaction;
use App\Models\SalesTransactionItem;
use App\Models\User;
use App\Services\LabaRugiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * KOREKSI HPP SNAPSHOT — dari kasus nyata TRX-202609-000022.
 *
 * 35 paper bag dijual Rp10.000/pcs, tapi HPP snapshot-nya tersimpan
 * Rp9.500.000/pcs (seharusnya Rp9.500). Satu baris itu membuat Laba Rugi
 * koperasi minus Rp332 juta. Test ini menjaga tiga hal:
 *
 *  1. Koreksi lewat Edit Penjualan memperbaiki snapshot, menghitung ulang
 *     laba rugi, dan MENINGGALKAN JEJAK — sebelumnya tidak, karena baris
 *     transaksinya sendiri tidak berubah.
 *  2. HPP > harga jual memunculkan peringatan, tapi TIDAK menolak.
 *  3. Perubahan harga di master produk tercatat dari berapa menjadi berapa.
 */
class KoreksiHppSnapshotTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private BusinessUnit $unit;
    private ProductService $paperBag;
    private ProductService $popCorn;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@hpp.test',
            'password' => bcrypt('rahasia123'),
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
        ]);

        $this->unit = BusinessUnit::create([
            'nama' => 'Cahaya Mart', 'kode' => 'UNIT-HPP', 'tipe' => 'Barang', 'status' => 'Aktif',
        ]);

        $this->paperBag = $this->produk('PaperBag Oleh-oleh', 9_500, 10_000);
        $this->popCorn = $this->produk('Pop Corn', 10_437, 11_000);
    }

    private function produk(string $nama, float $modal, float $jual): ProductService
    {
        return ProductService::create([
            'unit_id' => $this->unit->id,
            'nama' => $nama,
            'jenis' => 'Produk',
            'satuan' => 'Pcs',
            'harga_modal' => $modal,
            'harga_jual' => $jual,
            'status' => 'Aktif',
        ]);
    }

    /** @param list<array{0: ProductService, 1: int, 2: float, 3: float}> $baris [produk, qty, jual, hpp] */
    private function payload(array $baris): array
    {
        return [
            'unit_id' => $this->unit->id,
            'tanggal' => now()->toDateString(),
            'status_transaksi' => 'Aktif',
            'status_pembayaran' => 'Lunas',
            'metode_pembayaran' => 'Cash',
            'metode_pengiriman' => 'Ambil di Tempat',
            'customer_nama' => 'Pelanggan Toko',
            'items_master' => array_map(fn ($b) => [
                'product_id' => $b[0]->id,
                'qty' => $b[1],
                'harga_jual' => $b[2],
                'harga_modal' => $b[3],
            ], $baris),
        ];
    }

    /** Transaksi dengan HPP salah ketik, persis seperti kasusnya. */
    private function transaksiSalahKetik(): SalesTransaction
    {
        $this->actingAs($this->admin)
            ->post(route('koperasi.penjualan.store'), $this->payload([
                [$this->paperBag, 35, 10_000, 9_500_000],
                [$this->popCorn, 3, 11_000, 10_437],
            ]))
            ->assertSessionHasNoErrors();

        return SalesTransaction::with('items')->latest('id')->firstOrFail();
    }

    private function koreksi(SalesTransaction $trx): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->admin)
            ->put(route('koperasi.penjualan.update', $trx), $this->payload([
                [$this->paperBag, 35, 10_000, 9_500],
                [$this->popCorn, 3, 11_000, 10_437],
            ]));
    }

    // =================================================================
    // 1. EDIT HPP SNAPSHOT
    // =================================================================

    public function test_edit_penjualan_mengoreksi_hpp_snapshot_tanpa_menyentuh_master(): void
    {
        $trx = $this->transaksiSalahKetik();
        $this->assertSame(9_500_000.0, (float) $trx->items->firstWhere('nama_snapshot', 'PaperBag Oleh-oleh')->harga_modal_snapshot);

        $this->koreksi($trx)->assertSessionHasNoErrors()->assertRedirect(route('koperasi.penjualan.show', $trx));

        $trx->refresh()->load('items');
        $item = $trx->items->firstWhere('nama_snapshot', 'PaperBag Oleh-oleh');

        $this->assertSame(9_500.0, (float) $item->harga_modal_snapshot);
        $this->assertSame(35, (int) $item->qty);
        $this->assertSame(10_000.0, (float) $item->harga_jual_snapshot);
        $this->assertSame(350_000.0, (float) $item->subtotal);

        // Penjualan & tagihan tidak berubah — yang dikoreksi hanya modal.
        $this->assertSame(383_000.0, (float) $trx->total);
        $this->assertSame(383_000.0, (float) $trx->dp_nominal);

        // Master produk tidak ikut diubah.
        $this->assertSame(9_500.0, (float) $this->paperBag->fresh()->harga_modal);
    }

    public function test_laba_rugi_dihitung_ulang_setelah_hpp_dikoreksi(): void
    {
        $trx = $this->transaksiSalahKetik();
        $service = app(LabaRugiService::class);

        $sebelum = $service->hitung('default', 'default', null)['total'];
        $this->assertSame(383_000.0, (float) $sebelum['penjualan']);
        $this->assertSame(332_531_311.0, (float) $sebelum['hpp']);
        $this->assertLessThan(0, $sebelum['laba_kotor']);

        $this->koreksi($trx);

        $sesudah = $service->hitung('default', 'default', null)['total'];

        // 35 x 9.500 + 3 x 10.437
        $this->assertSame(383_000.0, (float) $sesudah['penjualan']);
        $this->assertSame(363_811.0, (float) $sesudah['hpp']);
        $this->assertSame(19_189.0, (float) $sesudah['laba_kotor']);
        $this->assertSame((float) $sesudah['laba_kotor'] + (float) $sesudah['fee_koperasi'], (float) $sesudah['kontribusi_usaha']);
        $this->assertSame((float) $sesudah['kontribusi_usaha'] - (float) $sesudah['pengeluaran_total'], (float) $sesudah['laba_bersih']);

        $this->assertSame($trx->fresh()->load('items')->estimasiProfit(), (float) $sesudah['laba_kotor']);
    }

    public function test_koreksi_hpp_tercatat_di_activity_log(): void
    {
        $trx = $this->transaksiSalahKetik();

        $this->koreksi($trx);

        $log = ActivityLog::where('action', 'sales_items_updated')
            ->where('model_id', $trx->id)
            ->sole();

        $this->assertSame('SalesTransaction', $log->model_type);
        $this->assertSame($this->admin->id, $log->user_id);
        $this->assertStringContainsString($trx->nomor_transaksi, $log->description);
        $this->assertStringContainsString('PaperBag Oleh-oleh HPP Rp9.500.000 -> Rp9.500', $log->description);

        // Baris yang tidak diubah tidak ikut disebut.
        $this->assertStringNotContainsString('Pop Corn', $log->description);
    }

    public function test_simpan_ulang_tanpa_perubahan_item_tidak_mencatat_perubahan_item(): void
    {
        $trx = $this->transaksiSalahKetik();

        $this->actingAs($this->admin)
            ->put(route('koperasi.penjualan.update', $trx), $this->payload([
                [$this->paperBag, 35, 10_000, 9_500_000],
                [$this->popCorn, 3, 11_000, 10_437],
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame(0, ActivityLog::where('action', 'sales_items_updated')->count());
    }

    public function test_perubahan_qty_dan_item_baru_tercatat(): void
    {
        $trx = $this->transaksiSalahKetik();
        $kopi = $this->produk('Kopi Bubuk', 33_441, 35_000);

        $this->actingAs($this->admin)
            ->put(route('koperasi.penjualan.update', $trx), $this->payload([
                [$this->paperBag, 35, 10_000, 9_500_000],
                [$this->popCorn, 5, 11_000, 10_437],
                [$kopi, 1, 35_000, 33_441],
            ]));

        $log = ActivityLog::where('action', 'sales_items_updated')->sole();
        $this->assertStringContainsString('Pop Corn qty 3 -> 5', $log->description);
        $this->assertStringContainsString('Kopi Bubuk ditambahkan', $log->description);
    }

    // =================================================================
    // 2. PERINGATAN HPP TIDAK WAJAR
    // =================================================================

    public function test_hpp_lebih_besar_dari_harga_jual_memberi_peringatan_tapi_tetap_tersimpan(): void
    {
        $jumlahAwal = SalesTransaction::count();

        $respons = $this->actingAs($this->admin)
            ->post(route('koperasi.penjualan.store'), $this->payload([
                [$this->paperBag, 35, 10_000, 9_500_000],
            ]));

        $respons->assertSessionHasNoErrors();
        $this->assertSame($jumlahAwal + 1, SalesTransaction::count());

        $peringatan = session('warning');
        $this->assertNotNull($peringatan);
        $this->assertStringContainsString('Perhatian: HPP Rp9.500.000 lebih besar dari harga jual Rp10.000', $peringatan);
        $this->assertStringContainsString('Periksa kembali nilai modal', $peringatan);
        $this->assertStringContainsString('salah ketik', $peringatan);
    }

    public function test_peringatan_tampil_di_halaman_setelah_simpan(): void
    {
        $this->actingAs($this->admin)
            ->followingRedirects()
            ->post(route('koperasi.penjualan.store'), $this->payload([
                [$this->paperBag, 1, 10_000, 15_000],
            ]))
            ->assertOk()
            ->assertSee('Perhatian: HPP Rp15.000 lebih besar dari harga jual Rp10.000', false);
    }

    public function test_edit_dengan_hpp_tidak_wajar_juga_memberi_peringatan(): void
    {
        $trx = $this->transaksiSalahKetik();

        $this->actingAs($this->admin)
            ->put(route('koperasi.penjualan.update', $trx), $this->payload([
                [$this->paperBag, 35, 10_000, 12_000],
            ]))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('warning');
    }

    public function test_hpp_wajar_tidak_memberi_peringatan(): void
    {
        $this->actingAs($this->admin)
            ->post(route('koperasi.penjualan.store'), $this->payload([
                [$this->paperBag, 35, 10_000, 9_500],
                [$this->popCorn, 3, 11_000, 11_000], // sama dengan harga jual: wajar
            ]))
            ->assertSessionHasNoErrors()
            ->assertSessionMissing('warning');

        $trx = SalesTransaction::latest('id')->firstOrFail();

        $this->koreksi($trx)->assertSessionMissing('warning');
    }

    public function test_aturan_peringatan_hpp(): void
    {
        $this->assertNull(SalesTransactionItem::peringatanHpp('A', 9_500, 10_000));
        $this->assertNull(SalesTransactionItem::peringatanHpp('A', 10_000, 10_000));

        $sedikit = SalesTransactionItem::peringatanHpp('A', 12_000, 10_000);
        $this->assertStringContainsString('lebih besar dari harga jual', $sedikit);
        $this->assertStringNotContainsString('salah ketik', $sedikit);

        $jauh = SalesTransactionItem::peringatanHpp('A', 100_000, 10_000);
        $this->assertStringContainsString('10×', $jauh);
        $this->assertStringContainsString('salah ketik', $jauh);

        // Barang gratis (harga jual 0) yang punya modal tetap diingatkan.
        $this->assertNotNull(SalesTransactionItem::peringatanHpp('A', 5_000, 0));
    }

    public function test_tambah_produk_cepat_mengembalikan_peringatan_tanpa_menolak(): void
    {
        $this->actingAs($this->admin)
            ->postJson(route('koperasi.produk.quick-store', $this->unit), [
                'nama' => 'PaperBag Besar', 'jenis' => 'Produk', 'satuan' => 'Pcs',
                'harga_jual' => 15_000, 'harga_modal' => 333_000,
            ])
            ->assertOk()
            ->assertJsonPath('harga_modal', 333000)
            ->assertJsonPath('peringatan', fn ($p) => str_contains($p, 'Perhatian: HPP Rp333.000 lebih besar dari harga jual Rp15.000'));

        $this->assertDatabaseHas('products_services', ['nama' => 'PaperBag Besar']);

        $this->actingAs($this->admin)
            ->postJson(route('koperasi.produk.quick-store', $this->unit), [
                'nama' => 'PaperBag Kecil', 'jenis' => 'Produk',
                'harga_jual' => 8_000, 'harga_modal' => 6_000,
            ])
            ->assertOk()
            ->assertJsonPath('peringatan', null);
    }

    public function test_form_penjualan_memuat_peringatan_hpp(): void
    {
        $this->actingAs($this->admin)
            ->get(route('koperasi.penjualan.create', ['unit' => $this->unit->id]))
            ->assertOk()
            ->assertSee('hpp-warning', false)
            ->assertSee('function pesanHpp', false);
    }

    public function test_ambang_hpp_jauh_bisa_diatur_lewat_config(): void
    {
        config(['cahayaos.hpp.jauh_kali' => 3]);
        $this->assertStringContainsString('salah ketik', SalesTransactionItem::peringatanHpp('A', 30_000, 10_000));

        config(['cahayaos.hpp.jauh_kali' => 50]);
        $this->assertStringNotContainsString('salah ketik', SalesTransactionItem::peringatanHpp('A', 300_000, 10_000));

        // Nilai tidak masuk akal tidak boleh mematikan aturan dasarnya.
        config(['cahayaos.hpp.jauh_kali' => 0]);
        $this->assertSame(1.0, SalesTransactionItem::ambangHppJauh());
        $this->assertNull(SalesTransactionItem::peringatanHpp('A', 9_500, 10_000));
    }

    // =================================================================
    // DETAIL PENJUALAN — HPP TERLIHAT TANPA MEMBUKA EDIT
    // =================================================================

    public function test_detail_penjualan_menampilkan_hpp_total_hpp_dan_profit_per_item(): void
    {
        $trx = $this->transaksiSalahKetik();
        $this->koreksi($trx);

        $halaman = $this->actingAs($this->admin)
            ->get(route('koperasi.penjualan.show', $trx))
            ->assertOk()
            ->assertSee('HPP / Modal per Unit')
            ->assertSee('Total HPP')
            ->assertSee('Profit');

        // PaperBag: 35 x Rp10.000 | HPP Rp9.500 | Total HPP Rp332.500 | Profit Rp17.500
        $halaman->assertSeeInOrder(['PaperBag Oleh-oleh', '35', 'Rp10.000', 'Rp350.000', 'Rp9.500', 'Rp332.500', 'Rp17.500']);
        // Total seluruh item: HPP 332.500 + 31.311, profit 17.500 + 1.689
        $halaman->assertSeeInOrder(['Total Item', 'Rp383.000', 'Rp363.811', 'Rp19.189']);
    }

    public function test_detail_penjualan_menandai_hpp_tidak_wajar(): void
    {
        $trx = $this->transaksiSalahKetik();

        $this->actingAs($this->admin)
            ->get(route('koperasi.penjualan.show', $trx))
            ->assertOk()
            ->assertSee('Rp332.500.000')
            ->assertSee('Perhatian: HPP Rp9.500.000 lebih besar dari harga jual Rp10.000', false);
    }

    public function test_tim_koperasi_melihat_hpp_di_detail_pimpinan_tidak(): void
    {
        $trx = $this->transaksiSalahKetik();

        $timKoperasi = User::create([
            'name' => 'Tim Koperasi', 'email' => 'kop@hpp.test', 'password' => bcrypt('rahasia123'),
            'role' => User::ROLE_TIM_KOPERASI, 'is_active' => true,
        ]);
        $pimpinan = User::create([
            'name' => 'Pimpinan', 'email' => 'pim@hpp.test', 'password' => bcrypt('rahasia123'),
            'role' => User::ROLE_PIMPINAN, 'is_active' => true,
        ]);

        $this->actingAs($timKoperasi)
            ->get(route('koperasi.penjualan.show', $trx))
            ->assertOk()
            ->assertSee('HPP / Modal per Unit');

        $this->actingAs($pimpinan)
            ->get(route('koperasi.penjualan.show', $trx))
            ->assertOk()
            ->assertDontSee('HPP / Modal per Unit')
            ->assertDontSee('Rp9.500.000');
    }

    // =================================================================
    // 3. ACTIVITY LOG MASTER PRODUK
    // =================================================================

    public function test_perubahan_master_produk_tercatat(): void
    {
        $this->actingAs($this->admin);

        $produk = $this->produk('Kerupuk Ikan', 9_500_000, 12_000);

        $dibuat = ActivityLog::where('action', 'product_created')->where('model_id', $produk->id)->sole();
        $this->assertSame('ProductService', $dibuat->model_type);
        $this->assertStringContainsString('HPP Rp9.500.000', $dibuat->description);
        $this->assertStringContainsString($produk->kode, $dibuat->description);

        $produk->update(['harga_modal' => 9_500, 'harga_jual' => 12_500, 'satuan' => 'Bungkus', 'nama' => 'Kerupuk Ikan Bleberan']);

        $diubah = ActivityLog::where('action', 'product_updated')->where('model_id', $produk->id)->sole();
        $this->assertStringContainsString('HPP: Rp9.500.000 -> Rp9.500', $diubah->description);
        $this->assertStringContainsString('harga jual: Rp12.000 -> Rp12.500', $diubah->description);
        $this->assertStringContainsString('satuan: Pcs -> Bungkus', $diubah->description);
        $this->assertStringContainsString('nama: Kerupuk Ikan -> Kerupuk Ikan Bleberan', $diubah->description);

        $produk->delete();
        $this->assertSame(1, ActivityLog::where('action', 'product_deleted')->where('model_id', $produk->id)->count());

        $produk->restore();
        $this->assertSame(1, ActivityLog::where('action', 'product_restored')->where('model_id', $produk->id)->count());
    }

    public function test_edit_produk_lewat_form_tercatat(): void
    {
        $this->actingAs($this->admin)
            ->put(route('koperasi.produk.update', $this->paperBag), [
                'kode' => $this->paperBag->kode,
                'nama' => $this->paperBag->nama,
                'jenis' => 'Produk',
                'satuan' => 'Pcs',
                'harga_modal' => 9_800,
                'harga_jual' => 10_000,
                'status' => 'Aktif',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(9_800.0, (float) $this->paperBag->fresh()->harga_modal);
        $this->assertStringContainsString(
            'HPP: Rp9.500 -> Rp9.800',
            ActivityLog::where('action', 'product_updated')->where('model_id', $this->paperBag->id)->sole()->description,
        );
    }

    // =================================================================
    // 4. KEUANGAN, DASHBOARD, LAPORAN — SATU SUMBER
    // =================================================================

    public function test_keuangan_dashboard_dan_laporan_memakai_hasil_laba_rugi_yang_sama_dengan_database(): void
    {
        $trx = $this->transaksiSalahKetik();
        $this->koreksi($trx);

        $db = DB::table('sales_transaction_items as i')
            ->join('sales_transactions as t', 't.id', '=', 'i.sales_transaction_id')
            ->whereNotIn('t.status_transaksi', ['Draft', 'Dibatalkan'])
            ->selectRaw('SUM(i.subtotal) as penjualan, SUM(i.qty * i.harga_modal_snapshot) as hpp')
            ->first();

        $lr = app(LabaRugiService::class)->hitung('default', 'default', null)['total'];

        $this->assertSame((float) $db->penjualan, (float) $lr['penjualan']);
        $this->assertSame((float) $db->hpp, (float) $lr['hpp']);
        $this->assertSame((float) $db->penjualan - (float) $db->hpp, (float) $lr['laba_kotor']);

        $keuangan = $this->actingAs($this->admin)->get(route('koperasi.keuangan.index'))->assertOk();
        $this->assertSame($lr, $keuangan->viewData('lr')['total']);
        $keuangan->assertSee(rupiah($lr['hpp']))->assertSee(rupiah($lr['laba_bersih']));

        $laporan = $this->actingAs($this->admin)->get(route('koperasi.laporan.laba-rugi'))->assertOk();
        $this->assertSame($lr, $laporan->viewData('lr')['total']);

        $stats = $this->actingAs($this->admin)
            ->get(route('dashboard', ['modul' => 'koperasi']))
            ->assertOk()
            ->viewData('stats');

        $this->assertSame($lr['penjualan'], $stats['penjualan_tahun_ini']);
        $this->assertSame($lr['hpp'], $stats['hpp_tahun_ini']);
        $this->assertSame($lr['laba_kotor'], $stats['laba_kotor_tahun_ini']);
        $this->assertSame($lr['pengeluaran_total'], $stats['pengeluaran_tahun_ini']);
        $this->assertSame($lr['laba_bersih'], $stats['laba_bersih_tahun_ini']);
    }

    public function test_teks_keuangan_tidak_lagi_menyebut_titip_jual(): void
    {
        $this->transaksiSalahKetik();

        $this->actingAs($this->admin)
            ->get(route('koperasi.keuangan.index'))
            ->assertOk()
            ->assertSee('Harga modal/HPP menggunakan nilai yang tersimpan pada setiap item transaksi.')
            ->assertDontSee('Titip Jual')
            ->assertSee('Total Hak Mitra');
    }
}
