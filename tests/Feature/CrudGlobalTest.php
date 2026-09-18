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

        $this->assertSoftDeleted('customers', ['id' => $pelanggan->id]);

        $this->actingAs($this->timKoperasi)
            ->post(route('koperasi.pelanggan.restore', $pelanggan->id))
            ->assertSessionHasNoErrors();

        $this->assertNotSoftDeleted('customers', ['id' => $pelanggan->id]);
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

    public function test_produk_bisa_dihapus_dan_dipulihkan(): void
    {
        $unit = $this->unit();

        $produk = ProductService::create([
            'unit_id' => $unit->id, 'kode' => 'PRD-001', 'nama' => 'Beras 5kg',
            'jenis' => 'Barang', 'satuan' => 'pack', 'harga_jual' => 60000, 'status' => 'Aktif',
        ]);

        $this->actingAs($this->timKoperasi)
            ->delete(route('koperasi.produk.destroy', $produk))
            ->assertRedirect(route('koperasi.unit-usaha.show', $unit->id));

        $this->assertSoftDeleted('products_services', ['id' => $produk->id]);
        $this->assertNotNull(ProductService::withTrashed()->find($produk->id));

        $this->actingAs($this->timKoperasi)
            ->post(route('koperasi.produk.restore', $produk->id))
            ->assertSessionHasNoErrors();

        $this->assertNotSoftDeleted('products_services', ['id' => $produk->id]);
    }

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

        $this->assertNotSoftDeleted('products_services', ['id' => $produk->id]);
    }

    // =====================================================================
    // KATEGORI PENGELUARAN — Hapus + Tab Terhapus
    // =====================================================================

    public function test_kategori_pengeluaran_bisa_dihapus_dan_dipulihkan(): void
    {
        $kategori = PengeluaranCategory::create(['nama' => 'Kategori Salah', 'status' => 'Aktif']);

        $this->actingAs($this->timKoperasi)
            ->delete(route('koperasi.kategori-pengeluaran.destroy', $kategori))
            ->assertRedirect(route('koperasi.kategori-pengeluaran.index'));

        $this->assertSoftDeleted('pengeluaran_categories', ['id' => $kategori->id]);

        // Hilang dari daftar aktif, muncul di Tab Terhapus.
        $this->actingAs($this->timKoperasi)
            ->get(route('koperasi.kategori-pengeluaran.index'))
            ->assertOk()
            ->assertDontSee(route('koperasi.kategori-pengeluaran.edit', $kategori), false);

        $this->actingAs($this->timKoperasi)
            ->get(route('koperasi.kategori-pengeluaran.index', ['arsip' => 1]))
            ->assertOk()
            ->assertSee('Kategori Salah');

        $this->actingAs($this->timKoperasi)
            ->post(route('koperasi.kategori-pengeluaran.restore', $kategori->id))
            ->assertSessionHasNoErrors();

        $this->assertNotSoftDeleted('pengeluaran_categories', ['id' => $kategori->id]);
    }

    /** Nonaktifkan dan Hapus adalah dua tindakan BERBEDA, keduanya tetap ada. */
    public function test_nonaktifkan_kategori_berbeda_dari_menghapusnya(): void
    {
        $kategori = PengeluaranCategory::create(['nama' => 'Kategori Lama', 'status' => 'Aktif']);

        $this->actingAs($this->timKoperasi)
            ->post(route('koperasi.kategori-pengeluaran.toggle', $kategori))
            ->assertSessionHasNoErrors();

        $this->assertSame('Tidak Aktif', $kategori->fresh()->status);
        $this->assertNotSoftDeleted('pengeluaran_categories', ['id' => $kategori->id]);
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

        $this->assertSoftDeleted('hpp_calculations', ['id' => $hpp->id]);
        $this->assertNotNull(HppCalculation::withTrashed()->find($hpp->id));
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
        $this->assertNotSoftDeleted('hpp_calculations', ['id' => $hpp->id]);

        // Admin tetap boleh.
        $this->actingAs($this->admin)->delete(route('hpp.destroy', $hpp))->assertSessionHasNoErrors();
        $this->assertSoftDeleted('hpp_calculations', ['id' => $hpp->id]);
    }

    // =====================================================================
    // PENDAMPING — Soft Delete menyelamatkan tautan binaan
    // =====================================================================

    /**
     * INTI: dulu destroy() adalah HARD DELETE, dan FK ber-nullOnDelete membuat
     * seluruh binaan kehilangan pemilik SECARA PERMANEN. Sekarang tautannya
     * bertahan dan ikut kembali saat Pendamping dipulihkan.
     */
    public function test_menghapus_pendamping_tidak_memutus_tautan_binaannya(): void
    {
        $binaan = $this->peserta('Binaan Terikat', $this->profil->id);

        $this->actingAs($this->admin)
            ->delete(route('pendampings.destroy', $this->profil))
            ->assertRedirect(route('pendampings.index'));

        $this->assertSoftDeleted('pendampings', ['id' => $this->profil->id]);

        // Binaannya TIDAK ikut terhapus dan tautannya TIDAK dikosongkan.
        $this->assertNotSoftDeleted('participants', ['id' => $binaan->id]);
        $this->assertSame($this->profil->id, $binaan->fresh()->pendamping_id);

        // Akun loginnya dilepas — soft delete saja tidak cukup mengamankannya,
        // karena barisnya masih ada sehingga canAccessParticipant() akan lolos.
        $this->assertNull($this->pendamping->fresh()->pendamping_id);
        $this->actingAs($this->pendamping->fresh())
            ->get(route('participants.show', $binaan))
            ->assertForbidden();

        // Dipulihkan -> data & tautan binaannya lengkap kembali.
        $this->actingAs($this->admin)
            ->post(route('pendampings.restore', $this->profil->id))
            ->assertSessionHasNoErrors();

        $this->assertNotSoftDeleted('pendampings', ['id' => $this->profil->id]);
        $this->assertSame(1, $this->profil->fresh()->participants()->count());
    }

    public function test_pendamping_terhapus_muncul_di_tab_terhapus_dengan_pelakunya(): void
    {
        $this->actingAs($this->admin)->delete(route('pendampings.destroy', $this->profil));

        $this->actingAs($this->admin)
            ->get(route('pendampings.index', ['arsip' => 1]))
            ->assertOk()
            ->assertSee('Pendamping Uji')
            ->assertSee($this->admin->name)
            ->assertSee(route('pendampings.restore', $this->profil->id), false);
    }

    public function test_hanya_admin_yang_boleh_menghapus_dan_memulihkan_pendamping(): void
    {
        foreach ([$this->pendamping, $this->timKoperasi, $this->pimpinan] as $user) {
            $this->actingAs($user)->delete(route('pendampings.destroy', $this->profil))->assertForbidden();
        }

        $this->assertNotSoftDeleted('pendampings', ['id' => $this->profil->id]);

        $this->profil->delete();

        foreach ([$this->pendamping, $this->timKoperasi, $this->pimpinan] as $user) {
            $this->actingAs($user)->post(route('pendampings.restore', $this->profil->id))->assertForbidden();
        }

        $this->assertSoftDeleted('pendampings', ['id' => $this->profil->id]);
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

        $this->assertSame($this->admin->id, Participant::onlyTrashed()->find($peserta->id)->deleted_by);
        $this->assertSame($this->timKoperasi->id, BusinessUnit::onlyTrashed()->find($unit->id)->deleted_by);

        $this->assertSame($this->admin->name, Participant::onlyTrashed()->find($peserta->id)->namaPenghapus());
    }

    /** Setelah dipulihkan, jejak penghapusnya tidak lagi berlaku. */
    public function test_penghapus_dikosongkan_setelah_dipulihkan(): void
    {
        $peserta = $this->peserta('Peserta Pulih', $this->profil->id);

        $this->actingAs($this->admin)->delete(route('participants.destroy', $peserta));
        $this->assertNotNull(Participant::onlyTrashed()->find($peserta->id)->deleted_by);

        $this->actingAs($this->admin)->post(route('participants.restore', $peserta->id));
        $this->assertNull($peserta->fresh()->deleted_by);
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
