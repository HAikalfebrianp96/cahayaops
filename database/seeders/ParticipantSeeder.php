<?php

namespace Database\Seeders;

use App\Models\Participant;
use App\Models\Pendamping;
use Illuminate\Database\Seeder;
use Database\Seeders\Concerns\MenolakDijalankanDiProduction;

class ParticipantSeeder extends Seeder
{
    use MenolakDijalankanDiProduction;

    public function run(): void
    {
        if ($this->berhentiKarenaProduction()) {
            return;
        }

        $ahmad = Pendamping::where('nama', 'Ahmad')->first();
        $siti = Pendamping::where('nama', 'Siti')->first();

        $participants = [
            [
                'nama_peserta' => 'Ibu Siti',
                'nomor_hp' => '081300000001',
                'alamat' => 'Bengkulu',
                'nama_usaha' => 'Keripik Siti',
                'kategori_usaha' => 'Makanan & Minuman',
                'tanggal_mulai_program' => now()->subMonths(6),
                'status' => 'Aktif',
                'pendamping_id' => $ahmad?->id,
            ],
            [
                'nama_peserta' => 'Ibu Ani',
                'nomor_hp' => '081300000002',
                'alamat' => 'Bengkulu',
                'nama_usaha' => 'Kue Ani',
                'kategori_usaha' => 'Makanan & Minuman',
                'tanggal_mulai_program' => now()->subMonths(4),
                'status' => 'Aktif',
                'pendamping_id' => $ahmad?->id,
            ],
            [
                'nama_peserta' => 'Ibu Rina',
                'nomor_hp' => '081300000003',
                'alamat' => 'Bengkulu',
                'nama_usaha' => 'Kerajinan Rina',
                'kategori_usaha' => 'Kerajinan',
                'tanggal_mulai_program' => now()->subMonths(3),
                'status' => 'Aktif',
                'pendamping_id' => $siti?->id,
            ],
            [
                'nama_peserta' => 'Ibu Dewi',
                'nomor_hp' => '081300000004',
                'alamat' => 'Bengkulu',
                'nama_usaha' => 'Catering Dewi',
                'kategori_usaha' => 'Makanan & Minuman',
                'tanggal_mulai_program' => now()->subMonths(2),
                'status' => 'Graduasi',
                'pendamping_id' => $siti?->id,
            ],
        ];

        foreach ($participants as $data) {
            Participant::firstOrCreate(
                ['nama_peserta' => $data['nama_peserta'], 'nama_usaha' => $data['nama_usaha']],
                $data
            );
        }
    }
}
