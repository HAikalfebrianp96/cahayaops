<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\LegalitasRecord;
use App\Models\Participant;
use App\Models\Pendamping;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PERUBAHAN DOKUMEN LEGALITAS HARUS MENINGGALKAN JEJAK.
 *
 * =====================================================================
 * KENAPA JUSTRU YANG INI
 * =====================================================================
 *
 * Tujuh belas model sudah dicatat jejaknya, tapi legalitas tidak — di
 * production terhitung 84 dokumen tersimpan dengan NOL baris jejak audit.
 *
 * Yang membuatnya lebih dari sekadar kelalaian pembukuan: KPI legalitas
 * menghitung dokumen berstatus "Sudah Ada". Artinya angka yang menilai
 * seorang pendamping bergantung pada kolom yang bisa ia ubah sendiri.
 *
 * Selama perubahannya tidak tercatat, tidak ada cara membedakan dokumen
 * yang memang terbit dari status yang sekadar diubah — dan tidak ada yang
 * bisa menjelaskannya kalau suatu saat ditanya pemberi dana.
 *
 * Yang dijaga di sini jejaknya, bukan perhitungannya. Tidak ada satu pun
 * angka yang berubah karena pencatatan ini.
 */
class JejakLegalitasTest extends TestCase
{
    use RefreshDatabase;

    private User $pendamping;
    private Participant $peserta;

    protected function setUp(): void
    {
        parent::setUp();

        $profil = Pendamping::create([
            'nama' => 'Rosa', 'nomor_hp' => '081200000001',
            'email' => 'rosa@jejak.test', 'status' => 'Aktif',
        ]);

        $this->pendamping = User::create([
            'name' => 'Rosa', 'email' => 'rosa.akun@jejak.test',
            'password' => 'password123', 'role' => 'pendamping',
            'pendamping_id' => $profil->id, 'is_active' => true,
        ]);

        $this->peserta = Participant::create([
            'nama_peserta' => 'Peserta Uji', 'nomor_hp' => '081200000002',
            'alamat' => 'Jl. Uji', 'nama_usaha' => 'Usaha Uji',
            'kategori_usaha' => 'Jasa',
            'tanggal_mulai_program' => now()->subMonths(6)->toDateString(),
            'status' => 'Aktif', 'pendamping_id' => $profil->id,
        ]);
    }

    private function jejakLegalitas()
    {
        return ActivityLog::where('model_type', 'LegalitasRecord');
    }

    public function test_dokumen_legalitas_baru_meninggalkan_jejak(): void
    {
        $this->actingAs($this->pendamping)
            ->post(route('legalitas.store', $this->peserta), [
                'jenis_dokumen' => 'NIB',
                'status' => LegalitasRecord::STATUS_BELUM_ADA,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $this->jejakLegalitas()->where('action', 'legalitas_created')->count());
    }

    /**
     * Inti persoalannya: status yang menaikkan KPI harus tercatat.
     */
    public function test_perubahan_status_menjadi_sudah_ada_tercatat(): void
    {
        $dokumen = LegalitasRecord::create([
            'participant_id' => $this->peserta->id,
            'jenis_dokumen' => 'NIB',
            'status' => LegalitasRecord::STATUS_PROSES,
        ]);

        $sebelum = $this->jejakLegalitas()->count();

        $this->actingAs($this->pendamping)
            ->put(route('legalitas.update', $dokumen), [
                'jenis_dokumen' => 'NIB',
                'status' => LegalitasRecord::STATUS_SUDAH_ADA,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(
            LegalitasRecord::STATUS_SUDAH_ADA,
            $dokumen->fresh()->status,
            'Perubahan statusnya sendiri harus tetap berhasil.'
        );

        $jejak = $this->jejakLegalitas()->where('action', 'legalitas_updated')->latest('id')->first();

        $this->assertNotNull($jejak, 'Perubahan status legalitas tidak meninggalkan jejak.');
        $this->assertGreaterThan($sebelum, $this->jejakLegalitas()->count());

        // Siapa yang mengubahnya harus ikut tercatat — jejak tanpa pelaku
        // tidak menjawab pertanyaan yang sebenarnya diajukan orang.
        $this->assertSame($this->pendamping->id, $jejak->user_id);
    }

    public function test_penghapusan_dokumen_legalitas_tercatat(): void
    {
        $dokumen = LegalitasRecord::create([
            'participant_id' => $this->peserta->id,
            'jenis_dokumen' => 'NIB',
            'status' => LegalitasRecord::STATUS_SUDAH_ADA,
        ]);

        $this->actingAs($this->pendamping)
            ->delete(route('legalitas.destroy', $dokumen))
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $this->jejakLegalitas()->where('action', 'legalitas_deleted')->count());
    }

    /**
     * Jejaknya harus bisa DIBACA, bukan sekadar ada barisnya.
     *
     * Baris log yang tidak menyebut dokumen apa dan milik siapa tidak
     * menolong siapa pun yang sedang menelusuri.
     */
    public function test_jejaknya_menyebut_dokumen_yang_dimaksud(): void
    {
        $dokumen = LegalitasRecord::create([
            'participant_id' => $this->peserta->id,
            'jenis_dokumen' => 'NIB',
            'status' => LegalitasRecord::STATUS_PROSES,
        ]);

        $jejak = $this->jejakLegalitas()->latest('id')->firstOrFail();

        $this->assertSame($dokumen->id, $jejak->model_id);
        $this->assertNotEmpty($jejak->description);
    }
}
