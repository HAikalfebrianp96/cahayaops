<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * PEMBUATAN FILE BACKUP DATABASE.
 *
 * ==========================================================================
 * KENAPA DIPINDAHKAN KELUAR DARI CONTROLLER
 * ==========================================================================
 *
 * Seluruh isi kelas ini DIPINDAHKAN dari BackupDatabaseController — bukan
 * disalin, dan perilakunya tidak diubah sebaris pun. Alasannya satu:
 *
 *   Selama logic backup hanya hidup di dalam controller, satu-satunya cara
 *   membuat backup adalah ADA ORANG YANG MENGKLIK TOMBOLNYA.
 *
 * Untuk sistem yang menyimpan buku simpanan anggota koperasi, "backup terjadi
 * kalau Admin ingat" bukan strategi cadangan — itu harapan. Backup terjadwal
 * membutuhkan pemanggil yang bukan permintaan HTTP, dan itu tidak mungkin
 * selama kodenya terkunci di balik `abort_unless(...isAdmin())`.
 *
 * Menyalin logic-nya ke command akan jauh lebih buruk: dua salinan format
 * dump SQL yang bisa berbeda diam-diam, dan yang ketahuan hanya pada hari
 * seseorang mencoba memulihkan data.
 *
 * CATATAN: implementasi ini MySQL — memakai SHOW TABLES / SHOW CREATE TABLE,
 * murni PHP tanpa shell_exec/mysqldump. Sifat itu diwarisi apa adanya dari
 * kode aslinya, tidak diperluas di sini.
 */
class BackupDatabaseService
{
    /**
     * Jumlah backup MANUAL & TERJADWAL yang dipertahankan (retention). Backup
     * otomatis SEBELUM restore (pre-restore-backup-*) TIDAK PERNAH ikut
     * dihapus oleh retention ini — itu jaring pengaman, bukan backup rutin.
     *
     * Dipakai sebagai nilai bawaan kalau konfigurasinya tidak diisi; angka
     * yang BERLAKU dibaca lewat retensi() supaya bisa diubah tanpa menyentuh
     * kode.
     */
    public const RETENTION_COUNT = 10;

    /**
     * Berapa backup rutin yang dipertahankan — DIPAKSA minimal 1.
     *
     * Lantai itu bukan kehati-hatian berlebihan: retensi 0 berarti backup
     * yang baru saja dibuat ikut terhapus di detik yang sama, dan sistem
     * ditinggalkan tanpa cadangan sama sekali. Keadaan itu tidak boleh bisa
     * dicapai hanya karena satu angka salah ketik di berkas konfigurasi.
     */
    public function retensi(): int
    {
        return max(1, (int) config('cahayaos.backup.retensi', self::RETENTION_COUNT));
    }

    /** Ruang bebas minimal (byte) sebelum backup boleh dijalankan. */
    public function minimalRuangBebas(): int
    {
        return max(0, (int) config('cahayaos.backup.min_disk_mb', 500)) * 1024 * 1024;
    }

