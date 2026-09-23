<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\AkunKas;
use App\Models\PembayaranPenjualan;
use App\Models\SalesTransaction;
use Illuminate\Support\Facades\Auth;

/**
 * SATU-SATUNYA PINTU untuk mengubah uang yang diterima dari penjualan.
 *
 * Aturannya:
 *
 *   1. Setiap uang masuk = satu baris pembayaran_penjualan.
 *   2. Baris pembayaran tidak pernah diedit atau dihapus — koreksi dilakukan
 *      dengan membatalkan (beserta alasan) lalu mencatat yang baru.
 *   3. SalesTransaction::dp_nominal dan status_pembayaran selalu diturunkan
 *      dari baris yang berlaku (hitungUlang), tidak pernah diisi tangan.
 *
 * Aturan nomor 3 yang menjaga invoice, laba rugi, laporan, dan SHU tetap
 * benar tanpa diubah: semuanya masih membaca dp_nominal, dan dp_nominal kini
 * selalu sama dengan jumlah riwayat pembayarannya.
 */
class PembayaranPenjualanService
{
    /** Lihat AkunKas::bawaanUntukMetode() — aturannya sama untuk semua modul. */
    public function akunBawaan(?string $metode): ?AkunKas
    {
        return AkunKas::bawaanUntukMetode($metode);
    }

    /**
     * Transaksi yang dibuat sebelum riwayat pembayaran ada (atau lewat jalur
     * lain seperti impor/seeder) punya dp_nominal tanpa satu pun baris
     * pembayaran. Sebelum riwayatnya diubah, angka lama itu dijadikan baris
     * pembayaran dulu — kalau tidak, uang yang sudah diterima hilang dari
     * hitungan begitu ada pembayaran baru.
     *
     * WAJIB dipanggil SEBELUM transaksinya diubah: hook saving pada
     * SalesTransaction bisa menaikkan dp_nominal, dan angka yang sudah
     * dinaikkan itu bukan uang yang pernah diterima.
     */
    public function pastikanRiwayatAwal(SalesTransaction $trx): void
    {
        if ((float) $trx->dp_nominal <= 0 || $trx->pembayaran()->exists()) {
            return;
        }

        $dibayar = (float) $trx->dp_nominal;
        $total = (float) $trx->total;

        $catatan = 'Dipindahkan dari catatan transaksi lama.';
        if ($dibayar > $total) {
            $catatan .= ' Tercatat dibayar ' . rupiah($dibayar) . '; selisih ' . rupiah($dibayar - $total) . ' dianggap kembalian.';
        }

        $this->catat(
            $trx,
            $trx->tanggal->toDateString(),
            min($dibayar, $total),
            $this->akunBawaan($trx->metode_pembayaran)?->id,
            $trx->metode_pembayaran,
            $catatan,
        );
    }

    /**
     * Samakan uang yang diterima dengan isian form transaksi.
     *
     * Form Tambah/Edit Transaksi tetap memakai isian "Nominal Dibayar" yang
     * sudah dikenal petugas. Yang berubah hanya di belakangnya:
     *
     *   - target lebih BESAR dari yang tercatat -> dicatat satu pembayaran
     *     baru sebesar selisihnya, bertanggal $tanggalTambahan.
     *   - target lebih KECIL -> pembayaran terakhir dibatalkan (beserta
     *     alasan) sampai cukup, dan kelebihan potongannya dicatat ulang
     *     sebagai pembayaran pengganti dengan tanggal & akun yang sama.
     *     Tanggal uang masuk yang asli tidak ikut bergeser karena koreksi.
     *
     * Target dibatasi setinggi total transaksi: uang di atas total adalah
     * kembalian, bukan uang yang tinggal di koperasi.
     */
    public function sesuaikan(
        SalesTransaction $trx,
        float $target,
        ?int $akunId,
        ?string $metode,
        string $tanggalTambahan,
        string $sebab,
    ): void {
        $target = round(max(0, min($target, (float) $trx->total)), 2);
        $berlaku = $trx->pembayaran()->berlaku()->get();
        $tercatat = round((float) $berlaku->sum('nominal'), 2);

        if ($target > $tercatat) {
            $this->catat($trx, $tanggalTambahan, $target - $tercatat, $akunId, $metode, $sebab);
        } elseif ($target < $tercatat) {
            $sisa = $tercatat;
            $terakhir = null;

            foreach ($berlaku->reverse() as $pembayaran) {
                if ($sisa <= $target) {
                    break;
                }

                $this->batalkanBaris(
                    $pembayaran,
                    'Dikoreksi lewat edit transaksi: nominal dibayar diubah menjadi ' . rupiah($target) . '.',
                );
                $sisa = round($sisa - (float) $pembayaran->nominal, 2);
                $terakhir = $pembayaran;
            }

            if ($terakhir && $sisa < $target) {
                $this->catat(
                    $trx,
                    $terakhir->tanggal->toDateString(),
                    $target - $sisa,
                    $terakhir->akun_kas_id,
                    $terakhir->metode,
                    'Pengganti pembayaran #' . $terakhir->id . ' setelah koreksi transaksi.',
                );
            }
        }

        $this->hitungUlang($trx);
    }

