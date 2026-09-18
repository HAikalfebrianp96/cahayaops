<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\BusinessUnit;
use App\Models\Customer;
use App\Models\HppCalculation;
use App\Models\KoperasiMember;
use App\Models\OmzetReport;
use App\Models\Participant;
use App\Models\Pendamping;
use App\Models\PendampinganRecord;
use App\Models\PengeluaranCategory;
use App\Models\ProductService;
use App\Models\TrainingRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * ARSIP DATA TERPUSAT + RESTORE SELURUH MODUL.
 *
 * Sebelas modul memakai Soft Delete, tapi hanya enam yang punya jalur
 * pemulihan; lima sisanya (riwayat pendampingan, pelatihan, omzet, HPP, dan
 * produk lewat halaman arsip) hanya bisa dihapus tanpa pernah bisa
 * dikembalikan. Kelas ini menutup lubang itu dan menjaga tiga hal:
 *
 *   1. SETIAP modul ber-Soft Delete punya route restore.
 *   2. Halaman Arsip menampilkan tab SESUAI KEWENANGAN, dan isinya
 *      ter-scope — Pendamping tidak boleh melihat arsip binaan orang lain.
 *   3. Pemulihan tetap diperiksa policy modulnya masing-masing, bukan oleh
 *      halaman arsip.
 */
class ArsipDanRestoreTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $timKoperasi;
    private User $pendampingA;
    private User $pendampingB;
    private User $pimpinan;
    private Pendamping $profilA;
    private Pendamping $profilB;
    private Participant $binaanA;
    private Participant $binaanB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->profilA = Pendamping::create(['nama' => 'Rosa Linda', 'nomor_hp' => '0812', 'email' => 'a@ar.test', 'status' => 'Aktif']);
        $this->profilB = Pendamping::create(['nama' => 'Pendamping B', 'nomor_hp' => '0813', 'email' => 'b@ar.test', 'status' => 'Aktif']);

        $this->admin = $this->user('admin', 'admin@ar.test');
        $this->timKoperasi = $this->user(User::ROLE_TIM_KOPERASI, 'tk@ar.test');
        $this->pendampingA = $this->user('pendamping', 'a.akun@ar.test', $this->profilA->id);
        $this->pendampingB = $this->user('pendamping', 'b.akun@ar.test', $this->profilB->id);
        $this->pimpinan = $this->user('pimpinan', 'pimpinan@ar.test');

        $this->binaanA = $this->peserta('Binaan Milik A', $this->profilA->id);
        $this->binaanB = $this->peserta('Binaan Milik B', $this->profilB->id);
    }

    private function user(string $role, string $email, ?int $pendampingId = null): User
    {
        return User::create([
            'name' => 'User ' . $email, 'email' => $email, 'password' => 'password123',
            'role' => $role, 'pendamping_id' => $pendampingId, 'is_active' => true,
        ]);
    }

    private function peserta(string $nama, ?int $pendampingId): Participant
    {
        return Participant::create([
            'nama_peserta' => $nama, 'nomor_hp' => '0812', 'nama_usaha' => 'Usaha ' . $nama,
            'kategori_usaha' => 'Jasa', 'tanggal_mulai_program' => '2026-01-10',
            'status' => 'Aktif', 'pendamping_id' => $pendampingId,
        ]);
    }

    private function catatan(Participant $p, string $materi = 'Kunjungan'): PendampinganRecord
    {
        return PendampinganRecord::create([
            'participant_id' => $p->id, 'pendamping_id' => $p->pendamping_id,
            'tanggal' => '2026-08-01', 'jenis_kegiatan' => 'Konsultasi', 'materi' => $materi,
        ]);
    }

    private function pelatihan(Participant $p, string $nama = 'Pelatihan NIB'): TrainingRecord
    {
        return $p->trainingRecords()->create([
            'tanggal' => '2026-05-10', 'nama_pelatihan' => $nama, 'status' => 'Selesai',
        ]);
    }

    private function omzet(Participant $p, int $bulan = 6): OmzetReport
    {
        return $p->omzetReports()->create([
            'periode_bulan' => $bulan, 'periode_tahun' => 2026, 'omzet' => 5000000,
        ]);
    }

    private function hpp(Participant $p, string $produk = 'Keripik'): HppCalculation
    {
        return HppCalculation::create([
            'participant_id' => $p->id, 'nama_produk' => $produk,
            'satuan_produksi' => 'pack', 'jumlah_produksi' => 100,
            'total_biaya_produksi' => 500000, 'hpp_per_unit' => 5000,
            'harga_jual' => 8000, 'laba_per_unit' => 3000,
            'margin_persen' => 37.5, 'estimasi_laba_batch' => 300000,
        ]);
    }

    // =====================================================================
    // SETIAP modul ber-Soft Delete punya jalur pemulihan
    // =====================================================================

    public function test_seluruh_modul_soft_delete_punya_route_restore(): void
    {
        foreach ([
            'participants.restore', 'pendampings.restore',
            'pendampingan.restore', 'training.restore', 'omzet.restore', 'hpp.restore',
            'koperasi.anggota.restore', 'koperasi.unit-usaha.restore',
            'koperasi.produk.restore', 'koperasi.pelanggan.restore',
            'koperasi.kategori-pengeluaran.restore',
        ] as $rute) {
            $this->assertTrue(Route::has($rute), "Route restore hilang: $rute");
        }
    }

    // =====================================================================
    // HALAMAN ARSIP — tab mengikuti kewenangan
    // =====================================================================

    public function test_admin_melihat_seluruh_tab_arsip(): void
    {
        $respons = $this->actingAs($this->admin)->get(route('arsip.index'));

        $respons->assertOk();
        foreach (['Peserta', 'Pendamping', 'Riwayat Pendampingan', 'Pelatihan',
            'Laporan Omzet', 'Perhitungan HPP', 'Anggota Koperasi', 'Unit Usaha',
            'Produk &amp; Jasa', 'Pelanggan', 'Kategori Pengeluaran'] as $judul) {
            $respons->assertSee($judul, false);
        }
    }

    /** Pendamping hanya melihat tab catatan binaannya — tanpa tab Peserta. */
    public function test_pendamping_hanya_melihat_tab_catatan_binaan(): void
    {
        $respons = $this->actingAs($this->pendampingA)->get(route('arsip.index'));

        $respons->assertOk()
            ->assertSee('Riwayat Pendampingan')
            ->assertSee('Pelatihan')
            // TIDAK ada tab modul koperasi maupun Pendamping.
            ->assertDontSee('Anggota Koperasi')
            ->assertDontSee('Unit Usaha')
            ->assertDontSee('Kategori Pengeluaran');

        // Tab 'peserta' tidak tersedia -> permintaannya diabaikan, bukan
        // menampilkan peserta seluruh organisasi.
        $this->actingAs($this->pendampingA)
            ->get(route('arsip.index', ['tab' => 'peserta']))
            ->assertOk()
            ->assertViewHas('tabAktif', fn ($tab) => $tab !== 'peserta');
    }

    public function test_tim_koperasi_hanya_melihat_tab_koperasi(): void
    {
        $respons = $this->actingAs($this->timKoperasi)->get(route('arsip.index'));

        $respons->assertOk()
            ->assertSee('Anggota Koperasi')
            ->assertSee('Unit Usaha')
            ->assertDontSee('Riwayat Pendampingan')
            ->assertDontSee('Perhitungan HPP');
    }

    /** Pimpinan read-only tidak menghapus apa pun, jadi tidak punya arsip. */
    public function test_pimpinan_ditolak_di_halaman_arsip(): void
    {
        $this->actingAs($this->pimpinan)->get(route('arsip.index'))->assertForbidden();
    }

    public function test_pendamping_tanpa_tautan_ditolak_di_arsip(): void
    {
        $this->actingAs($this->user('pendamping', 'lepas@ar.test'))
            ->get(route('arsip.index'))
            ->assertForbidden();
    }

    // =====================================================================
    // INTI: arsip Pendamping TER-SCOPE
    // =====================================================================

    public function test_arsip_pendamping_tidak_memuat_catatan_binaan_lain(): void
    {
        $milikA = $this->catatan($this->binaanA, 'Catatan Milik A');
        $milikB = $this->catatan($this->binaanB, 'Catatan Milik B');

        $milikA->delete();
        $milikB->delete();

        $this->actingAs($this->pendampingA)
            ->get(route('arsip.index', ['tab' => 'pendampingan']))
            ->assertOk()
            ->assertSee('Catatan Milik A')
            ->assertDontSee('Catatan Milik B');

        // Admin melihat keduanya.
        $this->actingAs($this->admin)
            ->get(route('arsip.index', ['tab' => 'pendampingan']))
            ->assertOk()
            ->assertSee('Catatan Milik A')
            ->assertSee('Catatan Milik B');
    }

    public function test_arsip_menampilkan_kapan_dan_oleh_siapa(): void
    {
        $catatan = $this->catatan($this->binaanA, 'Catatan Dihapus');

        $this->actingAs($this->admin)->delete(route('pendampingan.destroy', $catatan));

        $this->actingAs($this->admin)
            ->get(route('arsip.index', ['tab' => 'pendampingan']))
            ->assertOk()
            ->assertSee('Catatan Dihapus')
            ->assertSee($this->admin->name)
            ->assertSee(route('pendampingan.restore', $catatan->id), false);
    }

    public function test_pencarian_arsip_bekerja(): void
    {
        $this->pelatihan($this->binaanA, 'Pelatihan Halal')->delete();
        $this->pelatihan($this->binaanA, 'Pelatihan Pembukuan')->delete();

        $this->actingAs($this->admin)
            ->get(route('arsip.index', ['tab' => 'pelatihan', 'q' => 'Halal']))
            ->assertOk()
            ->assertSee('Pelatihan Halal')
            ->assertDontSee('Pelatihan Pembukuan');
    }

    // =====================================================================
    // RESTORE — empat modul catatan peserta
    // =====================================================================

    public function test_pendamping_bisa_memulihkan_catatan_binaannya(): void
    {
        $catatan = $this->catatan($this->binaanA);
        $pelatihan = $this->pelatihan($this->binaanA);
        $omzet = $this->omzet($this->binaanA);
        $hpp = $this->hpp($this->binaanA);

        foreach ([$catatan, $pelatihan, $omzet, $hpp] as $model) {
            $model->delete();
        }

        $this->actingAs($this->pendampingA)->post(route('pendampingan.restore', $catatan->id))->assertRedirect();
        $this->actingAs($this->pendampingA)->post(route('training.restore', $pelatihan->id))->assertRedirect();
        $this->actingAs($this->pendampingA)->post(route('omzet.restore', $omzet->id))->assertRedirect();
        $this->actingAs($this->pendampingA)->post(route('hpp.restore', $hpp->id))->assertRedirect();

        $this->assertNotSoftDeleted('pendampingan_records', ['id' => $catatan->id]);
        $this->assertNotSoftDeleted('training_records', ['id' => $pelatihan->id]);
        $this->assertNotSoftDeleted('omzet_reports', ['id' => $omzet->id]);
        $this->assertNotSoftDeleted('hpp_calculations', ['id' => $hpp->id]);
    }

    /** INTI: catatan binaan Pendamping LAIN tertutup rapat. */
    public function test_pendamping_tidak_bisa_memulihkan_catatan_binaan_lain(): void
    {
        $catatan = $this->catatan($this->binaanB);
        $pelatihan = $this->pelatihan($this->binaanB);
        $omzet = $this->omzet($this->binaanB);
        $hpp = $this->hpp($this->binaanB);

        foreach ([$catatan, $pelatihan, $omzet, $hpp] as $model) {
            $model->delete();
        }

        $this->actingAs($this->pendampingA)->post(route('pendampingan.restore', $catatan->id))->assertForbidden();
        $this->actingAs($this->pendampingA)->post(route('training.restore', $pelatihan->id))->assertForbidden();
        $this->actingAs($this->pendampingA)->post(route('omzet.restore', $omzet->id))->assertForbidden();
        $this->actingAs($this->pendampingA)->post(route('hpp.restore', $hpp->id))->assertForbidden();

        $this->assertSoftDeleted('pendampingan_records', ['id' => $catatan->id]);
        $this->assertSoftDeleted('training_records', ['id' => $pelatihan->id]);
        $this->assertSoftDeleted('omzet_reports', ['id' => $omzet->id]);
        $this->assertSoftDeleted('hpp_calculations', ['id' => $hpp->id]);
    }

    public function test_pimpinan_tidak_bisa_memulihkan_apa_pun(): void
    {
        $catatan = $this->catatan($this->binaanA);
        $catatan->delete();

        $this->actingAs($this->pimpinan)->post(route('pendampingan.restore', $catatan->id))->assertForbidden();
        $this->assertSoftDeleted('pendampingan_records', ['id' => $catatan->id]);
    }

    /**
     * INTI: catatan milik peserta yang IKUT diarsipkan tetap dikenali
     * pemiliknya. Tanpa pesertaTermasukArsip(), relasi participant() kena
     * global scope dan mengembalikan null, sehingga Pendamping pemiliknya
     * sendiri justru ditolak.
     */
    public function test_pendamping_tetap_dikenali_saat_pesertanya_ikut_diarsipkan(): void
    {
        $catatan = $this->catatan($this->binaanA, 'Catatan Ikut Terarsip');
        $catatan->delete();
        $this->binaanA->delete();

        // Halaman arsipnya tetap menampilkan catatan itu...
        $this->actingAs($this->pendampingA)
            ->get(route('arsip.index', ['tab' => 'pendampingan']))
            ->assertOk()
            ->assertSee('Catatan Ikut Terarsip')
            ->assertSee('Binaan Milik A');

        // ...dan pemiliknya tetap boleh memulihkannya.
        $this->actingAs($this->pendampingA)
            ->post(route('pendampingan.restore', $catatan->id))
            ->assertRedirect();

        $this->assertNotSoftDeleted('pendampingan_records', ['id' => $catatan->id]);
    }

    // =====================================================================
    // RESTORE tidak merusak relasi & tidak menggandakan
    // =====================================================================

    public function test_restore_tidak_menggandakan_data(): void
    {
        $catatan = $this->catatan($this->binaanA);
        $sebelum = PendampinganRecord::withTrashed()->count();

        $catatan->delete();
        $this->actingAs($this->admin)->post(route('pendampingan.restore', $catatan->id));

        $this->assertSame($sebelum, PendampinganRecord::withTrashed()->count());
        $this->assertSame(1, PendampinganRecord::where('id', $catatan->id)->count());
    }

    /**
     * Omzet punya unique index (peserta, bulan, tahun). Memulihkannya harus
     * aman — selama baris itu masih diarsipkan, tidak mungkin ada baris lain
     * untuk periode yang sama.
     */
    public function test_restore_omzet_tidak_melanggar_unique_periode(): void
    {
        $omzet = $this->omzet($this->binaanA, 6);
        $omzet->delete();

        $this->actingAs($this->admin)->post(route('omzet.restore', $omzet->id))->assertRedirect();

        $this->assertNotSoftDeleted('omzet_reports', ['id' => $omzet->id]);
        $this->assertSame(1, OmzetReport::withTrashed()
            ->where('participant_id', $this->binaanA->id)
            ->where('periode_bulan', 6)->where('periode_tahun', 2026)->count());
    }

    /** Peserta dipulihkan -> seluruh catatannya ikut terlihat kembali. */
    public function test_restore_peserta_mengembalikan_relasinya(): void
    {
        $this->catatan($this->binaanA);
        $this->pelatihan($this->binaanA);

        $this->actingAs($this->admin)->delete(route('participants.destroy', $this->binaanA));
        $this->actingAs($this->admin)->post(route('participants.restore', $this->binaanA->id));

        $segar = $this->binaanA->fresh();

        $this->assertNotSoftDeleted('participants', ['id' => $this->binaanA->id]);
        $this->assertSame(1, $segar->pendampinganRecords()->count());
        $this->assertSame(1, $segar->trainingRecords()->count());
    }

    // =====================================================================
    // ACTIVITY LOG — restore tercatat, tanpa sistem log kedua
    // =====================================================================

    public function test_restore_tercatat_di_activity_log_yang_sama(): void
    {
        $catatan = $this->catatan($this->binaanA);
        $pelatihan = $this->pelatihan($this->binaanA);
        $omzet = $this->omzet($this->binaanA);

        foreach ([$catatan, $pelatihan, $omzet] as $model) {
            $model->delete();
        }

        $this->actingAs($this->admin)->post(route('pendampingan.restore', $catatan->id));
        $this->actingAs($this->admin)->post(route('training.restore', $pelatihan->id));
        $this->actingAs($this->admin)->post(route('omzet.restore', $omzet->id));

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'mentoring_restored', 'model_type' => 'PendampinganRecord', 'model_id' => $catatan->id,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'training_restored', 'model_type' => 'TrainingRecord', 'model_id' => $pelatihan->id,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'omzet_restored', 'model_type' => 'OmzetReport', 'model_id' => $omzet->id,
        ]);

        // Tidak ada tabel log kedua.
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasTable('audit_logs'));
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasTable('activity_logs_v2'));
    }

    // =====================================================================
    // MODUL KOPERASI di halaman arsip
    // =====================================================================

    public function test_tim_koperasi_bisa_memulihkan_data_koperasi_dari_arsip(): void
    {
        $unit = BusinessUnit::create(['nama' => 'Unit Arsip', 'kode' => 'UNIT-AR', 'tipe' => 'Barang', 'status' => 'Aktif']);
        $produk = ProductService::create([
            'unit_id' => $unit->id, 'kode' => 'PRD-AR', 'nama' => 'Produk Arsip',
            'jenis' => 'Barang', 'satuan' => 'pack', 'harga_jual' => 10000, 'status' => 'Aktif',
        ]);
        $pelanggan = Customer::create(['nama' => 'Pelanggan Arsip']);
        $kategori = PengeluaranCategory::create(['nama' => 'Kategori Arsip', 'status' => 'Aktif']);
        $anggota = KoperasiMember::create([
            'nama' => 'Anggota Arsip', 'nomor_hp' => '0812',
            'tanggal_bergabung' => '2026-02-01', 'status' => 'Aktif',
        ]);

        foreach ([$produk, $pelanggan, $kategori, $anggota, $unit] as $model) {
            $model->delete();
        }

        $this->actingAs($this->timKoperasi)
            ->get(route('arsip.index', ['tab' => 'produk']))
            ->assertOk()
            ->assertSee('Produk Arsip');

        $this->actingAs($this->timKoperasi)->post(route('koperasi.produk.restore', $produk->id))->assertRedirect();
        $this->actingAs($this->timKoperasi)->post(route('koperasi.pelanggan.restore', $pelanggan->id))->assertRedirect();
        $this->actingAs($this->timKoperasi)->post(route('koperasi.kategori-pengeluaran.restore', $kategori->id))->assertRedirect();
        $this->actingAs($this->timKoperasi)->post(route('koperasi.anggota.restore', $anggota->id))->assertRedirect();
        $this->actingAs($this->timKoperasi)->post(route('koperasi.unit-usaha.restore', $unit->id))->assertRedirect();

        $this->assertNotSoftDeleted('products_services', ['id' => $produk->id]);
        $this->assertNotSoftDeleted('customers', ['id' => $pelanggan->id]);
        $this->assertNotSoftDeleted('pengeluaran_categories', ['id' => $kategori->id]);
        $this->assertNotSoftDeleted('koperasi_members', ['id' => $anggota->id]);
        $this->assertNotSoftDeleted('business_units', ['id' => $unit->id]);
    }

    public function test_pendamping_tidak_bisa_memulihkan_data_koperasi(): void
    {
        $unit = BusinessUnit::create(['nama' => 'Unit X', 'kode' => 'UNIT-X', 'tipe' => 'Barang', 'status' => 'Aktif']);
        $unit->delete();

        $this->actingAs($this->pendampingA)
            ->post(route('koperasi.unit-usaha.restore', $unit->id))
            ->assertForbidden();

        $this->assertSoftDeleted('business_units', ['id' => $unit->id]);
    }

    // =====================================================================
    // Transaksi yang DIBATALKAN bukan data terhapus
    // =====================================================================

    /**
     * Pembatalan transaksi dan Soft Delete adalah dua hal BERBEDA. Transaksi
     * yang dibatalkan tidak boleh muncul di Arsip — ia masih ada di daftarnya
     * sendiri, hanya berhenti dihitung.
     */
    public function test_transaksi_dibatalkan_tidak_muncul_di_arsip(): void
    {
        $tabTersedia = $this->actingAs($this->admin)
            ->get(route('arsip.index'))
            ->viewData('tabTersedia');

        $this->assertArrayNotHasKey('penjualan', $tabTersedia);
        $this->assertArrayNotHasKey('simpanan', $tabTersedia);
        $this->assertArrayNotHasKey('dana', $tabTersedia);
    }

    // =====================================================================
    // ACTIVITY LOG — kalimat yang bisa dibaca manusia
    // =====================================================================

    public function test_activity_log_disajikan_sebagai_kalimat(): void
    {
        $log = new ActivityLog([
            'action' => 'participant_updated',
            'model_type' => 'Participant',
            'description' => 'Updated Participant: Ahmad',
        ]);
        $log->setRelation('user', $this->pendampingA);

        $this->assertStringContainsString('mengubah data peserta', $log->kalimat());
        $this->assertStringContainsString('Pendamping', $log->kalimat());

        // Rincian teknis aslinya TIDAK hilang.
        $this->assertSame('Updated Participant: Ahmad', $log->rincian());
    }

    /** Aksi yang tidak dikenali dikembalikan apa adanya, bukan dikarang. */
    public function test_activity_log_tak_dikenali_tetap_apa_adanya(): void
    {
        $log = new ActivityLog([
            'action' => 'password_reset',
            'model_type' => 'User',
            'description' => 'Reset password akun Budi',
        ]);

        $this->assertSame('Reset password akun Budi', $log->kalimat());
    }

    public function test_riwayat_perubahan_tampil_sebagai_kalimat_di_detail_peserta(): void
    {
        $this->actingAs($this->admin)
            ->put(route('participants.update', $this->binaanA), [
                'nama_peserta' => 'Binaan Milik A', 'nomor_hp' => '081211112222',
                'nama_usaha' => 'Usaha', 'kategori_usaha' => 'Jasa',
                'tanggal_mulai_program' => '2026-01-10', 'status' => 'Aktif',
                'pendamping_id' => $this->profilA->id,
            ]);

        $this->actingAs($this->admin)
            ->get(route('participants.show', $this->binaanA))
            ->assertOk()
            ->assertSee('Riwayat Perubahan')
            ->assertSee('mengubah data peserta');
    }
}
