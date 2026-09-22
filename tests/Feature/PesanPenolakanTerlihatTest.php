<?php

namespace Tests\Feature;

use App\Models\KoperasiMember;
use App\Models\MemberSaving;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PENOLAKAN HARUS SAMPAI KE LAYAR.
 *
 * =====================================================================
 * KEADAAN YANG DITEMUKAN
 * =====================================================================
 *
 * PenghapusanPermanenService sengaja mengembalikan KALIMAT, bukan boolean.
 * Docblocknya menyebut alasannya: operator yang tombolnya menolak berhak
 * tahu APA yang menahannya — "ternyata produk ini pernah terjual" biasanya
 * justru informasi yang dicarinya.
 *
 * Kalimat itu dikirim lewat dua jalan: withErrors(['hapus' => ...]) dan
 * with('error', ...). Yang menampilkannya: 3 halaman, dari 19 controller
 * yang mengirimnya. Di semua halaman lain tombol Hapus Permanen diklik,
 * halamannya dimuat ulang, datanya masih ada, dan tidak ada satu kata pun
 * yang menerangkan kenapa.
 *
 * Itu lebih buruk daripada tombol yang gagal: ia terlihat seperti sistem
 * yang rusak, padahal sistemnya sedang bekerja persis sebagaimana mestinya.
 *
 * =====================================================================
 * PERBAIKANNYA
 * =====================================================================
 *
 * layouts.app menampilkan keduanya, SEKALI, untuk seluruh halaman. Yang
 * ditampilkan hanya kunci tindakan (hapus/restore/akun) — galat isian form
 * tetap urusan @error di sebelah isiannya masing-masing, supaya tidak ada
 * pesan yang muncul dua kali di dua tempat.
 */
class PesanPenolakanTerlihatTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private KoperasiMember $anggota;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin', 'email' => 'admin@pesan.test',
            'password' => bcrypt('rahasia123'), 'role' => User::ROLE_ADMIN, 'is_active' => true,
        ]);

        $this->anggota = KoperasiMember::create([
            'nama' => 'Bu Ani', 'nomor_hp' => '0812',
            'tanggal_bergabung' => '2026-01-01', 'status' => 'Aktif',
        ]);
    }

    /**
     * Inti persoalannya: bukan "session-nya berisi pesan", melainkan
     * pesannya TERBACA di halaman yang dikembalikan.
     */
    public function test_alasan_penolakan_terbaca_di_halaman(): void
    {
        $simpanan = MemberSaving::create([
            'koperasi_member_id' => $this->anggota->id,
            'jenis_simpanan' => 'Simpanan Pokok',
            'tanggal' => '2026-02-01',
            'nominal' => 50_000,
            'status' => MemberSaving::STATUS_TERCATAT,
        ]);

        $this->actingAs($this->admin)
            ->from(route('koperasi.anggota.show', $this->anggota))
            ->delete(route('koperasi.savings.destroy', $simpanan));

        $this->actingAs($this->admin)
            ->get(route('koperasi.anggota.show', $this->anggota))
            ->assertOk()
            ->assertSee('Batalkan dulu', false);
    }

    /** Halaman yang sehat tidak memuat kotak merah tanpa sebab. */
    public function test_tanpa_penolakan_tidak_ada_pesan(): void
    {
        $this->actingAs($this->admin)
            ->get(route('koperasi.anggota.show', $this->anggota))
            ->assertOk()
            ->assertDontSee('Batalkan dulu', false);
    }

    /**
     * Pesan sukses hanya muncul SEKALI.
     *
     * Modul Dampak dan halaman SHU menyertakan partial notifikasi yang dulu
     * menampilkan session('success') lagi, padahal layout sudah
     * menampilkannya — jadi setiap pesan sukses di halaman itu muncul dua
     * kali, bertumpuk.
     */
    public function test_pesan_sukses_tidak_tampil_ganda(): void
    {
        $halaman = $this->actingAs($this->admin)
            ->withSession(['success' => 'Tanda Khusus Pengujian'])
            ->get(route('koperasi.shu.index'))
            ->assertOk();

        $this->assertSame(
            1,
            substr_count($halaman->getContent(), 'Tanda Khusus Pengujian'),
            'Pesan sukses tampil lebih dari sekali di halaman yang sama.'
        );
    }
}
