<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SUMBER ANGGOTA KOPERASI.
 *
 * Menjawab pertanyaan program yang selama ini tidak bisa dijawab angka:
 * berapa banyak anggota Koperasi Cahaya yang lahir dari pembinaan Cahaya
 * Preneur, dan berapa yang datang sendiri.
 *
 * `participant_id` memang sudah menunjukkan keterhubungan, TAPI ia tidak
 * cukup: seorang pendaftar mandiri bisa saja ditautkan ke data peserta
 * belakangan (karena ternyata orang yang sama), dan sejak itu asal-usulnya
 * jadi tidak terbaca lagi. Kolom ini merekam asal-usul saat pendaftaran, dan
 * tidak berubah walau tautannya berubah.
 *
 * Nullable, tanpa default — 2 anggota existing dibiarkan kosong apa adanya
 * karena asal-usulnya memang tidak tercatat, dan menebaknya justru
 * memalsukan data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('koperasi_members', function (Blueprint $table) {
            if (! Schema::hasColumn('koperasi_members', 'sumber_anggota')) {
                $table->string('sumber_anggota')->nullable()->after('participant_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('koperasi_members', function (Blueprint $table) {
            if (Schema::hasColumn('koperasi_members', 'sumber_anggota')) {
                $table->dropColumn('sumber_anggota');
            }
        });
    }
};
