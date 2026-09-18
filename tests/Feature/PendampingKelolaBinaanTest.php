<?php

namespace Tests\Feature;

use App\Models\Participant;
use App\Models\Pendamping;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PENDAMPING BEKERJA LANGSUNG DI LAPANGAN LEWAT AKUNNYA SENDIRI.
 *
 * Yang diuji kelas ini adalah batas kewenangannya, dan SELALU lewat HTTP
 * sungguhan — bukan dengan memanggil helper. Yang menentukan aman atau tidak
 * adalah respons route-nya; menyembunyikan tombol TIDAK PERNAH dihitung
 * sebagai pengaman.
 *
 * Pendamping BOLEH:  lihat/tambah/lihat detail/ubah binaan SENDIRI.
 * Pendamping TIDAK BOLEH:  menyentuh binaan Pendamping lain (lewat URL
 * langsung maupun request manual), menghapus peserta, memindahkan binaan,
 * membuka daftar peserta global, Manajemen User, atau Pengaturan Sistem.
 */
class PendampingKelolaBinaanTest extends TestCase
{
    use RefreshDatabase;

    private Pendamping $profilA;
    private Pendamping $profilB;
    private User $pendampingA;
    private User $pendampingB;
    private User $admin;
    private Participant $binaanA;
    private Participant $binaanB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->profilA = $this->profil('Pendamping A', 'a@pd.test');
        $this->profilB = $this->profil('Pendamping B', 'b@pd.test');

        $this->pendampingA = $this->user('pendamping', 'a@akun.test', $this->profilA->id);
        $this->pendampingB = $this->user('pendamping', 'b@akun.test', $this->profilB->id);
        $this->admin = $this->user('admin', 'admin@akun.test');

