<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * PEMERIKSAAN SEBELUM CAHAYAOS DIPAKAI DI SERVER.
 *
 * ==========================================================================
 * KENAPA PERINTAH INI ADA
 * ==========================================================================
 *
 * Kesalahan deployment yang paling merugikan bukan yang membuat aplikasi
 * gagal jalan — yang itu langsung ketahuan. Yang berbahaya justru yang
 * membiarkan aplikasi berjalan MULUS sambil salah:
 *
 *   APP_DEBUG=true      -> setiap halaman error menampilkan isi konfigurasi,
 *                          termasuk kata sandi database, kepada siapa pun
 *   APP_URL=localhost   -> setiap link omzet yang sudah dikirim ke peserta
 *                          lewat WhatsApp tidak bisa dibuka, dan tidak ada
 *                          cara menariknya kembali
 *   APP_LOCALE=en       -> laporan PDF resmi mencetak "13 August 2026"
 *   SESSION_SECURE=false-> cookie sesi Admin terkirim lewat HTTP biasa
 *
 * Keempatnya tidak memunculkan error apa pun. Perintah ini yang memunculkan.
 *
 * Dipakai: php artisan cahayaos:preflight
 *
 * Keluar dengan kode 1 kalau ada MASALAH, supaya bisa dipasang di skrip
 * deploy dan menghentikannya sebelum aplikasi terlanjur dibuka pengguna.
 * PERINGATAN tidak menghentikan apa pun — ia hal yang perlu dilihat mata
 * manusia, bukan penghalang.
 */
class PreflightProduction extends Command
{
    protected $signature = 'cahayaos:preflight
                            {--lokal : Lewati pemeriksaan yang hanya berlaku di server}';

    protected $description = 'Periksa kesiapan konfigurasi CahayaOS sebelum dipakai di server';

    /** @var list<array{0:string,1:string}> */
    private array $masalah = [];

    /** @var list<array{0:string,1:string}> */
    private array $peringatan = [];

    /** @var list<string> */
    private array $lolos = [];

    public function handle(): int
    {
        $this->newLine();
        $this->line('  <fg=cyan;options=bold>CAHAYAOS — PEMERIKSAAN SEBELUM PRODUCTION</>');
        $this->line('  <fg=gray>' . now()->translatedFormat('l, d F Y H:i') . ' WIB</>');
        $this->newLine();

        $untukServer = ! $this->option('lokal');

        $this->periksaAplikasi($untukServer);
        $this->periksaTautanPublik($untukServer);
        $this->periksaSesi($untukServer);
        $this->periksaLog($untukServer);
        $this->periksaDatabase($untukServer);
        $this->periksaBerkas();
        $this->periksaCadangan();
        $this->periksaAsetFrontend();
        $this->periksaPengamanSeeder();
        $this->periksaAkun();

        return $this->laporkan();
    }

    // ==================================================================

    private function periksaAplikasi(bool $server): void
    {
        $this->nilai(
            'APP_KEY terisi',
            filled(config('app.key')),
            'APP_KEY kosong. Jalankan `php artisan key:generate` — tanpa kunci ini sesi dan cookie tidak bisa dienkripsi.'
        );

        if ($server) {
            $this->nilai(
                'APP_ENV = production',
                config('app.env') === 'production',
                'APP_ENV masih "' . config('app.env') . '". Setel ke production.'
            );

            $this->nilai(
                'APP_DEBUG = false',
                config('app.debug') === false,
                'APP_DEBUG masih aktif. Setiap halaman error akan menampilkan jejak kode lengkap '
                    . 'beserta isi konfigurasi — termasuk kata sandi database — kepada siapa pun yang membukanya.'
            );
        }

        // Berlaku di lingkungan mana pun: dokumen resminya berbahasa Indonesia.
        $this->nilai(
            'APP_LOCALE = id',
            config('app.locale') === 'id',
            'APP_LOCALE = "' . config('app.locale') . '". Laporan PDF akan mencetak nama bulan '
                . 'berbahasa Inggris ("13 August 2026"), dan itu hanya terlihat pada bulan yang '
                . 'namanya memang berbeda — mudah lolos berbulan-bulan.'
        );

        $this->nilai(
            'APP_TIMEZONE = Asia/Jakarta',
            config('app.timezone') === 'Asia/Jakarta',
            'APP_TIMEZONE = "' . config('app.timezone') . '". Tanggal transaksi dan laporan akan '
                . 'bergeser dari waktu setempat.'
        );
    }

