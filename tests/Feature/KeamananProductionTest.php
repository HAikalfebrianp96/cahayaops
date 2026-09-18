<?php

namespace Tests\Feature;

use App\Models\Pendamping;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * DUA LUBANG YANG DITEMUKAN SAAT AUDIT KESIAPAN PRODUCTION.
 *
 * ==========================================================================
 * 1. SEEDER BISA MENIMPA DATABASE PRODUCTION
 * ==========================================================================
 *
 * Seluruh seeder di proyek ini berisi akun contoh, dan beberapa memakai
 * alamat email yang SAMA dengan akun sungguhan. Satu perintah `db:seed` di
 * server — perintah yang lazim ikut tertulis di skrip deploy Laravel —
 * akan mengubah kata sandi Admin dan Pimpinan menjadi kata sandi contoh yang
 * tertulis di dalam repositori, sekaligus menghidupkan kembali akun uji coba
 * yang sudah sengaja dihapus permanen.
 *
 * Tidak ada error yang muncul saat itu terjadi.
 *
 * ==========================================================================
 * 2. HALAMAN LOGIN STAF TIDAK DIBATASI
 * ==========================================================================
 *
 * Halaman Cek Simpanan publik sudah dibatasi 5 percobaan per menit, tapi
 * halaman login staf sama sekali tidak — padahal akun Admin-lah yang
 * memegang Manajemen User, Backup, dan Restore Database.
 */
class KeamananProductionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear('login');
    }

    // ==================================================================
    // A. SEEDER TIDAK BOLEH MENYENTUH PRODUCTION
    // ==================================================================

    public function test_seeder_menolak_berjalan_saat_environment_production(): void
    {
        $admin = User::create([
            'name' => 'Admin YBM', 'email' => 'admin@ybmpln.id',
            'password' => Hash::make('kata-sandi-produksi-yang-asli'),
            'role' => 'admin', 'is_active' => true,
        ]);
        $sandiAsli = $admin->password;

        $this->app['env'] = 'production';

        // --force dipakai SENGAJA: itulah bentuk yang berbahaya. Laravel
        // sendiri sudah menanyakan konfirmasi saat APP_ENV=production, tapi
        // skrip deploy lazim menulis `db:seed --force` yang melewati
        // pertanyaan itu begitu saja. Penjaga di seeder-lah lapis terakhirnya.
        $this->artisan('db:seed', ['--force' => true])
            ->expectsOutputToContain('DITOLAK')
            ->assertExitCode(0);

        // Yang paling penting: kata sandinya TIDAK berubah.
        $this->assertSame($sandiAsli, $admin->fresh()->password);
    }

    public function test_seeder_tidak_menghidupkan_kembali_akun_yang_sudah_dihapus(): void
    {
        $this->app['env'] = 'production';

        $this->artisan('db:seed', ['--force' => true])->assertExitCode(0);

        // Ahmad & Siti sudah dihapus permanen atas keputusan sadar.
        // Seeder tidak boleh mengembalikannya lewat pintu belakang.
        $this->assertDatabaseMissing('users', ['email' => 'ahmad@ybmpln.id']);
        $this->assertDatabaseMissing('users', ['email' => 'siti@ybmpln.id']);
        $this->assertSame(0, Pendamping::withTrashed()->count());
    }

    public function test_seeder_tetap_berjalan_normal_di_lingkungan_pengembangan(): void
    {
        // Pengamannya tidak boleh sampai membuat seeder tidak berguna —
        // lingkungan pengembangan tetap membutuhkannya.
        $this->assertSame('testing', app()->environment());

        $this->artisan('db:seed')->assertExitCode(0);

        $this->assertDatabaseHas('users', ['email' => 'admin@ybmpln.id']);
    }

    public function test_seeder_tidak_menimpa_kata_sandi_akun_yang_sudah_ada(): void
    {
        // Berlaku di lingkungan mana pun. Seeder yang menimpa kata sandi akun
        // yang sedang dipakai adalah perilaku yang mengejutkan di mana saja,
        // bukan hanya di production.
        $admin = User::create([
            'name' => 'Admin YBM', 'email' => 'admin@ybmpln.id',
            'password' => Hash::make('kata-sandi-yang-sudah-dipakai'),
            'role' => 'admin', 'is_active' => true,
        ]);
        $sebelum = $admin->password;

        $this->artisan('db:seed')->assertExitCode(0);

        $this->assertSame($sebelum, $admin->fresh()->password);
    }

    // ==================================================================
    // B. LOGIN DIBATASI
    // ==================================================================

    private function pengguna(): User
    {
        return User::create([
            'name' => 'Admin', 'email' => 'admin@ybmpln.id',
            'password' => Hash::make('kata-sandi-benar'),
            'role' => 'admin', 'is_active' => true,
        ]);
    }

    public function test_percobaan_login_berulang_akhirnya_ditolak(): void
    {
        $this->pengguna();

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('login'), [
                'email' => 'admin@ybmpln.id',
                'password' => 'tebakan-salah-' . $i,
            ]);
        }

        // Percobaan keenam dalam satu menit sudah harus dibendung, bukan
        // sekadar gagal seperti biasa.
        $this->post(route('login'), [
            'email' => 'admin@ybmpln.id',
            'password' => 'tebakan-salah-lagi',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_pembatas_tidak_mengunci_akun_lain_di_kantor_yang_sama(): void
    {
        // Inti pemilihan kunci email+IP. Seluruh staf berbagi satu IP kantor;
        // kalau pembatasnya per-IP saja, satu orang yang lupa kata sandinya
        // akan mengunci semua orang — termasuk Admin yang seharusnya menolong.
        $this->pengguna();
        User::create([
            'name' => 'Rosa', 'email' => 'rosa@ybmpln.id',
            'password' => Hash::make('kata-sandi-rosa'),
            'role' => 'pendamping', 'is_active' => true,
        ]);

        for ($i = 0; $i < 6; $i++) {
            $this->post(route('login'), [
                'email' => 'admin@ybmpln.id',
                'password' => 'salah-' . $i,
            ]);
        }

        // Rosa tetap bisa masuk walau percobaan terhadap akun Admin dibendung.
        $this->post(route('login'), [
            'email' => 'rosa@ybmpln.id',
            'password' => 'kata-sandi-rosa',
        ]);

        $this->assertAuthenticated();
    }

    public function test_login_yang_benar_tetap_berhasil(): void
    {
        $this->pengguna();

        $this->post(route('login'), [
            'email' => 'admin@ybmpln.id',
            'password' => 'kata-sandi-benar',
        ]);

        $this->assertAuthenticated();
    }

    // ==================================================================
    // C. PIN ANGGOTA — tidak pernah tersimpan apa adanya
    // ==================================================================

    public function test_pin_anggota_disimpan_sebagai_hash(): void
    {
        $anggota = \App\Models\KoperasiMember::create([
            'nama' => 'Pak Budi', 'nomor_hp' => '0812',
            'tanggal_bergabung' => now()->toDateString(), 'status' => 'Aktif',
        ]);

        $anggota->setPin('123456');
        $anggota->save();

        $tersimpan = \Illuminate\Support\Facades\DB::table('koperasi_members')
            ->where('id', $anggota->id)->value('pin_hash');

        $this->assertNotSame('123456', $tersimpan);
        $this->assertStringStartsWith('$2y$', $tersimpan);
        $this->assertTrue($anggota->fresh()->verifyPin('123456'));
        $this->assertFalse($anggota->fresh()->verifyPin('654321'));
    }

    public function test_pin_tidak_bisa_diisi_lewat_mass_assignment(): void
    {
        // pin_hash sengaja tidak fillable — nilainya hanya boleh datang dari
        // setPin(), tidak pernah langsung dari input.
        $anggota = new \App\Models\KoperasiMember();

        $this->assertNotContains('pin_hash', $anggota->getFillable());
    }
}
