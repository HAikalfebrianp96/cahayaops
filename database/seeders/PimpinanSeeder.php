<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Database\Seeders\Concerns\MenolakDijalankanDiProduction;
use Illuminate\Support\Facades\Hash;

class PimpinanSeeder extends Seeder
{
    use MenolakDijalankanDiProduction;

    /**
     * Login Pimpinan : pimpinan@ybmpln.id / password
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
            ['email' => 'pimpinan@ybmpln.id'],
            [
                'name' => 'Pimpinan YBM',
                'password' => Hash::make('password'),
                'role' => 'pimpinan',
            ]
        );
    }
}