    private function periksaTautanPublik(bool $server): void
    {
        $url = (string) config('app.url');

        if (! $server) {
            $this->lolos[] = 'APP_URL (dilewati — mode lokal)';

            return;
        }

        $lokal = str_contains($url, 'localhost')
            || str_contains($url, '127.0.0.1')
            || str_contains($url, '.test');

        $this->nilai(
            'APP_URL bukan alamat lokal',
            ! $lokal,
            'APP_URL masih "' . $url . '". Seluruh tautan yang dikirim ke luar dibangun dari nilai ini — '
                . 'link laporan omzet yang dikirim ke peserta lewat WhatsApp, dan halaman Cek Simpanan '
                . 'anggota. Tautan yang sudah terkirim dengan alamat salah tidak bisa ditarik kembali.'
        );

        if (! $lokal) {
            $this->nilai(
                'APP_URL memakai HTTPS',
                str_starts_with($url, 'https://'),
                'APP_URL memakai http://. Halaman login, Cek Simpanan (nomor anggota + PIN), dan '
                    . 'unggahan bukti akan melintas tanpa enkripsi.',
                peringatanSaja: true
            );
        }
    }

    private function periksaSesi(bool $server): void
    {
        if (! $server) {
            $this->lolos[] = 'Sesi & cookie (dilewati — mode lokal)';

            return;
        }

        $https = str_starts_with((string) config('app.url'), 'https://');

        if ($https) {
            $this->nilai(
                'SESSION_SECURE_COOKIE aktif',
                (bool) config('session.secure'),
                'Situs memakai HTTPS tetapi cookie sesi belum ditandai secure. Cookie akan ikut '
                    . 'terkirim lewat koneksi HTTP biasa dan bisa dibaca di jaringan yang sama — '
                    . 'termasuk sesi Admin.'
            );
        }

        $this->nilai(
            'Cookie sesi HTTP-only',
            (bool) config('session.http_only'),
            'Cookie sesi bisa dibaca JavaScript.',
            peringatanSaja: true
        );

        $this->nilai(
            'Sesi terenkripsi',
            (bool) config('session.encrypt'),
            'SESSION_ENCRYPT belum aktif. Isi sesi tersimpan apa adanya di basis data.',
            peringatanSaja: true
        );
    }

    private function periksaLog(bool $server): void
    {
        if (! $server) {
            $this->lolos[] = 'Log (dilewati — mode lokal)';

            return;
        }

        $this->nilai(
            'LOG_LEVEL bukan debug',
            config('logging.channels.' . config('logging.default') . '.level', 'debug') !== 'debug'
                || env('LOG_LEVEL', 'debug') !== 'debug',
            'LOG_LEVEL masih debug. Berkas log akan tumbuh cepat dan ikut memuat data peserta.',
            peringatanSaja: true
        );

        $this->nilai(
            'Log harian (LOG_STACK=daily)',
            env('LOG_STACK') === 'daily',
            'Log memakai satu berkas tunggal yang tumbuh tanpa batas sampai memenuhi disk server. '
                . 'Setel LOG_STACK=daily.',
            peringatanSaja: true
        );
    }

    private function periksaDatabase(bool $server): void
    {
        try {
            DB::connection()->getPdo();
            $this->lolos[] = 'Koneksi database';
        } catch (Throwable $e) {
            $this->masalah[] = ['Koneksi database', 'Tidak bisa terhubung: ' . $e->getMessage()];

            return;
        }

        // Migration yang tertunda berarti kode dan basis data tidak sejalan.
        try {
            $berkas = collect(glob(database_path('migrations/*.php')))
                ->map(fn ($p) => basename($p, '.php'));

            $sudah = Schema::hasTable('migrations')
                ? DB::table('migrations')->pluck('migration')
                : collect();

            $tertunda = $berkas->diff($sudah);

            $this->nilai(
                'Tidak ada migration tertunda',
                $tertunda->isEmpty(),
                $tertunda->count() . ' migration belum dijalankan: ' . $tertunda->implode(', ')
                    . '. Jalankan `php artisan migrate --force`.'
            );
        } catch (Throwable $e) {
            $this->peringatan[] = ['Status migration', 'Tidak bisa diperiksa: ' . $e->getMessage()];
        }

        if ($server && config('database.default') === 'mysql') {
            $pengguna = (string) config('database.connections.mysql.username');

            $this->nilai(
                'Pengguna database bukan root',
                $pengguna !== 'root',
                'Aplikasi terhubung sebagai "root". Buat pengguna khusus dengan hak seperlunya — '
                    . 'akun root yang bocor membuka seluruh server basis data, bukan hanya CahayaOS.',
                peringatanSaja: true
            );

            $this->nilai(
                'Kata sandi database terisi',
                filled(config('database.connections.mysql.password')),
                'Kata sandi database kosong.'
            );
        }
    }

