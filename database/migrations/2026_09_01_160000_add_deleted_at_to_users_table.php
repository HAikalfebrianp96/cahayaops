<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menambahkan SoftDeletes pada tabel users.
 *
 * LATAR BELAKANG
 * Penghapusan akun sebelumnya bersifat permanen. Seluruh foreign key ke
 * `users` ber-ON DELETE SET NULL, jadi barisnya memang tidak ikut terhapus —
 * tapi kolom penunjuknya menjadi NULL dan riwayat kehilangan pelakunya tanpa
 * bisa dikembalikan. Dengan kolom ini, baris user-nya tetap ada sehingga
 * penghapusan bisa dibatalkan secara teknis.
 *
 * PERUBAHANNYA MURNI ADITIF: satu kolom `deleted_at` bertipe TIMESTAMP NULL.
 * Seluruh baris yang sudah ada otomatis bernilai NULL, yang artinya "tidak
 * terhapus" — jadi tidak ada satu pun akun existing yang berubah statusnya.
 * Tidak ada kolom lain yang disentuh, tidak ada data yang ditulis ulang.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Dijaga supaya migration ini aman dijalankan ulang dan tidak
        // menggagalkan environment yang kolomnya sudah ada.
        if (Schema::hasColumn('users', 'deleted_at')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'deleted_at')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
