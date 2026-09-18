<?php

namespace Tests\Feature\Uat;

use App\Models\Participant;
use App\Models\Pendamping;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * AKUN PENDAMPING HARUS TERTAUT KE PROFIL PENDAMPING.
 *
 * ==========================================================================
 * MASALAH NYATA YANG DITEMUKAN SAAT PERSIAPAN GO-LIVE
 * ==========================================================================
 *
 * Dua akun ber-role 'pendamping' (Ahmad dan Siti) ada dan aktif, tetapi
 * kolom pendamping_id-nya kosong. Keduanya bisa login — dan hanya itu.
 * Menu Binaan Saya, Follow Up, Prioritas, KPI, Laporan, dan Arsip semuanya
 * disembunyikan, karena controller-nya memang menolak akun pendamping yang
 * tidak tertaut data Pendamping.
 *
 * Perilaku sistemnya BENAR: menyembunyikan menu yang kalau diklik hanya akan
 * menghasilkan 403 jauh lebih baik daripada menampilkannya. Yang salah adalah
 * datanya. Dan karena sistem menanganinya dengan sopan, kesalahan itu tidak
 * pernah memunculkan error apa pun — akunnya hanya tampak "kosong" dan bisa
 * begitu berbulan-bulan tanpa ada yang menyadarinya.
 *
 * Test ini menjaga dua hal: akibatnya benar-benar separah itu (supaya tidak
 * dianggap sepele), dan begitu tautannya dibuat, akunnya langsung berfungsi.
 */
class RelasiAkunPendampingTest extends TestCase
{
    use RefreshDatabase;

    public function test_akun_pendamping_tanpa_profil_kehilangan_seluruh_halaman_kerjanya(): void
    {
        $menggantung = $this->akunPendamping('Ahmad', null);

        // Dashboard tetap terbuka — akunnya sah, hanya belum bisa bekerja.
        $this->actingAs($menggantung)->get(route('dashboard'))->assertOk();

        foreach ([
            'binaan.index',
            'follow-up.index',
            'prioritas.index',
            'kpi-pendamping.index',
            'laporan',
        ] as $halaman) {
            $this->actingAs($menggantung)
                ->get(route($halaman))
                ->assertForbidden();
        }
    }

    public function test_menu_kerja_tidak_ditawarkan_ke_akun_yang_belum_tertaut(): void
    {
        // Menu yang begitu diklik menghasilkan 403 lebih buruk daripada menu
        // yang tidak ada — pengguna akan mengira sistemnya rusak.
        $menggantung = $this->akunPendamping('Siti', null);

        $this->actingAs($menggantung)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee(route('binaan.index'), false)
            ->assertDontSee(route('follow-up.index'), false);
    }

    public function test_begitu_ditautkan_akun_langsung_bisa_bekerja(): void
    {
        $profil = Pendamping::create([
            'nama' => 'Ahmad', 'nomor_hp' => 'BELUM DIISI',
            'email' => 'ahmad@ybmpln.id', 'status' => 'Aktif',
        ]);

        $akun = $this->akunPendamping('Ahmad', $profil->id);

        $this->actingAs($akun)->get(route('binaan.index'))->assertOk();
        $this->actingAs($akun)->get(route('follow-up.index'))->assertOk();
        $this->actingAs($akun)->get(route('kpi-pendamping.index'))->assertOk();

        $this->actingAs($akun)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('binaan.index'), false);
    }

    public function test_pendamping_baru_melihat_daftar_binaan_kosong_bukan_binaan_orang_lain(): void
    {
        // Konsekuensi yang harus dipastikan setelah profil dibuat: akun baru
        // TIDAK ikut melihat 21 binaan milik pendamping lain hanya karena
        // kini punya profil.
        $rosa = Pendamping::create(['nama' => 'Rosa Linda', 'nomor_hp' => '0812', 'email' => 'rosa@ybmpln.id', 'status' => 'Aktif']);
        $ahmadProfil = Pendamping::create(['nama' => 'Ahmad', 'nomor_hp' => 'BELUM DIISI', 'email' => 'ahmad@ybmpln.id', 'status' => 'Aktif']);

        Participant::create([
            'nama_peserta' => 'Binaan Rosa', 'nomor_hp' => '0800',
            'nama_usaha' => 'Keripik', 'kategori_usaha' => 'Kuliner',
            'tanggal_mulai_program' => now()->subYear()->toDateString(),
            'status' => 'Aktif', 'pendamping_id' => $rosa->id,
        ]);

        $ahmad = $this->akunPendamping('Ahmad', $ahmadProfil->id);

        $this->actingAs($ahmad)
            ->get(route('binaan.index'))
            ->assertOk()
            ->assertDontSee('Binaan Rosa');
    }

    public function test_profil_pendamping_boleh_belum_punya_nomor_hp_yang_sah(): void
    {
        // Kolom nomor_hp NOT NULL di database, sementara nomor aslinya bisa
        // saja belum diketahui saat profil dibuat. Penanda yang jelas-jelas
        // bukan nomor telepon lebih aman daripada angka karangan yang tampak
        // sah — angka yang tampak sah bisa benar-benar ditelepon orang.
        $profil = Pendamping::create([
            'nama' => 'Ahmad', 'nomor_hp' => 'BELUM DIISI',
            'email' => 'ahmad@ybmpln.id', 'status' => 'Aktif',
        ]);

        $akun = $this->akunPendamping('Ahmad', $profil->id);

        // Tidak menghalangi pemakaian sistem sama sekali.
        $this->actingAs($akun)->get(route('binaan.index'))->assertOk();

        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin@rel.test',
            'password' => bcrypt('rahasia123'), 'role' => 'admin', 'is_active' => true,
        ]);

        // Dan terlihat jelas sebagai sesuatu yang masih harus diisi.
        $this->actingAs($admin)
            ->get(route('pendampings.index'))
            ->assertOk()
            ->assertSee('BELUM DIISI');
    }

    private function akunPendamping(string $nama, ?int $pendampingId): User
    {
        return User::create([
            'name' => $nama,
            'email' => strtolower($nama) . '@ybmpln.id',
            'password' => bcrypt('rahasia123'),
            'role' => 'pendamping',
            'pendamping_id' => $pendampingId,
            'is_active' => true,
        ]);
    }
}
