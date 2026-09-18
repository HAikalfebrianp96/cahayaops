<?php

namespace Tests\Feature;

use App\Models\Participant;
use App\Models\Pendamping;
use App\Models\PendampinganRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * RIWAYAT PENDAMPINGAN — bisa DIUBAH dan DIHAPUS, sesuai hak akses.
 *
 * Hak atas satu catatan diturunkan dari PESERTANYA, bukan dari siapa yang
 * dulu menuliskannya:
 *   Admin      -> semua catatan.
 *   Pendamping -> hanya catatan milik binaannya sendiri.
 *   Pimpinan   -> tidak sama sekali.
 *
 * Semua diuji lewat HTTP, termasuk URL langsung dan request manual.
 */
class RiwayatPendampinganCrudTest extends TestCase
{
    use RefreshDatabase;

    private Pendamping $profilA;
    private Pendamping $profilB;
    private User $pendampingA;
    private User $pendampingB;
    private User $admin;
    private Participant $binaanA;
    private Participant $binaanB;
    private PendampinganRecord $catatanA;
    private PendampinganRecord $catatanB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->profilA = Pendamping::create(['nama' => 'Rosa Linda', 'nomor_hp' => '0812', 'email' => 'rosa@pd.test', 'status' => 'Aktif']);
        $this->profilB = Pendamping::create(['nama' => 'Pendamping B', 'nomor_hp' => '0813', 'email' => 'b@pd.test', 'status' => 'Aktif']);

        $this->pendampingA = $this->user('pendamping', 'a@akun.test', $this->profilA->id);
        $this->pendampingB = $this->user('pendamping', 'b@akun.test', $this->profilB->id);
        $this->admin = $this->user('admin', 'admin@akun.test');

        $this->binaanA = $this->peserta('Binaan Milik A', $this->profilA->id);
        $this->binaanB = $this->peserta('Binaan Milik B', $this->profilB->id);

        $this->catatanA = $this->catatan($this->binaanA, $this->profilA->id, 'Evaluasi kegiatan company visit');
        $this->catatanB = $this->catatan($this->binaanB, $this->profilB->id, 'Catatan milik pendamping lain');
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

    private function catatan(Participant $peserta, ?int $pendampingId, string $materi): PendampinganRecord
    {
        return PendampinganRecord::create([
            'participant_id' => $peserta->id,
            'pendamping_id' => $pendampingId,
            'tanggal' => '2026-08-24',
            'jenis_kegiatan' => 'Evaluasi',
            'materi' => $materi,
        ]);
    }

    private function formCatatan(array $ubah = []): array
    {
        return array_merge([
            'tanggal' => '2026-08-25',
            'jenis_kegiatan' => 'Monitoring',
            'materi' => 'Materi hasil perbaikan',
        ], $ubah);
    }

    // =====================================================================
    // TAMBAH
    // =====================================================================

