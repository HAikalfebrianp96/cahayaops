<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * JEJAK PEMBATALAN UNTUK PENGELUARAN.
 *
 * ==========================================================================
 * KENAPA PERLU
 * ==========================================================================
 *
 * Penjualan dan Simpanan sama-sama menyimpan ALASAN, WAKTU, dan PELAKU saat
 * dibatalkan. Pengeluaran tidak: pembatalannya hanya mengubah satu kolom
 * `status` menjadi 'Dibatalkan', tanpa menyisakan keterangan apa pun.
 *
 * Akibatnya, sebuah pengeluaran senilai jutaan rupiah bisa lenyap dari laba
 * rugi tanpa ada satu pun catatan tentang siapa yang membatalkannya, kapan,
 * atau kenapa. Saat rekonsiliasi akhir tahun menemukan selisihnya, tidak ada
 * yang bisa ditelusuri.
 *
 * SIFAT MIGRATION INI:
 * - Murni menambah tiga kolom, semuanya nullable.
 * - Tidak menyentuh, memindahkan, atau menghapus satu baris data pun.
 * - Baris yang sudah ada tetap sah: kolomnya berisi null, yang JUJUR
 *   menyatakan "pembatalannya terjadi sebelum jejak ini dicatat" — bukan
 *   nilai karangan yang seolah-olah diketahui.
 * - down() hanya membuang kolom yang ditambahkan di sini.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pengeluaran', function (Blueprint $table) {
            $table->text('alasan_pembatalan')->nullable()->after('status');
            $table->timestamp('dibatalkan_at')->nullable()->after('alasan_pembatalan');
            $table->foreignId('dibatalkan_oleh')->nullable()->after('dibatalkan_at')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pengeluaran', function (Blueprint $table) {
            $table->dropConstrainedForeignId('dibatalkan_oleh');
            $table->dropColumn(['alasan_pembatalan', 'dibatalkan_at']);
        });
    }
};
