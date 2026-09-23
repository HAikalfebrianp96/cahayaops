<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * KAS & BANK — TAHAP 1: DAFTAR TEMPAT UANG DISIMPAN.
 *
 * Satu baris = satu tempat uang koperasi berada: laci kas, atau satu
 * rekening bank. Saldonya TIDAK disimpan di kolom mana pun, sama seperti
 * Dana Sosial & Hibah — kelak dijumlahkan dari saldo awal ditambah catatan
 * uang yang menunjuk ke akun ini. Yang disimpan di sini hanya TITIK AWAL.
 *
 * tanggal_saldo_awal NULL berarti saldo awal BELUM DITETAPKAN — berbeda
 * dengan saldo awal yang memang Rp0. Laporan kas perlu membedakan keduanya:
 * yang pertama adalah angka yang belum bisa dipercaya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('akun_kas', function (Blueprint $table) {
            $table->id();
            $table->string('nama')->unique();         // "Kas Tunai", "Bank BRI Koperasi"
            $table->string('jenis');                  // Kas | Bank
            $table->string('nomor_rekening')->nullable();
            $table->string('atas_nama')->nullable();
            $table->decimal('saldo_awal', 15, 2)->default(0);
            $table->date('tanggal_saldo_awal')->nullable();
            $table->text('keterangan')->nullable();
            $table->string('status')->default('Aktif'); // Aktif | Tidak Aktif
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Dua akun bawaan supaya modul langsung bisa dipakai. Saldo awalnya
        // SENGAJA belum ditetapkan (tanggal NULL) — angkanya harus datang dari
        // hitungan uang sungguhan oleh pengurus, bukan dari tebakan sistem.
        // insertOrIgnore: aman dijalankan ulang karena kolom 'nama' UNIQUE.
        DB::table('akun_kas')->insertOrIgnore([
            [
                'nama' => 'Kas Tunai',
                'jenis' => 'Kas',
                'saldo_awal' => 0,
                'status' => 'Aktif',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nama' => 'Rekening Bank',
                'jenis' => 'Bank',
                'saldo_awal' => 0,
                'status' => 'Aktif',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('akun_kas');
    }
};
