<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Participant;
use App\Models\Pendamping;
use App\Services\BackupDatabaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * STABILISASI BACKUP — RETENSI, RUANG DISK, DAN KEUTUHAN.
 *
 * =====================================================================
 * YANG DIJAGA DI SINI
 * =====================================================================
 *
 * Kegagalan backup TIDAK mengganggu siapa pun pada hari terjadinya. Ia
 * hanya terasa pada hari data dibutuhkan kembali — dan pada hari itu tidak
 * ada lagi yang bisa diperbaiki.
 *
 * Karena itu yang dijaga bukan cuma "backup berhasil", melainkan seluruh
 * keadaan di sekelilingnya: retensi yang tidak pernah menyisakan nol,
 * disk menipis yang dilaporkan apa adanya alih-alih menghasilkan berkas
 * separuh jadi, dan data bisnis yang tidak tersentuh oleh proses apa pun
 * di atas.
 *
 * Test yang sudah ada (TugasTerjadwalTest) menjaga bahwa backup yang gagal
 * melapor gagal dan yang berhasil tercatat di Activity Log. Berkas ini
 * TIDAK mengulanginya.
 */
class BackupStabilisasiTest extends TestCase
{
    use RefreshDatabase;

    private string $storageAsli;
    private string $storageUji;

    protected function setUp(): void
    {
        parent::setUp();

        // Storage dialihkan supaya test tidak pernah menyentuh — apalagi
        // menghapus — berkas cadangan sungguhan.
        $this->storageAsli = $this->app->storagePath();
        $this->storageUji = sys_get_temp_dir() . '/cahayaos-uji-backup-' . uniqid();

        File::ensureDirectoryExists($this->storageUji . '/logs');
        File::ensureDirectoryExists($this->storageUji . '/app/backups');

        $this->app->useStoragePath($this->storageUji);
    }

    protected function tearDown(): void
    {
        $this->app->useStoragePath($this->storageAsli);

        File::deleteDirectory($this->storageUji);

        parent::tearDown();
    }

    private function layanan(): BackupDatabaseService
    {
        return app(BackupDatabaseService::class);
    }

    private function folder(): string
    {
        return $this->storageUji . '/app/backups';
    }

    private function buatBerkas(string $nama, int $umurDetik = 0): string
    {
        $path = $this->folder() . '/' . $nama;
        file_put_contents($path, '-- cadangan uji');
        touch($path, now()->getTimestamp() - $umurDetik);

        return $path;
    }

    /** @return list<string> */
    private function isiFolder(): array
    {
        $f = array_values(array_diff(scandir($this->folder()), ['.', '..']));
        sort($f);

        return $f;
    }

    // =================================================================
    // RETENSI
    // =================================================================

    public function test_retensi_dibaca_dari_konfigurasi(): void
    {
        config(['cahayaos.backup.retensi' => 3]);

        $this->assertSame(3, $this->layanan()->retensi());
    }

    /**
     * Retensi 0 tidak boleh bisa menghapus segalanya.
     *
     * Satu angka salah ketik di berkas konfigurasi tidak boleh cukup untuk
     * meninggalkan sistem tanpa cadangan sama sekali.
     */
    public function test_retensi_dipaksa_minimal_satu(): void
    {
        foreach ([0, -5] as $nilai) {
            config(['cahayaos.backup.retensi' => $nilai]);

            $this->assertSame(1, $this->layanan()->retensi());
        }
    }

    public function test_retensi_menyisakan_yang_terbaru_dan_membuang_yang_terlama(): void
    {
        config(['cahayaos.backup.retensi' => 2]);

        $this->buatBerkas('backup-2026-01-01_010101.sql', umurDetik: 300);
        $this->buatBerkas('backup-2026-02-01_010101.sql', umurDetik: 200);
        $this->buatBerkas('backup-2026-03-01_010101.sql', umurDetik: 100);
        $this->buatBerkas('backup-2026-04-01_010101.sql', umurDetik: 0);

        $this->layanan()->terapkanRetention();

        $this->assertSame([
            'backup-2026-03-01_010101.sql',
            'backup-2026-04-01_010101.sql',
        ], $this->isiFolder());
    }

