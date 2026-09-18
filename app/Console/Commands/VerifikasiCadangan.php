<?php

namespace App\Console\Commands;

use App\Services\BackupDatabaseService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * MEMASTIKAN BERKAS CADANGAN BENAR-BENAR BISA DIPULIHKAN.
 *
 * =========================================================================
 * KENAPA PERINTAH INI ADA
 * =========================================================================
 *
 * Backup yang berhasil DIBUAT belum tentu bisa DIPULIHKAN. Berkas bisa
 * terpotong karena disk penuh di tengah jalan, atau berisi dump yang tidak
 * lengkap — dan keduanya menghasilkan berkas yang ukurannya wajar, namanya
 * benar, dan tercatat sukses di log.
 *
 * Ketidakutuhan itu baru ketahuan pada hari data dibutuhkan kembali, yaitu
 * hari paling buruk untuk menemukannya.
 *
 * =========================================================================
 * TIDAK PERNAH MENYENTUH DATABASE AKTIF
 * =========================================================================
 *
 * Pemulihan diuji ke database SEMENTARA yang dibuat khusus, lalu dibuang.
 * Database aktif tidak pernah dibaca-tulis oleh proses pemulihan ini —
 * hanya dipakai sebagai pembanding jumlah baris, lewat query SELECT.
 *
 * Kalau pembuatan database sementara tidak diizinkan (hak akses terbatas),
 * perintah ini berhenti dengan penjelasan — BUKAN dengan memulihkan ke
 * tempat lain.
 */
class VerifikasiCadangan extends Command
{
    protected $signature = 'cahayaos:verifikasi-cadangan
                            {--berkas= : Nama berkas cadangan tertentu. Bawaan: yang terbaru.}
                            {--tanpa-restore : Hanya periksa keutuhan berkas, tanpa mencoba memulihkan.}';

    protected $description = 'Memeriksa keutuhan berkas cadangan dan menguji pemulihannya ke database sementara.';

    /** Tabel yang jumlah barisnya dibandingkan dengan database aktif. */
    private const TABEL_PEMBANDING = [
        // Cahaya Preneur
        'users',
        'participants',
        'pendampingan_records',
        'training_records',
        'legalitas_records',
        'omzet_reports',
        // Koperasi Cahaya — ikut dibandingkan karena catatan uang anggota
        // adalah bagian yang paling tidak bisa disusun ulang kalau hilang.
        'koperasi_members',
        'member_savings',
        'sales_transactions',
    ];

    public function handle(BackupDatabaseService $backup): int
    {
        $path = $this->berkasYangDiuji($backup);

        if ($path === null) {
            return self::FAILURE;
        }

        $this->info('Memeriksa: ' . basename($path));
        $this->line('  Ukuran: ' . $backup->formatUkuran((int) filesize($path)));
        $this->newLine();

        if (! $this->periksaKeutuhan($path)) {
            return self::FAILURE;
        }

        if ($this->option('tanpa-restore')) {
            $this->newLine();
            $this->info('Keutuhan berkas: baik. Pengujian pemulihan dilewati (--tanpa-restore).');

            return self::SUCCESS;
        }

        return $this->ujiPemulihan($path);
    }

    private function berkasYangDiuji(BackupDatabaseService $backup): ?string
    {
        $dir = $backup->direktori();

        if ($nama = $this->option('berkas')) {
            // Nama divalidasi polanya, bukan digabung mentah: parameter dari
            // baris perintah tidak boleh bisa menunjuk berkas di luar folder
            // cadangan.
            if (! $backup->namaFileSah($nama)) {
                $this->error('Nama berkas tidak sesuai pola cadangan: ' . $nama);

                return null;
            }

            $path = $dir . '/' . $nama;
        } else {
            $daftar = glob($dir . '/backup-*.sql') ?: [];

            if ($daftar === []) {
                $this->error('Tidak ada berkas cadangan di ' . $dir);

                return null;
            }

            usort($daftar, fn ($a, $b) => filemtime($b) <=> filemtime($a));
            $path = $daftar[0];
        }

        if (! is_file($path)) {
            $this->error('Berkas tidak ditemukan: ' . $path);

            return null;
        }

        return $path;
    }

