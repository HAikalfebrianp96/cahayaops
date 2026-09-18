<?php

namespace Tests\Feature;

use App\Models\OmzetReport;
use App\Models\Participant;
use App\Models\ParticipantMeasurement;
use App\Models\Pendamping;
use App\Models\PendampinganRecord;
use App\Models\User;
use App\Services\FollowUpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TOMBOL TINDAKAN HARUS MENGIKUTI ALASANNYA.
 *
 * Daftar Prioritas dan daftar Follow Up menjawab pertanyaan yang sama.
 * Sebelumnya masing-masing menulis tombolnya sendiri, jadi peserta yang sama
 * menawarkan pilihan berbeda tergantung halaman mana yang kebetulan dibuka —
 * dan kartu yang menuliskan "Omzet bulan ini belum diperbarui" tidak pernah
 * menyediakan jalan untuk mengerjakannya.
 *
 * Yang dijaga di sini: tombolnya benar, sama di kedua halaman, dan tidak
 * berubah menjadi jalur input kedua untuk data yang sama.
 */
class TindakanPesertaTest extends TestCase
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
            'nama' => 'Pendamping Uji', 'nomor_hp' => '081200000001',
            'email' => 'p@tindakan.test', 'status' => 'Aktif',
        ]);

        $this->admin = User::create([
            'name' => 'Admin Uji', 'email' => 'admin@tindakan.test',
            'password' => 'password123', 'role' => 'admin', 'is_active' => true,
        ]);

        $this->pendamping = User::create([
            'name' => 'Rosa Uji', 'email' => 'rosa@tindakan.test',
            'password' => 'password123', 'role' => 'pendamping',
            'pendamping_id' => $this->profil->id, 'is_active' => true,
        ]);

        $this->peserta = Participant::create([
            'nama_peserta' => 'Peserta Uji', 'nomor_hp' => '081200000002',
            'alamat' => 'Jl. Uji', 'nama_usaha' => 'Usaha Uji',
            'kategori_usaha' => 'Jasa',
            'tanggal_mulai_program' => now()->subMonths(6)->toDateString(),
            'status' => 'Aktif', 'pendamping_id' => $this->profil->id,
        ]);
    }

    private function catatan(string $tanggal, array $ubah = []): PendampinganRecord
    {
        return PendampinganRecord::create(array_merge([
            'participant_id' => $this->peserta->id,
            'pendamping_id' => $this->profil->id,
            'tanggal' => $tanggal,
            'jenis_kegiatan' => 'Konsultasi',
            'materi' => 'Materi uji',
        ], $ubah));
    }

    private function laporkanOmzet(): void
    {
        OmzetReport::create([
            'participant_id' => $this->peserta->id,
            'periode_bulan' => now()->month,
            'periode_tahun' => now()->year,
            'omzet' => 1500000,
        ]);
    }

    private function catatBaseline(): void
    {
        ParticipantMeasurement::create([
            'participant_id' => $this->peserta->id,
            'jenis' => ParticipantMeasurement::BASELINE,
            'tanggal' => now()->subMonths(5)->toDateString(),
        ]);
    }

    /** @return list<string> label tindakan untuk peserta ini */
    private function labelDari(string $url): array
    {
        $kelompok = $this->actingAs($this->pendamping)
            ->get($url)
            ->assertOk()
            ->viewData('kelompok');

        $baris = collect($kelompok)->flatten(1)
            ->firstWhere(fn ($b) => $b['peserta']->id === $this->peserta->id);

        $this->assertNotNull($baris, "Peserta tidak muncul di {$url}");

        return array_column($baris['tindakan'], 'label');
    }

    // =================================================================
    // ISI TINDAKAN
    // =================================================================

    public function test_omzet_belum_dilaporkan_memunculkan_tombol_update_omzet(): void
    {
        $this->catatan(now()->subDays(10)->toDateString());
        $this->catatBaseline();

        $label = app(FollowUpService::class)->tindakan(
            ['terakhir' => now()->subDays(10)],
            omzetBulanIniSudahDilaporkan: false,
            punyaBaseline: true,
        );

        $this->assertContains('Update Omzet', array_column($label, 'label'));
    }

    public function test_omzet_sudah_dilaporkan_tidak_menawarkan_update_omzet(): void
    {
        $label = app(FollowUpService::class)->tindakan(
            ['terakhir' => now()->subDays(10)],
            omzetBulanIniSudahDilaporkan: true,
            punyaBaseline: true,
        );

        $this->assertNotContains('Update Omzet', array_column($label, 'label'));
    }

    public function test_tanpa_baseline_menawarkan_catat_kondisi_awal(): void
    {
        $tanpa = app(FollowUpService::class)->tindakan(
            ['terakhir' => now()], omzetBulanIniSudahDilaporkan: true, punyaBaseline: false,
        );
        $dengan = app(FollowUpService::class)->tindakan(
            ['terakhir' => now()], omzetBulanIniSudahDilaporkan: true, punyaBaseline: true,
        );

        $this->assertContains('Catat Kondisi Awal', array_column($tanpa, 'label'));
        $this->assertNotContains('Catat Kondisi Awal', array_column($dengan, 'label'));
    }

    /**
     * Peserta yang belum pernah didampingi disebut apa adanya.
     */
    public function test_belum_pernah_didampingi_memakai_label_pertama(): void
    {
        $label = app(FollowUpService::class)->tindakan(
            ['terakhir' => null], omzetBulanIniSudahDilaporkan: true, punyaBaseline: true,
        );

        $this->assertSame('Catat Pendampingan Pertama', $label[0]['label']);
        $this->assertTrue($label[0]['utama']);
    }

    /**
     * TIDAK boleh ada tombol "Catat Follow Up" tersendiri.
     *
     * Jadwal follow up diisi di dalam form catatan pendampingan. Tombol
     * terpisah akan menjadi jalur input kedua untuk data yang sama — persis
     * yang harus dihindari.
     */
    public function test_tidak_ada_jalur_input_kedua_untuk_follow_up(): void
    {
        foreach ([[true, true], [true, false], [false, true], [false, false]] as [$omzet, $baseline]) {
            $label = array_column(
                app(FollowUpService::class)->tindakan(
                    ['terakhir' => now()->subDays(50)],
                    omzetBulanIniSudahDilaporkan: $omzet,
                    punyaBaseline: $baseline,
                ),
                'label'
            );

            $this->assertNotContains('Catat Follow Up', $label);
        }
    }

    /**
     * Tombol utama membuka form pencatatannya, bukan sekadar tabnya.
     */
    public function test_tombol_utama_menuju_form_pencatatan(): void
    {
        $utama = app(FollowUpService::class)->tindakan(
            ['terakhir' => now()], omzetBulanIniSudahDilaporkan: true, punyaBaseline: true,
        )[0];

        $this->assertSame('#catat-pendampingan', $utama['fragmen']);
        $this->assertSame('participants.show', $utama['rute']);
    }

    // =================================================================
    // DUA HALAMAN, SATU JAWABAN
    // =================================================================

    /**
     * Inti dari seluruh perubahan ini: peserta yang sama harus menawarkan
     * tindakan yang sama, dibuka dari halaman mana pun.
     */
    public function test_prioritas_dan_follow_up_menawarkan_tindakan_yang_sama(): void
    {
        $this->catatan(now()->subDays(60)->toDateString());

        $this->assertSame(
            $this->labelDari(route('follow-up.index')),
            $this->labelDari(route('prioritas.index')),
            'Tindakan berbeda antara daftar Follow Up dan daftar Prioritas.'
        );
    }

    public function test_tindakan_ikut_berubah_setelah_omzet_dan_baseline_dicatat(): void
    {
        $this->catatan(now()->subDays(60)->toDateString());

        $this->assertContains('Update Omzet', $this->labelDari(route('prioritas.index')));
        $this->assertContains('Catat Kondisi Awal', $this->labelDari(route('prioritas.index')));

        $this->laporkanOmzet();
        $this->catatBaseline();

        $sesudah = $this->labelDari(route('prioritas.index'));

        $this->assertNotContains('Update Omzet', $sesudah);
        $this->assertNotContains('Catat Kondisi Awal', $sesudah);
        $this->assertContains('Catat Pendampingan', $sesudah);
    }

    // =================================================================
    // SATU DAFTAR KERJA, BUKAN DUA
    // =================================================================

    /**
     * Alasan keadaan usaha harus sampai ke halaman tempat orang bekerja.
     *
     * Kalimat "Omzet bulan ini belum diperbarui" dulu hanya bisa dibaca di
     * halaman Prioritas Usaha. Pendamping yang bekerja dari Tugas Saya tidak
     * pernah tahu, jadi diagnosisnya ada tapi tidak pernah sampai ke orang
     * yang bisa mengerjakannya.
     */
    public function test_kartu_tugas_menyebut_keadaan_usaha_bukan_cuma_jadwal(): void
    {
        $this->catatan(now()->subDays(90)->toDateString());

        $respons = $this->actingAs($this->pendamping)
            ->get(route('follow-up.index'))
            ->assertOk();

        // Alasan soal jadwal — sudah lama ada.
        $respons->assertSee('tanpa pendampingan', false);

        // Alasan soal keadaan usaha — inilah yang dulu tidak pernah sampai.
        $respons->assertSee('Omzet bulan ini belum diperbarui');
    }

    public function test_keadaan_usaha_menyebut_legalitas_yang_belum_selesai(): void
    {
        $this->catatan(now()->subDays(90)->toDateString());

        $this->peserta->legalitasRecords()->create([
            'jenis_dokumen' => 'NIB',
            'status' => 'Proses',
        ]);

        $this->actingAs($this->pendamping)
            ->get(route('follow-up.index'))
            ->assertOk()
            ->assertSee('1 dokumen legalitas belum selesai');
    }

    /**
     * Kedua halaman menyebut keadaan usaha yang SAMA PERSIS.
     *
     * Ini inti dari penggabungannya: kalau kalimatnya bisa berbeda, berarti
     * ada dua definisi, dan dua definisi akan menyimpang pada hari pertama
     * ada yang mengubah salah satunya.
     */
    public function test_tugas_saya_dan_prioritas_menyebut_keadaan_usaha_yang_sama(): void
    {
        $this->catatan(now()->subDays(90)->toDateString());
        $this->peserta->legalitasRecords()->create([
            'jenis_dokumen' => 'NIB',
            'status' => 'Proses',
        ]);

        $kelompok = $this->actingAs($this->pendamping)
            ->get(route('follow-up.index'))->assertOk()->viewData('kelompok');

        $baris = collect($kelompok)->flatten(1)
            ->firstWhere(fn ($b) => $b['peserta']->id === $this->peserta->id);

        $keadaan = array_column($baris['keadaan_usaha'], 'alasan');

        $prioritas = $this->actingAs($this->pendamping)
            ->get(route('prioritas.index'))->assertOk()->viewData('kelompok');

        $alasanPrioritas = collect($prioritas)->flatten(1)
            ->firstWhere(fn ($b) => $b['peserta']->id === $this->peserta->id)['alasan'];

        foreach ($keadaan as $kalimat) {
            $this->assertContains(
                $kalimat,
                $alasanPrioritas,
                "Kalimat \"{$kalimat}\" tidak sama di kedua halaman."
            );
        }
    }

    /**
     * Menu Pendamping tidak lagi menawarkan dua daftar untuk satu pertanyaan.
     *
     * Halamannya sendiri TIDAK dihapus — Admin masih memakainya, dan tautan
     * lama tetap bisa dibuka.
     */
    public function test_pendamping_tidak_lagi_punya_dua_daftar_kerja_di_menu(): void
    {
        $this->actingAs($this->pendamping)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('follow-up.index'))
            ->assertDontSee(route('prioritas.index'));

        // Masih bisa dibuka kalau memang dituju langsung.
        $this->actingAs($this->pendamping)
            ->get(route('prioritas.index'))
            ->assertOk();
    }

    public function test_admin_masih_punya_menu_prioritas(): void
    {
        $this->actingAs($this->admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('prioritas.index'));
    }

    /**
     * Halaman Tugas Saya benar-benar menampilkan tombolnya, bukan sekadar
     * menyimpannya di data.
     */
    public function test_halaman_tugas_saya_menampilkan_tombol_kontekstual(): void
    {
        $this->catatan(now()->subDays(90)->toDateString());

        $this->actingAs($this->pendamping)
            ->get(route('follow-up.index'))
            ->assertOk()
            ->assertSee('Update Omzet')
            ->assertSee('Catat Kondisi Awal')
            ->assertSee('#catat-pendampingan', false);
    }
}
