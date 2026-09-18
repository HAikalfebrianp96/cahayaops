<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legalitas_records', function (Blueprint $table) {
            $table->id();

            $table->foreignId('participant_id')
                ->constrained('participants')
                ->cascadeOnDelete();

            $table->string('jenis_dokumen'); // NIB, PIRT, Sertifikat Halal, NPWP, Merek, Sertifikat Pelatihan, Lainnya
            $table->string('nomor_dokumen')->nullable();
            $table->string('status')->default('Belum Ada'); // Belum Ada | Proses | Sudah Ada
            $table->date('tanggal_terbit')->nullable();
            $table->date('tanggal_berlaku')->nullable();

            $table->string('file_path')->nullable();
            $table->string('file_asli')->nullable(); // nama file asli, dipakai saat download

            $table->text('catatan')->nullable();
            $table->timestamps();

            // Satu jenis dokumen hanya punya satu catatan status per peserta (diperbarui, bukan ditumpuk)
            $table->unique(['participant_id', 'jenis_dokumen']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legalitas_records');
    }
};
