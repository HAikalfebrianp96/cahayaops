<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Services\BackupDatabaseService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * MENYALIN CADANGAN KE LUAR DISK INI.
 *
 * =========================================================================
 * KENAPA PERINTAH INI ADA
 * =========================================================================
 *
 * Cadangan yang sudah ada tersimpan di disk yang SAMA dengan databasenya.
 * Kalau disk itu rusak, komputernya hilang, atau kena ransomware, database
 * dan seluruh cadangannya lenyap bersamaan — praktis sama dengan tidak
 * pernah punya cadangan.
 *
 * =========================================================================
 * MENYALIN, BUKAN MEMBUAT DUMP KEDUA
 * =========================================================================
 *
 * Berkas yang disalin adalah berkas lokal yang SUDAH jadi — bukan hasil
 * dump ulang. Dua alasan:
 *
 *   1. Dump kedua berarti membaca seluruh database sekali lagi, dan
 *      isinya bisa sudah berbeda dari yang pertama.
 *   2. Salinan yang byte-nya identik dengan berkas lokal berarti verifikasi
 *      yang sudah dijalankan pada berkas lokal BERLAKU juga untuk
 *      salinannya. Kalau keduanya dump terpisah, keduanya harus diverifikasi
 *      terpisah pula.
 *
 * =========================================================================
 * MATI KALAU TIDAK DIKONFIGURASI
 * =========================================================================
 *
 * Tanpa cahayaos.backup.folder_luar, perintah ini berhenti dengan
 * penjelasan dan kode sukses — bukan galat. Menyalin ke luar adalah
 * pilihan, dan pilihan yang belum diambil bukan kegagalan.
 */
class SalinCadanganLuar extends Command
{
    protected $signature = 'cahayaos:salin-cadangan-luar
                            {--tujuan= : Folder tujuan. Bawaan: cahayaos.backup.folder_luar}';

    protected $description = 'Menyalin cadangan terbaru ke folder di luar disk ini (cloud tersinkron / disk eksternal).';

    public function handle(BackupDatabaseService $backup): int
    {
        $tujuan = $this->option('tujuan') ?: config('cahayaos.backup.folder_luar');

        if (blank($tujuan)) {
            $this->line('Folder cadangan luar belum diatur — penyalinan dilewati.');
            $this->line('Atur dengan: scripts\\pasang-cadangan-luar.ps1');

            return self::SUCCESS;
        }

        $sumber = $this->cadanganTerbaru($backup);

        if ($sumber === null) {
            $this->error('Tidak ada cadangan lokal yang bisa disalin. Jalankan dulu: php artisan cahayaos:backup-otomatis');

            return self::FAILURE;
        }

        if (! is_dir($tujuan)) {
            return $this->gagal('Folder tujuan tidak ditemukan: ' . $tujuan
                . '. Kalau ini folder cloud atau disk eksternal, pastikan sudah terpasang dan tersinkron.');
        }

        if (! is_writable($tujuan)) {
            return $this->gagal('Folder tujuan tidak bisa ditulis: ' . $tujuan);
        }

        $nama = basename($sumber);
        $path = rtrim($tujuan, '\\/') . DIRECTORY_SEPARATOR . $nama;

        if (is_file($path) && filesize($path) === filesize($sumber)) {
            $this->info('Sudah ada salinan yang sama di tujuan: ' . $nama);

            return self::SUCCESS;
        }

        if (! @copy($sumber, $path)) {
            return $this->gagal('Penyalinan gagal ke ' . $path . '. Periksa ruang dan hak akses folder tujuan.');
        }

        // Dibandingkan ukurannya, bukan diasumsikan berhasil: penyalinan ke
        // media jaringan atau folder cloud bisa berhenti di tengah dan tetap
        // meninggalkan berkas.
        if (filesize($path) !== filesize($sumber)) {
            @unlink($path);

            return $this->gagal('Salinan tidak utuh (ukurannya berbeda dari sumber) — salinan separuh jadi dibuang.');
        }

        $ukuran = $backup->formatUkuran((int) filesize($path));

        ActivityLog::record(
            action: 'cadangan_luar_disalin',
            modelType: 'SystemSetting',
            modelId: null,
            description: "Cadangan disalin ke luar: {$nama} ({$ukuran}) -> {$tujuan}",
        );

        $this->info("Disalin: {$nama} ({$ukuran})");
        $this->line('  Tujuan: ' . $tujuan);

        $this->terapkanRetensiTujuan($tujuan);

        return self::SUCCESS;
    }

    private function cadanganTerbaru(BackupDatabaseService $backup): ?string
    {
        $daftar = glob($backup->direktori() . '/backup-*.sql') ?: [];

        if ($daftar === []) {
            return null;
        }

        usort($daftar, fn ($a, $b) => filemtime($b) <=> filemtime($a));

        return $daftar[0];
    }

    /**
     * Retensi di folder tujuan — memakai aturan yang sama amannya dengan
     * retensi lokal: hanya berkas berpola "backup-*.sql", dan tidak pernah
     * menghapus sampai tidak tersisa satu pun.
     */
    private function terapkanRetensiTujuan(string $tujuan): void
    {
        $simpan = max(1, (int) config('cahayaos.backup.retensi_luar', 14));

        $berkas = glob(rtrim($tujuan, '\\/') . DIRECTORY_SEPARATOR . 'backup-*.sql') ?: [];

        if (count($berkas) <= $simpan) {
            $this->line('  Salinan di tujuan: ' . count($berkas) . ' berkas (retensi ' . $simpan . ').');

            return;
        }

        usort($berkas, fn ($a, $b) => filemtime($b) <=> filemtime($a));

        foreach (array_slice($berkas, $simpan) as $lama) {
            @unlink($lama);
        }

        $this->line('  Salinan di tujuan: ' . $simpan . ' berkas dipertahankan, '
            . (count($berkas) - $simpan) . ' yang terlama dibuang.');
    }

    private function gagal(string $pesan): int
    {
        Log::error('Salin cadangan luar GAGAL: ' . $pesan);

        ActivityLog::record(
            action: 'cadangan_luar_gagal',
            modelType: 'SystemSetting',
            modelId: null,
            description: 'Salin cadangan luar GAGAL: ' . $pesan,
        );

        $this->error($pesan);

        return self::FAILURE;
    }
}
