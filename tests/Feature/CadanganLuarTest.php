<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * SALINAN CADANGAN KE LUAR DISK INI.
 *
 * =====================================================================
 * RISIKO YANG DITUTUP
 * =====================================================================
 *
 * Cadangan CahayaOS tersimpan di disk yang SAMA dengan databasenya. Kalau
 * disk itu rusak, komputernya hilang, atau kena ransomware, database dan
 * seluruh cadangannya lenyap bersamaan — praktis sama dengan tidak pernah
 * punya cadangan.
 *
 * =====================================================================
 * MATI KALAU TIDAK DIKONFIGURASI — DAN ITU BUKAN KEGAGALAN
 * =====================================================================
 *
 * Tujuan salinan sengaja kosong secara bawaan. Berkas cadangan memuat data
 * pribadi peserta, dan ke mana data itu boleh disalin adalah keputusan
 * pengelola program, bukan keputusan kode.
 *
 * Karena itu tanpa konfigurasi perintahnya berhenti dengan penjelasan dan
 * kode SUKSES: pilihan yang belum diambil bukan kegagalan, dan menandainya
 * sebagai galat hanya akan membuat jadwal harian melapor merah setiap hari.
 */
class CadanganLuarTest extends TestCase
{
    use RefreshDatabase;

    private string $storageAsli;
    private string $storageUji;
    private string $tujuan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->storageAsli = $this->app->storagePath();
        $this->storageUji = sys_get_temp_dir() . '/cahayaos-uji-luar-' . uniqid();
        $this->tujuan = sys_get_temp_dir() . '/cahayaos-tujuan-luar-' . uniqid();

        File::ensureDirectoryExists($this->storageUji . '/logs');
        File::ensureDirectoryExists($this->storageUji . '/app/backups');
        File::ensureDirectoryExists($this->tujuan);

