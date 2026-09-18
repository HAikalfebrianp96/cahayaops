<?php

namespace Tests\Feature;

use App\Models\Participant;
use App\Models\Pendamping;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TAUTAN WHATSAPP UNTUK LINK LAPORAN OMZET.
 *
 * Deep-link, BUKAN integrasi API: tautan ini membuka WhatsApp milik petugas
 * dengan pesan yang sudah tertulis, dan petugaslah yang menekan kirim. Tidak
 * ada pesan yang dikirim sistem, dan tidak ada yang boleh membuat orang
 * mengira sebaliknya.
 *
 * Yang dijaga: nomor dinormalkan ke format internasional, dan tombolnya
 * TIDAK muncul kalau nomornya tidak masuk akal — mengarahkan petugas ke
 * percakapan dengan nomor yang tidak ada lebih buruk daripada tidak ada
 * tombol sama sekali.
 */
class TautanWhatsappOmzetTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin', 'email' => 'admin@wa.test',
            'password' => bcrypt('rahasia123'), 'role' => 'admin', 'is_active' => true,
        ]);
    }

    public function test_nomor_lokal_diubah_ke_format_internasional(): void
    {
        $p = $this->peserta('0812-3456-7890');

        $this->assertSame('6281234567890', $p->nomorHpInternasional());
    }

    public function test_nomor_yang_sudah_internasional_tidak_diubah_dua_kali(): void
    {
        $this->assertSame('6281234567890', $this->peserta('+62 812 3456 7890')->nomorHpInternasional());
        $this->assertSame('6281234567890', $this->peserta('6281234567890')->nomorHpInternasional());
    }

    public function test_nomor_tidak_masuk_akal_tidak_menghasilkan_tautan(): void
    {
        $p = $this->peserta('-');

        $this->assertNull($p->nomorHpInternasional());
        $this->assertNull($p->tautanWhatsappLaporanOmzet());
    }

    public function test_tautan_memuat_link_laporan_omzet_peserta_itu_sendiri(): void
    {
        $p = $this->peserta('081234567890');
        $tautan = $p->tautanWhatsappLaporanOmzet();

        $this->assertStringStartsWith('https://wa.me/6281234567890?text=', $tautan);
        // Link yang dibawa harus link peserta ini — bukan halaman umum.
        $this->assertStringContainsString(
            rawurlencode(route('omzet.public.show', $p->report_token)),
            $tautan
        );
    }

    public function test_tombol_muncul_di_detail_peserta_dan_menunjuk_tautan_yang_benar(): void
    {
        $p = $this->peserta('081234567890');

        $this->actingAs($this->admin)
            ->get(route('participants.show', $p))
            ->assertOk()
            ->assertSee('Kirim lewat WhatsApp')
            ->assertSee('https://wa.me/6281234567890', false);
    }

    public function test_tombol_disembunyikan_saat_nomor_tidak_bisa_dipakai(): void
    {
        $p = $this->peserta('-');

        $this->actingAs($this->admin)
            ->get(route('participants.show', $p))
            ->assertOk()
            ->assertDontSee('Kirim lewat WhatsApp');
    }

    private function peserta(string $nomorHp): Participant
    {
        $profil = Pendamping::create([
            'nama' => 'Rosa ' . uniqid(),
            'nomor_hp' => '0812',
            'email' => uniqid() . '@wa.test',
            'status' => 'Aktif',
        ]);

        return Participant::create([
            'nama_peserta' => 'Bu Ani',
            'nomor_hp' => $nomorHp,
            'nama_usaha' => 'Keripik Bawang',
            'kategori_usaha' => 'Kuliner',
            'tanggal_mulai_program' => now()->subYear(),
            'status' => 'Aktif',
            'pendamping_id' => $profil->id,
        ])->fresh();
    }
}
