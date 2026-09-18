<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * IDENTITAS PELAKU DISIMPAN LANGSUNG DI BARIS ACTIVITY LOG.
 *
 * ==========================================================================
 * KENAPA PERLU
 * ==========================================================================
 *
 * Seluruh foreign key ke `users` memakai ON DELETE SET NULL. Artinya
 * menghapus sebuah akun TIDAK menghapus catatan aktivitasnya — tapi kolom
 * `user_id`-nya menjadi NULL, dan barisnya berubah menjadi "Sistem".
 *
 * Untuk catatan biasa itu bisa diterima. Untuk Activity Log tidak: seluruh
 * gunanya adalah menjawab SIAPA yang melakukan sesuatu. Log yang kehilangan
 * pelakunya bukan sekadar kurang lengkap — ia menyesatkan, karena tindakan
 * seseorang jadi tampak dilakukan sistem.
 *
 * Selama ini keadaan itu dihindari dengan cara menolak penghapusan akun yang
 * punya jejak. Tapi aturan itu membuat akun uji coba tidak pernah bisa
 * dibersihkan, padahal justru akun semacam itu yang paling perlu hilang.
 *
 * Dengan dua kolom ini, identitas pelaku ikut tersimpan di barisnya sendiri.
 * Akun boleh benar-benar dihapus, dan jejak auditnya tetap terbaca lengkap.
 *
 * SIFAT MIGRATION INI:
 * - Menambah dua kolom nullable. Tidak ada baris yang dihapus atau dipindah.
 * - Mengisi kolom baru itu untuk catatan yang pelakunya MASIH ADA. Nilainya
 *   disalin dari tabel users, bukan dikarang.
 * - Catatan yang pelakunya sudah lebih dulu hilang tetap NULL — itu jujur,
 *   dan tidak ada sumber yang bisa dipakai untuk mengisinya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->string('actor_nama')->nullable()->after('user_id');
            $table->string('actor_email')->nullable()->after('actor_nama');
        });

        // Backfill dari data yang ada sekarang. Dikerjakan per user (bukan satu
        // UPDATE ... JOIN) supaya jalan juga di SQLite yang dipakai test.
        foreach (DB::table('users')->select('id', 'name', 'email')->get() as $user) {
            DB::table('activity_logs')
                ->where('user_id', $user->id)
                ->update([
                    'actor_nama' => $user->name,
                    'actor_email' => $user->email,
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropColumn(['actor_nama', 'actor_email']);
        });
    }
};
