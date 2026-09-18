<?php

namespace App\Http\Controllers\Concerns;

use App\Models\ActivityLog;
use App\Models\OmzetReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * UNGGAH BUKTI LAPORAN OMZET — dipakai BERSAMA oleh dua jalur input.
 *
 * Jalur internal (pendamping mencatat saat kunjungan) dan jalur publik
 * bertoken (peserta mengisi sendiri lewat ponsel) menyimpan bukti dengan
 * aturan yang harus sama persis. Kalau aturannya disalin ke dua controller,
 * cepat atau lambat salah satunya akan longgar — dan yang longgar hampir
 * pasti jalur publik, satu-satunya yang bisa diakses tanpa login.
 *
 * ==========================================================================
 * TIGA HAL YANG DIJAGA DI SINI
 * ==========================================================================
 *
 * 1. NAMA FILE TIDAK PERNAH BERASAL DARI PESERTA.
 *    store() milik Laravel membangkitkan nama acak; nama unggahan hanya
 *    disimpan sebagai teks untuk ditampilkan. Nama dari luar bisa memuat
 *    "../", karakter bermakna di shell, atau ekstensi ganda seperti
 *    "nota.jpg.php".
 *
 * 2. FILE LAMA TIDAK DIHAPUS SAAT DIGANTI.
 *    Bukti adalah jejak audit atas angka yang sudah masuk ke laporan dan
 *    perhitungan dampak. Kalau bukti bisa ditimpa tanpa sisa, seseorang bisa
 *    memperbaiki angka sekaligus menghapus dasar angka sebelumnya. File lama
 *    dibiarkan di disk dan penggantiannya dicatat di Activity Log lengkap
 *    dengan path lamanya, jadi selalu bisa ditelusuri.
 *
 * 3. UNGGAHAN GAGAL TIDAK MENGOSONGKAN BUKTI YANG SUDAH ADA.
 *    Request tanpa berkas berarti "tidak mengubah bukti", bukan "hapus
 *    bukti". Peserta yang mengedit angkanya lewat form yang sama tidak
 *    kehilangan bukti yang sudah dikirim minggu lalu.
 */
trait MenyimpanBuktiOmzet
{
    /**
     * Aturan validasi berkas bukti — SATU definisi untuk kedua jalur.
     *
     * `mimes` memeriksa isi berkas lewat ekstensi yang ditebak dari tipe
     * aslinya, bukan sekadar nama yang dikirim peramban. Berkas .php yang
     * dinamai ulang jadi .jpg tetap ditolak.
     */
    protected function aturanBukti(): array
    {
        return [
            'bukti' => [
                'nullable',
                'file',
                'mimes:' . implode(',', OmzetReport::BUKTI_MIME),
                'max:' . OmzetReport::BUKTI_MAKS_KB,
            ],
        ];
    }

    protected function pesanBukti(): array
    {
        return [
            'bukti.file' => 'Bukti harus berupa berkas yang diunggah.',
            'bukti.mimes' => 'Bukti harus berupa foto (JPG, PNG, WEBP) atau PDF.',
            'bukti.max' => 'Ukuran bukti maksimal 5 MB. Kalau fotonya terlalu besar, '
                . 'kirim ulang dengan resolusi lebih kecil.',
        ];
    }

    /**
     * Simpan berkas bukti kalau ada di request.
     *
     * Tidak melakukan apa-apa kalau request tidak membawa berkas — lihat
     * poin 3 di atas. Mengembalikan true kalau ada bukti baru tersimpan.
     */
    protected function simpanBukti(Request $request, OmzetReport $laporan): bool
    {
        if (! $request->hasFile('bukti')) {
            return false;
        }

        $lama = $laporan->bukti_path;
        $berkas = $request->file('bukti');

        // Dipisah per peserta supaya satu folder tidak menampung puluhan ribu
        // berkas, dan supaya jejaknya mudah ditelusuri manual saat audit.
        // Disk default aplikasi ini adalah `local` = storage/app/private.
        $path = $berkas->store('bukti-omzet/' . $laporan->participant_id);

        // store() mengembalikan false kalau penyimpanan gagal. Menyimpan
        // path yang salah lebih buruk daripada tidak menyimpan bukti: baris
        // laporannya akan mengaku punya bukti yang tidak bisa dibuka.
        if ($path === false) {
            return false;
        }

        $laporan->forceFill([
            'bukti_path' => $path,
            'bukti_asli' => $berkas->getClientOriginalName(),
        ])->save();

        if ($lama) {
            // Berkas lama TIDAK dihapus. Yang dicatat di sini adalah di mana
            // ia berada, supaya penggantian bukti selalu bisa ditelusuri
            // walau barisnya sudah menunjuk berkas yang baru.
            ActivityLog::record(
                'omzet_updated',
                'OmzetReport',
                $laporan->id,
                'Mengganti bukti laporan omzet ' . $laporan->periodeLabel()
                    . '. Bukti sebelumnya tetap tersimpan di: ' . $lama,
            );
        }

        return true;
    }
}
