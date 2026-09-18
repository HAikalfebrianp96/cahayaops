<?php

namespace Tests\Feature;

use App\Models\Pendamping;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * EMPAT PERAN MASUK, DAN MENDARAT DI TEMPAT YANG BENAR.
 *
 * =====================================================================
 * CELAH YANG DITUTUP
 * =====================================================================
 *
 * Test yang sudah ada menjaga banyak hal: akun nonaktif ditolak, sesi
 * di-invalidate, last_login tercatat, dan matriks hak akses antar modul.
 *
 * Yang TIDAK pernah diuji: apakah masing-masing dari keempat peran benar-
 * benar bisa menempuh POST /login sampai selesai, dan mendarat di dashboard
 * MILIKNYA — bukan sekadar "tidak error".
 *
 * Seluruh peran memakai satu route dashboard yang sama; yang membedakan
 * tampilannya adalah percabangan di controller. Percabangan semacam itu
 * bisa kehilangan satu cabang tanpa menimbulkan galat apa pun — perannya
 * hanya melihat halaman yang salah, atau halaman kosong.
 *
 * Dua hal lain yang juga belum dijaga: user yang sudah dihapus (soft
 * delete) harus ditolak login, dan satu peran tidak boleh melihat isi
 * dashboard peran lain hanya dengan mengetik URL-nya.
 */
class LoginEmpatRoleTest extends TestCase
{
    use RefreshDatabase;

    private const SANDI = 'kata-sandi-uji-123';

    /** Dashboard yang SEHARUSNYA dilihat tiap peran. */
    private const DASHBOARD = [
        'admin' => 'dashboard',
        'pimpinan' => 'dashboard-pimpinan',
        'pendamping' => 'dashboard-pendamping',
        User::ROLE_TIM_KOPERASI => 'dashboard-koperasi',
    ];

    private function akun(string $role): User
    {
        $pendampingId = null;

        if ($role === 'pendamping') {
            $pendampingId = Pendamping::create([
                'nama' => 'Rosa Uji', 'nomor_hp' => '081200000001',
                'email' => 'rosa@login.test', 'status' => 'Aktif',
            ])->id;
        }

        return User::create([
            'name' => 'Akun ' . $role,
            'email' => $role . '@login.test',
            'password' => self::SANDI,
            'role' => $role,
            'pendamping_id' => $pendampingId,
            'is_active' => true,
        ]);
    }

    private function masuk(User $user, string $sandi = self::SANDI)
    {
        return $this->post(route('login'), [
            'email' => $user->email,
            'password' => $sandi,
        ]);
    }

    // =================================================================
    // KEEMPAT PERAN BISA MASUK
    // =================================================================

    public function test_keempat_peran_bisa_login(): void
    {
        foreach (array_keys(self::DASHBOARD) as $role) {
            $user = $this->akun($role);

            $this->masuk($user)
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('dashboard'));

            $this->assertAuthenticatedAs($user, );

            Auth::logout();
        }
    }

    /**
     * Inti persoalannya: mendarat di dashboard MILIKNYA.
     *
     * Satu route, empat tampilan. Cabang yang hilang tidak menimbulkan
     * galat — perannya hanya melihat halaman yang salah.
     */
    public function test_setiap_peran_mendarat_di_dashboard_miliknya(): void
    {
        foreach (self::DASHBOARD as $role => $view) {
            $user = $this->akun($role);

            $this->masuk($user);

            $this->get(route('dashboard'))
                ->assertOk()
                ->assertViewIs($view);

            Auth::logout();
        }
    }

    public function test_login_gagal_dengan_sandi_salah(): void
    {
        $user = $this->akun('admin');

        $this->masuk($user, 'sandi-yang-salah')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    // =================================================================
    // AKUN YANG TIDAK BOLEH MASUK
    // =================================================================

    public function test_akun_nonaktif_ditolak_dengan_pesan_yang_membedakan(): void
    {
        $user = $this->akun('pendamping');
        $user->update(['is_active' => false]);

        $this->masuk($user)->assertSessionHasErrors('email');

        $this->assertGuest();

        // Pesannya sengaja BERBEDA dari "email/password salah": kalau
        // disamakan, pemilik akun akan mengira dirinya lupa sandi dan terus
        // mencoba, padahal masalahnya bukan itu.
        $this->assertStringContainsString(
            'dinonaktifkan',
            session('errors')->first('email')
        );
    }

    /**
     * Akun yang sudah dihapus tidak boleh bisa masuk.
     *
     * User memakai SoftDeletes, jadi barisnya masih ada di database —
     * yang menahannya hanyalah global scope Eloquent. Kalau suatu saat
     * penyedia autentikasinya diganti atau scope-nya dilewati, akun lama
     * bisa hidup kembali tanpa ada yang menyadarinya.
     */
    public function test_akun_terhapus_tidak_bisa_login(): void
    {
        $user = $this->akun('admin');
        $email = $user->email;

        $user->delete();

        $this->assertSoftDeleted('users', ['email' => $email]);

        $this->post(route('login'), [
            'email' => $email,
            'password' => self::SANDI,
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    // =================================================================
    // TIDAK BISA MENYEBERANG LEWAT URL
    // =================================================================

    /**
     * Mengetik URL modul peran lain tidak boleh menembus.
     *
     * Dashboard memang satu route untuk semua, jadi yang diuji di sini
     * halaman KHAS tiap peran.
     */
    public function test_peran_tidak_bisa_membuka_area_peran_lain_lewat_url(): void
    {
        $percobaan = [
            // [peran, rute yang bukan haknya]
            ['pendamping', 'manajemen-user.index'],
            ['pendamping', 'koperasi.anggota.index'],
            ['pendamping', 'impact.index'],
            [User::ROLE_TIM_KOPERASI, 'manajemen-user.index'],
            [User::ROLE_TIM_KOPERASI, 'pengaturan.index'],
            [User::ROLE_TIM_KOPERASI, 'participants.index'],
            ['pimpinan', 'manajemen-user.index'],
            ['pimpinan', 'pengaturan.index'],
        ];

        foreach ($percobaan as [$role, $rute]) {
            $user = $this->akun($role);

            $this->actingAs($user)
                ->get(route($rute))
                ->assertForbidden();

            Auth::logout();
            $user->forceDelete();

            if ($role === 'pendamping') {
                Pendamping::query()->forceDelete();
            }
        }
    }

    /**
     * Kontrol pembanding: Admin memang boleh membuka semuanya.
     *
     * Tanpa ini, seluruh penolakan di atas bisa lulus karena bug yang
     * menolak semua orang di mana-mana.
     */
    public function test_admin_tetap_bisa_membuka_area_administrasi(): void
    {
        $admin = $this->akun('admin');

        foreach (['manajemen-user.index', 'pengaturan.index', 'participants.index'] as $rute) {
            $this->actingAs($admin)->get(route($rute))->assertOk();
        }
    }

    public function test_tamu_diarahkan_ke_halaman_login(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }
}