    /**
     * Keadaan ruang disk di lokasi penyimpanan backup.
     *
     * Dikembalikan apa adanya — keputusan berhenti atau lanjut diambil
     * pemanggilnya, supaya perintah backup bisa menjelaskan keadaannya
     * kepada manusia alih-alih sekadar melempar galat.
     *
     * @return array{bebas:?int, total:?int, cukup:bool, minimal:int}
     */
    public function ruangDisk(): array
    {
        $dir = $this->direktori();

        if (! is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $bebas = @disk_free_space($dir);
        $total = @disk_total_space($dir);
        $minimal = $this->minimalRuangBebas();

        return [
            'bebas' => $bebas === false ? null : (int) $bebas,
            'total' => $total === false ? null : (int) $total,
            // Kalau ruang disk TIDAK bisa dibaca (hak akses, disk jaringan),
            // backup tetap dijalankan. Menolak bekerja karena pemeriksaannya
            // sendiri gagal akan menghentikan cadangan pada sistem yang
            // sebenarnya baik-baik saja.
            'cukup' => $bebas === false || (int) $bebas >= $minimal,
            'minimal' => $minimal,
        ];
    }

    public function direktori(): string
    {
        return storage_path('app/backups');
    }

    /**
     * Bikin 1 file backup .sql — dipakai oleh tombol "Buat Backup Sekarang"
     * (manual), oleh backup terjadwal, MAUPUN otomatis sebelum Restore.
     */
    public function buatFile(string $prefix): string
    {
        $dir = $this->direktori();
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filename = $prefix . '-' . now()->format('Y-m-d_His') . '.sql';
        $path = $dir . '/' . $filename;

        $dbName = DB::connection()->getDatabaseName();
        $tableKey = 'Tables_in_' . $dbName;
        $tables = DB::select('SHOW TABLES');

        $sql = "-- CahayaOS Database Backup\n-- Dibuat otomatis: " . now()->toDateTimeString() . "\n-- Database: {$dbName}\n\n";
        $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

        foreach ($tables as $tableObj) {
            $tableName = $tableObj->{$tableKey};

            $createRow = DB::select("SHOW CREATE TABLE `{$tableName}`")[0];
            $createSql = $createRow->{'Create Table'};
            $sql .= "-- ----------------------------\n-- Tabel: {$tableName}\n-- ----------------------------\n";
            $sql .= "DROP TABLE IF EXISTS `{$tableName}`;\n";
            $sql .= $createSql . ";\n\n";

            $pdo = DB::connection()->getPdo();
            $offset = 0;
            $batchSize = 200;

            while (true) {
                $rows = DB::table($tableName)->offset($offset)->limit($batchSize)->get();
                if ($rows->isEmpty()) {
                    break;
                }

                $columns = array_keys((array) $rows->first());
                $columnList = '`' . implode('`, `', $columns) . '`';
                $valuesList = [];

                foreach ($rows as $row) {
                    $values = array_map(function ($val) use ($pdo) {
                        if (is_null($val)) {
                            return 'NULL';
                        }

                        return $pdo->quote((string) $val);
                    }, (array) $row);

                    $valuesList[] = '(' . implode(', ', $values) . ')';
                }

                $sql .= "INSERT INTO `{$tableName}` ({$columnList}) VALUES\n" . implode(",\n", $valuesList) . ";\n";

                $offset += $batchSize;
            }

            $sql .= "\n";
        }

        $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";

        file_put_contents($path, $sql);

        $this->terapkanRetention();

        return $filename;
    }

    /**
     * Retention sederhana & aman: simpan HANYA N backup rutin terbaru
     * ("backup-*.sql"). File "pre-restore-backup-*.sql" (jaring pengaman
     * otomatis sebelum restore) TIDAK PERNAH ikut dihapus oleh fungsi ini,
     * apapun jumlahnya — itu bukan backup rutin, itu jaring pengaman.
     */
    public function terapkanRetention(): void
    {
        $dir = $this->direktori();
        if (! is_dir($dir)) {
            return;
        }

        $regularBackups = [];
        foreach (scandir($dir) as $file) {
            if (str_starts_with($file, 'backup-') && str_ends_with($file, '.sql')) {
                $regularBackups[$file] = filemtime($dir . '/' . $file);
            }
        }

        $retensi = $this->retensi();

        if (count($regularBackups) <= $retensi) {
            return;
        }

        arsort($regularBackups); // terbaru dulu
        $toDelete = array_slice(array_keys($regularBackups), $retensi);

        foreach ($toDelete as $file) {
            @unlink($dir . '/' . $file);
        }
    }

    public function namaFileSah(string $filename): bool
    {
        return (bool) preg_match('/^(backup|pre-restore-backup)-\d{4}-\d{2}-\d{2}_\d{6}\.sql$/', $filename);
    }

    /** @return array<int, array{name: string, size: string, created_at: int, is_pre_restore: bool}> */
    public function daftar(): array
    {
        $dir = $this->direktori();
        $files = [];

        if (is_dir($dir)) {
            foreach (scandir($dir) as $file) {
                if (str_ends_with($file, '.sql')) {
                    $files[] = [
                        'name' => $file,
                        'size' => $this->formatUkuran(filesize($dir . '/' . $file)),
                        'created_at' => filemtime($dir . '/' . $file),
                        'is_pre_restore' => str_starts_with($file, 'pre-restore-backup'),
                    ];
                }
            }
            usort($files, fn ($a, $b) => $b['created_at'] <=> $a['created_at']);
        }

        return $files;
    }

    /**
     * Keadaan salinan cadangan ke LUAR disk ini.
     *
     * =====================================================================
     * KENAPA INI PERLU TERLIHAT
     * =====================================================================
     *
     * Seluruh cadangan di daftar atas tersimpan di disk yang SAMA dengan
     * databasenya. Kalau disk itu rusak, komputernya hilang, atau kena
     * ransomware, database dan seluruh cadangannya lenyap bersamaan.
     *
     * Salinan ke luar sudah tersedia dan sengaja dibuat opt-in — ke mana
     * data peserta boleh disalin adalah keputusan pengelola program, bukan
     * keputusan kode. Tetapi selama ini keadaannya tidak muncul di mana pun
     * yang dilihat Admin: tidak di halaman Backup, tidak di jadwal (yang
     * memang tidak terdaftar ketika belum diatur), dan tidak di log (karena
     * perintahnya berhenti diam-diam dengan kode sukses).
     *
     * Akibatnya fitur yang mati tidak bisa dibedakan dari fitur yang
     * berjalan lancar — keduanya sama-sama senyap.
     *
     * =====================================================================
     * TIDAK PERNAH MELEMPAR GALAT
     * =====================================================================
     *
     * Folder tujuan bisa berupa disk eksternal yang sedang dicabut atau
     * folder cloud yang belum tersinkron. Membaca keadaannya tidak boleh
     * membuat halaman Backup gagal terbuka — justru halaman itulah yang
     * dibutuhkan ketika keadaan sedang tidak normal.
     *
     * @return array{aktif: bool, folder: ?string, folder_ada: bool,
     *               bisa_ditulis: bool, jumlah: int, terbaru: ?int, retensi: int}
     */
    public function keadaanSalinanLuar(): array
    {
        $folder = config('cahayaos.backup.folder_luar');

        $keadaan = [
            'aktif' => filled($folder),
            'folder' => $folder ?: null,
            'folder_ada' => false,
            'bisa_ditulis' => false,
            'jumlah' => 0,
            'terbaru' => null,
            'retensi' => max(1, (int) config('cahayaos.backup.retensi_luar', 14)),
        ];

        if (! $keadaan['aktif']) {
            return $keadaan;
        }

        try {
            $keadaan['folder_ada'] = is_dir($folder);
            $keadaan['bisa_ditulis'] = $keadaan['folder_ada'] && is_writable($folder);

            if ($keadaan['folder_ada']) {
                $berkas = glob(rtrim($folder, '\/') . DIRECTORY_SEPARATOR . 'backup-*.sql') ?: [];

                $keadaan['jumlah'] = count($berkas);

                foreach ($berkas as $b) {
                    $waktu = @filemtime($b);

                    if ($waktu !== false && ($keadaan['terbaru'] === null || $waktu > $keadaan['terbaru'])) {
                        $keadaan['terbaru'] = $waktu;
                    }
                }
            }
        } catch (\Throwable $e) {
            // Keadaan dilaporkan apa adanya: folder tidak terbaca.
        }

        return $keadaan;
    }

    public function formatUkuran(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2) . ' MB';
        }

        return round($bytes / 1024, 1) . ' KB';
    }
}
