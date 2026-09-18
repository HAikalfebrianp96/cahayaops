<?php

namespace Tests\Feature;

use App\Models\Pendamping;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Role ke-4: TIM KOPERASI.
 *
 * Pengguna internal yang menangani operasional Koperasi Cahaya. BUKAN Admin.
 *
 * Prinsip yang dijaga kelas ini:
 *
 * 1. isAdmin() TETAP berarti "Admin saja". Kalau ia diperluas menjadi
 *    isAdmin() || isTimKoperasi(), maka Manajemen User, Backup, Restore, dan
 *    Monitoring & Keamanan ikut terbuka - keempatnya memakai guard yang sama.
 *    Perluasan akses karenanya lewat helper terpisah bolehKelolaKoperasi().
 *
 * 2. Role lama tidak boleh kehilangan apa pun. Perilaku Admin, Pimpinan, dan
 *    Pendamping diuji ulang di sini sebagai pembanding.
 *
 * 3. Akses diuji lewat HTTP sungguhan, bukan dengan memanggil helper - yang
 *    menentukan aman atau tidak adalah respons route-nya. Menyembunyikan menu
 *    TIDAK PERNAH dianggap sebagai pengaman.
 */
class RoleTimKoperasiTest extends TestCase
{
    use RefreshDatabase;

    private function buat(string $role, string $email, ?int $pendampingId = null, bool $aktif = true): User
    {
        return User::create([
            'name' => 'User ' . $email,
            'email' => $email,
            'password' => 'password123',
            'role' => $role,
            'pendamping_id' => $pendampingId,
            'is_active' => $aktif,
        ]);
    }

    private function timKoperasi(string $email = 'tk@uji.test'): User
    {
        return $this->buat(User::ROLE_TIM_KOPERASI, $email);
    }

    private function nav(User $user): string
    {
        $html = $this->actingAs($user)->get(route('dashboard'))->getContent();
        $awal = strpos($html, '<nav');

        return substr($html, $awal, strpos($html, '</nav>') - $awal);
    }

    /** Modul Koperasi yang BOLEH dibuka Tim Koperasi. */
    public static function modulKoperasiDiizinkan(): array
    {
        return [
            'Dashboard Koperasi' => ['dashboard'],
            'Anggota' => ['koperasi.anggota.index'],
            'Anggota - tambah' => ['koperasi.anggota.create'],
            'Unit Usaha' => ['koperasi.unit-usaha.index'],
            'Unit Usaha - tambah' => ['koperasi.unit-usaha.create'],
            'Penjualan' => ['koperasi.penjualan.index'],
            'Penjualan - tambah' => ['koperasi.penjualan.create'],
            'Pengeluaran' => ['koperasi.pengeluaran.index'],
            'Pengeluaran - tambah' => ['koperasi.pengeluaran.create'],
            'Kategori Pengeluaran' => ['koperasi.kategori-pengeluaran.index'],
            'Keuangan' => ['koperasi.keuangan.index'],
            'Laporan Koperasi' => ['koperasi.laporan.index'],
            'Laporan - laba rugi' => ['koperasi.laporan.laba-rugi'],
            'Laporan - ringkasan' => ['koperasi.laporan.ringkasan'],
            'Laporan - keanggotaan' => ['koperasi.laporan.keanggotaan'],
            'Laporan - simpanan' => ['koperasi.laporan.simpanan'],
            'Laporan - unit usaha' => ['koperasi.laporan.unit-usaha'],
            'Laporan - dana sosial' => ['koperasi.laporan.dana-sosial'],
            'Ganti password sendiri' => ['account.password.edit'],
        ];
    }

