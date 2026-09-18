<?php

namespace Database\Seeders;

use App\Models\OmzetReport;
use App\Models\Participant;
use Illuminate\Database\Seeder;
use Database\Seeders\Concerns\MenolakDijalankanDiProduction;

class OmzetReportSeeder extends Seeder
{
    use MenolakDijalankanDiProduction;

    /**
     * Data dummy secukupnya supaya halaman Monitoring Omzet & Riwayat Omzet
     * langsung ada isinya saat testing — bukan data produksi.
     */
    public function run(): void
    {
        if ($this->berhentiKarenaProduction()) {
            return;
        }

        $siti = Participant::where('nama_peserta', 'Ibu Siti')->first();
        $ani = Participant::where('nama_peserta', 'Ibu Ani')->first();

        if ($siti) {
            $this->buatLaporan($siti, now()->subMonths(2), 1500000);
            $this->buatLaporan($siti, now()->subMonths(1), 2000000);
            $this->buatLaporan($siti, now(), 2500000, 'Omzet naik karena pesanan Lebaran.');
        }

        if ($ani) {
            $this->buatLaporan($ani, now()->subMonths(1), 1200000);
        }
    }

    private function buatLaporan(Participant $participant, $tanggal, int $omzet, ?string $catatan = null): void
    {
        OmzetReport::firstOrCreate(
            [
                'participant_id' => $participant->id,
                'periode_bulan' => $tanggal->month,
                'periode_tahun' => $tanggal->year,
            ],
            [
                'omzet' => $omzet,
                'catatan' => $catatan,
                'submitted_at' => $tanggal,
            ]
        );
    }
}
