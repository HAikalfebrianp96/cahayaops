<?php

namespace Tests\Feature;

use App\Models\LegalitasRecord;
use App\Models\Participant;
use App\Models\Pendamping;
use App\Models\PendampinganRecord;
use App\Models\PendampingTarget;
use App\Models\User;
use App\Services\KpiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PRIORITAS PENDAMPINGAN + WIDGET KINERJA DI DASHBOARD.
 *
 * Yang paling dijaga: TIDAK ADA data pendamping lain yang bocor, baik lewat
 * daftar, lewat angka ringkasan, maupun lewat filter di URL.
 *
 * Selain itu: angka KPI di Dashboard harus SAMA PERSIS dengan yang di
 * halaman KPI — keduanya memakai KpiService yang sama, dan test ini
 * memastikannya tidak berpencar.
 */
class PrioritasDanDashboardKinerjaTest extends TestCase
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

        $this->profilA = Pendamping::create(['nama' => 'Rosa Linda', 'nomor_hp' => '0812', 'email' => 'a@pr.test', 'status' => 'Aktif']);
        $this->profilB = Pendamping::create(['nama' => 'Pendamping B', 'nomor_hp' => '0813', 'email' => 'b@pr.test', 'status' => 'Aktif']);

        $this->pendampingA = $this->user('pendamping', 'a@akun.test', $this->profilA->id);
        $this->admin = $this->user('admin', 'admin@pr.test');
        $this->pimpinan = $this->user('pimpinan', 'pimpinan@pr.test');
        $this->timKoperasi = $this->user(User::ROLE_TIM_KOPERASI, 'tk@pr.test');

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

    private function peserta(string $nama, ?int $pendampingId, array $ubah = []): Participant
    {
        return Participant::create(array_merge([
            'nama_peserta' => $nama, 'nomor_hp' => '0812', 'alamat' => 'Jl. Uji',
            'nama_usaha' => 'Usaha ' . $nama, 'kategori_usaha' => 'Jasa',
            'tanggal_mulai_program' => '2026-01-10', 'status' => 'Aktif',
            'pendamping_id' => $pendampingId,
        ], $ubah));
    }

    private function catatan(Participant $p, string $tanggal, array $ubah = []): PendampinganRecord
    {
        return PendampinganRecord::create(array_merge([
            'participant_id' => $p->id, 'pendamping_id' => $p->pendamping_id,
            'tanggal' => $tanggal, 'jenis_kegiatan' => 'Konsultasi', 'materi' => 'Materi uji',
        ], $ubah));
    }

    private function lengkapiPeserta(Participant $p): void
    {
        // Sudah didampingi + omzet bulan ini + data lengkap = prioritas rendah.
        $this->catatan($p, now()->toDateString());
        $p->omzetReports()->create([
            'periode_bulan' => now()->month, 'periode_tahun' => now()->year, 'omzet' => 1000000,
        ]);
    }

    private function kelompok(User $user, array $query = [])
    {
        return $this->actingAs($user)
            ->get(route('prioritas.index', $query))
            ->assertOk()
            ->viewData('kelompok');
    }

    // =====================================================================
    // PRIORITAS — TINGKAT
    // =====================================================================

    public function test_belum_pernah_didampingi_masuk_prioritas_tinggi(): void
    {
        $kelompok = $this->kelompok($this->pendampingA);

        $this->assertSame(1, $kelompok['tinggi']->count());
        $this->assertStringContainsString(
            'Belum pernah mendapat pendampingan',
            implode(' ', $kelompok['tinggi'][0]['alasan'])
        );
    }

    public function test_follow_up_terlambat_masuk_prioritas_tinggi(): void
    {
        $this->catatan($this->binaanA, now()->subDays(5)->toDateString(), [
            'tanggal_follow_up' => now()->subDays(3)->toDateString(),
        ]);
        $this->binaanA->omzetReports()->create([
            'periode_bulan' => now()->month, 'periode_tahun' => now()->year, 'omzet' => 1000000,
        ]);

        $kelompok = $this->kelompok($this->pendampingA);

        $this->assertSame(1, $kelompok['tinggi']->count());
        $this->assertStringContainsString('Follow up terlambat', implode(' ', $kelompok['tinggi'][0]['alasan']));
    }

    /**
     * Janji yang jatuh HARI INI berdiri sendiri.
     *
     * Sebelum kelompok ini ada, janji hari ini tercampur dengan janji minggu
     * depan di "Prioritas Sedang" — padahal hanya satu dari keduanya yang
     * harus dikerjakan hari ini juga.
     */
    public function test_follow_up_hari_ini_masuk_kelompok_jadwal_hari_ini(): void
    {
        // lengkapiPeserta() TIDAK dipakai di sini: ia mencatat pendampingan
        // bertanggal HARI INI, yang otomatis menjadi catatan terakhir dan
        // menggeser catatan berjadwal di bawah ini — jadwal follow up dibaca
        // dari catatan TERAKHIR.
        $this->catatan($this->binaanA, now()->subDays(3)->toDateString(), [
            'tanggal_follow_up' => now()->toDateString(),
        ]);
        $this->binaanA->omzetReports()->create([
            'periode_bulan' => now()->month, 'periode_tahun' => now()->year, 'omzet' => 1000000,
        ]);

        $kelompok = $this->kelompok($this->pendampingA);

        $this->assertSame(1, $kelompok['hari_ini']->count());
        $this->assertSame(0, $kelompok['sedang']->count());
        $this->assertStringContainsString(
            'dijadwalkan hari ini',
            implode(' ', $kelompok['hari_ini'][0]['alasan'])
        );
    }

    /**
     * Yang mendesak tetap menang.
     *
     * Peserta yang belum pernah didampingi TIDAK boleh turun jadi sekadar
     * "ada janji hari ini" hanya karena janjinya kebetulan jatuh hari ini.
     */
    public function test_peserta_mendesak_tetap_di_perlu_segera_meski_ada_jadwal_hari_ini(): void
    {
        $this->catatan($this->binaanA, now()->subDays(120)->toDateString(), [
            'tanggal_follow_up' => now()->toDateString(),
        ]);
        $this->binaanA->omzetReports()->create([
            'periode_bulan' => now()->month, 'periode_tahun' => now()->year, 'omzet' => 1000000,
        ]);

        $kelompok = $this->kelompok($this->pendampingA);

        $this->assertSame(1, $kelompok['tinggi']->count());
        $this->assertSame(0, $kelompok['hari_ini']->count());
    }

    public function test_tidak_ada_aktivitas_lama_masuk_prioritas_tinggi(): void
    {
        $this->catatan($this->binaanA, now()->subDays(90)->toDateString());

        $kelompok = $this->kelompok($this->pendampingA);

        $this->assertSame(1, $kelompok['tinggi']->count());
        $this->assertStringContainsString('Tanpa aktivitas', implode(' ', $kelompok['tinggi'][0]['alasan']));
    }

    public function test_omzet_belum_diperbarui_masuk_prioritas_tinggi(): void
    {
        $this->catatan($this->binaanA, now()->toDateString());

        $kelompok = $this->kelompok($this->pendampingA);

        $this->assertStringContainsString(
            'Omzet bulan ini belum diperbarui',
            implode(' ', $kelompok['tinggi'][0]['alasan'])
        );
    }

    public function test_legalitas_belum_selesai_masuk_prioritas_tinggi(): void
    {
        $this->lengkapiPeserta($this->binaanA);

        LegalitasRecord::create([
            'participant_id' => $this->binaanA->id, 'jenis_dokumen' => 'NIB', 'status' => 'Belum Ada',
        ]);

        $kelompok = $this->kelompok($this->pendampingA);

        $this->assertSame(1, $kelompok['tinggi']->count());
        $this->assertStringContainsString('legalitas belum selesai', implode(' ', $kelompok['tinggi'][0]['alasan']));
    }

    /**
     * Peserta tanpa dokumen legalitas terdaftar TIDAK dianggap bermasalah —
     * tidak semua UMKM membutuhkan legalitas yang sama.
     */
    public function test_tanpa_dokumen_legalitas_tidak_dihukum(): void
    {
        $this->lengkapiPeserta($this->binaanA);

        $kelompok = $this->kelompok($this->pendampingA);

        $this->assertSame(0, $kelompok['tinggi']->count());
        $this->assertSame(1, $kelompok['rendah']->count());
    }

    public function test_data_belum_lengkap_masuk_prioritas_sedang(): void
    {
        $kurang = $this->peserta('Binaan Kurang', $this->profilA->id, ['alamat' => null]);
        $this->lengkapiPeserta($kurang);
        $this->lengkapiPeserta($this->binaanA);

        $kelompok = $this->kelompok($this->pendampingA);

        $this->assertSame(1, $kelompok['sedang']->count());
        $this->assertStringContainsString('Data belum lengkap', implode(' ', $kelompok['sedang'][0]['alasan']));
    }

    public function test_peserta_aktif_masuk_prioritas_rendah(): void
    {
        $this->lengkapiPeserta($this->binaanA);

        $kelompok = $this->kelompok($this->pendampingA);

        $this->assertSame(1, $kelompok['rendah']->count());
        $this->assertContains('Aktif dan rutin diperbarui', $kelompok['rendah'][0]['alasan']);
    }

    /** Alumni/Nonaktif tidak perlu diprioritaskan lagi. */
    public function test_peserta_tidak_berjalan_dikecualikan(): void
    {
        $this->binaanA->update(['status' => 'Alumni']);

        $kelompok = $this->kelompok($this->pendampingA);

        $this->assertSame(0, collect($kelompok)->sum(fn ($k) => $k->count()));
    }

    // =====================================================================
    // PRIORITAS — OTORISASI & SCOPE
    // =====================================================================

    /** INTI: Pendamping hanya melihat binaannya sendiri. */
    public function test_pendamping_hanya_melihat_prioritas_binaannya(): void
    {
        $this->actingAs($this->pendampingA)
            ->get(route('prioritas.index'))
            ->assertOk()
            ->assertSee('Binaan Milik A')
            ->assertDontSee('Binaan Milik B');
    }

    /** Dan filter dari URL tidak bisa dipakai mengintip. */
    public function test_pendamping_tidak_bisa_mengintip_lewat_filter_url(): void
    {
        $this->actingAs($this->pendampingA)
            ->get(route('prioritas.index', ['pendamping_id' => $this->profilB->id]))
            ->assertOk()
            ->assertSee('Binaan Milik A')
            ->assertDontSee('Binaan Milik B');
    }

    public function test_admin_melihat_seluruh_peserta(): void
    {
        $this->actingAs($this->admin)
            ->get(route('prioritas.index'))
            ->assertOk()
            ->assertSee('Binaan Milik A')
            ->assertSee('Binaan Milik B');
    }

    public function test_admin_bisa_memfilter_per_pendamping(): void
    {
        $this->actingAs($this->admin)
            ->get(route('prioritas.index', ['pendamping_id' => $this->profilA->id]))
            ->assertOk()
            ->assertSee('Binaan Milik A')
            ->assertDontSee('Binaan Milik B');
    }

    public function test_role_lain_ditolak_di_halaman_prioritas(): void
    {
        foreach ([$this->pimpinan, $this->timKoperasi] as $user) {
            $this->actingAs($user)->get(route('prioritas.index'))->assertForbidden();
        }

        $this->actingAs($this->user('pendamping', 'lepas@pr.test'))
            ->get(route('prioritas.index'))->assertForbidden();
    }

    /** Alasan SELALU disertakan — daftar prioritas tanpa alasan tidak berguna. */
    public function test_setiap_baris_prioritas_punya_alasan(): void
    {
        $kelompok = $this->kelompok($this->pendampingA);

        foreach ($kelompok as $tingkat) {
            foreach ($tingkat as $baris) {
                $this->assertNotEmpty($baris['alasan'], 'Ada baris prioritas tanpa alasan.');
            }
        }
    }

    // =====================================================================
    // DASHBOARD ADMIN — widget kinerja pendamping
    // =====================================================================

    public function test_dashboard_admin_menampilkan_widget_kinerja(): void
    {
        $this->actingAs($this->admin)
            ->get(route('dashboard', ['modul' => 'preneur']))
            ->assertOk()
            ->assertSee('Kinerja Pendamping')
            ->assertSee('Top 5 Pendamping Bulan Ini')
            ->assertSee('Pendamping Perlu Perhatian')
            ->assertSee(route('kpi-pendamping.index'), false);
    }

    public function test_ringkasan_kinerja_menghitung_seluruh_pendamping(): void
    {
        $this->actingAs($this->admin)
            ->get(route('dashboard', ['modul' => 'preneur']))
            ->assertOk()
            ->assertViewHas('kinerjaPendamping', fn ($k) => $k['total_pendamping'] === 2);
    }

    /**
     * INTI: angka KPI di Dashboard HARUS sama dengan di halaman KPI.
     * Keduanya memakai KpiService yang sama; test ini menjaga agar tidak
     * ada yang menghitung ulang dengan rumus sendiri.
     */
    public function test_kpi_dashboard_sama_dengan_halaman_kpi(): void
    {
        PendampingTarget::create([
            'pendamping_id' => $this->profilA->id,
            'periode' => PendampingTarget::PERIODE_BULANAN,
            'tahun' => now()->year, 'bulan' => now()->month,
            'target_kunjungan' => 2,
        ]);
        $this->catatan($this->binaanA, now()->toDateString(), ['jenis_kegiatan' => 'Kunjungan Usaha']);

        $dariService = app(KpiService::class)
            ->untukSatu($this->profilA, now()->startOfMonth(), now()->endOfMonth());

        $dariHalaman = $this->actingAs($this->admin)
            ->get(route('kpi-pendamping.index'))
            ->viewData('rapor')
            ->firstWhere('pendamping.id', $this->profilA->id);

        $this->assertSame($dariService['kpi'], $dariHalaman['kpi']);
        $this->assertSame(50, $dariService['kpi']); // 1 dari target 2
    }

    // =====================================================================
    // DASHBOARD PENDAMPING — target bulan ini
    // =====================================================================

    public function test_dashboard_pendamping_menampilkan_target_bulan_ini(): void
    {
        PendampingTarget::create([
            'pendamping_id' => $this->profilA->id,
            'periode' => PendampingTarget::PERIODE_BULANAN,
            'tahun' => now()->year, 'bulan' => now()->month,
            'target_kunjungan' => 4,
        ]);
        $this->catatan($this->binaanA, now()->toDateString(), ['jenis_kegiatan' => 'Kunjungan Usaha']);

        $this->actingAs($this->pendampingA)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Target Bulan Ini')
            ->assertSee('Anda sudah mencapai 25% target bulan ini')
            ->assertViewHas('raporKpi', fn ($r) => $r['kpi'] === 25);
    }

    public function test_dashboard_pendamping_memberi_pesan_saat_target_tercapai(): void
    {
        PendampingTarget::create([
            'pendamping_id' => $this->profilA->id,
            'periode' => PendampingTarget::PERIODE_BULANAN,
            'tahun' => now()->year, 'bulan' => now()->month,
            'target_kunjungan' => 1,
        ]);
        $this->catatan($this->binaanA, now()->toDateString(), ['jenis_kegiatan' => 'Kunjungan Usaha']);

        $this->actingAs($this->pendampingA)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Seluruh target bulan ini sudah tercapai');
    }

    /** Dashboard Pendamping TIDAK memuat KPI pendamping lain. */
    public function test_dashboard_pendamping_tidak_memuat_kinerja_pendamping_lain(): void
    {
        $this->actingAs($this->pendampingA)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Pendamping B')
            ->assertDontSee('Top 5 Pendamping');
    }

    // =====================================================================
    // BEBAN PENDAMPING di daftar Pendamping
    // =====================================================================

    public function test_daftar_pendamping_menampilkan_kategori_beban(): void
    {
        $this->actingAs($this->admin)
            ->get(route('pendampings.index'))
            ->assertOk()
            ->assertSee('Ringan')
            ->assertSee(route('kpi-pendamping.show', $this->profilA), false);
    }

    public function test_kategori_beban_berubah_saat_binaan_bertambah(): void
    {
        for ($i = 0; $i < 15; $i++) {
            $this->peserta('Binaan Massal ' . $i, $this->profilA->id);
        }

        $this->actingAs($this->admin)
            ->get(route('pendampings.index'))
            ->assertOk()
            ->assertSee('Normal');
    }
}
