<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\KoperasiMember;
use App\Models\MemberSaving;
use App\Models\ShuPeriod;
use App\Models\User;
use App\Services\ShuService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SHU — PENETAPAN & PEMBAGIAN.
 *
 * Tiga hal yang dijaga:
 *
 *   1. SIMPANAN SUKARELA BUKAN DASAR JASA MODAL. Ini keputusan yang paling
 *      mudah dilanggar tanpa terlihat: hasilnya tetap berupa angka yang masuk
 *      akal, hanya saja anggota yang menitipkan uang besar menjelang tutup
 *      buku mendapat bagian yang tidak ia usahakan.
 *
 *   2. DASAR PEMBAGIAN DIBEKUKAN. Penetapan yang sudah disahkan tidak boleh
 *      bergeser ketika simpanan atau transaksi baru masuk kemudian.
 *
 *   3. Persentase TIDAK di-hard-code, dan total bukan-100 diperingatkan —
 *      bukan ditolak. Pembagian SHU adalah keputusan Rapat Anggota.
 */
class ShuKoperasiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $timKoperasi;
    private User $pendamping;
    private KoperasiMember $anggotaA;
    private KoperasiMember $anggotaB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->user('admin', 'admin@shu.test');
        $this->timKoperasi = $this->user(User::ROLE_TIM_KOPERASI, 'tk@shu.test');
        $this->pendamping = $this->user('pendamping', 'pd@shu.test');

        $this->anggotaA = $this->anggota('A-001', 'Anggota A');
        $this->anggotaB = $this->anggota('A-002', 'Anggota B');
    }

    // ==================================================================
    // A. DASAR PEMBAGIAN
    // ==================================================================

    public function test_simpanan_sukarela_tidak_menjadi_dasar_jasa_modal(): void
    {
        // A: pokok 100rb + wajib 200rb = dasar 300rb.
        // B: pokok 100rb + SUKARELA 5jt = dasar 100rb, bukan 5,1jt.
        $this->simpan($this->anggotaA, 'Simpanan Pokok', 100_000);
        $this->simpan($this->anggotaA, 'Simpanan Wajib', 200_000);
        $this->simpan($this->anggotaB, 'Simpanan Pokok', 100_000);
        $this->simpan($this->anggotaB, 'Simpanan Sukarela', 5_000_000);

        $dasar = app(ShuService::class)->dasarPembagian(now()->year);

        $this->assertSame(300_000, $dasar[$this->anggotaA->id]['simpanan']);
        $this->assertSame(
            100_000,
            $dasar[$this->anggotaB->id]['simpanan'],
            'Simpanan sukarela adalah titipan, bukan modal — ia tidak boleh menambah bagian SHU.'
        );
    }

    public function test_simpanan_yang_dibatalkan_tidak_dihitung(): void
    {
        $this->simpan($this->anggotaA, 'Simpanan Pokok', 100_000);
        $batal = $this->simpan($this->anggotaA, 'Simpanan Wajib', 900_000);
        $batal->update(['status' => MemberSaving::STATUS_DIBATALKAN]);

        $dasar = app(ShuService::class)->dasarPembagian(now()->year);

        $this->assertSame(100_000, $dasar[$this->anggotaA->id]['simpanan']);
    }

    public function test_pelanggan_tanpa_tautan_anggota_tidak_ikut_jasa_usaha(): void
    {
        // Pembeli yang bukan anggota tetap tidak masuk pembagian — jasa usaha
        // adalah bagi hasil untuk anggota yang berbelanja di koperasinya
        // sendiri, bukan untuk setiap pembeli.
        Customer::create(['nama' => 'Pembeli Umum', 'nomor_hp' => '0800']);

        $dasar = app(ShuService::class)->dasarPembagian(now()->year);

        $this->assertSame(0, array_sum(array_column($dasar, 'transaksi')));
    }

    // ==================================================================
    // B. PEMBAGIAN
    // ==================================================================

    public function test_jasa_modal_dibagi_proporsional_menurut_simpanan(): void
    {
        // A 300rb : B 100rb  => 3 : 1
        // Pool jasa modal 25% dari 4jt = 1jt  => A 750rb, B 250rb
        $this->simpan($this->anggotaA, 'Simpanan Pokok', 300_000);
        $this->simpan($this->anggotaB, 'Simpanan Pokok', 100_000);

        $periode = $this->periode(4_000_000, [
            ['nama' => 'Cadangan', 'persen' => 75, 'ke_anggota' => false, 'dasar' => null],
            ['nama' => 'Jasa Modal', 'persen' => 25, 'ke_anggota' => true, 'dasar' => 'simpanan'],
        ]);

        app(ShuService::class)->susunAlokasi($periode);

        $a = $periode->allocations()->where('koperasi_member_id', $this->anggotaA->id)->first();
        $b = $periode->allocations()->where('koperasi_member_id', $this->anggotaB->id)->first();

        $this->assertSame(750_000, $a->jasa_modal);
        $this->assertSame(250_000, $b->jasa_modal);
        $this->assertSame(1_000_000, $periode->totalKeAnggota());
    }

    public function test_pool_tanpa_dasar_tidak_dibagi_rata_melainkan_tidak_dibagikan(): void
    {
        // Tidak ada satu pun simpanan. Pembagi nol tidak boleh menjadi
        // "bagi rata ke semua anggota" — anggota yang belum menyimpan apa pun
        // tidak berhak atas jasa modal.
        $periode = $this->periode(4_000_000, [
            ['nama' => 'Jasa Modal', 'persen' => 100, 'ke_anggota' => true, 'dasar' => 'simpanan'],
        ]);

        app(ShuService::class)->susunAlokasi($periode);

        $this->assertSame(0, $periode->totalKeAnggota());
        $this->assertCount(0, $periode->allocations()->get());
    }

    public function test_komponen_dana_koperasi_tidak_ikut_dibagi_ke_anggota(): void
    {
        $this->simpan($this->anggotaA, 'Simpanan Pokok', 100_000);

        $periode = $this->periode(10_000_000, [
            ['nama' => 'Cadangan', 'persen' => 40, 'ke_anggota' => false, 'dasar' => null],
            ['nama' => 'Dana Sosial', 'persen' => 40, 'ke_anggota' => false, 'dasar' => null],
            ['nama' => 'Jasa Modal', 'persen' => 20, 'ke_anggota' => true, 'dasar' => 'simpanan'],
        ]);

        app(ShuService::class)->susunAlokasi($periode);

        $this->assertSame(2_000_000, $periode->totalKeAnggota());
    }

    public function test_menyusun_ulang_mengganti_alokasi_bukan_menumpuknya(): void
    {
        $this->simpan($this->anggotaA, 'Simpanan Pokok', 100_000);

        $periode = $this->periode(1_000_000, [
            ['nama' => 'Jasa Modal', 'persen' => 100, 'ke_anggota' => true, 'dasar' => 'simpanan'],
        ]);

        app(ShuService::class)->susunAlokasi($periode);
        app(ShuService::class)->susunAlokasi($periode);

        $this->assertCount(1, $periode->allocations()->get());
        $this->assertSame(1_000_000, $periode->totalKeAnggota());
    }

    public function test_dasar_pembagian_dibekukan_dan_tidak_ikut_berubah(): void
    {
        $this->simpan($this->anggotaA, 'Simpanan Pokok', 100_000);

        $periode = $this->periode(1_000_000, [
            ['nama' => 'Jasa Modal', 'persen' => 100, 'ke_anggota' => true, 'dasar' => 'simpanan'],
        ]);
        app(ShuService::class)->susunAlokasi($periode);

        // Simpanan baru masuk SETELAH penetapan disusun.
        $this->simpan($this->anggotaA, 'Simpanan Wajib', 900_000);

        $alokasi = $periode->allocations()->first();

        $this->assertSame(100_000, $alokasi->dasar_simpanan,
            'Dasar yang sudah ditetapkan tidak boleh bergeser ketika simpanan baru masuk.');
    }

    // ==================================================================
    // C. PERINGATAN, BUKAN PENOLAKAN
    // ==================================================================

    public function test_total_persen_bukan_seratus_diperingatkan_tapi_tetap_disimpan(): void
    {
        $periode = $this->periode(1_000_000, [
            ['nama' => 'Cadangan', 'persen' => 40, 'ke_anggota' => false, 'dasar' => null],
        ]);

        $peringatan = app(ShuService::class)->peringatan($periode);

        $this->assertNotEmpty($peringatan);
        $this->assertStringContainsString('bukan 100%', $peringatan[0]);
        // Diperingatkan, TIDAK ditolak — penetapan SHU keputusan Rapat Anggota.
        $this->assertDatabaseHas('shu_periods', ['id' => $periode->id]);
    }

    public function test_form_penetapan_menolak_persen_di_luar_nol_seratus(): void
    {
        $this->actingAs($this->admin)
            ->post(route('koperasi.shu.store'), [
                'tahun' => 2026,
                'tanggal_penetapan' => '2027-01-15',
                'sumber' => ShuPeriod::SUMBER_MANUAL,
                'total_shu' => 1_000_000,
                'komponen' => [['nama' => 'Cadangan', 'persen' => 150]],
            ])
            ->assertSessionHasErrors('komponen.0.persen');
    }

    public function test_satu_tahun_hanya_boleh_punya_satu_penetapan(): void
    {
        $this->periode(1_000_000, [['nama' => 'Cadangan', 'persen' => 100, 'ke_anggota' => false, 'dasar' => null]], 2026);

        $this->actingAs($this->admin)
            ->post(route('koperasi.shu.store'), [
                'tahun' => 2026,
                'tanggal_penetapan' => '2027-01-15',
                'sumber' => ShuPeriod::SUMBER_MANUAL,
                'total_shu' => 2_000_000,
                'komponen' => [['nama' => 'Cadangan', 'persen' => 100]],
            ])
            ->assertSessionHasErrors('tahun');
    }

    public function test_tahun_yang_penetapannya_pernah_diarsipkan_bisa_ditetapkan_lagi(): void
    {
        // Unique index pada kolom tahun tidak mempedulikan deleted_at. Tanpa
        // pemeriksaan withTrashed(), tahun yang pernah diarsipkan akan gagal
        // dengan error database, bukan pesan yang bisa dibaca.
        $lama = $this->periode(1_000_000, [['nama' => 'Cadangan', 'persen' => 100, 'ke_anggota' => false, 'dasar' => null]], 2026);
        $lama->delete();

        $this->actingAs($this->admin)
            ->post(route('koperasi.shu.store'), [
                'tahun' => 2026,
                'tanggal_penetapan' => '2027-01-15',
                'sumber' => ShuPeriod::SUMBER_MANUAL,
                'total_shu' => 2_000_000,
                'komponen' => [['nama' => 'Cadangan', 'persen' => 100]],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('shu_periods', ['tahun' => 2026, 'total_shu' => 2_000_000, 'deleted_at' => null]);
        $this->assertSame(1, ShuPeriod::withTrashed()->where('tahun', 2026)->count());
    }

    // ==================================================================
    // D. PENGESAHAN & OTORISASI
    // ==================================================================

    public function test_penetapan_final_terkunci_dari_perubahan(): void
    {
        $periode = $this->periode(1_000_000, [['nama' => 'Cadangan', 'persen' => 100, 'ke_anggota' => false, 'dasar' => null]]);

        $this->actingAs($this->admin)->post(route('koperasi.shu.sahkan', $periode))->assertRedirect();
        $this->assertSame(ShuPeriod::STATUS_FINAL, $periode->fresh()->status);

        $this->actingAs($this->admin)->get(route('koperasi.shu.edit', $periode))->assertForbidden();
        $this->actingAs($this->admin)->post(route('koperasi.shu.susun-ulang', $periode))->assertForbidden();
        $this->actingAs($this->admin)->delete(route('koperasi.shu.destroy', $periode))->assertForbidden();
    }

    public function test_tim_koperasi_menyusun_tapi_hanya_admin_yang_mengesahkan(): void
    {
        $periode = $this->periode(1_000_000, [['nama' => 'Cadangan', 'persen' => 100, 'ke_anggota' => false, 'dasar' => null]]);

        $this->actingAs($this->timKoperasi)->get(route('koperasi.shu.edit', $periode))->assertOk();
        $this->actingAs($this->timKoperasi)->post(route('koperasi.shu.sahkan', $periode))->assertForbidden();

        $this->assertSame(ShuPeriod::STATUS_DRAFT, $periode->fresh()->status);
    }

    public function test_pendamping_tidak_bisa_membuka_modul_shu(): void
    {
        $this->actingAs($this->pendamping)->get(route('koperasi.shu.index'))->assertForbidden();
        $this->actingAs($this->pendamping)->get(route('koperasi.shu.create'))->assertForbidden();
    }

    public function test_pengesahan_tercatat_di_activity_log(): void
    {
        $periode = $this->periode(1_000_000, [['nama' => 'Cadangan', 'persen' => 100, 'ke_anggota' => false, 'dasar' => null]]);

        $this->actingAs($this->admin)->post(route('koperasi.shu.sahkan', $periode));

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'shu_finalized',
            'user_id' => $this->admin->id,
        ]);
    }

    // ==================================================================

    private function user(string $role, string $email): User
    {
        return User::create([
            'name' => $email,
            'email' => $email,
            'password' => bcrypt('rahasia123'),
            'role' => $role,
            'is_active' => true,
        ]);
    }

    private function anggota(string $nomor, string $nama): KoperasiMember
    {
        return KoperasiMember::create([
            'nomor_anggota' => $nomor,
            'nama' => $nama,
            'nomor_hp' => '0800',
            'tanggal_bergabung' => now()->subYear(),
            'status' => 'Aktif',
        ]);
    }

    private function simpan(KoperasiMember $m, string $jenis, int $nominal): MemberSaving
    {
        return MemberSaving::create([
            'koperasi_member_id' => $m->id,
            'jenis_simpanan' => $jenis,
            'tanggal' => now()->toDateString(),
            'nominal' => $nominal,
            'status' => MemberSaving::STATUS_TERCATAT,
        ]);
    }

    private function periode(int $total, array $komponen, ?int $tahun = null): ShuPeriod
    {
        return ShuPeriod::create([
            'tahun' => $tahun ?? now()->year,
            'tanggal_penetapan' => now()->toDateString(),
            'sumber' => ShuPeriod::SUMBER_MANUAL,
            'total_shu' => $total,
            'komponen' => $komponen,
        ]);
    }
}
