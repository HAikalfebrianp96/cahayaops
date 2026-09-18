<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products_services', function (Blueprint $table) {
            $table->id();

            $table->foreignId('unit_id')
                ->constrained('business_units')
                ->restrictOnDelete(); // produk/jasa TIDAK BOLEH ikut hilang kalau unit-nya dihapus

            $table->string('kode');
            $table->string('nama');
            $table->string('jenis'); // Produk | Jasa
            $table->string('kategori')->nullable(); // bebas, tidak hardcode
            $table->text('deskripsi')->nullable();
            $table->string('satuan')->nullable(); // pcs, kg, paket, jam, dst — bebas

            $table->decimal('harga_modal', 15, 2)->default(0); // HPP — nilai DEFAULT di master,
            // WAJIB di-snapshot ke transaksi nanti (K3+), bukan dihitung ulang dari sini.
            $table->decimal('harga_jual', 15, 2)->default(0);

            $table->string('status')->default('Aktif'); // Aktif | Tidak Aktif
            $table->boolean('stock_tracking')->default(false); // cuma flag, belum ada sistem stok

            $table->timestamps();

            // Kode unik CUKUP dalam satu unit (bukan unik global)
            $table->unique(['unit_id', 'kode']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products_services');
    }
};
