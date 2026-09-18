<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Soft Delete untuk PESERTA / BINAAN.
 *
 * Sebelum ini, menghapus peserta = hilang permanen, padahal peserta adalah
 * SUMBER IDENTITAS UTAMA yang dipakai modul lain (omzet, pendampingan,
 * pelatihan, legalitas, HPP, dan keanggotaan Koperasi Cahaya). Menghapusnya
 * secara permanen berarti memutus semua relasi itu sekaligus.
 *
 * Kolom `deleted_at` ditambahkan TANPA menyentuh satu pun kolom lama dan
 * TANPA memindahkan data. Baris yang sudah ada bernilai NULL — artinya
 * "tidak terhapus" — sehingga seluruh data existing tetap terlihat persis
 * seperti sebelumnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('participants', function (Blueprint $table) {
            if (! Schema::hasColumn('participants', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    public function down(): void
    {
        Schema::table('participants', function (Blueprint $table) {
            if (Schema::hasColumn('participants', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }
};
