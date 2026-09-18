<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * MEMBERSIHKAN BERKAS EKSPOR YANG TERTINGGAL.
 *
 * ==========================================================================
 * MASALAH NYATA YANG DITEMUKAN SAAT PERSIAPAN DEPLOYMENT
 * ==========================================================================
 *
 * Ekspor "Laporan Lengkap" membungkus banyak PDF menjadi satu .zip di
 * storage/app, lalu mengirimkannya dengan deleteFileAfterSend(true).
 * Kodenya BENAR. Tetapi penghapusan itu hanya berjalan kalau pengiriman
 * respons selesai dengan wajar — kalau unduhan dibatalkan, koneksinya putus,
 * atau proses PHP-nya berakhir lebih dulu, berkasnya tetap tinggal.
 *
 * Di mesin pengembangan, 17 berkas semacam itu (13 MB) terbukti bertahan.
 * Di server yang dipakai setiap hari tanpa penunggu, dua hal menumpuk
 * sekaligus: ruang disk, dan salinan laporan berisi data peserta yang tidak
 * pernah dimaksudkan tersimpan permanen.
 *
 * ALASAN TIDAK MENGUBAH MEKANISME EKSPORNYA: mekanisme itu sudah benar dan
 * dipakai jalur unduhan yang sudah teruji. Yang kurang bukan kebenaran
 * kodenya, melainkan penyapu untuk sisa yang memang tidak bisa dihindari.
 *
 * BATAS USIA ADA ALASANNYA: berkas hanya dihapus setelah cukup tua, supaya
 * unduhan yang SEDANG berjalan tidak pernah ditarik dari bawah kaki
 * penggunanya.
 */
class BersihkanBerkasSementara extends Command
{
    protected $signature = 'cahayaos:bersihkan-berkas-sementara
                            {--umur=24 : Hanya hapus berkas yang lebih tua dari sekian jam}
                            {--lihat-saja : Tampilkan yang akan dihapus, tanpa menghapus apa pun}';

    protected $description = 'Menghapus berkas ekspor .zip yang tertinggal di storage/app karena unduhannya tidak selesai';

    /**
     * Hanya pola ini yang disentuh. Sengaja spesifik: perintah yang menyapu
     * "semua .zip di storage/app" suatu hari akan menghapus berkas yang
     * ditaruh orang lain di sana dengan sengaja.
     */
    private const POLA = 'laporan-lengkap-*.zip';

    public function handle(): int
    {
        $umurJam = (int) $this->option('umur');

        if ($umurJam < 1) {
            $this->error('Opsi --umur minimal 1 jam. Berkas yang baru dibuat bisa jadi sedang diunduh.');

            return self::FAILURE;
        }

        $lihatSaja = (bool) $this->option('lihat-saja');
        $batas = now()->subHours($umurJam)->getTimestamp();

        $berkas = glob(storage_path('app/' . self::POLA)) ?: [];
        $terhapus = 0;
        $totalByte = 0;
        $dilewati = 0;

        foreach ($berkas as $path) {
            if (! is_file($path)) {
                continue;
            }

            if (filemtime($path) > $batas) {
                // Masih muda — mungkin sedang diunduh saat ini juga.
                $dilewati++;

                continue;
            }

            $byte = (int) filesize($path);

            if ($lihatSaja) {
                $this->line('  akan dihapus: ' . basename($path) . ' (' . $this->formatUkuran($byte) . ')');
                $terhapus++;
                $totalByte += $byte;

                continue;
            }

            if (@unlink($path)) {
                $terhapus++;
                $totalByte += $byte;
            } else {
                $this->warn('  gagal menghapus: ' . basename($path));
            }
        }

        $kata = $lihatSaja ? 'akan dihapus' : 'dihapus';
        $this->info("{$terhapus} berkas ekspor {$kata} ({$this->formatUkuran($totalByte)}).");

        if ($dilewati > 0) {
            $this->line("{$dilewati} berkas dilewati karena belum berumur {$umurJam} jam.");
        }

        return self::SUCCESS;
    }

    private function formatUkuran(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2) . ' MB';
        }

        return round($bytes / 1024, 1) . ' KB';
    }
}
