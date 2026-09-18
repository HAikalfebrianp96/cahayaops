<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SOFT DELETE untuk PELANGGAN koperasi.
 *
 * Pelanggan selama ini hanya bisa DIBUAT — lewat quick-add di halaman
 * Penjualan — dan tidak pernah bisa dilihat, diperbaiki, atau dibuang.
 * Akibatnya salah ketik nama pelanggan menempel permanen di setiap invoice
 * yang merujuknya.
 *
 * Penghapusannya soft karena `sales_transactions.customer_id` menunjuk ke
 * sini: menghapus permanen akan melubangi riwayat penjualan dan invoice yang
 * sudah terbit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            if (! Schema::hasColumn('customers', 'deleted_at')) {
                $table->softDeletes();
            }
            if (! Schema::hasColumn('customers', 'deleted_by')) {
                $table->foreignId('deleted_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            if (Schema::hasColumn('customers', 'deleted_by')) {
                $table->dropForeign(['deleted_by']);
                $table->dropColumn('deleted_by');
            }
            if (Schema::hasColumn('customers', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }
};
