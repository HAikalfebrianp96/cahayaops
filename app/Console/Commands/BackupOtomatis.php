<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Services\BackupDatabaseService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * BACKUP DATABASE TERJADWAL.
 *
 * ==========================================================================
 * KENAPA PERLU, PADAHAL TOMBOL BACKUP SUDAH ADA
 * ==========================================================================
 *
 * Halaman Backup & Restore sudah lengkap, tapi seluruhnya bergantung pada
 * ADA ORANG YANG MENGKLIK. Cadangan yang bergantung pada ingatan manusia
 * akan gagal tepat pada minggu yang paling sibuk — dan yang hilang di sini
 * bukan file biasa: buku simpanan anggota koperasi, dan riwayat omzet yang
 * dilaporkan peserta satu per satu selama berbulan-bulan.
 *
 * Perintah ini TIDAK membuat mekanisme backup baru. Ia memanggil
 * BackupDatabaseService yang sama persis dengan tombol di halaman Admin,
 * sehingga file yang dihasilkan cron dan file yang dihasilkan tombol
 * identik — dan keduanya bisa dipulihkan lewat halaman Restore yang sama.
 *
 * KEGAGALAN TIDAK BOLEH SENYAP. Tugas terjadwal yang gagal diam-diam lebih
 * berbahaya daripada tidak ada tugas sama sekali: Admin merasa terlindungi
 * padahal tidak. Karena itu kegagalan ditulis ke log DAN ke Activity Log,
 * dan perintah keluar dengan kode 1 supaya cron ikut menandainya.
 */
class BackupOtomatis extends Command
{
    protected $signature = 'cahayaos:backup-otomatis';

    protected $description = 'Membuat backup database terjadwal (memakai mekanisme yang sama dengan tombol Backup di halaman Admin)';

    public function handle(BackupDatabaseService $backup): int
    {
        // ============================================================
        // RUANG DISK DIPERIKSA SEBELUM MENULIS APA PUN.
        //
        // Menjalankan backup di disk yang hampir penuh berisiko
        // menghasilkan berkas SEPARUH JADI — dan berkas backup separuh jadi
        // lebih berbahaya daripada tidak ada backup sama sekali: ia
        // terlihat seperti cadangan, bernama seperti cadangan, dan baru
        // ketahuan tidak utuh pada saat dipakai memulihkan data.
        //
        // Kalau ruangnya kurang, backup TIDAK dijalankan dan keadaannya
        // disebut apa adanya: masalahnya penyimpanan, bukan backupnya.
        // Perintahnya tetap mengembalikan FAILURE karena memang tidak ada
        // cadangan yang terbuat — itu kegagalan sungguhan, bukan galat
        // buatan.
        // ============================================================
        $disk = $backup->ruangDisk();

        if (! $disk['cukup']) {
            $pesan = 'Backup dilewati — ruang disk tidak cukup. Tersisa '
                . $backup->formatUkuran((int) $disk['bebas'])
                . ', minimal ' . $backup->formatUkuran($disk['minimal']) . '. '
                . 'Kosongkan ruang penyimpanan atau pindahkan backup lama ke media lain, '
                . 'lalu jalankan lagi.';

            Log::warning('Backup terjadwal dilewati: ruang disk tidak cukup ('
                . $backup->formatUkuran((int) $disk['bebas']) . ' tersisa).');

            ActivityLog::record(
                action: 'backup_dilewati_disk_rendah',
                modelType: 'SystemSetting',
                modelId: null,
                description: $pesan,
            );

            $this->warn($pesan);

            return self::FAILURE;
        }

        $this->info('Membuat backup database…');

        try {
            $namaFile = $backup->buatFile('backup');
        } catch (\Throwable $e) {
            // Dicatat ke DUA tempat: log server (untuk teknisi) dan Activity
            // Log (supaya Admin melihatnya tanpa perlu akses server).
            Log::error('Backup terjadwal GAGAL: ' . $e->getMessage());

            ActivityLog::record(
                action: 'backup_terjadwal_gagal',
                modelType: 'SystemSetting',
                modelId: null,
                description: 'Backup terjadwal GAGAL: ' . $e->getMessage(),
            );

            $this->error('Backup GAGAL: ' . $e->getMessage());

            return self::FAILURE;
        }

        $path = $backup->direktori() . '/' . $namaFile;
        $ukuran = is_file($path) ? $backup->formatUkuran((int) filesize($path)) : 'tidak diketahui';

        ActivityLog::record(
            action: 'backup_terjadwal_dibuat',
            modelType: 'SystemSetting',
            modelId: null,
            description: "Backup terjadwal dibuat: {$namaFile} ({$ukuran})",
        );

        $this->info("Selesai: {$namaFile} ({$ukuran})");

        // Disebut terang-terangan: berapa yang disimpan dan berapa yang ada.
        // Retensi yang bekerja diam-diam membuat orang terkejut saat mencari
        // backup bulan lalu dan tidak menemukannya.
        // Dihitung dengan pola yang SAMA PERSIS dengan yang diatur retensi
        // ("backup-*"), bukan dengan penanda is_pre_restore.
        //
        // Folder cadangan bisa memuat berkas tonggak bernama lain — misalnya
        // "pra-softdeletes-*.sql" yang dibuat sebelum sebuah migrasi besar.
        // Berkas itu sengaja TIDAK diatur retensi, jadi menghitungnya sebagai
        // backup rutin membuat angka di layar tidak cocok dengan kebijakan
        // yang sebenarnya berlaku.
        $jumlahRutin = count(array_filter(
            $backup->daftar(),
            fn (array $f) => str_starts_with($f['name'], 'backup-'),
        ));

        $this->line('  Backup rutin disimpan: ' . $jumlahRutin . ' berkas '
            . '(retensi ' . $backup->retensi() . ' terbaru; backup pra-restore tidak ikut dihapus).');

        // Pengingat yang sengaja diulang setiap kali: backup yang tersimpan di
        // server yang sama dengan databasenya tidak menyelamatkan apa pun
        // kalau server itu sendiri yang hilang.
        $this->line('  Salin berkas ini ke luar server secara berkala — backup yang hanya ada di server yang sama tidak melindungi dari kegagalan server.');

        return self::SUCCESS;
    }
}
