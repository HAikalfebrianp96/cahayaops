<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Soft Delete untuk RIWAYAT PELATIHAN.
 *
 * Sebelum ini menghapus data pelatihan berarti hilang permanen, padahal
 * pelatihan adalah bagian dari rekam jejak peserta yang dipakai laporan dan
 * timeline. Salah ketik nama pelatihan atau salah pilih peserta harus bisa
 * dibereskan tanpa jejaknya lenyap.
 *
 * 24 baris yang sudah ada bernilai NULL (tidak terhapus) dan tetap tampil
 * seperti biasa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('training_records', function (Blueprint $table) {
            if (! Schema::hasColumn('training_records', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    public function down(): void
    {
        Schema::table('training_records', function (Blueprint $table) {
            if (Schema::hasColumn('training_records', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }
};
