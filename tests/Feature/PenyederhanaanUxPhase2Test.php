<?php

namespace Tests\Feature;

use App\Models\Participant;
use App\Models\Pendamping;
use App\Models\PendampingTarget;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PENYEDERHANAAN UX — YANG DIJAGA DI SINI ADALAH CARA MEMBACANYA.
 *
 * Perhitungan KPI, mesin SROI, dan aturan hapus permanen sudah punya test
 * masing-masing. Yang belum pernah dijaga justru hal yang paling gampang
 * hilang diam-diam saat ada fitur baru ditambahkan: pengelompokan indikator,
 * batas antara "pekerjaan pendamping" dan "pengaturan teknis", serta bahasa
 * yang dipakai menjelaskan keduanya.
 */
class PenyederhanaanUxPhase2Test extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $pendamping;
    private Pendamping $profil;
    private Participant $peserta;

    protected function setUp(): void
    {
        parent::setUp();

        $this->profil = Pendamping::create([
            'nama' => 'Pendamping Uji', 'nomor_hp' => '0812',
            'email' => 'p@ux.test', 'status' => 'Aktif',
        ]);

        $this->admin = User::create([
            'name' => 'Admin Uji', 'email' => 'admin@ux.test',
            'password' => 'password123', 'role' => 'admin', 'is_active' => true,
        ]);

        $this->pendamping = User::create([
            'name' => 'Rosa Uji', 'email' => 'rosa@ux.test',
            'password' => 'password123', 'role' => 'pendamping',
            'pendamping_id' => $this->profil->id, 'is_active' => true,
        ]);

        $this->peserta = Participant::create([
            'nama_peserta' => 'Peserta Uji', 'nomor_hp' => '0813', 'alamat' => 'Jl. Uji',
            'nama_usaha' => 'Usaha Uji', 'kategori_usaha' => 'Jasa',
            'tanggal_mulai_program' => now()->subMonths(3)->toDateString(),
            'status' => 'Aktif', 'pendamping_id' => $this->profil->id,
        ]);
    }

    // =================================================================
    // PENGELOMPOKAN INDIKATOR
    // =================================================================

    /**
     * Setiap indikator harus punya kelompok.
     *
     * Kalau nanti ada indikator baru ditambahkan ke INDIKATOR tapi lupa
     * didaftarkan ke KELOMPOK, ia akan jatuh ke "pendukung" tanpa ada yang
     * pernah memutuskan begitu. Test ini memaksa keputusannya dibuat sadar.
     */
    public function test_setiap_indikator_kpi_punya_kelompok_yang_didaftarkan(): void
    {
        $terkelompok = array_merge(...array_values(PendampingTarget::KELOMPOK));

        $this->assertSame(
            [],
            array_diff(array_keys(PendampingTarget::INDIKATOR), $terkelompok),
            'Ada indikator yang belum didaftarkan ke PendampingTarget::KELOMPOK.'
        );

        $this->assertSame(
            [],
            array_diff($terkelompok, array_keys(PendampingTarget::INDIKATOR)),
            'Ada kolom di KELOMPOK yang sudah tidak ada di INDIKATOR.'
        );
    }

    /**
     * Jumlah binaan adalah BEBAN KERJA, bukan prestasi.
     *
     * Pendamping dengan 40 binaan tidak lebih baik daripada yang punya 15 —
     * ia lebih berat. Menaruhnya sejajar dengan indikator prestasi mendorong
     * pembacaan yang keliru, dan itulah yang dijaga di sini.
     */
    public function test_jumlah_binaan_dikelompokkan_sebagai_beban_kerja(): void
    {
        $this->assertSame(
            PendampingTarget::KELOMPOK_BEBAN,
            PendampingTarget::kelompokIndikator('target_jumlah_binaan')
        );

        foreach (['target_pendampingan', 'target_kunjungan', 'target_follow_up'] as $utama) {
            $this->assertSame(
                PendampingTarget::KELOMPOK_UTAMA,
                PendampingTarget::kelompokIndikator($utama),
                "{$utama} seharusnya KPI utama."
            );
        }
    }

    public function test_halaman_kpi_menampilkan_ketiga_kelompok_dan_menandai_beban_kerja(): void
    {
        $respons = $this->actingAs($this->admin)
            ->get(route('kpi-pendamping.show', $this->profil))
            ->assertOk();

        $respons->assertSee('KPI Utama')
            ->assertSee('Indikator Pendukung')
            ->assertSee('Beban Kerja')
            ->assertSee('kapasitas, bukan prestasi');
    }

    /**
     * Indikator tanpa target tetap TIDAK ikut dihitung.
     *
     * Pengelompokan hanya mengubah tampilan. Kalau ia sampai ikut mengubah
     * perhitungan, seluruh rapor KPI yang sudah pernah dibaca orang menjadi
     * tidak cocok lagi dengan sistem.
     */
    public function test_pengelompokan_tidak_mengubah_indikator_mana_yang_dihitung(): void
    {
        PendampingTarget::create([
            'pendamping_id' => $this->profil->id,
            'periode' => PendampingTarget::PERIODE_BULANAN,
            'tahun' => now()->year,
            'bulan' => now()->month,
            'target_pendampingan' => 4,
        ]);

        $rapor = $this->actingAs($this->admin)
            ->get(route('kpi-pendamping.show', $this->profil))
            ->assertOk()
            ->viewData('rapor');

        $this->assertTrue(
            $rapor['indikator']['target_pendampingan']['dihitung'],
            'Indikator yang DITARGETKAN harus ikut dihitung.'
        );

        foreach ($rapor['indikator'] as $kolom => $i) {
            if ($kolom === 'target_pendampingan') {
                continue;
            }

            $this->assertFalse(
                $i['dihitung'],
                "{$kolom} tidak ditargetkan, jadi tidak boleh ikut dihitung."
            );
        }
    }

    // =================================================================
    // BATAS PENDAMPING vs PENGATURAN TEKNIS
    // =================================================================

    /**
     * Pendamping tidak berurusan dengan konfigurasi SROI.
     *
     * Ia menghasilkan data operasional; nilai acuan rupiah, deadweight, dan
     * attribution adalah urusan penyusun program. Dijaga di tingkat
     * authorization, bukan sekadar disembunyikan dari menu — menu yang
     * disembunyikan masih bisa dibuka lewat URL.
     */
    public function test_pendamping_tidak_bisa_membuka_konfigurasi_sroi(): void
    {
        foreach ([
            route('impact.index'),
            route('impact.proxy.index'),
            route('impact.proxy.create'),
            route('impact.create'),
        ] as $url) {
            $this->actingAs($this->pendamping)->get($url)->assertForbidden();
        }
    }

    public function test_pendamping_tidak_melihat_tautan_dampak_di_sidebar(): void
    {
        $this->actingAs($this->pendamping)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee(route('impact.index'));
    }

    public function test_admin_tetap_bisa_mengelola_program_dampak(): void
    {
        $this->actingAs($this->admin)
            ->get(route('impact.index'))
            ->assertOk()
            ->assertSee('Dampak Program');

        $this->actingAs($this->admin)
            ->get(route('impact.proxy.index'))
            ->assertOk();
    }

    // =================================================================
    // BAHASA
    // =================================================================

    /**
     * Ketiga tahap dijelaskan tanpa istilah SROI.
     */
    public function test_halaman_peserta_menjelaskan_tiga_tahap_dengan_bahasa_biasa(): void
    {
        $respons = $this->actingAs($this->pendamping)
            ->get(route('participants.show', $this->peserta))
            ->assertOk();

        $respons->assertSee('Catat kondisi awal')
            ->assertSee('catat kondisi akhir')
            ->assertSee('Monitoring')
            ->assertDontSee('deadweight')
            ->assertDontSee('attribution');
    }

    /**
     * Prioritas menyebut alasan yang bisa dibaca orang, bukan skor.
     */
    public function test_prioritas_menampilkan_alasan_manusiawi_bukan_rumus(): void
    {
        $respons = $this->actingAs($this->pendamping)
            ->get(route('prioritas.index'))
            ->assertOk();

        // Peserta ini belum pernah didampingi sama sekali.
        $respons->assertSee('Belum pernah mendapat pendampingan')
            ->assertSee('Perlu Segera')
            ->assertSee('Jadwal Hari Ini')
            ->assertSee('Normal');

        // Skor internal tidak pernah ditampilkan ke pendamping.
        $respons->assertDontSee('skor');
    }

}
