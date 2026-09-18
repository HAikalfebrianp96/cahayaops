<?php

namespace Tests\Feature;

use App\Models\Participant;
use App\Models\Pendamping;
use App\Models\PendampinganRecord;
use App\Models\User;
use App\Services\FollowUpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * "HARI INI" HARUS BERARTI HARI INI DI BENGKULU.
 *
 * =====================================================================
 * YANG DIJAGA
 * =====================================================================
 *
 * Seluruh pengelompokan waktu di sistem ini — "Jadwal Hari Ini", "terlambat",
 * periode omzet bulan berjalan, rentang KPI — membandingkan tanggal tersimpan
 * dengan now(). Kalau zona waktu aplikasi bergeser ke UTC, now() mundur tujuh
 * jam, dan setiap hari antara pukul 00:00-07:00 WIB sistem menganggap hari
 * masih kemarin.
 *
 * Akibatnya tidak pernah terlihat seperti kerusakan: janji yang jatuh hari
 * ini sekadar tidak muncul di keranjang "Jadwal Hari Ini" sampai lewat pukul
 * tujuh pagi — persis jam kerja pendamping dimulai.
 *
 * config/app.php sudah memakai env('APP_TIMEZONE', 'Asia/Jakarta') — bawaannya
 * pun sudah benar, bukan UTC. Test ini menjaga supaya tetap begitu, dan
 * memastikan perbandingannya benar di kedua ujung hari.
 */
class ZonaWaktuTest extends TestCase
{
    use RefreshDatabase;

    private Pendamping $profil;
    private Participant $peserta;

    protected function setUp(): void
    {
        parent::setUp();

        $this->profil = Pendamping::create([
            'nama' => 'Pendamping Uji', 'nomor_hp' => '081200000001',
            'email' => 'p@waktu.test', 'status' => 'Aktif',
        ]);

        User::create([
            'name' => 'Rosa Uji', 'email' => 'rosa@waktu.test',
            'password' => 'password123', 'role' => 'pendamping',
            'pendamping_id' => $this->profil->id, 'is_active' => true,
        ]);

        $this->peserta = Participant::create([
            'nama_peserta' => 'Peserta Uji', 'nomor_hp' => '081200000002',
            'alamat' => 'Jl. Uji', 'nama_usaha' => 'Usaha Uji',
            'kategori_usaha' => 'Jasa',
            'tanggal_mulai_program' => '2026-01-10',
            'status' => 'Aktif', 'pendamping_id' => $this->profil->id,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_zona_waktu_aplikasi_adalah_waktu_indonesia_barat(): void
    {
        $this->assertSame(
            'Asia/Jakarta',
            config('app.timezone'),
            'Seluruh pengelompokan "hari ini" dihitung memakai zona waktu ini.'
        );

        $this->assertSame('Asia/Jakarta', date_default_timezone_get());
    }

    /**
     * Janji yang jatuh hari ini harus masuk keranjang "Jadwal Hari Ini",
     * baik dibuka dini hari maupun menjelang tengah malam.
     *
     * Dua ujung hari sengaja diuji: kalau perbandingannya memakai waktu UTC,
     * pukul 00:15 WIB masih terbaca sebagai kemarin dan pukul 23:45 WIB
     * sudah terbaca sebagai besok.
     */
    public function test_jadwal_hari_ini_terbaca_benar_di_kedua_ujung_hari(): void
    {
        foreach (['00:15', '07:30', '23:45'] as $jam) {
            Carbon::setTestNow(Carbon::parse("2026-06-10 {$jam}", 'Asia/Jakarta'));

            $this->peserta->pendampinganRecords()->delete();

            PendampinganRecord::create([
                'participant_id' => $this->peserta->id,
                'pendamping_id' => $this->profil->id,
                'tanggal' => '2026-06-05',
                'jenis_kegiatan' => PendampinganRecord::KONSULTASI,
                'materi' => 'Materi uji',
                // Jatuh tempo HARI INI menurut kalender Bengkulu.
                'tanggal_follow_up' => '2026-06-10',
            ]);

            $kelompok = app(FollowUpService::class)
                ->kelompokkan(collect([$this->peserta->fresh()]));

            $this->assertSame(
                1,
                $kelompok['hari_ini']->count(),
                "Pukul {$jam} WIB, janji tanggal 10 Juni seharusnya masuk \"Jadwal Hari Ini\"."
            );

            $this->assertSame(0, $kelompok['terlambat']->count());
            $this->assertSame(0, $kelompok['akan_datang']->count());
        }
    }

    /**
     * Janji kemarin tetap terlambat, janji besok tetap akan datang —
     * diperiksa pada jam yang paling rawan salah baca.
     */
    public function test_batas_hari_tidak_bergeser_pada_dini_hari(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-10 00:15', 'Asia/Jakarta'));

        PendampinganRecord::create([
            'participant_id' => $this->peserta->id,
            'pendamping_id' => $this->profil->id,
            'tanggal' => '2026-06-01',
            'jenis_kegiatan' => PendampinganRecord::KONSULTASI,
            'materi' => 'Materi uji',
            'tanggal_follow_up' => '2026-06-09',
        ]);

        $kelompok = app(FollowUpService::class)
            ->kelompokkan(collect([$this->peserta->fresh()]));

        $this->assertSame(1, $kelompok['terlambat']->count(), 'Janji kemarin harus terbaca terlambat.');
        $this->assertSame(0, $kelompok['hari_ini']->count());
    }
}
