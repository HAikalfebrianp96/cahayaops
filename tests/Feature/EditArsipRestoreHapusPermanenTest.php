<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\BusinessUnit;
use App\Models\Customer;
use App\Models\HppCalculation;
use App\Models\KoperasiMember;
use App\Models\MemberSaving;
use App\Models\Participant;
use App\Models\Pendamping;
use App\Models\Pengeluaran;
use App\Models\ProductService;
use App\Models\SalesTransaction;
use App\Models\User;
use App\Services\LabaRugiService;
use App\Services\PenghapusanPermanenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TIGA TINGKAT PERLAKUAN DATA: EDIT, ARSIP/BATAL, HAPUS PERMANEN.
 *
 * ==========================================================================
 * YANG DIJAGA BERKAS INI
 * ==========================================================================
 *
 * Sistem ini harus longgar untuk manusia tapi ketat untuk uang. Dua sifat itu
 * saling tarik-menarik, dan yang menjaga keseimbangannya cuma aturan-aturan
 * kecil yang mudah hilang saat kode disentuh lagi:
 *
 *   - membatalkan HARUS mengeluarkan angkanya dari laporan aktif,
 *   - memulihkan HARUS mengembalikannya TANPA terhitung dua kali,
 *   - menghapus permanen HARUS menolak data yang punya jejak,
 *   - dan semuanya HARUS meninggalkan catatan.
 *
 * Kesalahan pada aturan-aturan itu tidak memunculkan error. Ia hanya membuat
 * angka di laporan keuangan salah — dan itu baru ketahuan berbulan-bulan
 * kemudian, saat sudah tidak ada yang ingat apa yang berubah.
 */
class EditArsipRestoreHapusPermanenTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $timKoperasi;
    private User $pendamping;
    private User $pimpinan;
    private BusinessUnit $unit;
    private Customer $pelanggan;

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

        $profil = Pendamping::create(['nama' => 'Rosa Linda', 'nomor_hp' => '081234567890', 'status' => 'Aktif']);
        $this->pendamping = User::create([
            'name' => 'Rosa Linda', 'email' => 'rosa@ybmpln.id',
            'password' => bcrypt('rahasia123'), 'role' => 'pendamping', 'is_active' => true,
            'pendamping_id' => $profil->id,
        ]);

        $this->unit = BusinessUnit::create(['nama' => 'Cahaya Mart', 'kode' => 'CM', 'tipe' => 'Retail', 'status' => 'Aktif']);
        $this->pelanggan = Customer::create(['nama' => 'Warung Bu Sri', 'nomor_hp' => '081200000009']);
    }

    // ==================================================================
    // PEMBANTU
    // ==================================================================

    private int $nomorProduk = 0;

    private function produk(float $jual = 15000, float $modal = 8000): ProductService
    {
        return ProductService::create([
            'unit_id' => $this->unit->id,
            'kode' => 'KRP-' . str_pad((string) ++$this->nomorProduk, 3, '0', STR_PAD_LEFT),
            'nama' => 'Keripik Pisang', 'jenis' => 'Produk',
            'satuan' => 'bungkus', 'harga_modal' => $modal, 'harga_jual' => $jual,
            'status' => 'Aktif', 'stock_tracking' => false,
        ]);
    }

    /**
     * Satu transaksi penjualan, dibuat lewat endpoint SUNGGUHAN.
     *
     * Sengaja tidak merakit modelnya dengan tangan: yang sedang diuji adalah
     * perilaku pembatalan & pemulihan pada transaksi yang benar-benar
     * terbentuk lewat alur aplikasi, lengkap dengan snapshot harganya.
     */
    private function transaksi(int $qty = 10): SalesTransaction
    {
        $produk = $this->produk();

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

        return SalesTransaction::with('items')->latest('id')->firstOrFail();
    }

    private function batalkan(SalesTransaction $t): void
    {
        $this->actingAs($this->admin)
            ->post(route('koperasi.penjualan.batalkan', $t), ['alasan_pembatalan' => 'pesanan batal'])
            ->assertRedirect();
    }

    private function omzetPenjualanAktif(): float
    {
        return (float) SalesTransaction::whereNotIn('status_transaksi', ['Draft', 'Dibatalkan'])->sum('total');
    }

    // ==================================================================
    // A. EDIT — siapa boleh, dan apakah tercatat  (AJ 1-5)
    // ==================================================================

    public function test_admin_dapat_membuka_edit_transaksi(): void
    {
        $t = $this->transaksi();

        $this->actingAs($this->admin)
            ->get(route('koperasi.penjualan.edit', $t))
            ->assertOk();
    }

    public function test_tim_koperasi_dapat_membuka_edit_transaksi(): void
    {
        // Sebelumnya tombolnya disembunyikan Blade dengan syarat isAdmin(),
        // padahal controllernya memakai bolehKelolaKoperasi() — kewenangannya
        // ada, tombolnya yang hilang.
        $t = $this->transaksi();

        $this->actingAs($this->timKoperasi)
            ->get(route('koperasi.penjualan.edit', $t))
            ->assertOk();
    }

    public function test_tombol_edit_terlihat_oleh_tim_koperasi(): void
    {
        $t = $this->transaksi();

        $this->actingAs($this->timKoperasi)
            ->get(route('koperasi.penjualan.show', $t))
            ->assertOk()
            ->assertSee('Edit Transaksi');
    }

    public function test_pendamping_ditolak_mengedit_transaksi_koperasi(): void
    {
        $t = $this->transaksi();

        $this->actingAs($this->pendamping)
            ->get(route('koperasi.penjualan.edit', $t))
            ->assertForbidden();
    }

    public function test_pimpinan_hanya_membaca_tidak_bisa_mengedit(): void
    {
        $t = $this->transaksi();

        $this->actingAs($this->pimpinan)->get(route('koperasi.penjualan.show', $t))->assertOk();
        $this->actingAs($this->pimpinan)->get(route('koperasi.penjualan.edit', $t))->assertForbidden();
    }

    public function test_koreksi_hpp_tercatat_dengan_nilai_sebelum_dan_sesudah(): void
    {
        $peserta = Participant::create([
            'nama_peserta' => 'Bu Sari', 'nama_usaha' => 'Keripik Sari',
            'nomor_hp' => '081200000003', 'kategori_usaha' => 'Kuliner',
            'tanggal_mulai_program' => now()->subYear()->toDateString(),
            'pendamping_id' => $this->pendamping->pendamping_id, 'status' => 'Aktif',
        ]);

        $this->actingAs($this->admin)->post(route('hpp.store', $peserta), [
            'nama_produk' => 'Keripik', 'satuan_produksi' => 'bungkus',
            'jumlah_produksi' => 100, 'harga_jual' => 15000,
            'komponen' => [['kategori' => 'Bahan Baku', 'nama_biaya' => 'Pisang', 'qty' => 1, 'satuan' => 'kg', 'harga_satuan' => 800000]],
        ])->assertRedirect();

        $hpp = HppCalculation::firstOrFail();
        $this->assertEquals(8000, $hpp->hpp_per_unit);

        // KOREKSI — harga bahan ternyata salah ketik.
        $this->actingAs($this->admin)->put(route('hpp.update', $hpp), [
            'nama_produk' => 'Keripik', 'satuan_produksi' => 'bungkus',
            'jumlah_produksi' => 100, 'harga_jual' => 15000,
            'komponen' => [['kategori' => 'Bahan Baku', 'nama_biaya' => 'Pisang', 'qty' => 1, 'satuan' => 'kg', 'harga_satuan' => 900000]],
        ])->assertRedirect();

        $hpp->refresh();
        $this->assertEquals(9000, $hpp->hpp_per_unit);

        // Komponen lama DIGANTI, bukan ditambah — kalau ditambah, totalnya
        // jadi 17.000 dan tidak ada yang menyadarinya.
        $this->assertSame(1, $hpp->components()->count());

        $log = ActivityLog::where('action', 'hpp_calculation_updated')->first();
        $this->assertNotNull($log);
        $this->assertStringContainsString('Sebelum', $log->description);
        $this->assertStringContainsString('Sesudah', $log->description);
    }

    // ==================================================================
    // B. BATALKAN — berhenti dihitung, histori tetap  (AJ 6-10)
    // ==================================================================

    public function test_transaksi_dapat_dibatalkan_dengan_alasan(): void
    {
        $t = $this->transaksi();
        $this->batalkan($t);

        $t->refresh();
        $this->assertSame('Dibatalkan', $t->status_transaksi);
        $this->assertSame('pesanan batal', $t->alasan_pembatalan);
        $this->assertNotNull($t->dibatalkan_at);
        $this->assertSame($this->admin->id, $t->dibatalkan_oleh);
    }

    public function test_pembatalan_wajib_menyertakan_alasan(): void
    {
        $t = $this->transaksi();

        $this->actingAs($this->admin)
            ->post(route('koperasi.penjualan.batalkan', $t), [])
            ->assertSessionHasErrors('alasan_pembatalan');

        // Yang penting bukan nilai persisnya, melainkan bahwa transaksi TIDAK
        // berpindah ke Dibatalkan hanya karena formnya dikirim tanpa alasan.
        $this->assertNotSame('Dibatalkan', $t->refresh()->status_transaksi);
    }

    public function test_transaksi_dibatalkan_keluar_dari_total_penjualan_aktif(): void
    {
        $t = $this->transaksi();
        $this->assertEqualsWithDelta(150000, $this->omzetPenjualanAktif(), 0.01);

        $this->batalkan($t);

        $this->assertEqualsWithDelta(0, $this->omzetPenjualanAktif(), 0.01);
    }

    public function test_transaksi_dibatalkan_keluar_dari_laba_rugi(): void
    {
        $t = $this->transaksi();
        $service = app(LabaRugiService::class);

        $sebelum = $service->hitung((string) now()->year, 'default', null);
        $this->batalkan($t);
        $sesudah = $service->hitung((string) now()->year, 'default', null);

        $this->assertGreaterThan(0, $sebelum['total']['penjualan']);
        $this->assertEqualsWithDelta(0, $sesudah['total']['penjualan'], 0.01);

        // HPP-nya ikut keluar, bukan cuma penjualannya — kalau hanya salah
        // satu yang keluar, laba bersih justru jadi lebih salah daripada
        // sebelum transaksinya dibatalkan.
        $this->assertEqualsWithDelta(0, $sesudah['total']['hpp'], 0.01);
    }

    public function test_histori_transaksi_dibatalkan_tetap_lengkap(): void
    {
        $t = $this->transaksi();
        $idItem = $t->items()->first()->id;

        $this->batalkan($t);

        // Barisnya TETAP ADA, beserta seluruh rinciannya.
        $this->assertDatabaseHas('sales_transactions', ['id' => $t->id]);
        $this->assertDatabaseHas('sales_transaction_items', ['id' => $idItem]);

        $this->actingAs($this->admin)
            ->get(route('koperasi.penjualan.show', $t))
            ->assertOk()
            ->assertSee('pesanan batal');
    }

    public function test_pengeluaran_dibatalkan_berhenti_mempengaruhi_profit(): void
    {
        $p = Pengeluaran::create([
            'tanggal' => now()->toDateString(), 'business_unit_id' => $this->unit->id,
            'kategori' => 'Operasional', 'nominal' => 500000,
            'metode_pembayaran' => 'Cash', 'status' => 'Aktif', 'created_by' => $this->admin->id,
        ]);

        $this->assertEqualsWithDelta(500000, (float) Pengeluaran::aktif()->sum('nominal'), 0.01);

        $this->actingAs($this->admin)
            ->post(route('koperasi.pengeluaran.cancel', $p), ['alasan_pembatalan' => 'salah input'])
            ->assertRedirect();

        $this->assertEqualsWithDelta(0, (float) Pengeluaran::aktif()->sum('nominal'), 0.01);
        $this->assertDatabaseHas('pengeluaran', ['id' => $p->id]);

        // Jejaknya kini tersimpan — sebelumnya pembatalan pengeluaran tidak
        // meninggalkan alasan, pelaku, maupun waktu.
        $p->refresh();
        $this->assertSame('salah input', $p->alasan_pembatalan);
        $this->assertSame($this->admin->id, $p->dibatalkan_oleh);
        $this->assertNotNull($p->dibatalkan_at);
        $this->assertDatabaseHas('activity_logs', ['action' => 'pengeluaran_cancelled']);
    }

    // ==================================================================
    // C. PULIHKAN — kembali dihitung, TANPA ganda  (AJ 11-13)
    // ==================================================================

    public function test_transaksi_dibatalkan_dapat_dipulihkan(): void
    {
        $t = $this->transaksi();
        $this->batalkan($t);

        $this->actingAs($this->admin)
            ->post(route('koperasi.penjualan.pulihkan', $t))
            ->assertRedirect();

        $t->refresh();
        $this->assertSame('Aktif', $t->status_transaksi);
        $this->assertNull($t->dibatalkan_at);
        $this->assertNull($t->alasan_pembatalan);
    }

    public function test_transaksi_dipulihkan_kembali_dihitung(): void
    {
        $t = $this->transaksi();
        $this->batalkan($t);
        $this->assertEqualsWithDelta(0, $this->omzetPenjualanAktif(), 0.01);

        $this->actingAs($this->admin)->post(route('koperasi.penjualan.pulihkan', $t));

        $this->assertEqualsWithDelta(150000, $this->omzetPenjualanAktif(), 0.01);
    }

    public function test_pemulihan_berulang_tidak_menghitung_ganda(): void
    {
        // Inti dari "tidak boleh double counting". Tombol yang ditekan dua
        // kali, atau permintaan yang terkirim ulang, tidak boleh melipatkan
        // angka penjualan.
        $t = $this->transaksi();
        $this->batalkan($t);

        $this->actingAs($this->admin)->post(route('koperasi.penjualan.pulihkan', $t))->assertRedirect();
        // Percobaan kedua ditolak karena transaksinya memang sudah aktif.
        $this->actingAs($this->admin)->post(route('koperasi.penjualan.pulihkan', $t))->assertForbidden();

        $this->assertEqualsWithDelta(150000, $this->omzetPenjualanAktif(), 0.01);
        $this->assertSame(1, SalesTransaction::count());
    }

    public function test_pemulihan_tercatat_di_activity_log(): void
    {
        $t = $this->transaksi();
        $this->batalkan($t);
        $this->actingAs($this->admin)->post(route('koperasi.penjualan.pulihkan', $t));

        $log = ActivityLog::where('action', 'sales_transaction_restored')->first();
        $this->assertNotNull($log);
        // Alasan pembatalan lama ikut disimpan di log walau dibersihkan dari
        // barisnya — kalau tidak, rangkaian kejadiannya jadi tidak terbaca.
        $this->assertStringContainsString('pesanan batal', $log->description);
    }

    public function test_pengeluaran_dapat_dipulihkan(): void
    {
        $p = Pengeluaran::create([
            'tanggal' => now()->toDateString(), 'business_unit_id' => $this->unit->id,
            'kategori' => 'Operasional', 'nominal' => 500000,
            'metode_pembayaran' => 'Cash', 'status' => 'Aktif', 'created_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)->post(route('koperasi.pengeluaran.cancel', $p), ['alasan_pembatalan' => 'keliru']);
        $this->actingAs($this->admin)->post(route('koperasi.pengeluaran.pulihkan', $p))->assertRedirect();

        $this->assertEqualsWithDelta(500000, (float) Pengeluaran::aktif()->sum('nominal'), 0.01);
        $this->assertNull($p->refresh()->alasan_pembatalan);
    }

    public function test_simpanan_dibatalkan_dapat_dipulihkan_dan_kembali_ke_saldo(): void
    {
        $anggota = KoperasiMember::create([
            'nama' => 'Pak Budi', 'nomor_hp' => '081200000001',
            'tanggal_bergabung' => now()->toDateString(), 'status' => 'Aktif',
        ]);
        $s = $anggota->savings()->create([
            'jenis_simpanan' => 'Simpanan Sukarela', 'tanggal' => now()->toDateString(),
            'nominal' => 100000, 'status' => 'Tercatat',
        ]);

        $this->actingAs($this->admin)->post(route('koperasi.savings.batalkan', $s), [
            'alasan_koreksi' => 'salah anggota',
        ])->assertRedirect();

        $this->assertEqualsWithDelta(0, $anggota->fresh()->ringkasanSimpanan()['total'], 0.01);

        $this->actingAs($this->admin)->post(route('koperasi.savings.pulihkan', $s))->assertRedirect();

        $this->assertEqualsWithDelta(100000, $anggota->fresh()->ringkasanSimpanan()['total'], 0.01);
    }

    public function test_simpanan_yang_sudah_punya_pengganti_tidak_bisa_dipulihkan(): void
    {
        // SATU-SATUNYA jalur di sistem ini yang benar-benar bisa menghitung
        // uang dua kali: entri asal dipulihkan sementara penggantinya masih
        // berlaku. Saldo anggota adalah angka yang dibawa ke Rapat Anggota.
        $anggota = KoperasiMember::create([
            'nama' => 'Pak Budi', 'nomor_hp' => '081200000001',
            'tanggal_bergabung' => now()->toDateString(), 'status' => 'Aktif',
        ]);
        $s = $anggota->savings()->create([
            'jenis_simpanan' => 'Simpanan Sukarela', 'tanggal' => now()->toDateString(),
            'nominal' => 100000, 'status' => 'Tercatat',
        ]);

        $this->actingAs($this->admin)->post(route('koperasi.savings.batalkan', $s), [
            'alasan_koreksi' => 'salah ketik nominal',
            'buat_pengganti' => 1,
            'nominal_pengganti' => 50000,
            'tanggal_pengganti' => now()->toDateString(),
        ])->assertRedirect();

        $this->assertEqualsWithDelta(50000, $anggota->fresh()->ringkasanSimpanan()['total'], 0.01);

        $this->actingAs($this->admin)
            ->post(route('koperasi.savings.pulihkan', $s))
            ->assertForbidden();

        // Saldonya TIDAK berubah — tetap 50.000, bukan 150.000.
        $this->assertEqualsWithDelta(50000, $anggota->fresh()->ringkasanSimpanan()['total'], 0.01);
    }

    // ==================================================================
    // D. HAPUS PERMANEN  (AJ 14-17)
    // ==================================================================

    private function hapusPermanen(User $aktor, string $modul, int $id, string $konfirmasi = 'HAPUS PERMANEN')
    {
        return $this->actingAs($aktor)->delete(
            route('arsip.hapus-permanen', ['modul' => $modul, 'id' => $id]),
            ['konfirmasi' => $konfirmasi]
        );
    }

    public function test_hanya_admin_yang_boleh_menghapus_permanen(): void
    {
        $produk = $this->produk();
        $produk->delete();

        // Tim Koperasi boleh mengarsipkan & memulihkan produk, TAPI tidak
        // boleh menghapusnya permanen — pemulihan bisa diperbaiki,
        // penghapusan permanen tidak.
        $this->hapusPermanen($this->timKoperasi, 'produk', $produk->id)->assertForbidden();
        $this->hapusPermanen($this->pendamping, 'produk', $produk->id)->assertForbidden();
        $this->hapusPermanen($this->pimpinan, 'produk', $produk->id)->assertForbidden();

        $this->assertNotNull(ProductService::withTrashed()->find($produk->id));
    }

    public function test_konfirmasi_harus_diketik_persis(): void
    {
        $produk = $this->produk();
        $produk->delete();

        $this->hapusPermanen($this->admin, 'produk', $produk->id, 'hapus')
            ->assertSessionHasErrors('konfirmasi');

        $this->assertNotNull(ProductService::withTrashed()->find($produk->id));
    }

    public function test_data_uji_coba_tanpa_jejak_dapat_dihapus_permanen(): void
    {
        $produk = $this->produk();
        $produk->delete();

        $this->hapusPermanen($this->admin, 'produk', $produk->id)->assertRedirect();

        $this->assertNull(ProductService::withTrashed()->find($produk->id));
        $this->assertDatabaseHas('activity_logs', ['action' => 'permanently_deleted']);
    }

    public function test_data_aktif_tidak_bisa_langsung_dihapus_permanen(): void
    {
        // Tidak ada jalan dari "aktif" langsung ke "hilang selamanya".
        $produk = $this->produk();

        $this->hapusPermanen($this->admin, 'produk', $produk->id)
            ->assertSessionHasErrors('hapus_permanen');

        $this->assertNotNull(ProductService::find($produk->id));
    }

    public function test_produk_yang_pernah_terjual_tidak_bisa_dihapus_permanen(): void
    {
        $t = $this->transaksi();
        $produk = ProductService::firstOrFail();
        $produk->delete();

        $this->hapusPermanen($this->admin, 'produk', $produk->id)
            ->assertSessionHasErrors('hapus_permanen');

        $this->assertNotNull(ProductService::withTrashed()->find($produk->id));
        // Transaksinya juga tidak tersentuh sama sekali.
        $this->assertDatabaseHas('sales_transaction_items', ['product_service_id' => $produk->id]);
    }

    public function test_pelanggan_dengan_transaksi_tidak_bisa_dihapus_permanen(): void
    {
        $pelanggan = Customer::create(['nama' => 'Bu Ani', 'nomor_hp' => '081200000002']);
        $t = $this->transaksi();
        $t->update(['customer_id' => $pelanggan->id]);
        $pelanggan->delete();

        $this->hapusPermanen($this->admin, 'pelanggan', $pelanggan->id)
            ->assertSessionHasErrors('hapus_permanen');

        $this->assertNotNull(Customer::withTrashed()->find($pelanggan->id));
    }

    public function test_anggota_dengan_simpanan_tidak_bisa_dihapus_permanen(): void
    {
        $anggota = KoperasiMember::create([
            'nama' => 'Pak Budi', 'nomor_hp' => '081200000001',
            'tanggal_bergabung' => now()->toDateString(), 'status' => 'Aktif',
        ]);
        $anggota->savings()->create([
            'jenis_simpanan' => 'Simpanan Pokok', 'tanggal' => now()->toDateString(),
            'nominal' => 50000, 'status' => 'Tercatat',
        ]);
        $anggota->delete();

        $this->hapusPermanen($this->admin, 'anggota', $anggota->id)
            ->assertSessionHasErrors('hapus_permanen');

        $this->assertNotNull(KoperasiMember::withTrashed()->find($anggota->id));
        $this->assertSame(1, MemberSaving::count());
    }

    public function test_peserta_dengan_riwayat_tidak_bisa_dihapus_permanen(): void
    {
        $peserta = Participant::create([
            'nama_peserta' => 'Bu Sari', 'nama_usaha' => 'Keripik Sari',
            'nomor_hp' => '081200000003', 'kategori_usaha' => 'Kuliner',
            'tanggal_mulai_program' => now()->subYear()->toDateString(),
            'pendamping_id' => $this->pendamping->pendamping_id, 'status' => 'Aktif',
        ]);
        $peserta->pendampinganRecords()->create([
            'tanggal' => now()->toDateString(), 'materi' => 'Pembukuan sederhana',
            'jenis_kegiatan' => 'Kunjungan', 'pendamping_id' => $this->pendamping->pendamping_id,
        ]);
        $peserta->delete();

        $this->hapusPermanen($this->admin, 'peserta', $peserta->id)
            ->assertSessionHasErrors('hapus_permanen');

        $this->assertNotNull(Participant::withTrashed()->find($peserta->id));
    }

    public function test_catatan_yang_ikut_diarsipkan_tetap_dihitung_sebagai_jejak(): void
    {
        // Kalau catatan terarsip tidak dihitung, peserta bisa dihapus
        // permanen hanya dengan mengarsipkan dulu seluruh catatannya —
        // pengamannya jadi sekadar formalitas.
        $peserta = Participant::create([
            'nama_peserta' => 'Bu Sari', 'nama_usaha' => 'Keripik Sari',
            'nomor_hp' => '081200000003', 'kategori_usaha' => 'Kuliner',
            'tanggal_mulai_program' => now()->subYear()->toDateString(),
            'pendamping_id' => $this->pendamping->pendamping_id, 'status' => 'Aktif',
        ]);
        $catatan = $peserta->pendampinganRecords()->create([
            'tanggal' => now()->toDateString(), 'materi' => 'Pembukuan sederhana',
            'jenis_kegiatan' => 'Kunjungan', 'pendamping_id' => $this->pendamping->pendamping_id,
        ]);
        $catatan->delete();
        $peserta->delete();

        $this->hapusPermanen($this->admin, 'peserta', $peserta->id)
            ->assertSessionHasErrors('hapus_permanen');

        $this->assertNotNull(Participant::withTrashed()->find($peserta->id));
    }

    public function test_modul_yang_tidak_terdaftar_menghasilkan_404(): void
    {
        $this->hapusPermanen($this->admin, 'modul-karangan', 1)->assertNotFound();
    }

    public function test_setiap_tab_arsip_punya_padanan_di_daftar_hapus_permanen(): void
    {
        // Penjaga sinkronisasi: kalau ada tab arsip baru yang lupa
        // didaftarkan, akibatnya harus tombolnya tidak muncul — bukan
        // penghapusan yang lolos tanpa pengaman. Test ini memastikan
        // ketidaksinkronan itu terlihat, bukan diam.
        $modul = array_keys(PenghapusanPermanenService::modulTersedia());

        $this->assertContains('peserta', $modul);
        $this->assertContains('produk', $modul);
        $this->assertContains('anggota', $modul);
        $this->assertNotContains('penjualan', $modul, 'Transaksi penjualan sengaja TIDAK bisa dihapus permanen.');
    }

    // ==================================================================
    // E. ARSIP  (AJ 29-30)
    // ==================================================================

    public function test_halaman_arsip_menampilkan_data_terhapus_dan_tombol_pulihkan(): void
    {
        $produk = $this->produk();
        $produk->delete();

        $this->actingAs($this->admin)
            ->get(route('arsip.index', ['tab' => 'produk']))
            ->assertOk()
            ->assertSee('Keripik Pisang')
            ->assertSee('Pulihkan');
    }

    public function test_arsip_menawarkan_hapus_permanen_hanya_untuk_data_tanpa_jejak(): void
    {
        $bersih = $this->produk();
        $bersih->delete();

        $this->actingAs($this->admin)
            ->get(route('arsip.index', ['tab' => 'produk']))
            ->assertOk()
            ->assertSee('Hapus Permanen');

        // Produk yang pernah terjual: tombolnya diganti penjelasan.
        $t = $this->transaksi();
        $terpakai = ProductService::where('nama', 'Keripik Pisang')->where('id', '!=', $bersih->id)->firstOrFail();
        $terpakai->delete();
        $bersih->forceDelete();

        $this->actingAs($this->admin)
            ->get(route('arsip.index', ['tab' => 'produk']))
            ->assertOk()
            // Yang ditampilkan sekarang alasannya, bukan label buntu
            // "Tidak bisa dihapus permanen" — alasannya memuat langkah yang
            // bisa ditempuh, dan itulah yang berguna bagi operator.
            ->assertSee('sudah dipakai di 1 baris transaksi penjualan');
    }

    public function test_tim_koperasi_tidak_melihat_tombol_hapus_permanen(): void
    {
        $produk = $this->produk();
        $produk->delete();

        $this->actingAs($this->timKoperasi)
            ->get(route('arsip.index', ['tab' => 'produk']))
            ->assertOk()
            ->assertSee('Pulihkan')
            ->assertDontSee('Hapus Permanen');
    }

    public function test_tidak_ada_tugas_terjadwal_yang_menghapus_arsip(): void
    {
        // ARSIP = DISIMPAN. Kalau suatu hari ada yang menambahkan penyapu
        // arsip otomatis, test ini yang menghentikannya.
        $terdaftar = collect(\Illuminate\Support\Facades\Schedule::events())
            ->map(fn ($e) => $e->command ?? '')
            ->implode(' | ');

        $this->assertStringNotContainsString('arsip', strtolower($terdaftar));
        $this->assertStringNotContainsString('purge', strtolower($terdaftar));
        $this->assertStringNotContainsString('prune', strtolower($terdaftar));
    }

    public function test_arsip_menampilkan_penjualan_yang_dibatalkan(): void
    {
        // Sebelum ini tidak ada satu pun halaman yang bisa menjawab "apa saja
        // yang dibatalkan bulan ini?" — pembatalan hanya terlihat kalau
        // seseorang kebetulan membuka transaksinya satu per satu.
        $t = $this->transaksi();
        $this->batalkan($t);

        $this->actingAs($this->admin)
            ->get(route('arsip.index', ['tab' => 'penjualan-batal']))
            ->assertOk()
            ->assertSee($t->nomor_transaksi)
            ->assertSee('pesanan batal')
            ->assertSee('Dibatalkan')
            ->assertSee('Pulihkan');
    }

    public function test_arsip_menampilkan_pengeluaran_yang_dibatalkan(): void
    {
        $p = Pengeluaran::create([
            'tanggal' => now()->toDateString(), 'business_unit_id' => $this->unit->id,
            'kategori' => 'Transportasi', 'nominal' => 250000,
            'metode_pembayaran' => 'Cash', 'status' => 'Aktif', 'created_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->post(route('koperasi.pengeluaran.cancel', $p), ['alasan_pembatalan' => 'dobel catat']);

        $this->actingAs($this->admin)
            ->get(route('arsip.index', ['tab' => 'pengeluaran-batal']))
            ->assertOk()
            ->assertSee('Transportasi')
            ->assertSee('dobel catat')
            ->assertSee('Admin');
    }

    public function test_arsip_simpanan_tidak_menawarkan_pulihkan_kalau_sudah_ada_pengganti(): void
    {
        $anggota = KoperasiMember::create([
            'nama' => 'Pak Budi', 'nomor_hp' => '081200000001',
            'tanggal_bergabung' => now()->toDateString(), 'status' => 'Aktif',
        ]);
        $s = $anggota->savings()->create([
            'jenis_simpanan' => 'Simpanan Sukarela', 'tanggal' => now()->toDateString(),
            'nominal' => 100000, 'status' => 'Tercatat',
        ]);

        $this->actingAs($this->admin)->post(route('koperasi.savings.batalkan', $s), [
            'alasan_koreksi' => 'salah ketik',
            'buat_pengganti' => 1,
            'nominal_pengganti' => 50000,
            'tanggal_pengganti' => now()->toDateString(),
        ]);

        $this->actingAs($this->admin)
            ->get(route('arsip.index', ['tab' => 'simpanan-batal']))
            ->assertOk()
            ->assertSee('Simpanan Sukarela')
            ->assertSee('Sudah diganti entri lain');
    }

    public function test_transaksi_aktif_tidak_bisa_langsung_dihapus_permanen(): void
    {
        // Tidak ada jalan dari "sedang dihitung di laba rugi" langsung ke
        // "hilang selamanya". Pembatalan adalah keputusan pertama; penghapusan
        // permanen keputusan kedua, di waktu yang berbeda.
        $t = $this->transaksi();

        $this->hapusPermanen($this->admin, 'penjualan-batal', $t->id)
            ->assertSessionHasErrors('hapus_permanen');

        $this->assertDatabaseHas('sales_transactions', ['id' => $t->id]);
    }

    public function test_transaksi_uji_coba_yang_sudah_dibatalkan_dapat_dihapus_permanen(): void
    {
        // CahayaOS dipakai uji coba. Transaksi percobaan yang sudah dibatalkan
        // tidak punya nilai histori — ia hanya menumpuk di daftar.
        $t = $this->transaksi();
        $idItem = $t->items()->first()->id;
        $this->batalkan($t);

        $this->hapusPermanen($this->admin, 'penjualan-batal', $t->id)->assertRedirect();

        $this->assertDatabaseMissing('sales_transactions', ['id' => $t->id]);
        // Item ikut terhapus lewat CASCADE — itu memang miliknya sendiri.
        $this->assertDatabaseMissing('sales_transaction_items', ['id' => $idItem]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'permanently_deleted']);
    }

    public function test_hanya_admin_yang_boleh_menghapus_transaksi_permanen(): void
    {
        $t = $this->transaksi();
        $this->batalkan($t);

        // Tim Koperasi boleh membatalkan dan memulihkan, TIDAK boleh menghapus.
        $this->hapusPermanen($this->timKoperasi, 'penjualan-batal', $t->id)->assertForbidden();

        $this->assertDatabaseHas('sales_transactions', ['id' => $t->id]);
    }

    public function test_pendamping_tidak_melihat_tab_catatan_uang(): void
    {
        $t = $this->transaksi();
        $this->batalkan($t);

        // Pendamping bukan urusan koperasi — tab-nya tidak boleh bisa dibuka
        // hanya dengan mengetik ?tab= di alamat.
        $respons = $this->actingAs($this->pendamping)
            ->get(route('arsip.index', ['tab' => 'penjualan-batal']));

        $respons->assertOk()->assertDontSee($t->nomor_transaksi);
    }

    // ==================================================================
    // E2. LEGALITAS — dokumen peserta tidak boleh hilang
    // ==================================================================

    private function legalitas(): \App\Models\LegalitasRecord
    {
        \Illuminate\Support\Facades\Storage::fake('local');

        $peserta = Participant::create([
            'nama_peserta' => 'Bu Sari', 'nama_usaha' => 'Keripik Sari',
            'nomor_hp' => '081200000003', 'kategori_usaha' => 'Kuliner',
            'tanggal_mulai_program' => now()->subYear()->toDateString(),
            'pendamping_id' => $this->pendamping->pendamping_id, 'status' => 'Aktif',
        ]);

        \Illuminate\Support\Facades\Storage::put('legalitas/nib-bu-sari.pdf', 'isi-dokumen');

        return $peserta->legalitasRecords()->create([
            'jenis_dokumen' => 'NIB',
            'nomor_dokumen' => '1234567890',
            'status' => 'Sudah Ada',
            'file_path' => 'legalitas/nib-bu-sari.pdf',
        ]);
    }

    public function test_mengarsipkan_legalitas_tidak_menghapus_berkasnya(): void
    {
        // Perilaku LAMA menghapus baris DAN berkasnya sekaligus. NIB dan
        // sertifikat halal butuh berbulan-bulan serta biaya nyata untuk
        // diurus, dan salinan di sini sering satu-satunya salinan digital
        // yang dimiliki peserta.
        $dok = $this->legalitas();

        $this->actingAs($this->admin)
            ->delete(route('legalitas.destroy', $dok))
            ->assertRedirect();

        $this->assertSoftDeleted('legalitas_records', ['id' => $dok->id]);
        \Illuminate\Support\Facades\Storage::assertExists('legalitas/nib-bu-sari.pdf');
    }

    public function test_legalitas_yang_diarsipkan_dapat_dipulihkan_lengkap_dengan_berkasnya(): void
    {
        $dok = $this->legalitas();

        $this->actingAs($this->admin)->delete(route('legalitas.destroy', $dok));
        $this->actingAs($this->admin)->post(route('legalitas.restore', $dok))->assertRedirect();

        $dok->refresh();
        $this->assertNull($dok->deleted_at);
        \Illuminate\Support\Facades\Storage::assertExists($dok->file_path);
    }

    public function test_berkas_legalitas_baru_dibuang_saat_dihapus_permanen(): void
    {
        $dok = $this->legalitas();
        $this->actingAs($this->admin)->delete(route('legalitas.destroy', $dok));

        $this->hapusPermanen($this->admin, 'legalitas', $dok->id)->assertRedirect();

        $this->assertDatabaseMissing('legalitas_records', ['id' => $dok->id]);
        \Illuminate\Support\Facades\Storage::assertMissing('legalitas/nib-bu-sari.pdf');
    }

    // ==================================================================
    // F. HPP SNAPSHOT — master berubah, histori tidak  (AJ 18-20)
    // ==================================================================

    public function test_master_hpp_berubah_tidak_mengubah_transaksi_lama(): void
    {
        $t = $this->transaksi();
        $produk = ProductService::firstOrFail();

        $this->assertEqualsWithDelta(8000, (float) $t->items()->first()->harga_modal_snapshot, 0.01);

        $produk->update(['harga_modal' => 9000]);

        $this->assertEqualsWithDelta(9000, (float) $produk->fresh()->harga_modal, 0.01);
        $this->assertEqualsWithDelta(8000, (float) $t->items()->first()->harga_modal_snapshot, 0.01);
    }

    // ==================================================================
    // G. INVOICE  (AJ 21-22)
    // ==================================================================

    public function test_invoice_tidak_hilang_saat_transaksi_dibatalkan(): void
    {
        $t = $this->transaksi();

        $this->actingAs($this->admin)->post(route('koperasi.invoice.store', $t))->assertRedirect();
        $invoice = $t->fresh()->invoice;
        $this->assertNotNull($invoice);

        $this->batalkan($t);

        // Invoice yang sudah dicetak untuk pembeli tidak boleh lenyap.
        $this->assertDatabaseHas('invoices', ['id' => $invoice->id]);
        $this->assertNotNull($t->fresh()->invoice);
    }

    public function test_invoice_transaksi_dibatalkan_menampilkan_keadaannya(): void
    {
        // Status invoice DITURUNKAN dari transaksinya, bukan disimpan sebagai
        // kolom kedua yang bisa berbeda diam-diam.
        $t = $this->transaksi();
        $this->actingAs($this->admin)->post(route('koperasi.invoice.store', $t));
        $this->batalkan($t);

        $this->actingAs($this->admin)
            ->get(route('koperasi.invoice.show', $t))
            ->assertOk()
            ->assertSee('Dibatalkan');
    }
}
