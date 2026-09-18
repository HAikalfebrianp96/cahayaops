<?php

namespace Tests\Feature\Uat;

use App\Models\KoperasiMember;
use App\Models\MemberSaving;
use App\Models\Participant;
use App\Models\ParticipantMeasurement;
use App\Models\Pendamping;
use App\Models\PendampingTarget;
use App\Models\ShuPeriod;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\FollowUpService;
use App\Services\KpiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * URUTAN MENYIAPKAN SISTEM — DITELUSURI LEWAT FORM SUNGGUHAN.
 *
 * =====================================================================
 * KENAPA TEST INI ADA
 * =====================================================================
 *
 * Seluruh test lain memeriksa JALUR BACA: halaman terbuka, angkanya benar,
 * angkanya sepakat antar halaman. Data untuk semua itu dibuat langsung lewat
 * Model::create() di dalam test.
 *
 * Yang tidak pernah ditelusuri: apakah seseorang benar-benar bisa MENGISI
 * semua itu lewat formnya. Padahal di situlah hambatan yang sesungguhnya —
 * validasi yang menolak masukan wajar, kolom wajib yang tidak terduga,
 * penyimpanan yang tidak nyantol.
 *
 * Empat langkah di bawah adalah urutan yang memang harus dikerjakan sebelum
 * sistem ini berguna: ambang, target, kondisi awal, lalu SHU. Test ini
 * mengerjakannya persis seperti manusia — POST ke rute yang sama, lewat
 * validasi yang sama, dengan akun yang sama — lalu memastikan hasilnya
 * benar-benar berubah di layar berikutnya.
 */
class AlurPenyiapanSistemTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $timKoperasi;
    private Pendamping $profil;
    private Participant $peserta;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-06-20 09:00', 'Asia/Jakarta'));

        $this->admin = User::create([
            'name' => 'Admin Uji', 'email' => 'admin@siap.test',
            'password' => 'password123', 'role' => 'admin', 'is_active' => true,
        ]);

        $this->timKoperasi = User::create([
            'name' => 'Tim Koperasi', 'email' => 'koperasi@siap.test',
            'password' => 'password123', 'role' => User::ROLE_TIM_KOPERASI, 'is_active' => true,
        ]);

        $this->profil = Pendamping::create([
            'nama' => 'Rosa Linda', 'nomor_hp' => '081200000001',
            'email' => 'rosa@siap.test', 'status' => 'Aktif',
        ]);

        $this->peserta = Participant::create([
            'nama_peserta' => 'Peserta Uji', 'nomor_hp' => '081200000002',
            'alamat' => 'Jl. Uji', 'nama_usaha' => 'Usaha Uji',
            'kategori_usaha' => 'Jasa',
            'tanggal_mulai_program' => '2026-01-10',
            'status' => 'Aktif', 'pendamping_id' => $this->profil->id,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    // =================================================================
    // LANGKAH 1 — MENETAPKAN AMBANG
    // =================================================================

    public function test_langkah_1_admin_dapat_menetapkan_ambang_lewat_pengaturan(): void
    {
        $this->actingAs($this->admin)
            ->post(route('pengaturan.update'), [
                'nama_sistem' => 'CahayaOS',
                'nama_lembaga' => 'YBM PLN UP3 Bengkulu',
                'nama_program' => 'Cahaya Preneur',
                'followup_kuning_hari' => 14,
                'followup_merah_hari' => 21,
                'peserta_aktif_hari' => 21,
                'peserta_perlu_follow_up_hari' => 45,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        // Tersimpan, dan yang BERLAKU memang yang baru — bukan sekadar
        // tersimpan di kolom lalu diabaikan pembacanya.
        app()->forgetInstance('cahayaos.system_setting');

        $setting = SystemSetting::current();
        $this->assertSame(14, (int) $setting->followup_kuning_hari);
        $this->assertSame(21, (int) $setting->followup_merah_hari);

        $ambang = app(FollowUpService::class)->ambang();
        $this->assertSame(14, $ambang['kuning']);
        $this->assertSame(21, $ambang['merah']);
    }

    // =================================================================
    // LANGKAH 2 — MENETAPKAN TARGET
    // =================================================================

    public function test_langkah_2_admin_dapat_menetapkan_target_pendamping(): void
    {
        $this->actingAs($this->admin)
            ->post(route('target-pendamping.store'), [
                'pendamping_id' => $this->profil->id,
                'periode' => PendampingTarget::PERIODE_BULANAN,
                'tahun' => 2026,
                'bulan' => 6,
                'target_pendampingan' => 12,
                'target_kunjungan' => 6,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('target-pendamping.index'));

        $this->assertDatabaseHas('pendamping_targets', [
            'pendamping_id' => $this->profil->id,
            'target_pendampingan' => 12,
        ]);

        // Halaman KPI harus BERHENTI menyebut "target bawaan sistem".
        $rapor = $this->actingAs($this->admin)
            ->get(route('kpi-pendamping.show', $this->profil))
            ->assertOk()
            ->assertDontSee('Target bawaan sistem')
            ->viewData('rapor');

        $this->assertFalse($rapor['pakai_default']);
        $this->assertSame(12, $rapor['indikator']['target_pendampingan']['target']);
    }

    /**
     * Indikator yang dikosongkan tetap "tidak ditargetkan".
     *
     * Ini janji yang tertulis di formnya sendiri, dan paling mudah
     * diingkari tanpa ada yang sadar.
     */
    public function test_langkah_2_indikator_yang_dikosongkan_tidak_ikut_dihitung(): void
    {
        $this->actingAs($this->admin)
            ->post(route('target-pendamping.store'), [
                'pendamping_id' => $this->profil->id,
                'periode' => PendampingTarget::PERIODE_BULANAN,
                'tahun' => 2026,
                'bulan' => 6,
                'target_pendampingan' => 12,
            ])
            ->assertSessionHasNoErrors();

        $rapor = $this->actingAs($this->admin)
            ->get(route('kpi-pendamping.show', $this->profil))
            ->assertOk()
            ->viewData('rapor');

        $this->assertTrue($rapor['indikator']['target_pendampingan']['dihitung']);
        $this->assertFalse($rapor['indikator']['target_legalitas']['dihitung']);
        $this->assertFalse($rapor['indikator']['target_pelatihan']['dihitung']);
    }

    // =================================================================
    // LANGKAH 3 — MENCATAT KONDISI AWAL
    // =================================================================

    public function test_langkah_3_pendamping_dapat_mencatat_kondisi_awal(): void
    {
        $pendamping = User::create([
            'name' => 'Rosa Akun', 'email' => 'rosa.akun@siap.test',
            'password' => 'password123', 'role' => 'pendamping',
            'pendamping_id' => $this->profil->id, 'is_active' => true,
        ]);

        $this->actingAs($pendamping)
            ->post(route('perkembangan.store', $this->peserta), [
                'jenis' => ParticipantMeasurement::BASELINE,
                'tanggal' => '2026-06-10',
                'omzet_bulanan' => 3000000,
                'jumlah_tenaga_kerja' => 2,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('perkembangan.index', $this->peserta));

        $this->assertDatabaseHas('participant_measurements', [
            'participant_id' => $this->peserta->id,
            'jenis' => ParticipantMeasurement::BASELINE,
        ]);

        // Halaman peserta harus ikut berubah: tahap pertama sudah tercatat,
        // dan tombol "Catat Kondisi Awal" tidak lagi ditawarkan.
        $this->actingAs($pendamping)
            ->get(route('participants.show', $this->peserta))
            ->assertOk()
            ->assertSee('Baseline')
            ->assertDontSee('Mulai dari');

        $tindakan = app(FollowUpService::class)->tindakan(
            ['terakhir' => now()],
            omzetBulanIniSudahDilaporkan: true,
            punyaBaseline: true,
        );

        $this->assertNotContains('Catat Kondisi Awal', array_column($tindakan, 'label'));
    }

    // =================================================================
    // LANGKAH 4 — PENETAPAN SHU
    // =================================================================

    public function test_langkah_4_tim_koperasi_dapat_menetapkan_dan_menyusun_shu(): void
    {
        $anggota = [];

        foreach ([['Ani', 1234567], ['Budi', 765433], ['Cita', 999999]] as [$nama, $simpanan]) {
            $m = KoperasiMember::create([
                'nama' => $nama,
                'nomor_hp' => '08120000' . strlen($nama) . rand(10, 99),
                'tanggal_bergabung' => '2025-01-10',
                'status' => 'Aktif',
            ]);

            MemberSaving::create([
                'koperasi_member_id' => $m->id,
                'jenis_simpanan' => 'Simpanan Pokok',
                'nominal' => $simpanan,
                'tanggal' => '2025-02-01',
            ]);

            $anggota[] = $m;
        }

        $this->actingAs($this->timKoperasi)
            ->post(route('koperasi.shu.store'), [
                'tahun' => 2026,
                'tanggal_penetapan' => '2026-06-20',
                'sumber' => 'manual',
                'total_shu' => 7777777,
                'komponen' => ShuPeriod::KOMPONEN_USULAN,
            ])
            ->assertSessionHasNoErrors();

        $periode = ShuPeriod::where('tahun', 2026)->firstOrFail();

        $this->actingAs($this->timKoperasi)
            ->post(route('koperasi.shu.susun-ulang', $periode))
            ->assertSessionHasNoErrors();

        $periode->refresh();

        // ============================================================
        // YANG DIBANDINGKAN HANYA POOL YANG PUNYA DASAR
        //
        // KOMPONEN_USULAN memuat DUA komponen untuk anggota: jasa modal
        // (dasarnya simpanan) dan jasa usaha (dasarnya transaksi). Anggota
        // di sini hanya punya simpanan.
        //
        // Komponen tanpa dasar memang TIDAK dibagikan sama sekali — bukan
        // dibagi rata. Itu perilaku yang benar: membagi jasa usaha kepada
        // anggota yang tidak bertransaksi berarti mengarang dasarnya.
        //
        // Percobaan pertama test ini menjumlahkan kedua pool lalu gagal, dan
        // sempat terlihat seperti separuh uang hilang. Yang keliru
        // assertion-nya, bukan aplikasinya.
        // ============================================================
        $nominal = $periode->nominalKomponenSemua();
        $poolBerdasar = 0;

        foreach ($periode->komponen as $i => $k) {
            if (! empty($k['ke_anggota']) && ($k['dasar'] ?? null) === ShuPeriod::DASAR_SIMPANAN) {
                $poolBerdasar += $nominal[$i];
            }
        }

        $this->assertGreaterThan(0, $periode->allocations()->count(), 'Tidak ada alokasi yang tersusun.');

        $this->assertSame(
            $poolBerdasar,
            (int) $periode->allocations()->sum('total'),
            'Jumlah yang dibagikan tidak sama dengan pool jasa modal.'
        );
    }

    /**
     * Pool yang TIDAK terbagikan wajib diperingatkan.
     *
     * Komponen tanpa dasar memang tidak dibagikan — itu benar. Yang tidak
     * boleh adalah uang berhenti di situ tanpa ada yang tahu. Dua puluh lima
     * persen SHU yang tidak sampai ke siapa pun adalah hal yang harus dilihat
     * pengurus SEBELUM penetapan disahkan, bukan ditemukan setahun kemudian.
     */
    public function test_pool_yang_tidak_terbagikan_diperingatkan_sebelum_disahkan(): void
    {
        $anggota = KoperasiMember::create([
            'nama' => 'Ani', 'nomor_hp' => '081200000009',
            'tanggal_bergabung' => '2025-01-10', 'status' => 'Aktif',
        ]);

        MemberSaving::create([
            'koperasi_member_id' => $anggota->id,
            'jenis_simpanan' => 'Simpanan Pokok',
            'nominal' => 1000000,
            'tanggal' => '2025-02-01',
        ]);

        $this->actingAs($this->timKoperasi)
            ->post(route('koperasi.shu.store'), [
                'tahun' => 2026,
                'tanggal_penetapan' => '2026-06-20',
                'sumber' => 'manual',
                'total_shu' => 7777777,
                'komponen' => ShuPeriod::KOMPONEN_USULAN,
            ])
            ->assertSessionHasNoErrors();

        $periode = ShuPeriod::where('tahun', 2026)->firstOrFail();

        $peringatan = app(\App\Services\ShuService::class)->peringatan($periode);

        $this->assertNotEmpty($peringatan, 'Jasa usaha tidak terbagikan, tapi tidak ada peringatan.');

        $this->assertStringContainsString(
            'jasa usaha tidak dapat dibagikan',
            implode(' ', $peringatan)
        );

        // Peringatannya harus benar-benar sampai ke layar pengurus.
        $this->actingAs($this->timKoperasi)
            ->get(route('koperasi.shu.show', $periode))
            ->assertOk()
            ->assertSee('jasa usaha tidak dapat dibagikan');
    }

    // =================================================================
    // SESUDAH KEEMPATNYA: SISTEM BERHENTI MEMPERINGATKAN
    // =================================================================

    /**
     * Peringatan yang sudah dikerjakan harus hilang.
     *
     * Kalau tetap tampil setelah diselesaikan, peringatan berikutnya ikut
     * diabaikan orang.
     */
    public function test_sesudah_disiapkan_peringatan_penyiapan_menghilang(): void
    {
        $this->actingAs($this->admin)->post(route('target-pendamping.store'), [
            'pendamping_id' => $this->profil->id,
            'periode' => PendampingTarget::PERIODE_BULANAN,
            'tahun' => 2026,
            'bulan' => 6,
            'target_pendampingan' => 12,
        ])->assertSessionHasNoErrors();

        $this->actingAs($this->admin)
            ->get(route('kpi-pendamping.index'))
            ->assertOk()
            ->assertDontSee('Belum ada target yang ditetapkan untuk periode ini');

        $this->assertFalse(
            app(KpiService::class)
                ->untukSatu($this->profil, now()->startOfMonth(), now()->endOfMonth())['pakai_default']
        );
    }
}
