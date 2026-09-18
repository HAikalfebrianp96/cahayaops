<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('participants', function (Blueprint $table) {
            $table->string('report_token', 40)->nullable()->unique()->after('pendamping_id');
        });

        // Backfill token untuk peserta yang sudah ada (dari Tahap 2),
        // supaya semua peserta punya link laporan omzet.
        DB::table('participants')->whereNull('report_token')->get(['id'])->each(function ($row) {
            DB::table('participants')
                ->where('id', $row->id)
                ->update(['report_token' => Str::random(32)]);
        });
    }

    public function down(): void
    {
        Schema::table('participants', function (Blueprint $table) {
            $table->dropColumn('report_token');
        });
    }
};
