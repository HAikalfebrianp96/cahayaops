<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('nomor_transaksi')->unique(); // TRX-YYYYMM-XXXXXX

            $table->foreignId('unit_id')
                ->constrained('business_units')
                ->restrictOnDelete();

            $table->foreignId('customer_id')
                ->nullable()
                ->constrained('customers')
                ->nullOnDelete();

            $table->date('tanggal');
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('diskon', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);

            $table->string('status_pembayaran')->default('Belum Dibayar'); // Belum Dibayar | DP | Lunas
            $table->decimal('dp_nominal', 15, 2)->default(0);
            $table->string('metode_pembayaran')->nullable(); // Cash | Transfer | QRIS | Lainnya

            $table->text('catatan')->nullable();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_transactions');
    }
};
