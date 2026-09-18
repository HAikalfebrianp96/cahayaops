<?php

namespace Tests\Feature;

use App\Models\BusinessUnit;
use App\Models\Customer;
use App\Models\HppCalculation;
use App\Models\Participant;
use App\Models\Pendamping;
use App\Models\PengeluaranCategory;
use App\Models\ProductService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PRINSIP CRUD GLOBAL: apa pun yang diinput manusia harus bisa dikoreksi.
 *
 * Kelas ini menutup modul-modul yang sebelumnya hanya bisa DIBUAT tapi tidak
 * pernah bisa diperbaiki atau dibuang:
 *
 *   Pelanggan              -> dulu HANYA quick-add AJAX dari form Penjualan;
 *                             tidak ada halaman daftar, edit, maupun hapus.
 *   Produk/Jasa            -> punya Edit, tapi tidak punya Hapus.
 *   Kategori Pengeluaran   -> punya Edit & Nonaktifkan, tapi tidak punya Hapus.
 *   Perhitungan HPP        -> hanya bisa dibuat; salah input menempel selamanya.
 *   Pendamping             -> penghapusannya HARD DELETE, dan karena FK
 *                             ber-nullOnDelete, seluruh binaannya kehilangan
 *                             pemilik SECARA PERMANEN.
 *
 * Semuanya kini memakai Soft Delete supaya relasi yang menunjuk ke sana
 * (transaksi, invoice, pengeluaran, binaan) tidak pernah berlubang.
 */
class CrudGlobalTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $timKoperasi;
    private User $pendamping;
    private User $pimpinan;
    private Pendamping $profil;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->user('admin', 'admin@crud.test');
        $this->timKoperasi = $this->user(User::ROLE_TIM_KOPERASI, 'tk@crud.test');

        $this->profil = Pendamping::create([
            'nama' => 'Pendamping Uji', 'nomor_hp' => '0812', 'email' => 'pd@crud.test', 'status' => 'Aktif',
        ]);
        $this->pendamping = $this->user('pendamping', 'pd.akun@crud.test', $this->profil->id);
        $this->pimpinan = $this->user('pimpinan', 'pimpinan@crud.test');
    }

    private function user(string $role, string $email, ?int $pendampingId = null): User
    {
        return User::create([
            'name' => 'User ' . $email, 'email' => $email, 'password' => 'password123',
            'role' => $role, 'pendamping_id' => $pendampingId, 'is_active' => true,
        ]);
    }

    private function peserta(string $nama = 'Peserta Uji', ?int $pendampingId = null): Participant
    {
        return Participant::create([
            'nama_peserta' => $nama, 'nomor_hp' => '0812', 'nama_usaha' => 'Usaha ' . $nama,
            'kategori_usaha' => 'Jasa', 'tanggal_mulai_program' => '2026-01-10',
            'status' => 'Aktif', 'pendamping_id' => $pendampingId,
        ]);
    }

    private function unit(): BusinessUnit
    {
        return BusinessUnit::create([
            'nama' => 'Unit Uji', 'kode' => 'UNIT-CRUD', 'tipe' => 'Barang', 'status' => 'Aktif',
        ]);
    }

    // =====================================================================
    // PELANGGAN — dari "hanya bisa dibuat" jadi CRUD penuh
    // =====================================================================

    public function test_pelanggan_bisa_dilihat_ditambah_diedit_dan_dihapus(): void
    {
        $this->actingAs($this->timKoperasi)->get(route('koperasi.pelanggan.index'))->assertOk();
        $this->actingAs($this->timKoperasi)->get(route('koperasi.pelanggan.create'))->assertOk();

        $this->actingAs($this->timKoperasi)
            ->post(route('koperasi.pelanggan.store'), [
                'nama' => 'Toko Berkah', 'nomor_hp' => '081200001111', 'email' => 'berkah@toko.test',
            ])
            ->assertSessionHasNoErrors();

        $pelanggan = Customer::where('nama', 'Toko Berkah')->first();
        $this->assertNotNull($pelanggan);

        $this->actingAs($this->timKoperasi)->get(route('koperasi.pelanggan.edit', $pelanggan))->assertOk();

        $this->actingAs($this->timKoperasi)
            ->put(route('koperasi.pelanggan.update', $pelanggan), [
                'nama' => 'Toko Berkah Jaya', 'nomor_hp' => '081200002222',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('Toko Berkah Jaya', $pelanggan->fresh()->nama);

        $this->actingAs($this->timKoperasi)
            ->delete(route('koperasi.pelanggan.destroy', $pelanggan))
            ->assertRedirect(route('koperasi.pelanggan.index'));

        // Benar-benar hilang: tidak ada jalan kembali, dan tidak ada baris
        // tersembunyi yang masih bisa ditemukan withTrashed().
        $this->assertDatabaseMissing('customers', ['id' => $pelanggan->id]);
        $this->assertNull(Customer::withTrashed()->find($pelanggan->id));
    }

    /** Quick-add dari halaman Penjualan TIDAK berubah — masih mengembalikan JSON. */
    public function test_quick_add_pelanggan_tetap_berfungsi(): void
    {
        $this->actingAs($this->timKoperasi)
            ->post(route('koperasi.customer.quick-store'), ['nama' => 'Pembeli Cepat'])
            ->assertOk()
            ->assertJsonStructure(['id', 'nama', 'nomor_hp']);

        $this->assertDatabaseHas('customers', ['nama' => 'Pembeli Cepat']);
    }

    public function test_pelanggan_tertutup_untuk_role_tanpa_hak(): void
    {
        $pelanggan = Customer::create(['nama' => 'Toko Uji']);

        foreach ([$this->pendamping, $this->pimpinan] as $user) {
            $this->actingAs($user)->get(route('koperasi.pelanggan.index'))->assertForbidden();
            $this->actingAs($user)->get(route('koperasi.pelanggan.edit', $pelanggan))->assertForbidden();
            $this->actingAs($user)
                ->put(route('koperasi.pelanggan.update', $pelanggan), ['nama' => 'DIBAJAK'])
                ->assertForbidden();
            $this->actingAs($user)->delete(route('koperasi.pelanggan.destroy', $pelanggan))->assertForbidden();
        }

        $this->assertSame('Toko Uji', $pelanggan->fresh()->nama);
    }

    // =====================================================================
    // PRODUK / JASA — Hapus yang sebelumnya tidak ada
    // =====================================================================

    public function test_produk_tertutup_untuk_role_tanpa_hak(): void
    {
        $unit = $this->unit();
        $produk = ProductService::create([
            'unit_id' => $unit->id, 'kode' => 'PRD-002', 'nama' => 'Gula 1kg',
            'jenis' => 'Barang', 'satuan' => 'pack', 'harga_jual' => 15000, 'status' => 'Aktif',
        ]);

        foreach ([$this->pendamping, $this->pimpinan] as $user) {
            $this->actingAs($user)->delete(route('koperasi.produk.destroy', $produk))->assertForbidden();
        }

        $this->assertDatabaseHas('products_services', ['id' => $produk->id]);
    }

    // =====================================================================
    // KATEGORI PENGELUARAN — Hapus + Tab Terhapus
    // =====================================================================

    /** Nonaktifkan dan Hapus adalah dua tindakan BERBEDA, keduanya tetap ada. */
    public function test_nonaktifkan_kategori_berbeda_dari_menghapusnya(): void
    {
        $kategori = PengeluaranCategory::create(['nama' => 'Kategori Lama', 'status' => 'Aktif']);

        $this->actingAs($this->timKoperasi)
            ->post(route('koperasi.kategori-pengeluaran.toggle', $kategori))
            ->assertSessionHasNoErrors();

        $this->assertSame('Tidak Aktif', $kategori->fresh()->status);
        $this->assertDatabaseHas('pengeluaran_categories', ['id' => $kategori->id]);
    }

    // =====================================================================
    // HPP — penghapusan yang aman untuk data histori
    // =====================================================================

    public function test_hpp_bisa_dihapus_pemilik_binaan_dan_admin(): void
    {
        $binaan = $this->peserta('Binaan HPP', $this->profil->id);

        $hpp = HppCalculation::create([
            'participant_id' => $binaan->id, 'nama_produk' => 'Keripik',
            'satuan_produksi' => 'pack', 'jumlah_produksi' => 100,
            'total_biaya_produksi' => 500000, 'hpp_per_unit' => 5000,
            'harga_jual' => 8000, 'laba_per_unit' => 3000,
            'margin_persen' => 37.5, 'estimasi_laba_batch' => 300000,
        ]);

        $this->actingAs($this->pendamping)
            ->delete(route('hpp.destroy', $hpp))
            ->assertRedirect(route('hpp.index', $binaan->id));

        $this->assertDatabaseMissing('hpp_calculations', ['id' => $hpp->id]);
        $this->assertNull(HppCalculation::withTrashed()->find($hpp->id));
    }

    public function test_hpp_binaan_lain_tidak_bisa_dihapus_pendamping(): void
    {
        $lain = Pendamping::create(['nama' => 'PD Lain', 'nomor_hp' => '0813', 'email' => 'lain@crud.test', 'status' => 'Aktif']);
        $binaanLain = $this->peserta('Binaan Lain', $lain->id);

        $hpp = HppCalculation::create([
            'participant_id' => $binaanLain->id, 'nama_produk' => 'Kue',
            'satuan_produksi' => 'box', 'jumlah_produksi' => 50,
            'total_biaya_produksi' => 250000, 'hpp_per_unit' => 5000,
            'harga_jual' => 9000, 'laba_per_unit' => 4000,
            'margin_persen' => 44.4, 'estimasi_laba_batch' => 200000,
        ]);

        $this->actingAs($this->pendamping)->delete(route('hpp.destroy', $hpp))->assertForbidden();
        $this->assertDatabaseHas('hpp_calculations', ['id' => $hpp->id]);

        // Admin tetap boleh.
        $this->actingAs($this->admin)->delete(route('hpp.destroy', $hpp))->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('hpp_calculations', ['id' => $hpp->id]);
    }

    // =====================================================================
    // PENDAMPING — Soft Delete menyelamatkan tautan binaan
    // =====================================================================

    /**
     * INTI: dulu destroy() adalah HARD DELETE, dan FK ber-nullOnDelete membuat
     * seluruh binaan kehilangan pemilik SECARA PERMANEN. Sekarang tautannya
     * bertahan dan ikut kembali saat Pendamping dipulihkan.
     */
    /**
     * Pendamping yang masih membina TIDAK bisa dihapus.
     *
     * Dulu ia diarsipkan dan tautan binaannya sengaja dipertahankan supaya
     * pemulihan bisa mengembalikannya utuh. Tanpa pemulihan, menghapusnya
     * berarti seluruh binaannya menunjuk pendamping yang tidak ada lagi —
     * jadi jalurnya ditutup di depan, beserta alasannya.
     */
    public function test_pendamping_yang_masih_membina_tidak_bisa_dihapus(): void
    {
        $binaan = $this->peserta('Binaan Terikat', $this->profil->id);

        $this->actingAs($this->admin)->delete(route('pendampings.destroy', $this->profil));

        $this->assertDatabaseHas('pendampings', ['id' => $this->profil->id]);
        $this->assertSame($this->profil->id, $binaan->fresh()->pendamping_id);
        $this->assertStringContainsString('membina', (string) session('error'));
    }

    // =====================================================================
    // SIAPA YANG MENGHAPUS — dicatat otomatis lewat trait MencatatPenghapus
    // =====================================================================

    public function test_penghapus_tercatat_otomatis_di_seluruh_data_master(): void
    {
        $peserta = $this->peserta('Peserta Arsip', $this->profil->id);
        $unit = $this->unit();

        $this->actingAs($this->admin)->delete(route('participants.destroy', $peserta));
        $this->actingAs($this->timKoperasi)->delete(route('koperasi.unit-usaha.destroy', $unit));

        // Barisnya sudah tidak ada, jadi kolom deleted_by tidak bisa lagi
        // menyimpan siapa pelakunya. Satu-satunya jejak yang tersisa —
        // dan justru karena itu wajib ada — adalah Activity Log.
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'permanently_deleted',
            'model_type' => 'Participant',
            'model_id' => $peserta->id,
            'user_id' => $this->admin->id,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'permanently_deleted',
            'model_type' => 'BusinessUnit',
            'model_id' => $unit->id,
            'user_id' => $this->timKoperasi->id,
        ]);
    }

    /** Tab Terhapus peserta menampilkan kapan dan oleh siapa. */
    public function test_tab_terhapus_peserta_menampilkan_kapan_dan_oleh_siapa(): void
    {
        $peserta = $this->peserta('Peserta Jejak', $this->profil->id);
        $this->actingAs($this->admin)->delete(route('participants.destroy', $peserta));

        $this->actingAs($this->admin)
            ->get(route('participants.index', ['arsip' => 1]))
            ->assertOk()
            ->assertSee('Peserta Jejak')
            ->assertSee($this->admin->name);
    }
}
