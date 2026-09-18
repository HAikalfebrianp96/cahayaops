<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            // Disimpan PUBLIK (public/images/preneur/) supaya bisa tampil langsung
            // di header, beda dari kolom "logo" lama yang privat & wajib download.
            $table->string('logo_program')->nullable()->after('nama_program');
            $table->string('logo_ybm_pln')->nullable()->after('logo_program');
        });
    }

    public function down(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            $table->dropColumn(['logo_program', 'logo_ybm_pln']);
        });
    }
};
