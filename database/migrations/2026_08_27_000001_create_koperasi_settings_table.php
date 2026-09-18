<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('koperasi_settings', function (Blueprint $table) {
            $table->id();
            $table->string('nama')->default('Koperasi Cahaya');
            $table->string('subtitle')->default('Koperasi Usaha Bersama');
            $table->string('tagline')->default('Dari Kita • Untuk Kita • Oleh Kita');

            $table->string('logo_koperasi')->nullable();
            $table->string('logo_cahayaos')->nullable();
            $table->string('logo_ybm_pln')->nullable();

            $table->string('ttd_koperasi')->nullable();
            $table->string('nama_penandatangan')->nullable();
            $table->string('jabatan_penandatangan')->nullable();
            $table->string('stempel_koperasi')->nullable();

            $table->string('prefix_invoice')->default('INV');
            $table->text('footer_invoice')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('koperasi_settings');
    }
};
