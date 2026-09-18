<?php

namespace Tests\Feature;

use App\Models\Pendamping;
use App\Models\Participant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * DASHBOARD CAHAYA PRENEUR HARUS TERBACA SEBAGAI EMPAT JAWABAN.
 *
 * Isi dashboard ini tumbuh mengikuti sejarah penambahan fitur, dan setiap
 * fitur baru wajar saja ingin menaruh kartunya di tempat yang enak. Yang
 * mudah hilang tanpa terasa justru URUTANNYA — dan begitu urutannya kembali
 * acak, pembacanya harus menebak lagi blok mana menjawab apa.
 *
 * Test ini mengunci strukturnya, bukan angkanya.
 */
class DashboardEmpatJawabanTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin Uji', 'email' => 'admin@empat.test',
            'password' => 'password123', 'role' => 'admin', 'is_active' => true,
        ]);

        $profil = Pendamping::create([
            'nama' => 'Pendamping Uji', 'nomor_hp' => '0812',
            'email' => 'p@empat.test', 'status' => 'Aktif',
        ]);

        Participant::create([
            'nama_peserta' => 'Peserta Uji', 'nomor_hp' => '0813', 'alamat' => 'Jl. Uji',
            'nama_usaha' => 'Usaha Uji', 'kategori_usaha' => 'Jasa',
            'tanggal_mulai_program' => now()->subMonths(3)->toDateString(),
            'status' => 'Aktif', 'pendamping_id' => $profil->id,
        ]);
    }

    private function halaman(): string
    {
        return $this->actingAs($this->admin)
            ->get(route('dashboard', ['modul' => 'preneur']))
            ->assertOk()
            ->getContent();
    }

    public function test_keempat_pertanyaan_muncul_sebagai_judul(): void
    {
        $html = $this->halaman();

        foreach ([
            'Berapa peserta yang kita dampingi?',
            'Siapa yang perlu ditindaklanjuti?',
            'Apakah peserta berkembang?',
            'Bagaimana hasil program?',
        ] as $pertanyaan) {
            $this->assertStringContainsString(
                $pertanyaan,
                $html,
                "Dashboard tidak lagi menjawab: {$pertanyaan}"
            );
        }
    }

    /**
     * Bagian yang menuntut tindakan harus didahulukan.
     *
     * Sisa dashboard adalah laporan keadaan; hanya bagian ini yang berisi
     * pekerjaan. Kalau ia turun ke bawah angka-angka, yang paling berguna
     * justru yang paling jarang terbaca.
     */
    public function test_pertanyaan_tindak_lanjut_berada_paling_atas(): void
    {
        $html = $this->halaman();

        $tindakLanjut = strpos($html, 'Siapa yang perlu ditindaklanjuti?');
        $this->assertNotFalse($tindakLanjut);

        foreach ([
            'Berapa peserta yang kita dampingi?',
            'Apakah peserta berkembang?',
            'Bagaimana hasil program?',
        ] as $sesudahnya) {
            $this->assertGreaterThan(
                $tindakLanjut,
                strpos($html, $sesudahnya),
                "\"{$sesudahnya}\" seharusnya berada SESUDAH bagian tindak lanjut."
            );
        }
    }

    /**
     * Kinerja pendamping adalah urusan pengelolaan tim, bukan jawaban atas
     * pertanyaan tentang peserta — tempatnya sesudah keempatnya.
     */
    public function test_kinerja_pendamping_berada_sesudah_keempat_jawaban(): void
    {
        $html = $this->halaman();

        $this->assertGreaterThan(
            strpos($html, 'Bagaimana hasil program?'),
            strpos($html, 'Kerja tim pendamping'),
            'Bagian kerja tim seharusnya tidak memotong alur keempat jawaban.'
        );
    }
}
