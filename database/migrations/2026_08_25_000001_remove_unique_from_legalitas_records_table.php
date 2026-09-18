<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL tidak izinkan hapus index yang masih dipakai foreign key
        // (participant_id) — jadi kita buat dulu index pengganti untuk
        // participant_id saja, baru boleh hapus unique constraint lamanya.
        Schema::table('legalitas_records', function (Blueprint $table) {
            $table->index('participant_id', 'legalitas_records_participant_id_index');
        });

        Schema::table('legalitas_records', function (Blueprint $table) {
            $table->dropUnique('legalitas_records_participant_id_jenis_dokumen_unique');
        });
    }

    public function down(): void
    {
        Schema::table('legalitas_records', function (Blueprint $table) {
            $table->unique(['participant_id', 'jenis_dokumen'], 'legalitas_records_participant_id_jenis_dokumen_unique');
        });

        Schema::table('legalitas_records', function (Blueprint $table) {
            $table->dropIndex('legalitas_records_participant_id_index');
        });
    }
};
