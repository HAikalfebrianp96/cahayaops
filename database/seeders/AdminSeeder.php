<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Database\Seeders\Concerns\MenolakDijalankanDiProduction;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    use MenolakDijalankanDiProduction;

    /**
     * Buat akun Admin YBM untuk testing Tahap 1.
     *
     * Login:
     *   Email    : admin@ybmpln.id
     *   Password : password
     */
    public function run(): void
    {
        if ($this->berhentiKarenaProduction()) {
            return;
        }

        // updateOrCreate() dulu ikut menimpa kata sandi akun yang SUDAH ADA.
        // Sekarang kata sandi hanya diisi saat akunnya benar-benar baru
        // dibuat — akun yang sudah dipakai orang tidak pernah disentuh,
        // bahkan di lingkungan pengembangan.
        User::firstOrCreate(
            ['email' => 'admin@ybmpln.id'],
            [
                'name' => 'Admin YBM',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'is_active' => true,
            ]
        );
    }
}