        $this->binaanA = $this->peserta('Binaan Milik A', $this->profilA->id);
        $this->binaanB = $this->peserta('Binaan Milik B', $this->profilB->id);
    }

    private function profil(string $nama, string $email): Pendamping
    {
        return Pendamping::create([
            'nama' => $nama, 'nomor_hp' => '08120000', 'email' => $email, 'status' => 'Aktif',
        ]);
    }

    private function user(string $role, string $email, ?int $pendampingId = null): User
    {
        return User::create([
            'name' => 'User ' . $email,
            'email' => $email,
            'password' => 'password123',
            'role' => $role,
            'pendamping_id' => $pendampingId,
            'is_active' => true,
        ]);
    }

    private function peserta(string $nama, ?int $pendampingId): Participant
    {
        return Participant::create([
            'nama_peserta' => $nama,
            'nomor_hp' => '08129999',
            'nama_usaha' => 'Usaha ' . $nama,
            'kategori_usaha' => 'Jasa',
            'tanggal_mulai_program' => '2026-01-10',
            'status' => 'Aktif',
            'pendamping_id' => $pendampingId,
        ]);
    }

    /** Payload lengkap & valid untuk form peserta. */
    private function formPeserta(array $ubah = []): array
    {
        return array_merge([
            'nama_peserta' => 'Peserta Lapangan',
            'nomor_hp' => '081234567890',
            'nama_usaha' => 'Warung Cahaya',
            'kategori_usaha' => 'Makanan & Minuman',
            'tanggal_mulai_program' => '2026-02-01',
            'status' => 'Aktif',
        ], $ubah);
    }

    // =====================================================================
    // 1. MELIHAT DAFTAR BINAAN SENDIRI
    // =====================================================================

    public function test_pendamping_melihat_daftar_binaannya_sendiri(): void
    {
        $this->actingAs($this->pendampingA)
            ->get(route('binaan.index'))
            ->assertOk()
            ->assertSee('Binaan Milik A');
    }

    /** INTI: daftar binaan TIDAK pernah bocor ke binaan Pendamping lain. */
    public function test_daftar_binaan_tidak_memuat_binaan_pendamping_lain(): void
    {
        $this->actingAs($this->pendampingA)
            ->get(route('binaan.index'))
            ->assertOk()
            ->assertDontSee('Binaan Milik B');
    }

    /**
     * Pencarian pun tidak bisa dipakai untuk memunculkan binaan orang lain.
     *
     * Yang diperiksa adalah TAUTAN ke detail peserta B, bukan namanya —
     * karena kata kunci pencarian memang dikembalikan ke kolom input, jadi
     * kemunculan teksnya di sana bukan kebocoran data.
     */
    public function test_pencarian_di_binaan_saya_tetap_terkurung(): void
    {
        $this->actingAs($this->pendampingA)
            ->get(route('binaan.index', ['q' => 'Binaan Milik B']))
            ->assertOk()
            ->assertDontSee(route('participants.show', $this->binaanB), false)
            ->assertSee('Belum ada peserta yang ditugaskan kepada Anda.');
    }

    /** Daftar peserta GLOBAL tetap Admin-only — perilaku lama tidak berubah. */
    public function test_pendamping_tetap_ditolak_di_daftar_peserta_global(): void
    {
        $this->actingAs($this->pendampingA)->get(route('participants.index'))->assertForbidden();
        $this->actingAs($this->pendampingA)->get('/participants')->assertForbidden();
    }

    // =====================================================================
    // 2. MELIHAT DETAIL BINAAN
    // =====================================================================

    public function test_pendamping_boleh_membuka_detail_binaannya(): void
    {
        $this->actingAs($this->pendampingA)
            ->get(route('participants.show', $this->binaanA))
            ->assertOk()
            ->assertSee('Binaan Milik A');
    }

    /** URL langsung ke binaan Pendamping lain TETAP ditolak. */
    public function test_pendamping_ditolak_membuka_detail_binaan_pendamping_lain(): void
    {
        $this->actingAs($this->pendampingA)
            ->get(route('participants.show', $this->binaanB))
            ->assertForbidden();

        // Diuji juga dengan URL harfiah, bukan hanya route().
        $this->actingAs($this->pendampingA)
            ->get('/participants/' . $this->binaanB->id)
            ->assertForbidden();
    }

    // =====================================================================
    // 3. MENAMBAH BINAAN BARU
    // =====================================================================

    public function test_pendamping_boleh_membuka_form_tambah_binaan(): void
    {
        $this->actingAs($this->pendampingA)->get(route('participants.create'))->assertOk();
    }

    public function test_pendamping_bisa_menambah_binaan_dan_otomatis_jadi_miliknya(): void
    {
        $this->actingAs($this->pendampingA)
            ->post(route('participants.store'), $this->formPeserta())
            ->assertSessionHasNoErrors();

        $baru = Participant::where('nama_peserta', 'Peserta Lapangan')->first();

        $this->assertNotNull($baru);
        $this->assertSame($this->profilA->id, $baru->pendamping_id);

        // Dan langsung muncul di daftar binaannya.
        $this->actingAs($this->pendampingA)->get(route('binaan.index'))->assertSee('Peserta Lapangan');
    }

    /**
     * Request manual yang menyisipkan pendamping_id milik orang lain TIDAK
     * berpengaruh — nilainya dipaksa jadi pendamping yang login.
     */
    public function test_pendamping_tidak_bisa_menitipkan_binaan_ke_pendamping_lain(): void
    {
        $this->actingAs($this->pendampingA)
            ->post(route('participants.store'), $this->formPeserta([
                'nama_peserta' => 'Peserta Selundupan',
                'pendamping_id' => $this->profilB->id,
            ]))
            ->assertSessionHasNoErrors();

        $baru = Participant::where('nama_peserta', 'Peserta Selundupan')->first();

        $this->assertSame($this->profilA->id, $baru->pendamping_id, 'pendamping_id dari request tidak boleh dipakai.');
    }

    /** Pendamping tanpa tautan data Pendamping tidak boleh menambah peserta. */
    public function test_pendamping_tanpa_tautan_tidak_boleh_menambah_peserta(): void
    {
        $lepas = $this->user('pendamping', 'lepas@akun.test');

        $this->actingAs($lepas)->get(route('participants.create'))->assertForbidden();
        $this->actingAs($lepas)->post(route('participants.store'), $this->formPeserta())->assertForbidden();

        $this->assertDatabaseMissing('participants', ['nama_peserta' => 'Peserta Lapangan']);
    }

    /** Pimpinan read-only — tidak boleh menambah peserta. */
    public function test_pimpinan_tidak_boleh_menambah_peserta(): void
    {
        $pimpinan = $this->user('pimpinan', 'pimpinan@akun.test');

        $this->actingAs($pimpinan)->get(route('participants.create'))->assertForbidden();
        $this->actingAs($pimpinan)->post(route('participants.store'), $this->formPeserta())->assertForbidden();
    }

    // =====================================================================
    // 4. MENGEDIT BINAAN
    // =====================================================================

    public function test_pendamping_bisa_mengedit_binaannya_sendiri(): void
    {
        $this->actingAs($this->pendampingA)
            ->get(route('participants.edit', $this->binaanA))
            ->assertOk();

        $this->actingAs($this->pendampingA)
            ->put(route('participants.update', $this->binaanA), $this->formPeserta([
                'nama_peserta' => 'Binaan Milik A (Diperbaiki)',
                'nomor_hp' => '081211112222',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame('Binaan Milik A (Diperbaiki)', $this->binaanA->fresh()->nama_peserta);
        $this->assertSame('081211112222', $this->binaanA->fresh()->nomor_hp);
    }

    /** Data baru (kecamatan, produk, dst) benar-benar tersimpan lewat Edit. */
    public function test_edit_memperbarui_data_lengkap_tanpa_membuat_peserta_baru(): void
    {
        $jumlahAwal = Participant::count();

        $this->actingAs($this->pendampingA)
            ->put(route('participants.update', $this->binaanA), $this->formPeserta([
                'nama_peserta' => 'Binaan Milik A',
                'nomor_identitas' => '1771010101010001',
                'email' => 'binaana@umkm.test',
                'alamat' => 'Jl. Merdeka No. 1',
                'kecamatan' => 'Gading Cempaka',
                'kelurahan' => 'Padang Harapan',
                'produk' => 'Keripik pisang',
                'tahun_berdiri' => 2019,
                'status_usaha' => 'Berkembang',
            ]))
            ->assertSessionHasNoErrors();

        $segar = $this->binaanA->fresh();

        $this->assertSame('1771010101010001', $segar->nomor_identitas);
        $this->assertSame('binaana@umkm.test', $segar->email);
        $this->assertSame('Gading Cempaka', $segar->kecamatan);
        $this->assertSame('Padang Harapan', $segar->kelurahan);
        $this->assertSame('Keripik pisang', $segar->produk);
        $this->assertSame(2019, $segar->tahun_berdiri);
        $this->assertSame('Berkembang', $segar->status_usaha);

        // Memperbaiki data TIDAK boleh melahirkan peserta kedua.
        $this->assertSame($jumlahAwal, Participant::count());
    }

    /** INTI: binaan Pendamping lain tertutup rapat, GET maupun PUT. */
    public function test_pendamping_tidak_bisa_mengedit_binaan_pendamping_lain(): void
    {
        $this->actingAs($this->pendampingA)
            ->get(route('participants.edit', $this->binaanB))
            ->assertForbidden();

        $this->actingAs($this->pendampingA)
            ->put(route('participants.update', $this->binaanB), $this->formPeserta([
                'nama_peserta' => 'DIBAJAK',
            ]))
            ->assertForbidden();

        $this->assertSame('Binaan Milik B', $this->binaanB->fresh()->nama_peserta);
    }

    /**
     * Pendamping tidak bisa mengambil alih binaan lewat request manual —
     * pendamping_id dikunci ke nilai yang sudah tersimpan.
     */
    public function test_pendamping_tidak_bisa_memindahkan_binaan_ke_dirinya_sendiri(): void
    {
        $this->actingAs($this->pendampingA)
            ->put(route('participants.update', $this->binaanA), $this->formPeserta([
                'nama_peserta' => 'Binaan Milik A',
                'pendamping_id' => $this->profilB->id,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame(
            $this->profilA->id,
            $this->binaanA->fresh()->pendamping_id,
            'Pendamping tidak boleh memindahkan binaan lewat request manual.',
        );
    }

    /** Juga tidak bisa MELEPAS binaannya sendiri dari pengawasan. */
    public function test_pendamping_tidak_bisa_mengosongkan_pendamping_binaannya(): void
    {
        $this->actingAs($this->pendampingA)
            ->put(route('participants.update', $this->binaanA), $this->formPeserta([
                'nama_peserta' => 'Binaan Milik A',
                'pendamping_id' => '',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame($this->profilA->id, $this->binaanA->fresh()->pendamping_id);
    }

    /** Field Pendamping memang tidak dirender untuk Pendamping. */
    public function test_form_pendamping_tidak_menampilkan_pilihan_pendamping(): void
    {
        $this->actingAs($this->pendampingA)
            ->get(route('participants.edit', $this->binaanA))
            ->assertOk()
            ->assertDontSee('name="pendamping_id"', false);

        $this->actingAs($this->admin)
            ->get(route('participants.edit', $this->binaanA))
            ->assertOk()
            ->assertSee('name="pendamping_id"', false);
    }

    // =====================================================================
    // 5. MENGHAPUS PESERTA — ADMIN SAJA
    // =====================================================================

    public function test_pendamping_tidak_boleh_menghapus_peserta(): void
    {
        $this->actingAs($this->pendampingA)
            ->delete(route('participants.destroy', $this->binaanA))
            ->assertForbidden();

        $this->assertNotSoftDeleted('participants', ['id' => $this->binaanA->id]);
    }

    // =====================================================================
    // 6. ADMIN — KONTROL GLOBAL
    // =====================================================================

    public function test_admin_melihat_seluruh_peserta(): void
    {
        $this->actingAs($this->admin)
            ->get(route('participants.index'))
            ->assertOk()
            ->assertSee('Binaan Milik A')
            ->assertSee('Binaan Milik B');
    }

    public function test_admin_bisa_mengedit_peserta_milik_pendamping_manapun(): void
    {
        foreach ([$this->binaanA, $this->binaanB] as $peserta) {
            $this->actingAs($this->admin)
                ->put(route('participants.update', $peserta), $this->formPeserta([
                    'nama_peserta' => $peserta->nama_peserta . ' (Admin)',
                    'pendamping_id' => $peserta->pendamping_id,
                ]))
                ->assertSessionHasNoErrors();

            $this->assertStringEndsWith('(Admin)', $peserta->fresh()->nama_peserta);
        }
    }

    /** Admin memindahkan binaan dari satu Pendamping ke Pendamping lain. */
    public function test_admin_bisa_memindahkan_binaan_antar_pendamping(): void
    {
        $this->actingAs($this->admin)
            ->put(route('participants.update', $this->binaanA), $this->formPeserta([
                'nama_peserta' => 'Binaan Milik A',
                'pendamping_id' => $this->profilB->id,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame($this->profilB->id, $this->binaanA->fresh()->pendamping_id);

        // Setelah dipindah, Pendamping A kehilangan aksesnya dan B mendapatkannya.
        $this->actingAs($this->pendampingA)->get(route('participants.show', $this->binaanA))->assertForbidden();
        $this->actingAs($this->pendampingB)->get(route('participants.show', $this->binaanA))->assertOk();
    }

    /** Field Pendamping boleh dikosongkan oleh Admin. */
    public function test_admin_bisa_mengosongkan_pendamping_peserta(): void
    {
        $this->actingAs($this->admin)
            ->put(route('participants.update', $this->binaanA), $this->formPeserta([
                'nama_peserta' => 'Binaan Milik A',
                'pendamping_id' => '',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertNull($this->binaanA->fresh()->pendamping_id);
    }

    public function test_admin_bisa_menentukan_pendamping_saat_peserta_dibuat(): void
    {
        $this->actingAs($this->admin)
            ->post(route('participants.store'), $this->formPeserta([
                'nama_peserta' => 'Peserta Baru Admin',
                'pendamping_id' => $this->profilB->id,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame(
            $this->profilB->id,
            Participant::where('nama_peserta', 'Peserta Baru Admin')->first()->pendamping_id,
        );
    }

    /** Seluruh halaman peserta milik Admin benar-benar terbuka. */
    public function test_halaman_peserta_admin_terbuka_semua(): void
    {
        foreach ([
            route('participants.index'),
            route('participants.index', ['arsip' => 1]),
            route('participants.create'),
            route('participants.show', $this->binaanB),
            route('participants.edit', $this->binaanB),
            route('pendampingan.index'),
        ] as $url) {
            $this->actingAs($this->admin)->get($url)->assertOk("Admin seharusnya bisa membuka $url");
        }
    }

    // =====================================================================
    // 7. SOFT DELETE & RESTORE
    // =====================================================================

    public function test_peserta_terhapus_hilang_dari_daftar_dan_tidak_bisa_dibuka(): void
    {
        $this->binaanA->delete();

        $this->actingAs($this->admin)
            ->get(route('participants.index'))
            ->assertOk()
            ->assertDontSee('Binaan Milik A');

        $this->actingAs($this->admin)->get('/participants/' . $this->binaanA->id)->assertNotFound();
        $this->actingAs($this->pendampingA)->get(route('binaan.index'))->assertDontSee('Binaan Milik A');
    }

    // =====================================================================
    // 8. ACTIVITY LOG
    // =====================================================================

    public function test_perubahan_peserta_tercatat_di_activity_log(): void
    {
        $this->actingAs($this->admin)->post(route('participants.store'), $this->formPeserta());
        $baru = Participant::where('nama_peserta', 'Peserta Lapangan')->first();

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'participant_created', 'model_type' => 'Participant', 'model_id' => $baru->id,
        ]);

        $this->actingAs($this->admin)->put(route('participants.update', $baru), $this->formPeserta([
            'nama_peserta' => 'Peserta Lapangan Diubah',
        ]));
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'participant_updated', 'model_id' => $baru->id,
        ]);

        $this->actingAs($this->admin)->delete(route('participants.destroy', $baru));
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'participant_deleted', 'model_id' => $baru->id,
        ]);

    }

    // =====================================================================
    // 9. BATAS KEWENANGAN LAIN TIDAK IKUT TERBUKA
    // =====================================================================

    /**
     * Membuka akses Tambah/Edit peserta TIDAK boleh ikut membuka administrasi
     * sistem. Diuji dengan URL harfiah supaya tidak bergantung pada route().
     */
    public function test_pendamping_tetap_tertutup_dari_administrasi_sistem(): void
    {
        foreach ([
            '/manajemen-user', '/manajemen-user/create', '/backup-database',
            '/backup-database/restore', '/pengaturan-sistem', '/pengaturan',
            '/pendampings', '/participants', '/koperasi/anggota',
            '/koperasi/anggota/pilih-peserta',
        ] as $url) {
            $this->actingAs($this->pendampingA)->get($url)
                ->assertForbidden("URL $url seharusnya tetap ditolak untuk Pendamping.");
        }
    }

    public function test_pendamping_tidak_bisa_mengubah_role_user(): void
    {
        $this->actingAs($this->pendampingA)
            ->put(route('manajemen-user.update', $this->pendampingB), [
                'name' => 'Dibajak', 'email' => $this->pendampingB->email, 'role' => 'admin',
            ])
            ->assertForbidden();

        $this->assertSame('pendamping', $this->pendampingB->fresh()->role);
    }
}
