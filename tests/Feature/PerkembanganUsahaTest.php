<?php

namespace Tests\Feature;

use App\Models\Participant;
use App\Models\ParticipantMeasurement;
use App\Models\Pendamping;
use App\Models\User;
use App\Services\PerbandinganService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * BASELINE -> MONITORING -> ENDLINE.
 *
 * Yang dijaga kelas ini, berurutan dari yang paling penting:
 *
 *   1. INDIKATOR TANPA PASANGAN TIDAK MENGHASILKAN PERUBAHAN. Ini aturan
 *      yang paling mudah dilanggar tanpa disadari — cukup satu `?? 0` di
 *      tempat yang salah, dan peserta yang belum diukur ulang akan terlihat
 *      seolah usahanya anjlok ke nol. Angkanya tetap masuk akal di layar,
 *      jadi tidak akan ada yang curiga.
 *
 *   2. PEMBAGI NOL TIDAK MENGHASILKAN PERSENTASE.
 *
 *   3. Otorisasinya sama persis dengan modul catatan peserta lain: Pendamping
 *      hanya binaannya, Pimpinan read-only, hapus Admin saja.
 */
class PerkembanganUsahaTest extends TestCase
{
    use RefreshDatabase;

    private Pendamping $profilA;
    private Pendamping $profilB;
    private User $admin;
    private User $pendampingA;
    private User $pimpinan;
    private Participant $binaanA;
    private Participant $binaanB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->profilA = Pendamping::create(['nama' => 'Rosa', 'nomor_hp' => '0812', 'email' => 'a@pk.test', 'status' => 'Aktif']);
        $this->profilB = Pendamping::create(['nama' => 'Budi', 'nomor_hp' => '0813', 'email' => 'b@pk.test', 'status' => 'Aktif']);

        $this->admin = $this->user('admin', 'admin@pk.test');
        $this->pendampingA = $this->user('pendamping', 'pa@pk.test', $this->profilA->id);
        $this->pimpinan = $this->user('pimpinan', 'pim@pk.test');

