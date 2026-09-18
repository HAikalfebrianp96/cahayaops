<?php

namespace App\Services;

use App\Models\User;

/**
 * Pengaman agar sistem TIDAK PERNAH kehilangan seluruh Admin aktif.
 *
 * LATAR BELAKANG (audit Tahap 1)
 * Otorisasi CahayaOS bertumpu pada User::isAdmin() di 83 titik. Saat ini hanya
 * ada SATU akun Admin. Kalau akun itu dinonaktifkan atau perannya diturunkan,
 * tidak ada lagi yang bisa membuka Pengaturan, Monitoring & Keamanan, Backup
 * Database, maupun Manajemen User — dan tidak ada jalan untuk memulihkannya
 * dari dalam aplikasi. Kelas ini mencegah keadaan itu terjadi.
 *
 * Kelas ini HANYA logic. Tahap 2 sengaja belum memasangnya ke UI/endpoint
 * manapun — pemakaiannya menyusul di Tahap 3 (tombol Nonaktifkan & Ubah Role),
 * supaya penjagaannya sudah siap SEBELUM tombolnya ada.
 */
class UserAccountGuard
{
    /** Nilai role Admin — sesuai audit Tahap 1, disimpan sebagai string di kolom `role`. */
    public const ROLE_ADMIN = 'admin';

    /**
     * Jumlah Admin yang masih aktif saat ini.
     */
    public function jumlahAdminAktif(): int
    {
        return User::where('role', self::ROLE_ADMIN)->where('is_active', true)->count();
    }

    /**
     * Apakah user ini satu-satunya Admin yang masih aktif?
     *
     * Akun yang sudah nonaktif TIDAK pernah dianggap "Admin aktif terakhir" —
     * dia memang sudah tidak memegang akses apa pun.
     */
    public function adminAktifTerakhir(User $user): bool
    {
        if ($user->role !== self::ROLE_ADMIN || ! $user->isActive()) {
            return false;
        }

        return $this->jumlahAdminAktif() <= 1;
    }

    /**
     * Boleh dinonaktifkan? Admin aktif terakhir TIDAK boleh.
     */
    public function bolehDinonaktifkan(User $user): bool
    {
        return ! $this->adminAktifTerakhir($user);
    }

    /**
     * Boleh diubah perannya jadi $roleBaru?
     *
     * Menurunkan Admin aktif terakhir ke role lain sama bahayanya dengan
     * menonaktifkannya. Mengubah role Admin terakhir menjadi 'admin' lagi
     * (alias tidak berubah) tentu tetap boleh.
     */
    public function bolehUbahRole(User $user, string $roleBaru): bool
    {
        if ($roleBaru === self::ROLE_ADMIN) {
            return true;
        }

        return ! $this->adminAktifTerakhir($user);
    }

    // ==========================================================
    // PENGHAPUSAN AKUN: SUDAH TIDAK ADA
    // ==========================================================
    //
    // Di sini dulu ada TABEL_JEJAK beserta rincianJejak(), punyaJejak(),
    // bolehDihapus(), dan alasanTidakBolehDihapus() — semuanya untuk menjawab
    // satu pertanyaan: "apakah akun ini aman dihapus permanen?"
    //
    // Pertanyaan itu tidak lagi pernah diajukan. Akun tidak dihapus, hanya
    // dinonaktifkan, dan menonaktifkan selalu aman terhadap jejak: barisnya
    // tetap ada, jadi seluruh relasi ke activity_logs dan data bisnis tetap
    // menunjuk pelakunya.
    //
    // Perkakasnya dilepas, bukan disimpan menganggur: helper bernama
    // "bolehDihapus" yang masih tersedia adalah undangan bagi kode berikutnya
    // untuk menghidupkan kembali penghapusan akun tanpa menyadari akibatnya.

    /**
     * Alasan penolakan untuk ditampilkan ke Admin — dipakai Tahap 3.
     * null berarti tindakannya diperbolehkan.
     */
    public function alasanTidakBoleh(User $user, ?string $roleBaru = null): ?string
    {
        $ditolak = $roleBaru === null
            ? ! $this->bolehDinonaktifkan($user)
            : ! $this->bolehUbahRole($user, $roleBaru);

        if (! $ditolak) {
            return null;
        }

        return 'Tindakan ini akan membuat sistem tidak punya Admin aktif sama sekali. '
            . 'Angkat Admin lain terlebih dahulu sebelum mengubah akun ini.';
    }
}
