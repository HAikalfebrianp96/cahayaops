<?php

namespace Tests\Feature;

use App\Models\BusinessUnit;
use App\Models\KoperasiMember;
use App\Models\MemberSaving;
use App\Models\Participant;
use App\Models\Pendamping;
use App\Models\PendampinganRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * DASHBOARD ROLE-BASED + LAPORAN KINERJA PENDAMPING + PWA.
 *
 * Tiga role, tiga dashboard yang benar-benar berbeda — bukan satu halaman
 * dengan bagian yang disembunyikan. Yang paling dijaga di sini: TIDAK ADA
 * data yang bocor lintas role, dan angkanya benar-benar ter-scope, bukan
 * sekadar tabelnya yang disaring.
 */
class DashboardRoleDanLaporanTest extends TestCase
{
    use RefreshDatabase;

    private Pendamping $profilA;
    private Pendamping $profilB;
    private User $pendampingA;
    private User $admin;
    private User $timKoperasi;
    private User $pimpinan;
    private Participant $binaanA;
    private Participant $binaanB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->profilA = Pendamping::create(['nama' => 'Rosa Linda', 'nomor_hp' => '0812', 'email' => 'a@dr.test', 'status' => 'Aktif']);
        $this->profilB = Pendamping::create(['nama' => 'Pendamping B', 'nomor_hp' => '0813', 'email' => 'b@dr.test', 'status' => 'Aktif']);

        $this->pendampingA = $this->user('pendamping', 'a@akun.test', $this->profilA->id);
        $this->admin = $this->user('admin', 'admin@dr.test');
        $this->timKoperasi = $this->user(User::ROLE_TIM_KOPERASI, 'tk@dr.test');
        $this->pimpinan = $this->user('pimpinan', 'pimpinan@dr.test');

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

    private function catatan(Participant $p, string $tanggal, string $jenis = 'Konsultasi'): PendampinganRecord
    {
        return PendampinganRecord::create([
            'participant_id' => $p->id, 'pendamping_id' => $p->pendamping_id,
            'tanggal' => $tanggal, 'jenis_kegiatan' => $jenis, 'materi' => 'Materi ' . $jenis,
        ]);
    }

    // =====================================================================
    // TIGA ROLE, TIGA DASHBOARD BERBEDA
    // =====================================================================

    public function test_setiap_role_mendarat_di_dashboard_yang_berbeda(): void
    {
        $this->actingAs($this->admin)->get(route('dashboard'))
            ->assertOk()->assertViewIs('dashboard')->assertViewHas('modul', 'semua');

        $this->actingAs($this->pendampingA)->get(route('dashboard'))
            ->assertOk()->assertViewIs('dashboard-pendamping');

        $this->actingAs($this->timKoperasi)->get(route('dashboard'))
            ->assertOk()->assertViewIs('dashboard-koperasi');

        $this->actingAs($this->pimpinan)->get(route('dashboard'))
            ->assertOk()->assertSee('Dashboard Pimpinan');
    }

    // =====================================================================
    // DASHBOARD ADMIN — panel alert & monitoring
    // =====================================================================

    public function test_admin_melihat_panel_perlu_perhatian_yang_bisa_diklik(): void
    {
        $respons = $this->actingAs($this->admin)->get(route('dashboard', ['modul' => 'preneur']));

        $respons->assertOk()
            ->assertSee('Perlu Perhatian')
            ->assertSee('peserta belum memperbarui omzet bulan ini')
            // Alert harus MEMBAWA tautan; alert tanpa tautan cuma memberi tahu
            // ada masalah tanpa jalan mengerjakannya.
            ->assertSee(route('omzet.index'), false)
            ->assertSee(route('follow-up.index'), false);
    }

    public function test_alert_hilang_saat_masalahnya_selesai(): void
    {
        // Semua peserta sudah lapor omzet & sudah didampingi.
        foreach ([$this->binaanA, $this->binaanB] as $p) {
            $p->omzetReports()->create([
                'periode_bulan' => now()->month, 'periode_tahun' => now()->year, 'omzet' => 1000000,
            ]);
            $this->catatan($p, now()->toDateString());
        }

        $this->actingAs($this->admin)
            ->get(route('dashboard', ['modul' => 'preneur']))
            ->assertOk()
            ->assertDontSee('peserta belum memperbarui omzet bulan ini');
    }