    /**
     * Halaman yang TIDAK BOLEH dibuka Tim Koperasi — seluruh administrasi
     * sistem dan seluruh modul Cahaya Preneur.
     */
    public static function modulDilarang(): array
    {
        return [
            'Manajemen User' => ['manajemen-user.index'],
            'Manajemen User - tambah' => ['manajemen-user.create'],
            'Backup Database' => ['backup-database.index'],
            'Restore Database' => ['backup-database.restore.show'],
            'Monitoring & Keamanan' => ['pengaturan-sistem.index'],
            'Pengaturan' => ['pengaturan.index'],
            'Pengaturan Koperasi' => ['koperasi.pengaturan.index'],
            'Peserta Cahaya Preneur' => ['participants.index'],
            'Peserta - tambah' => ['participants.create'],
            'Pendamping' => ['pendampings.index'],
            'Pendamping - tambah' => ['pendampings.create'],
            'Monitoring Omzet' => ['omzet.index'],
            'Monitoring Pendampingan' => ['pendampingan.index'],
            'Laporan Cahaya Preneur' => ['laporan'],
            'Laporan CP - peserta' => ['laporan.peserta'],
            'Laporan CP - rekap' => ['laporan.rekap'],
            'Laporan CP - unduh semua' => ['laporan.download-semua'],
            'Binaan Saya' => ['binaan.index'],
        ];
    }

    // =====================================================================
    // Definisi role & helper
    // =====================================================================

    public function test_role_options_berisi_empat_role(): void
    {
        $this->assertSame(
            ['admin', 'pimpinan', 'pendamping', 'tim_koperasi'],
            array_keys(User::ROLE_OPTIONS),
        );

        $this->assertSame('Tim Koperasi', User::ROLE_OPTIONS[User::ROLE_TIM_KOPERASI]);
        $this->assertSame('Admin', User::ROLE_OPTIONS['admin']);
        $this->assertSame('Pimpinan', User::ROLE_OPTIONS['pimpinan']);
        $this->assertSame('Pendamping', User::ROLE_OPTIONS['pendamping']);
    }

    public function test_is_tim_koperasi_true_hanya_untuk_role_tim_koperasi(): void
    {
        $this->assertTrue($this->timKoperasi()->isTimKoperasi());

        foreach (['admin', 'pimpinan', 'pendamping'] as $role) {
            $this->assertFalse(
                $this->buat($role, $role . '@cek.test')->isTimKoperasi(),
                "Role $role tidak boleh dianggap Tim Koperasi.",
            );
        }
    }

    /** INTI: Tim Koperasi BUKAN Admin. */
    public function test_tim_koperasi_bukan_admin(): void
    {
        $tk = $this->timKoperasi();

        $this->assertFalse($tk->isAdmin());
        $this->assertFalse($tk->isPimpinan());
        $this->assertFalse($tk->isPendamping());
        $this->assertTrue($tk->isTimKoperasi());
    }

    public function test_boleh_kelola_koperasi_hanya_admin_dan_tim_koperasi(): void
    {
        $this->assertTrue($this->buat('admin', 'a@bk.test')->bolehKelolaKoperasi());
        $this->assertTrue($this->buat('tim_koperasi', 'tk@bk.test')->bolehKelolaKoperasi());
        $this->assertFalse($this->buat('pimpinan', 'p@bk.test')->bolehKelolaKoperasi());
        $this->assertFalse($this->buat('pendamping', 'd@bk.test')->bolehKelolaKoperasi());
    }

    /** Tim Koperasi TIDAK memakai pendamping_id - tidak ada relasi palsu. */
    public function test_tim_koperasi_tidak_butuh_pendamping_id(): void
    {
        $tk = $this->timKoperasi();

        $this->assertNull($tk->pendamping_id);
        $this->actingAs($tk)->get(route('koperasi.anggota.index'))->assertOk();
    }

    // =====================================================================
    // Akses yang DIIZINKAN
    // =====================================================================

