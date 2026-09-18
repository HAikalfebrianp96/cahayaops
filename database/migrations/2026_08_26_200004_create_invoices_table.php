<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();

            $table->foreignId('sales_transaction_id')
                ->unique() // 1 transaksi = 1 invoice utama
                ->constrained('sales_transactions')
                ->cascadeOnDelete();

            $table->string('nomor_invoice')->unique(); // INV-YYYYMM-XXXXXX
            $table->date('tanggal_invoice');
            $table->text('catatan')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
