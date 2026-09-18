<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SIKLUS TINDAK LANJUT PENDAMPINGAN.
 *
 * Alur kerja pendampingan yang lengkap punya empat tahap:
 *
 *   Permasalahan -> Tindakan -> Hasil -> Rencana Tindak Lanjut (+ tanggalnya)
 *
 * DUA di antaranya SUDAH ADA di tabel ini sejak migration 2026_08_24_000002
 * dan TIDAK dibuat ulang:
 *   - `kendala`       = Permasalahan
 *   - `tindak_lanjut` = Rencana Tindak Lanjut
 *
 * Jadi yang ditambahkan hanya tiga kolom yang memang belum punya rumah.
 * Membuat kolom `permasalahan` baru di samping `kendala` yang sudah terisi 45
 * baris justru akan memecah data yang sama ke dua tempat — persis duplikasi
 * yang dihindari CahayaOS.
 *
 * `tanggal_follow_up` yang membuat siklusnya berguna: dari kolom itulah
 * Dashboard Pendamping tahu binaan mana yang jatuh tempo dikunjungi lagi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pendampingan_records', function (Blueprint $table) {
            if (! Schema::hasColumn('pendampingan_records', 'tindakan')) {
                $table->text('tindakan')->nullable()->after('kendala');
            }
            if (! Schema::hasColumn('pendampingan_records', 'hasil')) {
                $table->text('hasil')->nullable()->after('tindakan');
            }
            if (! Schema::hasColumn('pendampingan_records', 'tanggal_follow_up')) {
                $table->date('tanggal_follow_up')->nullable()->after('tindak_lanjut');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pendampingan_records', function (Blueprint $table) {
            foreach (['tindakan', 'hasil', 'tanggal_follow_up'] as $kolom) {
                if (Schema::hasColumn('pendampingan_records', $kolom)) {
                    $table->dropColumn($kolom);
                }
            }
        });
    }
};
