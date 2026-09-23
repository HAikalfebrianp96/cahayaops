<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\AkunKas;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * KAS & BANK — TAHAP 1: AKUN DAN SALDO AWAL.
 *
 * Yang dijaga di sini adalah TITIK AWAL perhitungan uang koperasi:
 * saldo awal selalu berlaku per tanggal, perubahannya selalu berjejak,
 * dan akun yang sudah bernilai tidak bisa lenyap diam-diam.
 */
class KasBankAkunTest extends TestCase
{
    use RefreshDatabase;

    private User $timKoperasi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->timKoperasi = $this->user(User::ROLE_TIM_KOPERASI, 'koperasi@kas.test');
    }

    private function user(string $role, string $email): User
    {
        return User::create([
            'name' => 'Akun ' . $role,
            'email' => $email,
            'password' => bcrypt('rahasia123'),
            'role' => $role,
            'is_active' => true,
        ]);
    }

    public function test_migration_menyediakan_dua_akun_bawaan_tanpa_saldo_awal(): void
    {
        $this->assertSame(['Kas Tunai', 'Rekening Bank'], AkunKas::orderBy('nama')->pluck('nama')->all());
        $this->assertFalse(AkunKas::where('nama', 'Kas Tunai')->first()->saldoAwalDitetapkan());
    }

    public function test_admin_dan_tim_koperasi_boleh_membuka_halaman(): void
    {
        $this->actingAs($this->timKoperasi)->get(route('koperasi.kas-bank.index'))
            ->assertOk()
            ->assertSee('Kas Tunai')
            ->assertSee('Belum ditetapkan');

        $this->actingAs($this->user(User::ROLE_ADMIN, 'admin@kas.test'))
            ->get(route('koperasi.kas-bank.index'))->assertOk();

        $this->actingAs($this->timKoperasi)->get(route('koperasi.kas-bank.create'))->assertOk();
        $this->actingAs($this->timKoperasi)
            ->get(route('koperasi.kas-bank.edit', AkunKas::firstOrFail()))->assertOk();
    }

    public function test_pimpinan_dan_pendamping_ditolak(): void
    {
        foreach ([User::ROLE_PIMPINAN, User::ROLE_PENDAMPING] as $role) {
            $this->actingAs($this->user($role, $role . '@kas.test'))
                ->get(route('koperasi.kas-bank.index'))->assertForbidden();
        }
    }

    public function test_tambah_akun_bank_dengan_saldo_awal(): void
    {
        $this->actingAs($this->timKoperasi)->post(route('koperasi.kas-bank.store'), [
            'nama' => 'Bank BRI Koperasi',
            'jenis' => 'Bank',
            'nomor_rekening' => '0123-01-000123-50-1',
            'atas_nama' => 'Koperasi Cahaya',
            'saldo_awal' => 1500000,
            'tanggal_saldo_awal' => '2026-06-01',
        ])->assertRedirect(route('koperasi.kas-bank.index'));

        $akun = AkunKas::where('nama', 'Bank BRI Koperasi')->firstOrFail();
        $this->assertEquals(1500000, (float) $akun->saldo_awal);
        $this->assertSame('2026-06-01', $akun->tanggal_saldo_awal->format('Y-m-d'));
        $this->assertSame($this->timKoperasi->id, $akun->created_by);
        $this->assertTrue(ActivityLog::where('model_type', 'AkunKas')->where('model_id', $akun->id)->exists());
    }

    public function test_saldo_awal_tanpa_tanggal_ditolak(): void
    {
        $this->actingAs($this->timKoperasi)->post(route('koperasi.kas-bank.store'), [
            'nama' => 'Kas Kecil',
            'jenis' => 'Kas',
            'saldo_awal' => 200000,
        ])->assertSessionHasErrors('tanggal_saldo_awal');

        $this->assertFalse(AkunKas::where('nama', 'Kas Kecil')->exists());
    }

    public function test_tanggal_saldo_awal_di_masa_depan_ditolak(): void
    {
        $this->actingAs($this->timKoperasi)->post(route('koperasi.kas-bank.store'), [
            'nama' => 'Kas Kecil',
            'jenis' => 'Kas',
            'saldo_awal' => 200000,
            'tanggal_saldo_awal' => now()->addDay()->format('Y-m-d'),
        ])->assertSessionHasErrors('tanggal_saldo_awal');
    }

    public function test_saldo_awal_negatif_dan_nama_ganda_ditolak(): void
    {
        $this->actingAs($this->timKoperasi)->post(route('koperasi.kas-bank.store'), [
            'nama' => 'Kas Tunai',
            'jenis' => 'Kas',
            'saldo_awal' => -1,
            'tanggal_saldo_awal' => '2026-06-01',
        ])->assertSessionHasErrors(['nama', 'saldo_awal']);
    }

    public function test_akun_kas_tidak_menyimpan_nomor_rekening(): void
    {
        $this->actingAs($this->timKoperasi)->post(route('koperasi.kas-bank.store'), [
            'nama' => 'Kas Kecil',
            'jenis' => 'Kas',
            'nomor_rekening' => '999',
            'atas_nama' => 'Siapa pun',
        ]);

        $akun = AkunKas::where('nama', 'Kas Kecil')->firstOrFail();
        $this->assertNull($akun->nomor_rekening);
        $this->assertNull($akun->atas_nama);
        $this->assertFalse($akun->saldoAwalDitetapkan());
    }

    public function test_perubahan_saldo_awal_tercatat_dengan_nilai_sebelumnya(): void
    {
        $akun = AkunKas::where('nama', 'Kas Tunai')->firstOrFail();

        $this->actingAs($this->timKoperasi)->put(route('koperasi.kas-bank.update', $akun), [
            'nama' => 'Kas Tunai',
            'jenis' => 'Kas',
            'saldo_awal' => 750000,
            'tanggal_saldo_awal' => '2026-06-24',
        ])->assertRedirect(route('koperasi.kas-bank.index'));

        $log = ActivityLog::where('model_type', 'AkunKas')->where('model_id', $akun->id)->latest('id')->firstOrFail();
        $this->assertStringContainsString('SALDO AWAL BERUBAH', $log->description);
        $this->assertStringContainsString('belum ditetapkan', $log->description);
        $this->assertStringContainsString('Rp750.000', $log->description);
    }

    public function test_nonaktifkan_dan_aktifkan_kembali(): void
    {
        $akun = AkunKas::where('nama', 'Rekening Bank')->firstOrFail();

        $this->actingAs($this->timKoperasi)->post(route('koperasi.kas-bank.toggle', $akun));
        $this->assertSame(AkunKas::STATUS_TIDAK_AKTIF, $akun->fresh()->status);

        $this->actingAs($this->timKoperasi)->post(route('koperasi.kas-bank.toggle', $akun));
        $this->assertSame(AkunKas::STATUS_AKTIF, $akun->fresh()->status);
    }

    public function test_akun_bersaldo_awal_tidak_bisa_dihapus_permanen(): void
    {
        $akun = AkunKas::where('nama', 'Kas Tunai')->firstOrFail();
        $akun->update(['saldo_awal' => 500000, 'tanggal_saldo_awal' => '2026-06-24']);

        $this->actingAs($this->timKoperasi)
            ->from(route('koperasi.kas-bank.index'))
            ->delete(route('koperasi.kas-bank.destroy', $akun))
            ->assertSessionHas('error');

        $this->assertTrue(AkunKas::whereKey($akun->id)->exists());
    }

    public function test_akun_tanpa_saldo_awal_bisa_dihapus_permanen_dan_berjejak(): void
    {
        $akun = AkunKas::where('nama', 'Rekening Bank')->firstOrFail();

        $this->actingAs($this->timKoperasi)
            ->delete(route('koperasi.kas-bank.destroy', $akun))
            ->assertRedirect(route('koperasi.kas-bank.index'));

        $this->assertFalse(AkunKas::whereKey($akun->id)->exists());
        $this->assertTrue(
            ActivityLog::where('action', 'permanently_deleted')->where('model_type', 'AkunKas')->exists()
        );
    }
}
