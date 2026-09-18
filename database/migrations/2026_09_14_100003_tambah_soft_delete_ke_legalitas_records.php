<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ARSIP UNTUK DOKUMEN LEGALITAS PESERTA.
 *
 * ==========================================================================
 * KENAPA PERLU
 * ==========================================================================
 *
 * Legalitas adalah satu-satunya modul catatan peserta yang masih menghapus
 * datanya sungguhan — dan bukan cuma barisnya: berkas unggahannya ikut
 * dihapus dari penyimpanan pada saat yang sama.
 *
 * Yang hilang di situ bukan catatan biasa. NIB, sertifikat halal, PIRT, dan
 * izin usaha adalah dokumen yang pengurusannya memakan waktu berbulan-bulan
 * dan biaya nyata bagi peserta. Salah klik satu kali pada daftar peserta
 * menghapusnya tanpa jalan kembali, dan seringkali salinan di aplikasi ini
 * adalah satu-satunya salinan digital yang dimiliki peserta.
 *
 * Setelah migration ini, penghapusan menjadi pengarsipan: barisnya tinggal,
 * berkasnya tidak disentuh, dan Admin bisa memulihkannya dari Arsip Data.
 * Penghapusan yang sungguh-sungguh permanen tetap tersedia — tapi sebagai
 * keputusan kedua yang terpisah, oleh Admin, dengan konfirmasi diketik ulang.
 *
 * SIFAT MIGRATION INI:
 * - Menambah dua kolom nullable. Tidak ada baris yang disentuh.
 * - 84 dokumen yang sudah ada tetap aktif (deleted_at null).
 * - down() hanya membuang kolom yang ditambahkan di sini.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('legalitas_records', function (Blueprint $table) {
            $table->softDeletes();
            $table->foreignId('deleted_by')->nullable()->after('deleted_at')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('legalitas_records', function (Blueprint $table) {
            $table->dropConstrainedForeignId('deleted_by');
            $table->dropSoftDeletes();
        });
    }
};
