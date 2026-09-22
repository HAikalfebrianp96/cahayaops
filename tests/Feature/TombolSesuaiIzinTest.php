<?php

namespace Tests\Feature;

use App\Models\BusinessUnit;
use App\Models\OmzetReport;
use App\Models\Participant;
use App\Models\Pendamping;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TOMBOL YANG TERLIHAT HARUS BISA DIKLIK.
 *
 * =====================================================================
 * KEADAAN YANG DITEMUKAN
 * =====================================================================
 *
 * Otorisasi backend-nya benar di semua kasus yang diperiksa. Yang tidak
 * cocok adalah TAMPILANNYA: beberapa halaman merender tombol untuk peran
 * yang backend-nya sudah pasti menolak.
 *
 * Ditemukan dengan menelusuri aplikasi sebagai tiap peran — membuka
 * dashboard, mengumpulkan setiap tautan yang benar-benar dirender, lalu
 * mengetuk semuanya:
 *
 *   Pimpinan -> /koperasi/penjualan/create         (tombol "+ Tambah Penjualan")
 *   Pimpinan -> /omzet/{id}/edit                   (tombol "Edit" di DETAIL omzet)
 *
 * Yang kedua paling jelas menunjukkan persoalannya: DAFTAR omzet sudah
 * membungkus Edit dengan @can sejak awal, halaman DETAIL-nya tidak. Satu
 * aturan, dua tempat, dan hanya satu yang diperbarui.
 *
 * =====================================================================
 * KENAPA DIUJI DARI SISI TAMPILAN
 * =====================================================================
 *
 * Menyembunyikan tombol BUKAN pengaman — pengamannya tetap Policy di
 * backend, dan itu tidak disentuh sama sekali. Tapi tombol yang menolak
 * saat diklik mengajari pengguna bahwa sistemnya rusak, dan pengguna yang
 * percaya sistemnya rusak berhenti mempercayai angkanya juga.
 *
 * Karena itu SETIAP test di bawah memeriksa DUA hal sekaligus: tombolnya
 * tampil/tidak, DAN permintaannya diterima/ditolak. Memeriksa salah satunya
 * saja akan lulus walau keduanya tidak lagi sepakat.
 */
class TombolSesuaiIzinTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $pimpinan;

    private User $pendamping;

    private User $timKoperasi;

    private Participant $binaan;

    private OmzetReport $omzet;

    protected function setUp(): void
    {
        parent::setUp();

        $profil = Pendamping::create([
            'nama' => 'Rosa', 'nomor_hp' => '0812', 'status' => 'Aktif',
        ]);

        $this->admin = $this->user(User::ROLE_ADMIN, 'admin@izin.test');
        $this->pimpinan = $this->user(User::ROLE_PIMPINAN, 'pimpinan@izin.test');
        $this->pendamping = $this->user(User::ROLE_PENDAMPING, 'pendamping@izin.test', $profil->id);
        $this->timKoperasi = $this->user(User::ROLE_TIM_KOPERASI, 'koperasi@izin.test');

        $this->binaan = Participant::create([
            'nama_peserta' => 'Titi Rahmawati', 'nomor_hp' => '0813',
            'nama_usaha' => 'Keripik Pisang', 'kategori_usaha' => 'Makanan',
            'tanggal_mulai_program' => '2026-01-10', 'status' => 'Aktif',
            'pendamping_id' => $profil->id,
        ]);

        $this->omzet = $this->binaan->omzetReports()->create([
            'periode_bulan' => 5, 'periode_tahun' => 2026, 'omzet' => 1_000_000,
        ]);

        BusinessUnit::create([
            'nama' => 'Unit Uji', 'kode' => 'UNIT-IZIN', 'tipe' => 'Barang', 'status' => 'Aktif',
        ]);
    }

    private function user(string $role, string $email, ?int $pendampingId = null): User
    {
        return User::create([
            'name' => 'User ' . $email, 'email' => $email, 'password' => 'password123',
            'role' => $role, 'pendamping_id' => $pendampingId, 'is_active' => true,
        ]);
    }

    // =================================================================
    // DETAIL PESERTA — halaman yang dilaporkan menghasilkan 403
    // =================================================================

    public function test_admin_boleh_lihat_edit_dan_hapus_peserta(): void
    {
        $halaman = $this->actingAs($this->admin)
            ->get(route('participants.show', $this->binaan))
            ->assertOk();

        // Tombolnya tampil...
        $halaman->assertSee('Edit Data Peserta');
        $halaman->assertSee('Hapus Peserta Permanen');

        // ...dan keduanya benar-benar bisa dijalankan.
        $this->actingAs($this->admin)
            ->get(route('participants.edit', $this->binaan))
            ->assertOk();

        $this->actingAs($this->admin)
            ->delete(route('participants.destroy', $this->binaan))
            ->assertRedirect();
    }

    public function test_pendamping_boleh_lihat_dan_edit_binaannya(): void
    {
        $halaman = $this->actingAs($this->pendamping)
            ->get(route('participants.show', $this->binaan))
            ->assertOk();

        $halaman->assertSee('Edit Data Peserta');

        $this->actingAs($this->pendamping)
            ->get(route('participants.edit', $this->binaan))
            ->assertOk();
    }

    /** Menghapus peserta berdampak lintas modul — tetap kewenangan Admin. */
    public function test_pendamping_tidak_boleh_menghapus_peserta(): void
    {
        $this->actingAs($this->pendamping)
            ->get(route('participants.show', $this->binaan))
            ->assertOk()
            // Khusus tombol peserta. Halaman ini juga memuat tombol Hapus
            // Permanen milik omzet & pendampingan — dan yang itu memang boleh
            // dipakai Pendamping atas binaannya sendiri.
            ->assertDontSee('Hapus Peserta Permanen');

        $this->actingAs($this->pendamping)
            ->delete(route('participants.destroy', $this->binaan))
            ->assertForbidden();

        $this->assertDatabaseHas('participants', ['id' => $this->binaan->id]);
    }

    public function test_pendamping_tidak_bisa_menyentuh_peserta_pendamping_lain(): void
    {
        $lain = Pendamping::create(['nama' => 'Sinta', 'nomor_hp' => '0814', 'status' => 'Aktif']);

        $bukanBinaan = Participant::create([
            'nama_peserta' => 'Peserta Lain', 'nomor_hp' => '0815',
            'nama_usaha' => 'Warung Lain', 'kategori_usaha' => 'Jasa',
            'tanggal_mulai_program' => '2026-01-10', 'status' => 'Aktif',
            'pendamping_id' => $lain->id,
        ]);

        $this->actingAs($this->pendamping)
            ->get(route('participants.show', $bukanBinaan))
            ->assertForbidden();

        $this->actingAs($this->pendamping)
            ->get(route('participants.edit', $bukanBinaan))
            ->assertForbidden();
    }

    /** PIMPINAN: boleh membaca, tidak boleh mengubah — dan tombolnya pun tidak ada. */
    public function test_pimpinan_read_only_pada_peserta(): void
    {
        $this->actingAs($this->pimpinan)
            ->get(route('participants.show', $this->binaan))
            ->assertOk()
            ->assertDontSee('Edit Data Peserta')
            ->assertDontSee('Hapus Peserta Permanen');

        $this->actingAs($this->pimpinan)
            ->get(route('participants.edit', $this->binaan))
            ->assertForbidden();

        $this->actingAs($this->pimpinan)
            ->delete(route('participants.destroy', $this->binaan))
            ->assertForbidden();

        $this->assertDatabaseHas('participants', ['id' => $this->binaan->id]);
    }

    /** TIM KOPERASI: tidak mendapat Cahaya Preneur hanya karena punya akun. */
    public function test_tim_koperasi_tidak_mendapat_akses_cahaya_preneur(): void
    {
        foreach ([
            route('participants.index'),
            route('participants.show', $this->binaan),
            route('participants.edit', $this->binaan),
            route('omzet.index'),
        ] as $url) {
            $this->actingAs($this->timKoperasi)->get($url)->assertForbidden();
        }
    }

    /**
     * Penolakannya harus BERBAHASA INDONESIA dan menerangkan keadaannya.
     *
     * Sebelum halaman errors/403 ada, yang muncul "403 | THIS ACTION IS
     * UNAUTHORIZED" — di aplikasi yang seluruh isinya berbahasa Indonesia,
     * tanpa penjelasan dan tanpa jalan kembali. Bagi pengurus yang bukan
     * orang teknis itu terbaca sebagai sistem rusak.
     */
    public function test_penolakan_dijelaskan_dalam_bahasa_indonesia(): void
    {
        $halaman = $this->actingAs($this->timKoperasi)
            ->get(route('participants.show', $this->binaan))
            ->assertForbidden();

        $halaman->assertSee('Halaman ini bukan untuk akun Anda');
        $halaman->assertSee('Kembali ke Dashboard');
        $halaman->assertDontSee('THIS ACTION IS UNAUTHORIZED', false);
    }

    // =================================================================
    // KETIDAKCOCOKAN YANG DITEMUKAN PENELUSURAN
    // =================================================================

    /**
     * DETAIL omzet dulu merender Edit tanpa penjaga, sementara DAFTAR-nya
     * sudah memakai @can. Satu aturan, dua tempat.
     */
    public function test_detail_omzet_tidak_menawarkan_edit_kepada_pimpinan(): void
    {
        $this->actingAs($this->pimpinan)
            ->get(route('omzet.show', $this->omzet))
            ->assertOk()
            ->assertDontSee(route('omzet.edit', $this->omzet), false);

        $this->actingAs($this->pimpinan)
            ->get(route('omzet.edit', $this->omzet))
            ->assertForbidden();
    }

    /** Pembanding: yang berhak tetap melihat tombolnya, dan tombolnya bekerja. */
    public function test_detail_omzet_tetap_menawarkan_edit_kepada_yang_berhak(): void
    {
        foreach ([$this->admin, $this->pendamping] as $user) {
            $this->actingAs($user)
                ->get(route('omzet.show', $this->omzet))
                ->assertOk()
                ->assertSee(route('omzet.edit', $this->omzet), false);

            $this->actingAs($user)
                ->get(route('omzet.edit', $this->omzet))
                ->assertOk();
        }
    }

    public function test_daftar_penjualan_tidak_menawarkan_tambah_kepada_pimpinan(): void
    {
        $this->actingAs($this->pimpinan)
            ->get(route('koperasi.penjualan.index'))
            ->assertOk()
            ->assertDontSee(route('koperasi.penjualan.create'), false);

        $this->actingAs($this->pimpinan)
            ->get(route('koperasi.penjualan.create'))
            ->assertForbidden();
    }

    public function test_daftar_penjualan_tetap_menawarkan_tambah_kepada_pengelola(): void
    {
        foreach ([$this->admin, $this->timKoperasi] as $user) {
            $this->actingAs($user)
                ->get(route('koperasi.penjualan.index'))
                ->assertOk()
                ->assertSee(route('koperasi.penjualan.create'), false);

            $this->actingAs($user)
                ->get(route('koperasi.penjualan.create'))
                ->assertSuccessful();
        }
    }

    // =================================================================
    // KONFIRMASI HARUS MENYATAKAN APA YANG SUNGGUH TERJADI
    // =================================================================

    /**
     * Tombol hapus peserta dulu berbunyi "Hapus" dengan konfirmasi
     * "Pindahkan peserta ini ke arsip? Data tidak hilang dan bisa
     * dipulihkan." — padahal controllernya memanggil hapusDenganCatatan()
     * yang membuang barisnya untuk selamanya.
     *
     * Pengguna diberi tahu datanya bisa kembali, lalu datanya tidak kembali.
     */
    public function test_konfirmasi_hapus_peserta_menyatakan_permanen(): void
    {
        $halaman = $this->actingAs($this->admin)
            ->get(route('participants.show', $this->binaan))
            ->assertOk();

        $halaman->assertSee('Hapus Permanen?', false);
        $halaman->assertDontSee('Pindahkan peserta ini ke arsip', false);
        $halaman->assertDontSee('bisa dipulihkan', false);
    }
}
