<?php

namespace Tests\Feature\Uat;

use App\Models\BusinessUnit;
use App\Models\ImpactProgram;
use App\Models\KoperasiMember;
use App\Models\Participant;
use App\Models\Pendamping;
use App\Models\ShuPeriod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * UAT — MATRIKS HAK AKSES & PERCOBAAN NAIK PANGKAT.
 *
 * Test otorisasi lain memeriksa bahwa yang BERHAK bisa masuk. Yang ini
 * memeriksa kebalikannya, dan itu sisi yang lebih penting: bahwa yang TIDAK
 * berhak benar-benar ditolak — bukan sekadar tidak melihat menunya.
 *
 * ==========================================================================
 * KENAPA MENYEMBUNYIKAN MENU TIDAK CUKUP
 * ==========================================================================
 *
 * Menu yang disembunyikan hanya menghalangi orang yang mengklik. URL bisa
 * diketik, ditebak dari pola, atau tersisa di riwayat peramban setelah
 * seseorang berganti peran. Karena itu seluruh percobaan di bawah menembak
 * ENDPOINT-nya langsung — termasuk POST, PUT, dan DELETE, yang justru paling
 * jarang diuji padahal paling merusak kalau lolos.
 *
 * Empat batas yang dijaga:
 *   Pendamping    tidak boleh menyentuh administrasi sistem maupun koperasi
 *   Tim Koperasi  tidak boleh menyentuh konfigurasi Cahaya Preneur/Admin
 *   Pimpinan      membaca dan menyetujui, tidak mengubah data operasional
 *   Semua         tidak bisa mengangkat dirinya sendiri jadi Admin
 */
class MatriksHakAksesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $pimpinan;
    private User $pendamping;
    private User $timKoperasi;
    private Participant $peserta;
    private KoperasiMember $anggota;
    private ImpactProgram $program;

    protected function setUp(): void
    {
        parent::setUp();

        $profil = Pendamping::create(['nama' => 'Rosa', 'nomor_hp' => '0812', 'email' => 'r@mtx.test', 'status' => 'Aktif']);

        $this->admin = $this->user('admin', 'admin@mtx.test');
        $this->pimpinan = $this->user('pimpinan', 'pimpinan@mtx.test');
        $this->pendamping = $this->user('pendamping', 'pendamping@mtx.test', $profil->id);
        $this->timKoperasi = $this->user(User::ROLE_TIM_KOPERASI, 'koperasi@mtx.test');

        $this->peserta = Participant::create([
            'nama_peserta' => 'Bu Ani', 'nomor_hp' => '0800',
            'nama_usaha' => 'Keripik', 'kategori_usaha' => 'Kuliner',
            'tanggal_mulai_program' => now()->subYear()->toDateString(),
            'status' => 'Aktif', 'pendamping_id' => $profil->id,
        ]);

        $this->anggota = KoperasiMember::create([
            'nama' => 'Bu Ani', 'nomor_hp' => '0800',
            'tanggal_bergabung' => now()->subYear()->toDateString(), 'status' => 'Aktif',
        ]);

        $this->program = ImpactProgram::create([
            'nama' => 'Program Uji',
            'periode_mulai' => now()->startOfYear()->toDateString(),
            'periode_selesai' => now()->endOfYear()->toDateString(),
            'status' => ImpactProgram::STATUS_DRAFT,
        ]);
    }

    // ==================================================================
    // A. ADMINISTRASI SISTEM — Admin saja
    // ==================================================================

    public function test_hanya_admin_yang_bisa_membuka_administrasi_sistem(): void
    {
        $halaman = [
            'manajemen-user.index',
            'pengaturan-sistem.index',
            'backup-database.index',
            'pengaturan.index',
        ];

        foreach ($halaman as $nama) {
            $this->actingAs($this->admin)->get(route($nama))->assertOk();

            foreach ([$this->pimpinan, $this->pendamping, $this->timKoperasi] as $user) {
                $this->actingAs($user)
                    ->get(route($nama))
                    ->assertForbidden();
            }
        }
    }

    public function test_selain_admin_tidak_bisa_menerbitkan_akun_baru(): void
    {
        foreach ([$this->pimpinan, $this->pendamping, $this->timKoperasi] as $user) {
            $this->actingAs($user)->post(route('manajemen-user.store'), [
                'name' => 'Akun Selundupan',
                'email' => 'selundupan' . $user->id . '@mtx.test',
                'password' => 'rahasia123',
                'password_confirmation' => 'rahasia123',
                'role' => 'admin',
            ])->assertForbidden();
        }

        $this->assertSame(0, User::where('email', 'like', 'selundupan%')->count());
    }

    public function test_tidak_ada_role_yang_bisa_mengangkat_dirinya_jadi_admin(): void
    {
        // Percobaan naik pangkat paling langsung: mengubah role akun sendiri.
        foreach ([$this->pimpinan, $this->pendamping, $this->timKoperasi] as $user) {
            $this->actingAs($user)->put(route('manajemen-user.update', $user), [
                'name' => $user->name,
                'email' => $user->email,
                'role' => 'admin',
            ])->assertForbidden();

            $this->assertNotSame('admin', $user->fresh()->role);
        }
    }

    public function test_selain_admin_tidak_bisa_memulihkan_database(): void
    {
        // Titik paling merusak di seluruh sistem: restore menimpa basis data.
        foreach ([$this->pimpinan, $this->pendamping, $this->timKoperasi] as $user) {
            $this->actingAs($user)->get(route('backup-database.restore.show'))->assertForbidden();
            $this->actingAs($user)->post(route('backup-database.store'))->assertForbidden();
        }
    }

    // ==================================================================
    // B. BATAS PENDAMPING
    // ==================================================================

    public function test_pendamping_tidak_bisa_masuk_modul_koperasi(): void
    {
        $halaman = [
            'koperasi.anggota.index',
            'koperasi.unit-usaha.index',
            'koperasi.penjualan.index',
            'koperasi.pengeluaran.index',
            'koperasi.keuangan.index',
            'koperasi.shu.index',
            'koperasi.laporan.index',
        ];

        foreach ($halaman as $nama) {
            $this->actingAs($this->pendamping)->get(route($nama))->assertForbidden();
        }
    }

    public function test_pendamping_tidak_bisa_menulis_ke_modul_koperasi(): void
    {
        $this->actingAs($this->pendamping)
            ->post(route('koperasi.anggota.store'), [
                'nama' => 'Selundupan', 'nomor_hp' => '0800',
                'tanggal_bergabung' => now()->toDateString(), 'status' => 'Aktif',
            ])
            ->assertForbidden();

        $this->actingAs($this->pendamping)
            ->post(route('koperasi.unit-usaha.store'), [
                'nama' => 'Unit Selundupan', 'kode' => 'XX', 'tipe' => 'Retail', 'status' => 'Aktif',
            ])
            ->assertForbidden();

        $this->assertSame(1, KoperasiMember::count());
        $this->assertSame(0, BusinessUnit::count());
    }

    public function test_pendamping_tidak_bisa_menyentuh_modul_dampak(): void
    {
        $this->actingAs($this->pendamping)->get(route('impact.index'))->assertForbidden();
        $this->actingAs($this->pendamping)->get(route('impact.show', $this->program))->assertForbidden();
        $this->actingAs($this->pendamping)->post(route('impact.hitung', $this->program))->assertForbidden();
        $this->actingAs($this->pendamping)->get(route('impact.proxy.index'))->assertForbidden();
    }

    public function test_pendamping_tidak_bisa_menghapus_peserta(): void
    {
        // Menghapus peserta berdampak lintas modul (omzet, pendampingan,
        // pelatihan, legalitas, HPP, keanggotaan). Pendamping memperbaiki
        // lewat Edit, bukan dengan menghapus lalu membuat ulang.
        $this->actingAs($this->pendamping)
            ->delete(route('participants.destroy', $this->peserta))
            ->assertForbidden();

        $this->assertDatabaseHas('participants', ['id' => $this->peserta->id, 'deleted_at' => null]);
    }

    public function test_pendamping_tidak_bisa_memindahkan_binaan_ke_dirinya(): void
    {
        $profilLain = Pendamping::create(['nama' => 'Budi', 'nomor_hp' => '0813', 'email' => 'b@mtx.test', 'status' => 'Aktif']);

        $binaanOrangLain = Participant::create([
            'nama_peserta' => 'Binaan Budi', 'nomor_hp' => '0800',
            'nama_usaha' => 'Warung', 'kategori_usaha' => 'Kuliner',
            'tanggal_mulai_program' => now()->subYear()->toDateString(),
            'status' => 'Aktif', 'pendamping_id' => $profilLain->id,
        ]);

        $this->actingAs($this->pendamping)
            ->put(route('participants.update', $binaanOrangLain), [
                'nama_peserta' => 'Binaan Budi', 'nomor_hp' => '0800',
                'nama_usaha' => 'Warung', 'kategori_usaha' => 'Kuliner',
                'tanggal_mulai_program' => now()->subYear()->toDateString(),
                'status' => 'Aktif',
                'pendamping_id' => $this->pendamping->pendamping_id,
            ])
            ->assertForbidden();

        $this->assertSame($profilLain->id, $binaanOrangLain->fresh()->pendamping_id);
    }

    // ==================================================================
    // C. BATAS TIM KOPERASI
    // ==================================================================

    public function test_tim_koperasi_tidak_bisa_masuk_modul_cahaya_preneur(): void
    {
        $halaman = [
            'participants.index',
            'pendampings.index',
            'omzet.index',
            'pendampingan.index',
            'follow-up.index',
            'kpi-pendamping.index',
            'laporan',
            'impact.index',
        ];

        foreach ($halaman as $nama) {
            $this->actingAs($this->timKoperasi)->get(route($nama))->assertForbidden();
        }
    }

    public function test_tim_koperasi_tidak_bisa_menulis_data_peserta(): void
    {
        $this->actingAs($this->timKoperasi)
            ->post(route('participants.store'), [
                'nama_peserta' => 'Selundupan', 'nomor_hp' => '0800',
                'nama_usaha' => 'X', 'kategori_usaha' => 'Kuliner',
                'tanggal_mulai_program' => now()->toDateString(), 'status' => 'Aktif',
            ])
            ->assertForbidden();

        $this->assertSame(1, Participant::count());
    }

    public function test_tim_koperasi_boleh_menjalankan_operasional_koperasi(): void
    {
        // Sisi lain dari batas di atas: yang memang menjadi tugasnya harus
        // benar-benar terbuka, bukan ikut tertutup karena guard terlalu luas.
        foreach ([
            'koperasi.anggota.index',
            'koperasi.unit-usaha.index',
            'koperasi.penjualan.index',
            'koperasi.pengeluaran.index',
            'koperasi.shu.index',
            'koperasi.laporan.index',
        ] as $nama) {
            $this->actingAs($this->timKoperasi)->get(route($nama))->assertOk();
        }
    }

    // ==================================================================
    // D. BATAS PIMPINAN — membaca & menyetujui, bukan mengubah
    // ==================================================================

    public function test_pimpinan_membaca_dampak_tapi_tidak_menyusunnya(): void
    {
        $this->actingAs($this->pimpinan)->get(route('impact.index'))->assertOk();
        $this->actingAs($this->pimpinan)->get(route('impact.show', $this->program))->assertOk();
        $this->actingAs($this->pimpinan)->get(route('impact.export-pdf', $this->program))->assertOk();

        $this->actingAs($this->pimpinan)->get(route('impact.create'))->assertForbidden();
        $this->actingAs($this->pimpinan)->post(route('impact.hitung', $this->program))->assertForbidden();
        $this->actingAs($this->pimpinan)->post(route('impact.proxy.store'), [
            'nama' => 'X', 'satuan' => 'y', 'nilai' => 1, 'sumber' => 'z', 'tahun' => now()->year,
        ])->assertForbidden();
    }

    public function test_pimpinan_tidak_bisa_mengubah_data_operasional_peserta(): void
    {
        $this->actingAs($this->pimpinan)
            ->post(route('omzet.store', $this->peserta), [
                'periode_bulan' => 5, 'periode_tahun' => now()->year, 'omzet' => 1_000_000,
            ])
            ->assertForbidden();

        $this->actingAs($this->pimpinan)
            ->post(route('perkembangan.store', $this->peserta), [
                'jenis' => 'Baseline', 'tanggal' => now()->toDateString(),
            ])
            ->assertForbidden();
    }

    public function test_pimpinan_boleh_melihat_detail_peserta_read_only(): void
    {
        $this->actingAs($this->pimpinan)->get(route('participants.show', $this->peserta))->assertOk();
        $this->actingAs($this->pimpinan)->get(route('perkembangan.index', $this->peserta))->assertOk();
        $this->actingAs($this->pimpinan)->get(route('participants.edit', $this->peserta))->assertForbidden();
    }

    // ==================================================================
    // E. SHU — menyusun vs mengesahkan
    // ==================================================================

    public function test_pengesahan_shu_dibatasi_ke_admin(): void
    {
        $periode = ShuPeriod::create([
            'tahun' => now()->year,
            'tanggal_penetapan' => now()->toDateString(),
            'sumber' => ShuPeriod::SUMBER_MANUAL,
            'total_shu' => 1_000_000,
            'komponen' => [['nama' => 'Cadangan', 'persen' => 100, 'ke_anggota' => false, 'dasar' => null]],
        ]);

        // Tim Koperasi menyusun...
        $this->actingAs($this->timKoperasi)->get(route('koperasi.shu.edit', $periode))->assertOk();
        // ...tapi tidak mengesahkan.
        $this->actingAs($this->timKoperasi)->post(route('koperasi.shu.sahkan', $periode))->assertForbidden();

        $this->assertSame(ShuPeriod::STATUS_DRAFT, $periode->fresh()->status);
    }

    // ==================================================================
    // F. TAMU — tidak ada pintu belakang
    // ==================================================================

    public function test_tamu_tanpa_login_ditolak_di_seluruh_modul_internal(): void
    {
        $halaman = [
            'dashboard', 'participants.index', 'omzet.index', 'impact.index',
            'koperasi.anggota.index', 'koperasi.shu.index', 'manajemen-user.index',
            'backup-database.index', 'laporan',
        ];

        foreach ($halaman as $nama) {
            $this->get(route($nama))->assertRedirect(route('login'));
        }
    }

    public function test_akun_nonaktif_tidak_bisa_dipakai_walau_sesinya_masih_ada(): void
    {
        // Menonaktifkan akun harus langsung memutus akses, bukan menunggu
        // sesinya kedaluwarsa sendiri.
        $this->actingAs($this->pendamping)->get(route('dashboard'))->assertOk();

        $this->pendamping->update(['is_active' => false]);

        $this->actingAs($this->pendamping)->get(route('dashboard'))->assertRedirect(route('login'));
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
}
