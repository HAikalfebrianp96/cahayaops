<?php

namespace Tests\Feature;

use App\Models\Participant;
use App\Models\Pendamping;
use App\Models\PendampinganRecord;
use App\Models\PendampingTarget;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\KpiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * TARGET PENDAMPING (CRUD) + PERHITUNGAN KPI.
 *
 * Empat hal yang paling dijaga kelas ini:
 *
 *   1. INDIKATOR TANPA TARGET TIDAK DIHITUNG. "Tidak ditargetkan" berbeda
 *      dari "ditargetkan tapi gagal"; menghitungnya sebagai 0% akan
 *      menghukum pendamping atas sesuatu yang tidak pernah diminta.
 *
 *   2. TARGET NOL TIDAK MENYEBABKAN PEMBAGIAN DENGAN NOL.
 *
 *   3. DATA TERHAPUS TIDAK IKUT DIHITUNG — baik catatan lapangannya maupun
 *      targetnya sendiri.
 *
 *   4. PENDAMPING TIDAK BISA MENYENTUH TARGET SIAPA PUN, termasuk miliknya.
 *      Kalau bisa, target berhenti jadi alat ukur dan berubah jadi sesuatu
 *      yang disesuaikan agar terlihat tercapai.
 */
class TargetDanKpiPendampingTest extends TestCase
{
    use RefreshDatabase;

    private Pendamping $profilA;
    private Pendamping $profilB;
    private User $pendampingA;
    private User $admin;
    private User $pimpinan;
    private User $timKoperasi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->profilA = Pendamping::create(['nama' => 'Rosa Linda', 'nomor_hp' => '0812', 'email' => 'a@kpi.test', 'status' => 'Aktif']);
        $this->profilB = Pendamping::create(['nama' => 'Pendamping B', 'nomor_hp' => '0813', 'email' => 'b@kpi.test', 'status' => 'Aktif']);