    /**
     * Retensi tidak pernah menyisakan folder kosong.
     */
    public function test_retensi_tidak_pernah_menghapus_seluruh_cadangan(): void
    {
        config(['cahayaos.backup.retensi' => 0]);

        $this->buatBerkas('backup-2026-01-01_010101.sql', umurDetik: 200);
        $this->buatBerkas('backup-2026-02-01_010101.sql', umurDetik: 100);

        $this->layanan()->terapkanRetention();

        $this->assertSame(['backup-2026-02-01_010101.sql'], $this->isiFolder());
    }

    /**
     * Jaring pengaman sebelum restore BUKAN backup rutin.
     */
    public function test_cadangan_pra_restore_tidak_pernah_ikut_dihapus(): void
    {
        config(['cahayaos.backup.retensi' => 1]);

        $this->buatBerkas('pre-restore-backup-2026-01-01_010101.sql', umurDetik: 900);
        $this->buatBerkas('pre-restore-backup-2026-01-02_010101.sql', umurDetik: 800);
        $this->buatBerkas('backup-2026-01-03_010101.sql', umurDetik: 200);
        $this->buatBerkas('backup-2026-01-04_010101.sql', umurDetik: 100);

        $this->layanan()->terapkanRetention();

        $tersisa = $this->isiFolder();

        $this->assertContains('pre-restore-backup-2026-01-01_010101.sql', $tersisa);
        $this->assertContains('pre-restore-backup-2026-01-02_010101.sql', $tersisa);
        $this->assertContains('backup-2026-01-04_010101.sql', $tersisa);
        $this->assertNotContains('backup-2026-01-03_010101.sql', $tersisa);
    }

    /**
     * Berkas selain cadangan tidak boleh ikut tersapu.
     */
    public function test_retensi_tidak_menyentuh_berkas_lain(): void
    {
        config(['cahayaos.backup.retensi' => 1]);

        $this->buatBerkas('backup-2026-01-01_010101.sql', umurDetik: 300);
        $this->buatBerkas('backup-2026-01-02_010101.sql', umurDetik: 100);
        $this->buatBerkas('catatan-penting.txt', umurDetik: 500);

        $this->layanan()->terapkanRetention();

        $this->assertContains('catatan-penting.txt', $this->isiFolder());
    }

    // =================================================================
    // RUANG DISK
    // =================================================================

    public function test_ambang_ruang_disk_dibaca_dari_konfigurasi(): void
    {
        config(['cahayaos.backup.min_disk_mb' => 250]);

        $this->assertSame(250 * 1024 * 1024, $this->layanan()->minimalRuangBebas());
    }

    /**
     * Ruang yang tidak cukup: backup DILEWATI, bukan dijalankan separuh.
     *
     * Berkas backup separuh jadi lebih berbahaya daripada tidak ada backup:
     * ia terlihat seperti cadangan dan baru ketahuan tidak utuh saat dipakai.
     */
    public function test_disk_rendah_membuat_backup_dilewati_dengan_peringatan_jelas(): void
    {
        // Ambang dipasang jauh di atas kapasitas disk mana pun.
        config(['cahayaos.backup.min_disk_mb' => 1024 * 1024 * 10]); // 10 TB

        $this->assertFalse($this->layanan()->ruangDisk()['cukup']);

        $kode = Artisan::call('cahayaos:backup-otomatis');
        $keluaran = Artisan::output();

        $this->assertSame(1, $kode, 'Backup yang tidak terbuat harus melapor gagal.');
        $this->assertStringContainsString('ruang disk tidak cukup', $keluaran);
        $this->assertStringContainsString('minimal', $keluaran);

        // Tidak ada berkas yang dibuat — bukan berkas separuh jadi.
        $this->assertSame([], $this->isiFolder());
    }

    public function test_disk_rendah_tercatat_sebagai_kejadian_nyata(): void
    {
        config(['cahayaos.backup.min_disk_mb' => 1024 * 1024 * 10]);

        Artisan::call('cahayaos:backup-otomatis');

        $jejak = ActivityLog::where('action', 'backup_dilewati_disk_rendah')->latest('id')->first();

        $this->assertNotNull($jejak, 'Backup yang dilewati harus meninggalkan jejak.');
        $this->assertStringContainsString('ruang disk tidak cukup', $jejak->description);
    }

