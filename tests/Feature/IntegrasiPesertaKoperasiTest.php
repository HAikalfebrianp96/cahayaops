<?php

namespace Tests\Feature;

use App\Models\BusinessUnit;
use App\Models\KoperasiMember;
use App\Models\Participant;
use App\Models\Pendamping;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * INTEGRASI CAHAYA PRENEUR <-> KOPERASI CAHAYA.
 *
 * SATU IDENTITAS, SATU SUMBER DATA. Peserta Cahaya Preneur yang masuk
 * koperasi TIDAK melahirkan baris identitas kedua: `koperasi_members` hanya
 * menunjuk ke `participants` lewat `participant_id` dan menyimpan data yang
 * memang milik koperasi (nomor anggota, tanggal bergabung, status, unit
 * usaha, simpanan, keterangan).
 *
 * Keanggotaan koperasi bersifat OPSIONAL di kedua arah:
 *   - peserta Cahaya Preneur BOLEH tidak menjadi anggota koperasi;
 *   - anggota koperasi BOLEH bukan peserta Cahaya Preneur.
 */
class IntegrasiPesertaKoperasiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $timKoperasi;
    private Participant $ahmad;
    private Participant $siti;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->user('admin', 'admin@kop.test');
        $this->timKoperasi = $this->user(User::ROLE_TIM_KOPERASI, 'tk@kop.test');

        $this->ahmad = $this->peserta('Ahmad', 'Warung Ahmad');
        $this->siti = $this->peserta('Siti', 'Katering Siti');
    }

    private function user(string $role, string $email, ?int $pendampingId = null): User
    {
        return User::create([
            'name' => 'User ' . $email, 'email' => $email, 'password' => 'password123',
            'role' => $role, 'pendamping_id' => $pendampingId, 'is_active' => true,
        ]);
    }

    private function peserta(string $nama, string $usaha): Participant
    {
        return Participant::create([
            'nama_peserta' => $nama,
            'nomor_hp' => '0812' . random_int(1000, 9999),
            'email' => strtolower($nama) . '@umkm.test',
            'alamat' => 'Jl. Contoh No. 1',
            'nama_usaha' => $usaha,
            'kategori_usaha' => 'Makanan & Minuman',
            'tanggal_mulai_program' => '2026-01-05',
            'status' => 'Aktif',
        ]);
    }

    private function formAnggota(array $ubah = []): array
    {
        return array_merge([
            'tanggal_bergabung' => '2026-09-01',
            'status' => 'Aktif',
        ], $ubah);
    }

    // =====================================================================
    // F — TAMBAH ANGGOTA DARI DATABASE PESERTA
    // =====================================================================

    public function test_halaman_tambah_anggota_menawarkan_dua_cara(): void
    {
        $this->actingAs($this->timKoperasi)
            ->get(route('koperasi.anggota.create'))
            ->assertOk()
            ->assertSee('Pilih dari Database Peserta')
            ->assertSee('Input Anggota Baru');
    }

    public function test_pencarian_peserta_menampilkan_calon_anggota(): void
    {
        $this->actingAs($this->timKoperasi)
            ->get(route('koperasi.anggota.pilih-peserta', ['q' => 'Ahmad']))
            ->assertOk()
            ->assertSee('Ahmad')
            ->assertSee('Warung Ahmad')
            ->assertDontSee('Katering Siti');
    }

    /** Data dasar peserta ditampilkan supaya TIDAK perlu diinput ulang. */
    public function test_form_dari_peserta_menampilkan_data_dasar_tanpa_input_ulang(): void
    {
        $respons = $this->actingAs($this->timKoperasi)
            ->get(route('koperasi.anggota.create', ['participant_id' => $this->ahmad->id]));

        $respons->assertOk()
            ->assertSee('Data dari Database Peserta')
            ->assertSee('Ahmad')
            ->assertSee('Warung Ahmad')
            ->assertSee($this->ahmad->nomor_hp)
            ->assertSee('ahmad@umkm.test');

        // Identitas TIDAK diminta lagi — yang dikirim cuma penunjuk ke peserta.
        $respons->assertDontSee('name="nama"', false);
        $respons->assertSee('name="participant_id"', false);
    }

    /** INTI H: peserta jadi anggota TANPA baris identitas kedua. */
    public function test_peserta_bisa_dijadikan_anggota_tanpa_duplikasi_data(): void
    {
        $jumlahPesertaAwal = Participant::count();

        $this->actingAs($this->timKoperasi)
            ->post(route('koperasi.anggota.store'), $this->formAnggota([
                'participant_id' => $this->ahmad->id,
            ]))
            ->assertSessionHasNoErrors();

        // TIDAK ada peserta baru yang lahir.
        $this->assertSame($jumlahPesertaAwal, Participant::count());

        // Hanya SATU baris peserta bernama Ahmad, dan satu keanggotaan yang menunjuk ke sana.
        $this->assertSame(1, Participant::where('nama_peserta', 'Ahmad')->count());
        $this->assertSame(1, KoperasiMember::where('participant_id', $this->ahmad->id)->count());

        $anggota = KoperasiMember::where('participant_id', $this->ahmad->id)->first();
        $this->assertSame($this->ahmad->id, $anggota->participant_id);
        $this->assertNotEmpty($anggota->nomor_anggota);
    }

    /** Identitas yang ditampilkan koperasi bersumber dari peserta. */
    public function test_identitas_anggota_mengikuti_data_peserta(): void
    {
        $this->actingAs($this->timKoperasi)
            ->post(route('koperasi.anggota.store'), $this->formAnggota(['participant_id' => $this->ahmad->id]));

        $anggota = KoperasiMember::where('participant_id', $this->ahmad->id)->first();

        // Peserta ganti nomor HP di modul Cahaya Preneur...
        $this->ahmad->update(['nomor_hp' => '081999888777', 'nama_peserta' => 'Ahmad Fauzi']);

        // ...koperasi ikut benar, tanpa input ulang dan tanpa dua angka berbeda.
        $anggota = $anggota->fresh()->load('participant');
        $this->assertSame('Ahmad Fauzi', $anggota->namaTampil());
        $this->assertSame('081999888777', $anggota->nomorHpTampil());
    }

    /** Simpanan awal dicatat lewat member_savings yang SUDAH ADA. */
    public function test_simpanan_awal_tercatat_sebagai_riwayat_simpanan(): void
    {
        $this->actingAs($this->timKoperasi)
            ->post(route('koperasi.anggota.store'), $this->formAnggota([
                'participant_id' => $this->ahmad->id,
                'simpanan_pokok' => 100000,
                'simpanan_wajib' => 25000,
            ]))
            ->assertSessionHasNoErrors();

        $anggota = KoperasiMember::where('participant_id', $this->ahmad->id)->first();

        $this->assertEqualsWithDelta(100000, $anggota->totalSimpananPerJenis('Simpanan Pokok'), 0.01);
        $this->assertEqualsWithDelta(25000, $anggota->totalSimpananPerJenis('Simpanan Wajib'), 0.01);
        $this->assertEqualsWithDelta(125000, $anggota->totalSimpanan(), 0.01);
    }

    /** Jalur "Input Anggota Baru" tetap punya form identitas lengkap. */
    public function test_form_anggota_baru_meminta_identitas_sendiri(): void
    {
        $this->actingAs($this->timKoperasi)
            ->get(route('koperasi.anggota.create', ['dari' => 'baru']))
            ->assertOk()
            ->assertSee('Tambah Anggota Baru')
            ->assertSee('name="nama"', false)
            ->assertSee('name="nomor_hp"', false)
            ->assertSee('name="simpanan_pokok"', false);
    }

    /** Halaman edit terbuka untuk kedua asal-usul anggota. */
    public function test_halaman_edit_terbuka_untuk_anggota_peserta_maupun_mandiri(): void
    {
        $this->actingAs($this->timKoperasi)
            ->post(route('koperasi.anggota.store'), $this->formAnggota(['participant_id' => $this->ahmad->id]));
        $this->actingAs($this->timKoperasi)
            ->post(route('koperasi.anggota.store'), $this->formAnggota([
                'nama' => 'Warga Mandiri', 'nomor_hp' => '081200001111',
            ]));

        $dariPeserta = KoperasiMember::where('participant_id', $this->ahmad->id)->first();
        $mandiri = KoperasiMember::where('nama', 'Warga Mandiri')->first();

        // Anggota dari peserta: identitas ditampilkan, TIDAK bisa diketik ulang.
        $this->actingAs($this->timKoperasi)
            ->get(route('koperasi.anggota.edit', $dariPeserta))
            ->assertOk()
            ->assertSee('Identitas dari Database Peserta');

        // Anggota mandiri: identitasnya memang diisi di modul koperasi.
        $this->actingAs($this->timKoperasi)
            ->get(route('koperasi.anggota.edit', $mandiri))
            ->assertOk()
            ->assertSee('Identitas Anggota')
            ->assertSee('Warga Mandiri');

        // Detail keduanya juga terbuka.
        $this->actingAs($this->timKoperasi)->get(route('koperasi.anggota.show', $dariPeserta))->assertOk();
        $this->actingAs($this->timKoperasi)->get(route('koperasi.anggota.show', $mandiri))->assertOk();

        // Dan arsipnya bisa dibuka tanpa error.
        $this->actingAs($this->timKoperasi)->get(route('koperasi.anggota.index', ['arsip' => 1]))->assertOk();
    }

    /** Laporan keanggotaan memakai nama yang sama dengan Data Peserta. */
    public function test_laporan_keanggotaan_memakai_nama_dari_data_peserta(): void
    {
        $this->actingAs($this->timKoperasi)
            ->post(route('koperasi.anggota.store'), $this->formAnggota(['participant_id' => $this->ahmad->id]));

        $this->ahmad->update(['nama_peserta' => 'Ahmad Fauzi']);

        $this->actingAs($this->timKoperasi)
            ->get(route('koperasi.laporan.keanggotaan'))
            ->assertOk()
            ->assertSee('Ahmad Fauzi');
    }

    public function test_unit_usaha_bisa_dipilih_saat_pendaftaran(): void
    {
        $unit = BusinessUnit::create([
            'nama' => 'Unit Sembako', 'kode' => 'UNIT-SEMBAKO', 'jenis_usaha' => 'Perdagangan',
            'tipe' => 'Barang', 'status' => 'Aktif',
        ]);

        $this->actingAs($this->timKoperasi)
            ->post(route('koperasi.anggota.store'), $this->formAnggota([
                'participant_id' => $this->ahmad->id,
                'unit_usaha_id' => $unit->id,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame($unit->id, KoperasiMember::where('participant_id', $this->ahmad->id)->first()->unit_usaha_id);
    }

    // =====================================================================
    // ANTI-DUPLIKASI
    // =====================================================================

    public function test_satu_peserta_tidak_bisa_didaftarkan_dua_kali(): void
    {
        $this->actingAs($this->timKoperasi)
            ->post(route('koperasi.anggota.store'), $this->formAnggota(['participant_id' => $this->ahmad->id]));

        $this->actingAs($this->timKoperasi)
            ->post(route('koperasi.anggota.store'), $this->formAnggota(['participant_id' => $this->ahmad->id]))
            ->assertSessionHasErrors('participant_id');

        $this->assertSame(1, KoperasiMember::where('participant_id', $this->ahmad->id)->count());
    }

    /** Peserta yang sudah jadi anggota diarahkan balik, bukan didaftar ulang. */
    public function test_form_menolak_peserta_yang_sudah_menjadi_anggota(): void
    {
        $this->actingAs($this->timKoperasi)
            ->post(route('koperasi.anggota.store'), $this->formAnggota(['participant_id' => $this->ahmad->id]));

        $this->actingAs($this->timKoperasi)
            ->get(route('koperasi.anggota.create', ['participant_id' => $this->ahmad->id]))
            ->assertRedirect(route('koperasi.anggota.pilih-peserta'))
            ->assertSessionHas('error');
    }

    public function test_daftar_calon_anggota_menandai_yang_sudah_terdaftar(): void
    {
        $this->actingAs($this->timKoperasi)
            ->post(route('koperasi.anggota.store'), $this->formAnggota(['participant_id' => $this->ahmad->id]));

        $this->actingAs($this->timKoperasi)
            ->get(route('koperasi.anggota.pilih-peserta'))
            ->assertOk()
            ->assertSee('Sudah anggota')
            ->assertSee('Belum anggota');

        // Filter "hanya yang belum jadi anggota" benar-benar menyaring.
        $this->actingAs($this->timKoperasi)
            ->get(route('koperasi.anggota.pilih-peserta', ['belum_anggota' => 1]))
            ->assertOk()
            ->assertSee('Katering Siti')
            ->assertDontSee('Warung Ahmad');
    }

    // =====================================================================
    // E — KEANGGOTAAN OPSIONAL DI KEDUA ARAH
    // =====================================================================

    /** Siti: peserta Cahaya Preneur = Ya, anggota koperasi = Tidak. */
    public function test_peserta_tidak_otomatis_menjadi_anggota_koperasi(): void
    {
        $this->assertFalse($this->siti->isAnggotaKoperasi());
        $this->assertNull($this->siti->koperasiMember);
        $this->assertSame(0, KoperasiMember::count());
    }

    /** Anggota baru: peserta Cahaya Preneur = Tidak, anggota koperasi = Ya. */
    public function test_anggota_koperasi_boleh_bukan_peserta_cahaya_preneur(): void
    {
        $this->actingAs($this->timKoperasi)
            ->post(route('koperasi.anggota.store'), $this->formAnggota([
                'nama' => 'Warga Baru',
                'nomor_hp' => '081277776666',
                'alamat' => 'Jl. Baru No. 9',
            ]))
            ->assertSessionHasNoErrors();

        $anggota = KoperasiMember::where('nama', 'Warga Baru')->first();

        $this->assertNotNull($anggota);
        $this->assertNull($anggota->participant_id);
        $this->assertFalse($anggota->dariPeserta());
        $this->assertSame('Warga Baru', $anggota->namaTampil());

        // Dan TIDAK diam-diam membuat peserta Cahaya Preneur baru.
        $this->assertDatabaseMissing('participants', ['nama_peserta' => 'Warga Baru']);
    }

    /** Anggota mandiri wajib punya identitas sendiri. */
    public function test_anggota_bukan_peserta_wajib_mengisi_identitas(): void
    {
        $this->actingAs($this->timKoperasi)
            ->post(route('koperasi.anggota.store'), $this->formAnggota())
            ->assertSessionHasErrors(['nama', 'nomor_hp']);

        $this->assertSame(0, KoperasiMember::count());
    }

    /** Status keanggotaan tampil di Detail Peserta — dua arah terlihat. */
    public function test_detail_peserta_menampilkan_status_keanggotaan_koperasi(): void
    {
        // Teksnya kini eksplisit "Belum menjadi anggota koperasi" — sesuai
        // permintaan agar keadaan ini terbaca sebagai ajakan bertindak,
        // bukan sekadar label kosong.
        $this->actingAs($this->admin)
            ->get(route('participants.show', $this->siti))
            ->assertOk()
            ->assertSee('Belum menjadi anggota koperasi')
            ->assertSee(route('koperasi.anggota.jadikan', $this->siti), false);

        $this->actingAs($this->timKoperasi)
            ->post(route('koperasi.anggota.store'), $this->formAnggota(['participant_id' => $this->ahmad->id]));

        $this->actingAs($this->admin)
            ->get(route('participants.show', $this->ahmad))
            ->assertOk()
            ->assertSee('Anggota');
    }

    // =====================================================================
    // G — ROLE TIM KOPERASI
    // =====================================================================

    public function test_tim_koperasi_boleh_mengelola_keanggotaan(): void
    {
        $this->actingAs($this->timKoperasi)->get(route('koperasi.anggota.index'))->assertOk();
        $this->actingAs($this->timKoperasi)->get(route('koperasi.anggota.create'))->assertOk();
        $this->actingAs($this->timKoperasi)->get(route('koperasi.anggota.pilih-peserta'))->assertOk();

        $this->actingAs($this->timKoperasi)
            ->post(route('koperasi.anggota.store'), $this->formAnggota(['participant_id' => $this->ahmad->id]))
            ->assertSessionHasNoErrors();

        $anggota = KoperasiMember::first();

        $this->actingAs($this->timKoperasi)->get(route('koperasi.anggota.show', $anggota))->assertOk();
        $this->actingAs($this->timKoperasi)->get(route('koperasi.anggota.edit', $anggota))->assertOk();

        $this->actingAs($this->timKoperasi)
            ->put(route('koperasi.anggota.update', $anggota), $this->formAnggota([
                'participant_id' => $this->ahmad->id,
                'status' => 'Tidak Aktif',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame('Tidak Aktif', $anggota->fresh()->status);
    }

    /**
     * Melihat database peserta untuk memilih calon anggota TIDAK sama dengan
     * membuka modul Cahaya Preneur. Batasnya tetap utuh.
     */
    public function test_tim_koperasi_tetap_tertutup_dari_modul_cahaya_preneur(): void
    {
        foreach ([
            '/participants', '/participants/create', '/pendampings', '/laporan',
            '/omzet', '/pendampingan', '/binaan-saya',
            '/manajemen-user', '/pengaturan', '/pengaturan-sistem', '/backup-database',
        ] as $url) {
            $this->actingAs($this->timKoperasi)->get($url)
                ->assertForbidden("URL $url seharusnya tetap ditolak untuk Tim Koperasi.");
        }

        // Termasuk Detail Peserta dan pengubahan datanya.
        $this->actingAs($this->timKoperasi)
            ->get(route('participants.show', $this->ahmad))
            ->assertForbidden();

        $this->actingAs($this->timKoperasi)
            ->put(route('participants.update', $this->ahmad), [
                'nama_peserta' => 'DIBAJAK', 'nomor_hp' => '0812', 'nama_usaha' => 'x',
                'kategori_usaha' => 'Jasa', 'tanggal_mulai_program' => '2026-01-01', 'status' => 'Aktif',
            ])
            ->assertForbidden();

        $this->assertSame('Ahmad', $this->ahmad->fresh()->nama_peserta);
    }

    /** Role yang tidak berhak tetap tidak bisa menyentuh modul keanggotaan. */
    public function test_pimpinan_dan_pendamping_tetap_ditolak_di_modul_keanggotaan(): void
    {
        $profil = Pendamping::create(['nama' => 'PD', 'nomor_hp' => '0812', 'email' => 'pd@kop.test', 'status' => 'Aktif']);
        $pendamping = $this->user('pendamping', 'pd.akun@kop.test', $profil->id);
        $pimpinan = $this->user('pimpinan', 'pimpinan@kop.test');

        foreach ([$pendamping, $pimpinan] as $user) {
            $this->actingAs($user)->get(route('koperasi.anggota.index'))->assertForbidden();
            $this->actingAs($user)->get(route('koperasi.anggota.pilih-peserta'))->assertForbidden();
            $this->actingAs($user)
                ->post(route('koperasi.anggota.store'), $this->formAnggota(['nama' => 'X', 'nomor_hp' => '0812']))
                ->assertForbidden();
        }

        $this->assertSame(0, KoperasiMember::count());
    }

    // =====================================================================
    // SOFT DELETE & RESTORE KEANGGOTAAN
    // =====================================================================

    public function test_anggota_dihapus_memakai_soft_delete_dan_bisa_dipulihkan(): void
    {
        $this->actingAs($this->timKoperasi)
            ->post(route('koperasi.anggota.store'), $this->formAnggota([
                'participant_id' => $this->ahmad->id,
                'simpanan_pokok' => 50000,
            ]));

        $anggota = KoperasiMember::first();

        $this->actingAs($this->timKoperasi)
            ->delete(route('koperasi.anggota.destroy', $anggota))
            ->assertRedirect(route('koperasi.anggota.index'));

        $this->assertSoftDeleted('koperasi_members', ['id' => $anggota->id]);

        // Identitas pesertanya SAMA SEKALI tidak tersentuh.
        $this->assertNotSoftDeleted('participants', ['id' => $this->ahmad->id]);
        // Riwayat simpanannya tetap utuh.
        $this->assertDatabaseHas('member_savings', ['koperasi_member_id' => $anggota->id]);

        $this->actingAs($this->timKoperasi)
            ->post(route('koperasi.anggota.restore', $anggota->id))
            ->assertRedirect(route('koperasi.anggota.index'));

        $this->assertNotSoftDeleted('koperasi_members', ['id' => $anggota->id]);
        $this->assertEqualsWithDelta(50000, $anggota->fresh()->totalSimpanan(), 0.01);
    }

    /** Setelah keanggotaan diarsipkan, pesertanya boleh didaftarkan lagi. */
    public function test_peserta_bisa_didaftarkan_lagi_setelah_keanggotaan_diarsipkan(): void
    {
        $this->actingAs($this->timKoperasi)
            ->post(route('koperasi.anggota.store'), $this->formAnggota(['participant_id' => $this->ahmad->id]));

        KoperasiMember::first()->delete();

        $this->actingAs($this->timKoperasi)
            ->post(route('koperasi.anggota.store'), $this->formAnggota(['participant_id' => $this->ahmad->id]))
            ->assertSessionHasNoErrors();

        $this->assertSame(1, KoperasiMember::where('participant_id', $this->ahmad->id)->count());
        $this->assertSame(2, KoperasiMember::withTrashed()->where('participant_id', $this->ahmad->id)->count());
    }

    /** Nomor anggota tidak pernah terulang meski ada anggota yang diarsipkan. */
    public function test_nomor_anggota_tidak_bentrok_setelah_ada_yang_dihapus(): void
    {
        $this->actingAs($this->timKoperasi)
            ->post(route('koperasi.anggota.store'), $this->formAnggota(['participant_id' => $this->ahmad->id]));

        $pertama = KoperasiMember::first();
        $pertama->delete();

        $this->actingAs($this->timKoperasi)
            ->post(route('koperasi.anggota.store'), $this->formAnggota(['participant_id' => $this->siti->id]))
            ->assertSessionHasNoErrors();

        $kedua = KoperasiMember::where('participant_id', $this->siti->id)->first();

        $this->assertNotSame($pertama->nomor_anggota, $kedua->nomor_anggota);
        $this->assertSame(2, KoperasiMember::withTrashed()->count());
    }

    // =====================================================================
    // ACTIVITY LOG
    // =====================================================================

    public function test_perubahan_keanggotaan_tercatat_di_activity_log(): void
    {
        $this->actingAs($this->timKoperasi)
            ->post(route('koperasi.anggota.store'), $this->formAnggota(['participant_id' => $this->ahmad->id]));

        $anggota = KoperasiMember::first();

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'koperasi_member_created', 'model_type' => 'KoperasiMember', 'model_id' => $anggota->id,
        ]);

        $this->actingAs($this->timKoperasi)
            ->put(route('koperasi.anggota.update', $anggota), $this->formAnggota([
                'participant_id' => $this->ahmad->id, 'status' => 'Tidak Aktif',
            ]));
        $this->assertDatabaseHas('activity_logs', ['action' => 'koperasi_member_updated', 'model_id' => $anggota->id]);

        $this->actingAs($this->timKoperasi)->delete(route('koperasi.anggota.destroy', $anggota));
        $this->assertDatabaseHas('activity_logs', ['action' => 'koperasi_member_deleted', 'model_id' => $anggota->id]);
    }

    // =====================================================================
    // ADMIN tetap punya akses penuh
    // =====================================================================

    public function test_admin_tetap_bisa_mengelola_keanggotaan(): void
    {
        $this->actingAs($this->admin)->get(route('koperasi.anggota.index'))->assertOk();
        $this->actingAs($this->admin)->get(route('koperasi.anggota.pilih-peserta'))->assertOk();

        $this->actingAs($this->admin)
            ->post(route('koperasi.anggota.store'), $this->formAnggota(['participant_id' => $this->siti->id]))
            ->assertSessionHasNoErrors();

        $this->assertSame(1, KoperasiMember::where('participant_id', $this->siti->id)->count());
    }
}
