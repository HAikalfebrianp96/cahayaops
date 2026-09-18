<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DATA USAHA PESERTA — alamat tempat usaha.
 *
 * Berbeda dari `alamat` yang sudah ada: itu alamat TINGGAL pelaku UMKM,
 * sedangkan lapak/dapur/bengkelnya sering berada di tempat lain. Pendamping
 * butuh keduanya untuk kunjungan lapangan.
 *
 * Nullable dan ditaruh berdampingan dengan kolom usaha lainnya. Tidak ada
 * kolom lama yang diubah, dan 21 peserta existing tetap valid tanpa perlu
 * diisi ulang.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('participants', function (Blueprint $table) {
            if (! Schema::hasColumn('participants', 'alamat_usaha')) {
                $table->text('alamat_usaha')->nullable()->after('nama_usaha');
            }
        });
    }

    public function down(): void
    {
        Schema::table('participants', function (Blueprint $table) {
            if (Schema::hasColumn('participants', 'alamat_usaha')) {
                $table->dropColumn('alamat_usaha');
            }
        });
    }
};
