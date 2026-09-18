<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\BackupDatabaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * KEADAAN SALINAN CADANGAN KE LUAR DISK INI.
 *
 * =====================================================================
 * KEADAAN YANG DITEMUKAN
 * =====================================================================
 *
 * Seluruh cadangan tersimpan di disk yang SAMA dengan databasenya. Salinan
 * ke luar sudah tersedia dan sengaja opt-in — ke mana data peserta boleh
 * disalin adalah keputusan pengelola program, bukan keputusan kode.
 *
 * Masalahnya: keadaannya tidak muncul di mana pun yang dilihat Admin.
 *
 *   - Halaman Backup tidak menyebutnya sama sekali.
 *   - Jadwalnya memang TIDAK terdaftar selama folder tujuan kosong, jadi
 *     `schedule:list` pun tidak menunjukkan apa-apa.
 *   - Perintahnya berhenti dengan kode SUKSES dan tanpa Activity Log ketika
 *     belum diatur — senyap yang disengaja.
 *
 * Ketiganya masuk akal sendiri-sendiri, tetapi bersama-sama menghasilkan
 * satu akibat: fitur yang mati tidak bisa dibedakan dari fitur yang
 * berjalan lancar. Keduanya sama-sama tidak meninggalkan jejak.
 *
 * Pada mesin ini, salinan luar memang belum diatur — dan tidak ada satu pun
 * halaman yang memberitahukannya.
 *
 * =====================================================================
 * YANG DIJAGA DI SINI
 * =====================================================================
 *
 * Tiga keadaan harus bisa dibedakan Admin dalam sekali lihat: belum diatur,
 * aktif dan sehat, serta diatur tetapi tujuannya tidak bisa ditulis — yang
 * terakhir adalah keadaan paling berbahaya, karena dari luar tampak seperti
 * sudah terlindungi.
 */
class StatusSalinanLuarTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private string $tujuan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin Uji',
            'email' => 'admin@salinan.test',
            'password' => bcrypt('rahasia123'),
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
        ]);

        $this->tujuan = sys_get_temp_dir() . '/cahayaos-luar-' . uniqid();
        File::ensureDirectoryExists($this->tujuan);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->tujuan);

        parent::tearDown();
    }

    private function keadaan(): array
    {
        return app(BackupDatabaseService::class)->keadaanSalinanLuar();
    }

    private function halaman()
    {
        return $this->actingAs($this->admin)->get(route('backup-database.index'));
    }

    // =================================================================
    // BELUM DIATUR
    // =================================================================

    public function test_belum_diatur_dilaporkan_apa_adanya(): void
    {
        config(['cahayaos.backup.folder_luar' => null]);

        $keadaan = $this->keadaan();

        $this->assertFalse($keadaan['aktif']);
        $this->assertNull($keadaan['folder']);
        $this->assertSame(0, $keadaan['jumlah']);
    }

    /**
     * Admin harus diberi tahu bahwa seluruh cadangan berbagi satu disk.
     *
     * Ini bukan peringatan yang berlebihan: tanpa kalimat itu, daftar berisi
     * sepuluh berkas cadangan justru memberi rasa aman yang keliru.
     */
    public function test_halaman_backup_menerangkan_belum_diatur(): void
    {
        config(['cahayaos.backup.folder_luar' => null]);

        $this->halaman()
            ->assertOk()
            ->assertSee('belum diatur')
            ->assertSee('disk yang sama dengan databasenya', false)
            ->assertSee('pasang-cadangan-luar.ps1');
    }

    // =================================================================
    // AKTIF DAN SEHAT
    // =================================================================

    public function test_aktif_melaporkan_jumlah_dan_waktu_salinan_terbaru(): void
    {
        config(['cahayaos.backup.folder_luar' => $this->tujuan]);

        file_put_contents($this->tujuan . '/backup-2026-09-15_020000.sql', '-- lama');
        touch($this->tujuan . '/backup-2026-09-15_020000.sql', now()->subDays(2)->getTimestamp());

        file_put_contents($this->tujuan . '/backup-2026-09-17_020000.sql', '-- terbaru');
        $terbaru = now()->subHours(3)->getTimestamp();
        touch($this->tujuan . '/backup-2026-09-17_020000.sql', $terbaru);

        $keadaan = $this->keadaan();

        $this->assertTrue($keadaan['aktif']);
        $this->assertTrue($keadaan['bisa_ditulis']);
        $this->assertSame(2, $keadaan['jumlah']);
        $this->assertSame($terbaru, $keadaan['terbaru']);
    }

    public function test_halaman_backup_menerangkan_salinan_aktif(): void
    {
        config(['cahayaos.backup.folder_luar' => $this->tujuan]);

        file_put_contents($this->tujuan . '/backup-2026-09-17_020000.sql', '-- dump');

        $this->halaman()
            ->assertOk()
            ->assertSee('aktif')
            ->assertSee('Terakhir disalin')
            ->assertDontSee('belum diatur');
    }

    /**
     * Waktunya ditampilkan dalam zona waktu aplikasi, bukan UTC.
     *
     * Sejak Carbon 3, createFromTimestamp() default-nya UTC. Kolom "Dibuat"
     * di halaman yang sama pernah tampil mundur tujuh jam karena itu, dan
     * kalimat "Terakhir disalin" akan mengulangi kesalahan yang sama kalau
     * zona waktunya tidak disebut.
     */
    public function test_waktu_salinan_memakai_zona_waktu_aplikasi(): void
    {
        config(['cahayaos.backup.folder_luar' => $this->tujuan]);

        $berkas = $this->tujuan . '/backup-2026-09-17_020000.sql';
        file_put_contents($berkas, '-- dump');

        // Jam yang bedanya jelas antara UTC dan WIB.
        $waktu = Carbon::create(2026, 9, 17, 2, 0, 0, config('app.timezone'));
        touch($berkas, $waktu->getTimestamp());

        $this->halaman()
            ->assertOk()
            ->assertSee($waktu->translatedFormat('d M Y H:i'));
    }

    /** Folder kosong tetap dilaporkan aktif, tapi tanpa salinan. */
    public function test_aktif_tanpa_satu_pun_salinan(): void
    {
        config(['cahayaos.backup.folder_luar' => $this->tujuan]);

        $keadaan = $this->keadaan();

        $this->assertTrue($keadaan['aktif']);
        $this->assertSame(0, $keadaan['jumlah']);
        $this->assertNull($keadaan['terbaru']);

        $this->halaman()->assertOk()->assertSee('Belum ada satu pun salinan di sana.');
    }

    // =================================================================
    // DIATUR TAPI TUJUANNYA TIDAK ADA — KEADAAN PALING BERBAHAYA
    // =================================================================

    /**
     * Disk eksternal yang dicabut harus terlihat jelas.
     *
     * Inilah keadaan yang paling menipu: konfigurasinya ada, jadwalnya
     * terdaftar, dan dari luar sistem tampak sudah terlindungi — padahal
     * tidak ada satu pun salinan yang benar-benar sampai ke sana.
     */
    public function test_folder_tujuan_hilang_dilaporkan_sebagai_bahaya(): void
    {
        config(['cahayaos.backup.folder_luar' => $this->tujuan . '-dicabut']);

        $keadaan = $this->keadaan();

        $this->assertTrue($keadaan['aktif']);
        $this->assertFalse($keadaan['folder_ada']);
        $this->assertFalse($keadaan['bisa_ditulis']);

        $this->halaman()
            ->assertOk()
            ->assertSee('TIDAK BISA DITULIS')
            ->assertSee('tidak ditemukan');
    }

    /**
     * Halaman Backup tidak boleh gagal terbuka hanya karena tujuannya
     * bermasalah — justru halaman itulah yang dibutuhkan saat keadaan
     * sedang tidak normal.
     */
    public function test_halaman_tetap_terbuka_walau_tujuannya_bermasalah(): void
    {
        config(['cahayaos.backup.folder_luar' => 'Z:\\tidak-ada-sama-sekali']);

        $this->halaman()
            ->assertOk()
            ->assertSee('Daftar File Backup');
    }
}
