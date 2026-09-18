<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_units', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->string('kode')->unique();
            $table->string('jenis_usaha')->nullable(); // deskripsi bebas, mis. "Retail Sembako"
            $table->string('tipe'); // Barang | Jasa | Campuran
            $table->text('deskripsi')->nullable();
            $table->text('alamat')->nullable();
            $table->string('nomor_kontak')->nullable();
            $table->string('status')->default('Aktif'); // Aktif | Tidak Aktif
            $table->date('tanggal_mulai')->nullable();
            $table->text('catatan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_units');
    }
};
