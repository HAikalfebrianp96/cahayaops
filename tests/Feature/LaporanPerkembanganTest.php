<?php

namespace Tests\Feature;

use App\Models\Participant;
use App\Models\ParticipantMeasurement;
use App\Models\Pendamping;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * LAPORAN PERKEMBANGAN USAHA.
 *
 * Satu-satunya laporan Cahaya Preneur yang isinya SELISIH, bukan daftar
 * kejadian — dan selisih paling mudah disalahbaca. Karena itu yang diuji di
 * sini bukan hanya angkanya benar, tapi bahwa KONTEKSNYA ikut terbaca:
 *
 *   - cakupan dinyatakan (berapa dari berapa peserta yang terukur)
 *   - peserta yang belum bisa dibandingkan TETAP muncul, ditandai apa adanya
 *   - sel export dikosongkan, bukan diisi nol
 *
 * Otorisasinya mengikuti gerbang laporan yang sudah ada: Admin, atau
 * Pendamping yang tertaut data Pendamping (dan hanya binaannya).
 */
class LaporanPerkembanganTest extends TestCase
{
    use RefreshDatabase;

    private Pendamping $profilA;
    private Pendamping $profilB;
    private User $admin;
    private User $pendampingA;
    private User $pimpinan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->profilA = Pendamping::create(['nama' => 'Rosa', 'nomor_hp' => '0812', 'email' => 'a@lp.test', 'status' => 'Aktif']);
        $this->profilB = Pendamping::create(['nama' => 'Budi', 'nomor_hp' => '0813', 'email' => 'b@lp.test', 'status' => 'Aktif']);

