<?php

namespace Database\Seeders;

use App\Models\LegalitasRecord;
use App\Models\Participant;
use App\Models\PendampinganRecord;
use App\Models\Pendamping;
use App\Models\TrainingRecord;
use Illuminate\Database\Seeder;
use Database\Seeders\Concerns\MenolakDijalankanDiProduction;

class PerkembanganDemoSeeder extends Seeder
{
    use MenolakDijalankanDiProduction;

    /**
     * Data dummy secukupnya untuk Ibu Siti, supaya halaman Detail Peserta
     * (Pendampingan/Pelatihan/Legalitas/Timeline) langsung ada isinya saat
     * testing Tahap 4 — bukan data produksi.
     */
    public function run(): void
    {
        if ($this->berhentiKarenaProduction()) {
            return;
        }

        $siti = Participant::where('nama_peserta', 'Ibu Siti')->first();
        $ahmad = Pendamping::where('nama', 'Ahmad')->first();

        if (! $siti) {
            return;
        }

        PendampinganRecord::firstOrCreate(
            ['participant_id' => $siti->id, 'tanggal' => now()->subMonths(2)->startOfMonth()->addDays(9), 'jenis_kegiatan' => 'Asesmen'],
            [
                'pendamping_id' => $ahmad?->id,
                'materi' => 'Asesmen awal kondisi usaha keripik.',
                'catatan' => 'Usaha sudah berjalan 1 tahun, omzet belum stabil.',
            ]
        );

        PendampinganRecord::firstOrCreate(
            ['participant_id' => $siti->id, 'tanggal' => now()->subMonths(2)->startOfMonth()->addDays(24), 'jenis_kegiatan' => 'Pendampingan'],
            [
                'pendamping_id' => $ahmad?->id,
                'materi' => 'Membantu Ibu Siti menyelesaikan proses pembuatan NIB.',
                'tindak_lanjut' => 'Lanjut pendampingan packaging bulan depan.',
            ]
        );

        TrainingRecord::firstOrCreate(
            ['participant_id' => $siti->id, 'nama_pelatihan' => 'Pelatihan Pembuatan NIB'],
            [
                'tanggal' => now()->subMonths(2)->startOfMonth()->addDays(21),
                'penyelenggara' => 'YBM PLN UP3 Bengkulu',
                'status' => 'Selesai',
            ]
        );

        LegalitasRecord::firstOrCreate(
            ['participant_id' => $siti->id, 'jenis_dokumen' => 'NIB'],
            [
                'status' => 'Sudah Ada',
                'tanggal_terbit' => now()->subMonths(1)->startOfMonth()->addDays(2),
                'catatan' => 'NIB terbit setelah pendampingan.',
            ]
        );

        LegalitasRecord::firstOrCreate(
            ['participant_id' => $siti->id, 'jenis_dokumen' => 'PIRT'],
            [
                'status' => 'Proses',
            ]
        );
    }
}