    private function periksaBerkas(): void
    {
        foreach ([
            'storage/app' => storage_path('app'),
            'storage/framework' => storage_path('framework'),
            'storage/logs' => storage_path('logs'),
            'bootstrap/cache' => base_path('bootstrap/cache'),
        ] as $label => $path) {
            $this->nilai(
                'Dapat ditulis: ' . $label,
                is_dir($path) && is_writable($path),
                'Direktori ' . $label . ' tidak bisa ditulis. Unggahan bukti, sesi, dan cache akan gagal.'
            );
        }

        // Bukti omzet & kuitansi investasi adalah data keuangan peserta.
        // Keduanya HARUS berada di disk privat, bukan di folder yang disajikan
        // langsung oleh web server.
        $this->nilai(
            'Disk bawaan privat (bukan public)',
            config('filesystems.default') !== 'public',
            'FILESYSTEM_DISK = public. Bukti laporan omzet dan kuitansi investasi akan tersimpan '
                . 'di folder yang bisa diakses langsung tanpa login.'
        );

        $this->nilai(
            '.env diabaikan Git',
            str_contains((string) @file_get_contents(base_path('.gitignore')), '.env'),
            'Berkas .env tidak tercantum di .gitignore.'
        );
    }

    /**
     * ASET FRONTEND SUDAH DIBANGUN.
     *
     * ==================================================================
     * KENAPA INI MASUK DAFTAR PEMERIKSAAN DEPLOY
     * ==================================================================
     *
     * Sejak tampilan berpindah dari CDN Tailwind ke build Vite, aplikasi
     * TIDAK BISA merender satu halaman pun tanpa berkas hasil build. Bukan
     * "tampilannya polos" seperti dulu — melainkan setiap halaman melempar
     * error dan berbalas 500, termasuk halaman login.
     *
     * Folder public/build sengaja tidak masuk Git (lihat .gitignore), jadi
     * `git pull` saja tidak pernah membawanya. Satu-satunya yang
     * menghasilkannya adalah `npm run build` di server, dan itu langkah yang
     * paling mudah terlewat justru karena sebelumnya tidak pernah ada.
     *
     * Diperiksa juga saat mode --lokal: pengembang yang baru meng-clone
     * repositori akan menemui kegagalan yang sama persis.
     */
    private function periksaAsetFrontend(): void
    {
        $manifest = public_path('build/manifest.json');

        $ada = is_file($manifest);

        $this->nilai(
            'Aset frontend sudah dibangun',
            $ada,
            'Berkas public/build/manifest.json tidak ada. TANPA INI SELURUH HALAMAN GAGAL DIBUKA '
                . '(error 500), termasuk halaman login. Jalankan: npm ci && npm run build'
        );

        if (! $ada) {
            return;
        }

        // Manifest ada tapi berkas yang ditunjuknya hilang — terjadi kalau
        // build lama tersalin sebagian, atau folder assets terhapus. Gejalanya
        // membingungkan: halaman terbuka, tapi tanpa gaya sama sekali.
        $isi = json_decode((string) @file_get_contents($manifest), true) ?: [];
        $hilang = [];

        foreach ($isi as $entri) {
            $berkas = $entri['file'] ?? null;

            if ($berkas && ! is_file(public_path('build/' . $berkas))) {
                $hilang[] = $berkas;
            }
        }

        $this->nilai(
            'Berkas aset lengkap',
            $hilang === [],
            'Manifest menunjuk berkas yang tidak ada: ' . implode(', ', $hilang)
                . '. Bangun ulang dengan: npm run build'
        );
    }