    public function test_admin_melihat_monitoring_pendamping(): void
    {
        $this->catatan($this->binaanA, now()->toDateString());

        $this->actingAs($this->admin)
            ->get(route('dashboard', ['modul' => 'preneur']))
            ->assertOk()
            ->assertSee('Monitoring Pendamping')
            ->assertSee('Rosa Linda')
            ->assertSee('Pendamping B')
            ->assertSee('pendamping belum mencatat aktivitas apa pun bulan ini');
    }

    public function test_admin_melihat_monitoring_usaha_dan_aktivitas_terbaru(): void
    {
        $this->actingAs($this->admin)
            ->get(route('dashboard', ['modul' => 'preneur']))
            ->assertOk()
            ->assertSee('Monitoring Usaha')
            ->assertSee('Omzet Bulan Ini')
            ->assertSee('Aktivitas Terbaru');
    }

    /** Pertumbuhan hanya dihitung dari peserta yang punya DUA laporan berurutan. */
    public function test_pertumbuhan_omzet_membandingkan_periode_yang_benar(): void
    {
        $lalu = now()->subMonth();

        $this->binaanA->omzetReports()->create([
            'periode_bulan' => $lalu->month, 'periode_tahun' => $lalu->year, 'omzet' => 2000000,
        ]);
        $this->binaanA->omzetReports()->create([
            'periode_bulan' => now()->month, 'periode_tahun' => now()->year, 'omzet' => 3000000,
        ]);

        $this->actingAs($this->admin)
            ->get(route('dashboard', ['modul' => 'preneur']))
            ->assertOk()
            ->assertViewHas('omzetPerbandingan', function ($data) {
                return $data['total_bulan_ini'] === 3000000
                    && $data['total_bulan_lalu'] === 2000000
                    && $data['pertumbuhan_persen'] === 50.0
                    && $data['naik']->count() === 1;
            });
    }

    // =====================================================================
    // DASHBOARD TIM KOPERASI
    // =====================================================================

    public function test_dashboard_koperasi_menampilkan_calon_anggota_dari_peserta(): void
    {
        $this->actingAs($this->timKoperasi)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Peserta Cahaya Preneur Potensial Menjadi Anggota')
            ->assertSee('Binaan Milik A')
            ->assertSee(route('koperasi.anggota.jadikan', $this->binaanA), false);
    }

    /** Peserta yang SUDAH jadi anggota keluar dari daftar calon. */
    public function test_peserta_yang_sudah_anggota_bukan_lagi_calon(): void
    {
        KoperasiMember::create([
            'nama' => 'Binaan Milik A', 'nomor_hp' => '0812',
            'tanggal_bergabung' => now()->toDateString(), 'status' => 'Aktif',
            'participant_id' => $this->binaanA->id,
        ]);

        $this->actingAs($this->timKoperasi)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('calonAnggota', fn ($calon) => ! $calon->contains('id', $this->binaanA->id));
    }

    /**
     * Dashboard Tim Koperasi fokus pada KONDISI USAHA (keputusan 23 Sep 2026):
     * enam KPI laba rugi — angka yang sama dengan halaman Keuangan, yang
     * memang boleh dibuka Tim Koperasi. Arus dana sosial & hibah tetap
     * TIDAK dimuat, begitu pula Hak Mitra/Fee sebagai KPI.
     */
    public function test_dashboard_koperasi_fokus_kondisi_usaha_tanpa_dana_sosial(): void
    {
        $this->actingAs($this->timKoperasi)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSeeInOrder(['Total Penjualan', 'HPP Terjual', 'Laba Kotor', 'Pengeluaran Operasional',
                'Piutang Aktif', 'Laba Bersih', 'Produk Terlaris', 'Penjualan Terbesar', 'Transaksi Terbaru'])
            ->assertDontSee('Dana Hibah')
            ->assertDontSee('Dana Sosial')
            ->assertDontSee('Hak Mitra');
    }

