<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();

            // Profil Sistem
            $table->string('nama_sistem')->default('CahayaOS');
            $table->string('nama_lembaga')->default('YBM PLN UP3 Bengkulu');
            $table->string('logo_path')->nullable();
            $table->string('logo_asli')->nullable();
            $table->text('alamat')->nullable();
            $table->string('kontak')->nullable();

            // Program
            $table->string('nama_program')->default('Cahaya Preneur');
            $table->string('periode_program')->nullable();
            $table->string('tahun_program')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};
