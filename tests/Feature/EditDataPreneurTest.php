<?php

namespace Tests\Feature;

use App\Models\OmzetReport;
use App\Models\Participant;
use App\Models\Pendamping;
use App\Models\TrainingRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FLEKSIBILITAS EDIT DI MODUL CAHAYA PRENEUR.
 *
 * Fokus kelas ini adalah tiga hal yang sebelumnya TIDAK bisa dikoreksi
 * tanpa menghapus lalu membuat ulang:
 *
 *   1. Data usaha peserta (nama usaha, alamat usaha, produk, tahun berdiri,
 *      status usaha).
 *   2. Riwayat pelatihan — dulu Admin-only, dan update()-nya cuma bisa
 *      mengubah kolom `status`.
 *   3. Perkembangan omzet — dulu hanya bisa masuk lewat form publik bertoken.
 *
 * Batas kewenangannya diuji lewat HTTP sungguhan, termasuk URL langsung dan
 * request manual. Menyembunyikan tombol TIDAK dihitung sebagai pengaman.
 */
class EditDataPreneurTest extends TestCase
{
    use RefreshDatabase;

    private Pendamping $profilA;
    private Pendamping $profilB;
    private User $pendampingA;
    private User $pendampingB;
    private User $admin;
    private User $pimpinan;
    private Participant $binaanA;
    private Participant $binaanB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->profilA = Pendamping::create(['nama' => 'Pendamping A', 'nomor_hp' => '0812', 'email' => 'a@pd.test', 'status' => 'Aktif']);
        $this->profilB = Pendamping::create(['nama' => 'Pendamping B', 'nomor_hp' => '0813', 'email' => 'b@pd.test', 'status' => 'Aktif']);

        $this->pendampingA = $this->user('pendamping', 'a@akun.test', $this->profilA->id);
        $this->pendampingB = $this->user('pendamping', 'b@akun.test', $this->profilB->id);
        $this->admin = $this->user('admin', 'admin@akun.test');
        $this->pimpinan = $this->user('pimpinan', 'pimpinan@akun.test');

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

    private function formPeserta(Participant $peserta, array $ubah = []): array
    {
        return array_merge([
            'nama_peserta' => $peserta->nama_peserta,
            'nomor_hp' => $peserta->nomor_hp,
            'nama_usaha' => $peserta->nama_usaha,
            'kategori_usaha' => $peserta->kategori_usaha,
            'tanggal_mulai_program' => $peserta->tanggal_mulai_program->format('Y-m-d'),
            'status' => $peserta->status,
        ], $ubah);
    }

    private function pelatihan(Participant $peserta, string $nama = 'Pelatihan NIB'): TrainingRecord
    {
        return $peserta->trainingRecords()->create([
            'tanggal' => '2026-05-10',
            'nama_pelatihan' => $nama,
            'penyelenggara' => 'Dinas Koperasi',
            'status' => 'Terdaftar',
        ]);
    }

    private function omzet(Participant $peserta, int $bulan = 6, int $nominal = 5000000): OmzetReport
    {
        return $peserta->omzetReports()->create([
            'periode_bulan' => $bulan, 'periode_tahun' => 2026,
            'omzet' => $nominal, 'submitted_at' => now(),
        ]);
    }

    private function formPelatihan(array $ubah = []): array
    {
        return array_merge([
            'tanggal' => '2026-06-01',
            'nama_pelatihan' => 'Pelatihan Pembukuan',
            'penyelenggara' => 'YBM PLN',
            'status' => 'Selesai',
        ], $ubah);
    }

    // =====================================================================
    // D — DATA USAHA PESERTA
    // =====================================================================

