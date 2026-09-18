<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fondasi status akun User (Manajemen User — Tahap 2).
 *
 * Audit Tahap 1 memastikan tabel `users` BELUM punya mekanisme aktif/nonaktif
 * maupun catatan login terakhir. Tiga kolom ini melengkapinya:
 *
 *   is_active      -> penentu boleh login atau tidak (BUKAN hapus akun)
 *   last_login_at  -> kapan terakhir berhasil login
 *   last_login_ip  -> dari IP mana
 *
 * TIDAK ADA kolom lama yang dihapus/diubah, dan TIDAK ADA data existing yang
 * disentuh: default is_active = true membuat kelima akun yang sudah ada tetap
 * bisa login persis seperti sebelumnya.
 *
 * Kolom last_login_* sengaja NULLABLE tanpa default — akun yang belum pernah
 * login setelah fitur ini ada memang tidak punya catatan, dan itu keadaan
 * yang jujur (bukan diisi tanggal palsu).
 *
 * Panjang 45 untuk IP mengikuti kolom ip_address yang sudah ada di tabel
 * `sessions` dan `activity_logs` — cukup untuk IPv6.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('pendamping_id');
            $table->timestamp('last_login_at')->nullable()->after('is_active');
            $table->string('last_login_ip', 45)->nullable()->after('last_login_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_active', 'last_login_at', 'last_login_ip']);
        });
    }
};