    /** Terima pembayaran — DP susulan, cicilan, atau pelunasan. */
    public function terima(
        SalesTransaction $trx,
        string $tanggal,
        float $nominal,
        ?int $akunId,
        ?string $metode,
        ?string $catatan,
    ): PembayaranPenjualan {
        $this->pastikanRiwayatAwal($trx);

        $pembayaran = $this->catat($trx, $tanggal, $nominal, $akunId, $metode, $catatan);
        $this->hitungUlang($trx);

        return $pembayaran;
    }

    public function batalkan(PembayaranPenjualan $pembayaran, string $alasan): void
    {
        $this->batalkanBaris($pembayaran, $alasan);
        $this->hitungUlang($pembayaran->transaksi);
    }

    /**
     * Turunkan dp_nominal & status_pembayaran dari pembayaran yang berlaku.
     *
     * Transaksi bernilai Rp0 dibiarkan dengan status yang dipilih petugas —
     * tidak ada angka yang bisa dipakai untuk menurunkannya.
     */
    public function hitungUlang(SalesTransaction $trx): void
    {
        $dibayar = round((float) $trx->pembayaran()->berlaku()->sum('nominal'), 2);
        $total = (float) $trx->total;

        $trx->dp_nominal = $dibayar;

        if ($total > 0) {
            $trx->status_pembayaran = match (true) {
                $dibayar <= 0 => 'Belum Dibayar',
                $dibayar >= $total => 'Lunas',
                default => 'DP',
            };
        }

        $trx->save();
    }

    private function catat(
        SalesTransaction $trx,
        string $tanggal,
        float $nominal,
        ?int $akunId,
        ?string $metode,
        ?string $catatan,
    ): PembayaranPenjualan {
        $pembayaran = new PembayaranPenjualan([
            'tanggal' => $tanggal,
            'nominal' => round($nominal, 2),
            'akun_kas_id' => $akunId,
            'metode' => $metode,
            'catatan' => $catatan,
        ]);
        $pembayaran->sales_transaction_id = $trx->id;
        $pembayaran->created_by = Auth::id();
        $pembayaran->save();

        $akun = $akunId ? AkunKas::find($akunId)?->nama : null;

        ActivityLog::record(
            action: 'sales_payment_received',
            modelType: 'PembayaranPenjualan',
            modelId: $pembayaran->id,
            description: 'Pembayaran ' . rupiah($pembayaran->nominal) . ' diterima untuk ' . $trx->nomor_transaksi
                . ' tanggal ' . $pembayaran->tanggal->format('d-m-Y')
                . ' ke ' . ($akun ?? 'akun belum ditentukan') . '.'
                . ($catatan ? ' Catatan: ' . $catatan : ''),
        );

        return $pembayaran;
    }

    private function batalkanBaris(PembayaranPenjualan $pembayaran, string $alasan): void
    {
        $pembayaran->forceFill([
            'status' => PembayaranPenjualan::STATUS_DIBATALKAN,
            'alasan_pembatalan' => $alasan,
            'dibatalkan_at' => now(),
            'dibatalkan_oleh' => Auth::id(),
        ])->save();

        ActivityLog::record(
            action: 'sales_payment_cancelled',
            modelType: 'PembayaranPenjualan',
            modelId: $pembayaran->id,
            description: 'Pembayaran ' . rupiah($pembayaran->nominal) . ' tanggal ' . $pembayaran->tanggal->format('d-m-Y')
                . ' untuk ' . $pembayaran->transaksi->nomor_transaksi . ' dibatalkan. Alasan: ' . $alasan,
        );
    }
}