    /**
     * SEEDER TIDAK BOLEH BISA MENIMPA DATABASE PRODUCTION.
     *
     * Seeder proyek ini memakai alamat email yang sama dengan akun sungguhan.
     * Satu `php artisan db:seed --force` di server akan mengubah kata sandi
     * Admin menjadi kata sandi contoh yang tertulis di dalam repositori.
     *
     * Diperiksa di sini supaya kalau suatu hari ada seeder baru ditambahkan
     * tanpa penjaganya, ketahuan sebelum deploy — bukan setelah.
     */
    private function periksaPengamanSeeder(): void
    {
        $tanpaPenjaga = [];

        foreach (glob(database_path('seeders/*.php')) as $berkas) {
            $isi = (string) file_get_contents($berkas);

            if (! str_contains($isi, 'berhentiKarenaProduction')) {
                $tanpaPenjaga[] = basename($berkas);
            }
        }

        $this->nilai(
            'Seluruh seeder terjaga dari production',
            $tanpaPenjaga === [],
            'Seeder berikut belum memakai penjaga production: ' . implode(', ', $tanpaPenjaga)
                . '. Menjalankannya di server bisa menimpa kata sandi akun sungguhan.'
        );

        // SEEDER_BOLEH_PRODUCTION adalah pintu darurat. Kalau tertinggal
        // menyala di .env server, penjaganya tidak berarti apa-apa.
        $this->nilai(
            'Pintu darurat seeder tertutup',
            ! filter_var(env('SEEDER_BOLEH_PRODUCTION', false), FILTER_VALIDATE_BOOLEAN),
            'SEEDER_BOLEH_PRODUCTION masih bernilai true di .env. Hapus barisnya setelah selesai dipakai.'
        );
    }

    private function periksaAkun(): void
    {
        try {
            $admin = User::where('role', User::ROLE_ADMIN)->where('is_active', true)->count();

            $this->nilai(
                'Ada Admin aktif',
                $admin > 0,
                'Tidak ada satu pun akun Admin yang aktif. Tidak akan ada yang bisa mengelola sistem.'
            );

            $lemah = User::whereIn('email', ['admin@admin.com', 'test@test.com'])->count();

            $this->nilai(
                'Tidak ada akun contoh tersisa',
                $lemah === 0,
                $lemah . ' akun contoh masih ada. Hapus atau ganti sebelum dipakai sungguhan.',
                peringatanSaja: true
            );
        } catch (Throwable $e) {
            $this->peringatan[] = ['Akun', 'Tidak bisa diperiksa: ' . $e->getMessage()];
        }
    }

    // ==================================================================

    /**
     * CADANGAN YANG BERHENTI DIBUAT TIDAK MENIMBULKAN ERROR APA PUN.
     *
     * =================================================================
     * KENAPA PEMERIKSAAN INI ADA
     * =================================================================
     *
     * routes/console.php menjadwalkan backup harian pukul 02:00. Tapi
     * jadwal Laravel hanya bergerak kalau ada yang memanggil
     * "php artisan schedule:run" setiap menit dari luar aplikasi.
     *
     * Kalau pemanggil itu tidak pernah dipasang — atau terhapus, atau
     * rusak setelah PHP di-upgrade — backup berhenti dibuat dan TIDAK ADA
     * yang gagal: memang tidak ada yang dijalankan untuk gagal. Halaman
     * tetap terbuka, test tetap hijau, dan ketiadaan cadangan baru
     * ketahuan pada hari seseorang membutuhkannya.
     *
     * Terjadi sungguhan di mesin pengembangan ini: tiga proyek lain punya
     * Scheduled Task, CahayaOS tidak.
     *
     * Ambangnya 2 hari, bukan 1: satu hari terlewat bisa saja karena
     * komputer sempat dimatikan, dan peringatan yang berbunyi terlalu
     * mudah akan diabaikan orang.
     */
    private function periksaCadangan(): void
    {
        $folder = storage_path('app/backups');

        if (! is_dir($folder)) {
            $this->nilai(
                'Cadangan database',
                false,
                'Folder cadangan belum ada sama sekali (' . $folder . '). '
                    . 'Belum pernah ada backup yang berhasil dibuat.'
            );

            return;
        }

        $berkas = glob($folder . '/*.sql') ?: [];

        if ($berkas === []) {
            $this->nilai(
                'Cadangan database',
                false,
                'Tidak ada satu pun berkas cadangan di ' . $folder . '.'
            );

            return;
        }

        $terbaru = max(array_map('filemtime', $berkas));
        $umurHari = (int) floor((time() - $terbaru) / 86400);

        $this->nilai(
            'Cadangan database terbaru',
            $umurHari <= 2,
            'Cadangan terakhir dibuat ' . $umurHari . ' hari lalu ('
                . date('d-m-Y H:i', $terbaru) . '). Jadwal harian pukul 02:00 '
                . 'kemungkinan tidak berjalan — periksa apakah Scheduled Task '
                . '"CahayaOS Scheduler" sudah terpasang. '
                . 'Pasang dengan: scripts\\pasang-penjadwal.ps1 (sebagai Administrator).'
        );

        // ============================================================
        // RUANG DISK — syarat yang membuat backup bisa dibuat sama sekali.
        //
        // Diperiksa di sini, bersama cadangan, karena keduanya satu
        // persoalan: disk yang penuh membuat backup gagal, dan backup yang
        // gagal baru terasa pada hari data dibutuhkan kembali.
        //
        // TIDAK ada ambang "benar" yang bisa saya tetapkan untuk lembaga
        // ini — berapa ruang yang aman bergantung pada apa lagi yang
        // berbagi disk. Karena itu angkanya disebutkan apa adanya sebagai
        // PERINGATAN, bukan gerbang yang menjatuhkan: yang bisa dilakukan
        // sistem adalah memastikan orangnya melihat, bukan memutuskan
        // untuknya.
        //
        // Batas 10% dipilih sebagai isyarat yang lazim, bukan sebagai
        // kebijakan: MySQL juga butuh ruang kerja untuk berkas sementara,
        // dan disk yang benar-benar penuh menghentikan penulisan — bukan
        // cuma backup.
        // ============================================================
        $bebas = @disk_free_space($folder);
        $total = @disk_total_space($folder);

        if ($bebas !== false && $total !== false && $total > 0) {
            $persenBebas = $bebas / $total * 100;

            // Ambangnya DIBACA dari konfigurasi yang sama dengan yang dipakai
            // perintah backup — supaya preflight tidak pernah berkata "aman"
            // untuk ruang yang justru akan ditolak backupnya nanti.
            $minimal = (int) config('cahayaos.backup.min_disk_mb', 500) * 1024 * 1024;

            $this->nilai(
                'Ruang disk tersisa',
                $bebas >= $minimal && $persenBebas >= 10,
                'Tersisa ' . number_format($bebas / 1073741824, 1, ',', '.') . ' GB dari '
                    . number_format($total / 1073741824, 0, ',', '.') . ' GB ('
                    . number_format($persenBebas, 0, ',', '.') . '%). '
                    . 'Backup memerlukan minimal '
                    . number_format($minimal / 1048576, 0, ',', '.') . ' MB bebas '
                    . '(config cahayaos.backup.min_disk_mb). '
                    . 'Disk yang penuh membuat backup dilewati dan penulisan data berhenti — '
                    . 'keduanya tanpa peringatan sebelumnya.',
                peringatanSaja: true,
            );
        }

        // Peringatan, bukan masalah: menyalin ke luar server adalah kebiasaan
        // yang tidak bisa diperiksa dari dalam aplikasi. Yang bisa dilakukan
        // hanyalah mengingatkan.
        $this->nilai(
            'Cadangan disalin ke luar server',
            false,
            'Cadangan hanya ada di server yang sama dengan databasenya. '
                . 'Kalau servernya rusak, keduanya hilang bersamaan. '
                . 'Salin berkas di ' . $folder . ' ke media lain secara berkala.',
            peringatanSaja: true,
        );
    }