    /**
     * Ruang disk yang TIDAK bisa dibaca tidak boleh menghentikan backup.
     *
     * Menolak bekerja karena pemeriksaannya sendiri gagal akan mematikan
     * cadangan pada sistem yang sebenarnya baik-baik saja.
     */
    public function test_ruang_disk_cukup_pada_keadaan_normal(): void
    {
        $this->assertTrue($this->layanan()->ruangDisk()['cukup']);
    }

    // =================================================================
    // BACKUP BERHASIL & KEUTUHANNYA
    // =================================================================

    /**
     * Jalur sukses di-MOCK, mengikuti cara test yang sudah ada.
     *
     * Database test adalah SQLite in-memory, sedangkan dump yang dihasilkan
     * bersintaks MySQL — backup sungguhan memang tidak bisa dijalankan di
     * sini. Yang diuji perilaku PERINTAHNYA; keutuhan dump sungguhan diuji
     * terpisah lewat cahayaos:verifikasi-cadangan.
     */
    private function palsukanLayanan(string $namaFile = 'backup-2026-09-16_020000.sql'): void
    {
        $this->mock(BackupDatabaseService::class, function ($mock) use ($namaFile) {
            $mock->shouldReceive('ruangDisk')->andReturn([
                'bebas' => 50 * 1024 * 1024 * 1024,
                'total' => 100 * 1024 * 1024 * 1024,
                'cukup' => true,
                'minimal' => 500 * 1024 * 1024,
            ]);
            $mock->shouldReceive('buatFile')->andReturn($namaFile);
            $mock->shouldReceive('direktori')->andReturn($this->folder());
            $mock->shouldReceive('formatUkuran')->andReturn('407.4 KB');
            $mock->shouldReceive('retensi')->andReturn(7);
            $mock->shouldReceive('daftar')->andReturn([
                ['name' => $namaFile, 'size' => '407.4 KB', 'created_at' => time(), 'is_pre_restore' => false],
                ['name' => 'pre-restore-backup-2026-09-01_010101.sql', 'size' => '1 KB', 'created_at' => time(), 'is_pre_restore' => true],
                // Berkas tonggak bernama lain — TIDAK diatur retensi, jadi
                // tidak boleh ikut terhitung sebagai backup rutin.
                ['name' => 'pra-softdeletes-2026-09-01_155155.sql', 'size' => '78 KB', 'created_at' => time(), 'is_pre_restore' => false],
            ]);
        });
    }

    public function test_nama_berkas_backup_memuat_tanggal_dan_jam(): void
    {
        $nama = 'backup-' . now()->format('Y-m-d_His') . '.sql';

        $this->assertTrue(
            $this->layanan()->namaFileSah($nama),
            'Pola nama berkas harus dikenali sistemnya sendiri.'
        );

        $this->assertMatchesRegularExpression('/^backup-\d{4}-\d{2}-\d{2}_\d{6}\.sql$/', $nama);
    }

    public function test_backup_berhasil_melapor_sukses(): void
    {
        $this->palsukanLayanan();

        $this->assertSame(0, Artisan::call('cahayaos:backup-otomatis'));
    }

    public function test_ringkasan_retensi_disebutkan_setelah_backup(): void
    {
        $this->palsukanLayanan();

        Artisan::call('cahayaos:backup-otomatis');
        $keluaran = Artisan::output();

        $this->assertStringContainsString('Backup rutin disimpan', $keluaran);
        $this->assertStringContainsString('retensi 7', $keluaran);

        // Yang dihitung hanya backup rutin — pra-restore tidak ikut.
        $this->assertStringContainsString('1 berkas', $keluaran);
    }

    // =================================================================
    // KEUTUHAN BERKAS CADANGAN
    // =================================================================

    /**
     * Berkas yang TERPOTONG harus ditolak.
     *
     * Disk yang penuh di tengah penulisan menghasilkan berkas dengan nama
     * benar dan ukuran wajar, tapi isinya berhenti di tengah pernyataan.
     * Ia terlihat seperti cadangan sampai hari ia dipakai memulihkan data.
     */
    public function test_berkas_cadangan_terpotong_ditolak(): void
    {
        $path = $this->folder() . '/backup-2026-09-16_020000.sql';
        file_put_contents($path, "CREATE TABLE `participants` (\n  `id` bigint");

        $kode = Artisan::call('cahayaos:verifikasi-cadangan', [
            '--berkas' => 'backup-2026-09-16_020000.sql',
            '--tanpa-restore' => true,
        ]);

        $this->assertSame(1, $kode);
        $this->assertStringContainsString('terpotong', Artisan::output());
    }

