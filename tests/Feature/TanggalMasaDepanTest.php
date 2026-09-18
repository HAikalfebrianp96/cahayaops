<?php

namespace Tests\Feature;

use App\Models\Participant;
use App\Models\Pendamping;
use App\Models\PendampinganRecord;
use App\Models\TrainingRecord;
use App\Models\User;
use App\Services\FollowUpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * SALAH KETIK TAHUN TIDAK BOLEH MENGHILANGKAN PESERTA.
 *
 * =====================================================================
 * APA YANG TERJADI SEBELUM INI
 * =====================================================================
 *
 * Tanggal pendampingan hanya divalidasi sebagai 'date', tanpa batas atas.
 * Mengetik 2027 alih-alih 2026 — salah ketik yang sangat lazim — diterima
 * begitu saja, dan akibatnya sunyi sekaligus berat:
 *
 *   * catatan itu menjadi "aktivitas terakhir", jadi pesertanya terbaca
 *     baru saja didampingi;
 *   * ia terkunci di status Hijau dan HILANG dari daftar tindak lanjut
 *     sampai tanggal itu benar-benar lewat — setahun penuh;
 *   * layarnya menampilkan "Didampingi -365 hari lalu".
 *
 * Terukur langsung pada data sungguhan sebelum diperbaiki: seorang peserta
 * berubah dari "23 hari" menjadi "-365 hari" hanya karena satu catatan.
 *
 * =====================================================================
 * YANG TIDAK IKUT DIBATASI
 * =====================================================================
 *
 * Mencatat MUNDUR tetap boleh — pendamping lazim mengisi catatan minggu
 * lalu pada hari Jumat.
 *
 * Tanggal follow up juga tidak dibatasi: itu rencana, dan rencana memang
 * berada di masa depan.
 *
 * Pelatihan pun tidak: statusnya mengenal "Terdaftar", jadi pelatihan yang
 * dijadwalkan bulan depan adalah pemakaian yang sah.
 */
class TanggalMasaDepanTest extends TestCase
{
    use RefreshDatabase;

    private User $pendamping;
    private Pendamping $profil;
    private Participant $peserta;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-06-20 09:00', 'Asia/Jakarta'));

        $this->profil = Pendamping::create([
            'nama' => 'Rosa', 'nomor_hp' => '081200000001',
            'email' => 'rosa@tanggal.test', 'status' => 'Aktif',
        ]);

        $this->pendamping = User::create([
            'name' => 'Rosa', 'email' => 'rosa.akun@tanggal.test',
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

    private function catat(array $ubah = [])
    {
        return $this->actingAs($this->pendamping)
            ->post(route('pendampingan.store', $this->peserta), array_merge([
                'tanggal' => '2026-06-15',
                'jenis_kegiatan' => PendampinganRecord::KONSULTASI,
                'materi' => 'Materi uji',
            ], $ubah));
    }

    // =================================================================
    // PENCEGAHAN
    // =================================================================

    public function test_pendampingan_bertanggal_masa_depan_ditolak(): void
    {
        $this->catat(['tanggal' => '2027-06-15'])
            ->assertSessionHasErrors('tanggal');

        $this->assertSame(0, PendampinganRecord::count());
    }

    public function test_pendampingan_besok_pun_ditolak(): void
    {
        $this->catat(['tanggal' => '2026-06-21'])
            ->assertSessionHasErrors('tanggal');
    }

    /**
     * Pesannya harus mengarahkan ke sebab yang paling sering: salah tahun.
     */
    public function test_pesannya_menjelaskan_bukan_sekadar_menolak(): void
    {
        $this->catat(['tanggal' => '2027-06-15'])
            ->assertSessionHasErrors(['tanggal' => 'Tanggal pendampingan tidak boleh di masa depan. '
                . 'Periksa kembali tahunnya — mencatat mundur tetap boleh.']);
    }

    // =================================================================
    // YANG TETAP BOLEH
    // =================================================================

    public function test_mencatat_mundur_tetap_boleh(): void
    {
        $this->catat(['tanggal' => '2026-01-05'])->assertSessionHasNoErrors();
        $this->catat(['tanggal' => '2026-06-20'])->assertSessionHasNoErrors();

        $this->assertSame(2, PendampinganRecord::count());
    }

    /**
     * Follow up adalah RENCANA — memang berada di masa depan.
     */
    public function test_jadwal_follow_up_masa_depan_tetap_boleh(): void
    {
        $this->catat([
            'tanggal' => '2026-06-15',
            'tanggal_follow_up' => '2026-07-15',
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, PendampinganRecord::whereNotNull('tanggal_follow_up')->count());
    }

    /**
     * Pelatihan yang dijadwalkan bulan depan adalah pemakaian yang sah —
     * statusnya sendiri mengenal "Terdaftar".
     */
    public function test_pelatihan_terjadwal_masa_depan_tetap_boleh(): void
    {
        $this->actingAs($this->pendamping)
            ->post(route('training.store', $this->peserta), [
                'nama_pelatihan' => 'Pelatihan Pembukuan',
                'tanggal' => '2026-08-10',
                'status' => TrainingRecord::STATUS_TERDAFTAR,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, TrainingRecord::count());
    }

    // =================================================================
    // KETAHANAN UNTUK BARIS YANG TERLANJUR ADA
    // =================================================================

    /**
     * Baris bertanggal masa depan yang sudah terlanjur tersimpan tidak boleh
     * menghasilkan kalimat yang tidak masuk akal.
     *
     * Dibuat langsung lewat model, melewati validasi — meniru baris yang
     * masuk sebelum pencegahannya ada.
     */
    public function test_baris_lama_bertanggal_masa_depan_tidak_menghasilkan_hari_negatif(): void
    {
        PendampinganRecord::create([
            'participant_id' => $this->peserta->id,
            'pendamping_id' => $this->profil->id,
            'tanggal' => '2027-06-15',
            'jenis_kegiatan' => PendampinganRecord::KONSULTASI,
            'materi' => 'Terlanjur tersimpan',
        ]);

        $ringkas = app(FollowUpService::class)
            ->untukBanyak(collect([$this->peserta->fresh()]))[$this->peserta->id];

        $this->assertGreaterThanOrEqual(
            0,
            $ringkas['hari'],
            'Selisih hari tidak boleh negatif — layarnya akan berbunyi "-365 hari lalu".'
        );

        $this->assertStringNotContainsString('-', $ringkas['alasan']);
    }
}
