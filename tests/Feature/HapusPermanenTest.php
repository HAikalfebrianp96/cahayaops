<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\KoperasiMember;
use App\Models\Participant;
use App\Models\Pendamping;
use App\Models\PendampinganRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * HAPUS PERMANEN — pengganti ArsipDanRestoreTest, EditArsipRestoreHapusPermanenTest,
 * dan UxPeranDanArsipTest.
 *
 * Konsep Arsip dilepas dari CahayaOS (keputusan pemilik 18 Sep 2026): tidak
 * ada lagi Arsipkan, Pulihkan, maupun halaman Arsip Data. Yang tersisa adalah
 * Hapus Permanen, dan justru karena tidak bisa dibatalkan, pengamannya yang
 * harus diuji ketat.
 *
 * Yang dijaga kelas ini:
 *   1. Jalur arsip benar-benar hilang (rute maupun menu).
 *   2. Data tanpa tanggungan memang terhapus dari database, bukan disembunyikan.
 *   3. Data yang masih dipakai data lain DITOLAK beserta alasannya.
 *   4. Otorisasi tetap berlaku.
 *   5. Activity Log mencatat penghapusan, tanpa pernah memuat password.
 *   6. Siklus akun (aktif/nonaktif) TIDAK ikut terhapus konsepnya.
 */
class HapusPermanenTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $pendampingA;
    private Pendamping $profilA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->profilA = Pendamping::create([
            'nama' => 'Rosa Linda', 'nomor_hp' => '0812', 'email' => 'rosa@hp.test', 'status' => 'Aktif',
        ]);

        $this->admin = $this->user('admin', 'admin@hp.test');
        $this->pendampingA = $this->user('pendamping', 'rosa.akun@hp.test', $this->profilA->id);
    }

    private function user(string $role, string $email, ?int $pendampingId = null): User
    {
        return User::create([
            'name' => 'User ' . $email, 'email' => $email, 'password' => 'password123',
            'role' => $role, 'pendamping_id' => $pendampingId, 'is_active' => true,
        ]);
    }

    private function peserta(string $nama = 'Peserta Uji'): Participant
    {
        return Participant::create([
            'nama_peserta' => $nama, 'nomor_hp' => '0812', 'nama_usaha' => 'Usaha ' . $nama,
            'kategori_usaha' => 'Jasa', 'tanggal_mulai_program' => '2026-01-10',
            'status' => 'Aktif', 'pendamping_id' => $this->profilA->id,
        ]);
    }

    // ==========================================================
    // 1. JALUR ARSIP BENAR-BENAR HILANG
    // ==========================================================

    public function test_rute_arsip_tidak_ada_lagi(): void
    {
        foreach (['arsip.index', 'arsip.hapus-permanen'] as $nama) {
            $this->assertFalse(
                Route::has($nama),
                "Route [{$nama}] seharusnya sudah dihapus bersama konsep Arsip.",
            );
        }
    }

    public function test_tidak_ada_satu_pun_rute_restore_arsip(): void
    {
        // Restore DATABASE (cadangan) berbeda urusan dan tetap boleh ada.
        $restoreArsip = collect(Route::getRoutes())
            ->map(fn ($r) => $r->getName())
            ->filter()
            ->filter(fn (string $n) => str_contains($n, 'restore'))
            ->reject(fn (string $n) => str_starts_with($n, 'backup-database.'))
            ->values();

        $this->assertTrue(
            $restoreArsip->isEmpty(),
            'Masih ada rute restore arsip: ' . $restoreArsip->implode(', '),
        );
    }

    public function test_url_arsip_tidak_bisa_dibuka(): void
    {
        $this->actingAs($this->admin)->get('/arsip')->assertNotFound();
    }

    public function test_menu_arsip_data_tidak_muncul_di_sidebar(): void
    {
        $this->actingAs($this->admin)->get(route('participants.index'))
            ->assertOk()
            ->assertDontSee('Arsip Data');
    }

    // ==========================================================
    // 2. BENAR-BENAR TERHAPUS
    // ==========================================================

    public function test_peserta_tanpa_tanggungan_terhapus_dari_database(): void
    {
        $peserta = $this->peserta();

        $this->actingAs($this->admin)
            ->delete(route('participants.destroy', $peserta))
            ->assertRedirect(route('participants.index'));

        // Bukan sekadar hilang dari daftar: barisnya memang tidak ada lagi,
        // termasuk bila dicari dengan withTrashed().
        $this->assertDatabaseMissing('participants', ['id' => $peserta->id]);
        $this->assertNull(Participant::withTrashed()->find($peserta->id));
    }

    public function test_catatan_pendampingan_terhapus_permanen(): void
    {
        $peserta = $this->peserta();
        $catatan = PendampinganRecord::create([
            'participant_id' => $peserta->id, 'pendamping_id' => $this->profilA->id,
            'tanggal' => '2026-08-01', 'jenis_kegiatan' => 'Konsultasi', 'materi' => 'Kunjungan',
        ]);

        $this->actingAs($this->admin)->delete(route('pendampingan.destroy', $catatan));

        $this->assertDatabaseMissing('pendampingan_records', ['id' => $catatan->id]);
    }

    // ==========================================================
    // 3. PROTEKSI RELASI
    // ==========================================================

    public function test_peserta_yang_punya_catatan_tidak_bisa_dihapus(): void
    {
        $peserta = $this->peserta();
        PendampinganRecord::create([
            'participant_id' => $peserta->id, 'pendamping_id' => $this->profilA->id,
            'tanggal' => '2026-08-01', 'jenis_kegiatan' => 'Konsultasi', 'materi' => 'Kunjungan',
        ]);

        $this->actingAs($this->admin)->delete(route('participants.destroy', $peserta));

        // Yang penting bukan sekadar "gagal", melainkan pesertanya MASIH ADA.
        $this->assertDatabaseHas('participants', ['id' => $peserta->id]);
    }

    public function test_penolakan_menyebut_apa_yang_masih_memakai_data(): void
    {
        $peserta = $this->peserta();
        PendampinganRecord::create([
            'participant_id' => $peserta->id, 'pendamping_id' => $this->profilA->id,
            'tanggal' => '2026-08-01', 'jenis_kegiatan' => 'Konsultasi', 'materi' => 'Kunjungan',
        ]);

        $respons = $this->actingAs($this->admin)
            ->from(route('participants.index'))
            ->delete(route('participants.destroy', $peserta));

        $pesan = session('error');

        $this->assertIsString($pesan, 'Penolakan harus disertai penjelasan, bukan diam saja.');
        $this->assertStringContainsString('catatan pendampingan', $pesan);
    }

    public function test_pendamping_yang_masih_membina_tidak_bisa_dihapus(): void
    {
        $this->peserta('Binaan Aktif');

        $this->actingAs($this->admin)->delete(route('pendampings.destroy', $this->profilA));

        $this->assertDatabaseHas('pendampings', ['id' => $this->profilA->id]);
        $this->assertStringContainsString('membina', (string) session('error'));
    }

    public function test_pelanggan_yang_punya_transaksi_tidak_bisa_dihapus(): void
    {
        $pelanggan = Customer::create(['nama' => 'Toko Maju', 'nomor_hp' => '0812']);

        // Tanpa transaksi: boleh dihapus.
        $this->actingAs($this->admin)->delete(route('koperasi.pelanggan.destroy', $pelanggan));
        $this->assertDatabaseMissing('customers', ['id' => $pelanggan->id]);
    }

    // ==========================================================
    // 4. OTORISASI
    // ==========================================================

    public function test_pendamping_tidak_boleh_menghapus_peserta(): void
    {
        $peserta = $this->peserta();

        $this->actingAs($this->pendampingA)
            ->delete(route('participants.destroy', $peserta))
            ->assertForbidden();

        $this->assertDatabaseHas('participants', ['id' => $peserta->id]);
    }

    public function test_tamu_tidak_bisa_menghapus_apa_pun(): void
    {
        $peserta = $this->peserta();

        $this->delete(route('participants.destroy', $peserta))->assertRedirect(route('login'));

        $this->assertDatabaseHas('participants', ['id' => $peserta->id]);
    }

    // ==========================================================
    // 5. ACTIVITY LOG
    // ==========================================================

    public function test_activity_log_mencatat_penghapusan_permanen(): void
    {
        $peserta = $this->peserta('Peserta Dicatat');

        $this->actingAs($this->admin)->delete(route('participants.destroy', $peserta));

        $log = ActivityLog::where('action', 'permanently_deleted')
            ->where('model_type', 'Participant')
            ->latest('id')
            ->first();

        $this->assertNotNull($log, 'Penghapusan permanen wajib meninggalkan jejak.');
        $this->assertSame($this->admin->id, $log->user_id);
        $this->assertSame($peserta->id, $log->model_id);
        $this->assertStringContainsString('Peserta Dicatat', $log->description);
    }

    public function test_activity_log_tidak_pernah_memuat_password(): void
    {
        $peserta = $this->peserta();

        $this->actingAs($this->admin)->delete(route('participants.destroy', $peserta));

        foreach (ActivityLog::all() as $log) {
            $isi = json_encode($log->toArray());

            $this->assertStringNotContainsString('password', strtolower((string) $isi));
            $this->assertStringNotContainsString($this->admin->password, (string) $isi);
        }
    }

    // ==========================================================
    // 6. SIKLUS AKUN PENGGUNA — TETAP ADA
    // ==========================================================

    public function test_akun_masih_bisa_dinonaktifkan_dan_diaktifkan(): void
    {
        $target = $this->user('pimpinan', 'target@hp.test');

        $this->actingAs($this->admin)->post(route('manajemen-user.status', $target));
        $this->assertFalse($target->fresh()->isActive());

        $this->actingAs($this->admin)->post(route('manajemen-user.status', $target));
        $this->assertTrue($target->fresh()->isActive());
    }

    public function test_admin_tidak_bisa_menghapus_akunnya_sendiri(): void
    {
        $this->actingAs($this->admin)->delete(route('manajemen-user.destroy', $this->admin));

        $this->assertDatabaseHas('users', ['id' => $this->admin->id]);
    }

    public function test_admin_aktif_terakhir_tidak_bisa_dihapus(): void
    {
        $adminKedua = $this->user('admin', 'admin2@hp.test');

        // Admin pertama menghapus admin kedua: boleh, masih ada satu Admin.
        $this->actingAs($this->admin)->delete(route('manajemen-user.destroy', $adminKedua));
        $this->assertDatabaseMissing('users', ['id' => $adminKedua->id]);

        // Sekarang $this->admin adalah Admin terakhir. Dibuat admin lain yang
        // NONAKTIF supaya ada pelaku yang bisa mencoba menghapusnya.
        $penghapus = $this->user('admin', 'admin3@hp.test');

        $this->actingAs($penghapus)->delete(route('manajemen-user.destroy', $this->admin));

        $this->assertDatabaseHas('users', ['id' => $this->admin->id]);
    }

    public function test_akun_yang_punya_jejak_tidak_bisa_dihapus(): void
    {
        $target = $this->user('pimpinan', 'berjejak@hp.test');

        ActivityLog::create([
            'user_id' => $target->id,
            'action' => 'updated',
            'model_type' => 'Participant',
            'model_id' => 1,
            'description' => 'Menyunting sesuatu',
        ]);

        $this->actingAs($this->admin)->delete(route('manajemen-user.destroy', $target));

        $this->assertDatabaseHas('users', ['id' => $target->id]);
    }

    public function test_bukan_admin_tidak_bisa_menghapus_akun(): void
    {
        $target = $this->user('pimpinan', 'korban@hp.test');

        $this->actingAs($this->pendampingA)
            ->delete(route('manajemen-user.destroy', $target))
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $target->id]);
    }

    // ==========================================================
    // 7. ANGGOTA KOPERASI — SIMPANAN TETAP MENAHAN
    // ==========================================================

    public function test_anggota_tanpa_simpanan_bisa_dihapus(): void
    {
        $anggota = KoperasiMember::create([
            'nama' => 'Anggota Bersih',
            'nomor_anggota' => 'A-001',
            'nomor_hp' => '0812',
            'tanggal_bergabung' => '2026-01-05',
            'status' => 'Aktif',
        ]);

        $this->actingAs($this->admin)->delete(route('koperasi.anggota.destroy', $anggota));

        $this->assertDatabaseMissing('koperasi_members', ['id' => $anggota->id]);
    }
}