    public function test_tim_koperasi_bisa_login(): void
    {
        $this->buat(User::ROLE_TIM_KOPERASI, 'login@tk.test');

        $this->post(route('login'), ['email' => 'login@tk.test', 'password' => 'password123'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticated();
    }

    public function test_tim_koperasi_boleh_membuka_seluruh_modul_koperasi(): void
    {
        $tk = $this->timKoperasi();

        foreach (self::modulKoperasiDiizinkan() as $label => [$rute]) {
            $this->actingAs($tk)->get(route($rute))->assertOk("Tim Koperasi seharusnya boleh: $label");
        }
    }

    public function test_admin_tetap_boleh_membuka_seluruh_modul_koperasi(): void
    {
        $admin = $this->buat('admin', 'admin@tetap.test');

        foreach (self::modulKoperasiDiizinkan() as $label => [$rute]) {
            $this->actingAs($admin)->get(route($rute))->assertOk("Admin kehilangan akses: $label");
        }
    }

    /** Mengubah password sendiri: boleh, dan benar-benar tersimpan. */
    public function test_tim_koperasi_boleh_mengganti_password_sendiri(): void
    {
        $tk = $this->timKoperasi('ganti@tk.test');
        $lama = $tk->password;

        $this->actingAs($tk)->put(route('account.password.update'), [
            'current_password' => 'password123',
            'password' => 'rahasiabaru123',
            'password_confirmation' => 'rahasiabaru123',
        ])->assertSessionHasNoErrors();

        $this->assertNotSame($lama, $tk->fresh()->password);
    }

    // =====================================================================
    // Akses yang DILARANG — backend, bukan sekadar menu
    // =====================================================================

    public function test_tim_koperasi_ditolak_di_seluruh_modul_terlarang(): void
    {
        $tk = $this->timKoperasi();

        foreach (self::modulDilarang() as $label => [$rute]) {
            $this->actingAs($tk)->get(route($rute))->assertForbidden("Tim Koperasi seharusnya DITOLAK: $label");
        }
    }

    /**
     * Penolakan tidak boleh bergantung pada tidak adanya menu: URL diketik
     * langsung pun harus ditolak. Diuji dengan URL harfiah, bukan route().
     */
    public function test_url_langsung_ke_halaman_admin_tetap_ditolak(): void
    {
        $tk = $this->timKoperasi();

        foreach ([
            '/manajemen-user', '/manajemen-user/create', '/backup-database',
            '/backup-database/restore', '/pengaturan-sistem', '/pengaturan',
            '/participants', '/pendampings', '/laporan', '/omzet', '/pendampingan',
        ] as $url) {
            $this->actingAs($tk)->get($url)->assertForbidden("URL langsung $url seharusnya ditolak");
        }
    }

    /** Tim Koperasi tidak boleh menyentuh akun pengguna lain. */
    public function test_tim_koperasi_tidak_boleh_mengelola_akun_pengguna(): void
    {
        $tk = $this->timKoperasi();
        $korban = $this->buat('pendamping', 'korban@uji.test');

        $this->actingAs($tk)->post(route('manajemen-user.store'), [
            'name' => 'Akun Selundupan', 'email' => 'selundup@uji.test',
            'password' => 'rahasia12345', 'password_confirmation' => 'rahasia12345',
            'role' => 'admin',
        ])->assertForbidden();

        $this->actingAs($tk)->put(route('manajemen-user.update', $korban), [
            'name' => 'Diubah', 'email' => $korban->email, 'role' => 'admin',
        ])->assertForbidden();

        $this->actingAs($tk)->post(route('manajemen-user.status', $korban))->assertForbidden();
        $this->actingAs($tk)->post(route('manajemen-user.reset-password', $korban))->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'selundup@uji.test']);
        $this->assertSame('pendamping', $korban->fresh()->role);
        $this->assertTrue($korban->fresh()->isActive());
    }

    /** Memulihkan akun juga tertutup rapat untuk Tim Koperasi. */
    public function test_tim_koperasi_tidak_boleh_memulihkan_akun(): void
    {
        // Bagian "menghapus" dilepas dari test ini karena penghapusan akun
        // sudah tidak ada sama sekali — rutenya pun tidak lagi terdaftar
        // (lihat SiklusAkunUserTest). Yang tersisa untuk dijaga: arah
        // sebaliknya, memulihkan akun nonaktif, sama tertutupnya.
        $tk = $this->timKoperasi();
        $korban = $this->buat('pendamping', 'korban2@uji.test');
        $korban->update(['is_active' => false]);

        $this->actingAs($tk)
            ->post(route('manajemen-user.status', $korban))
            ->assertForbidden();

        // Akunnya tetap nonaktif — bukan cuma tombolnya yang disembunyikan.
        $this->assertFalse($korban->fresh()->isActive());
    }

