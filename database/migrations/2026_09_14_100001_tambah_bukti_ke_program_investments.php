<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BUKTI INVESTASI PROGRAM.
 *
 * Investasi adalah PENYEBUT rasio SROI — satu-satunya angka yang, kalau
 * dicatat terlalu kecil, membuat hasilnya terlihat lebih baik. Justru pos
 * inilah yang paling perlu bisa ditunjukkan bukti fisiknya saat laporan
 * dampak diperiksa pemberi dana: kuitansi pelatihan, nota pembelian alat,
 * bukti transfer bantuan modal.
 *
 * Outcome dan proxy TIDAK ikut diberi kolom ini, dan itu disengaja. Keduanya
 * mengutip sumber — studi, statistik, hasil monitoring — bukan melampirkan
 * kuitansi; kolom teks `bukti` dan `sumber` yang sudah ada memang bentuk yang
 * tepat untuk itu. Menambahkan unggahan berkas ke sana hanya akan
 * menambah kolom yang tidak pernah terisi.
 *
 * Dua kolom, dengan alasan yang sama persis seperti bukti laporan omzet:
 * nama file di disk TIDAK PERNAH berasal dari pengguna (bisa memuat "../"
 * atau ekstensi ganda), sedangkan nama aslinya disimpan sebagai teks untuk
 * ditampilkan. Berkasnya sendiri berada di disk privat dan hanya bisa dibuka
 * lewat route ber-otorisasi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('program_investments', function (Blueprint $table) {
            $table->string('bukti_path')->nullable()->after('catatan');
            $table->string('bukti_asli')->nullable()->after('bukti_path');
        });
    }

    public function down(): void
    {
        Schema::table('program_investments', function (Blueprint $table) {
            $table->dropColumn(['bukti_path', 'bukti_asli']);
        });
    }
};
