<?php

namespace Tests\Feature;

use App\Models\LegalitasRecord;
use App\Models\Participant;
use App\Models\Pendamping;
use App\Models\PendampinganRecord;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\FollowUpService;
use App\Services\PerkembanganService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * FOLLOW UP (Hijau/Kuning/Merah) + MONITORING PERKEMBANGAN PESERTA.
 *
 * Dua hal yang dijaga kelas ini:
 *
 *   1. Status follow up DITURUNKAN dari catatan pendampingan, bukan disimpan
 *      sebagai kolom. Kalau disimpan, ia harus diperbarui setiap kali catatan
 *      ditambah/diubah/dihapus/dipulihkan — dan pasti akan basi. Test di sini
 *      memastikan status ikut berubah begitu datanya berubah.
 *
 *   2. Status perkembangan SELALU punya alasan yang bisa dibaca. Tidak ada
 *      skor tersembunyi; kalau seorang peserta ditandai "Perlu Pendampingan",
 *      sistem harus bisa menyebutkan kenapa.
 */
class FollowUpDanPerkembanganTest extends TestCase
{
    use RefreshDatabase;

    private Pendamping $profilA;
    private Pendamping $profilB;
    private User $pendampingA;
    private User $admin;
    private User $pimpinan;
    private User $timKoperasi;
    private Participant $binaanA;
    private Participant $binaanB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->profilA = Pendamping::create(['nama' => 'Rosa Linda', 'nomor_hp' => '0812', 'email' => 'a@fu.test', 'status' => 'Aktif']);
        $this->profilB = Pendamping::create(['nama' => 'Pendamping B', 'nomor_hp' => '0813', 'email' => 'b@fu.test', 'status' => 'Aktif']);

        $this->pendampingA = $this->user('pendamping', 'a@akun.test', $this->profilA->id);
        $this->admin = $this->user('admin', 'admin@fu.test');
        $this->pimpinan = $this->user('pimpinan', 'pimpinan@fu.test');
        $this->timKoperasi = $this->user(User::ROLE_TIM_KOPERASI, 'tk@fu.test');

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

    private function peserta(string $nama, ?int $pendampingId, string $status = 'Aktif'): Participant
    {
        return Participant::create([
            'nama_peserta' => $nama, 'nomor_hp' => '0812', 'alamat' => 'Jl. Uji',
            'nama_usaha' => 'Usaha ' . $nama, 'kategori_usaha' => 'Jasa',
            'tanggal_mulai_program' => '2026-01-10', 'status' => $status,
            'pendamping_id' => $pendampingId,
        ]);
    }

    private function catatan(Participant $p, string $tanggal, array $ubah = []): PendampinganRecord
    {
        return PendampinganRecord::create(array_merge([
            'participant_id' => $p->id, 'pendamping_id' => $p->pendamping_id,
            'tanggal' => $tanggal, 'jenis_kegiatan' => 'Konsultasi', 'materi' => 'Materi uji',
        ], $ubah));
    }

    private function followUp(): FollowUpService
    {
        return app(FollowUpService::class);
    }

    private function statusFollowUp(Participant $p): array
    {
        $terakhir = $p->pendampinganRecords()->orderByDesc('tanggal')->first();

        return $this->followUp()->untukPeserta($p, $terakhir);
    }

    // =====================================================================
    // STATUS HIJAU / KUNING / MERAH
    // =====================================================================

    public function test_belum_pernah_didampingi_berstatus_merah(): void
    {
        $hasil = $this->statusFollowUp($this->binaanA);

        $this->assertSame(FollowUpService::MERAH, $hasil['status']);
        $this->assertSame('Belum pernah mendapat pendampingan.', $hasil['alasan']);
        $this->assertNull($hasil['terakhir']);
    }

    public function test_baru_didampingi_berstatus_hijau(): void
    {
        $this->catatan($this->binaanA, now()->subDays(5)->toDateString());

        $hasil = $this->statusFollowUp($this->binaanA);

        $this->assertSame(FollowUpService::HIJAU, $hasil['status']);
        $this->assertSame(5, $hasil['hari']);
    }

    public function test_lewat_ambang_kuning_berstatus_kuning(): void
    {
        $this->catatan($this->binaanA, now()->subDays(35)->toDateString());

        $this->assertSame(FollowUpService::KUNING, $this->statusFollowUp($this->binaanA)['status']);
    }

    public function test_lewat_ambang_merah_berstatus_merah(): void
    {
        $this->catatan($this->binaanA, now()->subDays(60)->toDateString());

        $hasil = $this->statusFollowUp($this->binaanA);

        $this->assertSame(FollowUpService::MERAH, $hasil['status']);
        $this->assertStringContainsString('60 hari', $hasil['alasan']);
    }

