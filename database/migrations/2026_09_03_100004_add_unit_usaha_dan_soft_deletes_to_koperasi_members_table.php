<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * KOPERASI CAHAYA — data KHUSUS keanggotaan.
 *
 * Tabel `koperasi_members` SENGAJA tidak menyimpan ulang identitas orang.
 * Kalau anggota berasal dari peserta Cahaya Preneur, identitasnya diambil
 * dari `participants` lewat FK `participant_id` yang SUDAH ADA sejak
 * migration 2026_08_26_000001 — tidak ada baris identitas kedua.
 *
 * Yang ditambahkan di sini hanya dua hal yang memang milik koperasi:
 *
 *   1. `unit_usaha_id` — unit usaha koperasi tempat anggota terdaftar,
 *      merujuk `business_units` yang sudah ada (nullOnDelete supaya
 *      menghapus unit usaha TIDAK ikut menghapus anggotanya).
 *   2. `deleted_at`   — Soft Delete, sehingga anggota yang dihapus keliru
 *      bisa dipulihkan tanpa kehilangan riwayat simpanannya.
 *
 * Simpanan Pokok & Simpanan Wajib TIDAK dibuatkan kolom baru: keduanya sudah
 * punya rumah sendiri di tabel `member_savings` (jenis_simpanan). Membuat
 * kolom nominal di sini akan jadi sumber angka kedua yang bisa berbeda dari
 * riwayat simpanan — persis duplikasi yang ingin dihindari.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('koperasi_members', function (Blueprint $table) {
            if (! Schema::hasColumn('koperasi_members', 'unit_usaha_id')) {
                $table->foreignId('unit_usaha_id')
                    ->nullable()
                    ->after('participant_id')
                    ->constrained('business_units')
                    ->nullOnDelete();
            }
            if (! Schema::hasColumn('koperasi_members', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    public function down(): void
    {
        Schema::table('koperasi_members', function (Blueprint $table) {
            if (Schema::hasColumn('koperasi_members', 'unit_usaha_id')) {
                $table->dropForeign(['unit_usaha_id']);
                $table->dropColumn('unit_usaha_id');
            }
            if (Schema::hasColumn('koperasi_members', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }
};
