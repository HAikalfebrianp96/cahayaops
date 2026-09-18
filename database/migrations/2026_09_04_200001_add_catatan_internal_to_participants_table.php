<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CATATAN INTERNAL PESERTA.
 *
 * Catatan kerja yang HANYA boleh dilihat Admin dan Pendamping yang
 * bertanggung jawab atas peserta itu — bukan data profil publik. Isinya
 * hal-hal yang membantu pendampingan tapi tidak pantas tampil di mana-mana
 * (mis. "sulit dihubungi pagi hari", "usaha sempat vakum karena sakit").
 *
 * Kolomnya SENGAJA satu kolom teks, bukan tabel baru: kebutuhannya adalah
 * catatan bebas, dan menambah tabel berarti menambah relasi, halaman, dan
 * otorisasi baru untuk sesuatu yang tidak memerlukannya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('participants', function (Blueprint $table) {
            if (! Schema::hasColumn('participants', 'catatan_internal')) {
                $table->text('catatan_internal')->nullable()->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('participants', function (Blueprint $table) {
            if (Schema::hasColumn('participants', 'catatan_internal')) {
                $table->dropColumn('catatan_internal');
            }
        });
    }
};
