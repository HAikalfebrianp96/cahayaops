<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('participants', function (Blueprint $table) {
            $table->id();
            $table->string('nama_peserta');
            $table->string('nomor_hp');
            $table->text('alamat')->nullable();
            $table->string('nama_usaha');
            $table->string('kategori_usaha'); // Makanan & Minuman, Kerajinan, Jasa, Pertanian, Peternakan, Perdagangan, Lainnya
            $table->date('tanggal_mulai_program');
            $table->string('status')->default('Aktif'); // Aktif | Tidak Aktif | Graduasi

            $table->foreignId('pendamping_id')
                ->nullable()
                ->constrained('pendampings')
                ->nullOnDelete(); // kalau pendamping dihapus, peserta TIDAK ikut terhapus — cuma pendamping_id jadi kosong

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('participants');
    }
};
