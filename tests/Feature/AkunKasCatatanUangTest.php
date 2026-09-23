<?php

namespace Tests\Feature;

use App\Models\AkunKas;
use App\Models\DanaHibahKeluar;
use App\Models\DanaHibahMasuk;
use App\Models\DanaSukarelaKeluar;
use App\Models\DanaSukarelaMasuk;
use App\Models\KoperasiMember;
use App\Models\MemberSaving;
use App\Models\Pengeluaran;
use App\Models\PengeluaranCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * KAS & BANK — TAHAP 3: PENGELUARAN, SIMPANAN, DAN DANA MENYEBUT AKUNNYA.
 *
 * Setiap catatan uang baru harus tahu dari laci/rekening mana uangnya
 * keluar atau ke mana masuknya. Kosong berarti akun bawaan — tidak pernah
 * "hilang" tanpa akun kecuali memang tidak ada akun yang cocok.
 */
class AkunKasCatatanUangTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private AkunKas $kas;
    private AkunKas $bank;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin', 'email' => 'admin@akun.test', 'password' => bcrypt('rahasia123'),
            'role' => User::ROLE_ADMIN, 'is_active' => true,
        ]);
        $this->kas = AkunKas::where('nama', 'Kas Tunai')->firstOrFail();
        $this->bank = AkunKas::where('nama', 'Rekening Bank')->firstOrFail();
    }

    private function pengeluaran(array $ubah = []): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->admin)->post(route('koperasi.pengeluaran.store'), array_merge([
            'tanggal' => '2026-09-10',
            'pengeluaran_category_id' => PengeluaranCategory::where('nama', 'Listrik')->value('id'),
            'nominal' => 150000,
            'metode_pembayaran' => 'Cash',
        ], $ubah));
    }

    private function anggota(): KoperasiMember
    {
        return KoperasiMember::create([
            'nama' => 'Siti', 'nomor_hp' => '0812', 'tanggal_bergabung' => '2026-09-01', 'status' => 'Aktif',
        ]);
    }

    // =================================================================
    // PENGELUARAN
    // =================================================================

    public function test_pengeluaran_cash_otomatis_dari_kas(): void
    {
        $this->pengeluaran()->assertSessionHasNoErrors();

        $this->assertSame($this->kas->id, Pengeluaran::latest('id')->first()->akun_kas_id);
    }

    public function test_pengeluaran_transfer_otomatis_dari_bank(): void
    {
        $this->pengeluaran(['metode_pembayaran' => 'Transfer'])->assertSessionHasNoErrors();

        $this->assertSame($this->bank->id, Pengeluaran::latest('id')->first()->akun_kas_id);
    }

    public function test_pengeluaran_memakai_akun_pilihan(): void
    {
        $this->pengeluaran(['metode_pembayaran' => 'Cash', 'akun_kas_id' => $this->bank->id])->assertSessionHasNoErrors();

        $this->assertSame($this->bank->id, Pengeluaran::latest('id')->first()->akun_kas_id);
    }

    public function test_akun_nonaktif_ditolak_untuk_catatan_baru(): void
    {
        $this->bank->update(['status' => AkunKas::STATUS_TIDAK_AKTIF]);

        $this->pengeluaran(['akun_kas_id' => $this->bank->id])->assertSessionHasErrors('akun_kas_id');
    }

    public function test_perbaikan_pengeluaran_boleh_tetap_di_akun_yang_sudah_nonaktif(): void
    {
        $this->pengeluaran(['akun_kas_id' => $this->bank->id]);
        $p = Pengeluaran::latest('id')->first();
        $this->bank->update(['status' => AkunKas::STATUS_TIDAK_AKTIF]);

        $this->actingAs($this->admin)->put(route('koperasi.pengeluaran.update', $p), [
            'tanggal' => '2026-09-10', 'nominal' => 175000, 'metode_pembayaran' => 'Cash',
            'akun_kas_id' => $this->bank->id,
        ])->assertSessionHasNoErrors();

        $this->assertSame($this->bank->id, $p->fresh()->akun_kas_id);
        $this->assertEquals(175000, (float) $p->fresh()->nominal);
    }

    public function test_form_pengeluaran_menampilkan_pilihan_akun(): void
    {
        $this->actingAs($this->admin)->get(route('koperasi.pengeluaran.create'))
            ->assertOk()->assertSee('Dibayar dari')->assertSee('Rekening Bank (Bank)');
    }

    // =================================================================
    // SIMPANAN
    // =================================================================

    public function test_simpanan_otomatis_ke_kas_dan_pengganti_mewarisi_akun(): void
    {
        $anggota = $this->anggota();

        $this->actingAs($this->admin)->post(route('koperasi.savings.store', $anggota), [
            'jenis_simpanan' => 'Simpanan Pokok', 'tanggal' => '2026-09-01', 'nominal' => 100000,
        ])->assertSessionHasNoErrors();

        $simpanan = MemberSaving::latest('id')->first();
        $this->assertSame($this->kas->id, $simpanan->akun_kas_id);

        // Pindahkan ke bank, lalu koreksi lewat pembatalan + pengganti.
        $simpanan->update(['akun_kas_id' => $this->bank->id]);
        $this->actingAs($this->admin)->post(route('koperasi.savings.batalkan', $simpanan), [
            'alasan_koreksi' => 'Salah nominal', 'buat_pengganti' => 1,
            'nominal_pengganti' => 150000, 'tanggal_pengganti' => '2026-09-01',
        ])->assertSessionHasNoErrors();

        $pengganti = MemberSaving::where('dikoreksi_dari_id', $simpanan->id)->firstOrFail();
        $this->assertSame($this->bank->id, $pengganti->akun_kas_id);
    }

    public function test_simpanan_memakai_akun_pilihan(): void
    {
        $anggota = $this->anggota();

        $this->actingAs($this->admin)->post(route('koperasi.savings.store', $anggota), [
            'jenis_simpanan' => 'Simpanan Wajib', 'tanggal' => '2026-09-01', 'nominal' => 20000,
            'akun_kas_id' => $this->bank->id,
        ])->assertSessionHasNoErrors();

        $this->assertSame($this->bank->id, MemberSaving::latest('id')->first()->akun_kas_id);
    }

    public function test_halaman_anggota_dan_edit_simpanan_menampilkan_pilihan_akun(): void
    {
        $anggota = $this->anggota();
        $simpanan = $anggota->savings()->create([
            'jenis_simpanan' => 'Simpanan Pokok', 'tanggal' => '2026-09-01', 'nominal' => 100000,
            'akun_kas_id' => $this->kas->id,
        ]);

        $this->actingAs($this->admin)->get(route('koperasi.anggota.show', $anggota))
            ->assertOk()->assertSee('Diterima di');
        $this->actingAs($this->admin)->get(route('koperasi.savings.edit', $simpanan))
            ->assertOk()->assertSee('Kas Tunai (Kas)');
    }

    // =================================================================
    // DANA SOSIAL & HIBAH
    // =================================================================

    public function test_keempat_arus_dana_mencatat_akun(): void
    {
        $this->actingAs($this->admin)->post(route('koperasi.dana-sukarela.masuk'), [
            'tanggal' => '2026-09-01', 'nominal' => 50000, 'sumber' => 'Celengan',
        ])->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post(route('koperasi.dana-sukarela.keluar'), [
            'tanggal' => '2026-09-02', 'nominal' => 20000, 'keperluan' => 'Konsumsi rapat',
        ])->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post(route('koperasi.dana-hibah.masuk'), [
            'tanggal' => '2026-09-01', 'nominal' => 5000000, 'sumber' => 'YBM PLN', 'akun_kas_id' => $this->bank->id,
        ])->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post(route('koperasi.dana-hibah.keluar'), [
            'tanggal' => '2026-09-03', 'nominal' => 1000000, 'keperluan' => 'Etalase', 'akun_kas_id' => $this->bank->id,
        ])->assertSessionHasNoErrors();

        $this->assertSame($this->kas->id, DanaSukarelaMasuk::first()->akun_kas_id);
        $this->assertSame($this->kas->id, DanaSukarelaKeluar::first()->akun_kas_id);
        $this->assertSame($this->bank->id, DanaHibahMasuk::first()->akun_kas_id);
        $this->assertSame($this->bank->id, DanaHibahKeluar::first()->akun_kas_id);
    }

    public function test_perbaikan_dana_bisa_memindah_akun(): void
    {
        $baris = DanaHibahMasuk::create([
            'tanggal' => '2026-09-01', 'nominal' => 5000000, 'sumber' => 'YBM PLN', 'akun_kas_id' => $this->kas->id,
        ]);

        $this->actingAs($this->admin)->get(route('koperasi.dana-hibah.edit', ['jenis' => 'masuk', 'id' => $baris->id]))
            ->assertOk()->assertSee('Diterima di');

        $this->actingAs($this->admin)->put(route('koperasi.dana-hibah.perbarui', ['jenis' => 'masuk', 'id' => $baris->id]), [
            'tanggal' => '2026-09-01', 'nominal' => 5000000, 'sumber' => 'YBM PLN', 'akun_kas_id' => $this->bank->id,
        ])->assertSessionHasNoErrors();

        $this->assertSame($this->bank->id, $baris->fresh()->akun_kas_id);
    }

    public function test_halaman_keuangan_menampilkan_pilihan_akun(): void
    {
        $this->actingAs($this->admin)->get(route('koperasi.keuangan.index'))
            ->assertOk()->assertSee('Dibayar dari')->assertSee('Diterima di');
    }

    // =================================================================
    // DATA LAMA & PENGHAPUSAN AKUN
    // =================================================================

    public function test_migrasi_mengisi_akun_data_lama_menurut_metode(): void
    {
        $kategori = PengeluaranCategory::where('nama', 'Listrik')->first();
        $tunai = Pengeluaran::create([
            'tanggal' => '2026-07-15', 'kategori' => 'Listrik', 'pengeluaran_category_id' => $kategori->id,
            'nominal' => 100000, 'metode_pembayaran' => 'Cash',
        ]);
        $transfer = Pengeluaran::create([
            'tanggal' => '2026-07-15', 'kategori' => 'Listrik', 'pengeluaran_category_id' => $kategori->id,
            'nominal' => 100000, 'metode_pembayaran' => 'QRIS',
        ]);
        $dana = DanaSukarelaMasuk::create(['tanggal' => '2026-07-01', 'nominal' => 1000, 'sumber' => 'Celengan']);
        DB::table('pengeluaran')->update(['akun_kas_id' => null]);
        DB::table('dana_sukarela_masuk')->update(['akun_kas_id' => null]);

        $migration = require database_path('migrations/2026_09_23_300001_tambah_akun_kas_ke_catatan_uang.php');
        (new \ReflectionMethod($migration, 'isiDataLama'))->invoke($migration);

        $this->assertSame($this->kas->id, $tunai->fresh()->akun_kas_id);
        $this->assertSame($this->bank->id, $transfer->fresh()->akun_kas_id);
        $this->assertSame($this->kas->id, $dana->fresh()->akun_kas_id);
    }

    public function test_akun_yang_dipakai_pengeluaran_tidak_bisa_dihapus(): void
    {
        $this->pengeluaran(['metode_pembayaran' => 'Transfer']);

        $this->actingAs($this->admin)
            ->from(route('koperasi.kas-bank.index'))
            ->delete(route('koperasi.kas-bank.destroy', $this->bank))
            ->assertSessionHas('error', fn ($pesan) => str_contains($pesan, '1 pengeluaran'));

        $this->assertTrue(AkunKas::whereKey($this->bank->id)->exists());
    }
}
