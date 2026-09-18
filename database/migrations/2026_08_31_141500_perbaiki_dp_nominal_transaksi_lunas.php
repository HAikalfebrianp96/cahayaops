<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Samakan dp_nominal dengan total untuk transaksi berstatus "Lunas".
 *
 * MASALAH
 * Ada transaksi berstatus "Lunas" tapi dp_nominal-nya 0. Karena
 * SalesTransaction::sisaPembayaran() = max(0, total - nominalDibayar()),
 * invoice jadi menampilkan dua hal yang bertentangan sekaligus:
 * badge "LUNAS" berdampingan dengan "Sisa Pembayaran Rp300.000".
 * Ini terlihat di halaman Invoice MAUPUN PDF-nya — dokumen yang
 * diserahkan ke pelanggan.
 *
 * BUKAN BUG KODE
 * SalesTransactionController::hitungDibayar() sudah benar: status "Lunas"
 * tanpa isian nominal otomatis dianggap dibayar penuh sebesar total. Jadi
 * transaksi baru yang disimpan lewat aplikasi tidak akan bermasalah.
 * Yang perlu dirapikan hanya baris lama yang telanjur tersimpan dengan
 * dp_nominal = 0 — dikonfirmasi pemilik data memang sudah lunas.
 *
 * CAKUPAN
 * HANYA baris yang status_pembayaran = 'Lunas' DAN dp_nominal < total.
 * Baris "DP" dan "Belum Dibayar" TIDAK disentuh sama sekali — nominal
 * bayarnya memang seharusnya lebih kecil dari total.
 *
 * Aman dijalankan berulang: setelah dp_nominal = total, syarat
 * "dp_nominal < total" tidak lagi terpenuhi sehingga tidak ada yang diubah.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('sales_transactions')
            ->where('status_pembayaran', 'Lunas')
            ->whereColumn('dp_nominal', '<', 'total')
            ->update(['dp_nominal' => DB::raw('`total`')]);
    }

    /**
     * SENGAJA tidak melakukan apa-apa.
     *
     * Migration ini KOREKSI DATA, bukan perubahan struktur. Nilai lama
     * (dp_nominal = 0) tidak disimpan di mana pun, dan mengembalikan
     * "semua Lunas jadi 0" akan ikut merusak baris yang nominalnya memang
     * sudah benar — termasuk transaksi baru yang dibuat setelah migration
     * ini jalan. Jadi rollback otomatis justru berbahaya di sini.
     *
     * Kalau perlu mundur, pulihkan dari backup yang dibuat sebelum
     * migration ini dijalankan: storage/app/backups/backup-2026-08-31_141345.sql
     */
    public function down(): void
    {
        // Tidak ada aksi — lihat penjelasan di atas.
    }
};
