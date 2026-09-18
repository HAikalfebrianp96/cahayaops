<?php

namespace Database\Seeders;

use App\Models\BusinessUnit;
use Illuminate\Database\Seeder;
use Database\Seeders\Concerns\MenolakDijalankanDiProduction;

class BusinessUnitSeeder extends Seeder
{
    use MenolakDijalankanDiProduction;

    /**
     * Cuma data MASTER unit usaha awal — sengaja TIDAK ada produk/jasa dummy,
     * biar Admin yang input sendiri lewat UI (sesuai instruksi K2).
     */
    public function run(): void
    {
        if ($this->berhentiKarenaProduction()) {
            return;
        }

        BusinessUnit::firstOrCreate(
            ['kode' => 'MART'],
            [
                'nama' => 'Cahaya Mart',
                'jenis_usaha' => 'Retail/Sembako',
                'tipe' => 'Barang',
                'status' => 'Aktif',
                'tanggal_mulai' => now(),
            ]
        );

        BusinessUnit::firstOrCreate(
            ['kode' => 'KREATIF'],
            [
                'nama' => 'Cahaya Kreatif',
                'jenis_usaha' => 'Sablon & Percetakan',
                'tipe' => 'Campuran',
                'status' => 'Aktif',
                'tanggal_mulai' => now(),
            ]
        );
    }
}
