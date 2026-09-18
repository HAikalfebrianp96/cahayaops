<?php

namespace Tests\Feature;

use App\Models\Participant;
use App\Models\Pendamping;
use App\Models\PendampingTarget;
use App\Models\User;
use App\Services\KpiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PENDAMPING YANG TARGETNYA BELUM PERNAH DIATUR.
 *
 * =====================================================================
 * KEADAAN YANG DITEMUKAN
 * =====================================================================
 *
 * Tabel pendamping_targets KOSONG — tidak seorang pun pernah diberi
 * target. Meski begitu, halaman KPI Saya menampilkan kepada satu-satunya
 * pendamping di sistem ini:
 *
 *     Rosa Linda · Target bawaan sistem · 25% · RENDAH (merah)
 *     3 indikator belum tercapai
 *
 * Angkanya sendiri benar: ia memang belum mencatat pendampingan bulan
 * ini. Yang keliru adalah VONISNYA. Target bawaan adalah angka acuan
 * sistem, bukan kesepakatan dengan siapa pun — dan orang yang membuka
 * halaman itu membaca dirinya gagal atas ukuran yang tidak pernah
 * disampaikan kepadanya.
 *
 * =====================================================================
 * YANG DIUBAH DAN YANG TIDAK
 * =====================================================================
 *
 * Angkanya TETAP dihitung dan ditampilkan — berguna sebagai acuan, dan
 * Admin tetap perlu melihat siapa yang belum bergerak.
 *
 * Yang dilepas hanya penilaiannya: status menjadi "Target Belum Diatur"
 * berwarna netral, indikatornya tidak dihitung gagal, dan rata-rata KPI
 * tidak lagi mencampur angka yang tidak punya pembanding sah.
 *
 * Kontrol pembanding di bawah memastikan KPI TIDAK dimatikan: pendamping
 * yang benar-benar punya target tetap mendapat penilaian sungguhan.
 */
class KpiTanpaTargetTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Pendamping $profil;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin Uji',
            'email' => 'admin@kpi.test',
            'password' => bcrypt('rahasia123'),
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
        ]);

        $this->profil = Pendamping::create([
            'nama' => 'Rosa Linda', 'nomor_hp' => '081200000001', 'status' => 'Aktif',
        ]);

        // Satu binaan supaya rapornya punya isi.
        Participant::create([
            'nama_peserta' => 'Bu Ani',
            'nomor_hp' => '081234567890',
            'nama_usaha' => 'Keripik Bawang',
            'kategori_usaha' => 'Kuliner',
            'tanggal_mulai_program' => now()->subYear()->toDateString(),
            'status' => 'Aktif',
            'pendamping_id' => $this->profil->id,
        ]);
    }

    private function halaman()
    {
        return $this->actingAs($this->admin)->get(route('kpi-pendamping.index'))->assertOk();
    }

    private function barisRosa(): array
    {
        return $this->halaman()->viewData('rapor')->firstWhere('pendamping.id', $this->profil->id)
            ?? $this->halaman()->viewData('rapor')->first();
    }

    private function beriTarget(int $pendampingan = 10): void
    {
        PendampingTarget::create([
            'pendamping_id' => $this->profil->id,
            'periode' => PendampingTarget::PERIODE_BULANAN,
            'tahun' => now()->year,
            'bulan' => now()->month,
            'target_jumlah_binaan' => 1,
            'target_pendampingan' => $pendampingan,
            'target_kunjungan' => $pendampingan,
            'target_follow_up' => $pendampingan,
        ]);
    }

    // =================================================================
    // TANPA TARGET: BUKAN VONIS
    // =================================================================

    public function test_tanpa_target_tidak_dinyatakan_rendah(): void
    {
        $baris = $this->barisRosa();

        $this->assertTrue($baris['pakai_default'], 'Prasyarat: belum ada target individual.');

        $this->assertSame(KpiService::TANPA_TARGET, $baris['status']);
        $this->assertNotSame(KpiService::RENDAH, $baris['status']);
    }

    /** Warnanya netral, bukan merah bahaya. */
    public function test_tanpa_target_memakai_warna_netral(): void
    {
        $this->assertStringNotContainsString('danger', $this->barisRosa()['warna']);
    }

    /** Indikator tanpa target tidak dianggap gagal. */
    public function test_indikator_tanpa_target_tidak_dihitung_gagal(): void
    {
        $this->assertSame(0, $this->barisRosa()['belum_tercapai']);
    }

    public function test_ringkasan_tidak_menghitungnya_sebagai_rendah(): void
    {
        $ringkasan = $this->halaman()->viewData('ringkasan');

        $this->assertSame(0, $ringkasan['rendah']);
        $this->assertSame(1, $ringkasan['tanpa_target']);
    }

    /**
     * Rata-rata KPI kosong ketika tidak seorang pun punya target.
     *
     * Merata-ratakan angka yang diukur terhadap target bawaan menghasilkan
     * satu persentase yang terlihat resmi padahal tidak ada pembanding yang
     * pernah disepakati.
     */
    public function test_rata_rata_kosong_ketika_tidak_ada_target_sama_sekali(): void
    {
        $this->assertNull($this->halaman()->viewData('ringkasan')['rata_rata']);

        $this->halaman()->assertSee('Target Belum Diatur');
    }

    /** Admin tetap bisa menyaring siapa yang targetnya belum diatur. */
    public function test_status_tanpa_target_bisa_disaring(): void
    {
        $halaman = $this->halaman();

        $this->assertContains(KpiService::TANPA_TARGET, $halaman->viewData('statusOptions'));

        $this->actingAs($this->admin)
            ->get(route('kpi-pendamping.index', ['status' => KpiService::TANPA_TARGET]))
            ->assertOk()
            ->assertSee('Rosa Linda');
    }

    // =================================================================
    // KONTROL PEMBANDING: KPI TIDAK DIMATIKAN
    // =================================================================

    /**
     * Pendamping yang BENAR-BENAR punya target tetap dinilai sungguhan.
     *
     * Tanpa test ini, seluruh assertion di atas juga akan lulus kalau
     * penilaian KPI dimatikan total — dan alat monitoring Admin hilang
     * tanpa ada yang menyadarinya.
     */
    public function test_pendamping_bertarget_tetap_mendapat_penilaian(): void
    {
        $this->beriTarget();

        $baris = $this->barisRosa();

        $this->assertFalse($baris['pakai_default'], 'Prasyarat: targetnya sudah diatur.');

        $this->assertContains($baris['status'], [
            KpiService::SANGAT_BAIK,
            KpiService::BAIK,
            KpiService::PERLU_PERHATIAN,
            KpiService::RENDAH,
        ], 'Pendamping bertarget harus tetap mendapat salah satu penilaian sungguhan.');

        $this->assertNotSame(KpiService::TANPA_TARGET, $baris['status']);
    }

    /** Indikator yang benar-benar meleset dari target tetap dihitung gagal. */
    public function test_indikator_bertarget_yang_meleset_tetap_dihitung(): void
    {
        $this->beriTarget(pendampingan: 10);

        $this->assertGreaterThan(
            0,
            $this->barisRosa()['belum_tercapai'],
            'Target 10 pendampingan tanpa satu pun catatan harus terhitung belum tercapai.'
        );
    }

    /** Rata-rata kembali terisi begitu ada target sungguhan. */
    public function test_rata_rata_terisi_untuk_pendamping_bertarget(): void
    {
        $this->beriTarget();

        $ringkasan = $this->halaman()->viewData('ringkasan');

        $this->assertNotNull($ringkasan['rata_rata']);
        $this->assertSame(0, $ringkasan['tanpa_target']);
    }
}
