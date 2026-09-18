<?php

namespace Tests\Feature;

use App\Models\Participant;
use App\Models\Pendamping;
use App\Models\PendampinganRecord;
use App\Models\PendampingTarget;
use App\Models\User;
use App\Services\KpiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * BINAAN PINDAH — JASA YANG SUDAH DIKERJAKAN TIDAK IKUT PINDAH.
 *
 * =====================================================================
 * PERILAKU YANG DIJAGA
 * =====================================================================
 *
 * Sistem ini membedakan dua hal yang mudah tertukar:
 *
 *   AKTIVITAS  dikreditkan lewat PendampinganRecord.pendamping_id —
 *              siapa yang BENAR-BENAR mengerjakan, tersimpan per catatan.
 *
 *   BEBAN      dihitung lewat Participant.pendamping_id — siapa yang
 *              MEMEGANG binaan itu sekarang.
 *
 * Akibatnya, saat seorang peserta dipindahkan: pendamping lama tetap
 * memegang jasa atas pekerjaan yang sudah ia lakukan, sementara beban
 * kerjanya berkurang. Pendamping baru menerima bebannya, bukan riwayat
 * kerja orang lain.
 *
 * =====================================================================
 * KENAPA PERLU DIJAGA
 * =====================================================================
 *
 * Pembedaan ini tidak terlihat di layar mana pun, dan "menyederhanakannya"
 * dengan menghitung aktivitas lewat peserta akan tampak lebih rapi. Tapi
 * akibatnya berat dan sunyi: seorang pendamping kehilangan jasa atas
 * berbulan-bulan kerjanya begitu binaannya dipindahkan, dan yang menerima
 * mendapat angka kerja yang tidak pernah ia kerjakan.
 *
 * Tidak ada error, tidak ada halaman rusak — hanya penilaian yang keliru
 * terhadap dua orang sekaligus.
 */
class PemindahanBinaanTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Pendamping $rosa;
    private Pendamping $dewi;
    private Participant $peserta;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-06-20 09:00', 'Asia/Jakarta'));

        $this->admin = User::create([
            'name' => 'Admin', 'email' => 'admin@pindah.test',
            'password' => 'password123', 'role' => 'admin', 'is_active' => true,
        ]);

        $this->rosa = Pendamping::create([
            'nama' => 'Rosa', 'nomor_hp' => '081200000001',
            'email' => 'rosa@pindah.test', 'status' => 'Aktif',
        ]);

        $this->dewi = Pendamping::create([
            'nama' => 'Dewi', 'nomor_hp' => '081200000002',
            'email' => 'dewi@pindah.test', 'status' => 'Aktif',
        ]);

        $this->peserta = Participant::create([
            'nama_peserta' => 'Peserta Uji', 'nomor_hp' => '081200000003',
            'alamat' => 'Jl. Uji', 'nama_usaha' => 'Usaha Uji',
            'kategori_usaha' => 'Jasa',
            'tanggal_mulai_program' => '2026-01-10',
            'status' => 'Aktif', 'pendamping_id' => $this->rosa->id,
        ]);

        // Rosa mengerjakan tiga kali pada bulan berjalan.
        foreach ([5, 10, 15] as $hari) {
            PendampinganRecord::create([
                'participant_id' => $this->peserta->id,
                'pendamping_id' => $this->rosa->id,
                'tanggal' => '2026-06-' . str_pad((string) $hari, 2, '0', STR_PAD_LEFT),
                'jenis_kegiatan' => PendampinganRecord::KONSULTASI,
                'materi' => 'Materi uji',
            ]);
        }

        foreach ([$this->rosa, $this->dewi] as $p) {
            PendampingTarget::create([
                'pendamping_id' => $p->id,
                'periode' => PendampingTarget::PERIODE_BULANAN,
                'tahun' => 2026,
                'bulan' => 6,
                'target_pendampingan' => 10,
                'target_jumlah_binaan' => 5,
            ]);
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function rapor(Pendamping $p): array
    {
        return app(KpiService::class)->untukSatu(
            $p,
            Carbon::parse('2026-06-01')->startOfDay(),
            Carbon::parse('2026-06-30')->endOfDay(),
        );
    }

    private function pindahkanKeDewi(): void
    {
        $this->actingAs($this->admin)
            ->put(route('participants.update', $this->peserta), [
                'nama_peserta' => $this->peserta->nama_peserta,
                'nomor_hp' => $this->peserta->nomor_hp,
                'alamat' => $this->peserta->alamat,
                'nama_usaha' => $this->peserta->nama_usaha,
                'kategori_usaha' => $this->peserta->kategori_usaha,
                'tanggal_mulai_program' => $this->peserta->tanggal_mulai_program->toDateString(),
                'status' => $this->peserta->status,
                'pendamping_id' => $this->dewi->id,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(
            $this->dewi->id,
            $this->peserta->fresh()->pendamping_id,
            'Pemindahannya sendiri harus berhasil.'
        );
    }

    // =================================================================
    // SEBELUM PINDAH
    // =================================================================

    public function test_sebelum_pindah_jasa_dan_beban_ada_pada_pendamping_lama(): void
    {
        $rosa = $this->rapor($this->rosa);
        $dewi = $this->rapor($this->dewi);

        $this->assertSame(3, $rosa['indikator']['target_pendampingan']['aktual']);
        $this->assertSame(1, $rosa['indikator']['target_jumlah_binaan']['aktual']);

        $this->assertSame(0, $dewi['indikator']['target_pendampingan']['aktual']);
        $this->assertSame(0, $dewi['indikator']['target_jumlah_binaan']['aktual']);
    }

    // =================================================================
    // SESUDAH PINDAH
    // =================================================================

    /**
     * Inti persoalannya: pekerjaan yang sudah dilakukan tetap menjadi jasa
     * orang yang mengerjakannya.
     */
    public function test_jasa_pendampingan_tetap_pada_yang_mengerjakan(): void
    {
        $this->pindahkanKeDewi();

        $rosa = $this->rapor($this->rosa);

        $this->assertSame(
            3,
            $rosa['indikator']['target_pendampingan']['aktual'],
            'Rosa kehilangan jasa atas pekerjaan yang sudah ia lakukan.'
        );
    }

    /**
     * Sisi sebaliknya: yang menerima tidak mewarisi angka kerja orang lain.
     */
    public function test_pendamping_baru_tidak_mewarisi_angka_kerja_orang_lain(): void
    {
        $this->pindahkanKeDewi();

        $dewi = $this->rapor($this->dewi);

        $this->assertSame(
            0,
            $dewi['indikator']['target_pendampingan']['aktual'],
            'Dewi menerima angka kerja yang tidak pernah ia kerjakan.'
        );
    }

    /**
     * Beban kerja — dan HANYA beban — yang berpindah.
     */
    public function test_beban_kerja_berpindah_ke_pendamping_baru(): void
    {
        $this->pindahkanKeDewi();

        $this->assertSame(0, $this->rapor($this->rosa)['indikator']['target_jumlah_binaan']['aktual']);
        $this->assertSame(1, $this->rapor($this->dewi)['indikator']['target_jumlah_binaan']['aktual']);
    }

    /**
     * Sesudah pindah, binaannya benar-benar berpindah tangan: yang lama
     * tidak lagi boleh membukanya, yang baru boleh.
     */
    public function test_akses_ikut_berpindah(): void
    {
        $akunRosa = User::create([
            'name' => 'Rosa', 'email' => 'rosa.akun@pindah.test',
            'password' => 'password123', 'role' => 'pendamping',
            'pendamping_id' => $this->rosa->id, 'is_active' => true,
        ]);

        $akunDewi = User::create([
            'name' => 'Dewi', 'email' => 'dewi.akun@pindah.test',
            'password' => 'password123', 'role' => 'pendamping',
            'pendamping_id' => $this->dewi->id, 'is_active' => true,
        ]);

        $this->actingAs($akunRosa)
            ->get(route('participants.show', $this->peserta))
            ->assertOk();

        $this->pindahkanKeDewi();

        $this->actingAs($akunRosa)
            ->get(route('participants.show', $this->peserta))
            ->assertForbidden();

        $this->actingAs($akunDewi)
            ->get(route('participants.show', $this->peserta))
            ->assertOk();
    }

    /**
     * Catatan lamanya tetap utuh dan tetap menyebut siapa yang mengerjakan.
     *
     * Kalau pendamping_id di catatan ikut ditimpa saat pemindahan, riwayat
     * pendampingan akan berbohong tentang siapa yang datang ke lapangan.
     */
    public function test_catatan_lama_tetap_menyebut_pelaksana_aslinya(): void
    {
        $this->pindahkanKeDewi();

        $catatan = PendampinganRecord::where('participant_id', $this->peserta->id)->get();

        $this->assertCount(3, $catatan);

        foreach ($catatan as $c) {
            $this->assertSame(
                $this->rosa->id,
                $c->pendamping_id,
                'Riwayat pendampingan berubah menyebut orang yang tidak mengerjakannya.'
            );
        }
    }
}
