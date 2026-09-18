<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

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
    /**
     * Jejak yang membuat akun TIDAK boleh dihapus permanen.
     *
     * Seluruh FK ke `users` memakai ON DELETE SET NULL, jadi menghapus akun
     * tidak akan menimbulkan error apa pun — ia hanya mengosongkan kolom
     * "oleh siapa" di seluruh riwayat, diam-diam. Justru karena database
     * tidak akan menolak, penolakannya harus eksplisit di sini.
     *
     * Kolom diperiksa dengan Schema::hasTable/hasColumn supaya tetap jalan
     * pada basis data uji yang belum memuat seluruh tabel.
     *
     * @var array<string, array{0: string, 1: string}>  label => [tabel, kolom]
     */
    private const JEJAK = [
        'catatan aktivitas' => ['activity_logs', 'user_id'],
        'transaksi penjualan' => ['sales_transactions', 'created_by'],
        'pengeluaran' => ['pengeluaran', 'created_by'],
        'perhitungan HPP' => ['hpp_calculations', 'created_by'],
        'program dampak' => ['impact_programs', 'dibuat_oleh'],
        'periode SHU' => ['shu_periods', 'ditetapkan_oleh'],
    ];

    /**
     * Alasan akun ini tidak boleh dihapus permanen, atau null kalau boleh.
     *
     * Urutannya disengaja: pemeriksaan yang paling mudah dipahami pengguna
     * didahulukan, supaya pesannya menjelaskan hal yang paling relevan.
     */
    public function alasanTidakBisaDihapus(User $user, ?int $idPengguna = null): ?string
    {
        if ($idPengguna !== null && $user->id === $idPengguna) {
            return 'Anda tidak dapat menghapus akun Anda sendiri.';
        }

        if ($this->adminAktifTerakhir($user)) {
            return 'Akun ini adalah Admin aktif terakhir. Menghapusnya membuat sistem tidak punya Admin sama sekali.';
        }

        if ($user->pendamping_id !== null) {
            return 'Akun ini masih terhubung ke data Pendamping. Lepaskan hubungannya lebih dulu.';
        }

        $ada = [];

        foreach (self::JEJAK as $label => [$tabel, $kolom]) {
            if (! Schema::hasTable($tabel) || ! Schema::hasColumn($tabel, $kolom)) {
                continue;
            }

            $jumlah = DB::table($tabel)->where($kolom, $user->id)->count();

            if ($jumlah > 0) {
                $ada[] = "{$jumlah} {$label}";
            }
        }

        if ($ada !== []) {
            return 'Akun ini masih tercatat sebagai pelaku pada ' . implode(', ', $ada)
                . '. Menghapusnya membuat riwayat itu kehilangan keterangan siapa yang melakukannya. Nonaktifkan saja akunnya.';
        }

        return null;
    }

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
