<?php

namespace App\Http\Controllers\Concerns;

use App\Models\OmzetReport;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * VALIDASI PERIODE OMZET — Harian / Mingguan / Bulanan.
 *
 * Dipakai BERSAMA oleh jalur internal (pendamping mencatat) dan jalur publik
 * bertoken (peserta mengisi sendiri). Sama seperti MenyimpanBuktiOmzet,
 * aturannya tidak boleh disalin ke dua tempat: yang paling mungkin longgar
 * adalah jalur publik, satu-satunya yang bisa diakses tanpa login.
 *
 * ==========================================================================
 * YANG DIJAGA DI SINI, DAN KENAPA
 * ==========================================================================
 *
 * periode_urut dibatasi MENURUT BULANNYA, bukan dengan angka tetap.
 * Februari 2027 tidak punya tanggal 29 dan tidak punya minggu ke-5. Tanpa
 * batas yang mengikuti bulan, laporan "29 Februari" akan tersimpan dan
 * rentang tanggalnya dijepit ke akhir Februari — dua baris yang mengaku
 * periode berbeda padahal menunjuk hari yang sama, dan omzetnya terhitung
 * dua kali.
 *
 * Untuk mode Bulanan, periode_urut WAJIB 0. Kalau dibiarkan bebas, satu
 * peserta bisa punya "September 2026 urut 0" dan "September 2026 urut 1" —
 * keduanya lolos unique index, keduanya mengaku mewakili seluruh September.
 */
trait MenentukanPeriodeOmzet
{
    /**
     * Aturan validasi periode.
     *
     * Menerima $request karena batas periode_urut bergantung pada nilai
     * jenis_periode, periode_bulan, dan periode_tahun di request yang sama —
     * ketiganya tidak bisa diketahui dari luar closure.
     */
    protected function aturanPeriode(Request $request): array
    {
        return [
            'periode_bulan' => ['required', 'integer', 'min:1', 'max:12'],
            'periode_tahun' => ['required', 'integer', 'min:2000', 'max:' . (now()->year + 1)],
            // NULLABLE, bukan required — dan itu keputusan kompatibilitas
            // yang disengaja. Sebelum mode periode ada, setiap laporan omzet
            // adalah laporan bulanan, dan request lama tidak membawa field
            // ini sama sekali. Menjadikannya wajib akan menolak request yang
            // dulu sah padahal maksudnya tidak pernah ambigu: tidak diisi
            // berarti bulanan, persis seperti default kolomnya di database.
            'jenis_periode' => ['nullable', Rule::in(OmzetReport::jenisYangSah())],
            'periode_urut' => [
                'nullable',
                'integer',
                'min:0',
                function (string $atribut, $nilai, callable $gagal) use ($request) {
                    $this->periksaUrut($request, (int) $nilai, $gagal);
                },
            ],
        ];
    }

    protected function pesanPeriode(): array
    {
        return [
            'periode_bulan.required' => 'Bulan wajib dipilih.',
            'periode_tahun.required' => 'Tahun wajib diisi.',
            'jenis_periode.in' => 'Cara pencatatan tidak dikenali.',
            'periode_urut.integer' => 'Periode harus berupa angka.',
        ];
    }

    /**
     * Empat kolom periode yang siap disimpan.
     *
     * Mode Bulanan SELALU dinormalkan ke urut 0, apa pun yang dikirim
     * peramban — validasi di atas sudah menolak yang lain, dan ini menjaga
     * agar jalur lain (mis. request tanpa field periode_urut sama sekali)
     * tetap menghasilkan baris yang benar.
     */
    protected function periodeDari(array $validated): array
    {
        $jenis = $validated['jenis_periode'] ?? OmzetReport::BULANAN;

        return [
            'periode_bulan' => (int) $validated['periode_bulan'],
            'periode_tahun' => (int) $validated['periode_tahun'],
            'jenis_periode' => $jenis,
            'periode_urut' => $jenis === OmzetReport::BULANAN
                ? 0
                : (int) ($validated['periode_urut'] ?? 0),
        ];
    }

    /** Isi closure validasi periode_urut — dipisah supaya alasannya punya tempat. */
    private function periksaUrut(Request $request, int $nilai, callable $gagal): void
    {
        // Mode yang tidak dikirim berarti bulanan — sama dengan aturan di
        // periodeDari(), supaya validasi dan penyimpanan tidak pernah membaca
        // request yang sama dengan cara berbeda.
        $jenis = $request->input('jenis_periode') ?: OmzetReport::BULANAN;
        $bulan = (int) $request->input('periode_bulan');
        $tahun = (int) $request->input('periode_tahun');

        // Bulan/tahun yang tidak masuk akal sudah punya pesan kesalahannya
        // sendiri. Menambahkan pesan kedua di sini hanya membingungkan.
        if ($bulan < 1 || $bulan > 12 || $tahun < 2000) {
            return;
        }

        if ($jenis === OmzetReport::BULANAN) {
            if ($nilai !== 0) {
                $gagal('Mode bulanan mencakup seluruh bulan, jadi tidak memakai nomor periode.');
            }

            return;
        }

        if (! in_array($jenis, OmzetReport::jenisYangSah(), true)) {
            return; // jenis_periode sendiri yang akan melaporkan kesalahannya
        }

        $maks = OmzetReport::urutMaksimal($jenis, $bulan, $tahun);

        if ($nilai < 1 || $nilai > $maks) {
            $gagal($jenis === OmzetReport::HARIAN
                ? 'Tanggal harus antara 1 dan ' . $maks . ' untuk '
                    . OmzetReport::namaBulan($bulan) . ' ' . $tahun . '.'
                : 'Minggu harus antara 1 dan ' . $maks . ' untuk '
                    . OmzetReport::namaBulan($bulan) . ' ' . $tahun . '.');
        }
    }
}
