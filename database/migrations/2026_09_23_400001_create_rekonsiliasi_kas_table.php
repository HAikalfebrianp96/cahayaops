<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * KAS & BANK — TAHAP 5: CATATAN HITUNG FISIK (REKONSILIASI).
 *
 * Satu baris = satu kali pengurus mencocokkan saldo menurut sistem dengan
 * uang yang sungguh ada (isi laci dihitung, atau saldo di mutasi rekening).
 *
 * saldo_sistem dibekukan pada saat pencatatan. Kalau catatan uang di
 * tanggal itu kemudian dikoreksi, baris ini tetap menunjukkan angka yang
 * dilihat pengurus waktu itu — itulah gunanya sebagai jejak pemeriksaan.
 *
 * Baris ini TIDAK mengubah saldo. Selisih yang ditemukan dibereskan dengan
 * mencari catatan yang terlewat/salah, atau (kelak) lewat mutasi Penyesuaian.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rekonsiliasi_kas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('akun_kas_id')->constrained('akun_kas')->cascadeOnDelete();
            $table->date('tanggal');
            $table->decimal('saldo_sistem', 15, 2);
            $table->decimal('saldo_fisik', 15, 2);
            $table->decimal('selisih', 15, 2); // fisik - sistem; negatif = uang kurang
            $table->text('catatan')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['akun_kas_id', 'tanggal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rekonsiliasi_kas');
    }
};