    /** INTI: status ikut berubah begitu catatan baru masuk — tanpa kolom status. */
    public function test_status_berubah_otomatis_saat_aktivitas_baru_dicatat(): void
    {
        $this->catatan($this->binaanA, now()->subDays(60)->toDateString());
        $this->assertSame(FollowUpService::MERAH, $this->statusFollowUp($this->binaanA->fresh())['status']);

        $this->actingAs($this->pendampingA)
            ->post(route('pendampingan.store', $this->binaanA), [
                'tanggal' => now()->toDateString(),
                'jenis_kegiatan' => 'Kunjungan Usaha',
                'materi' => 'Kunjungan tindak lanjut',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(FollowUpService::HIJAU, $this->statusFollowUp($this->binaanA->fresh())['status']);
    }

    /** Dan ikut berubah lagi kalau catatan itu dihapus. */
    public function test_status_kembali_merah_saat_catatan_dihapus(): void
    {
        $catatan = $this->catatan($this->binaanA, now()->toDateString());
        $this->assertSame(FollowUpService::HIJAU, $this->statusFollowUp($this->binaanA->fresh())['status']);

        $this->actingAs($this->pendampingA)->delete(route('pendampingan.destroy', $catatan));

        $this->assertSame(FollowUpService::MERAH, $this->statusFollowUp($this->binaanA->fresh())['status']);
    }

    // =====================================================================
    // AMBANG BATAS DAPAT DIUBAH ADMIN
    // =====================================================================

    public function test_ambang_batas_memakai_nilai_bawaan_kalau_belum_diatur(): void
    {
        $ambang = $this->followUp()->ambang();

        $this->assertSame(config('cahayaos.followup.kuning_hari'), $ambang['kuning']);
        $this->assertSame(config('cahayaos.followup.merah_hari'), $ambang['merah']);
    }

    public function test_ambang_batas_bisa_diubah_lewat_pengaturan(): void
    {
        SystemSetting::current()->update([
            'followup_kuning_hari' => 10,
            'followup_merah_hari' => 20,
        ]);

        $ambang = $this->followUp()->ambang();
        $this->assertSame(10, $ambang['kuning']);
        $this->assertSame(20, $ambang['merah']);

        // Dan status ikut aturan baru: 15 hari kini sudah Kuning.
        $this->catatan($this->binaanA, now()->subDays(15)->toDateString());
        $this->assertSame(FollowUpService::KUNING, $this->statusFollowUp($this->binaanA)['status']);
    }

    /** Ambang terbalik dirapikan, tidak membuat status kacau tanpa pesan. */
    public function test_ambang_terbalik_dirapikan(): void
    {
        SystemSetting::current()->update([
            'followup_kuning_hari' => 60,
            'followup_merah_hari' => 30,
        ]);

        $ambang = $this->followUp()->ambang();

        $this->assertGreaterThan($ambang['kuning'], $ambang['merah']);
    }

    // =====================================================================
    // HALAMAN FOLLOW UP
    // =====================================================================

    public function test_pendamping_hanya_melihat_follow_up_binaannya(): void
    {
        $this->catatan($this->binaanA, now()->subDays(60)->toDateString());
        $this->catatan($this->binaanB, now()->subDays(60)->toDateString());

        $this->actingAs($this->pendampingA)
            ->get(route('follow-up.index'))
            ->assertOk()
            ->assertSee('Binaan Milik A')
            ->assertDontSee('Binaan Milik B');
    }

    /** INTI: filter ?pendamping_id dari URL DIABAIKAN untuk Pendamping. */
    public function test_pendamping_tidak_bisa_melihat_follow_up_lain_lewat_url(): void
    {
        $this->catatan($this->binaanB, now()->subDays(60)->toDateString());

        $this->actingAs($this->pendampingA)
            ->get(route('follow-up.index', ['pendamping_id' => $this->profilB->id]))
            ->assertOk()
            ->assertDontSee('Binaan Milik B');
    }

    public function test_admin_melihat_follow_up_seluruh_pendamping(): void
    {
        $this->catatan($this->binaanA, now()->subDays(60)->toDateString());
        $this->catatan($this->binaanB, now()->subDays(60)->toDateString());

        $this->actingAs($this->admin)
            ->get(route('follow-up.index'))
            ->assertOk()
            ->assertSee('Binaan Milik A')
            ->assertSee('Binaan Milik B');
    }

    public function test_admin_bisa_memfilter_per_pendamping(): void
    {
        $this->catatan($this->binaanA, now()->subDays(60)->toDateString());
        $this->catatan($this->binaanB, now()->subDays(60)->toDateString());

        $this->actingAs($this->admin)
            ->get(route('follow-up.index', ['pendamping_id' => $this->profilA->id]))
            ->assertOk()
            ->assertSee('Binaan Milik A')
            ->assertDontSee('Binaan Milik B');
    }

    public function test_role_lain_ditolak_di_halaman_follow_up(): void
    {
        foreach ([$this->pimpinan, $this->timKoperasi] as $user) {
            $this->actingAs($user)->get(route('follow-up.index'))->assertForbidden();
        }

        $this->actingAs($this->user('pendamping', 'lepas@fu.test'))
            ->get(route('follow-up.index'))->assertForbidden();
    }

    /** Empat keranjang saling lepas: satu peserta hanya muncul sekali. */
    public function test_kelompok_follow_up_saling_lepas(): void
    {
        $this->catatan($this->binaanA, now()->subDays(60)->toDateString(), [
            'tanggal_follow_up' => now()->subDays(3)->toDateString(),
        ]);
        $lain = $this->peserta('Binaan Jadwal', $this->profilA->id);
        $this->catatan($lain, now()->subDays(2)->toDateString(), [
            'tanggal_follow_up' => now()->toDateString(),
        ]);
        $this->peserta('Binaan Baru Sekali', $this->profilA->id);

        $peserta = Participant::where('pendamping_id', $this->profilA->id)->get();
        $kelompok = $this->followUp()->kelompokkan($peserta);

        $semua = collect($kelompok)->flatMap(fn ($k) => $k->pluck('peserta.id'));

        $this->assertSame($semua->count(), $semua->unique()->count(), 'Satu peserta muncul di lebih dari satu kelompok.');
        $this->assertSame(1, $kelompok['terlambat']->count());
        $this->assertSame(1, $kelompok['hari_ini']->count());
        $this->assertSame(1, $kelompok['belum_pernah']->count());
    }

    /** Binaan Alumni/Nonaktif tidak perlu ditindaklanjuti lagi. */
    public function test_binaan_tidak_berjalan_tidak_muncul_di_follow_up(): void
    {
        $this->binaanA->update(['status' => 'Alumni']);

        $this->actingAs($this->pendampingA)
            ->get(route('follow-up.index'))
            ->assertOk()
            ->assertDontSee('Binaan Milik A');
    }

    // =====================================================================
    // MONITORING PERKEMBANGAN
    // =====================================================================

    private function rapor(Participant $p): array
    {
        $p->load(['omzetReports', 'pendampinganRecords', 'trainingRecords', 'legalitasRecords', 'koperasiMember']);

        return app(PerkembanganService::class)->rapor($p);
    }

    public function test_status_perkembangan_selalu_disertai_alasan(): void
    {
        $rapor = $this->rapor($this->binaanA);

        $this->assertNotEmpty($rapor['alasan'], 'Status tanpa alasan tidak boleh terjadi.');
        $this->assertIsString($rapor['alasan'][0]);
    }

    public function test_peserta_tanpa_jejak_berstatus_belum_berkembang(): void
    {
        $rapor = $this->rapor($this->binaanA);

        $this->assertSame(PerkembanganService::BELUM_BERKEMBANG, $rapor['status']);
        $this->assertContains('Belum ada catatan pendampingan.', $rapor['alasan']);
    }

    public function test_kelengkapan_data_menyebut_yang_kurang(): void
    {
        $tanpaPendamping = $this->peserta('Tanpa Pendamping', null);

        $rapor = $this->rapor($tanpaPendamping);

        $this->assertFalse($rapor['kelengkapan']['lengkap']);
        $this->assertContains('Pendamping', $rapor['kelengkapan']['kurang']);
        $this->assertStringContainsString('Pendamping', implode(' ', $rapor['alasan']));
    }

    public function test_pertumbuhan_omzet_dihitung_dari_dua_laporan_berurutan(): void
    {
        $this->binaanA->omzetReports()->create(['periode_bulan' => 7, 'periode_tahun' => 2026, 'omzet' => 4000000]);
        $this->binaanA->omzetReports()->create(['periode_bulan' => 8, 'periode_tahun' => 2026, 'omzet' => 5000000]);

        $rapor = $this->rapor($this->binaanA);

        $this->assertSame(25.0, $rapor['usaha']['pertumbuhan_persen']);
        $this->assertSame(5000000, $rapor['usaha']['omzet_terbaru']);
    }

    public function test_omzet_turun_membuat_status_perlu_pendampingan(): void
    {
        $this->catatan($this->binaanA, now()->toDateString());
        $this->binaanA->omzetReports()->create(['periode_bulan' => 7, 'periode_tahun' => 2026, 'omzet' => 5000000]);
        $this->binaanA->omzetReports()->create(['periode_bulan' => 8, 'periode_tahun' => 2026, 'omzet' => 3000000]);

        $rapor = $this->rapor($this->binaanA);

        $this->assertSame(PerkembanganService::PERLU_PENDAMPINGAN, $rapor['status']);
        $this->assertStringContainsString('Omzet turun 40%', implode(' ', $rapor['alasan']));
    }

    public function test_omzet_naik_dan_pendampingan_berjalan_berstatus_berkembang(): void
    {
        $this->catatan($this->binaanA, now()->toDateString());
        $this->binaanA->omzetReports()->create([
            'periode_bulan' => now()->subMonth()->month, 'periode_tahun' => now()->subMonth()->year, 'omzet' => 4000000,
        ]);
        $this->binaanA->omzetReports()->create([
            'periode_bulan' => now()->month, 'periode_tahun' => now()->year, 'omzet' => 6000000,
        ]);

        $rapor = $this->rapor($this->binaanA->fresh());

        $this->assertSame(PerkembanganService::BERKEMBANG, $rapor['status']);
        $this->assertStringContainsString('Omzet naik', implode(' ', $rapor['alasan']));
    }

    /**
     * INTI: legalitas dihitung dari dokumen yang MEMANG didaftarkan.
     * Tidak semua UMKM butuh BPOM/Halal — menilai dengan daftar wajib bikinan
     * sistem akan membuat warung kelontong selamanya terlihat bermasalah.
     */
    public function test_legalitas_tidak_menghukum_usaha_yang_tidak_membutuhkannya(): void
    {
        $rapor = $this->rapor($this->binaanA);

        $this->assertFalse($rapor['legalitas']['relevan']);
        $this->assertSame(0, $rapor['legalitas']['terdaftar']);
        // Tidak ada alasan yang menyalahkan ketiadaan legalitas.
        $this->assertStringNotContainsString('legalitas', strtolower(implode(' ', $rapor['alasan'])));
    }

    public function test_legalitas_terhitung_saat_dokumen_didaftarkan(): void
    {
        LegalitasRecord::create([
            'participant_id' => $this->binaanA->id, 'jenis_dokumen' => 'NIB', 'status' => 'Sudah Ada',
        ]);
        LegalitasRecord::create([
            'participant_id' => $this->binaanA->id, 'jenis_dokumen' => 'PIRT', 'status' => 'Belum Ada',
        ]);

        $rapor = $this->rapor($this->binaanA);

        $this->assertTrue($rapor['legalitas']['relevan']);
        $this->assertSame(2, $rapor['legalitas']['terdaftar']);
        $this->assertSame(1, $rapor['legalitas']['sudah_ada']);
    }

    /** Status koperasi TIDAK mempengaruhi status perkembangan Cahaya Preneur. */
    public function test_status_koperasi_tidak_mempengaruhi_status_perkembangan(): void
    {
        $this->catatan($this->binaanA, now()->toDateString());
        $this->binaanA->omzetReports()->create([
            'periode_bulan' => now()->month, 'periode_tahun' => now()->year, 'omzet' => 5000000,
        ]);

        $sebelum = $this->rapor($this->binaanA->fresh())['status'];

        \App\Models\KoperasiMember::create([
            'nama' => 'Binaan Milik A', 'nomor_hp' => '0812',
            'tanggal_bergabung' => now()->toDateString(), 'status' => 'Aktif',
            'participant_id' => $this->binaanA->id,
        ]);

        $rapor = $this->rapor($this->binaanA->fresh());

        $this->assertSame($sebelum, $rapor['status'], 'Keanggotaan koperasi tidak boleh mengubah status Cahaya Preneur.');
        $this->assertTrue($rapor['koperasi']['anggota']);
    }

    /** Monitoring TIDAK mengubah data peserta — ia hanya membaca. */
    public function test_monitoring_tidak_mengubah_data_peserta(): void
    {
        $sebelum = $this->binaanA->toArray();

        $this->rapor($this->binaanA);

        $this->assertSame($sebelum['status'], $this->binaanA->fresh()->status);
        $this->assertSame($sebelum['updated_at'], $this->binaanA->fresh()->toArray()['updated_at']);

        // Dan tidak ada kolom status perkembangan yang disimpan di database.
        $this->assertFalse(Schema::hasColumn('participants', 'status_perkembangan'));
        $this->assertFalse(Schema::hasColumn('participants', 'follow_up_status'));
    }

    public function test_rapor_tampil_di_detail_peserta_beserta_alasannya(): void
    {
        $this->actingAs($this->admin)
            ->get(route('participants.show', $this->binaanA))
            ->assertOk()
            ->assertSee('Status Perkembangan')
            ->assertSee('Alasan status ini:')
            ->assertSee('Belum ada catatan pendampingan.');
    }
}
