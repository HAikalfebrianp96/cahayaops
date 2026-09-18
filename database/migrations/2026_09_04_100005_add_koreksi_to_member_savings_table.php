<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MEKANISME KOREKSI SIMPANAN — koreksi yang MENINGGALKAN JEJAK.
 *
 * Simpanan adalah uang anggota. Membiarkannya diedit bebas tanpa jejak
 * berbahaya; melarang koreksi sama sekali juga tidak realistis, karena salah
 * ketik nominal di lapangan pasti terjadi. Jalan tengahnya dua tingkat:
 *
 *   1. Selama entri masih "Tercatat" -> boleh DIPERBAIKI di tempat.
 *      Setiap perubahan dicatat Activity Log lengkap dengan nilai
 *      sebelum -> sesudah, jadi tetap bisa ditelusuri.
 *
 *   2. Entri yang sudah DIKUNCI (`dikunci_at` terisi) -> TIDAK bisa diedit
 *      lagi. Koreksinya lewat PEMBATALAN: barisnya diberi status
 *      "Dibatalkan" (tetap ada, tidak dihapus) dan boleh diikuti satu entri
 *      PENGGANTI yang menunjuk balik lewat `dikoreksi_dari_id`.
 *
 * Jadi tidak ada baris keuangan yang pernah lenyap — yang berubah hanya
 * apakah ia masih dihitung ke saldo atau tidak.
 *
 * Kolom `status` SUDAH ADA sejak migration 2026_08_26_000002 dengan default
 * 'Tercatat' dan TIDAK diubah di sini; nilai 'Dibatalkan' hanyalah nilai
 * tambahan yang dipakai alur di atas. Seluruh 4 baris existing tetap
 * 'Tercatat' dan tetap dihitung ke saldo persis seperti sebelumnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('member_savings', function (Blueprint $table) {
            // Entri pengganti menunjuk ke entri yang dibatalkannya.
            // nullOnDelete: kalau baris asal benar-benar lenyap, penggantinya
            // TIDAK ikut terhapus — jejaknya lebih berharga daripada tautannya.
            if (! Schema::hasColumn('member_savings', 'dikoreksi_dari_id')) {
                $table->foreignId('dikoreksi_dari_id')
                    ->nullable()
                    ->after('status')
                    ->constrained('member_savings')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('member_savings', 'alasan_koreksi')) {
                $table->text('alasan_koreksi')->nullable()->after('dikoreksi_dari_id');
            }

            if (! Schema::hasColumn('member_savings', 'dibatalkan_at')) {
                $table->timestamp('dibatalkan_at')->nullable()->after('alasan_koreksi');
            }

            // Siapa yang membatalkan. nullOnDelete menyamai pola FK ke users
            // di tabel lain: menghapus akun tidak boleh menghapus transaksinya.
            if (! Schema::hasColumn('member_savings', 'dibatalkan_oleh')) {
                $table->foreignId('dibatalkan_oleh')
                    ->nullable()
                    ->after('dibatalkan_at')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            // Penanda entri sudah final. NULL = masih boleh diperbaiki di tempat.
            if (! Schema::hasColumn('member_savings', 'dikunci_at')) {
                $table->timestamp('dikunci_at')->nullable()->after('dibatalkan_oleh');
            }
        });
    }

    public function down(): void
    {
        Schema::table('member_savings', function (Blueprint $table) {
            if (Schema::hasColumn('member_savings', 'dikoreksi_dari_id')) {
                $table->dropForeign(['dikoreksi_dari_id']);
                $table->dropColumn('dikoreksi_dari_id');
            }
            if (Schema::hasColumn('member_savings', 'dibatalkan_oleh')) {
                $table->dropForeign(['dibatalkan_oleh']);
                $table->dropColumn('dibatalkan_oleh');
            }
            foreach (['alasan_koreksi', 'dibatalkan_at', 'dikunci_at'] as $kolom) {
                if (Schema::hasColumn('member_savings', $kolom)) {
                    $table->dropColumn($kolom);
                }
            }
        });
    }
};
