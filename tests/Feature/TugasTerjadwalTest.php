<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Services\BackupDatabaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schedule;
use Tests\TestCase;

/**
 * TUGAS TERJADWAL — BACKUP OTOMATIS & PEMBERSIHAN BERKAS SISA.
 *
 * ==========================================================================
 * YANG SEBENARNYA DIUJI DI SINI
 * ==========================================================================
 *
 * Dua tugas ini berjalan tanpa penonton, di jam dua pagi, di server yang
 * tidak dibuka siapa pun. Kesalahan pada keduanya punya sifat yang sama:
 * tidak ada yang tahu sampai hari data dibutuhkan.
 *
 * Maka yang diperiksa bukan cuma "perintahnya jalan", melainkan:
 * - penghapusan TIDAK menyentuh berkas yang masih muda (bisa saja sedang
 *   diunduh seseorang saat itu juga),
 * - penghapusan TIDAK menyentuh berkas milik orang lain di folder yang sama,
 * - backup yang GAGAL benar-benar melapor gagal, bukan diam-diam sukses.
 */
class TugasTerjadwalTest extends TestCase
{
    use RefreshDatabase;

    private string $folder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->folder = storage_path('app');
    }

    /** Membuat berkas uji dengan umur tertentu, lalu mencatatnya untuk dibereskan. */
    private function berkas(string $nama, int $umurJam): string
    {
        $path = $this->folder . '/' . $nama;
        file_put_contents($path, 'isi-uji');
        touch($path, now()->subHours($umurJam)->getTimestamp());

        return $path;
    }

    protected function tearDown(): void
    {
        // Berkas uji memakai penanda 'uji' pada namanya supaya tidak mungkin
        // bertabrakan dengan berkas ekspor sungguhan milik pengembang.
        foreach (glob($this->folder . '/*uji-terjadwal*') ?: [] as $sisa) {
            @unlink($sisa);
        }

        parent::tearDown();
    }

    // ==================================================================
    // A. PEMBERSIHAN BERKAS EKSPOR SISA
    // ==================================================================

    public function test_menghapus_berkas_ekspor_yang_sudah_tua(): void
    {
        $tua = $this->berkas('laporan-lengkap-uji-terjadwal-tua.zip', 48);

        $this->artisan('cahayaos:bersihkan-berkas-sementara')->assertExitCode(0);

        $this->assertFileDoesNotExist($tua);
    }

    public function test_tidak_menyentuh_berkas_yang_masih_muda(): void
    {
        // Berkas berumur satu jam bisa saja SEDANG diunduh detik ini juga.
        // Menghapusnya berarti menggagalkan unduhan orang yang sedang menunggu.
        $muda = $this->berkas('laporan-lengkap-uji-terjadwal-muda.zip', 1);

        $this->artisan('cahayaos:bersihkan-berkas-sementara')->assertExitCode(0);

        $this->assertFileExists($muda);
    }

    public function test_tidak_menyentuh_berkas_lain_di_folder_yang_sama(): void
    {
        // Folder storage/app bukan milik fitur ekspor saja. Perintah yang
        // menyapu "semua .zip" suatu hari akan menghapus berkas yang ditaruh
        // orang lain di sana dengan sengaja.
        $milikOrangLain = $this->berkas('arsip-penting-uji-terjadwal.zip', 999);

        $this->artisan('cahayaos:bersihkan-berkas-sementara')->assertExitCode(0);

        $this->assertFileExists($milikOrangLain);
    }

    public function test_mode_lihat_saja_tidak_menghapus_apa_pun(): void
    {
        $tua = $this->berkas('laporan-lengkap-uji-terjadwal-intip.zip', 48);

        $this->artisan('cahayaos:bersihkan-berkas-sementara', ['--lihat-saja' => true])
            ->assertExitCode(0);

        $this->assertFileExists($tua);
    }

    public function test_batas_umur_bisa_diatur(): void
    {
        $tigaJam = $this->berkas('laporan-lengkap-uji-terjadwal-3jam.zip', 3);

        // Dengan batas bawaan 24 jam berkas ini selamat…
        $this->artisan('cahayaos:bersihkan-berkas-sementara')->assertExitCode(0);
        $this->assertFileExists($tigaJam);

        // …tapi dengan batas 2 jam ia ikut tersapu.
        $this->artisan('cahayaos:bersihkan-berkas-sementara', ['--umur' => 2])->assertExitCode(0);
        $this->assertFileDoesNotExist($tigaJam);
    }

    public function test_menolak_umur_nol_jam(): void
    {
        // --umur=0 berarti "hapus juga yang baru saja dibuat", dan berkas yang
        // baru dibuat justru yang paling mungkin sedang dikirim ke pengguna.
        $baru = $this->berkas('laporan-lengkap-uji-terjadwal-baru.zip', 0);

        $this->artisan('cahayaos:bersihkan-berkas-sementara', ['--umur' => 0])
            ->expectsOutputToContain('minimal 1 jam')
            ->assertExitCode(1);

        $this->assertFileExists($baru);
    }

    // ==================================================================
    // B. BACKUP OTOMATIS
    // ==================================================================

    public function test_backup_yang_gagal_melapor_gagal_bukan_diam_diam_sukses(): void
    {
        // Ini perilaku terpenting dari seluruh berkas ini. Tugas terjadwal
        // yang gagal senyap membuat Admin merasa punya cadangan padahal tidak
        // — dan itu baru ketahuan pada hari data benar-benar dibutuhkan.
        $this->mock(BackupDatabaseService::class, function ($mock) {
            // Sejak backup memeriksa ruang disk lebih dulu, mock-nya harus
            // ikut menjawab pemeriksaan itu — kalau tidak, yang diuji bukan
            // lagi perilaku backupnya melainkan mock yang tidak lengkap.
            $mock->shouldReceive('ruangDisk')->andReturn([
                'bebas' => 50 * 1024 * 1024 * 1024,
                'total' => 100 * 1024 * 1024 * 1024,
                'cukup' => true,
                'minimal' => 500 * 1024 * 1024,
            ]);
            $mock->shouldReceive('buatFile')
                ->andThrow(new \RuntimeException('disk penuh'));
        });

        $this->artisan('cahayaos:backup-otomatis')
            ->expectsOutputToContain('disk penuh')
            ->assertExitCode(1);

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'backup_terjadwal_gagal',
        ]);
    }

    public function test_backup_yang_berhasil_tercatat_di_activity_log(): void
    {
        // Activity Log adalah satu-satunya tempat Admin bisa memastikan
        // backup semalam benar-benar terjadi, tanpa perlu akses ke server.
        $this->mock(BackupDatabaseService::class, function ($mock) {
            // Sejak backup memeriksa ruang disk lebih dulu, mock-nya harus
            // ikut menjawab pemeriksaan itu — kalau tidak, yang diuji bukan
            // lagi perilaku backupnya melainkan mock yang tidak lengkap.
            $mock->shouldReceive('ruangDisk')->andReturn([
                'bebas' => 50 * 1024 * 1024 * 1024,
                'total' => 100 * 1024 * 1024 * 1024,
                'cukup' => true,
                'minimal' => 500 * 1024 * 1024,
            ]);
            $mock->shouldReceive('buatFile')->andReturn('backup-2026-09-13_020000.sql');
            $mock->shouldReceive('direktori')->andReturn(storage_path('app/backups'));
            $mock->shouldReceive('formatUkuran')->andReturn('1.5 MB');
            $mock->shouldReceive('retensi')->andReturn(10);
            $mock->shouldReceive('daftar')->andReturn([
                ['name' => 'backup-2026-09-13_020000.sql', 'size' => '1.5 MB', 'created_at' => time(), 'is_pre_restore' => false],
            ]);
        });

        $this->artisan('cahayaos:backup-otomatis')->assertExitCode(0);

        $log = ActivityLog::where('action', 'backup_terjadwal_dibuat')->first();

        $this->assertNotNull($log);
        $this->assertStringContainsString('backup-2026-09-13_020000.sql', $log->description);
    }

    // ==================================================================
    // C. JADWALNYA BENAR-BENAR TERDAFTAR
    // ==================================================================

    public function test_kedua_tugas_terdaftar_di_scheduler(): void
    {
        // Perintah yang ada tapi tidak terjadwal sama saja dengan tidak ada.
        $terdaftar = collect(Schedule::events())
            ->map(fn ($e) => $e->command ?? '')
            ->implode(' | ');

        $this->assertStringContainsString('cahayaos:backup-otomatis', $terdaftar);
        $this->assertStringContainsString('cahayaos:bersihkan-berkas-sementara', $terdaftar);
    }
}
