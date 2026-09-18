<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_transaction_items', function (Blueprint $table) {
            $table->decimal('diskon', 15, 2)->default(0)->after('harga_modal_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('sales_transaction_items', function (Blueprint $table) {
            $table->dropColumn('diskon');
        });
    }
};