        $this->pendampingA = $this->user('pendamping', 'a@akun.test', $this->profilA->id);
        $this->admin = $this->user('admin', 'admin@kpi.test');
        $this->pimpinan = $this->user('pimpinan', 'pimpinan@kpi.test');
        $this->timKoperasi = $this->user(User::ROLE_TIM_KOPERASI, 'tk@kpi.test');
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
            'nama_peserta' => $nama, 'nomor_hp' => '0812', 'alamat' => 'Jl. Uji',
            'nama_usaha' => 'Usaha ' . $nama, 'kategori_usaha' => 'Jasa',
            'tanggal_mulai_program' => '2026-01-10', 'status' => 'Aktif',
            'pendamping_id' => $pendampingId,
        ]);
    }

    private function catatan(Participant $p, string $jenis = 'Konsultasi', ?string $followUp = null): PendampinganRecord
    {
        return PendampinganRecord::create([
            'participant_id' => $p->id, 'pendamping_id' => $p->pendamping_id,
            'tanggal' => now()->toDateString(), 'jenis_kegiatan' => $jenis,
            'materi' => 'Materi ' . $jenis, 'tanggal_follow_up' => $followUp,
        ]);
    }

    private function target(array $ubah = []): PendampingTarget
    {
        return PendampingTarget::create(array_merge([
            'pendamping_id' => $this->profilA->id,
            'periode' => PendampingTarget::PERIODE_BULANAN,
            'tahun' => now()->year,
            'bulan' => now()->month,
        ], $ubah));
    }

    private function formTarget(array $ubah = []): array
    {
        return array_merge([
            'pendamping_id' => $this->profilA->id,
            'periode' => PendampingTarget::PERIODE_BULANAN,
            'tahun' => now()->year,
            'bulan' => now()->month,
            'target_kunjungan' => 10,
        ], $ubah);
    }

    private function kpi(): KpiService
    {
        return app(KpiService::class);
    }

    private function rapor(Pendamping $p): array
    {
        return $this->kpi()->untukSatu($p, now()->startOfMonth(), now()->endOfMonth());
    }

    // =====================================================================
    // CRUD TARGET — Admin
    // =====================================================================

    // =====================================================================
    // TATA LETAK KARTU "PERKEMBANGAN KINERJA"
    //
    // Chart.js dipakai dengan maintainAspectRatio:false. Dalam mode itu
    // kanvas mengambil tinggi dari elemen INDUKNYA — kalau induknya tidak
    // punya tinggi, grafiknya memanjang tak terkendali dan mendorong seluruh
    // halaman ke bawah. Itu persis yang dulu terjadi di halaman ini.
    //
    // Kegagalannya tidak menimbulkan error apa pun, jadi hanya test seperti
    // ini yang bisa menahannya kembali.
    // =====================================================================

    public function test_grafik_kinerja_punya_pembungkus_bertinggi_tetap(): void
    {
        $isi = $this->actingAs($this->admin)
            ->get(route('kpi-pendamping.show', $this->profilA))
            ->assertOk()
            ->getContent();

        // Tinggi dikunci di pembungkus, bertingkat sesuai lebar layar.
        $this->assertStringContainsString('h-[260px]', $isi, 'Tinggi grafik di layar kecil tidak dikunci.');
        $this->assertStringContainsString('sm:h-[300px]', $isi);
        $this->assertStringContainsString('lg:h-[330px]', $isi);

        // Atribut height pada kanvas tidak pernah berpengaruh di mode ini —
        // keberadaannya hanya menyesatkan pembaca berikutnya.
        $this->assertStringNotContainsString('id="grafikKpi" height=', $isi);
    }

    public function test_riwayat_bulanan_tertutup_tapi_datanya_tetap_ada(): void
    {
        // Dua belas baris tabel yang selalu terbuka mengubah halaman ringkasan
        // menjadi halaman tabel. Ditutup, BUKAN dihilangkan — datanya tetap
        // dirender dan bisa dibuka.
        $isi = $this->actingAs($this->admin)
            ->get(route('kpi-pendamping.show', $this->profilA))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('<details', $isi);
        $this->assertStringContainsString('Lihat Detail', $isi);
        $this->assertStringContainsString('Riwayat Kinerja Bulanan', $isi);
    }

    public function test_grafik_memakai_dua_belas_bulan_dari_data_yang_ada(): void
    {
        // Memastikan pemendekan label tidak sampai memotong datanya: yang
        // dipendekkan hanya tulisan di sumbu X, bukan jumlah bulannya.
        $isi = $this->actingAs($this->admin)
            ->get(route('kpi-pendamping.show', $this->profilA))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression('/labelPenuh = \[.*\];/', $isi);

        preg_match('/labelPenuh = (\[.*?\]);/', $isi, $m);
        $label = json_decode($m[1] ?? '[]', true);

        $this->assertCount(12, $label, 'Grafik harus tetap memuat 12 bulan.');
        // Label utuh (berikut tahun) tetap tersedia untuk tooltip.
        $this->assertMatchesRegularExpression('/^[A-Za-z]{3} \d{4}$/', $label[0]);
    }

    public function test_admin_dapat_membuat_target(): void
    {
        $this->actingAs($this->admin)->get(route('target-pendamping.create'))->assertOk();

        $this->actingAs($this->admin)
            ->post(route('target-pendamping.store'), $this->formTarget([
                'target_jumlah_binaan' => 20,
                'target_pendampingan' => 15,
                'catatan' => 'Target awal program.',
            ]))
            ->assertSessionHasNoErrors();

        $target = PendampingTarget::first();

        $this->assertNotNull($target);
        $this->assertSame($this->profilA->id, $target->pendamping_id);
        $this->assertSame(20, $target->target_jumlah_binaan);
        $this->assertSame($this->admin->id, $target->created_by);
    }

    public function test_admin_dapat_mengedit_target(): void
    {
        $target = $this->target(['target_kunjungan' => 10]);

        $this->actingAs($this->admin)->get(route('target-pendamping.edit', $target))->assertOk();

        $this->actingAs($this->admin)
            ->put(route('target-pendamping.update', $target), $this->formTarget(['target_kunjungan' => 25]))
            ->assertSessionHasNoErrors();

        $this->assertSame(25, $target->fresh()->target_kunjungan);
    }

    /** Satu pendamping hanya boleh punya SATU target per periode. */
    public function test_target_ganda_untuk_periode_sama_ditolak(): void
    {
        $this->actingAs($this->admin)->post(route('target-pendamping.store'), $this->formTarget());

        $this->actingAs($this->admin)
            ->post(route('target-pendamping.store'), $this->formTarget())
            ->assertSessionHasErrors('periode');

        $this->assertSame(1, PendampingTarget::count());
    }

    public function test_periode_tahunan_tidak_menyimpan_bulan(): void
    {
        $this->actingAs($this->admin)
            ->post(route('target-pendamping.store'), $this->formTarget([
                'periode' => PendampingTarget::PERIODE_TAHUNAN,
                'bulan' => 7,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertNull(PendampingTarget::first()->bulan);
    }

    /** Triwulan disimpan sebagai bulan AWAL triwulan, apa pun yang dipilih. */
    public function test_periode_triwulan_dinormalkan_ke_bulan_awal(): void
    {
        $this->actingAs($this->admin)
            ->post(route('target-pendamping.store'), $this->formTarget([
                'periode' => PendampingTarget::PERIODE_TRIWULAN,
                'bulan' => 8, // triwulan 3 -> disimpan sebagai Juli (7)
            ]))
            ->assertSessionHasNoErrors();

        $target = PendampingTarget::first();

        $this->assertSame(7, $target->bulan);
        $this->assertSame(3, $target->triwulanKe());
    }

    public function test_bulan_wajib_untuk_periode_bulanan(): void
    {
        $this->actingAs($this->admin)
            ->post(route('target-pendamping.store'), $this->formTarget(['bulan' => null]))
            ->assertSessionHasErrors('bulan');
    }

    // =====================================================================
    // OTORISASI TARGET
    // =====================================================================

    /** INTI: Pendamping TIDAK boleh membuat/mengubah target siapa pun. */
    public function test_pendamping_tidak_bisa_menyentuh_target(): void
    {
        $target = $this->target(['target_kunjungan' => 10]);

        $this->actingAs($this->pendampingA)->get(route('target-pendamping.index'))->assertForbidden();
        $this->actingAs($this->pendampingA)->get(route('target-pendamping.create'))->assertForbidden();
        $this->actingAs($this->pendampingA)->post(route('target-pendamping.store'), $this->formTarget())->assertForbidden();
        $this->actingAs($this->pendampingA)->get(route('target-pendamping.edit', $target))->assertForbidden();
        $this->actingAs($this->pendampingA)
            ->put(route('target-pendamping.update', $target), $this->formTarget(['target_kunjungan' => 1]))
            ->assertForbidden();
        $this->actingAs($this->pendampingA)->delete(route('target-pendamping.destroy', $target))->assertForbidden();

        $this->assertSame(10, $target->fresh()->target_kunjungan);
    }

    /** Termasuk membuat target untuk pendamping LAIN. */
    public function test_pendamping_tidak_bisa_membuat_target_pendamping_lain(): void
    {
        $this->actingAs($this->pendampingA)
            ->post(route('target-pendamping.store'), $this->formTarget(['pendamping_id' => $this->profilB->id]))
            ->assertForbidden();

        $this->assertSame(0, PendampingTarget::count());
    }

    public function test_role_lain_ditolak_di_modul_target(): void
    {
        foreach ([$this->pimpinan, $this->timKoperasi] as $user) {
            $this->actingAs($user)->get(route('target-pendamping.index'))->assertForbidden();
            $this->actingAs($user)->post(route('target-pendamping.store'), $this->formTarget())->assertForbidden();
        }
    }

    // =====================================================================
    // PERHITUNGAN KPI
    // =====================================================================

    /** INTI: rata-rata hanya dari indikator yang PUNYA target. */
    public function test_kpi_hanya_menghitung_indikator_yang_ditargetkan(): void
    {
        $peserta = $this->peserta('Binaan A1', $this->profilA->id);
        $this->peserta('Binaan A2', $this->profilA->id);

        // 2 kunjungan dari target 4 = 50%; 2 binaan dari target 2 = 100%.
        $this->catatan($peserta, 'Kunjungan Usaha');
        $this->catatan($peserta, 'Kunjungan Usaha');

        $this->target([
            'target_jumlah_binaan' => 2,
            'target_kunjungan' => 4,
            // Sisanya SENGAJA kosong — tidak boleh ikut dihitung.
        ]);

        $rapor = $this->rapor($this->profilA);

        $this->assertSame(2, $rapor['jumlah_indikator']);
        $this->assertSame(75, $rapor['kpi']); // (100 + 50) / 2
        $this->assertFalse($rapor['indikator']['target_pelatihan']['dihitung']);
        $this->assertNull($rapor['indikator']['target_pelatihan']['target']);
    }

    /** Target nol diperlakukan sama dengan tidak ditargetkan. */
    public function test_target_nol_tidak_menyebabkan_pembagian_dengan_nol(): void
    {
        $this->target(['target_kunjungan' => 0, 'target_pendampingan' => 5]);

        $rapor = $this->rapor($this->profilA);

        $this->assertFalse($rapor['indikator']['target_kunjungan']['dihitung']);
        $this->assertSame(1, $rapor['jumlah_indikator']);
        $this->assertIsInt($rapor['kpi']);
    }

    /** Tanpa target sama sekali -> memakai target default sistem. */
    public function test_tanpa_target_khusus_memakai_target_default(): void
    {
        $rapor = $this->rapor($this->profilA);

        $this->assertTrue($rapor['pakai_default']);
        $this->assertSame(
            (int) config('cahayaos.target_default.kunjungan'),
            $rapor['indikator']['target_kunjungan']['target']
        );
    }

    /** Target individual SELALU menang atas default. */
    public function test_target_individual_mengalahkan_default(): void
    {
        $this->target(['target_kunjungan' => 3]);

        $rapor = $this->rapor($this->profilA);

        $this->assertFalse($rapor['pakai_default']);
        $this->assertSame(3, $rapor['indikator']['target_kunjungan']['target']);
    }

    public function test_target_default_bisa_diubah_admin(): void
    {
        SystemSetting::current()->update(['target_default_kunjungan' => 99]);

        $this->assertSame(99, $this->rapor($this->profilA)['indikator']['target_kunjungan']['target']);
    }

    /** INTI: target yang DIARSIPKAN tidak ikut dihitung. */
    public function test_target_terhapus_tidak_dihitung(): void
    {
        $target = $this->target(['target_kunjungan' => 3]);
        $this->assertFalse($this->rapor($this->profilA)['pakai_default']);

        $target->delete();

        // Kembali memakai default, bukan memakai target yang sudah diarsipkan.
        $this->assertTrue($this->rapor($this->profilA)['pakai_default']);
    }

    /** Dan catatan lapangan yang diarsipkan juga tidak ikut dihitung. */
    public function test_catatan_terhapus_tidak_dihitung_ke_realisasi(): void
    {
        $peserta = $this->peserta('Binaan A1', $this->profilA->id);
        $catatan = $this->catatan($peserta, 'Kunjungan Usaha');

        $this->target(['target_kunjungan' => 2]);
        $this->assertSame(1, $this->rapor($this->profilA)['indikator']['target_kunjungan']['aktual']);

        $catatan->delete();

        $this->assertSame(0, $this->rapor($this->profilA)['indikator']['target_kunjungan']['aktual']);
    }

    /** Melampaui target dibatasi 100% supaya tidak menutupi indikator lain. */
    public function test_progress_dibatasi_seratus_persen(): void
    {
        $peserta = $this->peserta('Binaan A1', $this->profilA->id);
        for ($i = 0; $i < 10; $i++) {
            $this->catatan($peserta, 'Kunjungan Usaha');
        }

        $this->target(['target_kunjungan' => 2]);

        $this->assertSame(100, $this->rapor($this->profilA)['indikator']['target_kunjungan']['progress']);
    }

    /** Realisasi hanya menghitung aktivitas DALAM periode. */
    public function test_realisasi_hanya_menghitung_periode_yang_diminta(): void
    {
        $peserta = $this->peserta('Binaan A1', $this->profilA->id);

        PendampinganRecord::create([
            'participant_id' => $peserta->id, 'pendamping_id' => $this->profilA->id,
            'tanggal' => now()->subMonths(3)->toDateString(),
            'jenis_kegiatan' => 'Kunjungan Usaha', 'materi' => 'Bulan lalu',
        ]);

        $this->target(['target_kunjungan' => 2]);

        $this->assertSame(0, $this->rapor($this->profilA)['indikator']['target_kunjungan']['aktual']);
    }

    /** Follow up dihitung dari catatan yang PUNYA jadwal tindak lanjut. */
    public function test_follow_up_dihitung_dari_catatan_berjadwal(): void
    {
        $peserta = $this->peserta('Binaan A1', $this->profilA->id);

        $this->catatan($peserta, 'Konsultasi', now()->addWeek()->toDateString());
        $this->catatan($peserta, 'Konsultasi'); // tanpa jadwal — tidak dihitung

        $this->target(['target_follow_up' => 2]);

        $this->assertSame(1, $this->rapor($this->profilA)['indikator']['target_follow_up']['aktual']);
    }

    // =====================================================================
    // STATUS KINERJA
    // =====================================================================

    public function test_status_kinerja_sesuai_ambang(): void
    {
        $kpi = $this->kpi();

        foreach ([
            100 => KpiService::SANGAT_BAIK,
            90 => KpiService::SANGAT_BAIK,
            89 => KpiService::BAIK,
            75 => KpiService::BAIK,
            74 => KpiService::PERLU_PERHATIAN,
            50 => KpiService::PERLU_PERHATIAN,
            49 => KpiService::RENDAH,
            0 => KpiService::RENDAH,
        ] as $nilai => $status) {
            $this->assertSame($status, $kpi->status($nilai), "KPI $nilai seharusnya $status");
        }
    }

    // =====================================================================
    // BEBAN PENDAMPING
    // =====================================================================

    public function test_kategori_beban_sesuai_ambang(): void
    {
        $kpi = $this->kpi();

        foreach ([5 => 'Ringan', 10 => 'Ringan', 15 => 'Normal', 20 => 'Normal',
            25 => 'Tinggi', 30 => 'Tinggi', 31 => 'Sangat Tinggi'] as $jumlah => $kategori) {
            $this->assertSame($kategori, $kpi->beban($jumlah)['kategori'], "$jumlah binaan seharusnya $kategori");
        }
    }

    public function test_ambang_beban_bisa_diubah_admin(): void
    {
        SystemSetting::current()->update([
            'beban_ringan_maks' => 3, 'beban_normal_maks' => 6, 'beban_tinggi_maks' => 9,
        ]);

        $this->assertSame('Normal', $this->kpi()->beban(5)['kategori']);
        $this->assertSame('Sangat Tinggi', $this->kpi()->beban(12)['kategori']);
    }

    /** Ambang terbalik dirapikan, tidak membuat kategori kacau tanpa pesan. */
    public function test_ambang_beban_terbalik_dirapikan(): void
    {
        SystemSetting::current()->update([
            'beban_ringan_maks' => 30, 'beban_normal_maks' => 10, 'beban_tinggi_maks' => 5,
        ]);

        $ambang = $this->kpi()->ambangBeban();

        $this->assertGreaterThan($ambang['ringan'], $ambang['normal']);
        $this->assertGreaterThan($ambang['normal'], $ambang['tinggi']);
    }

    // =====================================================================
    // KEAKTIFAN PESERTA
    // =====================================================================

    public function test_keaktifan_peserta_sesuai_ambang(): void
    {
        $kpi = $this->kpi();

        $this->assertSame('Aktif', $kpi->keaktifanPeserta(now()->subDays(10))['status']);
        $this->assertSame('Perlu Follow Up', $kpi->keaktifanPeserta(now()->subDays(45))['status']);
        $this->assertSame('Tidak Aktif', $kpi->keaktifanPeserta(now()->subDays(90))['status']);
        $this->assertSame('Tidak Aktif', $kpi->keaktifanPeserta(null)['status']);
    }

    public function test_ambang_keaktifan_bisa_diubah_admin(): void
    {
        SystemSetting::current()->update([
            'peserta_aktif_hari' => 7, 'peserta_perlu_follow_up_hari' => 14,
        ]);

        $this->assertSame('Perlu Follow Up', $this->kpi()->keaktifanPeserta(now()->subDays(10))['status']);
    }

    // =====================================================================
    // HALAMAN KPI — otorisasi & scope
    // =====================================================================

    public function test_admin_melihat_seluruh_pendamping_di_halaman_kpi(): void
    {
        $this->actingAs($this->admin)
            ->get(route('kpi-pendamping.index'))
            ->assertOk()
            ->assertSee('Rosa Linda')
            ->assertSee('Pendamping B');
    }

    /** INTI: Pendamping hanya melihat dirinya sendiri. */
    public function test_pendamping_hanya_melihat_kpi_sendiri(): void
    {
        $this->actingAs($this->pendampingA)
            ->get(route('kpi-pendamping.index'))
            ->assertOk()
            ->assertSee('Rosa Linda')
            ->assertDontSee('Pendamping B');
    }

    /** Dan filter dari URL tidak bisa dipakai mengintip. */
    public function test_pendamping_tidak_bisa_mengintip_lewat_filter_url(): void
    {
        $this->actingAs($this->pendampingA)
            ->get(route('kpi-pendamping.index', ['pendamping_id' => $this->profilB->id]))
            ->assertOk()
            ->assertSee('Rosa Linda')
            ->assertDontSee('Pendamping B');
    }

    public function test_pendamping_tidak_bisa_membuka_detail_kpi_pendamping_lain(): void
    {
        $this->actingAs($this->pendampingA)
            ->get(route('kpi-pendamping.show', $this->profilB))
            ->assertForbidden();

        $this->actingAs($this->pendampingA)
            ->get(route('kpi-pendamping.show', $this->profilA))
            ->assertOk();
    }

    public function test_role_lain_ditolak_di_halaman_kpi(): void
    {
        foreach ([$this->pimpinan, $this->timKoperasi] as $user) {
            $this->actingAs($user)->get(route('kpi-pendamping.index'))->assertForbidden();
            $this->actingAs($user)->get(route('kpi-pendamping.show', $this->profilA))->assertForbidden();
            $this->actingAs($user)->get(route('kpi-pendamping.export'))->assertForbidden();
            $this->actingAs($user)->get(route('kpi-pendamping.export-pdf'))->assertForbidden();
        }

        $this->actingAs($this->user('pendamping', 'lepas@kpi.test'))
            ->get(route('kpi-pendamping.index'))->assertForbidden();
    }

    // =====================================================================
    // EXPORT
    // =====================================================================

    public function test_export_kpi_mengikuti_scope(): void
    {
        $isi = $this->actingAs($this->pendampingA)
            ->get(route('kpi-pendamping.export'))->streamedContent();

        $this->assertStringContainsString('Rosa Linda', $isi);
        $this->assertStringNotContainsString('Pendamping B', $isi);
        $this->assertStringContainsString('LAPORAN KINERJA PENDAMPING CAHAYA PRENEUR', $isi);

        $isiAdmin = $this->actingAs($this->admin)
            ->get(route('kpi-pendamping.export'))->streamedContent();

        $this->assertStringContainsString('Pendamping B', $isiAdmin);
    }

    public function test_export_pdf_kpi_berhasil(): void
    {
        $respons = $this->actingAs($this->admin)->get(route('kpi-pendamping.export-pdf'));

        $respons->assertOk();
        $this->assertStringContainsString('application/pdf', $respons->headers->get('content-type'));
    }

    // =====================================================================
    // ACTIVITY LOG
    // =====================================================================

    public function test_perubahan_target_tercatat_di_activity_log(): void
    {
        $this->actingAs($this->admin)->post(route('target-pendamping.store'), $this->formTarget());
        $target = PendampingTarget::first();

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'target_created', 'model_type' => 'PendampingTarget', 'model_id' => $target->id,
        ]);

        $this->actingAs($this->admin)
            ->put(route('target-pendamping.update', $target), $this->formTarget(['target_kunjungan' => 5]));
        $this->assertDatabaseHas('activity_logs', ['action' => 'target_updated', 'model_id' => $target->id]);

        $this->actingAs($this->admin)->delete(route('target-pendamping.destroy', $target));
        $this->assertDatabaseHas('activity_logs', ['action' => 'target_deleted', 'model_id' => $target->id]);


        // Tidak ada sistem log kedua.
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasTable('kpi_logs'));
    }

    // =====================================================================
    // PERIODE
    // =====================================================================

    public function test_rentang_periode_dihitung_benar(): void
    {
        $bulanan = $this->target(['periode' => PendampingTarget::PERIODE_BULANAN, 'tahun' => 2026, 'bulan' => 3]);
        [$awal, $akhir] = $bulanan->rentang();
        $this->assertSame('2026-03-01', $awal->toDateString());
        $this->assertSame('2026-03-31', $akhir->toDateString());

        $triwulan = new PendampingTarget(['periode' => PendampingTarget::PERIODE_TRIWULAN, 'tahun' => 2026, 'bulan' => 4]);
        [$awal, $akhir] = $triwulan->rentang();
        $this->assertSame('2026-04-01', $awal->toDateString());
        $this->assertSame('2026-06-30', $akhir->toDateString());

        $tahunan = new PendampingTarget(['periode' => PendampingTarget::PERIODE_TAHUNAN, 'tahun' => 2026]);
        [$awal, $akhir] = $tahunan->rentang();
        $this->assertSame('2026-01-01', $awal->toDateString());
        $this->assertSame('2026-12-31', $akhir->toDateString());
    }

    /** Bulanan menang atas Triwulan, Triwulan menang atas Tahunan. */
    public function test_target_lebih_spesifik_diutamakan(): void
    {
        $this->target(['periode' => PendampingTarget::PERIODE_TAHUNAN, 'bulan' => null, 'target_kunjungan' => 1]);
        $this->target([
            'periode' => PendampingTarget::PERIODE_TRIWULAN,
            'bulan' => ((int) ceil(now()->month / 3) - 1) * 3 + 1,
            'target_kunjungan' => 2,
        ]);
        $this->target(['periode' => PendampingTarget::PERIODE_BULANAN, 'target_kunjungan' => 3]);

        $berlaku = PendampingTarget::berlakuUntuk($this->profilA->id, Carbon::now());

        $this->assertSame(PendampingTarget::PERIODE_BULANAN, $berlaku->periode);
        $this->assertSame(3, $berlaku->target_kunjungan);
    }
}
