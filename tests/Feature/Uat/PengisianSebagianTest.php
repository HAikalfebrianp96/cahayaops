<?php

namespace Tests\Feature\Uat;

use App\Models\LegalitasRecord;
use App\Models\Participant;
use App\Models\ParticipantMeasurement;
use App\Models\Pendamping;
use App\Models\PendampinganRecord;
use App\Models\PendampingTarget;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * FORM DIISI SEADANYA — SEPERTI YANG SUNGGUHAN TERJADI.
 *
 * =====================================================================
 * KENAPA SAPUAN INI ADA
 * =====================================================================
 *
 * Seluruh test form sebelumnya mengirim isian LENGKAP dan rapi. Pengguna
 * tidak begitu: mereka mengisi yang mereka tahu, mengosongkan sisanya, dan
 * menekan Simpan.
 *
 * Bug pemblokir yang ditemukan sebelum ini — ambang follow up yang menolak
 * pengisian sebagian padahal formnya sendiri menjanjikan boleh — ketahuan
 * justru karena satu test kebetulan mengirim satu kolom saja. Sapuan ini
 * melakukannya dengan sengaja dan menyeluruh.
 *
 * Yang diuji BUKAN validasi menolak masukan buruk (itu sudah diuji di
 * tempat lain), melainkan sebaliknya: masukan yang SAH dan tidak lengkap
 * harus diterima, bukan ditolak dengan pesan yang membingungkan.
 */
class PengisianSebagianTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $pendamping;
    private Pendamping $profil;
    private Participant $peserta;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-06-20 09:00', 'Asia/Jakarta'));

        $this->profil = Pendamping::create([
            'nama' => 'Rosa', 'nomor_hp' => '081200000001',
            'email' => 'rosa@sebagian.test', 'status' => 'Aktif',
        ]);

        $this->admin = User::create([
            'name' => 'Admin', 'email' => 'admin@sebagian.test',
            'password' => 'password123', 'role' => 'admin', 'is_active' => true,
        ]);

        $this->pendamping = User::create([
            'name' => 'Rosa', 'email' => 'rosa.akun@sebagian.test',
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
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** Seperti form asli: kolom kosong terkirim sebagai string kosong. */
    private function pengaturan(array $ubah)
    {
        return $this->actingAs($this->admin)->post(route('pengaturan.update'), array_merge([
            'nama_sistem' => 'CahayaOS',
            'nama_lembaga' => 'YBM PLN UP3 Bengkulu',
            'nama_program' => 'Cahaya Preneur',
            'followup_kuning_hari' => '',
            'followup_merah_hari' => '',
            'peserta_aktif_hari' => '',
            'peserta_perlu_follow_up_hari' => '',
            'beban_ringan_maks' => '',
            'beban_normal_maks' => '',
            'beban_tinggi_maks' => '',
            'target_default_binaan' => '',
            'target_default_kunjungan' => '',
            'target_default_pendampingan' => '',
            'target_default_follow_up' => '',
        ], $ubah));
    }

    // =================================================================
    // PENGATURAN
    // =================================================================

    public function test_menyimpan_pengaturan_tanpa_mengisi_satu_ambang_pun(): void
    {
        $this->pengaturan([])->assertSessionHasNoErrors();
    }

    /**
     * Tiap ambang dicoba SENDIRIAN. Empat pasang, delapan kemungkinan —
     * masing-masing harus bisa disimpan tanpa memaksa pasangannya diisi.
     */
    public function test_setiap_ambang_bisa_diisi_sendirian(): void
    {
        $percobaan = [
            ['followup_kuning_hari' => 10],
            ['followup_merah_hari' => 60],
            ['peserta_aktif_hari' => 20],
            ['peserta_perlu_follow_up_hari' => 90],
            ['beban_ringan_maks' => 8],
            ['beban_normal_maks' => 25],
            ['beban_tinggi_maks' => 40],
        ];

        foreach ($percobaan as $isian) {
            $this->pengaturan($isian)
                ->assertSessionHasNoErrors();
        }
    }

    public function test_target_bawaan_bisa_diisi_sebagian(): void
    {
        $this->pengaturan(['target_default_pendampingan' => 12])
            ->assertSessionHasNoErrors();
    }

    // =================================================================
    // TARGET PENDAMPING
    // =================================================================

    /**
     * Formnya sendiri berbunyi "Isi HANYA indikator yang memang menjadi
     * tanggung jawab pendamping". Satu indikator harus cukup.
     */
    public function test_target_dengan_satu_indikator_saja_diterima(): void
    {
        $this->actingAs($this->admin)
            ->post(route('target-pendamping.store'), [
                'pendamping_id' => $this->profil->id,
                'periode' => PendampingTarget::PERIODE_BULANAN,
                'tahun' => 2026,
                'bulan' => 6,
                'target_pendampingan' => 12,
                'target_kunjungan' => '',
                'target_follow_up' => '',
                'target_perkembangan_peserta' => '',
                'target_input_omzet' => '',
                'target_pelatihan' => '',
                'target_legalitas' => '',
                'target_jumlah_binaan' => '',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('pendamping_targets', [
            'pendamping_id' => $this->profil->id,
            'target_pendampingan' => 12,
        ]);
    }

    // =================================================================
    // PENCATATAN HARIAN PENDAMPING
    // =================================================================

    /**
     * Catatan pendampingan tanpa rencana tindak lanjut dan tanpa catatan
     * tambahan — bentuk paling lazim di lapangan.
     */
    public function test_catatan_pendampingan_minimal_diterima(): void
    {
        $this->actingAs($this->pendamping)
            ->post(route('pendampingan.store', $this->peserta), [
                'tanggal' => '2026-06-15',
                'jenis_kegiatan' => PendampinganRecord::KONSULTASI,
                'materi' => 'Diskusi pembukuan',
                'catatan' => '',
                'tanggal_follow_up' => '',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('pendampingan_records', [
            'participant_id' => $this->peserta->id,
            'materi' => 'Diskusi pembukuan',
        ]);
    }

    /**
     * Omzet tanpa HPP. Banyak peserta belum menghitung modalnya, dan
     * sistem memang memperlakukan laba sebagai "belum dilaporkan".
     */
    public function test_omzet_tanpa_hpp_diterima(): void
    {
        $this->actingAs($this->pendamping)
            ->post(route('omzet.store', $this->peserta), [
                'periode_bulan' => 6,
                'periode_tahun' => 2026,
                'omzet' => 2500000,
                'hpp' => '',
                'catatan' => '',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('omzet_reports', [
            'participant_id' => $this->peserta->id,
            'omzet' => 2500000,
            'hpp' => null,
        ]);
    }

    /**
     * Kondisi awal dengan hanya beberapa indikator terisi.
     *
     * Pendamping jarang tahu semuanya sekaligus di kunjungan pertama.
     */
    public function test_kondisi_awal_dengan_sebagian_indikator_diterima(): void
    {
        $this->actingAs($this->pendamping)
            ->post(route('perkembangan.store', $this->peserta), [
                'jenis' => ParticipantMeasurement::BASELINE,
                'tanggal' => '2026-06-15',
                'omzet_bulanan' => 3000000,
                'hpp_bulanan' => '',
                'biaya_operasional_bulanan' => '',
                'jumlah_tenaga_kerja' => '',
                'jumlah_produk' => '',
                'jumlah_pelanggan' => '',
                'kapasitas_produksi' => '',
                'satuan_produksi' => '',
                'jangkauan_pasar' => '',
                'catatan' => '',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('participant_measurements', [
            'participant_id' => $this->peserta->id,
            'jenis' => ParticipantMeasurement::BASELINE,
        ]);
    }

    /**
     * Kondisi awal TANPA satu pun angka — hanya tanggal dan jenisnya.
     *
     * Kalau ini ditolak, pendamping yang baru survei awal tidak bisa
     * mencatat apa pun sampai ia tahu seluruh angkanya.
     */
    public function test_kondisi_awal_tanpa_angka_sama_sekali(): void
    {
        $this->actingAs($this->pendamping)
            ->post(route('perkembangan.store', $this->peserta), [
                'jenis' => ParticipantMeasurement::BASELINE,
                'tanggal' => '2026-06-15',
            ])
            ->assertSessionHasNoErrors();
    }

    /**
     * Dokumen legalitas tanpa berkas dan tanpa nomor — keadaan normal
     * untuk dokumen yang masih diurus.
     */
    public function test_legalitas_tanpa_berkas_dan_nomor_diterima(): void
    {
        $this->actingAs($this->pendamping)
            ->post(route('legalitas.store', $this->peserta), [
                'jenis_dokumen' => 'NIB',
                'status' => LegalitasRecord::STATUS_PROSES,
                'nomor_dokumen' => '',
                'tanggal_terbit' => '',
                'catatan' => '',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('legalitas_records', [
            'participant_id' => $this->peserta->id,
            'jenis_dokumen' => 'NIB',
        ]);
    }

    // =================================================================
    // NOL YANG DISENGAJA — BEDA DARI KOSONG
    // =================================================================

    /**
     * "Bulan ini saya tidak berjualan" harus bisa dicatat.
     *
     * Omzet nol adalah fakta, dan fakta itu berbeda dari tidak melapor.
     * Kalau nol ditolak, satu-satunya cara pendamping mencatat keadaan itu
     * adalah dengan tidak mencatat apa pun — dan keduanya lalu terlihat
     * sama di seluruh laporan.
     */
    public function test_omzet_nol_bisa_dilaporkan(): void
    {
        $this->actingAs($this->pendamping)
            ->post(route('omzet.store', $this->peserta), [
                'periode_bulan' => 6,
                'periode_tahun' => 2026,
                'omzet' => 0,
                'catatan' => 'Tidak berjualan bulan ini',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('omzet_reports', [
            'participant_id' => $this->peserta->id,
            'omzet' => 0,
        ]);
    }

    /**
     * Target bernilai 0 diperlakukan sama dengan dikosongkan.
     *
     * KpiService melewati indikator bertarget <= 0. Yang dijaga di sini:
     * mengetik 0 tidak menghasilkan pembagian dengan nol maupun indikator
     * yang dinilai mustahil tercapai.
     */
    public function test_target_bernilai_nol_diperlakukan_sebagai_tidak_ditargetkan(): void
    {
        $this->actingAs($this->admin)
            ->post(route('target-pendamping.store'), [
                'pendamping_id' => $this->profil->id,
                'periode' => PendampingTarget::PERIODE_BULANAN,
                'tahun' => 2026,
                'bulan' => 6,
                'target_pendampingan' => 5,
                'target_kunjungan' => 0,
            ])
            ->assertSessionHasNoErrors();

        $rapor = $this->actingAs($this->admin)
            ->get(route('kpi-pendamping.show', $this->profil))
            ->assertOk()
            ->viewData('rapor');

        $this->assertTrue($rapor['indikator']['target_pendampingan']['dihitung']);
        $this->assertFalse(
            $rapor['indikator']['target_kunjungan']['dihitung'],
            'Target 0 harus diperlakukan sebagai tidak ditargetkan.'
        );
        $this->assertNotNull($rapor['kpi']);
    }

    // =================================================================
    // PESERTA BARU
    // =================================================================

    /**
     * Peserta baru dengan isian seminimal mungkin.
     *
     * Data yang belum lengkap memang sudah ditandai sistem lewat daftar
     * prioritas — jadi menolaknya di muka hanya membuat pendamping tidak
     * bisa mencatat siapa pun sampai datanya lengkap.
     */
    public function test_peserta_baru_dengan_isian_minimal(): void
    {
        $this->actingAs($this->pendamping)
            ->post(route('participants.store'), [
                'nama_peserta' => 'Peserta Minimal',
                'nomor_hp' => '081200000003',
                'nama_usaha' => 'Warung Minimal',
                // Harus salah satu dari Participant::KATEGORI_OPTIONS;
                // "Kuliner" bukan pilihan yang sah, dan validasi memang benar
                // menolaknya.
                'kategori_usaha' => 'Makanan & Minuman',
                'tanggal_mulai_program' => '2026-06-01',
                'status' => 'Aktif',
                'alamat' => '',
                'pendamping_id' => $this->profil->id,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('participants', ['nama_peserta' => 'Peserta Minimal']);
    }
}