    /** Tim Koperasi tidak boleh mengubah data Pendamping. */
    public function test_tim_koperasi_tidak_boleh_mengubah_data_pendamping(): void
    {
        $tk = $this->timKoperasi();
        $profil = Pendamping::create([
            'nama' => 'Pendamping Asli', 'nomor_hp' => '0812',
            'email' => 'pd@uji.test', 'status' => 'Aktif',
        ]);

        $this->actingAs($tk)->put(route('pendampings.update', $profil), [
            'nama' => 'Dibajak', 'nomor_hp' => '0812', 'email' => 'pd@uji.test', 'status' => 'Aktif',
        ])->assertForbidden();

        $this->actingAs($tk)->delete(route('pendampings.destroy', $profil))->assertForbidden();

        $this->assertDatabaseHas('pendampings', ['id' => $profil->id, 'nama' => 'Pendamping Asli']);
    }

    /** Pengaturan Koperasi: tidak boleh dibuka maupun diubah. */
    public function test_tim_koperasi_tidak_boleh_mengubah_pengaturan_koperasi(): void
    {
        $tk = $this->timKoperasi();

        $this->actingAs($tk)->post(route('koperasi.pengaturan.update'), [
            'nama' => 'Koperasi Dibajak', 'subtitle' => 'x', 'prefix_invoice' => 'HACK',
        ])->assertForbidden();

        $this->assertDatabaseMissing('koperasi_settings', ['nama' => 'Koperasi Dibajak']);
        $this->assertDatabaseMissing('koperasi_settings', ['prefix_invoice' => 'HACK']);
    }

    public function test_tim_koperasi_tidak_boleh_menyimpan_pengaturan_preneur(): void
    {
        $this->actingAs($this->timKoperasi())->post(route('pengaturan.update'), [
            'nama_sistem' => 'Dibajak', 'nama_lembaga' => 'Dibajak', 'nama_program' => 'Dibajak',
        ])->assertForbidden();

        $this->assertDatabaseMissing('system_settings', ['nama_sistem' => 'Dibajak']);
    }

    /** POST Dana Hibah & Dana Sukarela TETAP Admin-only. */
    public function test_tim_koperasi_tidak_boleh_post_dana_hibah_dan_sukarela(): void
    {
        $tk = $this->timKoperasi();

        foreach ([
            'koperasi.dana-hibah.masuk', 'koperasi.dana-hibah.keluar',
            'koperasi.dana-sukarela.masuk', 'koperasi.dana-sukarela.keluar',
        ] as $rute) {
            $this->actingAs($tk)->post(route($rute), ['nominal' => 50000, 'keterangan' => 'uji'])
                ->assertForbidden("POST $rute seharusnya Admin-only");
        }
    }

    public function test_post_dana_tetap_tertutup_untuk_pimpinan_dan_pendamping(): void
    {
        foreach (['pimpinan' => 'p@dana.test', 'pendamping' => 'd@dana.test'] as $role => $email) {
            $this->actingAs($this->buat($role, $email))
                ->post(route('koperasi.dana-hibah.masuk'), ['nominal' => 1000])
                ->assertForbidden();
        }
    }

    // =====================================================================
    // Role lama TIDAK kehilangan apa pun
    // =====================================================================

    public function test_perilaku_admin_tidak_berubah(): void
    {
        $admin = $this->buat('admin', 'admin@lama.test');

        foreach ([
            'manajemen-user.index', 'backup-database.index', 'pengaturan-sistem.index',
            'pengaturan.index', 'participants.index', 'pendampings.index', 'laporan',
            'koperasi.pengaturan.index', 'omzet.index', 'pendampingan.index',
        ] as $rute) {
            $respons = $this->actingAs($admin)->get(route($rute));
            $this->assertContains(
                $respons->getStatusCode(), [200, 302],
                "Admin kehilangan akses ke $rute (status {$respons->getStatusCode()})",
            );
        }
    }

    public function test_admin_tetap_melihat_kedua_panel_pengaturan(): void
    {
        $respons = $this->actingAs($this->buat('admin', 'admin@set.test'))
            ->get(route('pengaturan.index'));

        $respons->assertOk();
        $respons->assertSee('name="nama_sistem"', false);
        $respons->assertSee('Identitas Koperasi');
        $respons->assertSee('Simpan Pengaturan');
    }

