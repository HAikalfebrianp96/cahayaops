<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\BusinessUnit;
use App\Models\KoperasiMember;
use App\Models\MemberSaving;
use App\Models\Participant;
use App\Models\Pendamping;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FLEKSIBILITAS EDIT DI MODUL KOPERASI CAHAYA.
 *
 * Tiga hal yang diuji:
 *
 *   1. Unit usaha — kini punya Hapus (Soft Delete) & Pulihkan, bukan cuma
 *      tambah/edit.
 *   2. Data keanggotaan — bisa dikoreksi Admin maupun Tim Koperasi, TANPA
 *      merusak aturan satu identitas milik [IntegrasiPesertaKoperasiTest].
 *   3. Simpanan — koreksi yang MENINGGALKAN JEJAK. Ini bagian paling ketat:
 *      tidak ada hard delete sama sekali, entri terkunci tidak bisa diedit
 *      diam-diam, dan entri yang dibatalkan berhenti dihitung ke saldo tapi
 *      tetap ada di riwayat.
 */
class EditDataKoperasiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $timKoperasi;
    private User $pendamping;
    private User $pimpinan;
    private Participant $peserta;
    private KoperasiMember $anggota;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->user('admin', 'admin@kop.test');
        $this->timKoperasi = $this->user(User::ROLE_TIM_KOPERASI, 'tk@kop.test');

        $profil = Pendamping::create(['nama' => 'PD', 'nomor_hp' => '0812', 'email' => 'pd@kop.test', 'status' => 'Aktif']);
        $this->pendamping = $this->user('pendamping', 'pd.akun@kop.test', $profil->id);
        $this->pimpinan = $this->user('pimpinan', 'pimpinan@kop.test');

        $this->peserta = Participant::create([
            'nama_peserta' => 'Ahmad', 'nomor_hp' => '081200001111', 'email' => 'ahmad@umkm.test',
            'alamat' => 'Jl. Contoh No. 1', 'nama_usaha' => 'Warung Ahmad',
            'kategori_usaha' => 'Makanan & Minuman', 'tanggal_mulai_program' => '2026-01-05',
            'status' => 'Aktif',
        ]);

        $this->anggota = KoperasiMember::create([
            'nama' => 'Ahmad', 'nomor_hp' => '081200001111', 'alamat' => 'Jl. Contoh No. 1',
            'tanggal_bergabung' => '2026-02-01', 'status' => 'Aktif',
            'participant_id' => $this->peserta->id,
        ]);
    }

    private function user(string $role, string $email, ?int $pendampingId = null): User
    {
        return User::create([
            'name' => 'User ' . $email, 'email' => $email, 'password' => 'password123',
            'role' => $role, 'pendamping_id' => $pendampingId, 'is_active' => true,
        ]);
    }

    private function unit(string $nama = 'Unit Sembako', string $kode = 'UNIT-01'): BusinessUnit
    {
        return BusinessUnit::create([
            'nama' => $nama, 'kode' => $kode, 'jenis_usaha' => 'Perdagangan',
            'tipe' => 'Barang', 'status' => 'Aktif',
        ]);
    }

    private function formUnit(BusinessUnit $unit, array $ubah = []): array
    {
        return array_merge([
            'nama' => $unit->nama, 'kode' => $unit->kode, 'jenis_usaha' => $unit->jenis_usaha,
            'tipe' => $unit->tipe, 'status' => $unit->status,
        ], $ubah);
    }

    private function formAnggota(array $ubah = []): array
    {
        return array_merge([
            'participant_id' => $this->peserta->id,
            'tanggal_bergabung' => '2026-02-01',
            'status' => 'Aktif',
        ], $ubah);
    }

    private function simpanan(array $ubah = []): MemberSaving
    {
        return $this->anggota->savings()->create(array_merge([
            'jenis_simpanan' => 'Simpanan Pokok',
            'tanggal' => '2026-02-01',
            'nominal' => 100000,
            'keterangan' => 'Setoran awal',
        ], $ubah));
    }

    // =====================================================================
    // K — UNIT USAHA
    // =====================================================================

    public function test_unit_usaha_bisa_diedit_admin_dan_tim_koperasi(): void
    {
        $unit = $this->unit();

        foreach ([$this->admin, $this->timKoperasi] as $index => $user) {
            $this->actingAs($user)->get(route('koperasi.unit-usaha.edit', $unit))->assertOk();

            $this->actingAs($user)
                ->put(route('koperasi.unit-usaha.update', $unit), $this->formUnit($unit, [
                    'nama' => 'Unit Diubah ' . $index,
                    'jenis_usaha' => 'Retail Sembako',
                    'deskripsi' => 'Melayani warga sekitar',
                    'status' => 'Tidak Aktif',
                ]))
                ->assertSessionHasNoErrors();

            $this->assertSame('Unit Diubah ' . $index, $unit->fresh()->nama);
        }

        $this->assertSame('Retail Sembako', $unit->fresh()->jenis_usaha);
        $this->assertSame('Tidak Aktif', $unit->fresh()->status);
    }

    /**
     * Menghapus unit usaha TIDAK boleh ikut menghapus anggota yang terdaftar
     * di sana — FK-nya nullOnDelete, dan soft delete bahkan tidak memicunya.
     */
    /**
     * Unit yang masih dipakai anggota DITOLAK, bukan dihapus diam-diam.
     *
     * Foreign key-nya ON DELETE SET NULL, jadi database tidak akan menolak
     * apa pun — anggotanya hanya akan kehilangan unitnya tanpa jejak. Karena
     * itu penolakannya harus datang dari aplikasi.
     */
    public function test_unit_usaha_yang_masih_punya_anggota_tidak_bisa_dihapus(): void
    {
        $unit = $this->unit();
        $this->anggota->update(['unit_usaha_id' => $unit->id]);

        $this->actingAs($this->timKoperasi)->delete(route('koperasi.unit-usaha.destroy', $unit));

        $this->assertDatabaseHas('business_units', ['id' => $unit->id]);
        $this->assertDatabaseHas('koperasi_members', ['id' => $this->anggota->id]);
        $this->assertSame($unit->id, $this->anggota->fresh()->unit_usaha_id);
        $this->assertStringContainsString('anggota terdaftar', (string) session('error'));
    }

    /**
     * Kode unit yang diarsipkan TETAP terpakai — dan ditolak dengan pesan
     * yang jelas, bukan dengan error database mentah.
     *
     * `business_units.kode` punya unique index yang tidak mempedulikan
     * `deleted_at`, jadi validasinya sengaja dibuat sama ketatnya. Kalau
     * kodenya memang mau dipakai lagi, unit lamanya tinggal dipulihkan.
     */
    public function test_kode_unit_yang_diarsipkan_tetap_terpakai(): void
    {
        $unit = $this->unit('Unit Lama', 'UNIT-A');
        $unit->delete();

        $this->actingAs($this->timKoperasi)
            ->post(route('koperasi.unit-usaha.store'), [
                'nama' => 'Unit Baru', 'kode' => 'UNIT-A', 'tipe' => 'Jasa', 'status' => 'Aktif',
            ])
            ->assertSessionHasErrors('kode');

        $this->assertSame(1, BusinessUnit::withTrashed()->where('kode', 'UNIT-A')->count());

        // Kode lain tetap bisa dipakai seperti biasa.
        $this->actingAs($this->timKoperasi)
            ->post(route('koperasi.unit-usaha.store'), [
                'nama' => 'Unit Baru', 'kode' => 'UNIT-B', 'tipe' => 'Jasa', 'status' => 'Aktif',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, BusinessUnit::where('kode', 'UNIT-B')->count());
    }

    public function test_role_tanpa_hak_tidak_bisa_menyentuh_unit_usaha(): void
    {
        $unit = $this->unit();

        foreach ([$this->pendamping, $this->pimpinan] as $user) {
            $this->actingAs($user)->get(route('koperasi.unit-usaha.index'))->assertForbidden();
            $this->actingAs($user)->get(route('koperasi.unit-usaha.edit', $unit))->assertForbidden();
            $this->actingAs($user)
                ->put(route('koperasi.unit-usaha.update', $unit), $this->formUnit($unit, ['nama' => 'DIBAJAK']))
                ->assertForbidden();
            $this->actingAs($user)->delete(route('koperasi.unit-usaha.destroy', $unit))->assertForbidden();
        }

        $this->assertSame('Unit Sembako', $unit->fresh()->nama);
        $this->assertDatabaseHas('business_units', ['id' => $unit->id]);
    }

    public function test_perubahan_unit_usaha_tercatat_di_activity_log(): void
    {
        $this->actingAs($this->timKoperasi)->post(route('koperasi.unit-usaha.store'), [
            'nama' => 'Unit Tercatat', 'kode' => 'UNIT-LOG', 'tipe' => 'Jasa', 'status' => 'Aktif',
        ]);

        $unit = BusinessUnit::where('kode', 'UNIT-LOG')->first();

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'business_unit_created', 'model_type' => 'BusinessUnit', 'model_id' => $unit->id,
        ]);

        $this->actingAs($this->timKoperasi)->delete(route('koperasi.unit-usaha.destroy', $unit));
        $this->assertDatabaseHas('activity_logs', ['action' => 'business_unit_deleted', 'model_id' => $unit->id]);

    }

    // =====================================================================
    // I — DATA KEANGGOTAAN
    // =====================================================================

    public function test_data_keanggotaan_bisa_diedit_admin_dan_tim_koperasi(): void
    {
        $unit = $this->unit();

        foreach ([$this->admin, $this->timKoperasi] as $user) {
            $this->actingAs($user)
                ->put(route('koperasi.anggota.update', $this->anggota), $this->formAnggota([
                    'tanggal_bergabung' => '2026-03-15',
                    'status' => 'Tidak Aktif',
                    'unit_usaha_id' => $unit->id,
                    'keterangan' => 'Pindah unit usaha',
                ]))
                ->assertSessionHasNoErrors();
        }

        $segar = $this->anggota->fresh();

        $this->assertSame('2026-03-15', $segar->tanggal_bergabung->format('Y-m-d'));
        $this->assertSame('Tidak Aktif', $segar->status);
        $this->assertSame($unit->id, $segar->unit_usaha_id);
        $this->assertSame('Pindah unit usaha', $segar->keterangan);
    }

    /**
     * Mengedit keanggotaan TIDAK boleh melahirkan identitas kedua — nama & HP
     * tetap bersumber dari peserta.
     */
    public function test_edit_keanggotaan_tidak_membuat_identitas_kedua(): void
    {
        $jumlahPesertaAwal = Participant::count();

        $this->actingAs($this->timKoperasi)
            ->put(route('koperasi.anggota.update', $this->anggota), $this->formAnggota([
                'status' => 'Tidak Aktif',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame($jumlahPesertaAwal, Participant::count());

        // Nama tetap mengikuti data peserta, bukan salinan yang bisa berbeda.
        $this->peserta->update(['nama_peserta' => 'Ahmad Fauzi']);
        $this->assertSame('Ahmad Fauzi', $this->anggota->fresh()->load('participant')->namaTampil());
    }

    /** Menonaktifkan anggota berbeda dari menghapusnya — keduanya tersedia. */
    public function test_anggota_bisa_dinonaktifkan_tanpa_dihapus(): void
    {
        $this->actingAs($this->timKoperasi)
            ->put(route('koperasi.anggota.update', $this->anggota), $this->formAnggota(['status' => 'Tidak Aktif']))
            ->assertSessionHasNoErrors();

        $this->assertSame('Tidak Aktif', $this->anggota->fresh()->status);
        $this->assertDatabaseHas('koperasi_members', ['id' => $this->anggota->id]);
    }

    public function test_role_tanpa_hak_tidak_bisa_mengedit_anggota(): void
    {
        foreach ([$this->pendamping, $this->pimpinan] as $user) {
            $this->actingAs($user)->get(route('koperasi.anggota.edit', $this->anggota))->assertForbidden();
            $this->actingAs($user)
                ->put(route('koperasi.anggota.update', $this->anggota), $this->formAnggota(['status' => 'Tidak Aktif']))
                ->assertForbidden();
            $this->actingAs($user)->delete(route('koperasi.anggota.destroy', $this->anggota))->assertForbidden();
        }

        $this->assertSame('Aktif', $this->anggota->fresh()->status);
    }

    // =====================================================================
    // J — SIMPANAN: KOREKSI YANG BERJEJAK
    // =====================================================================

    public function test_simpanan_belum_terkunci_bisa_diperbaiki_di_tempat(): void
    {
        $simpanan = $this->simpanan();

        $this->actingAs($this->timKoperasi)->get(route('koperasi.savings.edit', $simpanan))->assertOk();

        $this->actingAs($this->timKoperasi)
            ->put(route('koperasi.savings.update', $simpanan), [
                'jenis_simpanan' => 'Simpanan Wajib',
                'tanggal' => '2026-02-05',
                'nominal' => 75000,
                'keterangan' => 'Diperbaiki: salah ketik nominal',
            ])
            ->assertSessionHasNoErrors();

        $segar = $simpanan->fresh();

        $this->assertSame('Simpanan Wajib', $segar->jenis_simpanan);
        $this->assertEqualsWithDelta(75000, (float) $segar->nominal, 0.01);
        $this->assertSame('2026-02-05', $segar->tanggal->format('Y-m-d'));
    }

    /** Perbaikan wajib meninggalkan jejak nilai SEBELUM -> SESUDAH. */
    public function test_perbaikan_simpanan_mencatat_nilai_sebelum_dan_sesudah(): void
    {
        $simpanan = $this->simpanan();

        $this->actingAs($this->timKoperasi)->put(route('koperasi.savings.update', $simpanan), [
            'jenis_simpanan' => 'Simpanan Pokok', 'tanggal' => '2026-02-01', 'nominal' => 50000,
        ]);

        $log = ActivityLog::where('action', 'koperasi_saving_updated')
            ->where('model_id', $simpanan->id)
            ->where('description', 'like', '%sebelum%')
            ->first();

        $this->assertNotNull($log, 'Perbaikan simpanan wajib mencatat nilai sebelum -> sesudah.');
        $this->assertStringContainsString('100.000', $log->description);
        $this->assertStringContainsString('50.000', $log->description);
    }

    /** Entri terkunci TIDAK bisa diedit lagi — Admin sekalipun. */
    public function test_simpanan_terkunci_tidak_bisa_diedit_langsung(): void
    {
        $simpanan = $this->simpanan();

        $this->actingAs($this->timKoperasi)
            ->post(route('koperasi.savings.kunci', $simpanan))
            ->assertSessionHasNoErrors();

        $this->assertNotNull($simpanan->fresh()->dikunci_at);

        foreach ([$this->timKoperasi, $this->admin] as $user) {
            $this->actingAs($user)->get(route('koperasi.savings.edit', $simpanan->fresh()))->assertForbidden();
            $this->actingAs($user)
                ->put(route('koperasi.savings.update', $simpanan->fresh()), [
                    'jenis_simpanan' => 'Simpanan Pokok', 'tanggal' => '2026-02-01', 'nominal' => 999999,
                ])
                ->assertForbidden();
        }

        $this->assertEqualsWithDelta(100000, (float) $simpanan->fresh()->nominal, 0.01);
    }

    /** Pembatalan: barisnya TETAP ada, tapi berhenti dihitung ke saldo. */
    public function test_pembatalan_simpanan_menyisakan_jejak_dan_mengoreksi_saldo(): void
    {
        $simpanan = $this->simpanan(['nominal' => 100000]);

        $this->assertEqualsWithDelta(100000, $this->anggota->totalSimpanan(), 0.01);

        $this->actingAs($this->timKoperasi)
            ->post(route('koperasi.savings.batalkan', $simpanan), [
                'alasan_koreksi' => 'Salah ketik nominal, seharusnya Rp50.000',
            ])
            ->assertRedirect(route('koperasi.anggota.show', $this->anggota->id));

        $segar = $simpanan->fresh();

        // Barisnya TIDAK dihapus.
        $this->assertDatabaseHas('member_savings', ['id' => $simpanan->id]);
        $this->assertSame(MemberSaving::STATUS_DIBATALKAN, $segar->status);
        $this->assertSame('Salah ketik nominal, seharusnya Rp50.000', $segar->alasan_koreksi);
        $this->assertNotNull($segar->dibatalkan_at);
        $this->assertSame($this->timKoperasi->id, $segar->dibatalkan_oleh);

        // Tapi berhenti dihitung ke saldo.
        $this->assertEqualsWithDelta(0, $this->anggota->fresh()->totalSimpanan(), 0.01);
    }

    /** Pembatalan + entri pengganti: rantai koreksinya bisa ditelusuri. */
    public function test_pembatalan_bisa_langsung_membuat_entri_pengganti(): void
    {
        $simpanan = $this->simpanan(['nominal' => 100000]);

        $this->actingAs($this->timKoperasi)
            ->post(route('koperasi.savings.batalkan', $simpanan), [
                'alasan_koreksi' => 'Salah ketik nominal',
                'buat_pengganti' => 1,
                'nominal_pengganti' => 50000,
                'tanggal_pengganti' => '2026-02-01',
            ])
            ->assertSessionHasNoErrors();

        $pengganti = MemberSaving::where('dikoreksi_dari_id', $simpanan->id)->first();

        $this->assertNotNull($pengganti);
        $this->assertEqualsWithDelta(50000, (float) $pengganti->nominal, 0.01);
        $this->assertSame('Simpanan Pokok', $pengganti->jenis_simpanan);

        // Kedua baris tetap ada; saldonya yang benar.
        $this->assertSame(2, $this->anggota->savings()->count());
        $this->assertEqualsWithDelta(50000, $this->anggota->fresh()->totalSimpanan(), 0.01);
    }

    public function test_pembatalan_wajib_menyertakan_alasan(): void
    {
        $simpanan = $this->simpanan();

        $this->actingAs($this->timKoperasi)
            ->post(route('koperasi.savings.batalkan', $simpanan), [])
            ->assertSessionHasErrors('alasan_koreksi');

        $this->assertSame(MemberSaving::STATUS_TERCATAT, $simpanan->fresh()->status);
        $this->assertEqualsWithDelta(100000, $this->anggota->totalSimpanan(), 0.01);
    }

    /** Entri terkunci tetap bisa dikoreksi — lewat pembatalan yang berjejak. */
    public function test_entri_terkunci_masih_bisa_dikoreksi_lewat_pembatalan(): void
    {
        $simpanan = $this->simpanan();
        $this->actingAs($this->timKoperasi)->post(route('koperasi.savings.kunci', $simpanan));

        $this->actingAs($this->timKoperasi)
            ->post(route('koperasi.savings.batalkan', $simpanan->fresh()), [
                'alasan_koreksi' => 'Setoran ganda',
                'buat_pengganti' => 0,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(MemberSaving::STATUS_DIBATALKAN, $simpanan->fresh()->status);
        $this->assertEqualsWithDelta(0, $this->anggota->fresh()->totalSimpanan(), 0.01);
    }

    public function test_entri_yang_sudah_dibatalkan_tidak_bisa_dibatalkan_lagi(): void
    {
        $simpanan = $this->simpanan();

        $this->actingAs($this->timKoperasi)
            ->post(route('koperasi.savings.batalkan', $simpanan), ['alasan_koreksi' => 'Keliru']);

        $this->actingAs($this->timKoperasi)
            ->post(route('koperasi.savings.batalkan', $simpanan->fresh()), ['alasan_koreksi' => 'Lagi'])
            ->assertForbidden();
    }

    /** Koreksi simpanan punya catatan Activity Log tersendiri. */
    public function test_koreksi_simpanan_tercatat_di_activity_log(): void
    {
        $simpanan = $this->simpanan();

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'koperasi_saving_created', 'model_type' => 'MemberSaving', 'model_id' => $simpanan->id,
        ]);

        $this->actingAs($this->timKoperasi)
            ->post(route('koperasi.savings.batalkan', $simpanan), ['alasan_koreksi' => 'Setoran ganda']);

        $log = ActivityLog::where('action', 'koperasi_saving_corrected')
            ->where('model_id', $simpanan->id)
            ->first();

        $this->assertNotNull($log);
        $this->assertStringContainsString('Setoran ganda', $log->description);
    }

    /**
     * JALUR HAPUS ADA, TAPI TIDAK MENGGANTIKAN KOREKSI.
     *
     * Test ini dulu berbunyi "tidak ada route hapus simpanan": baris simpanan
     * sama sekali tidak bisa dibuang. Aturannya sekarang berubah — entri
     * percobaan yang sudah dibatalkan tidak punya nilai histori apa pun dan
     * hanya menumpuk di riwayat anggota.
     *
     * Yang TIDAK berubah, dan itulah yang dijaga di sini: entri yang masih
     * berlaku tetap tidak bisa dibuang. Membatalkan lebih dulu (beserta
     * alasannya) tetap syarat mutlak, jadi uang yang hilang dari laporan
     * selalu punya keterangan.
     */
    public function test_simpanan_berlaku_tetap_tidak_bisa_dihapus(): void
    {
        $simpanan = $this->simpanan();

        $this->actingAs($this->timKoperasi)
            ->delete(route('koperasi.savings.destroy', $simpanan))
            ->assertSessionHasErrors('hapus');

        $this->assertDatabaseHas('member_savings', ['id' => $simpanan->id]);
    }

    public function test_role_tanpa_hak_tidak_bisa_menyentuh_simpanan(): void
    {
        $simpanan = $this->simpanan();

        foreach ([$this->pendamping, $this->pimpinan] as $user) {
            $this->actingAs($user)->get(route('koperasi.savings.edit', $simpanan))->assertForbidden();
            $this->actingAs($user)
                ->put(route('koperasi.savings.update', $simpanan), [
                    'jenis_simpanan' => 'Simpanan Pokok', 'tanggal' => '2026-02-01', 'nominal' => 1,
                ])
                ->assertForbidden();
            $this->actingAs($user)
                ->post(route('koperasi.savings.batalkan', $simpanan), ['alasan_koreksi' => 'x'])
                ->assertForbidden();
            $this->actingAs($user)
                ->post(route('koperasi.savings.store', $this->anggota), [
                    'jenis_simpanan' => 'Simpanan Pokok', 'tanggal' => '2026-02-01', 'nominal' => 1000,
                ])
                ->assertForbidden();
        }

        $this->assertEqualsWithDelta(100000, (float) $simpanan->fresh()->nominal, 0.01);
        $this->assertSame(1, $this->anggota->savings()->count());
    }

    /** Saldo yang dibatalkan juga tidak boleh muncul di laporan & dashboard. */
    public function test_simpanan_dibatalkan_tidak_dihitung_di_laporan(): void
    {
        $simpanan = $this->simpanan(['nominal' => 100000]);

        $this->actingAs($this->timKoperasi)
            ->get(route('koperasi.laporan.simpanan'))
            ->assertOk()
            ->assertSee('100.000');

        $this->actingAs($this->timKoperasi)
            ->post(route('koperasi.savings.batalkan', $simpanan), ['alasan_koreksi' => 'Setoran ganda']);

        $this->actingAs($this->timKoperasi)
            ->get(route('koperasi.laporan.simpanan'))
            ->assertOk()
            ->assertDontSee('100.000');

        // Halaman keuangan (Buku 2 — Dana Anggota) juga memakai saldo yang sama.
        $this->actingAs($this->admin)->get(route('koperasi.keuangan.index'))->assertOk();
    }

    // =====================================================================
    // M — TIM KOPERASI TETAP TERKURUNG DI MODULNYA
    // =====================================================================

    public function test_tim_koperasi_tetap_ditolak_di_pengaturan_dan_manajemen_user(): void
    {
        foreach ([
            '/pengaturan', '/pengaturan-sistem', '/manajemen-user', '/manajemen-user/create',
            '/backup-database', '/backup-database/restore', '/koperasi/pengaturan',
        ] as $url) {
            $this->actingAs($this->timKoperasi)->get($url)
                ->assertForbidden("URL $url seharusnya tetap ditolak untuk Tim Koperasi.");
        }

        // Termasuk mengubah role pengguna lewat request manual.
        $this->actingAs($this->timKoperasi)
            ->put(route('manajemen-user.update', $this->pendamping), [
                'name' => 'Dibajak', 'email' => $this->pendamping->email, 'role' => 'admin',
            ])
            ->assertForbidden();

        $this->assertSame('pendamping', $this->pendamping->fresh()->role);
    }

    /** Membuka modul Koperasi TIDAK ikut membuka modul Cahaya Preneur. */
    public function test_tim_koperasi_tidak_bisa_mengubah_data_preneur(): void
    {
        $this->actingAs($this->timKoperasi)
            ->put(route('participants.update', $this->peserta), [
                'nama_peserta' => 'DIBAJAK', 'nomor_hp' => '0812', 'nama_usaha' => 'x',
                'kategori_usaha' => 'Jasa', 'tanggal_mulai_program' => '2026-01-01', 'status' => 'Aktif',
            ])
            ->assertForbidden();

        $this->actingAs($this->timKoperasi)
            ->post(route('omzet.store', $this->peserta), [
                'periode_bulan' => 1, 'periode_tahun' => 2026, 'omzet' => 1000,
            ])
            ->assertForbidden();

        $this->actingAs($this->timKoperasi)
            ->post(route('training.store', $this->peserta), [
                'tanggal' => '2026-01-01', 'nama_pelatihan' => 'x', 'status' => 'Terdaftar',
            ])
            ->assertForbidden();

        $this->assertSame('Ahmad', $this->peserta->fresh()->nama_peserta);
        $this->assertSame(0, $this->peserta->omzetReports()->count());
        $this->assertSame(0, $this->peserta->trainingRecords()->count());
    }
}
