<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dana_hibah_masuk', function (Blueprint $table) {
            $table->id();
            $table->date('tanggal');
            $table->decimal('nominal', 15, 2);
            $table->string('sumber'); // mis. "YBM PLN Pusat", "Kemenkop UKM"
            $table->string('peruntukan')->nullable(); // mis. "Modal Usaha Cahaya Mart"
            $table->text('keterangan')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dana_hibah_masuk');
    }
};
