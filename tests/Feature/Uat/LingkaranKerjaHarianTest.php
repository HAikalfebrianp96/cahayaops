<?php

namespace Tests\Feature\Uat;

use App\Models\OmzetReport;
use App\Models\Participant;
use App\Models\ParticipantMeasurement;
use App\Models\Pendamping;
use App\Models\PendampinganRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * SATU INPUT, DAFTAR KERJANYA IKUT BERUBAH.
 *
 * =====================================================================
 * JANJI YANG DIUJI DI SINI
 * =====================================================================
 *
 * Seluruh sistem ini dibangun di atas satu janji: pendamping cukup mencatat
 * apa yang ia kerjakan, dan sistem yang mengurus sisanya.
 *
 * Test-test sebelumnya memeriksa KEADAAN DIAM — halaman terbuka, angkanya
 * benar, angkanya sepakat antar halaman. Tidak satu pun memeriksa
 * TRANSISINYA: sesudah mencatat, apakah daftarnya benar-benar berubah?
 *
 * Kalau tidak, akibatnya langsung dan fatal bagi kepercayaan: pendamping
 * mengerjakan pesertanya, kembali ke Tugas Saya, dan orang itu masih
 * tercantum di "Perlu Segera". Ia akan menyimpulkan sistemnya tidak
 * mencatat pekerjaannya — lalu berhenti memakainya.
 *
 * Yang diuji di sini seluruh lingkarannya, dari sisi pendamping.
 */