    public function test_perilaku_pimpinan_tidak_berubah(): void
    {
        $pimpinan = $this->buat('pimpinan', 'pimpinan@lama.test');

        $this->actingAs($pimpinan)->get(route('dashboard'))->assertOk();
        $this->actingAs($pimpinan)->get(route('koperasi.penjualan.index'))->assertOk();
        $this->actingAs($pimpinan)->get(route('koperasi.laporan.index'))->assertOk();
        $this->actingAs($pimpinan)->get(route('omzet.index'))->assertOk();

        foreach ([
            'koperasi.anggota.index', 'koperasi.unit-usaha.index', 'koperasi.pengeluaran.index',
            'koperasi.keuangan.index', 'koperasi.dashboard', 'manajemen-user.index',
            'participants.index', 'laporan', 'pengaturan.index',
        ] as $rute) {
            $this->actingAs($pimpinan)->get(route($rute))->assertForbidden("Pimpinan tidak boleh: $rute");
        }
    }

    public function test_perilaku_pendamping_tidak_berubah(): void
    {
        $profil = Pendamping::create([
            'nama' => 'Pendamping Uji', 'nomor_hp' => '0812',
            'email' => 'pd2@uji.test', 'status' => 'Aktif',
        ]);
        $pendamping = $this->buat('pendamping', 'pendamping@lama.test', $profil->id);

        $this->actingAs($pendamping)->get(route('dashboard'))->assertOk();
        $this->actingAs($pendamping)->get(route('binaan.index'))->assertOk();
        $this->actingAs($pendamping)->get(route('laporan'))->assertOk();
        $this->actingAs($pendamping)->get(route('omzet.index'))->assertOk();

        foreach ([
            'koperasi.anggota.index', 'koperasi.penjualan.index', 'koperasi.laporan.index',
            'manajemen-user.index', 'participants.index', 'pengaturan.index',
        ] as $rute) {
            $this->actingAs($pendamping)->get(route($rute))->assertForbidden("Pendamping tidak boleh: $rute");
        }
    }

    public function test_pimpinan_dan_pendamping_tetap_ditolak_di_pengaturan(): void
    {
        foreach (['pimpinan' => 'p@set.test', 'pendamping' => 'd@set.test'] as $role => $email) {
            $this->actingAs($this->buat($role, $email))
                ->get(route('pengaturan.index'))
                ->assertForbidden();
        }
    }

    // =====================================================================
    // Akun nonaktif
    // =====================================================================

