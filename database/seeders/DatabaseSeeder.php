<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Database\Seeders\Concerns\MenolakDijalankanDiProduction;

class DatabaseSeeder extends Seeder
{
    use MenolakDijalankanDiProduction;

    public function run(): void
    {
        if ($this->berhentiKarenaProduction()) {
            return;
        }

        $this->call([
            AdminSeeder::class,
            PendampingSeeder::class,
            ParticipantSeeder::class,
            OmzetReportSeeder::class,
            PendampingUserSeeder::class,
            PerkembanganDemoSeeder::class,
            PimpinanSeeder::class,
            BusinessUnitSeeder::class,
        ]);
    }
}