    public function test_statistik_koperasi_benar(): void
    {
        $anggota = KoperasiMember::create([
            'nama' => 'Anggota Uji', 'nomor_hp' => '0812',
            'tanggal_bergabung' => now()->toDateString(), 'status' => 'Aktif',
            'participant_id' => $this->binaanA->id,
        ]);
        $anggota->savings()->create([
            'jenis_simpanan' => 'Simpanan Pokok', 'tanggal' => now()->toDateString(), 'nominal' => 100000,
        ]);
        BusinessUnit::create(['nama' => 'Unit A', 'kode' => 'UNIT-DR', 'tipe' => 'Barang', 'status' => 'Aktif']);

        $this->actingAs($this->timKoperasi)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('stats', function ($stats) {
                return $stats['total_anggota'] === 1
                    && $stats['anggota_aktif'] === 1
                    && $stats['anggota_baru'] === 1
                    && $stats['dari_preneur'] === 1
                    && $stats['total_unit'] === 1
                    && (int) $stats['total_simpanan'] === 100000;
            });
    }

    /** Simpanan yang dibatalkan tidak ikut menambah total di dashboard. */
    public function test_simpanan_dibatalkan_tidak_dihitung_di_dashboard_koperasi(): void
    {
        $anggota = KoperasiMember::create([
            'nama' => 'Anggota Uji', 'nomor_hp' => '0812',
            'tanggal_bergabung' => now()->toDateString(), 'status' => 'Aktif',
        ]);
        $simpanan = $anggota->savings()->create([
            'jenis_simpanan' => 'Simpanan Pokok', 'tanggal' => now()->toDateString(), 'nominal' => 500000,
        ]);
        $simpanan->forceFill(['status' => MemberSaving::STATUS_DIBATALKAN])->save();

        $this->actingAs($this->timKoperasi)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('stats', fn ($stats) => (int) $stats['total_simpanan'] === 0);
    }

    // =====================================================================
    // LAPORAN KINERJA PENDAMPING
    // =====================================================================

    public function test_admin_melihat_kinerja_seluruh_pendamping(): void
    {
        $this->catatan($this->binaanA, now()->toDateString(), 'Kunjungan Usaha');
        $this->catatan($this->binaanA, now()->toDateString(), 'Konsultasi');

        $this->actingAs($this->admin)
            ->get(route('laporan.pendamping'))
            ->assertOk()
            ->assertSee('Rosa Linda')
            ->assertSee('Pendamping B')
            ->assertViewHas('baris', function ($baris) {
                $rosa = $baris->firstWhere('pendamping.nama', 'Rosa Linda');

                return $rosa['pendampingan'] === 2
                    && $rosa['kunjungan'] === 1
                    && $rosa['konsultasi'] === 1;
            });
    }

    /** INTI: Pendamping hanya melihat barisnya sendiri. */
    public function test_pendamping_hanya_melihat_kinerjanya_sendiri(): void
    {
        $this->catatan($this->binaanB, now()->toDateString());

        $this->actingAs($this->pendampingA)
            ->get(route('laporan.pendamping'))
            ->assertOk()
            ->assertSee('Rosa Linda')
            ->assertDontSee('Pendamping B');
    }

    /** Dan tidak bisa mengintip lewat filter di URL. */
    public function test_pendamping_tidak_bisa_melihat_kinerja_lain_lewat_url(): void
    {
        $this->actingAs($this->pendampingA)
            ->get(route('laporan.pendamping', ['pendamping_id' => $this->profilB->id]))
            ->assertOk()
            ->assertSee('Rosa Linda')
            ->assertDontSee('Pendamping B');
    }

    public function test_filter_periode_laporan_pendamping_bekerja(): void
    {
        $this->catatan($this->binaanA, '2026-01-15');
        $this->catatan($this->binaanA, now()->toDateString());

        $this->actingAs($this->admin)
            ->get(route('laporan.pendamping', ['dari' => now()->startOfMonth()->toDateString()]))
            ->assertOk()
            ->assertViewHas('baris', function ($baris) {
                return $baris->firstWhere('pendamping.nama', 'Rosa Linda')['pendampingan'] === 1;
            });
    }

