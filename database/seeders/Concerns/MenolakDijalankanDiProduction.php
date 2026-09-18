<?php

namespace Database\Seeders\Concerns;

/**
 * PENGAMAN SEEDER TERHADAP DATABASE PRODUCTION.
 *
 * ==========================================================================
 * KENAPA INI ADA
 * ==========================================================================
 *
 * Seluruh seeder di proyek ini dibuat untuk menyiapkan data uji coba, dan
 * beberapa di antaranya memakai updateOrCreate() pada akun yang alamat
 * emailnya SAMA dengan akun sungguhan di production:
 *
 *   AdminSeeder          -> admin@ybmpln.id
 *   PimpinanSeeder       -> pimpinan@ybmpln.id
 *   PendampingUserSeeder -> ahmad@ybmpln.id, siti@ybmpln.id
 *   PendampingSeeder     -> profil Ahmad & Siti
 *
 * Artinya satu perintah `php artisan db:seed` di server production akan:
 *
 *   1. MENGUBAH KATA SANDI Admin dan Pimpinan yang sedang dipakai menjadi
 *      kata sandi contoh yang tertulis di dalam berkas seeder-nya — dan
 *      berkas itu ada di dalam repositori.
 *   2. MENGHIDUPKAN KEMBALI akun serta profil Ahmad dan Siti, yang sudah
 *      sengaja dihapus permanen.
 *
 * Keduanya terjadi tanpa peringatan apa pun, dan `db:seed` adalah perintah
 * yang sangat lazim ikut tertulis di skrip deploy Laravel.
 *
 * Seeder-nya TIDAK dihapus: semuanya masih dipakai untuk menyiapkan
 * lingkungan pengembangan. Yang ditambahkan hanya satu penjaga — seeder
 * berhenti dengan pesan jelas kalau dijalankan di production.
 *
 * Kalau suatu saat memang perlu dijalankan di sana (misalnya mengisi data
 * awal di server yang benar-benar masih kosong), pengelola harus menyatakan
 * niatnya secara eksplisit:
 *
 *     SEEDER_BOLEH_PRODUCTION=true php artisan db:seed
 *
 * Membuatnya merepotkan itu memang tujuannya.
 */
trait MenolakDijalankanDiProduction
{
    /**
     * @return bool true kalau seeder harus berhenti sekarang juga.
     */
    protected function berhentiKarenaProduction(): bool
    {
        if (! app()->environment('production')) {
            return false;
        }

        if (filter_var(env('SEEDER_BOLEH_PRODUCTION', false), FILTER_VALIDATE_BOOLEAN)) {
            $this->command?->warn(
                '  SEEDER_BOLEH_PRODUCTION=true — seeder dijalankan di lingkungan production atas permintaan eksplisit.'
            );

            return false;
        }

        $this->command?->error(
            '  DITOLAK: ' . static::class . ' tidak dijalankan karena APP_ENV=production.'
        );
        $this->command?->line(
            '  Seeder di proyek ini berisi akun & data contoh. Menjalankannya di production akan '
            . 'menimpa kata sandi akun sungguhan dan menghidupkan kembali akun uji coba yang sudah dihapus.'
        );
        $this->command?->line(
            '  Kalau memang disengaja (server baru yang masih kosong), jalankan dengan '
            . 'SEEDER_BOLEH_PRODUCTION=true.'
        );

        return true;
    }
}
