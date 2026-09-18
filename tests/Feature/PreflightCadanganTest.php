<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * CADANGAN YANG BERHENTI DIBUAT HARUS KETAHUAN.
 *
 * =====================================================================
 * KEGAGALAN YANG DIJAGA
 * =====================================================================
 *
 * routes/console.php menjadwalkan backup harian pukul 02:00. Tapi jadwal
 * Laravel hanya bergerak kalau ada yang memanggil "php artisan
 * schedule:run" setiap menit DARI LUAR aplikasi.
 *
 * Kalau pemanggil itu tidak pernah dipasang — atau terhapus, atau rusak
 * setelah PHP di-upgrade — backup berhenti dibuat dan TIDAK ADA yang
 * gagal: memang tidak ada yang dijalankan untuk gagal. Halaman tetap
 * terbuka, seluruh test tetap hijau, dan ketiadaan cadangan baru ketahuan
 * pada hari seseorang benar-benar membutuhkannya.
 *
 * Terjadi sungguhan di mesin pengembangan ini: tiga proyek lain punya
 * Scheduled Task, CahayaOS tidak.
 */
class PreflightCadanganTest extends TestCase
{
    private string $storageAsli;
    private string $storageUji;

    protected function setUp(): void
    {
        parent::setUp();

        // Storage dialihkan ke folder sementara supaya test TIDAK pernah
        // menyentuh berkas cadangan sungguhan — apalagi menghapusnya.
        $this->storageAsli = $this->app->storagePath();
        $this->storageUji = sys_get_temp_dir() . '/cahayaos-uji-cadangan-' . uniqid();

        File::ensureDirectoryExists($this->storageUji . '/logs');
        File::ensureDirectoryExists($this->storageUji . '/framework/views');

        $this->app->useStoragePath($this->storageUji);
    }

    protected function tearDown(): void
    {
        $this->app->useStoragePath($this->storageAsli);

        File::deleteDirectory($this->storageUji);

        parent::tearDown();
    }

    private function preflight(): string
    {
        Artisan::call('cahayaos:preflight');

        return Artisan::output();
    }

    private function buatCadangan(string $nama, int $umurHari): void
    {
        $folder = $this->storageUji . '/app/backups';
        File::ensureDirectoryExists($folder);

        $path = $folder . '/' . $nama;
        file_put_contents($path, '-- cadangan uji');
        touch($path, now()->subDays($umurHari)->getTimestamp());
    }

    public function test_tanpa_folder_cadangan_dilaporkan_sebagai_masalah(): void
    {
        $keluaran = $this->preflight();

        $this->assertStringContainsString('Cadangan database', $keluaran);
        $this->assertStringContainsString('Belum pernah ada backup', $keluaran);
    }

    public function test_cadangan_basi_dilaporkan_beserta_umurnya(): void
    {
        $this->buatCadangan('backup-lama.sql', 9);

        $keluaran = $this->preflight();

        $this->assertStringContainsString('Cadangan terakhir dibuat 9 hari lalu', $keluaran);
    }

    /**
     * Pesannya harus menyebut SEBAB yang paling mungkin dan CARA
     * memperbaikinya — bukan sekadar mengabarkan bahwa cadangannya tua.
     */
    public function test_pesannya_mengarahkan_ke_penjadwal_yang_belum_terpasang(): void
    {
        $this->buatCadangan('backup-lama.sql', 9);

        $keluaran = $this->preflight();

        $this->assertStringContainsString('CahayaOS Scheduler', $keluaran);
        $this->assertStringContainsString('pasang-penjadwal.ps1', $keluaran);
    }

    public function test_cadangan_segar_tidak_dilaporkan_sebagai_masalah(): void
    {
        $this->buatCadangan('backup-baru.sql', 0);

        $keluaran = $this->preflight();

        $this->assertStringNotContainsString('Cadangan terakhir dibuat', $keluaran);
    }

    /**
     * Ambangnya 2 hari, bukan 1: satu hari terlewat bisa saja karena
     * komputernya sempat dimatikan, dan peringatan yang berbunyi terlalu
     * mudah akan diabaikan orang.
     */
    public function test_terlambat_satu_hari_belum_dianggap_masalah(): void
    {
        $this->buatCadangan('backup-kemarin.sql', 1);

        $keluaran = $this->preflight();

        $this->assertStringNotContainsString('Cadangan terakhir dibuat', $keluaran);
    }

    /**
     * Pengingat menyalin ke luar server selalu ada — kebiasaan itu tidak
     * bisa diperiksa dari dalam aplikasi, jadi yang bisa dilakukan hanya
     * mengingatkan.
     */
    public function test_pengingat_salinan_luar_server_selalu_muncul(): void
    {
        $this->buatCadangan('backup-baru.sql', 0);

        $this->assertStringContainsString(
            'Cadangan disalin ke luar server',
            $this->preflight()
        );
    }

    /**
     * Ruang disk ikut dilaporkan bersama cadangan.
     *
     * Keduanya satu persoalan: disk yang penuh membuat backup gagal, dan
     * backup yang gagal baru terasa pada hari data dibutuhkan kembali.
     *
     * Yang diuji hanya bahwa angkanya DISEBUTKAN — bukan nilai tertentu,
     * karena berapa ruang yang tersisa jelas berbeda di tiap mesin.
     */
    public function test_ruang_disk_ikut_dilaporkan(): void
    {
        $this->buatCadangan('backup-baru.sql', 0);

        $keluaran = $this->preflight();

        $this->assertStringContainsString('Ruang disk tersisa', $keluaran);
        $this->assertMatchesRegularExpression('/Tersisa [\d.,]+ GB dari/', $keluaran);
    }

    /**
     * Ia peringatan, bukan gerbang.
     *
     * Tidak ada ambang "benar" yang bisa ditetapkan dari dalam aplikasi —
     * berapa ruang yang aman bergantung pada apa lagi yang berbagi disk.
     * Yang bisa dilakukan sistem adalah memastikan orangnya melihat.
     */
    public function test_ruang_disk_tidak_menjatuhkan_preflight(): void
    {
        $this->buatCadangan('backup-baru.sql', 0);

        $keluaran = $this->preflight();

        // Baris ruang disk muncul di bagian "PERHATIKAN", bukan "MASALAH".
        $posisiDisk = strpos($keluaran, 'Ruang disk tersisa');
        $posisiMasalah = strpos($keluaran, 'MASALAH');

        $this->assertNotFalse($posisiDisk);

        if ($posisiMasalah !== false) {
            $this->assertLessThan(
                $posisiMasalah,
                $posisiDisk,
                'Ruang disk seharusnya dilaporkan sebagai peringatan, bukan masalah.'
            );
        }
    }

    /**
     * Preflight hanya membaca — ia tidak boleh menyentuh berkas cadangan.
     */
    public function test_preflight_tidak_menghapus_atau_mengubah_cadangan(): void
    {
        $this->buatCadangan('backup-penting.sql', 3);

        $path = $this->storageUji . '/app/backups/backup-penting.sql';
        $isiSebelum = file_get_contents($path);
        $waktuSebelum = filemtime($path);

        $this->preflight();

        $this->assertFileExists($path);
        $this->assertSame($isiSebelum, file_get_contents($path));
        $this->assertSame($waktuSebelum, filemtime($path));
    }
}
