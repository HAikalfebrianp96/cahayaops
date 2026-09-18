<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hpp_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hpp_calculation_id')->constrained('hpp_calculations')->cascadeOnDelete();
            $table->string('kategori'); // Bahan Baku / Kemasan / Tenaga Kerja / Biaya Produksi / Biaya Lainnya
            $table->string('nama_biaya');
            $table->decimal('qty', 10, 2);
            $table->string('satuan')->nullable();
            $table->decimal('harga_satuan', 15, 2);
            $table->decimal('total', 15, 2); // snapshot qty × harga_satuan
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hpp_components');
    }
};