        $this->admin = $this->user('admin', 'admin@lp.test');
        $this->pendampingA = $this->user('pendamping', 'pa@lp.test', $this->profilA->id);
        $this->pimpinan = $this->user('pimpinan', 'pim@lp.test');
    }

    public function test_cakupan_dinyatakan_bukan_hanya_totalnya(): void
    {
        // 1 peserta terukur, 2 peserta tidak. Kalau laporan hanya menampilkan
        // "+Rp 400.000", pembacanya akan mengira itu prestasi ketiganya.
        $terukur = $this->peserta('Terukur', $this->profilA->id);
        $this->ukur($terukur, ParticipantMeasurement::BASELINE, ['omzet_bulanan' => 1_000_000]);
        $this->ukur($terukur, ParticipantMeasurement::ENDLINE, ['omzet_bulanan' => 1_400_000]);

        $this->peserta('Belum Diukur', $this->profilA->id);
        $this->peserta('Baseline Saja', $this->profilA->id)
            ->measurements()->create([
                'jenis' => ParticipantMeasurement::BASELINE,
                'tanggal' => '2026-01-10',
                'omzet_bulanan' => 900_000,
            ]);

        $this->actingAs($this->admin)
            ->get(route('laporan.perkembangan'))
            ->assertOk()
            ->assertSee('1')
            ->assertSee('dari 3 peserta punya baseline sekaligus pembanding');
    }

    public function test_peserta_yang_belum_bisa_dibandingkan_tetap_muncul(): void
    {
        $this->peserta('Belum Diukur', $this->profilA->id);

        $this->actingAs($this->admin)
            ->get(route('laporan.perkembangan'))
            ->assertOk()
            ->assertSee('Belum Diukur')
            ->assertSee('Belum bisa dibandingkan');
    }

    public function test_selisih_omzet_dan_profit_tampil_untuk_peserta_terukur(): void
    {
        $p = $this->peserta('Bu Ani', $this->profilA->id);
        $this->ukur($p, ParticipantMeasurement::BASELINE, [
            'omzet_bulanan' => 1_000_000, 'hpp_bulanan' => 600_000, 'biaya_operasional_bulanan' => 100_000,
        ]);
        $this->ukur($p, ParticipantMeasurement::ENDLINE, [
            'omzet_bulanan' => 2_000_000, 'hpp_bulanan' => 1_000_000, 'biaya_operasional_bulanan' => 200_000,
        ]);

        $this->actingAs($this->admin)
            ->get(route('laporan.perkembangan'))
            ->assertOk()
            ->assertSee('Terukur (Endline)')
            // Selisih omzet +1.000.000 dan profit +500.000.
            // Format mengikuti helper rupiah(): "Rp1.000.000", tanpa spasi.
            ->assertSee('+Rp1.000.000')
            ->assertSee('+Rp500.000');
    }

    public function test_pendamping_hanya_melihat_binaannya(): void
    {
        $this->peserta('Binaan A', $this->profilA->id);
        $this->peserta('Binaan B', $this->profilB->id);

        $this->actingAs($this->pendampingA)
            ->get(route('laporan.perkembangan'))
            ->assertOk()
            ->assertSee('Binaan A')
            ->assertDontSee('Binaan B');
    }

    public function test_pimpinan_tidak_masuk_modul_laporan_cahaya_preneur(): void
    {
        // Aturan yang SUDAH berlaku di seluruh sub-laporan Cahaya Preneur —
        // laporan baru ini tidak boleh menjadi pengecualiannya.
        $this->actingAs($this->pimpinan)->get(route('laporan.perkembangan'))->assertForbidden();
        $this->actingAs($this->pimpinan)->get(route('laporan.perkembangan.export'))->assertForbidden();
    }

    public function test_export_mengikuti_scope_dan_mengosongkan_sel_yang_belum_terukur(): void
    {
        $terukur = $this->peserta('Terukur', $this->profilA->id);
        $this->ukur($terukur, ParticipantMeasurement::BASELINE, ['omzet_bulanan' => 1_000_000]);
        $this->ukur($terukur, ParticipantMeasurement::ENDLINE, ['omzet_bulanan' => 1_400_000]);

        $this->peserta('Belum Diukur', $this->profilA->id);
        $this->peserta('Binaan B', $this->profilB->id);

        $respons = $this->actingAs($this->pendampingA)->get(route('laporan.perkembangan.export'));
        $respons->assertOk();

        $isi = $respons->streamedContent();

        $this->assertStringContainsString('Terukur', $isi);
        $this->assertStringContainsString('Belum Diukur', $isi);
        $this->assertStringNotContainsString('Binaan B', $isi);

        // Baris peserta yang belum diukur berisi sel KOSONG, bukan nol —
        // nol ikut terhitung saat penerima file menjumlahkan kolomnya.
        $barisBelum = collect(explode("\n", $isi))->first(fn ($b) => str_contains($b, 'Belum Diukur'));
        $this->assertStringNotContainsString(',0,', $barisBelum);
    }

    public function test_filter_pendamping_mempersempit_hasil(): void
    {
        $this->peserta('Binaan A', $this->profilA->id);
        $this->peserta('Binaan B', $this->profilB->id);

        $this->actingAs($this->admin)
            ->get(route('laporan.perkembangan', ['pendamping_id' => $this->profilB->id]))
            ->assertOk()
            ->assertSee('Binaan B')
            ->assertDontSee('Binaan A');
    }

    public function test_kartu_laporan_muncul_di_daftar_laporan(): void
    {
        $this->actingAs($this->admin)
            ->get(route('laporan'))
            ->assertOk()
            ->assertSee(route('laporan.perkembangan'), false);
    }

    // ==================================================================

    private function user(string $role, string $email, ?int $pendampingId = null): User
    {
        return User::create([
            'name' => $email,
            'email' => $email,
            'password' => bcrypt('rahasia123'),
            'role' => $role,
            'pendamping_id' => $pendampingId,
            'is_active' => true,
        ]);
    }

    private function peserta(string $nama, int $pendampingId): Participant
    {
        return Participant::create([
            'nama_peserta' => $nama,
            'nomor_hp' => '0800',
            'nama_usaha' => 'Usaha ' . $nama,
            'kategori_usaha' => 'Kuliner',
            'tanggal_mulai_program' => '2026-01-01',
            'status' => 'Aktif',
            'pendamping_id' => $pendampingId,
        ]);
    }

    private function ukur(Participant $p, string $jenis, array $data): ParticipantMeasurement
    {
        return $p->measurements()->create($data + [
            'jenis' => $jenis,
            'tanggal' => $jenis === ParticipantMeasurement::BASELINE ? '2026-01-10' : '2026-12-10',
        ]);
    }
}