class LingkaranKerjaHarianTest extends TestCase
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
            'email' => 'rosa@lingkaran.test', 'status' => 'Aktif',
        ]);

        $this->pendamping = User::create([
            'name' => 'Rosa', 'email' => 'rosa.akun@lingkaran.test',
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

        // Terbengkalai 90 hari — jauh melewati ambang bawaan 45 hari.
        PendampinganRecord::create([
            'participant_id' => $this->peserta->id,
            'pendamping_id' => $this->profil->id,
            'tanggal' => '2026-03-22',
            'jenis_kegiatan' => PendampinganRecord::KONSULTASI,
            'materi' => 'Kunjungan lama',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** Keranjang tempat peserta ini berada sekarang. */
    private function keranjang(): ?string
    {
        $kelompok = $this->actingAs($this->pendamping)
            ->get(route('follow-up.index'))
            ->assertOk()
            ->viewData('kelompok');

        foreach ($kelompok as $nama => $isi) {
            if ($isi->contains(fn ($b) => $b['peserta']->id === $this->peserta->id)) {
                return $nama;
            }
        }

        return null;
    }

    /** @return list<string> tombol yang ditawarkan untuk peserta ini */
    private function tombol(): array
    {
        $kelompok = $this->actingAs($this->pendamping)
            ->get(route('follow-up.index'))
            ->assertOk()
            ->viewData('kelompok');

        $baris = collect($kelompok)->flatten(1)
            ->firstWhere(fn ($b) => $b['peserta']->id === $this->peserta->id);

        return $baris ? array_column($baris['tindakan'], 'label') : [];
    }

    private function catatPendampingan(): void
    {
        $this->actingAs($this->pendamping)
            ->post(route('pendampingan.store', $this->peserta), [
                'tanggal' => '2026-06-20',
                'jenis_kegiatan' => PendampinganRecord::KUNJUNGAN_USAHA,
                'materi' => 'Kunjungan hari ini',
            ])
            ->assertSessionHasNoErrors();
    }

    // =================================================================
    // LINGKARAN UTAMA
    // =================================================================

    public function test_sebelum_dikerjakan_peserta_berada_di_perlu_segera(): void
    {
        $this->assertSame('terlambat', $this->keranjang());
    }

    /**
     * Inti janjinya: sesudah dicatat, orangnya pindah keranjang.
     */
    public function test_sesudah_dicatat_peserta_pindah_dari_perlu_segera(): void
    {
        $this->assertSame('terlambat', $this->keranjang());

        $this->catatPendampingan();

        $this->assertSame(
            'normal',
            $this->keranjang(),
            'Sesudah dikerjakan, pesertanya masih tercantum sebagai terlambat.'
        );
    }

    /**
     * Hitungan di Dashboard ikut berubah — bukan cuma halaman daftarnya.
     */
    public function test_hitungan_tugas_di_dashboard_ikut_berkurang(): void
    {
        $sebelum = $this->actingAs($this->pendamping)
            ->get(route('dashboard'))->assertOk()->viewData('ringkasTugas');

        $this->assertSame(1, $sebelum['perlu_segera']);

        $this->catatPendampingan();

        $sesudah = $this->actingAs($this->pendamping)
            ->get(route('dashboard'))->assertOk()->viewData('ringkasTugas');

        $this->assertSame(0, $sesudah['perlu_segera']);
        $this->assertSame(1, $sesudah['normal']);
    }

    /**
     * Tombol ikut menyesuaikan: yang sudah dikerjakan berhenti ditawarkan.
     */
    public function test_tombol_update_omzet_hilang_sesudah_omzet_dilaporkan(): void
    {
        $this->assertContains('Update Omzet', $this->tombol());

        $this->actingAs($this->pendamping)
            ->post(route('omzet.store', $this->peserta), [
                'periode_bulan' => 6,
                'periode_tahun' => 2026,
                'omzet' => 2500000,
            ])
            ->assertSessionHasNoErrors();

        $this->assertNotContains('Update Omzet', $this->tombol());
    }

    public function test_tombol_kondisi_awal_hilang_sesudah_baseline_dicatat(): void
    {
        $this->assertContains('Catat Kondisi Awal', $this->tombol());

        $this->actingAs($this->pendamping)
            ->post(route('perkembangan.store', $this->peserta), [
                'jenis' => ParticipantMeasurement::BASELINE,
                'tanggal' => '2026-06-18',
                'omzet_bulanan' => 3000000,
            ])
            ->assertSessionHasNoErrors();

        $this->assertNotContains('Catat Kondisi Awal', $this->tombol());
    }

    /**
     * Sesudah seluruhnya dikerjakan, yang tersisa hanya pekerjaan intinya.
     */
    public function test_sesudah_semua_dikerjakan_tinggal_tombol_inti(): void
    {
        $this->catatPendampingan();

        $this->actingAs($this->pendamping)->post(route('omzet.store', $this->peserta), [
            'periode_bulan' => 6, 'periode_tahun' => 2026, 'omzet' => 2500000,
        ])->assertSessionHasNoErrors();

        $this->actingAs($this->pendamping)->post(route('perkembangan.store', $this->peserta), [
            'jenis' => ParticipantMeasurement::BASELINE,
            'tanggal' => '2026-06-18',
        ])->assertSessionHasNoErrors();

        $this->assertSame(['Catat Pendampingan'], $this->tombol());
    }

    // =================================================================
    // PERIODE LAPORAN OMZET TIDAK BISA DIGESER LEWAT EDIT
    // =================================================================

    /**
     * Keputusan yang sudah diambil di controller: periode TIDAK ikut
     * diperbarui lewat Edit, karena memindahkan laporan ke periode lain
     * bisa menabrak indeks unik atau mencampur mode pencatatan dalam satu
     * bulan. Perbaikan periode dilakukan dengan hapus lalu catat ulang.
     *
     * Yang dijaga di sini: mengirimkan periode baru lewat form TIDAK
     * diam-diam menggesernya.
     */
    public function test_periode_tidak_ikut_bergeser_walau_dikirim_lewat_edit(): void
    {
        $this->actingAs($this->pendamping)->post(route('omzet.store', $this->peserta), [
            'periode_bulan' => 6, 'periode_tahun' => 2026, 'omzet' => 2500000,
        ])->assertSessionHasNoErrors();

        $laporan = OmzetReport::where('participant_id', $this->peserta->id)->firstOrFail();

        $this->actingAs($this->pendamping)
            ->put(route('omzet.update', $laporan), [
                'omzet' => 3000000,
                // Percobaan menggeser periode lewat form.
                'periode_bulan' => 5,
                'periode_tahun' => 2025,
            ])
            ->assertSessionHasNoErrors();

        $laporan->refresh();

        $this->assertSame(3000000, (int) $laporan->omzet, 'Nilainya memang boleh dikoreksi.');
        $this->assertSame(6, (int) $laporan->periode_bulan, 'Periode tidak boleh ikut bergeser.');
        $this->assertSame(2026, (int) $laporan->periode_tahun);
    }
}
