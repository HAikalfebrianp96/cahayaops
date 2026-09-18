<?php

namespace Tests\Feature;

use App\Models\FinancialProxy;
use App\Models\ImpactOutcome;
use App\Models\ImpactProgram;
use App\Models\Participant;
use App\Models\ParticipantMeasurement;
use App\Models\Pendamping;
use App\Models\SroiCalculation;
use App\Models\User;
use App\Services\SroiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * RINGKASAN DAMPAK & SROI DI DASHBOARD.
 *
 * ==========================================================================
 * DUA ATURAN YANG MENENTUKAN SELURUH TEST DI BAWAH
 * ==========================================================================
 *
 * 1. ANGKA NOL BUKAN PENGGANTI "BELUM CUKUP DATA".
 *    Rasio 0 berarti "sudah dihitung, hasilnya nol" — pernyataan yang sangat
 *    berbeda dari "belum bisa dihitung", dan jauh lebih buruk kalau salah.
 *
 * 2. RASIO TIDAK PERNAH TAMPIL TELANJANG.
 *    Angka SROI di dashboard adalah angka yang paling mungkin dikutip tanpa
 *    dibaca konteksnya: ia terlihat sekilas, terlihat resmi, dan terlihat
 *    final. Lencana statusnya harus menempel padanya, supaya yang mengutip
 *    ikut membaca seberapa jauh angka itu sudah ditinjau.
 */
class DashboardDampakTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $pimpinan;
    private User $pendamping;
    private User $timKoperasi;

    protected function setUp(): void
    {
        parent::setUp();

        $profil = Pendamping::create(['nama' => 'Rosa', 'nomor_hp' => '0812', 'email' => 'r@dsh.test', 'status' => 'Aktif']);

        $this->admin = $this->user('admin', 'admin@dsh.test');
        $this->pimpinan = $this->user('pimpinan', 'pimpinan@dsh.test');
        $this->pendamping = $this->user('pendamping', 'pd@dsh.test', $profil->id);
        $this->timKoperasi = $this->user(User::ROLE_TIM_KOPERASI, 'tk@dsh.test');
    }

    // ==================================================================
    // A. BELUM ADA DATA
    // ==================================================================

    public function test_tanpa_program_dashboard_tidak_menampilkan_rasio_apa_pun(): void
    {
        foreach ([$this->admin, $this->pimpinan] as $user) {
            $respons = $this->actingAs($user)->get(route('dashboard'));

            $respons->assertOk()
                ->assertSee('Dampak Program')
                ->assertSee('Belum ada program dampak yang disusun');
        }
    }

    public function test_program_yang_belum_bisa_dihitung_menampilkan_belum_cukup_data(): void
    {
        // Program ADA, tapi belum lengkap. Inilah keadaan yang paling mudah
        // salah ditampilkan sebagai rasio 0.
        ImpactProgram::create([
            'nama' => 'Program Tanpa Data',
            'periode_mulai' => now()->startOfYear()->toDateString(),
            'periode_selesai' => now()->endOfYear()->toDateString(),
        ]);

        $this->actingAs($this->admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Belum Cukup Data')
            ->assertDontSee('nilai sosial untuk setiap');
    }

    public function test_proxy_kosong_ditandai_di_dashboard(): void
    {
        ImpactProgram::create([
            'nama' => 'Program Uji',
            'periode_mulai' => now()->startOfYear()->toDateString(),
            'periode_selesai' => now()->endOfYear()->toDateString(),
        ]);

        $this->actingAs($this->admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('belum dikonfigurasi');
    }

    // ==================================================================
    // B. SUDAH DIHITUNG — rasio selalu bersama statusnya
    // ==================================================================

    public function test_rasio_tampil_bersama_lencana_statusnya(): void
    {
        $hasil = $this->hitungSroi();

        $respons = $this->actingAs($this->admin)->get(route('dashboard'));

        $respons->assertOk()
            ->assertSee(number_format($hasil->rasio, 2, ',', '.'))
            // Status yang sebenarnya, bukan sekadar angkanya.
            ->assertSee($hasil->status);
    }

    public function test_hasil_yang_belum_disetujui_ditandai_belum_layak_dikutip(): void
    {
        $this->hitungSroi();

        $this->actingAs($this->admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('belum disetujui sebagai Final');
    }

    public function test_hasil_final_tidak_lagi_ditandai_belum_disetujui(): void
    {
        $hasil = $this->hitungSroi();

        $hasil->update([
            'status' => SroiCalculation::STATUS_FINAL,
            'disetujui_oleh' => $this->pimpinan->id,
            'disetujui_at' => now(),
        ]);

        $this->actingAs($this->pimpinan)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Final')
            ->assertDontSee('belum disetujui sebagai Final');
    }

    public function test_dashboard_menampilkan_investasi_outcome_dan_peserta_terukur(): void
    {
        $this->hitungSroi();

        $this->actingAs($this->pimpinan)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Total Investasi')
            ->assertSee('Peserta Terukur')
            ->assertSee('Proxy Finansial')
            // 10 juta investasi, 1 outcome, 1 peserta terukur.
            ->assertSee('10.000.000')
            ->assertSee('1 outcome tercatat');
    }

    // ==================================================================
    // C. YANG TIDAK BERHAK TIDAK MELIHATNYA
    // ==================================================================

    public function test_pendamping_dan_tim_koperasi_tidak_melihat_ringkasan_dampak(): void
    {
        $this->hitungSroi();

        foreach ([$this->pendamping, $this->timKoperasi] as $user) {
            $this->actingAs($user)
                ->get(route('dashboard'))
                ->assertOk()
                ->assertDontSee('Buka Modul Dampak');
        }
    }

    public function test_dashboard_pendamping_tetap_terbuka_tanpa_data_dampak(): void
    {
        // Ringkasan dampak tidak dikirim ke dashboard mereka sama sekali —
        // dan halamannya harus tetap terbuka, bukan gagal karena variabel
        // yang tidak ada.
        $this->hitungSroi();

        $this->actingAs($this->pendamping)->get(route('dashboard'))->assertOk();
        $this->actingAs($this->timKoperasi)->get(route('dashboard'))->assertOk();
    }

    // ==================================================================

    private function user(string $role, string $email, ?int $pendampingId = null): User
    {
        return User::create([
            'name' => $email, 'email' => $email,
            'password' => bcrypt('rahasia123'), 'role' => $role,
            'pendamping_id' => $pendampingId, 'is_active' => true,
        ]);
    }

    /** Program lengkap yang benar-benar bisa dihitung. */
    private function hitungSroi(): SroiCalculation
    {
        $program = ImpactProgram::create([
            'nama' => 'Pemberdayaan UMKM',
            'periode_mulai' => now()->startOfYear()->toDateString(),
            'periode_selesai' => now()->endOfYear()->toDateString(),
        ]);

        $peserta = Participant::create([
            'nama_peserta' => 'Bu Ani', 'nomor_hp' => '0800',
            'nama_usaha' => 'Keripik', 'kategori_usaha' => 'Kuliner',
            'tanggal_mulai_program' => now()->startOfYear()->toDateString(),
            'status' => 'Aktif',
        ]);

        $peserta->measurements()->create([
            'jenis' => ParticipantMeasurement::BASELINE,
            'tanggal' => now()->startOfYear()->toDateString(),
            'omzet_bulanan' => 1_000_000,
        ]);
        $peserta->measurements()->create([
            'jenis' => ParticipantMeasurement::ENDLINE,
            'tanggal' => now()->toDateString(),
            'omzet_bulanan' => 2_000_000,
        ]);

        $program->investments()->create([
            'kategori' => 'Pelatihan', 'uraian' => 'Pelatihan',
            'tanggal' => now()->toDateString(), 'nominal' => 10_000_000, 'jenis' => 'Tunai',
        ]);

        $proxy = FinancialProxy::create([
            'nama' => 'Proxy Uji', 'satuan' => 'per rupiah kenaikan omzet',
            'nilai' => 1, 'sumber' => 'Dokumen Uji', 'tahun' => now()->year,
        ]);

        $program->outcomes()->create([
            'nama' => 'Pendapatan meningkat',
            'sumber_nilai' => ImpactOutcome::SUMBER_OTOMATIS,
            'indikator' => 'omzet_bulanan',
            'financial_proxy_id' => $proxy->id,
            'deadweight_persen' => 20,
            'attribution_persen' => 80,
            'attribution_catatan' => 'Sebagian dari usaha peserta sendiri.',
            'displacement_relevan' => false,
            'displacement_catatan' => 'Pasarnya tidak bersinggungan.',
        ]);

        return app(SroiService::class)->hitung($program->fresh());
    }
}