        $this->binaanA = $this->peserta('Binaan A', $this->profilA->id);
        $this->binaanB = $this->peserta('Binaan B', $this->profilB->id);
    }

    // ==================================================================
    // A. ATURAN PALING PENTING — data tidak lengkap tidak diklaim berubah
    // ==================================================================

    public function test_indikator_tanpa_pasangan_tidak_dihitung_sebagai_perubahan(): void
    {
        // Baseline mencatat tenaga kerja, endline TIDAK. Kalau sistem
        // memperlakukan yang kosong sebagai 0, ia akan melaporkan
        // "tenaga kerja berkurang 3 orang" — perubahan yang tidak pernah
        // terjadi dan tidak pernah diukur.
        $this->ukur($this->binaanA, ParticipantMeasurement::BASELINE, [
            'omzet_bulanan' => 1_000_000,
            'jumlah_tenaga_kerja' => 3,
        ]);
        $this->ukur($this->binaanA, ParticipantMeasurement::ENDLINE, [
            'omzet_bulanan' => 1_500_000,
            'jumlah_tenaga_kerja' => null,
        ]);

        $rapor = app(PerbandinganService::class)->untukPeserta($this->binaanA->fresh());

        $this->assertArrayHasKey('omzet_bulanan', $rapor['perubahan']);
        $this->assertArrayNotHasKey('jumlah_tenaga_kerja', $rapor['perubahan']);
        $this->assertSame(500_000, $rapor['perubahan']['omzet_bulanan']['selisih']);
    }

    public function test_tanpa_baseline_perubahan_tidak_bisa_dihitung_dan_alasannya_disebutkan(): void
    {
        $this->ukur($this->binaanA, ParticipantMeasurement::ENDLINE, ['omzet_bulanan' => 2_000_000]);

        $rapor = app(PerbandinganService::class)->untukPeserta($this->binaanA->fresh());

        $this->assertFalse($rapor['siap_dibandingkan']);
        $this->assertSame([], $rapor['perubahan']);
        $this->assertNotEmpty($rapor['alasan_belum_siap']);
        $this->assertStringContainsString('Baseline belum dicatat', $rapor['alasan_belum_siap'][0]);
    }

    public function test_kenaikan_dari_nol_tidak_menghasilkan_persentase(): void
    {
        $this->ukur($this->binaanA, ParticipantMeasurement::BASELINE, ['jumlah_pelanggan' => 0]);
        $this->ukur($this->binaanA, ParticipantMeasurement::ENDLINE, ['jumlah_pelanggan' => 25]);

        $rapor = app(PerbandinganService::class)->untukPeserta($this->binaanA->fresh());

        $this->assertSame(25, $rapor['perubahan']['jumlah_pelanggan']['selisih']);
        // Naik dari 0 ke 25 bukan "kenaikan tak hingga persen".
        $this->assertNull($rapor['perubahan']['jumlah_pelanggan']['persen']);
    }

    public function test_monitoring_dipakai_sebagai_pembanding_kalau_endline_belum_ada_dan_sumbernya_dinyatakan(): void
    {
        $this->ukur($this->binaanA, ParticipantMeasurement::BASELINE, ['omzet_bulanan' => 1_000_000], '2026-01-10');
        $this->ukur($this->binaanA, ParticipantMeasurement::MONITORING, ['omzet_bulanan' => 1_200_000], '2026-06-10');

        $rapor = app(PerbandinganService::class)->untukPeserta($this->binaanA->fresh());

        $this->assertTrue($rapor['siap_dibandingkan']);
        // Pembacanya harus tahu ini monitoring berjalan, bukan hasil akhir.
        $this->assertSame(ParticipantMeasurement::MONITORING, $rapor['sumber_akhir']);
    }

    public function test_endline_mengalahkan_monitoring_sebagai_titik_akhir(): void
    {
        $this->ukur($this->binaanA, ParticipantMeasurement::BASELINE, ['omzet_bulanan' => 1_000_000], '2026-01-10');
        $this->ukur($this->binaanA, ParticipantMeasurement::MONITORING, ['omzet_bulanan' => 1_200_000], '2026-06-10');
        $this->ukur($this->binaanA, ParticipantMeasurement::ENDLINE, ['omzet_bulanan' => 1_800_000], '2026-12-10');

        $rapor = app(PerbandinganService::class)->untukPeserta($this->binaanA->fresh());

        $this->assertSame(ParticipantMeasurement::ENDLINE, $rapor['sumber_akhir']);
        $this->assertSame(800_000, $rapor['perubahan']['omzet_bulanan']['selisih']);
    }

    public function test_indikator_netral_tidak_dinilai_membaik_atau_memburuk(): void
    {
        // HPP naik bisa berarti usaha membesar, bisa juga boros. Sistem tidak
        // punya cara membedakannya, jadi ia tidak menilai.
        $this->ukur($this->binaanA, ParticipantMeasurement::BASELINE, ['hpp_bulanan' => 500_000]);
        $this->ukur($this->binaanA, ParticipantMeasurement::ENDLINE, ['hpp_bulanan' => 900_000]);

        $rapor = app(PerbandinganService::class)->untukPeserta($this->binaanA->fresh());

        $this->assertSame(400_000, $rapor['perubahan']['hpp_bulanan']['selisih']);
        $this->assertNull($rapor['perubahan']['hpp_bulanan']['membaik']);
    }

    public function test_profit_bulanan_ikut_dibandingkan_hanya_kalau_komponennya_lengkap(): void
    {
        $this->ukur($this->binaanA, ParticipantMeasurement::BASELINE, [
            'omzet_bulanan' => 1_000_000,
            'hpp_bulanan' => 600_000,
            'biaya_operasional_bulanan' => 100_000,
        ]);
        $this->ukur($this->binaanA, ParticipantMeasurement::ENDLINE, [
            'omzet_bulanan' => 2_000_000,
            'hpp_bulanan' => 1_000_000,
            'biaya_operasional_bulanan' => 200_000,
        ]);

        $rapor = app(PerbandinganService::class)->untukPeserta($this->binaanA->fresh());

        $this->assertSame(300_000, $rapor['perubahan']['profit_bulanan']['sebelum']);
        $this->assertSame(800_000, $rapor['perubahan']['profit_bulanan']['sesudah']);
        $this->assertSame(500_000, $rapor['perubahan']['profit_bulanan']['selisih']);
    }

    public function test_profit_tidak_dibandingkan_saat_hpp_belum_dilaporkan(): void
    {
        // HPP kosong berarti BELUM TERDATA. Menganggapnya nol akan membuat
        // profit awal sama dengan seluruh omzet — angka karangan.
        $this->ukur($this->binaanA, ParticipantMeasurement::BASELINE, ['omzet_bulanan' => 1_000_000]);
        $this->ukur($this->binaanA, ParticipantMeasurement::ENDLINE, [
            'omzet_bulanan' => 2_000_000,
            'hpp_bulanan' => 1_000_000,
        ]);

        $rapor = app(PerbandinganService::class)->untukPeserta($this->binaanA->fresh());

        $this->assertArrayNotHasKey('profit_bulanan', $rapor['perubahan']);
    }

    public function test_indikator_ya_tidak_dihitung_sebagai_pencapaian(): void
    {
        $this->ukur($this->binaanA, ParticipantMeasurement::BASELINE, ['punya_legalitas' => false]);
        $this->ukur($this->binaanA, ParticipantMeasurement::ENDLINE, ['punya_legalitas' => true]);

        $rapor = app(PerbandinganService::class)->untukPeserta($this->binaanA->fresh());

        $this->assertTrue($rapor['perubahan_status']['punya_legalitas']['membaik']);
    }

    public function test_rekap_menyebutkan_berapa_peserta_yang_benar_benar_terukur(): void
    {
        // Satu peserta lengkap, satu hanya punya baseline. Totalnya boleh
        // dijumlahkan, TAPI jumlah pesertanya harus ikut terbaca — total
        // kenaikan dari 1 peserta dan dari 100 peserta adalah dua klaim yang
        // sangat berbeda.
        $this->ukur($this->binaanA, ParticipantMeasurement::BASELINE, ['omzet_bulanan' => 1_000_000]);
        $this->ukur($this->binaanA, ParticipantMeasurement::ENDLINE, ['omzet_bulanan' => 1_400_000]);
        $this->ukur($this->binaanB, ParticipantMeasurement::BASELINE, ['omzet_bulanan' => 900_000]);

        $rekap = app(PerbandinganService::class)->rekap(
            Participant::with('measurements')->get()
        );

        $this->assertSame(2, $rekap['jumlah_peserta']);
        $this->assertSame(1, $rekap['jumlah_siap_dibandingkan']);
        $this->assertSame(400_000, $rekap['indikator']['omzet_bulanan']['total_selisih']);
        $this->assertSame(1, $rekap['indikator']['omzet_bulanan']['jumlah_peserta_terukur']);
    }

    // ==================================================================
    // B. CRUD & OTORISASI
    // ==================================================================

    public function test_pendamping_bisa_mencatat_pengukuran_binaannya(): void
    {
        $this->actingAs($this->pendampingA)
            ->post(route('perkembangan.store', $this->binaanA), [
                'jenis' => ParticipantMeasurement::BASELINE,
                'tanggal' => now()->toDateString(),
                'omzet_bulanan' => 750_000,
            ])
            ->assertRedirect(route('perkembangan.index', $this->binaanA));

        $this->assertDatabaseHas('participant_measurements', [
            'participant_id' => $this->binaanA->id,
            'jenis' => ParticipantMeasurement::BASELINE,
            'omzet_bulanan' => 750_000,
            'dicatat_oleh' => $this->pendampingA->id,
        ]);
    }

    public function test_kolom_yang_dikosongkan_tersimpan_sebagai_null_bukan_nol(): void
    {
        $this->actingAs($this->admin)->post(route('perkembangan.store', $this->binaanA), [
            'jenis' => ParticipantMeasurement::BASELINE,
            'tanggal' => now()->toDateString(),
            'omzet_bulanan' => 500_000,
            'jumlah_tenaga_kerja' => '',
        ]);

        $m = ParticipantMeasurement::firstOrFail();

        $this->assertNull($m->jumlah_tenaga_kerja, 'Kolom kosong harus berarti "belum terdata", bukan nol.');
    }

    public function test_pendamping_tidak_bisa_mencatat_untuk_binaan_pendamping_lain(): void
    {
        $this->actingAs($this->pendampingA)
            ->post(route('perkembangan.store', $this->binaanB), [
                'jenis' => ParticipantMeasurement::BASELINE,
                'tanggal' => now()->toDateString(),
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('participant_measurements', 0);
    }

    public function test_pimpinan_boleh_melihat_tapi_tidak_boleh_mencatat(): void
    {
        $this->ukur($this->binaanA, ParticipantMeasurement::BASELINE, ['omzet_bulanan' => 1_000_000]);

        $this->actingAs($this->pimpinan)
            ->get(route('perkembangan.index', $this->binaanA))
            ->assertOk();

        $this->actingAs($this->pimpinan)
            ->post(route('perkembangan.store', $this->binaanA), [
                'jenis' => ParticipantMeasurement::MONITORING,
                'tanggal' => now()->toDateString(),
            ])
            ->assertForbidden();
    }

    public function test_pendamping_bisa_mengubah_tapi_tidak_bisa_menghapus(): void
    {
        $m = $this->ukur($this->binaanA, ParticipantMeasurement::BASELINE, ['omzet_bulanan' => 1_000_000]);

        $this->actingAs($this->pendampingA)
            ->put(route('perkembangan.update', $m), [
                'jenis' => ParticipantMeasurement::BASELINE,
                'tanggal' => $m->tanggal->toDateString(),
                'omzet_bulanan' => 1_100_000,
            ])
            ->assertRedirect();

        $this->assertSame(1_100_000, $m->fresh()->omzet_bulanan);

        // Menghapus baseline memutus SELURUH klaim perubahan peserta itu dan
        // tidak bisa disusun ulang dari data lain — jadi Admin saja.
        $this->actingAs($this->pendampingA)
            ->delete(route('perkembangan.destroy', $m))
            ->assertForbidden();
    }

    public function test_admin_menghapus_lalu_memulihkan_dari_arsip(): void
    {
        $m = $this->ukur($this->binaanA, ParticipantMeasurement::BASELINE, ['omzet_bulanan' => 1_000_000]);

        $this->actingAs($this->admin)->delete(route('perkembangan.destroy', $m))->assertRedirect();
        $this->assertSoftDeleted('participant_measurements', ['id' => $m->id]);

        $this->actingAs($this->admin)->post(route('perkembangan.restore', $m->id))->assertRedirect();
        $this->assertDatabaseHas('participant_measurements', ['id' => $m->id, 'deleted_at' => null]);
    }

    public function test_pengukuran_terhapus_tidak_ikut_dibandingkan(): void
    {
        $baseline = $this->ukur($this->binaanA, ParticipantMeasurement::BASELINE, ['omzet_bulanan' => 1_000_000]);
        $this->ukur($this->binaanA, ParticipantMeasurement::ENDLINE, ['omzet_bulanan' => 1_500_000]);

        $baseline->delete();

        $rapor = app(PerbandinganService::class)->untukPeserta($this->binaanA->fresh());

        $this->assertFalse($rapor['siap_dibandingkan']);
    }

    public function test_tanggal_pengukuran_tidak_boleh_di_masa_depan(): void
    {
        $this->actingAs($this->admin)
            ->post(route('perkembangan.store', $this->binaanA), [
                'jenis' => ParticipantMeasurement::BASELINE,
                'tanggal' => now()->addMonth()->toDateString(),
            ])
            ->assertSessionHasErrors('tanggal');
    }

    public function test_jenis_karangan_ditolak(): void
    {
        $this->actingAs($this->admin)
            ->post(route('perkembangan.store', $this->binaanA), [
                'jenis' => 'Midline',
                'tanggal' => now()->toDateString(),
            ])
            ->assertSessionHasErrors('jenis');
    }

    public function test_perubahan_tercatat_di_activity_log(): void
    {
        $this->actingAs($this->admin)->post(route('perkembangan.store', $this->binaanA), [
            'jenis' => ParticipantMeasurement::BASELINE,
            'tanggal' => now()->toDateString(),
            'omzet_bulanan' => 500_000,
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'measurement_created',
            'user_id' => $this->admin->id,
        ]);
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
            'tanggal_mulai_program' => now()->subYear(),
            'status' => 'Aktif',
            'pendamping_id' => $pendampingId,
        ]);
    }

    private function ukur(Participant $p, string $jenis, array $data, ?string $tanggal = null): ParticipantMeasurement
    {
        return $p->measurements()->create($data + [
            'jenis' => $jenis,
            'tanggal' => $tanggal ?? now()->subDays($jenis === ParticipantMeasurement::BASELINE ? 300 : 1)->toDateString(),
        ]);
    }
}
