<?php

namespace Tests\Feature;

use App\Models\KoperasiMember;
use App\Models\MemberSaving;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PANEL "SIMPANAN TERBARU" KETIKA PENYETORNYA BUKAN ANGGOTA AKTIF LAGI.
 *
 * =====================================================================
 * KEADAAN YANG DITEMUKAN
 * =====================================================================
 *
 * Panel ini pernah memakai relasi biasa (koperasiMember) yang kena global
 * scope SoftDeletes, sehingga penyetor yang diarsipkan berubah jadi "—":
 * baris uang tanpa satu pun nama pemilik, persis di halaman yang dibuka Tim
 * Koperasi setiap hari.
 *
 * Arsip anggota kini digantikan kolom status, jadi barisnya tidak pernah
 * lagi hilang dari relasi. Yang tetap diuji di sini: namanya tampil, dan
 * penandanya menerangkan kenapa orang itu tidak ada di daftar anggota.
 *
 * =====================================================================
 * BARIS RIWAYAT, BUKAN DAFTAR ANGGOTA
 * =====================================================================
 *
 * Pembedanya menentukan, dan tidak boleh disamaratakan ke seluruh sistem:
 *
 *   Baris RIWAYAT (setoran yang benar-benar terjadi)
 *       -> namanya bagian dari fakta itu, harus tampil.
 *
 *   Daftar anggota / pilihan / agregasi
 *       -> tetap menyaring yang tidak aktif.
 *
 * Karena itu yang ditampilkan nama BESERTA PENANDA: namanya menjawab
 * "siapa yang menyetor", penandanya menjawab "kenapa dia tidak ada di
 * daftar anggota".
 */
class SimpananTerbaruAnggotaTidakAktifTest extends TestCase
{
    use RefreshDatabase;

    private User $timKoperasi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->timKoperasi = User::create([
            'name' => 'Tim Koperasi',
            'email' => 'koperasi@simpanan.test',
            'password' => bcrypt('rahasia123'),
            'role' => User::ROLE_TIM_KOPERASI,
            'is_active' => true,
        ]);
    }

    private function anggotaBersimpanan(string $nama): KoperasiMember
    {
        $anggota = KoperasiMember::create([
            'nama' => $nama,
            'nomor_hp' => '0812',
            'tanggal_bergabung' => '2026-01-01',
            'status' => 'Aktif',
        ]);

        MemberSaving::create([
            'koperasi_member_id' => $anggota->id,
            'jenis_simpanan' => 'Simpanan Pokok',
            'tanggal' => now()->subDay()->toDateString(),
            'nominal' => 50_000,
            'status' => MemberSaving::STATUS_TERCATAT,
        ]);

        return $anggota;
    }

    private function dashboard()
    {
        return $this->actingAs($this->timKoperasi)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewIs('dashboard-koperasi');
    }

    // =================================================================

    /** Selama anggotanya aktif, tidak ada penanda apa pun. */
    public function test_anggota_aktif_tampil_tanpa_penanda(): void
    {
        $this->anggotaBersimpanan('Bu Ani');

        $this->dashboard()
            ->assertSee('Bu Ani')
            ->assertDontSee('(tidak aktif)');
    }

    public function test_penyetor_tidak_aktif_namanya_tetap_tampil(): void
    {
        $this->anggotaBersimpanan('Bu Sinta')->update(['status' => 'Tidak Aktif']);

        $this->dashboard()
            ->assertSee('Bu Sinta')
            ->assertSee('(tidak aktif)');
    }

    /**
     * Uangnya tetap terhitung, dan pemiliknya tetap bernama.
     *
     * Baris tanpa nama pemilik pada panel yang menampilkan uang adalah
     * gabungan terburuk: angkanya nyata, pertanggungjawabannya hilang.
     */
    public function test_nominalnya_tetap_tampil_bersama_namanya(): void
    {
        $this->anggotaBersimpanan('Bu Sinta')->update(['status' => 'Tidak Aktif']);

        $this->dashboard()
            ->assertSee('Bu Sinta')
            ->assertSee('50.000');
    }

    /**
     * Anggota tidak aktif TIDAK terhitung sebagai anggota aktif.
     *
     * Menampilkan namanya di baris riwayat satu hal; membuatnya kembali
     * masuk hitungan anggota hal lain, dan itu tetap tidak boleh terjadi.
     */
    public function test_anggota_tidak_aktif_tidak_terhitung_sebagai_aktif(): void
    {
        $this->anggotaBersimpanan('Bu Sinta')->update(['status' => 'Tidak Aktif']);

        $stats = $this->dashboard()->viewData('stats');

        // Barisnya TETAP ADA — menonaktifkan bukan menghapus. Yang harus nol
        // adalah hitungan anggota AKTIF; "Total Anggota" memang memuat yang
        // sudah tidak aktif, dan itu benar: mereka pernah dan masih tercatat.
        $this->assertSame(1, KoperasiMember::count());
        $this->assertSame(1, $stats['total_anggota']);
        $this->assertSame(0, $stats['anggota_aktif']);

        // Keterangan simpanan milik anggota tidak aktif tetap berlaku (Phase 35).
        $this->assertSame(50_000.0, $stats['simpanan_anggota_tidak_aktif']);
    }

    /** Entri yang dibatalkan tetap tidak muncul di panel ini. */
    public function test_simpanan_dibatalkan_tetap_tidak_muncul(): void
    {
        $anggota = $this->anggotaBersimpanan('Bu Sinta');

        MemberSaving::where('koperasi_member_id', $anggota->id)
            ->update(['status' => MemberSaving::STATUS_DIBATALKAN]);

        $anggota->update(['status' => 'Tidak Aktif']);

        $this->dashboard()->assertDontSee('(tidak aktif)');
    }
}