    /**
     * Keutuhan berkas — diperiksa tanpa menjalankan SQL apa pun.
     *
     * Berkas yang terpotong di tengah hampir selalu kehilangan bagian
     * penutupnya, dan itu bisa dikenali tanpa menyentuh database mana pun.
     */
    private function periksaKeutuhan(string $path): bool
    {
        $isi = file_get_contents($path);

        if ($isi === false || trim($isi) === '') {
            $this->error('Berkas kosong atau tidak terbaca.');

            return false;
        }

        $tabelDitemukan = preg_match_all('/CREATE TABLE/i', $isi);
        $adaPenutup = str_contains($isi, 'SELESAI') || preg_match('/;\s*$/', rtrim($isi));

        $this->line('  Pernyataan CREATE TABLE : ' . $tabelDitemukan);
        $this->line('  Berakhir utuh           : ' . ($adaPenutup ? 'ya' : 'TIDAK'));

        if ($tabelDitemukan === 0) {
            $this->error('Tidak ada satu pun definisi tabel — berkas ini bukan dump yang sah.');

            return false;
        }

        if (! $adaPenutup) {
            $this->error('Berkas berakhir di tengah pernyataan — kemungkinan terpotong saat ditulis.');

            return false;
        }

        return true;
    }

    /**
     * Memulihkan ke database SEMENTARA, membandingkan isinya, lalu membuang
     * database itu apa pun hasilnya.
     */
    private function ujiPemulihan(string $path): int
    {
        $koneksi = config('database.default');

        if ($koneksi !== 'mysql') {
            $this->warn('Pengujian pemulihan hanya didukung pada MySQL (koneksi aktif: ' . $koneksi . ').');
            $this->line('Keutuhan berkas sudah diperiksa di atas.');

            return self::SUCCESS;
        }

        $namaUji = config('database.connections.mysql.database') . '_verifikasi_' . now()->format('His');

        $this->newLine();
        $this->info('Menguji pemulihan ke database sementara: ' . $namaUji);

        $sebelum = $this->hitungTabel(null);

        try {
            DB::statement('CREATE DATABASE `' . $namaUji . '` CHARACTER SET utf8mb4');
        } catch (\Throwable $e) {
            $this->error('Tidak bisa membuat database sementara: ' . $e->getMessage());
            $this->line('Pengujian dihentikan. Database aktif TIDAK disentuh.');

            return self::FAILURE;
        }

        try {
            $this->jalankanDump($namaUji, $path);

            $sesudah = $this->hitungTabel($namaUji);

            $this->newLine();
            $this->line(sprintf('  %-24s %10s %10s', 'TABEL', 'AKTIF', 'PULIHAN'));

            $cocok = true;

            foreach (self::TABEL_PEMBANDING as $tabel) {
                $a = $sebelum[$tabel] ?? null;
                $b = $sesudah[$tabel] ?? null;

                $sama = $a !== null && $a === $b;
                $cocok = $cocok && $sama;

                $this->line(sprintf(
                    '  %-24s %10s %10s  %s',
                    $tabel,
                    $a ?? '-',
                    $b ?? '-',
                    $sama ? 'cocok' : 'BEDA'
                ));
            }

            $this->newLine();

            if (! $cocok) {
                $this->error('Isi hasil pemulihan TIDAK sama dengan database aktif.');

                return self::FAILURE;
            }

            $this->info('Cadangan ini utuh dan bisa dipulihkan.');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Pemulihan gagal: ' . $e->getMessage());

            return self::FAILURE;
        } finally {
            // Dibuang apa pun yang terjadi — database sementara yang
            // tertinggal akan membingungkan orang berikutnya dan memakan
            // ruang disk yang justru sedang menipis.
            try {
                DB::statement('DROP DATABASE IF EXISTS `' . $namaUji . '`');
                $this->line('Database sementara dibuang.');
            } catch (\Throwable $e) {
                $this->warn('Database sementara GAGAL dibuang: ' . $namaUji . ' — hapus manual.');
            }
        }
    }

    private function jalankanDump(string $database, string $path): void
    {
        $sql = file_get_contents($path);

        $pdo = DB::connection('mysql')->getPdo();
        $pdo->exec('USE `' . $database . '`');

        // Dump dijalankan sebagai satu rangkaian; kalau ada pernyataan yang
        // rusak, PDO melemparkan galat dan pemanggilnya melaporkannya.
        $pdo->exec($sql);

        $pdo->exec('USE `' . config('database.connections.mysql.database') . '`');
    }

    /** @return array<string,int> */
    private function hitungTabel(?string $database): array
    {
        $hasil = [];

        foreach (self::TABEL_PEMBANDING as $tabel) {
            $nama = $database === null ? $tabel : '`' . $database . '`.`' . $tabel . '`';

            try {
                $hasil[$tabel] = (int) DB::selectOne('SELECT COUNT(*) AS n FROM ' . $nama)->n;
            } catch (\Throwable $e) {
                // Tabel yang tidak ada dibiarkan kosong — perbandingannya
                // nanti akan menandainya sebagai BEDA, dan itu memang benar.
            }
        }

        return $hasil;
    }
}
