<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tambah kolom catatan per-item (untuk Item Manual) — aman lewat Schema biasa.
        Schema::table('sales_transaction_items', function (Blueprint $table) {
            $table->text('catatan')->nullable()->after('hak_mitra_amount');
        });

        // product_service_id jadi boleh NULL (item manual tidak selalu punya master produk).
        // Pakai SQL mentah supaya tidak butuh package doctrine/dbal.
        //
        // CATATAN (ditambahkan belakangan): statement MODIFY ini sintaks
        // KHUSUS MySQL — tidak dikenali SQLite (dipakai untuk `php artisan
        // test`, lihat phpunit.xml). Dibungkus kondisi driver supaya testing
        // tidak gagal. TIDAK ADA PERUBAHAN PERILAKU untuk MySQL — statement
        // yang dijalankan di MySQL PERSIS SAMA seperti sebelumnya.
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE `sales_transaction_items` MODIFY `product_service_id` BIGINT UNSIGNED NULL');
        } elseif (DB::getDriverName() === 'sqlite') {
            // SQLite (database test) sebelumnya tetap NOT NULL, sehingga
            // penjualan ITEM MANUAL tidak pernah bisa diuji — padahal di
            // MySQL item manual sudah dipakai (mis. Kerupuk Tuiri, HPP Rp0).
            // change() bawaan Laravel 12 membangun ulang tabel SQLite; MySQL
            // tidak tersentuh cabang ini.
            Schema::table('sales_transaction_items', function (Blueprint $table) {
                $table->unsignedBigInteger('product_service_id')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE `sales_transaction_items` MODIFY `product_service_id` BIGINT UNSIGNED NOT NULL');
        }

        Schema::table('sales_transaction_items', function (Blueprint $table) {
            $table->dropColumn('catatan');
        });
    }
};
