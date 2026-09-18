<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_savings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('koperasi_member_id')
                ->constrained('koperasi_members')
                ->restrictOnDelete(); // anggota TIDAK BOLEH terhapus kalau masih punya riwayat simpanan

            $table->string('jenis_simpanan'); // Simpanan Pokok | Simpanan Wajib | Simpanan Sukarela
            $table->date('tanggal');
            $table->decimal('nominal', 15, 2);
            $table->text('keterangan')->nullable();
            $table->string('status')->default('Tercatat');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_savings');
    }
};
