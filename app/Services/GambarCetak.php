<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * MENYUSUTKAN GAMBAR IDENTITAS UNTUK BERKAS CETAK.
 *
 * =========================================================================
 * KEADAAN YANG DITEMUKAN
 * =========================================================================
 *
 * Logo, tanda tangan, dan stempel koperasi diunggah apa adanya dari kamera
 * atau pemindai, lalu ditanamkan utuh ke setiap PDF:
 *
 *   tanda tangan   6000 x 3375 px  (1.085 KB)  dicetak setinggi 40 px
 *   logo           3231 x 3231 px  (  283 KB)  dicetak setinggi 44 px
 *   stempel        3231 x 3231 px  (  251 KB)  dicetak setinggi 60 px
 *
 * Akibatnya setiap laporan koperasi berukuran ~1,6 MB — sepuluh kali lipat
 * laporan Cahaya Preneur yang isinya justru lebih panjang — dan butuh
 * beberapa detik untuk dibuat. Delapan laporan untuk satu Rapat Anggota
 * menjadi belasan megabita, dan setiap invoice yang dikirim ke pelanggan
 * membawa beban yang sama.
 *
 * =========================================================================
 * SALINAN, BUKAN PENGGANTIAN
 * =========================================================================
 *
 * Berkas asli TIDAK PERNAH disentuh: tidak ditimpa, tidak dihapus, tidak
 * dipindah. Yang dibuat adalah salinan kecil di storage, dan hanya salinan
 * itulah yang dipakai PDF. Halaman layar tetap memakai berkas aslinya.
 *
 * Alasannya sederhana: berkas identitas resmi adalah milik lembaga, dan
 * mengecilkannya secara permanen menghilangkan sesuatu yang tidak bisa
 * dikembalikan.
 *
 * =========================================================================
 * GAGAL DENGAN AMAN
 * =========================================================================
 *
 * Setiap kegagalan — GD tidak ada, berkas tidak terbaca, storage tidak bisa
 * ditulis — mengembalikan path ASLINYA. Laporan tetap tercetak, hanya
 * kembali berat. Sebuah penghematan ukuran tidak boleh menjadi alasan
 * laporan gagal keluar tepat ketika dibutuhkan.
 */
class GambarCetak
{
    /**
     * Sisi terpanjang salinan cetak, dalam piksel.
     *
     * Elemen terbesar yang dicetak adalah stempel setinggi 60 pt. Pada 300
     * DPI itu setara ~250 px. Angka 600 memberi ruang lebih dari dua kali
     * lipat, sehingga tetap tajam kalau kelak ada yang dicetak lebih besar.
     */
    public const SISI_MAKS = 600;

    /**
     * Path gambar yang siap ditanam ke PDF.
     *
     * Mengembalikan path asli apa adanya kalau tidak ada yang bisa dihemat
     * atau kalau penyusutannya gagal.
     */
    public function untukCetak(?string $path, int $sisiMaks = self::SISI_MAKS): ?string
    {
        if ($path === null || ! is_file($path) || ! is_readable($path)) {
            return $path;
        }

        try {
            $ukuran = @getimagesize($path);

            if ($ukuran === false) {
                return $path;
            }

            [$lebar, $tinggi] = $ukuran;
            $tipe = $ukuran[2];

            // Sudah cukup kecil — tidak ada gunanya menyalin.
            if ($lebar <= $sisiMaks && $tinggi <= $sisiMaks) {
                return $path;
            }

            if (! in_array($tipe, [IMAGETYPE_JPEG, IMAGETYPE_PNG], true)) {
                return $path;
            }

            $tujuan = $this->pathSalinan($path, $sisiMaks, $tipe);

            if (is_file($tujuan)) {
                return $tujuan;
            }

            return $this->susutkan($path, $tujuan, $lebar, $tinggi, $sisiMaks, $tipe) ? $tujuan : $path;
        } catch (\Throwable $e) {
            Log::warning('Penyusutan gambar cetak dilewati: ' . $e->getMessage());

            return $path;
        }
    }

    /**
     * Nama salinan ikut menyertakan waktu ubah dan ukuran berkas sumbernya.
     *
     * Tanpa itu, mengganti logo dengan berkas bernama sama akan tetap
     * mencetak logo yang lama — dan tidak ada yang menyadarinya sampai
     * laporan sudah terlanjur dibagikan.
     */
    private function pathSalinan(string $sumber, int $sisiMaks, int $tipe): string
    {
        $kunci = sha1(implode('|', [
            realpath($sumber) ?: $sumber,
            (string) @filemtime($sumber),
            (string) @filesize($sumber),
            (string) $sisiMaks,
        ]));

        $ext = $tipe === IMAGETYPE_PNG ? 'png' : 'jpg';

        return $this->direktori() . DIRECTORY_SEPARATOR . $kunci . '.' . $ext;
    }

    public function direktori(): string
    {
        $dir = storage_path('app/gambar-cetak');

        File::ensureDirectoryExists($dir);

        return $dir;
    }

    private function susutkan(
        string $sumber,
        string $tujuan,
        int $lebar,
        int $tinggi,
        int $sisiMaks,
        int $tipe,
    ): bool {
        if (! function_exists('imagecreatefromstring')) {
            return false;
        }

        $asli = @imagecreatefromstring((string) file_get_contents($sumber));

        if ($asli === false) {
            return false;
        }

        $rasio = min($sisiMaks / $lebar, $sisiMaks / $tinggi);
        $lebarBaru = max(1, (int) round($lebar * $rasio));
        $tinggiBaru = max(1, (int) round($tinggi * $rasio));

        $kecil = imagecreatetruecolor($lebarBaru, $tinggiBaru);

        // TRANSPARANSI HARUS IKUT.
        //
        // Tanda tangan dan stempel hampir selalu PNG berlatar transparan.
        // Tanpa tiga baris ini, latarnya menjadi kotak hitam pekat di atas
        // kolom tanda tangan — laporan tetap terbit, tapi tidak layak
        // dibawa ke Rapat Anggota.
        if ($tipe === IMAGETYPE_PNG) {
            imagealphablending($kecil, false);
            imagesavealpha($kecil, true);
            imagefill($kecil, 0, 0, imagecolorallocatealpha($kecil, 0, 0, 0, 127));
        }

        imagecopyresampled($kecil, $asli, 0, 0, 0, 0, $lebarBaru, $tinggiBaru, $lebar, $tinggi);

        $berhasil = $tipe === IMAGETYPE_PNG
            ? imagepng($kecil, $tujuan, 6)
            : imagejpeg($kecil, $tujuan, 85);

        imagedestroy($asli);
        imagedestroy($kecil);

        // Salinan separuh jadi lebih buruk daripada tidak ada salinan sama
        // sekali: ia akan dipakai berulang kali karena dikira sudah jadi.
        if (! $berhasil || ! is_file($tujuan) || filesize($tujuan) === 0) {
            @unlink($tujuan);

            return false;
        }

        return true;
    }
}
