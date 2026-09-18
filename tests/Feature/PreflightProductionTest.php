<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PEMERIKSAAN SEBELUM PRODUCTION.
 *
 * ==========================================================================
 * PEMERIKSAAN YANG TIDAK PERNAH GAGAL TIDAK MENJAGA APA PUN
 * ==========================================================================
 *
 * Perintah preflight ada untuk menangkap empat kesalahan deployment yang
 * semuanya membiarkan aplikasi berjalan MULUS sambil salah — tidak satu pun
 * memunculkan error. Karena itu yang paling penting diuji bukan bahwa
 * perintahnya lolos pada konfigurasi yang benar, melainkan bahwa ia
 * BENAR-BENAR GAGAL pada konfigurasi yang salah.
 *
 * Sebuah preflight yang selalu hijau justru lebih berbahaya daripada tidak
 * ada preflight sama sekali: ia memberi keyakinan tanpa dasar.
 *
 * Kode keluarnya juga diuji, karena skrip deploy bergantung padanya untuk
 * berhenti sebelum aplikasi terlanjur dibuka pengguna.
 */
class PreflightProductionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Preflight memeriksa keberadaan Admin aktif; tanpa ini seluruh
        // pemeriksaan lain tertutupi oleh satu masalah yang sama.
        User::create([
            'name' => 'Admin', 'email' => 'admin@ybmpln.id',
            'password' => bcrypt('rahasia123'), 'role' => 'admin', 'is_active' => true,
        ]);
    }

    // ==================================================================
    // A. MENANGKAP KESALAHAN — bagian terpenting
    // ==================================================================

    public function test_menolak_saat_debug_masih_aktif(): void
    {
        // Kebocoran paling umum pada Laravel yang naik ke server: setiap
        // halaman error menampilkan isi konfigurasi, termasuk kata sandi
        // database, kepada siapa pun yang membukanya.
        config(['app.debug' => true, 'app.env' => 'production']);

        $this->artisan('cahayaos:preflight')
            ->expectsOutputToContain('APP_DEBUG = false')
            ->assertExitCode(1);
    }

    public function test_menolak_saat_app_url_masih_localhost(): void
    {
        // Seluruh link omzet yang dikirim ke peserta lewat WhatsApp dibangun
        // dari APP_URL. Tautan yang sudah terkirim salah tidak bisa ditarik.
        config(['app.url' => 'http://localhost', 'app.env' => 'production', 'app.debug' => false]);

        $this->artisan('cahayaos:preflight')
            ->expectsOutputToContain('APP_URL bukan alamat lokal')
            ->assertExitCode(1);
    }

    public function test_menolak_saat_env_belum_production(): void
    {
        config(['app.env' => 'local']);

        $this->artisan('cahayaos:preflight')
            ->expectsOutputToContain('APP_ENV = production')
            ->assertExitCode(1);
    }

    public function test_menolak_saat_locale_bukan_indonesia(): void
    {
        // Laporan PDF resmi akan mencetak nama bulan berbahasa Inggris, dan
        // itu hanya terlihat pada bulan yang namanya memang berbeda.
        config(['app.locale' => 'en']);

        $this->artisan('cahayaos:preflight')
            ->expectsOutputToContain('APP_LOCALE = id')
            ->assertExitCode(1);
    }

    public function test_menolak_saat_disk_bawaan_publik(): void
    {
        // Bukti laporan omzet dan kuitansi investasi adalah data keuangan
        // peserta; keduanya tidak boleh berada di folder yang disajikan
        // langsung web server.
        config(['filesystems.default' => 'public']);

        $this->artisan('cahayaos:preflight')
            ->expectsOutputToContain('Disk bawaan privat')
            ->assertExitCode(1);
    }

    public function test_menolak_saat_tidak_ada_admin_aktif(): void
    {
        User::query()->update(['is_active' => false]);

        $this->artisan('cahayaos:preflight')
            ->expectsOutputToContain('Ada Admin aktif')
            ->assertExitCode(1);
    }

    public function test_menolak_saat_cookie_sesi_tidak_secure_di_https(): void
    {
        config([
            'app.env' => 'production',
            'app.debug' => false,
            'app.url' => 'https://cahayaos.example.id',
            'session.secure' => false,
        ]);

        $this->artisan('cahayaos:preflight')
            ->expectsOutputToContain('SESSION_SECURE_COOKIE aktif')
            ->assertExitCode(1);
    }

    // ==================================================================
    // B. MELOLOSKAN KONFIGURASI YANG BENAR
    // ==================================================================

    public function test_lolos_pada_konfigurasi_production_yang_benar(): void
    {
        config([
            'app.env' => 'production',
            'app.debug' => false,
            'app.url' => 'https://cahayaos.example.id',
            'app.locale' => 'id',
            'app.timezone' => 'Asia/Jakarta',
            'session.secure' => true,
            'session.http_only' => true,
            'session.encrypt' => true,
            'filesystems.default' => 'local',
        ]);

        $this->artisan('cahayaos:preflight')->assertExitCode(0);
    }

    public function test_mode_lokal_melewati_pemeriksaan_khusus_server(): void
    {
        // Pengembang di Laragon tidak boleh dipaksa menyetel APP_ENV=production
        // hanya supaya preflight-nya hijau.
        config(['app.env' => 'local', 'app.debug' => true, 'app.url' => 'http://localhost']);

        $this->artisan('cahayaos:preflight', ['--lokal' => true])->assertExitCode(0);
    }

    public function test_locale_tetap_diperiksa_walau_mode_lokal(): void
    {
        // Bahasa dokumen bukan urusan lingkungan — laporan yang dicetak dari
        // laptop pengembang pun harus berbahasa Indonesia.
        config(['app.locale' => 'en']);

        $this->artisan('cahayaos:preflight', ['--lokal' => true])
            ->expectsOutputToContain('APP_LOCALE = id')
            ->assertExitCode(1);
    }

    // ==================================================================
    // B2. ASET FRONTEND — kegagalan paling mahal setelah pindah ke Vite
    // ==================================================================

    public function test_menolak_saat_aset_frontend_belum_dibangun(): void
    {
        // Sejak tampilan berpindah dari CDN Tailwind ke build Vite, tidak ada
        // satu halaman pun yang bisa dirender tanpa public/build. Folder itu
        // tidak masuk Git, jadi `git pull` tidak pernah membawanya — dan
        // akibat melewatkan `npm run build` bukan halaman yang polos,
        // melainkan error 500 di SETIAP halaman, termasuk login.
        $this->pindahkanBuild();

        try {
            $this->artisan('cahayaos:preflight', ['--lokal' => true])
                ->expectsOutputToContain('Aset frontend sudah dibangun')
                ->assertExitCode(1);
        } finally {
            $this->kembalikanBuild();
        }
    }

    public function test_menolak_saat_manifest_menunjuk_berkas_yang_hilang(): void
    {
        // Gejala yang paling membingungkan: manifest-nya ada sehingga halaman
        // TERBUKA, tapi seluruh tampilannya hilang karena berkas CSS-nya
        // tidak pernah ikut tersalin.
        $assets = public_path('build/assets');

        if (! is_dir($assets)) {
            $this->markTestSkipped('Aset belum dibangun di lingkungan ini.');
        }

        $sementara = public_path('build/assets-dipindah-sementara');
        rename($assets, $sementara);

        try {
            $this->artisan('cahayaos:preflight', ['--lokal' => true])
                ->expectsOutputToContain('Berkas aset lengkap')
                ->assertExitCode(1);
        } finally {
            rename($sementara, $assets);
        }
    }

    public function test_lolos_saat_aset_sudah_dibangun(): void
    {
        if (! is_file(public_path('build/manifest.json'))) {
            $this->markTestSkipped('Aset belum dibangun di lingkungan ini — jalankan npm run build.');
        }

        $this->artisan('cahayaos:preflight', ['--lokal' => true])->assertExitCode(0);
    }

    public function test_tidak_ada_satu_pun_view_yang_memuat_berkas_dari_server_luar(): void
    {
        // Penjaga kemunduran. Menempelkan kembali satu baris <script src="https://…">
        // ke sebuah halaman akan "berhasil" tanpa error apa pun — halamannya
        // tampil normal di layar pengembang yang internetnya menyala — sambil
        // diam-diam mengembalikan ketergantungan ke server luar.
        //
        // Yang dijaga di sini bukan selera teknis, melainkan satu sifat yang
        // dijanjikan panduan deployment: aplikasi tetap tampil utuh saat
        // internet kantor mati.
        $pelanggaran = [];

        foreach ($this->berkasBlade() as $path => $isi) {
            foreach (['cdn.tailwindcss.com', 'cdn.jsdelivr.net', 'fonts.googleapis.com', 'unpkg.com', 'code.jquery.com'] as $host) {
                if (str_contains($isi, $host)) {
                    $pelanggaran[] = $path . ' memuat ' . $host;
                }
            }
        }

        $this->assertSame([], $pelanggaran, "Berkas berikut masih memuat aset dari server luar:\n" . implode("\n", $pelanggaran));
    }

    public function test_pemanggilan_chart_menunggu_modulnya_selesai_dimuat(): void
    {
        // Chart.js kini dimuat Vite sebagai modul, dan modul SELALU ditunda
        // sampai halaman selesai diurai. Kode `new Chart(...)` yang ditulis
        // langsung di tengah Blade berjalan lebih dulu — dan kalau
        // pembungkus DOMContentLoaded-nya hilang, grafiknya lenyap tanpa
        // pesan apa pun di halaman. Tidak ada error yang terlihat pengguna,
        // hanya kotak kosong.
        $tanpaPembungkus = [];

        foreach ($this->berkasBlade() as $path => $isi) {
            if (! str_contains($isi, 'new Chart(')) {
                continue;
            }

            if (! str_contains($isi, 'DOMContentLoaded')) {
                $tanpaPembungkus[] = $path;
            }
        }

        $this->assertSame([], $tanpaPembungkus,
            "Halaman berikut memanggil Chart tanpa menunggu modulnya dimuat:\n" . implode("\n", $tanpaPembungkus));
    }

    /** @return array<string, string> [nama berkas relatif => isinya] */
    private function berkasBlade(): array
    {
        $hasil = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'))
        );

        foreach ($iterator as $berkas) {
            if ($berkas->isFile() && str_ends_with($berkas->getFilename(), '.blade.php')) {
                $nama = str_replace(resource_path('views') . DIRECTORY_SEPARATOR, '', $berkas->getPathname());
                $hasil[$nama] = (string) file_get_contents($berkas->getPathname());
            }
        }

        return $hasil;
    }

    public function test_setiap_halaman_utuh_memuat_aset_lewat_vite(): void
    {
        // Kelima berkas ini adalah satu-satunya yang punya <head> sendiri;
        // sisanya mewarisi layouts/app. Kalau salah satu tidak memanggil
        // @vite, halaman itu tampil tanpa gaya sama sekali — dan karena
        // empat di antaranya adalah halaman publik (login, cek simpanan,
        // form omzet peserta), yang melihatnya justru orang luar.
        $halaman = [
            'layouts/app.blade.php',
            'auth/login.blade.php',
            'anggota-akses/login.blade.php',
            'anggota-akses/hasil.blade.php',
            'omzet/public-form.blade.php',
        ];

        foreach ($halaman as $berkas) {
            $isi = (string) file_get_contents(resource_path('views/' . $berkas));

            $this->assertStringContainsString('@vite(', $isi, $berkas . ' tidak memuat aset lewat Vite.');
        }
    }

    private function pindahkanBuild(): void
    {
        $build = public_path('build');

        if (is_dir($build)) {
            rename($build, public_path('build-dipindah-sementara'));
        }
    }

    private function kembalikanBuild(): void
    {
        $sementara = public_path('build-dipindah-sementara');

        if (is_dir($sementara)) {
            rename($sementara, public_path('build'));
        }
    }

    // ==================================================================
    // C. BAHASA DOKUMEN — yang diperbaiki tahap ini
    // ==================================================================

    public function test_penanggalan_laporan_berbahasa_indonesia(): void
    {
        // Bug nyata yang ditemukan saat persiapan deployment: seluruh laporan
        // PDF mencetak "13 August 2026" alih-alih "13 Agustus 2026".
        $this->assertSame('id', config('app.locale'));

        $this->assertSame(
            '13 Agustus 2026',
            \Carbon\Carbon::create(2026, 8, 13)->translatedFormat('d F Y')
        );

        $this->assertSame(
            '13 Mei 2026',
            \Carbon\Carbon::create(2026, 5, 13)->translatedFormat('d F Y')
        );
    }

    public function test_locale_indonesia_adalah_bawaan_bukan_bergantung_env(): void
    {
        // Ditaruh sebagai bawaan di config/app.php supaya lingkungan baru yang
        // lupa menyalin APP_LOCALE tetap menghasilkan dokumen yang benar.
        $isi = file_get_contents(config_path('app.php'));

        $this->assertStringContainsString("env('APP_LOCALE', 'id')", $isi);
    }
}
