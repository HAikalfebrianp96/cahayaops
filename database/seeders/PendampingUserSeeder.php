<?php

namespace Database\Seeders;

use App\Models\Pendamping;
use App\Models\User;
use Illuminate\Database\Seeder;
use Database\Seeders\Concerns\MenolakDijalankanDiProduction;
use Illuminate\Support\Facades\Hash;

class PendampingUserSeeder extends Seeder
{
    use MenolakDijalankanDiProduction;

    /**
     * Buat akun login untuk Pendamping yang sudah ada (dari Tahap 2),
     * supaya bisa dites login sebagai Pendamping.
     *
     * Login Ahmad : ahmad@ybmpln.id / password
     * Login Siti  : siti@ybmpln.id  / password
     * (Password sama dengan email yang sudah ada di data Pendamping Tahap 2)
     */
    public function run(): void
    {
        if ($this->berhentiKarenaProduction()) {
            return;
        }

        $pendampingList = Pendamping::whereIn('nama', ['Ahmad', 'Siti'])->get();

        foreach ($pendampingList as $pendamping) {
            if (! $pendamping->email) {
                continue;
            }

                    // updateOrCreate() dulu ikut menimpa kata sandi akun yang SUDAH ADA.
        // Sekarang kata sandi hanya diisi saat akunnya benar-benar baru
        // dibuat — akun yang sudah dipakai orang tidak pernah disentuh,
        // bahkan di lingkungan pengembangan.
            User::firstOrCreate(
                ['email' => $pendamping->email],
                [
                    'name' => $pendamping->nama,
                    'password' => Hash::make('password'),
                    'role' => 'pendamping',
                    'pendamping_id' => $pendamping->id,
                ]
            );
        }
    }
}