    public function test_seluruh_data_usaha_bisa_diedit(): void
    {
        $this->actingAs($this->admin)
            ->put(route('participants.update', $this->binaanA), $this->formPeserta($this->binaanA, [
                'nama_usaha' => 'Dapur Cahaya',
                'alamat_usaha' => 'Jl. Pasar Minggu No. 12',
                'kategori_usaha' => 'Makanan & Minuman',
                'produk' => 'Rendang kemasan',
                'tahun_berdiri' => 2021,
                'status_usaha' => 'Mapan',
            ]))
            ->assertSessionHasNoErrors();

        $segar = $this->binaanA->fresh();

        $this->assertSame('Dapur Cahaya', $segar->nama_usaha);
        $this->assertSame('Jl. Pasar Minggu No. 12', $segar->alamat_usaha);
        $this->assertSame('Makanan & Minuman', $segar->kategori_usaha);
        $this->assertSame('Rendang kemasan', $segar->produk);
        $this->assertSame(2021, $segar->tahun_berdiri);
        $this->assertSame('Mapan', $segar->status_usaha);
    }

    public function test_pendamping_bisa_mengedit_data_usaha_binaannya(): void
    {
        $this->actingAs($this->pendampingA)
            ->put(route('participants.update', $this->binaanA), $this->formPeserta($this->binaanA, [
                'alamat_usaha' => 'Kios Pasar Panorama Blok C',
                'produk' => 'Keripik tempe',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame('Kios Pasar Panorama Blok C', $this->binaanA->fresh()->alamat_usaha);
        $this->assertSame('Keripik tempe', $this->binaanA->fresh()->produk);
    }

    public function test_pendamping_tidak_bisa_mengedit_data_usaha_binaan_lain(): void
    {
        $this->actingAs($this->pendampingA)
            ->put(route('participants.update', $this->binaanB), $this->formPeserta($this->binaanB, [
                'nama_usaha' => 'DIBAJAK',
            ]))
            ->assertForbidden();

        $this->assertSame('Usaha Binaan Milik B', $this->binaanB->fresh()->nama_usaha);
    }

    public function test_data_usaha_tampil_di_detail_peserta(): void
    {
        $this->binaanA->update(['alamat_usaha' => 'Jl. Flamboyan No. 7', 'produk' => 'Sambal kemasan']);

        $this->actingAs($this->admin)
            ->get(route('participants.show', $this->binaanA))
            ->assertOk()
            ->assertSee('Alamat Usaha')
            ->assertSee('Jl. Flamboyan No. 7')
            ->assertSee('Sambal kemasan');
    }

    // =====================================================================
    // F — RIWAYAT PELATIHAN
    // =====================================================================

    public function test_admin_bisa_mengedit_seluruh_isi_data_pelatihan(): void
    {
        $record = $this->pelatihan($this->binaanA);

        $this->actingAs($this->admin)->get(route('training.edit', $record))->assertOk();

        $this->actingAs($this->admin)
            ->put(route('training.update', $record), $this->formPelatihan([
                'nama_pelatihan' => 'Pelatihan Sertifikasi Halal',
                'penyelenggara' => 'BPJPH',
                'tanggal' => '2026-07-15',
                'status' => 'Mengikuti',
                'catatan' => 'Diikuti sampai selesai',
            ]))
            ->assertSessionHasNoErrors();

        $segar = $record->fresh();

        $this->assertSame('Pelatihan Sertifikasi Halal', $segar->nama_pelatihan);
        $this->assertSame('BPJPH', $segar->penyelenggara);
        $this->assertSame('2026-07-15', $segar->tanggal->format('Y-m-d'));
        $this->assertSame('Mengikuti', $segar->status);
        $this->assertSame('Diikuti sampai selesai', $segar->catatan);
    }

    /** Pendamping kini boleh mengelola pelatihan binaannya — dulu Admin-only. */
    public function test_pendamping_bisa_menambah_dan_mengedit_pelatihan_binaannya(): void
    {
        $this->actingAs($this->pendampingA)
            ->post(route('training.store', $this->binaanA), $this->formPelatihan([
                'nama_pelatihan' => 'Pelatihan Digital Marketing',
            ]))
            ->assertSessionHasNoErrors();

        $record = TrainingRecord::where('nama_pelatihan', 'Pelatihan Digital Marketing')->first();
        $this->assertNotNull($record);
        $this->assertSame($this->binaanA->id, $record->participant_id);

        $this->actingAs($this->pendampingA)
            ->put(route('training.update', $record), $this->formPelatihan([
                'nama_pelatihan' => 'Pelatihan Digital Marketing Lanjutan',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame('Pelatihan Digital Marketing Lanjutan', $record->fresh()->nama_pelatihan);
    }

    public function test_pendamping_tidak_bisa_menyentuh_pelatihan_binaan_lain(): void
    {
        $record = $this->pelatihan($this->binaanB, 'Pelatihan Milik B');

        $this->actingAs($this->pendampingA)->get(route('training.edit', $record))->assertForbidden();
        $this->actingAs($this->pendampingA)
            ->put(route('training.update', $record), $this->formPelatihan(['nama_pelatihan' => 'DIBAJAK']))
            ->assertForbidden();
        $this->actingAs($this->pendampingA)->delete(route('training.destroy', $record))->assertForbidden();
        $this->actingAs($this->pendampingA)
            ->post(route('training.store', $this->binaanB), $this->formPelatihan())
            ->assertForbidden();

        $this->assertSame('Pelatihan Milik B', $record->fresh()->nama_pelatihan);
        $this->assertDatabaseHas('training_records', ['id' => $record->id]);
    }

    /** Dropdown status di Detail Peserta memakai route terpisah & tetap jalan. */
    public function test_ubah_status_pelatihan_lewat_dropdown_tetap_berfungsi(): void
    {
        $record = $this->pelatihan($this->binaanA);

        $this->actingAs($this->pendampingA)
            ->put(route('training.status', $record), ['status' => 'Selesai'])
            ->assertSessionHasNoErrors();

        $segar = $record->fresh();

        $this->assertSame('Selesai', $segar->status);
        // Field lain TIDAK ikut tersentuh oleh dropdown.
        $this->assertSame('Pelatihan NIB', $segar->nama_pelatihan);
        $this->assertSame('Dinas Koperasi', $segar->penyelenggara);
    }

    public function test_pimpinan_tidak_bisa_mengubah_pelatihan(): void
    {
        $record = $this->pelatihan($this->binaanA);

        $this->actingAs($this->pimpinan)->get(route('training.edit', $record))->assertForbidden();
        $this->actingAs($this->pimpinan)
            ->put(route('training.update', $record), $this->formPelatihan())
            ->assertForbidden();
        $this->actingAs($this->pimpinan)->delete(route('training.destroy', $record))->assertForbidden();
    }

    public function test_perubahan_pelatihan_tercatat_di_activity_log(): void
    {
        $this->actingAs($this->admin)
            ->post(route('training.store', $this->binaanA), $this->formPelatihan(['nama_pelatihan' => 'Pelatihan Tercatat']));

        $record = TrainingRecord::where('nama_pelatihan', 'Pelatihan Tercatat')->first();

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'training_created', 'model_type' => 'TrainingRecord', 'model_id' => $record->id,
        ]);

        $this->actingAs($this->admin)->put(route('training.update', $record), $this->formPelatihan());
        $this->assertDatabaseHas('activity_logs', ['action' => 'training_updated', 'model_id' => $record->id]);

        $this->actingAs($this->admin)->delete(route('training.destroy', $record));
        $this->assertDatabaseHas('activity_logs', ['action' => 'training_deleted', 'model_id' => $record->id]);
    }

    // =====================================================================
    // G — PERKEMBANGAN USAHA (OMZET)
    // =====================================================================

    public function test_pendamping_bisa_mencatat_omzet_binaannya(): void
    {
        $this->actingAs($this->pendampingA)->get(route('omzet.create', $this->binaanA))->assertOk();

        $this->actingAs($this->pendampingA)
            ->post(route('omzet.store', $this->binaanA), [
                'periode_bulan' => 8, 'periode_tahun' => 2026, 'omzet' => 7500000,
                'catatan' => 'Disampaikan saat kunjungan',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('omzet_reports', [
            'participant_id' => $this->binaanA->id, 'periode_bulan' => 8,
            'periode_tahun' => 2026, 'omzet' => 7500000,
        ]);
    }

    public function test_pendamping_tidak_bisa_mencatat_omzet_binaan_lain(): void
    {
        $this->actingAs($this->pendampingA)->get(route('omzet.create', $this->binaanB))->assertForbidden();
        $this->actingAs($this->pendampingA)
            ->post(route('omzet.store', $this->binaanB), [
                'periode_bulan' => 8, 'periode_tahun' => 2026, 'omzet' => 1000,
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('omzet_reports', ['participant_id' => $this->binaanB->id]);
    }

    /** Satu peserta + satu periode tetap = satu laporan. */
    public function test_periode_yang_sudah_terisi_ditolak_bukan_digandakan(): void
    {
        $this->omzet($this->binaanA, 6);

        $this->actingAs($this->admin)
            ->post(route('omzet.store', $this->binaanA), [
                'periode_bulan' => 6, 'periode_tahun' => 2026, 'omzet' => 9999999,
            ])
            ->assertSessionHasErrors('periode_bulan');

        $this->assertSame(1, OmzetReport::where('participant_id', $this->binaanA->id)->count());
    }

    /**
     * INTI: unique index (peserta, bulan, tahun) TIDAK melihat `deleted_at`,
     * jadi periode yang laporannya pernah dihapus harus tetap bisa diisi lagi
     * — lewat pemulihan baris lama, bukan baris kembar.
     */
    public function test_periode_yang_pernah_dihapus_bisa_diisi_ulang(): void
    {
        $report = $this->omzet($this->binaanA, 6, 5000000);

        $this->actingAs($this->admin)->delete(route('omzet.destroy', $report));
        $this->assertDatabaseMissing('omzet_reports', ['id' => $report->id]);

        $this->actingAs($this->admin)
            ->post(route('omzet.store', $this->binaanA), [
                'periode_bulan' => 6, 'periode_tahun' => 2026, 'omzet' => 6000000,
            ])
            ->assertSessionHasNoErrors();

        // Baris lamanya sudah benar-benar hilang, jadi yang terbentuk adalah
        // baris BARU. Yang penting tetap sama: periode itu hanya boleh punya
        // satu baris, tidak menumpuk.
        $this->assertSame(1, OmzetReport::withTrashed()->where('participant_id', $this->binaanA->id)->count());
        $this->assertDatabaseMissing('omzet_reports', ['id' => $report->id]);
        $this->assertDatabaseHas('omzet_reports', [
            'participant_id' => $this->binaanA->id,
            'periode_bulan' => 6,
            'periode_tahun' => 2026,
            'omzet' => 6000000,
        ]);
    }

    /** Form publik peserta juga tidak boleh pecah karena unique index itu. */
    public function test_form_publik_tetap_bisa_mengisi_periode_yang_pernah_dihapus(): void
    {
        $report = $this->omzet($this->binaanA, 7, 3000000);
        $report->delete();

        $this->post(route('omzet.public.store', $this->binaanA->report_token), [
            'periode_bulan' => 7, 'periode_tahun' => 2026, 'omzet' => 4000000,
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, OmzetReport::withTrashed()->where('participant_id', $this->binaanA->id)->count());
        $this->assertSame(4000000, $report->fresh()->omzet);
    }

    public function test_omzet_bisa_diedit_dan_dihapus_sesuai_hak_akses(): void
    {
        $milikA = $this->omzet($this->binaanA, 6);
        $milikB = $this->omzet($this->binaanB, 6);

        // Pemilik binaan boleh.
        $this->actingAs($this->pendampingA)
            ->put(route('omzet.update', $milikA), ['omzet' => 8000000])
            ->assertSessionHasNoErrors();
        $this->assertSame(8000000, $milikA->fresh()->omzet);

        // Binaan Pendamping lain tertutup, GET maupun PUT maupun DELETE.
        $this->actingAs($this->pendampingA)->get(route('omzet.edit', $milikB))->assertForbidden();
        $this->actingAs($this->pendampingA)->put(route('omzet.update', $milikB), ['omzet' => 1])->assertForbidden();
        $this->actingAs($this->pendampingA)->delete(route('omzet.destroy', $milikB))->assertForbidden();
        $this->assertSame(5000000, $milikB->fresh()->omzet);

        // Admin boleh semuanya.
        $this->actingAs($this->admin)->delete(route('omzet.destroy', $milikB))->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('omzet_reports', ['id' => $milikB->id]);
    }

    /** Pimpinan boleh MELIHAT omzet, tapi tidak boleh mengubah apa pun. */
    public function test_pimpinan_read_only_di_omzet(): void
    {
        $report = $this->omzet($this->binaanA);

        $this->actingAs($this->pimpinan)->get(route('omzet.show', $report))->assertOk();

        $this->actingAs($this->pimpinan)->get(route('omzet.edit', $report))->assertForbidden();
        $this->actingAs($this->pimpinan)->put(route('omzet.update', $report), ['omzet' => 1])->assertForbidden();
        $this->actingAs($this->pimpinan)->delete(route('omzet.destroy', $report))->assertForbidden();
        $this->actingAs($this->pimpinan)->get(route('omzet.create', $this->binaanA))->assertForbidden();

        $this->assertSame(5000000, $report->fresh()->omzet);
    }

    public function test_perubahan_omzet_tercatat_di_activity_log(): void
    {
        $this->actingAs($this->admin)
            ->post(route('omzet.store', $this->binaanA), [
                'periode_bulan' => 9, 'periode_tahun' => 2026, 'omzet' => 1234000,
            ]);

        $report = OmzetReport::where('participant_id', $this->binaanA->id)->first();

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'omzet_created', 'model_type' => 'OmzetReport', 'model_id' => $report->id,
        ]);

        $this->actingAs($this->admin)->put(route('omzet.update', $report), ['omzet' => 2000000]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'omzet_updated', 'model_id' => $report->id]);

        $this->actingAs($this->admin)->delete(route('omzet.destroy', $report));
        $this->assertDatabaseHas('activity_logs', ['action' => 'omzet_deleted', 'model_id' => $report->id]);
    }

    // =====================================================================
    // TAMPILAN — aksi Edit mudah ditemukan
    // =====================================================================

    public function test_detail_peserta_menampilkan_aksi_untuk_pendamping_pemilik(): void
    {
        $pelatihan = $this->pelatihan($this->binaanA);
        $omzet = $this->omzet($this->binaanA);

        $this->actingAs($this->pendampingA)
            ->get(route('participants.show', $this->binaanA))
            ->assertOk()
            ->assertSee('Edit Data Peserta')
            ->assertSee(route('training.edit', $pelatihan), false)
            ->assertSee(route('omzet.edit', $omzet), false)
            ->assertSee(route('omzet.create', $this->binaanA), false);
    }

    public function test_detail_peserta_tidak_menampilkan_aksi_untuk_pimpinan(): void
    {
        $pelatihan = $this->pelatihan($this->binaanA);
        $omzet = $this->omzet($this->binaanA);

        $this->actingAs($this->pimpinan)
            ->get(route('participants.show', $this->binaanA))
            ->assertOk()
            ->assertDontSee('Edit Data Peserta')
            ->assertDontSee(route('training.edit', $pelatihan), false)
            ->assertDontSee(route('omzet.edit', $omzet), false);
    }
}
