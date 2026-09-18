<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_transaction_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('sales_transaction_id')
                ->constrained('sales_transactions')
                ->cascadeOnDelete(); // item cuma bermakna sebagai bagian dari transaksinya

            $table->foreignId('product_service_id')
                ->constrained('products_services')
                ->restrictOnDelete();

            // SNAPSHOT — dibekukan saat transaksi terjadi, TIDAK ikut berubah
            // walau data master produk berubah di kemudian hari.
            $table->string('nama_snapshot');
            $table->string('jenis_snapshot'); // Produk | Jasa
            $table->integer('qty');
            $table->decimal('harga_jual_snapshot', 15, 2);
            $table->decimal('harga_modal_snapshot', 15, 2); // HPP saat itu

            // Disiapkan strukturnya untuk skema fee/mitra di masa depan — boleh NULL untuk sekarang.
            $table->decimal('fee_rate', 5, 2)->nullable();
            $table->decimal('fee_amount', 15, 2)->nullable();
            $table->decimal('hak_mitra_amount', 15, 2)->nullable();

            $table->decimal('subtotal', 15, 2);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_transaction_items');
    }
};
