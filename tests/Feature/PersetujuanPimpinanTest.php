<?php

namespace Tests\Feature;

use App\Models\ImpactProgram;
use App\Models\SroiCalculation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SATU-SATUNYA TINDAKAN MILIK PIMPINAN: menyetujui hasil SROI.
 *
 * ==========================================================================
 * KENAPA BERKAS INI ADA
 * ==========================================================================
 *
 * Seluruh pekerjaan Pimpinan di CahayaOS adalah MEMBACA — kecuali satu:
 * menetapkan hasil perhitungan SROI sebagai Final. Sampai sekarang tidak ada
 * satu pun tempat yang memberitahunya bahwa ada angka yang menunggu; ia harus
 * membuka Dampak & SROI lalu memeriksa sendiri program per program.
 *
 * Kewenangan yang tidak pernah diingatkan akan berakhir tidak pernah dipakai,
 * dan laporan dampak berhenti di "Terhitung" tanpa ada yang tahu ia sedang
 * menunggu siapa.
 *
 * ==========================================================================
 * KENAPA DIUJI, PADAHAL DATANYA BELUM ADA
 * ==========================================================================
 *
 * Database sungguhan saat ini punya NOL perhitungan SROI, jadi kartunya tidak
 * pernah tampil dan tidak bisa diperiksa dengan mata. Test inilah satu-satunya
 * cara memastikan ia benar-benar muncul pada hari perhitungan pertama dibuat —
 * bukan berbulan-bulan kemudian, saat ada yang bertanya kenapa laporannya
 * tidak pernah difinalkan.
 */
class PersetujuanPimpinanTest extends TestCase
{
    use RefreshDatabase;

    private User $pimpinan;
    private User $admin;
    private User $pendamping;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pimpinan = $this->user('pimpinan', 'pimpinan@setuju.test');
        $this->admin = $this->user('admin', 'admin@setuju.test');
        $this->pendamping = $this->user('pendamping', 'rosa@setuju.test');
    }

    private function user(string $role, string $email): User
    {
        return User::create([
            'name' => $email, 'email' => $email, 'password' => bcrypt('rahasia123'),
            'role' => $role, 'is_active' => true,
        ]);
    }

    private function program(string $nama = 'Program Cahaya Preneur 2026'): ImpactProgram
    {
        return ImpactProgram::create([
            'nama' => $nama,
            'periode_mulai' => now()->startOfYear()->toDateString(),
            'periode_selesai' => now()->endOfYear()->toDateString(),
            'status' => 'Draf',
        ]);
    }

    private function perhitungan(ImpactProgram $p, string $status): SroiCalculation
    {
        return SroiCalculation::create([
            'impact_program_id' => $p->id,
            'status' => $status,
            'total_investasi' => 10_000_000,
            'total_nilai_dampak' => 25_000_000,
            'nilai_sosial_bersih' => 15_000_000,
            'rasio' => 2.5,
            // rincian & sensitivitas wajib: keduanya adalah SNAPSHOT beku
            // dari asumsi yang dipakai saat menghitung — itulah yang membuat
            // hasil SROI lama tidak berubah saat proxy diperbarui.
            'rincian' => [],
            'sensitivitas' => [],
            'dihitung_at' => now(),
        ]);
    }

    // ==================================================================
    // A. KARTU MUNCUL SAAT ADA YANG MENUNGGU
    // ==================================================================

    public function test_perhitungan_terhitung_muncul_di_dashboard_pimpinan(): void
    {
        $p = $this->program();
        $this->perhitungan($p, SroiCalculation::STATUS_TERHITUNG);

        $this->actingAs($this->pimpinan)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Menunggu Persetujuan Anda')
            ->assertSee('Program Cahaya Preneur 2026')
            // Tanpa argumen false: Laravel meng-escape string yang diharapkan,
            // sehingga cocok dengan "&amp;" yang memang dirender Blade.
            ->assertSee('Periksa & Setujui');
    }

    public function test_perhitungan_perlu_review_juga_muncul(): void
    {
        $p = $this->program();
        $this->perhitungan($p, SroiCalculation::STATUS_PERLU_REVIEW);

        $this->actingAs($this->pimpinan)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Menunggu Persetujuan Anda');
    }

    // ==================================================================
    // B. KARTU TIDAK MUNCUL SAAT TIDAK ADA
    // ==================================================================

    public function test_kartu_tersembunyi_kalau_tidak_ada_yang_menunggu(): void
    {
        // Kartu kosong bertuliskan "tidak ada" setiap hari akan berhenti
        // dibaca — dan pada hari ia benar-benar berisi, tidak ada yang
        // menyadarinya.
        $this->actingAs($this->pimpinan)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Menunggu Persetujuan Anda');
    }

    public function test_perhitungan_yang_sudah_final_tidak_menunggu_lagi(): void
    {
        $p = $this->program();
        $this->perhitungan($p, SroiCalculation::STATUS_FINAL);

        $this->actingAs($this->pimpinan)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Menunggu Persetujuan Anda');
    }

    // ==================================================================
    // C. TIDAK MENGUBAH KEWENANGAN SIAPA PUN
    // ==================================================================

    public function test_admin_juga_melihat_karena_ia_juga_boleh_menyetujui(): void
    {
        // ImpactPolicy::setujui mengizinkan Pimpinan ATAU Admin — pada
        // lembaga sekecil ini Pimpinan bisa saja belum punya akun aktif.
        $p = $this->program();
        $this->perhitungan($p, SroiCalculation::STATUS_TERHITUNG);

        $this->actingAs($this->admin)->get(route('dashboard'))->assertOk();

        $this->assertTrue($this->admin->can('setujui', $p));
        $this->assertTrue($this->pimpinan->can('setujui', $p));
        $this->assertFalse($this->pendamping->can('setujui', $p));
    }

    public function test_pimpinan_tetap_tidak_bisa_mengubah_data_operasional(): void
    {
        // Kartu baru ini hanya menampilkan; ia tidak boleh menjadi pintu
        // belakang yang memberi Pimpinan kemampuan menulis.
        $this->actingAs($this->pimpinan)->get(route('koperasi.tugas.index'))->assertForbidden();
        $this->actingAs($this->pimpinan)->get(route('follow-up.index'))->assertForbidden();
    }

    public function test_kartu_menautkan_ke_halaman_program_bukan_tombol_setujui_langsung(): void
    {
        // Menyetujui angka dampak tanpa membukanya dulu bukan persetujuan,
        // hanya klik. Tautannya harus menuju halaman program tempat seluruh
        // asumsinya bisa diperiksa.
        $p = $this->program();
        $this->perhitungan($p, SroiCalculation::STATUS_TERHITUNG);

        $isi = $this->actingAs($this->pimpinan)->get(route('dashboard'))->getContent();

        $this->assertStringContainsString(route('impact.show', $p->id), $isi);
        $this->assertStringNotContainsString(route('impact.setujui', [$p->id, 1]), $isi);
    }
}
