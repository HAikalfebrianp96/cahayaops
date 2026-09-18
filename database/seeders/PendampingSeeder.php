<?php

namespace Database\Seeders;

use App\Models\Pendamping;
use Illuminate\Database\Seeder;
use Database\Seeders\Concerns\MenolakDijalankanDiProduction;

class PendampingSeeder extends Seeder
{
    use MenolakDijalankanDiProduction;

    public function run(): void
    {
        if ($this->berhentiKarenaProduction()) {
            return;
        }

        $pendampings = [
            ['nama' => 'Ahmad', 'nomor_hp' => '081200000001', 'email' => 'ahmad@ybmpln.id', 'status' => 'Aktif'],
            ['nama' => 'Siti', 'nomor_hp' => '081200000002', 'email' => 'siti@ybmpln.id', 'status' => 'Aktif'],
        ];

        foreach ($pendampings as $data) {
            Pendamping::firstOrCreate(['nama' => $data['nama']], $data);
        }
    }
}
