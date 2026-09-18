<?php

namespace Tests\Feature;

use App\Services\GambarCetak;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * GAMBAR IDENTITAS YANG DITANAM KE BERKAS CETAK.
 *
 * =====================================================================
 * KEADAAN YANG DITEMUKAN
 * =====================================================================
 *
 * Logo, tanda tangan, dan stempel koperasi diunggah apa adanya dari
 * pemindai, lalu ditanam utuh ke setiap PDF:
 *
 *   tanda tangan  6000 x 3375 px (1.085 KB)  dicetak setinggi 40 px
 *   logo          3231 x 3231 px (  283 KB)  dicetak setinggi 44 px
 *   stempel       3231 x 3231 px (  251 KB)  dicetak setinggi 60 px
 *
 * Setiap laporan koperasi karena itu berukuran ~1,6 MB. Delapan laporan
 * untuk satu Rapat Anggota menjadi belasan megabita, dan setiap invoice
 * yang dikirim ke pelanggan membawa beban yang sama.
 *
 * =====================================================================
 * YANG DIJAGA DI SINI
 * =====================================================================
 *
 * Bukan sekadar "berkasnya jadi kecil", melainkan tiga hal yang kalau
 * salah justru merugikan:
 *
 *   1. Berkas ASLI tidak pernah berubah. Itu berkas identitas resmi
 *      lembaga; mengecilkannya secara permanen tidak bisa dibatalkan.
 *   2. Transparansi ikut terbawa. Tanda tangan berlatar transparan yang
 *      kehilangan alpha menjadi kotak hitam di kolom tanda tangan —
 *      laporannya tetap terbit, tapi tidak layak dibawa ke rapat.
 *   3. Kegagalan apa pun mengembalikan berkas aslinya. Penghematan ukuran
 *      tidak boleh menjadi alasan laporan gagal keluar.
 */
class GambarCetakTest extends TestCase
{
    use RefreshDatabase;

    private string $ruang;
    private string $storageAsli;
    private string $storageUji;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ruang = sys_get_temp_dir() . '/cahayaos-gambar-' . uniqid();
        File::ensureDirectoryExists($this->ruang);

