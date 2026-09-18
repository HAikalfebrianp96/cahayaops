<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * OMZET SAJA TIDAK CUKUP.
 *
 * Sampai sekarang laporan omzet hanya menyimpan satu angka: uang yang masuk.
 * Untuk peserta pemberdayaan itu justru angka yang paling mudah disalahbaca —
 * omzet naik sambil rugi adalah kejadian biasa di UMKM yang baru belajar
 * menghitung biaya. Dua kolom ini melengkapi laporan supaya sistem bisa
 * menyatakan LABA, bukan cuma penjualan:
 *
 *   omzet - hpp                  = laba kotor
 *   laba kotor - biaya operasional = profit bersih
 *
 * SENGAJA NULLABLE, bukan default 0.
 *
 * 271 laporan yang sudah ada dibuat sebelum kolom ini ada; peserta memang
 * tidak pernah ditanya HPP-nya. Mengisinya 0 berarti sistem akan mengklaim
 * "HPP-nya nol, labanya = seluruh omzet" — angka karangan yang langsung
 * masuk ke laporan dan perhitungan dampak. NULL berkata jujur: belum
 * dilaporkan. Perhitungan laba pun hanya ditampilkan saat datanya ada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('omzet_reports', function (Blueprint $table) {
            $table->unsignedBigInteger('hpp')->nullable()->after('omzet');
            $table->unsignedBigInteger('biaya_operasional')->nullable()->after('hpp');
        });
    }

    public function down(): void
    {
        Schema::table('omzet_reports', function (Blueprint $table) {
            $table->dropColumn(['hpp', 'biaya_operasional']);
        });
    }
};
