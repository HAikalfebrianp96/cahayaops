<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Soft Delete untuk RIWAYAT PENDAMPINGAN.
 *
 * Riwayat pendampingan adalah catatan lapangan: salah ketik tanggal atau
 * salah pilih jenis kegiatan itu wajar terjadi. Pendamping harus bisa
 * memperbaiki — termasuk menghapus catatan yang salah — tanpa jejaknya
 * lenyap dari database.
 *
 * 17 catatan yang sudah ada bernilai NULL (tidak terhapus) dan tetap tampil
 * seperti biasa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pendampingan_records', function (Blueprint $table) {
            if (! Schema::hasColumn('pendampingan_records', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    public function down(): void
    {
        Schema::table('pendampingan_records', function (Blueprint $table) {
            if (Schema::hasColumn('pendampingan_records', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }
};
