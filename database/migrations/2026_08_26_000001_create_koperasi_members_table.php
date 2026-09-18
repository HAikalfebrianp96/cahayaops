<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('koperasi_members', function (Blueprint $table) {
            $table->id();
            $table->string('nomor_anggota')->unique();
            $table->string('nama');
            $table->string('nomor_hp');
            $table->text('alamat')->nullable();
            $table->date('tanggal_bergabung');
            $table->string('status')->default('Aktif'); // Aktif | Tidak Aktif
            $table->text('keterangan')->nullable();

            // Hubungan OPSIONAL ke peserta Cahaya Preneur — TIDAK mengubah
            // tabel participants sama sekali, cuma menambah referensi.
            $table->foreignId('participant_id')
                ->nullable()
                ->constrained('participants')
                ->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('koperasi_members');
    }
};
