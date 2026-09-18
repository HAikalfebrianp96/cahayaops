<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MASTER PESERTA / ORANG / UMKM — kelengkapan data identitas.
 *
 * Peserta adalah Single Source of Truth: satu orang = satu baris di
 * `participants`, dipakai bersama oleh Cahaya Preneur, Koperasi Cahaya, dan
 * program lain di masa depan. Karena Koperasi ikut memakai data dasar yang
 * sama (nama, HP, email, alamat, nama usaha, jenis usaha), field-field itu
 * harus tersedia di SATU tempat — di sini — bukan diketik ulang di modul lain.
 *
 * SEMUA kolom baru NULLABLE. Tidak ada kolom lama yang diubah, tidak ada data
 * lama yang tersentuh, dan 21 peserta yang sudah ada tetap valid tanpa perlu
 * diisi ulang. Kolom `kategori_usaha` yang lama SENGAJA dipertahankan sebagai
 * "Jenis Usaha" karena masih dipakai laporan, filter, dan dashboard.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('participants', function (Blueprint $table) {
            if (! Schema::hasColumn('participants', 'nomor_identitas')) {
                $table->string('nomor_identitas')->nullable()->after('nama_peserta');
            }
            if (! Schema::hasColumn('participants', 'email')) {
                $table->string('email')->nullable()->after('nomor_hp');
            }
            if (! Schema::hasColumn('participants', 'kecamatan')) {
                $table->string('kecamatan')->nullable()->after('alamat');
            }
            if (! Schema::hasColumn('participants', 'kelurahan')) {
                $table->string('kelurahan')->nullable()->after('kecamatan');
            }
            if (! Schema::hasColumn('participants', 'produk')) {
                $table->string('produk')->nullable()->after('kategori_usaha');
            }
            if (! Schema::hasColumn('participants', 'tahun_berdiri')) {
                $table->unsignedSmallInteger('tahun_berdiri')->nullable()->after('produk');
            }
            if (! Schema::hasColumn('participants', 'status_usaha')) {
                $table->string('status_usaha')->nullable()->after('tahun_berdiri');
            }
        });
    }

    public function down(): void
    {
        Schema::table('participants', function (Blueprint $table) {
            foreach ([
                'nomor_identitas', 'email', 'kecamatan', 'kelurahan',
                'produk', 'tahun_berdiri', 'status_usaha',
            ] as $kolom) {
                if (Schema::hasColumn('participants', $kolom)) {
                    $table->dropColumn($kolom);
                }
            }
        });
    }
};
