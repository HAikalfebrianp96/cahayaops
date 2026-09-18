<?php

namespace Tests\Feature;

use App\Models\OmzetReport;
use App\Models\Participant;
use App\Models\Pendamping;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * OMZET -> HPP -> LABA KOTOR -> PROFIT BERSIH.
 *
 * Yang dijaga kelas ini, dan alasan kenapa:
 *
 *   HPP KOSONG BERARTI "BELUM DILAPORKAN", BUKAN NOL.
 *
 * 271 laporan omzet sudah ada sebelum kolom ini lahir; pesertanya memang tidak
 * pernah ditanya HPP-nya. Kalau kosong dibaca sebagai nol, sistem akan
 * mengklaim "HPP-nya nol, labanya sama dengan seluruh omzet" untuk semuanya —
 * angka yang salah, terlihat masuk akal, dan langsung masuk ke laporan
 * pimpinan serta perhitungan dampak.
 *
 * Biaya operasional kosong DIPERLAKUKAN BERBEDA: ia dibaca sebagai nol. Itu
 * disengaja — banyak usaha mikro memang tidak punya biaya operasional
 * terpisah (jualan dari rumah, tanpa karyawan, tanpa sewa). Usaha yang
 * menjual barang, sebaliknya, selalu punya harga pokok.
 */
class OmzetProfitTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Participant $peserta;

    protected function setUp(): void
    {
        parent::setUp();

        $profil = Pendamping::create(['nama' => 'Rosa', 'nomor_hp' => '0812', 'email' => 'r@op.test', 'status' => 'Aktif']);

        $this->admin = User::create([
            'name' => 'Admin', 'email' => 'admin@op.test',
            'password' => bcrypt('rahasia123'), 'role' => 'admin', 'is_active' => true,
        ]);

        $this->peserta = Participant::create([
            'nama_peserta' => 'Bu Ani',
            'nomor_hp' => '0800',
            'nama_usaha' => 'Keripik Bawang',
            'kategori_usaha' => 'Kuliner',
            'tanggal_mulai_program' => now()->subYear(),
            'status' => 'Aktif',
            'pendamping_id' => $profil->id,
        ]);
    }

    // ==================================================================
    // A. PERHITUNGAN
    // ==================================================================

    public function test_laba_dihitung_dari_omzet_hpp_dan_biaya_operasional(): void
    {
        $r = $this->laporan(['omzet' => 5_000_000, 'hpp' => 3_000_000, 'biaya_operasional' => 500_000]);

        $this->assertSame(2_000_000, $r->labaKotor());
        $this->assertSame(1_500_000, $r->profitBersih());
        $this->assertSame(30.0, $r->marginPersen());
    }

    public function test_hpp_belum_dilaporkan_membuat_laba_null_bukan_nol(): void
    {
        $r = $this->laporan(['omzet' => 5_000_000]);

        $this->assertNull($r->hpp);
        $this->assertNull($r->labaKotor(), 'HPP kosong berarti belum tahu — bukan laba sebesar seluruh omzet.');
        $this->assertNull($r->profitBersih());
        $this->assertNull($r->marginPersen());
        $this->assertFalse($r->punyaDataLaba());
    }

    public function test_biaya_operasional_kosong_diperlakukan_sebagai_nol(): void
    {
        // Berbeda dari HPP: usaha mikro yang jualan dari rumah memang wajar
        // tidak punya biaya operasional terpisah.
        $r = $this->laporan(['omzet' => 5_000_000, 'hpp' => 3_000_000]);

        $this->assertSame(2_000_000, $r->profitBersih());
    }

    public function test_usaha_yang_rugi_dilaporkan_apa_adanya(): void
    {
        $r = $this->laporan(['omzet' => 1_000_000, 'hpp' => 900_000, 'biaya_operasional' => 400_000]);

        $this->assertSame(-300_000, $r->profitBersih());
    }

    public function test_omzet_nol_tidak_menghasilkan_margin(): void
    {
        $r = $this->laporan(['omzet' => 0, 'hpp' => 0]);

        $this->assertSame(0, $r->profitBersih());
        $this->assertNull($r->marginPersen(), 'Margin dari omzet nol tidak punya arti.');
    }

    public function test_scope_dengan_laba_hanya_memuat_laporan_berkomponen_biaya(): void
    {
        $this->laporan(['omzet' => 1_000_000], 1);
        $this->laporan(['omzet' => 2_000_000, 'hpp' => 1_000_000], 2);

        $this->assertSame(1, OmzetReport::denganLaba()->count());
    }

    // ==================================================================
    // B. JALUR INPUT
    // ==================================================================

    public function test_petugas_mencatat_omzet_lengkap_dengan_komponen_biaya(): void
    {
        $this->actingAs($this->admin)
            ->post(route('omzet.store', $this->peserta), [
                'periode_bulan' => 3,
                'periode_tahun' => now()->year,
                'omzet' => 4_000_000,
                'hpp' => 2_500_000,
                'biaya_operasional' => 300_000,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('omzet_reports', [
            'participant_id' => $this->peserta->id,
            'omzet' => 4_000_000,
            'hpp' => 2_500_000,
            'biaya_operasional' => 300_000,
        ]);
    }

    public function test_hpp_boleh_dikosongkan_dan_tersimpan_sebagai_null(): void
    {
        // Memaksa peserta mengisi HPP adalah cara tercepat membuat laporan
        // omzet berhenti masuk sama sekali.
        $this->actingAs($this->admin)->post(route('omzet.store', $this->peserta), [
            'periode_bulan' => 4,
            'periode_tahun' => now()->year,
            'omzet' => 4_000_000,
            'hpp' => '',
        ]);

        $this->assertNull(OmzetReport::firstOrFail()->hpp);
    }

    public function test_hpp_lebih_besar_dari_omzet_ditolak(): void
    {
        $this->actingAs($this->admin)
            ->post(route('omzet.store', $this->peserta), [
                'periode_bulan' => 5,
                'periode_tahun' => now()->year,
                'omzet' => 1_000_000,
                'hpp' => 2_000_000,
            ])
            ->assertSessionHasErrors('hpp');
    }

    public function test_hpp_negatif_ditolak(): void
    {
        $this->actingAs($this->admin)
            ->post(route('omzet.store', $this->peserta), [
                'periode_bulan' => 6,
                'periode_tahun' => now()->year,
                'omzet' => 1_000_000,
                'hpp' => -5000,
            ])
            ->assertSessionHasErrors('hpp');
    }

    public function test_edit_bisa_melengkapi_hpp_laporan_lama(): void
    {
        $r = $this->laporan(['omzet' => 3_000_000]);

        $this->actingAs($this->admin)
            ->put(route('omzet.update', $r), [
                'omzet' => 3_000_000,
                'hpp' => 1_800_000,
                'biaya_operasional' => 200_000,
            ])
            ->assertRedirect();

        $this->assertSame(1_000_000, $r->fresh()->profitBersih());
    }

    // ==================================================================
    // C. FORM PUBLIK (tanpa akun)
    // ==================================================================

    public function test_peserta_mengirim_laporan_lengkap_lewat_link_publik(): void
    {
        $token = $this->peserta->fresh()->report_token;

        $this->post(route('omzet.public.store', $token), [
            'periode_bulan' => 7,
            'periode_tahun' => now()->year,
            'omzet' => 2_000_000,
            'hpp' => 1_200_000,
            'biaya_operasional' => 150_000,
        ])->assertRedirect();

        $r = OmzetReport::firstOrFail();
        $this->assertSame(650_000, $r->profitBersih());
    }

    public function test_form_publik_tetap_menerima_laporan_tanpa_hpp(): void
    {
        $token = $this->peserta->fresh()->report_token;

        $this->post(route('omzet.public.store', $token), [
            'periode_bulan' => 8,
            'periode_tahun' => now()->year,
            'omzet' => 2_000_000,
        ])->assertSessionHasNoErrors();

        $this->assertNull(OmzetReport::firstOrFail()->hpp);
    }

    public function test_form_publik_menolak_hpp_melebihi_omzet(): void
    {
        $token = $this->peserta->fresh()->report_token;

        $this->post(route('omzet.public.store', $token), [
            'periode_bulan' => 9,
            'periode_tahun' => now()->year,
            'omzet' => 1_000_000,
            'hpp' => 9_000_000,
        ])->assertSessionHasErrors('hpp');
    }

    // ==================================================================
    // D. RINGKASAN
    // ==================================================================

    public function test_ringkasan_profit_hanya_menjumlahkan_laporan_berkomponen_biaya(): void
    {
        // Kalau laporan tanpa HPP ikut dijumlahkan dengan HPP dianggap 0,
        // "laba" akan menggelembung sebesar omzet penuh laporan itu.
        $this->laporan(['omzet' => 9_000_000], 1);                                  // tanpa HPP
        $this->laporan(['omzet' => 2_000_000, 'hpp' => 1_200_000], 2);              // dengan HPP

        $this->actingAs($this->admin)
            ->get(route('omzet.index', ['tahun' => now()->year]))
            ->assertOk()
            ->assertSee('Profit Usaha Binaan')
            // 1 dari 2 laporan — pembacanya harus tahu ini sebagian.
            ->assertSee('Dari 1 dari 2 laporan');
    }

    public function test_detail_laporan_tanpa_hpp_mengakui_labanya_belum_bisa_dihitung(): void
    {
        $r = $this->laporan(['omzet' => 3_000_000]);

        $this->actingAs($this->admin)
            ->get(route('omzet.show', $r))
            ->assertOk()
            ->assertSee('HPP belum dilaporkan untuk periode ini');
    }

    // ==================================================================

    private function laporan(array $data, int $bulan = 1): OmzetReport
    {
        return OmzetReport::create($data + [
            'participant_id' => $this->peserta->id,
            'periode_bulan' => $bulan,
            'periode_tahun' => now()->year,
            'submitted_at' => now(),
        ]);
    }
}