    private function nilai(string $label, bool $lulus, string $pesan, bool $peringatanSaja = false): void
    {
        if ($lulus) {
            $this->lolos[] = $label;

            return;
        }

        $peringatanSaja
            ? $this->peringatan[] = [$label, $pesan]
            : $this->masalah[] = [$label, $pesan];
    }

    private function laporkan(): int
    {
        foreach ($this->lolos as $l) {
            $this->line('  <fg=green>OK  </> ' . $l);
        }

        foreach ($this->peringatan as [$label, $pesan]) {
            $this->newLine();
            $this->line('  <fg=yellow;options=bold>PERHATIKAN</> ' . $label);
            $this->line('       <fg=gray>' . wordwrap($pesan, 90, "\n       ") . '</>');
        }

        foreach ($this->masalah as [$label, $pesan]) {
            $this->newLine();
            $this->line('  <fg=red;options=bold>MASALAH</> ' . $label);
            $this->line('       <fg=gray>' . wordwrap($pesan, 90, "\n       ") . '</>');
        }

        $this->newLine();
        $this->line('  <fg=gray>' . count($this->lolos) . ' lolos · '
            . count($this->peringatan) . ' perlu diperhatikan · '
            . count($this->masalah) . ' masalah</>');
        $this->newLine();

        if ($this->masalah !== []) {
            $this->line('  <bg=red;fg=white;options=bold> BELUM SIAP </> Perbaiki masalah di atas sebelum dipakai pengguna.');
            $this->newLine();

            return self::FAILURE;
        }

        $this->peringatan === []
            ? $this->line('  <bg=green;fg=white;options=bold> SIAP </> Seluruh pemeriksaan lolos.')
            : $this->line('  <bg=yellow;fg=black;options=bold> SIAP DENGAN CATATAN </> Tidak ada yang memblokir, tetapi lihat catatan di atas.');

        $this->newLine();

        return self::SUCCESS;
    }
}