    public function test_export_laporan_pendamping_mengikuti_scope(): void
    {
        $this->catatan($this->binaanA, now()->toDateString());
        $this->catatan($this->binaanB, now()->toDateString());

        $respons = $this->actingAs($this->pendampingA)->get(route('laporan.pendamping.export'));

        $respons->assertOk();
        $isi = $respons->streamedContent();

        $this->assertStringContainsString('Rosa Linda', $isi);
        $this->assertStringNotContainsString('Pendamping B', $isi);

        // Admin mendapat keduanya.
        $isiAdmin = $this->actingAs($this->admin)
            ->get(route('laporan.pendamping.export'))->streamedContent();

        $this->assertStringContainsString('Rosa Linda', $isiAdmin);
        $this->assertStringContainsString('Pendamping B', $isiAdmin);
    }

    public function test_export_pdf_laporan_pendamping_berhasil(): void
    {
        $this->catatan($this->binaanA, now()->toDateString());

        $respons = $this->actingAs($this->admin)->get(route('laporan.pendamping.export-pdf'));

        $respons->assertOk();
        $this->assertStringContainsString('application/pdf', $respons->headers->get('content-type'));
    }

    public function test_role_lain_ditolak_di_laporan_pendamping(): void
    {
        foreach ([$this->pimpinan, $this->timKoperasi] as $user) {
            $this->actingAs($user)->get(route('laporan.pendamping'))->assertForbidden();
            $this->actingAs($user)->get(route('laporan.pendamping.export'))->assertForbidden();
            $this->actingAs($user)->get(route('laporan.pendamping.export-pdf'))->assertForbidden();
        }
    }

    // =====================================================================
    // PWA
    // =====================================================================

    public function test_manifest_valid_dan_dapat_diakses(): void
    {
        $this->assertFileExists(public_path('manifest.webmanifest'));

        $manifest = json_decode(file_get_contents(public_path('manifest.webmanifest')), true);

        $this->assertIsArray($manifest, 'Manifest harus JSON yang valid.');
        foreach (['name', 'short_name', 'start_url', 'display', 'icons'] as $wajib) {
            $this->assertArrayHasKey($wajib, $manifest, "Manifest kehilangan field wajib: $wajib");
        }

        $this->assertSame('standalone', $manifest['display']);
        $this->assertNotEmpty($manifest['icons']);

        // Ikon yang disebut manifest HARUS benar-benar ada.
        foreach ($manifest['icons'] as $ikon) {
            $this->assertFileExists(public_path(ltrim($ikon['src'], '/')), 'Ikon manifest tidak ditemukan: ' . $ikon['src']);
        }
    }

    public function test_layout_memuat_manifest_dan_service_worker(): void
    {
        $this->actingAs($this->admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('manifest.webmanifest', false)
            ->assertSee('serviceWorker', false)
            ->assertSee('theme-color', false);
    }

    /**
     * INTI KEAMANAN PWA: service worker TIDAK BOLEH menyimpan halaman atau
     * data di cache. CahayaOS adalah sistem pencatatan operasional — data
     * basi yang tampak baru lebih berbahaya daripada halaman lambat.
     */
    public function test_service_worker_tidak_menyimpan_halaman_di_cache(): void
    {
        $this->assertFileExists(public_path('sw.js'));

        $sw = file_get_contents(public_path('sw.js'));

        // Permintaan non-GET dilewati sepenuhnya.
        $this->assertStringContainsString("permintaan.method !== 'GET'", $sw);
        // Permintaan HTML/navigasi dilewati sepenuhnya.
        $this->assertStringContainsString("permintaan.mode === 'navigate'", $sw);
        $this->assertStringContainsString('memintaHtml', $sw);
        // Ada penyaring aset statis — cache hanya untuk itu.
        $this->assertStringContainsString('asetStatis', $sw);
    }
}
