<?php

namespace Tests\Feature\Uat;

use App\Models\LegalitasRecord;
use App\Models\OmzetReport;
use App\Models\Participant;
use App\Models\Pendamping;
use App\Models\PendampinganRecord;
use App\Models\PendampingTarget;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SATU CATATAN, BANYAK HALAMAN — ANGKANYA HARUS SEPAKAT.
 *
 * =====================================================================
 * YANG BELUM PERNAH DIUJI
 * =====================================================================
 *
 * Sebelas berkas UAT yang sudah ada menguji tiap modul sendiri-sendiri:
 * apakah halamannya terbuka, apakah hak aksesnya benar, apakah satu alur
 * bisa diselesaikan. Tidak satu pun menanyakan hal yang berbeda ini:
 *
 *   Kalau satu catatan pendampingan dimasukkan sekali, apakah SELURUH
 *   halaman yang membacanya menyebut angka yang sama?
 *
 * Itu justru tempat penyimpangan diam bersembunyi. Setiap halaman punya
 * jalan sendiri menuju angkanya — KPI lewat KpiService, Tugas Saya lewat
 * FollowUpService, dashboard lewat controllernya, laporan lewat query
 * tersendiri. Selama tidak ada yang membandingkan, satu halaman bisa
 * menghitung dengan cara yang berbeda selama berbulan-bulan tanpa terlihat
 * salah: tiap halaman tampak masuk akal kalau dibaca sendirian.
 *
 * Prinsip yang dijaga: satu input, banyak keluaran otomatis — dan keluaran
 * itu tidak boleh saling bertentangan.
 */
class AngkaAntarHalamanSepakatTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $pendamping;
    private Pendamping $profil;
    private Participant $peserta;

    /** Jumlah catatan yang sengaja dibuat, dipakai membandingkan seluruh halaman. */
    private const JUMLAH_KUNJUNGAN = 2;
    private const JUMLAH_KONSULTASI = 3;
    private const JUMLAH_SELURUH_CATATAN = self::JUMLAH_KUNJUNGAN + self::JUMLAH_KONSULTASI;

    protected function setUp(): void
    {
        parent::setUp();

        // ============================================================
        // WAKTU DIPATOK — dan ini bukan kerapian belaka.
        //
        // KPI dihitung PER BULAN. Percobaan pertama test ini memakai
        // now()->subDays(21), yang jatuh ke bulan sebelumnya kalau
        // dijalankan pada paruh pertama bulan — dua kunjungan hilang dari
        // hitungan KPI, dan test-nya gagal seolah-olah aplikasinya keliru.
        //
        // Dengan tanggal dipatok, seluruh catatan pasti berada di bulan
        // yang sama dan hasilnya tidak bergantung kapan test dijalankan.
        // ============================================================
        \Illuminate\Support\Carbon::setTestNow(
            \Illuminate\Support\Carbon::parse('2026-06-20 09:00', 'Asia/Jakarta')
        );

        $this->profil = Pendamping::create([
            'nama' => 'Rosa Linda', 'nomor_hp' => '081200000001',
            'email' => 'rosa@sepakat.test', 'status' => 'Aktif',
        ]);

        $this->admin = User::create([
            'name' => 'Admin Uji', 'email' => 'admin@sepakat.test',
            'password' => 'password123', 'role' => 'admin', 'is_active' => true,
        ]);

        $this->pendamping = User::create([
            'name' => 'Rosa Linda', 'email' => 'rosa.akun@sepakat.test',
            'password' => 'password123', 'role' => 'pendamping',
            'pendamping_id' => $this->profil->id, 'is_active' => true,
        ]);

        $this->peserta = Participant::create([
            'nama_peserta' => 'Peserta Uji', 'nomor_hp' => '081200000002',
            'alamat' => 'Jl. Uji', 'nama_usaha' => 'Usaha Uji',
            'kategori_usaha' => 'Jasa',
            'tanggal_mulai_program' => now()->startOfYear()->toDateString(),
            'status' => 'Aktif', 'pendamping_id' => $this->profil->id,
        ]);

        // --- Satu musim kerja, dimasukkan SEKALI ---
        foreach (range(1, self::JUMLAH_KUNJUNGAN) as $i) {
            $this->catat(PendampinganRecord::KUNJUNGAN_USAHA, now()->startOfMonth()->addDays($i));
        }

        foreach (range(1, self::JUMLAH_KONSULTASI) as $i) {
            $this->catat(PendampinganRecord::KONSULTASI, now()->startOfMonth()->addDays(9 + $i));
        }

        OmzetReport::create([
            'participant_id' => $this->peserta->id,
            'periode_bulan' => now()->month,
            'periode_tahun' => now()->year,
            'omzet' => 4500000,
            'hpp' => 2000000,
        ]);

        LegalitasRecord::create([
            'participant_id' => $this->peserta->id,
            'jenis_dokumen' => 'NIB',
            'status' => LegalitasRecord::STATUS_SUDAH_ADA,
            'tanggal_terbit' => now()->subMonths(2)->toDateString(),
        ]);

        PendampingTarget::create([
            'pendamping_id' => $this->profil->id,
            'periode' => PendampingTarget::PERIODE_BULANAN,
            'tahun' => now()->year,
            'bulan' => now()->month,
            'target_pendampingan' => 10,
            'target_kunjungan' => 10,
        ]);
    }

    protected function tearDown(): void
    {
        \Illuminate\Support\Carbon::setTestNow();

        parent::tearDown();
    }

    private function catat(string $jenis, $tanggal): void
    {
        PendampinganRecord::create([
            'participant_id' => $this->peserta->id,
            'pendamping_id' => $this->profil->id,
            'tanggal' => $tanggal->toDateString(),
            'jenis_kegiatan' => $jenis,
            'materi' => 'Materi uji',
        ]);
    }

    // =================================================================
    // KPI vs DASHBOARD vs LAPORAN
    // =================================================================

    /**
     * Angka pendampingan bulan ini harus sama di tiga halaman yang
     * menghitungnya lewat jalan yang berbeda-beda.
     */
    public function test_jumlah_pendampingan_sama_di_kpi_dashboard_dan_laporan(): void
    {
        $rapor = $this->actingAs($this->admin)
            ->get(route('kpi-pendamping.show', $this->profil))
            ->assertOk()
            ->viewData('rapor');

        $dariKpi = $rapor['indikator']['target_pendampingan']['aktual'];

        $statsDashboard = $this->actingAs($this->pendamping)
            ->get(route('dashboard'))
            ->assertOk()
            ->viewData('stats');

        $baris = $this->actingAs($this->admin)
            ->get(route('laporan.pendamping'))
            ->assertOk()
            ->viewData('baris');

        $dariLaporan = collect($baris)
            ->firstWhere(fn ($b) => $b['pendamping']->id === $this->profil->id);

        $this->assertSame(
            self::JUMLAH_SELURUH_CATATAN,
            $dariKpi,
            'KPI menghitung jumlah catatan yang berbeda dari yang dimasukkan.'
        );

        $this->assertSame(
            $dariKpi,
            $statsDashboard['pendampingan_bulan_ini'],
            'Dashboard Pendamping tidak sepakat dengan halaman KPI.'
        );

        $this->assertSame(
            $dariKpi,
            $dariLaporan['pendampingan'],
            'Laporan kinerja tidak sepakat dengan halaman KPI.'
        );
    }

    /**
     * Kunjungan usaha disaring khusus, jadi paling mudah menyimpang.
     */
    public function test_jumlah_kunjungan_sama_di_kpi_dashboard_dan_laporan(): void
    {
        $rapor = $this->actingAs($this->admin)
            ->get(route('kpi-pendamping.show', $this->profil))
            ->assertOk()
            ->viewData('rapor');

        $statsDashboard = $this->actingAs($this->pendamping)
            ->get(route('dashboard'))
            ->assertOk()
            ->viewData('stats');

        $baris = collect($this->actingAs($this->admin)
            ->get(route('laporan.pendamping'))
            ->assertOk()
            ->viewData('baris'))
            ->firstWhere(fn ($b) => $b['pendamping']->id === $this->profil->id);

        $this->assertSame(self::JUMLAH_KUNJUNGAN, $rapor['indikator']['target_kunjungan']['aktual']);
        $this->assertSame(self::JUMLAH_KUNJUNGAN, $statsDashboard['kunjungan_bulan_ini']);
        $this->assertSame(self::JUMLAH_KUNJUNGAN, $baris['kunjungan']);
    }

    // =================================================================
    // TUGAS SAYA vs PRIORITAS
    // =================================================================

    /**
     * Peserta yang sama harus punya keadaan yang sama di kedua daftar.
     *
     * Keduanya menjawab pertanyaan yang sama dan sejak penggabungan memang
     * membaca dari satu sumber — test ini menjaga agar tetap begitu.
     */
    public function test_tugas_saya_dan_prioritas_sepakat_soal_peserta_yang_sama(): void
    {
        $dariTugas = collect($this->actingAs($this->pendamping)
            ->get(route('follow-up.index'))->assertOk()->viewData('kelompok'))
            ->flatten(1)
            ->firstWhere(fn ($b) => $b['peserta']->id === $this->peserta->id);

        $dariPrioritas = collect($this->actingAs($this->pendamping)
            ->get(route('prioritas.index'))->assertOk()->viewData('kelompok'))
            ->flatten(1)
            ->firstWhere(fn ($b) => $b['peserta']->id === $this->peserta->id);

        $this->assertNotNull($dariTugas, 'Peserta hilang dari Tugas Saya.');
        $this->assertNotNull($dariPrioritas, 'Peserta hilang dari Prioritas.');

        $this->assertSame(
            array_column($dariTugas['tindakan'], 'label'),
            array_column($dariPrioritas['tindakan'], 'label'),
            'Tindakan yang ditawarkan berbeda antara kedua daftar.'
        );

        // Omzet bulan ini SUDAH dilaporkan di setUp, jadi kedua daftar
        // sama-sama tidak boleh lagi menawarkan "Update Omzet".
        $this->assertNotContains('Update Omzet', array_column($dariTugas['tindakan'], 'label'));
    }

    // =================================================================
    // OMZET: MONITORING vs LAPORAN vs DASHBOARD
    // =================================================================

    /**
     * Satu laporan omzet, tiga halaman yang menjumlahkannya sendiri-sendiri.
     */
    public function test_total_omzet_sama_di_monitoring_laporan_dan_dashboard(): void
    {
        $ringkasMonitoring = $this->actingAs($this->admin)
            ->get(route('omzet.index'))->assertOk()->viewData('summary');

        // laporan.omzet mengekspos totalOmzet langsung, bukan di dalam
        // sebuah array 'ringkasan'.
        $totalLaporan = $this->actingAs($this->admin)
            ->get(route('laporan.omzet'))->assertOk()->viewData('totalOmzet');

        $statsDashboard = $this->actingAs($this->pendamping)
            ->get(route('dashboard'))->assertOk()->viewData('stats');

        $this->assertSame(4500000, (int) $ringkasMonitoring['total_omzet']);
        $this->assertSame(4500000, (int) $totalLaporan);
        $this->assertSame(4500000, (int) $statsDashboard['omzet_bulan_ini']);
    }

    // =================================================================
    // KPI: SKOR HARUS COCOK DENGAN INDIKATORNYA SENDIRI
    // =================================================================

    /**
     * Skor KPI harus benar-benar merupakan rata-rata indikator yang dihitung.
     *
     * Angka besar di kepala halaman dan rincian di bawahnya dibaca sebagai
     * satu kesatuan. Kalau keduanya tidak konsisten, tidak ada yang bisa
     * menjelaskan selisihnya — dan yang dipercaya biasanya yang paling besar.
     */
    public function test_skor_kpi_konsisten_dengan_rincian_indikatornya(): void
    {
        $rapor = $this->actingAs($this->admin)
            ->get(route('kpi-pendamping.show', $this->profil))
            ->assertOk()
            ->viewData('rapor');

        $dihitung = collect($rapor['indikator'])->where('dihitung', true);

        $this->assertSame(
            2,
            $dihitung->count(),
            'Hanya dua indikator yang ditargetkan, jadi hanya dua yang boleh dihitung.'
        );

        $this->assertSame(
            (int) round($dihitung->avg('progress')),
            $rapor['kpi'],
            'Skor KPI tidak sama dengan rata-rata indikator yang ditampilkan di bawahnya.'
        );

        $this->assertSame($dihitung->count(), $rapor['jumlah_indikator']);
    }
}