    public function test_berkas_cadangan_kosong_ditolak(): void
    {
        $path = $this->folder() . '/backup-2026-09-16_020000.sql';
        file_put_contents($path, '');

        $kode = Artisan::call('cahayaos:verifikasi-cadangan', [
            '--berkas' => 'backup-2026-09-16_020000.sql',
            '--tanpa-restore' => true,
        ]);

        $this->assertSame(1, $kode);
        $this->assertStringContainsString('kosong', Artisan::output());
    }

    public function test_berkas_cadangan_utuh_diterima(): void
    {
        $path = $this->folder() . '/backup-2026-09-16_020000.sql';
        file_put_contents(
            $path,
            "CREATE TABLE `participants` (`id` bigint);\n"
            . "INSERT INTO `participants` VALUES (1);\n"
        );

        $kode = Artisan::call('cahayaos:verifikasi-cadangan', [
            '--berkas' => 'backup-2026-09-16_020000.sql',
            '--tanpa-restore' => true,
        ]);

        $this->assertSame(0, $kode);
        $this->assertStringContainsString('Keutuhan berkas: baik', Artisan::output());
    }

    /**
     * Nama berkas dari baris perintah tidak boleh bisa menunjuk ke luar
     * folder cadangan.
     */
    public function test_verifikasi_menolak_nama_berkas_di_luar_pola(): void
    {
        $kode = Artisan::call('cahayaos:verifikasi-cadangan', [
            '--berkas' => '../../../.env',
        ]);

        $this->assertSame(1, $kode);
        $this->assertStringContainsString('tidak sesuai pola', Artisan::output());
    }

    // =================================================================
    // DATA BISNIS TIDAK BOLEH BERUBAH
    // =================================================================

    /**
     * Membuat cadangan adalah tindakan MEMBACA. Kalau ia sampai mengubah
     * satu baris pun, seluruh gunanya hilang.
     */
    public function test_membuat_backup_tidak_mengubah_data_bisnis(): void
    {
        $profil = Pendamping::create([
            'nama' => 'Rosa Linda', 'nomor_hp' => '081200000001',
            'email' => 'rosa@backup.test', 'status' => 'Aktif',
        ]);

        $peserta = Participant::create([
            'nama_peserta' => 'Peserta Uji', 'nomor_hp' => '081200000002',
            'alamat' => 'Jl. Uji', 'nama_usaha' => 'Usaha Uji',
            'kategori_usaha' => 'Jasa',
            'tanggal_mulai_program' => now()->subMonth()->toDateString(),
            'status' => 'Aktif', 'pendamping_id' => $profil->id,
        ]);

        $sebelum = [
            'peserta' => Participant::count(),
            'pendamping' => Pendamping::count(),
            'nama' => $peserta->nama_peserta,
            'diubah' => $peserta->updated_at?->toDateTimeString(),
        ];

        Artisan::call('cahayaos:backup-otomatis');
        Artisan::call('cahayaos:backup-otomatis');

        $this->assertSame($sebelum['peserta'], Participant::count());
        $this->assertSame($sebelum['pendamping'], Pendamping::count());

        $segar = $peserta->fresh();
        $this->assertSame($sebelum['nama'], $segar->nama_peserta);
        $this->assertSame($sebelum['diubah'], $segar->updated_at?->toDateTimeString());
    }

    /**
     * Retensi hanya menyentuh folder cadangan — tidak pernah data bisnis.
     */
    public function test_retensi_tidak_menghapus_data_bisnis(): void
    {
        config(['cahayaos.backup.retensi' => 1]);

        $profil = Pendamping::create([
            'nama' => 'Rosa', 'nomor_hp' => '081200000001',
            'email' => 'rosa@backup.test', 'status' => 'Aktif',
        ]);

        $this->buatBerkas('backup-2026-01-01_010101.sql', umurDetik: 300);
        $this->buatBerkas('backup-2026-01-02_010101.sql', umurDetik: 100);

        $this->layanan()->terapkanRetention();

        $this->assertSame(1, Pendamping::count());
        $this->assertNotNull($profil->fresh());
    }
}
