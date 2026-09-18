<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Data lama (Admin YBM dari Tahap 1) otomatis dapat role 'admin'
            // karena ini nilai default kolom.
            $table->string('role')->default('admin')->after('password');

            $table->foreignId('pendamping_id')
                ->nullable()
                ->after('role')
                ->constrained('pendampings')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pendamping_id');
            $table->dropColumn('role');
        });
    }
};
