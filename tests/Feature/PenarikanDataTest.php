<?php

namespace Tests\Feature;

use App\Models\KoperasiMember;
use App\Models\MemberSaving;
use App\Models\Participant;
use App\Models\Pendamping;
use App\Models\PendampinganRecord;
use App\Models\PendampingTarget;
use App\Models\ShuPeriod;
use App\Models\User;
use App\Services\FollowUpService;
use App\Services\KpiService;
use App\Services\ShuService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * DATA YANG DITARIK HARUS BERHENTI DIHITUNG — DAN KEMBALI KALAU DIPULIHKAN.
 *
 * =====================================================================
 * SISI YANG BELUM PERNAH DIUJI SEBAGAI TRANSISI
 * =====================================================================
 *
 * Menghapus catatan yang salah dan membatalkan simpanan sama lazimnya
 * dengan menambahkannya. Risikonya jelas dan sunyi: data yang sudah ditarik
 * tetap ikut dihitung, sehingga angka di layar tidak lagi cocok dengan
 * daftar yang bisa dilihat orang.
 *
 * Arah sebaliknya sama pentingnya: catatan yang dipulihkan harus kembali
 * utuh. Kalau tidak, pekerjaan seseorang hilang hanya karena sempat salah
 * hapus.
 */
class PenarikanDataTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $akunPendamping;
    private Pendamping $profil;
    private Participant $peserta;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-06-20 09:00', 'Asia/Jakarta'));

        $this->profil = Pendamping::create([
            'nama' => 'Rosa', 'nomor_hp' => '081200000001',
            'email' => 'rosa@tarik.test', 'status' => 'Aktif',
        ]);

        $this->admin = User::create([
            'name' => 'Admin', 'email' => 'admin@tarik.test',
            'password' => 'password123', 'role' => 'admin', 'is_active' => true,
        ]);

        $this->akunPendamping = User::create([
            'name' => 'Rosa', 'email' => 'rosa.akun@tarik.test',
            'password' => 'password123', 'role' => 'pendamping',
            'pendamping_id' => $this->profil->id, 'is_active' => true,
        ]);

        $this->peserta = Participant::create([
            'nama_peserta' => 'Peserta Uji', 'nomor_hp' => '081200000002',
            'alamat' => 'Jl. Uji', 'nama_usaha' => 'Usaha Uji',
            'kategori_usaha' => 'Jasa',
            'tanggal_mulai_program' => '2026-01-10',
            'status' => 'Aktif', 'pendamping_id' => $this->profil->id,
        ]);

        PendampingTarget::create([
            'pendamping_id' => $this->profil->id,
            'periode' => PendampingTarget::PERIODE_BULANAN,
            'tahun' => 2026, 'bulan' => 6,
            'target_pendampingan' => 10,
            'target_kunjungan' => 10,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function rapor(): array
    {
        return app(KpiService::class)->untukSatu(
            $this->profil,
            Carbon::parse('2026-06-01')->startOfDay(),
            Carbon::parse('2026-06-30')->endOfDay(),
        );
    }

    private function catat(string $tanggal): PendampinganRecord
    {
        return PendampinganRecord::create([
            'participant_id' => $this->peserta->id,
            'pendamping_id' => $this->profil->id,
            'tanggal' => $tanggal,
            'jenis_kegiatan' => PendampinganRecord::KUNJUNGAN_USAHA,
            'materi' => 'Materi uji',
        ]);
    }

    // =================================================================
    // CATATAN PENDAMPINGAN
    // =================================================================

    public function test_catatan_yang_dihapus_berhenti_dihitung_di_kpi(): void
    {
        $catatan = $this->catat('2026-06-15');

        $this->assertSame(1, $this->rapor()['indikator']['target_pendampingan']['aktual']);
        $this->assertSame(1, $this->rapor()['indikator']['target_kunjungan']['aktual']);

        $this->actingAs($this->akunPendamping)
            ->delete(route('pendampingan.destroy', $catatan))
            ->assertSessionHasNoErrors();

        $this->assertSame(
            0,
            $this->rapor()['indikator']['target_pendampingan']['aktual'],
            'Catatan yang sudah dihapus masih ikut dihitung di KPI.'
        );
        $this->assertSame(0, $this->rapor()['indikator']['target_kunjungan']['aktual']);
    }

    /**
     * Sesudah catatan terbaru dihapus, "aktivitas terakhir" harus mundur ke
     * catatan sebelumnya — bukan tetap menunjuk yang sudah tidak ada.
     */
    public function test_aktivitas_terakhir_mundur_setelah_catatan_terbaru_dihapus(): void
    {
        $this->catat('2026-03-10');
        $terbaru = $this->catat('2026-06-15');

        $sebelum = app(FollowUpService::class)
            ->untukBanyak(collect([$this->peserta]))[$this->peserta->id];

        $this->assertSame('2026-06-15', $sebelum['terakhir']->toDateString());

        $this->actingAs($this->akunPendamping)
            ->delete(route('pendampingan.destroy', $terbaru))
            ->assertSessionHasNoErrors();

        $sesudah = app(FollowUpService::class)
            ->untukBanyak(collect([$this->peserta->fresh()]))[$this->peserta->id];

        $this->assertSame(
            '2026-03-10',
            $sesudah['terakhir']->toDateString(),
            'Aktivitas terakhir masih menunjuk catatan yang sudah dihapus.'
        );
    }

    // =================================================================
    // SIMPANAN ANGGOTA
    // =================================================================

    private function anggotaBersimpanan(string $nama, int $nominal): array
    {
        $anggota = KoperasiMember::create([
            'nama' => $nama, 'nomor_hp' => '0812' . random_int(10000000, 99999999),
            'tanggal_bergabung' => '2025-01-10', 'status' => 'Aktif',
        ]);

        $simpanan = MemberSaving::create([
            'koperasi_member_id' => $anggota->id,
            'jenis_simpanan' => 'Simpanan Pokok',
            'nominal' => $nominal,
            'tanggal' => '2026-02-01',
        ]);

        return [$anggota, $simpanan];
    }

    private function dasarSimpanan(): int
    {
        return (int) array_sum(array_column(
            app(ShuService::class)->dasarPembagian(2026),
            'simpanan'
        ));
    }

    public function test_simpanan_yang_dibatalkan_keluar_dari_dasar_pembagian(): void
    {
        [, $simpanan] = $this->anggotaBersimpanan('Ani', 1000000);

        $this->assertSame(1000000, $this->dasarSimpanan());

        $simpanan->update(['status' => MemberSaving::STATUS_DIBATALKAN]);

        $this->assertSame(
            0,
            $this->dasarSimpanan(),
            'Simpanan yang dibatalkan masih ikut menjadi dasar pembagian SHU.'
        );
    }

    // =================================================================
    // PERINGATAN HARUS MENYEBUT SEBAB YANG BENAR
    // =================================================================

    /**
     * Dasar simpanan nol punya DUA sebab yang sangat berbeda, dan keduanya
     * dulu memunculkan kalimat yang sama.
     *
     * Keadaan kedua terjadi sungguhan pada data berjalan: simpanan pokok &
     * wajib tercatat Rp160.000, seluruhnya milik anggota tidak aktif. Kalimat
     * "belum ada simpanan tercatat" menyesatkan ke arah yang salah —
     * pengurus akan mencari-cari untuk mencatat simpanan yang sudah ada.
     */
    public function test_peringatan_membedakan_belum_dicatat_dari_milik_anggota_tidak_aktif(): void
    {
        $periode = ShuPeriod::create([
            'tahun' => 2026,
            'tanggal_penetapan' => '2026-06-20',
            'sumber' => 'manual',
            'total_shu' => 10000000,
            'status' => ShuPeriod::STATUS_DRAFT,
            'komponen' => ShuPeriod::KOMPONEN_USULAN,
        ]);

        $layanan = app(ShuService::class);

        // --- Sebab pertama: memang belum ada ---
        $this->assertStringContainsString(
            'Belum ada simpanan pokok/wajib tercatat',
            implode(' ', $layanan->peringatan($periode))
        );

        // --- Sebab kedua: ada, tapi pemiliknya bukan anggota aktif lagi ---
        [$anggota] = $this->anggotaBersimpanan('Zul', 160000);
        $anggota->update(['status' => 'Tidak Aktif']);

        $peringatan = implode(' ', $layanan->peringatan($periode));

        $this->assertStringContainsString('anggota yang statusnya tidak lagi aktif', $peringatan);
        $this->assertStringContainsString('Rp160.000', $peringatan);
        $this->assertStringNotContainsString('Belum ada simpanan pokok/wajib tercatat', $peringatan);
    }
}
