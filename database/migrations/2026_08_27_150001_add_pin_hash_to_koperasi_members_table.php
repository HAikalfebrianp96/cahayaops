<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('koperasi_members', function (Blueprint $table) {
            // PIN untuk akses "Cek Simpanan Saya" (publik, TERPISAH dari sistem
            // users/Auth Admin-Pendamping-Pimpinan). Disimpan HASHED, tidak
            // pernah plaintext — sama seperti password di User model.
            $table->string('pin_hash')->nullable()->after('nomor_anggota');
        });
    }

    public function down(): void
    {
        Schema::table('koperasi_members', function (Blueprint $table) {
            $table->dropColumn('pin_hash');
        });
    }
};