        $this->app->useStoragePath($this->storageUji);
    }

    protected function tearDown(): void
    {
        $this->app->useStoragePath($this->storageAsli);

        File::deleteDirectory($this->storageUji);
        File::deleteDirectory($this->tujuan);

        parent::tearDown();
    }

    private function folderLokal(): string
    {
        return $this->storageUji . '/app/backups';
    }

    private function buatCadanganLokal(string $nama, string $isi = '-- dump uji', int $umurDetik = 0): string
    {
        $path = $this->folderLokal() . '/' . $nama;
        file_put_contents($path, $isi);
        touch($path, now()->getTimestamp() - $umurDetik);

        return $path;
    }

    /** @return list<string> */
    private function isiTujuan(): array
    {
        $f = array_values(array_diff(scandir($this->tujuan), ['.', '..']));
        sort($f);

        return $f;
    }

    private function salin(array $opsi = []): int
    {
        return Artisan::call('cahayaos:salin-cadangan-luar', $opsi);
    }

    // =================================================================
    // KEADAAN BELUM DIKONFIGURASI
    // =================================================================

    public function test_tanpa_konfigurasi_dilewati_bukan_gagal(): void
    {
        config(['cahayaos.backup.folder_luar' => null]);

        $this->buatCadanganLokal('backup-2026-09-16_020000.sql');

        $this->assertSame(0, $this->salin(), 'Pilihan yang belum diambil bukan kegagalan.');
        $this->assertStringContainsString('belum diatur', Artisan::output());
        $this->assertSame([], $this->isiTujuan());
    }

    // =================================================================
    // PENYALINAN
    // =================================================================

    public function test_cadangan_terbaru_disalin_ke_tujuan(): void
    {
        config(['cahayaos.backup.folder_luar' => $this->tujuan]);

        $this->buatCadanganLokal('backup-2026-09-15_020000.sql', '-- lama', umurDetik: 200);
        $this->buatCadanganLokal('backup-2026-09-16_020000.sql', '-- terbaru', umurDetik: 0);

        $this->assertSame(0, $this->salin());

        $this->assertSame(['backup-2026-09-16_020000.sql'], $this->isiTujuan());
    }

    /**
     * Salinannya harus identik dengan sumbernya.
     *
     * Kalau berbeda, verifikasi yang sudah dijalankan pada berkas lokal
     * tidak lagi berlaku untuk salinannya — dan salinan itulah yang akan
     * dipakai justru ketika yang lokal sudah tidak ada.
     */
    public function test_salinan_identik_dengan_sumbernya(): void
    {
        config(['cahayaos.backup.folder_luar' => $this->tujuan]);

        $isi = "CREATE TABLE `participants` (`id` bigint);\nINSERT INTO `participants` VALUES (1);\n";
        $this->buatCadanganLokal('backup-2026-09-16_020000.sql', $isi);

        $this->salin();

        $this->assertSame(
            $isi,
            file_get_contents($this->tujuan . '/backup-2026-09-16_020000.sql')
        );
    }

    public function test_penyalinan_tercatat_di_activity_log(): void
    {
        config(['cahayaos.backup.folder_luar' => $this->tujuan]);

        $this->buatCadanganLokal('backup-2026-09-16_020000.sql');

        $this->salin();

        $jejak = ActivityLog::where('action', 'cadangan_luar_disalin')->latest('id')->first();

        $this->assertNotNull($jejak);
        $this->assertStringContainsString('backup-2026-09-16_020000.sql', $jejak->description);
    }

    // =================================================================
    // KEADAAN YANG SALAH
    // =================================================================

    public function test_folder_tujuan_hilang_melapor_gagal_dengan_jelas(): void
    {
        config(['cahayaos.backup.folder_luar' => $this->tujuan . '-tidak-ada']);

        $this->buatCadanganLokal('backup-2026-09-16_020000.sql');

        $this->assertSame(1, $this->salin());
        $this->assertStringContainsString('tidak ditemukan', Artisan::output());

        $this->assertNotNull(
            ActivityLog::where('action', 'cadangan_luar_gagal')->first(),
            'Kegagalan menyalin harus meninggalkan jejak.'
        );
    }

    public function test_tanpa_cadangan_lokal_melapor_gagal(): void
    {
        config(['cahayaos.backup.folder_luar' => $this->tujuan]);

        $this->assertSame(1, $this->salin());
        $this->assertStringContainsString('Tidak ada cadangan lokal', Artisan::output());
    }

    // =================================================================
    // RETENSI DI TUJUAN
    // =================================================================

    public function test_retensi_tujuan_menyisakan_yang_terbaru(): void
    {
        config([
            'cahayaos.backup.folder_luar' => $this->tujuan,
            'cahayaos.backup.retensi_luar' => 2,
        ]);

        foreach (['01', '02', '03'] as $i => $hari) {
            file_put_contents($this->tujuan . "/backup-2026-09-{$hari}_020000.sql", '-- lama');
            touch($this->tujuan . "/backup-2026-09-{$hari}_020000.sql", now()->getTimestamp() - (500 - $i * 100));
        }

        $this->buatCadanganLokal('backup-2026-09-16_020000.sql');

        $this->salin();

        $tersisa = $this->isiTujuan();

        $this->assertCount(2, $tersisa);
        $this->assertContains('backup-2026-09-16_020000.sql', $tersisa, 'Yang baru disalin harus ikut dipertahankan.');
        $this->assertNotContains('backup-2026-09-01_020000.sql', $tersisa);
    }

    /**
     * Retensi tujuan tidak boleh bisa mengosongkan folder.
     */
    public function test_retensi_tujuan_dipaksa_minimal_satu(): void
    {
        config([
            'cahayaos.backup.folder_luar' => $this->tujuan,
            'cahayaos.backup.retensi_luar' => 0,
        ]);

        $this->buatCadanganLokal('backup-2026-09-16_020000.sql');

        $this->salin();

        $this->assertCount(1, $this->isiTujuan());
    }

    /**
     * Berkas selain cadangan di folder tujuan tidak boleh ikut tersapu —
     * folder cloud atau disk eksternal biasanya dipakai bersama.
     */
    public function test_berkas_lain_di_folder_tujuan_tidak_disentuh(): void
    {
        config([
            'cahayaos.backup.folder_luar' => $this->tujuan,
            'cahayaos.backup.retensi_luar' => 1,
        ]);

        file_put_contents($this->tujuan . '/dokumen-penting.pdf', 'bukan cadangan');
        file_put_contents($this->tujuan . '/backup-2026-09-01_020000.sql', '-- lama');
        touch($this->tujuan . '/backup-2026-09-01_020000.sql', now()->getTimestamp() - 500);

        $this->buatCadanganLokal('backup-2026-09-16_020000.sql');

        $this->salin();

        $this->assertContains('dokumen-penting.pdf', $this->isiTujuan());
    }
}