    public function test_akun_tim_koperasi_nonaktif_diblokir(): void
    {
        $tk = $this->buat(User::ROLE_TIM_KOPERASI, 'nonaktif@uji.test', null, false);
        $this->assertFalse($tk->isActive());

        $this->post(route('login'), ['email' => 'nonaktif@uji.test', 'password' => 'password123'])
            ->assertSessionHasErrors();
        $this->assertGuest();

        $this->actingAs($tk)->get(route('koperasi.anggota.index'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    // =====================================================================
    // Sidebar — cerminan hak akses, bukan pengaman
    // =====================================================================

    public function test_sidebar_tim_koperasi_hanya_berisi_modul_koperasi(): void
    {
        $nav = $this->nav($this->timKoperasi());

        foreach (['Dashboard Koperasi', 'Anggota', 'Unit Usaha', 'Penjualan',
                  'Pengeluaran', 'Keuangan', 'Laporan'] as $label) {
            $this->assertStringContainsString($label, $nav, "Menu hilang: $label");
        }

        foreach (['Manajemen User', 'Backup Database', 'Monitoring &amp; Keamanan',
                  'Binaan Saya', 'Peserta', 'Pendamping', 'Cahaya Preneur'] as $label) {
            $this->assertStringNotContainsString($label, $nav, "Menu bocor: $label");
        }

        foreach (self::modulDilarang() as [$rute]) {
            $this->assertStringNotContainsString('href="' . route($rute) . '"', $nav);
        }
    }

    /** Setiap menu di sidebar Tim Koperasi harus benar-benar bisa dibuka. */
    public function test_sidebar_tim_koperasi_tidak_punya_tautan_buntu(): void
    {
        $tk = $this->timKoperasi();
        $nav = $this->nav($tk);

        preg_match_all('/<a\s[^>]*href="(http:\/\/localhost[^"#]*)"/', $nav, $cocok);
        $tautan = array_values(array_unique($cocok[1]));
        $this->assertNotEmpty($tautan);

        $buntu = [];
        foreach ($tautan as $url) {
            $status = $this->actingAs($tk)->get($url)->getStatusCode();
            if ($status >= 400) {
                $buntu[] = "$url ($status)";
            }
        }

        $this->assertSame([], $buntu, 'Tautan buntu di sidebar: ' . implode(', ', $buntu));
    }

    /** Admin tidak kehilangan menu administrasinya. */
    public function test_menu_admin_tidak_berubah(): void
    {
        $nav = $this->nav($this->buat('admin', 'admin@menu-set.test'));

        $this->assertStringContainsString('href="' . route('pengaturan.index') . '"', $nav);
        $this->assertStringContainsString('Monitoring &amp; Keamanan', $nav);
        $this->assertStringContainsString('Manajemen User', $nav);
        $this->assertStringContainsString('Backup Database', $nav);
    }

    /**
     * Label role dirender di blok identitas sidebar & topbar - DI LUAR <nav> -
     * jadi yang diperiksa halaman penuh, bukan potongan menu.
     */
    public function test_tim_koperasi_dilabeli_dengan_benar(): void
    {
        $html = $this->actingAs($this->timKoperasi())->get(route('dashboard'))->getContent();

        $this->assertStringContainsString('Tim Koperasi', $html);
        // Dulu ternary label memaksa role non-Admin/Pimpinan menjadi 'Pendamping'.
        $this->assertStringNotContainsString('Pendamping', $html);
    }

    // =====================================================================
    // Dashboard
    // =====================================================================

    /** Tim Koperasi mendarat di panel Koperasi, BUKAN Cahaya Preneur. */
    public function test_dashboard_tim_koperasi_adalah_dashboard_koperasi(): void
    {
        $respons = $this->actingAs($this->timKoperasi())->get(route('dashboard'));

        $respons->assertOk();
        $respons->assertViewHas('modul', 'koperasi');
        $respons->assertDontSee('Dashboard Pimpinan');
    }

    /** Nilai ?modul dari Tim Koperasi diabaikan - tidak bisa menyeberang ekosistem. */
    public function test_tim_koperasi_tidak_bisa_pindah_ke_panel_cahaya_preneur(): void
    {
        foreach (['preneur', 'semua'] as $modul) {
            $this->actingAs($this->timKoperasi('tk.' . $modul . '@uji.test'))
                ->get(route('dashboard', ['modul' => $modul]))
                ->assertOk()
                ->assertViewHas('modul', 'koperasi');
        }
    }

    public function test_dashboard_pimpinan_tidak_berubah(): void
    {
        $this->actingAs($this->buat('pimpinan', 'pimpinan@dash2.test'))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Dashboard Pimpinan')
            ->assertSee('Cahaya Preneur')
            ->assertSee('Koperasi Cahaya');
    }

    // =====================================================================
    // Manajemen User mengenali role baru
    // =====================================================================

    public function test_admin_melihat_pilihan_role_tim_koperasi(): void
    {
        $admin = $this->buat('admin', 'admin@mu.test');

        $this->actingAs($admin)->get(route('manajemen-user.create'))
            ->assertOk()
            ->assertSee('Tim Koperasi');

        $tk = $this->timKoperasi('tk.list@uji.test');

        $this->actingAs($admin)->get(route('manajemen-user.index'))
            ->assertOk()
            ->assertSee('Tim Koperasi');

        $this->actingAs($admin)->get(route('manajemen-user.edit', $tk))
            ->assertOk()
            ->assertSee('Tim Koperasi');
    }

    /** Route yang dipakai role baru semuanya route EXISTING. */
    public function test_tidak_ada_route_baru_yang_dibuat(): void
    {
        foreach (self::modulKoperasiDiizinkan() as [$rute]) {
            $this->assertTrue(Route::has($rute), "Route $rute tidak terdaftar.");
        }
    }
}