        // Salinan cetak menumpang di storage. Diarahkan ke folder sementara
        // supaya test tidak menaruh berkas di storage sungguhan mesin ini.
        $this->storageAsli = $this->app->storagePath();
        $this->storageUji = sys_get_temp_dir() . '/cahayaos-storage-gambar-' . uniqid();
        File::ensureDirectoryExists($this->storageUji . '/app');
        $this->app->useStoragePath($this->storageUji);
    }

    protected function tearDown(): void
    {
        $this->app->useStoragePath($this->storageAsli);

        File::deleteDirectory($this->ruang);
        File::deleteDirectory($this->storageUji);

        parent::tearDown();
    }

    private function buatPng(string $nama, int $lebar, int $tinggi, bool $transparan = false): string
    {
        $im = imagecreatetruecolor($lebar, $tinggi);

        if ($transparan) {
            imagealphablending($im, false);
            imagesavealpha($im, true);
            imagefill($im, 0, 0, imagecolorallocatealpha($im, 0, 0, 0, 127));
        } else {
            imagefill($im, 0, 0, imagecolorallocate($im, 255, 255, 255));
        }

        // Sedikit isi supaya berkasnya tidak seragam sempurna.
        imagefilledrectangle($im, (int) ($lebar / 4), (int) ($tinggi / 4), (int) ($lebar / 2), (int) ($tinggi / 2),
            imagecolorallocate($im, 20, 40, 90));

        $path = $this->ruang . '/' . $nama;
        imagepng($im, $path);
        imagedestroy($im);

        return $path;
    }

    private function cetak(): GambarCetak
    {
        return app(GambarCetak::class);
    }

    // =================================================================
    // PENYUSUTAN
    // =================================================================

    public function test_gambar_besar_disusutkan(): void
    {
        $asli = $this->buatPng('ttd.png', 3000, 1600);

        $hasil = $this->cetak()->untukCetak($asli);

        $this->assertNotSame($asli, $hasil, 'Gambar sebesar ini harus disalin, bukan dipakai apa adanya.');

        [$lebar, $tinggi] = getimagesize($hasil);

        $this->assertLessThanOrEqual(GambarCetak::SISI_MAKS, $lebar);
        $this->assertLessThanOrEqual(GambarCetak::SISI_MAKS, $tinggi);
        $this->assertLessThan(filesize($asli), filesize($hasil));
    }

    /** Bentuknya tidak boleh berubah — tanda tangan yang gepeng terlihat palsu. */
    public function test_perbandingan_sisi_dipertahankan(): void
    {
        $asli = $this->buatPng('ttd.png', 3000, 1500);

        [$lebar, $tinggi] = getimagesize($this->cetak()->untukCetak($asli));

        $this->assertSame(2.0, round($lebar / $tinggi, 2));
    }

    /**
     * BERKAS ASLI TIDAK BOLEH BERUBAH.
     *
     * Ini berkas identitas resmi lembaga. Kalau suatu saat penyusutannya
     * dibuat menimpa sumbernya demi menghemat ruang, tidak ada cara
     * mengembalikan berkas beresolusi penuhnya.
     */
    public function test_berkas_asli_tidak_disentuh(): void
    {
        $asli = $this->buatPng('ttd.png', 3000, 1600);

        $isiSebelum = md5_file($asli);
        $ukuranSebelum = filesize($asli);
        $waktuSebelum = filemtime($asli);

        $this->cetak()->untukCetak($asli);

        $this->assertFileExists($asli);
        $this->assertSame($isiSebelum, md5_file($asli));
        $this->assertSame($ukuranSebelum, filesize($asli));
        $this->assertSame($waktuSebelum, filemtime($asli));
    }

    /**
     * Transparansi ikut terbawa.
     *
     * Tanpa penjagaan ini, tanda tangan berlatar transparan berubah menjadi
     * kotak hitam pekat di kolom tanda tangan laporan.
     */
    public function test_transparansi_png_ikut_terbawa(): void
    {
        $asli = $this->buatPng('ttd.png', 2000, 1000, transparan: true);

        $hasil = $this->cetak()->untukCetak($asli);

        $im = imagecreatefrompng($hasil);
        $alpha = (imagecolorat($im, 0, 0) >> 24) & 0x7F;
        imagedestroy($im);

        $this->assertGreaterThan(
            100,
            $alpha,
            'Sudut gambar harus tetap transparan, bukan menjadi hitam pekat.'
        );
    }

    // =================================================================
    // TIDAK MENGERJAKAN YANG TIDAK PERLU
    // =================================================================

    public function test_gambar_yang_sudah_kecil_dipakai_apa_adanya(): void
    {
        $asli = $this->buatPng('logo.png', 300, 200);

        $this->assertSame($asli, $this->cetak()->untukCetak($asli));
    }

    public function test_salinan_dipakai_ulang_bukan_dibuat_lagi(): void
    {
        $asli = $this->buatPng('ttd.png', 2000, 1000);

        $pertama = $this->cetak()->untukCetak($asli);
        $waktu = filemtime($pertama);

        $kedua = $this->cetak()->untukCetak($asli);

        $this->assertSame($pertama, $kedua);
        $this->assertSame($waktu, filemtime($kedua), 'Salinan yang sama tidak perlu dibuat dua kali.');
    }

    /**
     * Mengganti gambar harus menghasilkan salinan baru.
     *
     * Kalau kuncinya hanya nama berkas, mengunggah tanda tangan baru dengan
     * nama yang sama akan tetap mencetak tanda tangan LAMA — dan tidak ada
     * yang menyadarinya sampai laporannya terlanjur dibagikan.
     */
    public function test_mengganti_sumber_menghasilkan_salinan_baru(): void
    {
        $asli = $this->buatPng('ttd.png', 2000, 1000);
        $pertama = $this->cetak()->untukCetak($asli);

        // Berkas berbeda, nama sama.
        unlink($asli);
        $asli = $this->buatPng('ttd.png', 2400, 1200);
        touch($asli, time() + 5);

        $this->assertNotSame($pertama, $this->cetak()->untukCetak($asli));
    }

    // =================================================================
    // GAGAL DENGAN AMAN
    // =================================================================

    public function test_path_kosong_dikembalikan_apa_adanya(): void
    {
        $this->assertNull($this->cetak()->untukCetak(null));
    }

    public function test_berkas_tidak_ada_dikembalikan_apa_adanya(): void
    {
        $hilang = $this->ruang . '/tidak-ada.png';

        $this->assertSame($hilang, $this->cetak()->untukCetak($hilang));
    }

    /**
     * Berkas yang bukan gambar tidak boleh membuat laporan gagal terbit.
     */
    public function test_berkas_rusak_dikembalikan_apa_adanya(): void
    {
        $rusak = $this->ruang . '/rusak.png';
        file_put_contents($rusak, 'ini jelas bukan gambar');

        $this->assertSame($rusak, $this->cetak()->untukCetak($rusak));
    }
}
