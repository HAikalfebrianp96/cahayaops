<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\BusinessUnit;
use App\Models\Customer;
use App\Models\Pendamping;
use App\Models\ProductService;
use App\Models\SalesTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * KOREKSI TRANSAKSI YANG UNIT USAHANYA SUDAH DIARSIPKAN.
 *
 * ==========================================================================
 * BUG YANG DIJAGA BERKAS INI
 * ==========================================================================
 *
 * BusinessUnit memakai SoftDeletes. Relasi `businessUnit()` biasa berhenti
 * menemukan unitnya begitu unit itu diarsipkan — padahal transaksi lamanya
 * tetap milik unit tersebut. Akibatnya halaman Koreksi seluruh transaksi unit
 * itu mati dengan TypeError.
 *
 * Yang membuatnya berbahaya adalah JARAKNYA: mengarsipkan unit usaha berhasil
 * tanpa peringatan apa pun, dan kerusakannya baru terlihat berhari-hari
 * kemudian di halaman yang sama sekali berbeda. Tidak ada yang akan
 * menghubungkan keduanya.
 *
 * Mengarsipkan unit usaha adalah tindakan yang MEMANG didukung sistem ini —
 * ada tombolnya, ada halaman Arsipnya, ada pemulihannya. Jadi keadaan ini
 * bukan kejadian langka, melainkan akibat wajar dari pemakaian normal.
 */
class EditPenjualanUnitTerarsipTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $timKoperasi;
    private User $pendamping;
    private User $pimpinan;
    private BusinessUnit $unit;
    private Customer $pelanggan;
    private ProductService $produk;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin', 'email' => 'admin@ybmpln.id',
            'password' => bcrypt('rahasia123'), 'role' => 'admin', 'is_active' => true,
        ]);
        $this->timKoperasi = User::create([
            'name' => 'Tim Koperasi', 'email' => 'koperasi@ybmpln.id',
            'password' => bcrypt('rahasia123'), 'role' => 'tim_koperasi', 'is_active' => true,
        ]);
        $this->pimpinan = User::create([
            'name' => 'Pimpinan', 'email' => 'pimpinan@ybmpln.id',
            'password' => bcrypt('rahasia123'), 'role' => 'pimpinan', 'is_active' => true,
        ]);
        $profil = Pendamping::create(['nama' => 'Rosa Linda', 'nomor_hp' => '0812', 'status' => 'Aktif']);
        $this->pendamping = User::create([
            'name' => 'Rosa Linda', 'email' => 'rosa@ybmpln.id',
            'password' => bcrypt('rahasia123'), 'role' => 'pendamping', 'is_active' => true,
            'pendamping_id' => $profil->id,
        ]);

        $this->unit = BusinessUnit::create([
            'nama' => 'Cahaya Sembako', 'kode' => 'CS', 'tipe' => 'Retail', 'status' => 'Aktif',
        ]);
        $this->produk = ProductService::create([
            'unit_id' => $this->unit->id, 'kode' => 'BRS-001', 'nama' => 'Beras 5kg',
            'jenis' => 'Produk', 'harga_modal' => 55_000, 'harga_jual' => 62_000,
            'status' => 'Aktif', 'stock_tracking' => false,
        ]);
        $this->pelanggan = Customer::create(['nama' => 'Warung Bu Sri', 'nomor_hp' => '0813']);
    }

    private function buatTransaksi(int $qty = 2): SalesTransaction
    {
        $this->actingAs($this->admin)
            ->post(route('koperasi.penjualan.store'), $this->payload($qty))
            ->assertSessionHasNoErrors();

        return SalesTransaction::with('items')->latest('id')->firstOrFail();
    }

    private function payload(int $qty = 2, array $ganti = []): array
    {
        return array_merge([
            'unit_id' => $this->unit->id,
            'tanggal' => now()->toDateString(),
            'status_transaksi' => 'Selesai',
            'customer_id' => $this->pelanggan->id,
            'items_master' => [['product_id' => $this->produk->id, 'qty' => $qty]],
            'metode_pengiriman' => 'Ambil di Tempat',
            'status_pembayaran' => 'Lunas',
            'metode_pembayaran' => 'Cash',
        ], $ganti);
    }

    // ==================================================================
    // A. HALAMAN KOREKSI TERBUKA
    // ==================================================================

    public function test_koreksi_transaksi_dengan_unit_aktif_terbuka(): void
    {
        $trx = $this->buatTransaksi();

        $this->actingAs($this->admin)
            ->get(route('koperasi.penjualan.edit', $trx))
            ->assertOk()
            ->assertSee('Cahaya Sembako');
    }

    public function test_koreksi_transaksi_dengan_unit_terarsip_tetap_terbuka(): void
    {
        // Inti bug-nya. Sebelum perbaikan, baris ini melempar
        // TypeError: formData(): Argument #1 ($unit) must be of type
        // BusinessUnit, null given.
        $trx = $this->buatTransaksi();

        $this->unit->delete();

        $this->actingAs($this->admin)
            ->get(route('koperasi.penjualan.edit', $trx))
            ->assertOk()
            ->assertSee('Cahaya Sembako')
            ->assertSee('Unit diarsipkan');
    }

    public function test_unit_yang_masih_dipakai_transaksi_tidak_bisa_hilang_dari_database(): void
    {
        // Ditulis setelah percobaan pertama gagal, dan hasilnya justru kabar
        // baik: foreign key MENOLAK penghapusan unit yang masih dirujuk
        // transaksi. Artinya transaksi tidak mungkin kehilangan unitnya
        // sungguhan — yang mungkin terjadi hanya unit yang DIARSIPKAN, dan
        // itulah keadaan yang diperbaiki berkas ini.
        //
        // Tanda tangan formData() tetap dibuat nullable sebagai lapis
        // terakhir, tapi jaminan sesungguhnya ada di sini, di skema.
        $trx = $this->buatTransaksi();

        $this->expectException(\Illuminate\Database\QueryException::class);

        BusinessUnit::withTrashed()->where('id', $this->unit->id)->forceDelete();
    }

    public function test_baris_produk_tetap_tampil_walau_unitnya_terarsip(): void
    {
        // Kalau daftar produknya kosong, baris transaksi kehilangan isinya dan
        // ikut terhapus begitu form disimpan ulang — kerusakan diam-diam yang
        // jauh lebih mahal daripada halaman yang error.
        $trx = $this->buatTransaksi();
        $this->unit->delete();

        $this->actingAs($this->admin)
            ->get(route('koperasi.penjualan.edit', $trx))
            ->assertOk()
            ->assertSee('Beras 5kg');
    }

    public function test_produk_yang_ikut_diarsipkan_tetap_tampil_di_barisnya(): void
    {
        $trx = $this->buatTransaksi();
        $this->produk->delete();

        $this->actingAs($this->admin)
            ->get(route('koperasi.penjualan.edit', $trx))
            ->assertOk()
            ->assertSee('Beras 5kg');
    }

    // ==================================================================
    // B. MEMBUKA HALAMAN TIDAK MENGUBAH APA PUN
    // ==================================================================

    public function test_membuka_halaman_koreksi_tidak_mengubah_transaksi(): void
    {
        $trx = $this->buatTransaksi();
        $this->unit->delete();

        $sebelum = $trx->fresh()->toArray();
        $itemSebelum = $trx->items()->get()->toArray();

        $this->actingAs($this->admin)->get(route('koperasi.penjualan.edit', $trx))->assertOk();

        $this->assertSame($sebelum, $trx->fresh()->toArray());
        $this->assertSame($itemSebelum, $trx->items()->get()->toArray());
    }

    public function test_membuka_halaman_koreksi_tidak_memulihkan_unit_terarsip(): void
    {
        // Perbaikan ini hanya boleh MEMBACA unit terarsip, bukan diam-diam
        // menghidupkannya kembali ke daftar unit aktif.
        $trx = $this->buatTransaksi();
        $this->unit->delete();

        $this->actingAs($this->admin)->get(route('koperasi.penjualan.edit', $trx))->assertOk();

        $this->assertSoftDeleted('business_units', ['id' => $this->unit->id]);
    }

    // ==================================================================
    // C. SIMPAN KOREKSI
    // ==================================================================

    public function test_koreksi_tersimpan_walau_unitnya_terarsip(): void
    {
        // Formulirnya harus benar-benar bisa dikirim, bukan cuma terbuka.
        // unit_id transaksi lama menunjuk unit terarsip, dan aturan validasi
        // tidak boleh menolaknya — kalau ditolak, transaksi itu jadi mustahil
        // dikoreksi selamanya.
        $trx = $this->buatTransaksi();
        $this->unit->delete();

        $this->actingAs($this->admin)
            ->put(route('koperasi.penjualan.update', $trx), $this->payload(5))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $trx->refresh();
        $this->assertSame(5, (int) $trx->items()->first()->qty);
        $this->assertEqualsWithDelta(310_000, (float) $trx->total, 0.01);
    }

    public function test_unit_transaksi_tidak_berpindah_karena_koreksi(): void
    {
        $trx = $this->buatTransaksi();
        $unitLain = BusinessUnit::create([
            'nama' => 'Cahaya Mart', 'kode' => 'CM', 'tipe' => 'Retail', 'status' => 'Aktif',
        ]);
        $this->unit->delete();

        // Walau permintaan mencantumkan unit lain, transaksinya TIDAK pindah:
        // memindahkannya akan menggeser angka penjualan ke laporan laba rugi
        // unit yang salah.
        $this->actingAs($this->admin)
            ->put(route('koperasi.penjualan.update', $trx), $this->payload(2, ['unit_id' => $unitLain->id]))
            ->assertRedirect();

        $this->assertSame($this->unit->id, $trx->fresh()->unit_id);
    }

    public function test_koreksi_tanpa_unit_id_ditolak_validasi(): void
    {
        $trx = $this->buatTransaksi();
        $this->unit->delete();

        $payload = $this->payload();
        unset($payload['unit_id']);

        $this->actingAs($this->admin)
            ->put(route('koperasi.penjualan.update', $trx), $payload)
            ->assertSessionHasErrors('unit_id');

        // Transaksinya tidak berubah sedikit pun oleh percobaan yang gagal.
        $this->assertSame(2, (int) $trx->fresh()->items()->first()->qty);
    }

    // ==================================================================
    // D. HPP SNAPSHOT
    // ==================================================================

    public function test_hpp_snapshot_aman_saat_unit_terarsip_dan_master_berubah(): void
    {
        $trx = $this->buatTransaksi();
        $this->unit->delete();

        $this->assertEqualsWithDelta(55_000, (float) $trx->items()->first()->harga_modal_snapshot, 0.01);

        $this->produk->update(['harga_modal' => 60_000]);

        // Master berubah, histori tidak.
        $this->assertEqualsWithDelta(55_000, (float) $trx->fresh()->items()->first()->harga_modal_snapshot, 0.01);
    }

    public function test_hpp_boleh_dikoreksi_manual_saat_transaksi_diedit(): void
    {
        $trx = $this->buatTransaksi();
        $this->unit->delete();

        $this->actingAs($this->admin)
            ->put(route('koperasi.penjualan.update', $trx), $this->payload(2, [
                'items_master' => [[
                    'product_id' => $this->produk->id, 'qty' => 2, 'harga_modal' => 58_000,
                ]],
            ]))
            ->assertSessionHasNoErrors();

        $this->assertEqualsWithDelta(58_000, (float) $trx->fresh()->items()->first()->harga_modal_snapshot, 0.01);
        // Master TIDAK ikut berubah oleh koreksi transaksi.
        $this->assertEqualsWithDelta(55_000, (float) $this->produk->fresh()->harga_modal, 0.01);
    }

    // ==================================================================
    // E. OTORISASI & JEJAK
    // ==================================================================

    public function test_otorisasi_tetap_berlaku_pada_unit_terarsip(): void
    {
        $trx = $this->buatTransaksi();
        $this->unit->delete();

        $this->actingAs($this->timKoperasi)->get(route('koperasi.penjualan.edit', $trx))->assertOk();
        $this->actingAs($this->pendamping)->get(route('koperasi.penjualan.edit', $trx))->assertForbidden();
        $this->actingAs($this->pimpinan)->get(route('koperasi.penjualan.edit', $trx))->assertForbidden();
    }

    public function test_transaksi_dibatalkan_tetap_tidak_bisa_diedit(): void
    {
        $trx = $this->buatTransaksi();
        $this->actingAs($this->admin)->post(route('koperasi.penjualan.batalkan', $trx), [
            'alasan_pembatalan' => 'pesanan batal',
        ]);
        $this->unit->delete();

        $this->actingAs($this->admin)->get(route('koperasi.penjualan.edit', $trx))->assertForbidden();
    }

    public function test_pembatalan_dan_pemulihan_tetap_tercatat_walau_unit_terarsip(): void
    {
        $trx = $this->buatTransaksi();
        $this->unit->delete();

        $this->actingAs($this->admin)->post(route('koperasi.penjualan.batalkan', $trx), [
            'alasan_pembatalan' => 'unit tutup',
        ])->assertRedirect();
        $this->actingAs($this->admin)->post(route('koperasi.penjualan.pulihkan', $trx))->assertRedirect();

        $this->assertDatabaseHas('activity_logs', ['action' => 'sales_transaction_cancelled']);
        $this->assertDatabaseHas('activity_logs', ['action' => 'sales_transaction_restored']);
    }

    // ==================================================================
    // F. TRANSAKSI LAIN TIDAK TERPENGARUH
    // ==================================================================

    public function test_transaksi_unit_lain_tidak_terpengaruh(): void
    {
        $trxSembako = $this->buatTransaksi();

        $unitLain = BusinessUnit::create([
            'nama' => 'Cahaya Mart', 'kode' => 'CM', 'tipe' => 'Retail', 'status' => 'Aktif',
        ]);
        $produkLain = ProductService::create([
            'unit_id' => $unitLain->id, 'kode' => 'KRP-001', 'nama' => 'Keripik',
            'jenis' => 'Produk', 'harga_modal' => 8_000, 'harga_jual' => 15_000,
            'status' => 'Aktif', 'stock_tracking' => false,
        ]);

        $this->actingAs($this->admin)->post(route('koperasi.penjualan.store'), [
            'unit_id' => $unitLain->id,
            'tanggal' => now()->toDateString(),
            'status_transaksi' => 'Selesai',
            'customer_id' => $this->pelanggan->id,
            'items_master' => [['product_id' => $produkLain->id, 'qty' => 3]],
            'metode_pengiriman' => 'Ambil di Tempat',
            'status_pembayaran' => 'Lunas',
            'metode_pembayaran' => 'Cash',
        ])->assertSessionHasNoErrors();

        $trxMart = SalesTransaction::latest('id')->firstOrFail();
        $totalMartSebelum = (float) $trxMart->total;

        $this->unit->delete();

        // Koreksi transaksi milik unit terarsip tidak menyentuh transaksi lain.
        $this->actingAs($this->admin)
            ->put(route('koperasi.penjualan.update', $trxSembako), $this->payload(4))
            ->assertSessionHasNoErrors();

        $this->assertEqualsWithDelta($totalMartSebelum, (float) $trxMart->fresh()->total, 0.01);
        $this->assertSame($unitLain->id, $trxMart->fresh()->unit_id);
        $this->assertSame(3, (int) $trxMart->fresh()->items()->first()->qty);
    }

    public function test_daftar_penjualan_tetap_terbuka_saat_ada_unit_terarsip(): void
    {
        $this->buatTransaksi();
        $this->unit->delete();

        $this->actingAs($this->admin)->get(route('koperasi.penjualan.index'))->assertOk();
    }

    public function test_detail_dan_invoice_tetap_terbuka_saat_unit_terarsip(): void
    {
        $trx = $this->buatTransaksi();
        $this->actingAs($this->admin)->post(route('koperasi.invoice.store', $trx))->assertRedirect();

        $this->unit->delete();

        $this->actingAs($this->admin)->get(route('koperasi.penjualan.show', $trx))->assertOk();
        $this->actingAs($this->admin)->get(route('koperasi.invoice.show', $trx))->assertOk();
    }

    // ==================================================================
    // G. RELASI ITU SENDIRI
    // ==================================================================

    public function test_relasi_biasa_sengaja_tetap_menyembunyikan_unit_terarsip(): void
    {
        // Pembeda kedua relasi ini disengaja: daftar & laporan memakai relasi
        // biasa dan memang harus berhenti menampilkan unit terarsip; hanya
        // halaman riwayat yang melihat ke belakang.
        $trx = $this->buatTransaksi();
        $this->unit->delete();

        $trx = $trx->fresh();

        $this->assertNull($trx->businessUnit);
        $this->assertNotNull($trx->businessUnitTermasukArsip);
        $this->assertSame('Cahaya Sembako', $trx->businessUnitTermasukArsip->nama);
        $this->assertTrue($trx->businessUnitTermasukArsip->trashed());
    }
}
