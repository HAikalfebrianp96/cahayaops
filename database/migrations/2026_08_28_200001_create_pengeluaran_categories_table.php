<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengeluaran_categories', function (Blueprint $table) {
            $table->id();
            $table->string('nama')->unique();
            $table->enum('status', ['Aktif', 'Tidak Aktif'])->default('Aktif');
            $table->timestamps();
        });

        // 7 kategori default — insertOrIgnore aman dijalankan berkali-kali
        // (tidak akan bikin duplikat karena kolom 'nama' UNIQUE).
        $default = ['Gaji', 'Listrik', 'BBM', 'Kemasan', 'Transportasi', 'ATK', 'Lainnya'];
        $rows = array_map(fn ($nama) => [
            'nama' => $nama,
            'status' => 'Aktif',
            'created_at' => now(),
            'updated_at' => now(),
        ], $default);

        DB::table('pengeluaran_categories')->insertOrIgnore($rows);
    }

    public function down(): void
    {
        Schema::dropIfExists('pengeluaran_categories');
    }
};
