<?php

namespace Tests\Feature;

use App\Models\Pendamping;
use App\Models\PendampingTarget;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PENILAIAN HARUS MENYEBUTKAN DASARNYA.
 *
 * =====================================================================
 * KEADAAN YANG DIJAGA
 * =====================================================================
 *
 * Kalau tidak ada baris target untuk sebuah periode, KpiService memakai
 * angka bawaan dari pengaturan/config. Perhitungannya memang dirancang
 * begitu, dan test ini TIDAK mengubahnya.
 *
 * Yang dijaga di sini: keadaan itu harus terbaca di tempat vonisnya
 * ditampilkan. Menyebut seorang pendamping "Rendah" terhadap angka yang
 * tidak pernah ia sepakati adalah penilaian yang terbaca seperti vonis,
 * padahal dasarnya baru asumsi — dan keterangannya dulu hanya berupa ekor
 * kalimat kecil di bawah grafik.
 */
class TargetBawaanTerbacaTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $pendamping;
    private Pendamping $profil;

    protected function setUp(): void
    {
        parent::setUp();

        $this->profil = Pendamping::create([
            'nama' => 'Pendamping Uji', 'nomor_hp' => '081200000001',
            'email' => 'p@target.test', 'status' => 'Aktif',
        ]);

        $this->admin = User::create([
            'name' => 'Admin Uji', 'email' => 'admin@target.test',
            'password' => 'password123', 'role' => 'admin', 'is_active' => true,
        ]);

        $this->pendamping = User::create([
            'name' => 'Rosa Uji', 'email' => 'rosa@target.test',
            'password' => 'password123', 'role' => 'pendamping',
            'pendamping_id' => $this->profil->id, 'is_active' => true,
        ]);
    }

    private function tetapkanTarget(): void
    {
        PendampingTarget::create([
            'pendamping_id' => $this->profil->id,
            'periode' => PendampingTarget::PERIODE_BULANAN,
            'tahun' => now()->year,
            'bulan' => now()->month,
            'target_pendampingan' => 4,
        ]);
    }

    // =================================================================
    // HALAMAN RINCIAN
    // =================================================================

    public function test_tanpa_target_halaman_kpi_menyebut_dasarnya_di_dekat_vonis(): void
    {
        $this->actingAs($this->pendamping)
            ->get(route('kpi-pendamping.show', $this->profil))
            ->assertOk()
            ->assertSee('Target bawaan sistem')
            ->assertSee('Belum ada target yang ditetapkan untuk periode ini');
    }

    /**
     * Pendamping TIDAK diberi tautan menetapkan target — ia memang tidak
     * berwenang, dan tautan yang berujung 403 lebih buruk daripada tidak ada.
     */
    public function test_pendamping_diberi_arahan_bukan_tautan_yang_akan_ditolak(): void
    {
        $this->actingAs($this->pendamping)
            ->get(route('kpi-pendamping.show', $this->profil))
            ->assertOk()
            ->assertSee('Hubungi Admin')
            ->assertDontSee(route('target-pendamping.create'));
    }

    public function test_admin_diberi_tautan_menetapkan_target(): void
    {
        $this->actingAs($this->admin)
            ->get(route('kpi-pendamping.show', $this->profil))
            ->assertOk()
            ->assertSee('Tetapkan target untuk pendamping ini');
    }

    /**
     * Begitu target ditetapkan, seluruh peringatan itu HILANG.
     *
     * Peringatan yang tetap tampil setelah dikerjakan akan berhenti dibaca,
     * dan itu membuat peringatan berikutnya ikut diabaikan.
     */
    public function test_setelah_target_ditetapkan_peringatan_hilang(): void
    {
        $this->tetapkanTarget();

        $this->actingAs($this->admin)
            ->get(route('kpi-pendamping.show', $this->profil))
            ->assertOk()
            ->assertDontSee('Target bawaan sistem')
            ->assertDontSee('Belum ada target yang ditetapkan untuk periode ini')
            // Halaman rincian menulisnya huruf kecil di tengah kalimat
            // ("Sumber target: target individual"); yang berkapital ada di
            // halaman daftar.
            ->assertSee('target individual');
    }

    // =================================================================
    // DAFTAR KPI
    // =================================================================

    public function test_daftar_kpi_memberi_tahu_admin_belum_ada_target_sama_sekali(): void
    {
        $this->actingAs($this->admin)
            ->get(route('kpi-pendamping.index'))
            ->assertOk()
            ->assertSee('Belum ada target yang ditetapkan untuk periode ini');
    }

    /**
     * Peringatan itu pekerjaan Admin, bukan beban Pendamping.
     */
    public function test_pendamping_tidak_dibebani_peringatan_target_di_daftar(): void
    {
        $this->actingAs($this->pendamping)
            ->get(route('kpi-pendamping.index'))
            ->assertOk()
            ->assertDontSee('Belum ada target yang ditetapkan untuk periode ini');
    }

    public function test_peringatan_daftar_hilang_setelah_seluruh_target_terisi(): void
    {
        $this->tetapkanTarget();

        $this->actingAs($this->admin)
            ->get(route('kpi-pendamping.index'))
            ->assertOk()
            ->assertDontSee('Belum ada target yang ditetapkan untuk periode ini');
    }

    // =================================================================
    // PERHITUNGANNYA TIDAK IKUT BERUBAH
    // =================================================================

    /**
     * Seluruh perubahan ini soal penyampaian. Kalau skornya ikut bergeser,
     * seluruh rapor KPI yang pernah dibaca orang jadi tidak cocok lagi.
     */
    public function test_penyampaian_tidak_mengubah_skor_maupun_sumber_target(): void
    {
        $rapor = $this->actingAs($this->admin)
            ->get(route('kpi-pendamping.show', $this->profil))
            ->assertOk()
            ->viewData('rapor');

        $this->assertTrue($rapor['pakai_default']);
        $this->assertNotNull($rapor['kpi'], 'Target bawaan tetap menghasilkan skor, seperti sebelumnya.');

        $this->tetapkanTarget();

        $sesudah = $this->actingAs($this->admin)
            ->get(route('kpi-pendamping.show', $this->profil))
            ->assertOk()
            ->viewData('rapor');

        $this->assertFalse($sesudah['pakai_default']);
    }
}