    public function test_pendamping_bisa_menambah_catatan_pada_binaannya(): void
    {
        $this->actingAs($this->pendampingA)
            ->post(route('pendampingan.store', $this->binaanA), $this->formCatatan([
                'materi' => 'Kunjungan lapangan pertama',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('pendampingan_records', [
            'participant_id' => $this->binaanA->id,
            'pendamping_id' => $this->profilA->id,
            'materi' => 'Kunjungan lapangan pertama',
        ]);
    }

    public function test_pendamping_tidak_bisa_menambah_catatan_pada_binaan_lain(): void
    {
        $this->actingAs($this->pendampingA)
            ->post(route('pendampingan.store', $this->binaanB), $this->formCatatan())
            ->assertForbidden();

        $this->assertDatabaseMissing('pendampingan_records', [
            'participant_id' => $this->binaanB->id,
            'materi' => 'Materi hasil perbaikan',
        ]);
    }

    /** Pendamping tidak bisa mengatasnamakan pendamping lain saat menambah. */
    public function test_pendamping_id_dipaksa_jadi_diri_sendiri_saat_menambah(): void
    {
        $this->actingAs($this->pendampingA)
            ->post(route('pendampingan.store', $this->binaanA), $this->formCatatan([
                'materi' => 'Catatan atas nama orang lain',
                'pendamping_id' => $this->profilB->id,
            ]));

        $this->assertDatabaseHas('pendampingan_records', [
            'materi' => 'Catatan atas nama orang lain',
            'pendamping_id' => $this->profilA->id,
        ]);
    }

    // =====================================================================
    // EDIT
    // =====================================================================

    public function test_pendamping_bisa_mengedit_catatan_binaannya(): void
    {
        $this->actingAs($this->pendampingA)
            ->get(route('pendampingan.edit', $this->catatanA))
            ->assertOk()
            ->assertSee('Binaan Milik A');

        $this->actingAs($this->pendampingA)
            ->put(route('pendampingan.update', $this->catatanA), $this->formCatatan([
                'materi' => 'Materi sudah diperbaiki',
                'jenis_kegiatan' => 'Konsultasi',
                'tanggal' => '2026-08-26',
                'kendala' => 'Modal terbatas',
                'tindak_lanjut' => 'Ajukan pembiayaan koperasi',
            ]))
            ->assertRedirect(route('participants.show', $this->binaanA->id));

        $segar = $this->catatanA->fresh();

        $this->assertSame('Materi sudah diperbaiki', $segar->materi);
        $this->assertSame('Konsultasi', $segar->jenis_kegiatan);
        $this->assertSame('2026-08-26', $segar->tanggal->format('Y-m-d'));
        $this->assertSame('Modal terbatas', $segar->kendala);
        $this->assertSame('Ajukan pembiayaan koperasi', $segar->tindak_lanjut);
    }

    /** INTI: catatan milik binaan Pendamping lain tertutup, GET maupun PUT. */
    public function test_pendamping_tidak_bisa_mengedit_catatan_binaan_lain(): void
    {
        $this->actingAs($this->pendampingA)
            ->get(route('pendampingan.edit', $this->catatanB))
            ->assertForbidden();

        $this->actingAs($this->pendampingA)
            ->put(route('pendampingan.update', $this->catatanB), $this->formCatatan([
                'materi' => 'DIBAJAK',
            ]))
            ->assertForbidden();

        $this->assertSame('Catatan milik pendamping lain', $this->catatanB->fresh()->materi);
    }

    /** Saat mengedit pun, pendamping_id tetap dikunci ke diri sendiri. */
    public function test_pendamping_tidak_bisa_mengganti_pelaksana_saat_mengedit(): void
    {
        $this->actingAs($this->pendampingA)
            ->put(route('pendampingan.update', $this->catatanA), $this->formCatatan([
                'pendamping_id' => $this->profilB->id,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame($this->profilA->id, $this->catatanA->fresh()->pendamping_id);
    }

    public function test_admin_bisa_mengedit_seluruh_catatan(): void
    {
        foreach ([$this->catatanA, $this->catatanB] as $catatan) {
            $this->actingAs($this->admin)->get(route('pendampingan.edit', $catatan))->assertOk();

            $this->actingAs($this->admin)
                ->put(route('pendampingan.update', $catatan), $this->formCatatan([
                    'materi' => 'Diperbaiki Admin',
                ]))
                ->assertSessionHasNoErrors();

            $this->assertSame('Diperbaiki Admin', $catatan->fresh()->materi);
        }
    }

    /** Admin boleh mengganti Pendamping pelaksana pada catatan. */
    public function test_admin_bisa_mengganti_pendamping_pelaksana(): void
    {
        $this->actingAs($this->admin)
            ->put(route('pendampingan.update', $this->catatanA), $this->formCatatan([
                'pendamping_id' => $this->profilB->id,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame($this->profilB->id, $this->catatanA->fresh()->pendamping_id);
    }

    public function test_pimpinan_tidak_bisa_mengedit_catatan(): void
    {
        $pimpinan = $this->user('pimpinan', 'pimpinan@akun.test');

        $this->actingAs($pimpinan)->get(route('pendampingan.edit', $this->catatanA))->assertForbidden();
        $this->actingAs($pimpinan)
            ->put(route('pendampingan.update', $this->catatanA), $this->formCatatan())
            ->assertForbidden();
    }

    // =====================================================================
    // HAPUS
    // =====================================================================

    public function test_pendamping_bisa_menghapus_catatan_binaannya(): void
    {
        $this->actingAs($this->pendampingA)
            ->delete(route('pendampingan.destroy', $this->catatanA))
            ->assertRedirect(route('participants.show', $this->binaanA->id));

        // Soft Delete: hilang dari tampilan, TAPI barisnya tetap ada.
        $this->assertSoftDeleted('pendampingan_records', ['id' => $this->catatanA->id]);
        $this->assertNotNull(PendampinganRecord::withTrashed()->find($this->catatanA->id));
        $this->assertSame(0, $this->binaanA->fresh()->pendampinganRecords()->count());
    }

    public function test_pendamping_tidak_bisa_menghapus_catatan_binaan_lain(): void
    {
        $this->actingAs($this->pendampingA)
            ->delete(route('pendampingan.destroy', $this->catatanB))
            ->assertForbidden();

        $this->assertNotSoftDeleted('pendampingan_records', ['id' => $this->catatanB->id]);
    }

    public function test_admin_bisa_menghapus_seluruh_catatan(): void
    {
        foreach ([$this->catatanA, $this->catatanB] as $catatan) {
            $this->actingAs($this->admin)->delete(route('pendampingan.destroy', $catatan));
            $this->assertSoftDeleted('pendampingan_records', ['id' => $catatan->id]);
        }
    }

    public function test_pimpinan_tidak_bisa_menghapus_catatan(): void
    {
        $pimpinan = $this->user('pimpinan', 'pimpinan2@akun.test');

        $this->actingAs($pimpinan)
            ->delete(route('pendampingan.destroy', $this->catatanA))
            ->assertForbidden();

        $this->assertNotSoftDeleted('pendampingan_records', ['id' => $this->catatanA->id]);
    }

    // =====================================================================
    // TAMPILAN — aksi muncul persis sesuai hak akses
    // =====================================================================

    public function test_tombol_edit_dan_hapus_muncul_untuk_pemilik_binaan(): void
    {
        $this->actingAs($this->pendampingA)
            ->get(route('participants.show', $this->binaanA))
            ->assertOk()
            ->assertSee(route('pendampingan.edit', $this->catatanA), false)
            ->assertSee(route('pendampingan.destroy', $this->catatanA), false);
    }

    public function test_pimpinan_melihat_riwayat_tanpa_tombol_aksi(): void
    {
        $pimpinan = $this->user('pimpinan', 'pimpinan3@akun.test');

        $this->actingAs($pimpinan)
            ->get(route('participants.show', $this->binaanA))
            ->assertOk()
            ->assertSee('Evaluasi kegiatan company visit')
            ->assertDontSee(route('pendampingan.edit', $this->catatanA), false);
    }

    // =====================================================================
    // ACTIVITY LOG
    // =====================================================================

    public function test_perubahan_catatan_tercatat_di_activity_log(): void
    {
        $this->actingAs($this->pendampingA)
            ->post(route('pendampingan.store', $this->binaanA), $this->formCatatan(['materi' => 'Catatan tercatat']));

        $baru = PendampinganRecord::where('materi', 'Catatan tercatat')->first();

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'mentoring_created', 'model_type' => 'PendampinganRecord', 'model_id' => $baru->id,
        ]);

        $this->actingAs($this->pendampingA)
            ->put(route('pendampingan.update', $baru), $this->formCatatan(['materi' => 'Catatan diubah']));
        $this->assertDatabaseHas('activity_logs', ['action' => 'mentoring_updated', 'model_id' => $baru->id]);

        $this->actingAs($this->pendampingA)->delete(route('pendampingan.destroy', $baru));
        $this->assertDatabaseHas('activity_logs', ['action' => 'mentoring_deleted', 'model_id' => $baru->id]);
    }

    // =====================================================================
    // MONITORING PENDAMPINGAN (Admin) tidak berubah
    // =====================================================================

    public function test_monitoring_pendampingan_tetap_admin_saja(): void
    {
        $this->actingAs($this->admin)->get(route('pendampingan.index'))->assertOk();
        $this->actingAs($this->pendampingA)->get(route('pendampingan.index'))->assertForbidden();
        $this->actingAs($this->user('pimpinan', 'pimpinan4@akun.test'))->get(route('pendampingan.index'))->assertForbidden();
    }

    /** Catatan yang di-soft-delete tidak lagi muncul di Monitoring. */
    public function test_catatan_terhapus_hilang_dari_monitoring(): void
    {
        $this->catatanA->delete();

        $this->actingAs($this->admin)
            ->get(route('pendampingan.index'))
            ->assertOk()
            ->assertDontSee('Evaluasi kegiatan company visit')
            ->assertSee('Catatan milik pendamping lain');
    }
}
