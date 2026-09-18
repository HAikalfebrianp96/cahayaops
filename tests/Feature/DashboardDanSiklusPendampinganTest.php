<?php

namespace Tests\Feature;

use App\Models\KoperasiMember;
use App\Models\Participant;
use App\Models\Pendamping;
use App\Models\PendampinganRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * DASHBOARD PENDAMPING + SIKLUS TINDAK LANJUT + STATUS PERJALANAN PESERTA.
 *
 * Tiga hal yang saling terkait:
 *
 *   1. Dashboard Pendamping. Sebelumnya Pendamping memakai panel Cahaya
 *      Preneur milik Admin, yang menghitung Participant::count(),
 *      Pendamping::count(), dan SELURUH omzet — artinya setiap Pendamping
 *      melihat rekap binaan Pendamping lain. Itu kebocoran, dan kelas ini
 *      menjaganya tidak kembali.
 *
 *   2. Siklus tindak lanjut pada catatan pendampingan
 *      (Permasalahan -> Tindakan -> Hasil -> Rencana + tanggal), yang
 *      mengisi kartu "Perlu Follow Up" di Dashboard itu.
 *
 *   3. Status perjalanan peserta, yang menentukan mana binaan yang dihitung
 *      "masih berjalan" — dan yang nilai LAMA-nya tidak boleh rusak.
 */
class DashboardDanSiklusPendampinganTest extends TestCase
{
    use RefreshDatabase;

    private Pendamping $profilA;
    private Pendamping $profilB;
    private User $pendampingA;
    private User $admin;
    private Participant $binaanA;
    private Participant $binaanB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->profilA = Pendamping::create(['nama' => 'Rosa Linda', 'nomor_hp' => '0812', 'email' => 'a@pd.test', 'status' => 'Aktif']);
        $this->profilB = Pendamping::create(['nama' => 'Pendamping B', 'nomor_hp' => '0813', 'email' => 'b@pd.test', 'status' => 'Aktif']);

        $this->pendampingA = $this->user('pendamping', 'a@akun.test', $this->profilA->id);
        $this->admin = $this->user('admin', 'admin@akun.test');

        $this->binaanA = $this->peserta('Binaan Milik A', $this->profilA->id);
        $this->binaanB = $this->peserta('Binaan Milik B', $this->profilB->id);
    }

    private function user(string $role, string $email, ?int $pendampingId = null): User
    {
        return User::create([
            'name' => 'User ' . $email, 'email' => $email, 'password' => 'password123',
            'role' => $role, 'pendamping_id' => $pendampingId, 'is_active' => true,
        ]);
    }

    private function peserta(string $nama, ?int $pendampingId, string $status = 'Aktif'): Participant
    {
        return Participant::create([
            'nama_peserta' => $nama, 'nomor_hp' => '0812', 'nama_usaha' => 'Usaha ' . $nama,
            'kategori_usaha' => 'Jasa', 'tanggal_mulai_program' => '2026-01-10',
            'status' => $status, 'pendamping_id' => $pendampingId,
        ]);
    }

    private function formCatatan(array $ubah = []): array
    {
        return array_merge([
            'tanggal' => now()->toDateString(),
            'jenis_kegiatan' => 'Konsultasi',
            'materi' => 'Kunjungan rutin',
        ], $ubah);
    }

    // =====================================================================
    // DASHBOARD PENDAMPING — ter-scope, tidak bocor
    // =====================================================================

    public function test_dashboard_pendamping_hanya_menampilkan_binaannya(): void
    {
        $this->actingAs($this->pendampingA)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Binaan Saya')
            ->assertSee('Binaan Milik A')
            ->assertDontSee('Binaan Milik B');
    }

    /** INTI: hitungan KPI-nya juga ter-scope, bukan hanya daftarnya. */
    public function test_kpi_dashboard_pendamping_tidak_menghitung_binaan_lain(): void
    {
        // Tiga binaan milik A, satu milik B.
        $this->peserta('Binaan A2', $this->profilA->id);
        $this->peserta('Binaan A3', $this->profilA->id, 'Alumni');

        $respons = $this->actingAs($this->pendampingA)->get(route('dashboard'));

        $respons->assertOk();
        // 3 binaan milik A — bukan 4 (total seluruh peserta di sistem).
        $respons->assertViewHas('stats', fn ($stats) => $stats['total_binaan'] === 3);
        // 'Alumni' tidak dihitung sebagai masih berjalan.
        $respons->assertViewHas('stats', fn ($stats) => $stats['binaan_aktif'] === 2);
    }

    public function test_pendampingan_bulan_ini_hanya_menghitung_binaan_sendiri(): void
    {
        PendampinganRecord::create([
            'participant_id' => $this->binaanA->id, 'pendamping_id' => $this->profilA->id,
            'tanggal' => now()->toDateString(), 'jenis_kegiatan' => 'Monitoring', 'materi' => 'Milik A',
        ]);
        PendampinganRecord::create([
            'participant_id' => $this->binaanB->id, 'pendamping_id' => $this->profilB->id,
            'tanggal' => now()->toDateString(), 'jenis_kegiatan' => 'Monitoring', 'materi' => 'Milik B',
        ]);

        $this->actingAs($this->pendampingA)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('stats', fn ($stats) => $stats['pendampingan_bulan_ini'] === 1);
    }

    public function test_dashboard_pendamping_punya_tombol_cepat(): void
    {
        $this->actingAs($this->pendampingA)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('+ Tambah Binaan')
            ->assertSee('Lihat Semua Binaan')
            ->assertSee(route('participants.create'), false)
            ->assertSee(route('binaan.index'), false);
    }

    /** Dashboard Pendamping tidak boleh menawarkan modul Koperasi. */
    public function test_dashboard_pendamping_tidak_menampilkan_modul_koperasi(): void
    {
        $html = $this->actingAs($this->pendampingA)->get(route('dashboard'))->getContent();
        $main = substr($html, strpos($html, '<main'));

        $this->assertStringNotContainsString('modul=koperasi', $main);
    }

    /** Admin & Pimpinan TIDAK ikut dialihkan — dashboardnya tidak berubah. */
    public function test_dashboard_admin_dan_pimpinan_tidak_berubah(): void
    {
        $this->actingAs($this->admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('modul', 'semua');

        $this->actingAs($this->user('pimpinan', 'pimpinan@akun.test'))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Dashboard Pimpinan');
    }

    /** Pendamping TANPA tautan tetap memakai alur lama, tidak error. */
    public function test_pendamping_tanpa_tautan_tetap_bisa_membuka_dashboard(): void
    {
        $this->actingAs($this->user('pendamping', 'lepas@akun.test'))
            ->get(route('dashboard'))
            ->assertOk();
    }

    // =====================================================================
    // SIKLUS TINDAK LANJUT
    // =====================================================================

    public function test_siklus_tindak_lanjut_tersimpan_lengkap(): void
    {
        $this->actingAs($this->pendampingA)
            ->post(route('pendampingan.store', $this->binaanA), $this->formCatatan([
                'materi' => 'Evaluasi penjualan',
                'kendala' => 'Penjualan menurun',
                'tindakan' => 'Konsultasi pemasaran digital',
                'hasil' => 'Peserta mulai memakai WhatsApp Business',
                'tindak_lanjut' => 'Pantau hasilnya bulan depan',
                'tanggal_follow_up' => '2026-10-15',
            ]))
            ->assertSessionHasNoErrors();

        $catatan = PendampinganRecord::where('materi', 'Evaluasi penjualan')->first();

        $this->assertSame('Penjualan menurun', $catatan->kendala);
        $this->assertSame('Konsultasi pemasaran digital', $catatan->tindakan);
        $this->assertSame('Peserta mulai memakai WhatsApp Business', $catatan->hasil);
        $this->assertSame('Pantau hasilnya bulan depan', $catatan->tindak_lanjut);
        $this->assertSame('2026-10-15', $catatan->tanggal_follow_up->format('Y-m-d'));
    }

    public function test_siklus_tindak_lanjut_bisa_diperbaiki(): void
    {
        $catatan = PendampinganRecord::create([
            'participant_id' => $this->binaanA->id, 'pendamping_id' => $this->profilA->id,
            'tanggal' => '2026-08-01', 'jenis_kegiatan' => 'Konsultasi', 'materi' => 'Awal',
        ]);

        $this->actingAs($this->pendampingA)
            ->put(route('pendampingan.update', $catatan), $this->formCatatan([
                'materi' => 'Awal',
                'tindakan' => 'Pendampingan pembukuan',
                'hasil' => 'Peserta punya buku kas',
                'tanggal_follow_up' => '2026-11-01',
            ]))
            ->assertSessionHasNoErrors();

        $segar = $catatan->fresh();

        $this->assertSame('Pendampingan pembukuan', $segar->tindakan);
        $this->assertSame('Peserta punya buku kas', $segar->hasil);
        $this->assertSame('2026-11-01', $segar->tanggal_follow_up->format('Y-m-d'));
    }

    /** Follow up yang jatuh tempo muncul di Dashboard; milik orang lain tidak. */
    public function test_follow_up_jatuh_tempo_muncul_di_dashboard(): void
    {
        PendampinganRecord::create([
            'participant_id' => $this->binaanA->id, 'pendamping_id' => $this->profilA->id,
            'tanggal' => '2026-08-01', 'jenis_kegiatan' => 'Konsultasi', 'materi' => 'Perlu dikunjungi lagi',
            'tindak_lanjut' => 'Cek perkembangan pemasaran', 'tanggal_follow_up' => now()->subDay()->toDateString(),
        ]);

        // Milik Pendamping lain — TIDAK boleh ikut muncul.
        PendampinganRecord::create([
            'participant_id' => $this->binaanB->id, 'pendamping_id' => $this->profilB->id,
            'tanggal' => '2026-08-01', 'jenis_kegiatan' => 'Konsultasi', 'materi' => 'Milik B',
            'tindak_lanjut' => 'Rahasia B', 'tanggal_follow_up' => now()->subDay()->toDateString(),
        ]);

        $this->actingAs($this->pendampingA)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Cek perkembangan pemasaran')
            ->assertSee('Terlambat')
            ->assertDontSee('Rahasia B')
            ->assertViewHas('stats', fn ($stats) => $stats['perlu_follow_up'] === 1);
    }

    /** Follow up yang masih jauh belum dianggap jatuh tempo. */
    public function test_follow_up_jauh_belum_muncul_sebagai_jatuh_tempo(): void
    {
        PendampinganRecord::create([
            'participant_id' => $this->binaanA->id, 'pendamping_id' => $this->profilA->id,
            'tanggal' => now()->toDateString(), 'jenis_kegiatan' => 'Konsultasi', 'materi' => 'Nanti saja',
            'tindak_lanjut' => 'Kunjungan berikutnya', 'tanggal_follow_up' => now()->addMonths(2)->toDateString(),
        ]);

        $this->actingAs($this->pendampingA)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('stats', fn ($stats) => $stats['perlu_follow_up'] === 0);
    }

    // =====================================================================
    // STATUS PERJALANAN PESERTA
    // =====================================================================

    public function test_status_perjalanan_baru_bisa_dipakai(): void
    {
        foreach (Participant::STATUS_OPTIONS as $status) {
            $this->actingAs($this->admin)
                ->put(route('participants.update', $this->binaanA), [
                    'nama_peserta' => 'Binaan Milik A', 'nomor_hp' => '0812',
                    'nama_usaha' => 'Usaha', 'kategori_usaha' => 'Jasa',
                    'tanggal_mulai_program' => '2026-01-10', 'status' => $status,
                    'pendamping_id' => $this->profilA->id,
                ])
                ->assertSessionHasNoErrors("Status '$status' seharusnya diterima.");

            $this->assertSame($status, $this->binaanA->fresh()->status);
        }
    }

    /**
     * INTI: nilai status LAMA yang masih dipakai baris existing tidak boleh
     * mendadak tertolak — kalau tertolak, peserta ber-status 'Graduasi' tidak
     * akan pernah bisa disimpan lagi.
     */
    public function test_status_lama_tetap_sah_dan_tidak_berubah_diam_diam(): void
    {
        $lama = $this->peserta('Peserta Lama', $this->profilA->id, 'Graduasi');

        $this->actingAs($this->admin)
            ->put(route('participants.update', $lama), [
                'nama_peserta' => 'Peserta Lama Diperbaiki', 'nomor_hp' => '0812',
                'nama_usaha' => 'Usaha', 'kategori_usaha' => 'Jasa',
                'tanggal_mulai_program' => '2026-01-10', 'status' => 'Graduasi',
                'pendamping_id' => $this->profilA->id,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('Graduasi', $lama->fresh()->status);
        $this->assertSame('Peserta Lama Diperbaiki', $lama->fresh()->nama_peserta);

        // Dan form Edit-nya ikut merender nilai lama itu sebagai pilihan,
        // supaya tidak terganti diam-diam saat disimpan.
        $this->actingAs($this->admin)
            ->get(route('participants.edit', $lama))
            ->assertOk()
            ->assertSee('Graduasi');
    }

    public function test_status_karangan_tetap_ditolak(): void
    {
        $this->actingAs($this->admin)
            ->put(route('participants.update', $this->binaanA), [
                'nama_peserta' => 'Binaan Milik A', 'nomor_hp' => '0812',
                'nama_usaha' => 'Usaha', 'kategori_usaha' => 'Jasa',
                'tanggal_mulai_program' => '2026-01-10', 'status' => 'Status Karangan',
            ])
            ->assertSessionHasErrors('status');

        $this->assertSame('Aktif', $this->binaanA->fresh()->status);
    }

    // =====================================================================
    // CATATAN INTERNAL
    // =====================================================================

    public function test_catatan_internal_bisa_diisi_dan_diperbaiki(): void
    {
        $this->actingAs($this->pendampingA)
            ->put(route('participants.update', $this->binaanA), [
                'nama_peserta' => 'Binaan Milik A', 'nomor_hp' => '0812',
                'nama_usaha' => 'Usaha', 'kategori_usaha' => 'Jasa',
                'tanggal_mulai_program' => '2026-01-10', 'status' => 'Aktif',
                'catatan_internal' => 'Sulit dihubungi pagi hari.',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('Sulit dihubungi pagi hari.', $this->binaanA->fresh()->catatan_internal);

        $this->actingAs($this->pendampingA)
            ->get(route('participants.show', $this->binaanA))
            ->assertOk()
            ->assertSee('Sulit dihubungi pagi hari.');
    }

    /** Pimpinan (read-only) tidak melihat catatan internal. */
    public function test_catatan_internal_tidak_tampil_untuk_pimpinan(): void
    {
        $this->binaanA->update(['catatan_internal' => 'Catatan rahasia kerja.']);

        $this->actingAs($this->user('pimpinan', 'pimpinan2@akun.test'))
            ->get(route('participants.show', $this->binaanA))
            ->assertOk()
            ->assertDontSee('Catatan rahasia kerja.');
    }

    // =====================================================================
    // RIWAYAT PERUBAHAN
    // =====================================================================

    public function test_riwayat_perubahan_tampil_di_detail_peserta(): void
    {
        $this->actingAs($this->admin)
            ->put(route('participants.update', $this->binaanA), [
                'nama_peserta' => 'Binaan Milik A', 'nomor_hp' => '081299998888',
                'nama_usaha' => 'Usaha', 'kategori_usaha' => 'Jasa',
                'tanggal_mulai_program' => '2026-01-10', 'status' => 'Aktif',
                'pendamping_id' => $this->profilA->id,
            ]);

        $this->actingAs($this->admin)
            ->get(route('participants.show', $this->binaanA))
            ->assertOk()
            ->assertSee('Riwayat Perubahan')
            ->assertSee('Updated Participant: Binaan Milik A')
            ->assertSee($this->admin->name);
    }

    // =====================================================================
    // JADIKAN ANGGOTA KOPERASI dari Detail Peserta
    // =====================================================================

    public function test_jadikan_anggota_membawa_peserta_ke_form_pendaftaran(): void
    {
        $timKoperasi = $this->user(User::ROLE_TIM_KOPERASI, 'tk@akun.test');

        $this->actingAs($timKoperasi)
            ->get(route('koperasi.anggota.jadikan', $this->binaanA))
            ->assertRedirect(route('koperasi.anggota.create', ['participant_id' => $this->binaanA->id]));

        $this->actingAs($timKoperasi)
            ->get(route('koperasi.anggota.create', ['participant_id' => $this->binaanA->id]))
            ->assertOk()
            ->assertSee('Data dari Database Peserta')
            ->assertSee('Binaan Milik A');
    }

    /** Sumber anggota terisi otomatis saat berasal dari peserta. */
    public function test_sumber_anggota_terisi_otomatis_dari_peserta(): void
    {
        $timKoperasi = $this->user(User::ROLE_TIM_KOPERASI, 'tk2@akun.test');

        $this->actingAs($timKoperasi)
            ->post(route('koperasi.anggota.store'), [
                'participant_id' => $this->binaanA->id,
                'tanggal_bergabung' => '2026-09-01',
                'status' => 'Aktif',
            ])
            ->assertSessionHasNoErrors();

        $anggota = KoperasiMember::where('participant_id', $this->binaanA->id)->first();

        $this->assertSame(KoperasiMember::SUMBER_PESERTA, $anggota->sumber_anggota);
        $this->assertSame(KoperasiMember::SUMBER_PESERTA, $anggota->sumberTampil());
    }

    /** Status keanggotaan yang lebih teliti tersedia dan tersimpan. */
    public function test_status_anggota_koperasi_diperluas(): void
    {
        $timKoperasi = $this->user(User::ROLE_TIM_KOPERASI, 'tk3@akun.test');

        $this->actingAs($timKoperasi)->post(route('koperasi.anggota.store'), [
            'participant_id' => $this->binaanA->id,
            'tanggal_bergabung' => '2026-09-01', 'status' => 'Aktif',
        ]);

        $anggota = KoperasiMember::first();

        foreach (['Mengundurkan Diri', 'Diberhentikan', 'Tidak Aktif', 'Aktif'] as $status) {
            $this->actingAs($timKoperasi)
                ->put(route('koperasi.anggota.update', $anggota), [
                    'participant_id' => $this->binaanA->id,
                    'tanggal_bergabung' => '2026-09-01', 'status' => $status,
                ])
                ->assertSessionHasNoErrors("Status '$status' seharusnya diterima.");

            $this->assertSame($status, $anggota->fresh()->status);
        }

        // Riwayat keanggotaannya tidak ikut hilang saat statusnya berubah.
        $this->assertNotSoftDeleted('koperasi_members', ['id' => $anggota->id]);
    }

    // =====================================================================
    // FILTER & EXPORT PESERTA
    // =====================================================================

    public function test_filter_peserta_bekerja_untuk_seluruh_dimensi(): void
    {
        $this->binaanA->update(['kecamatan' => 'Gading Cempaka', 'kategori_usaha' => 'Kerajinan']);

        $this->actingAs($this->admin)
            ->get(route('participants.index', ['kecamatan' => 'Gading Cempaka']))
            ->assertOk()
            ->assertSee('Binaan Milik A')
            ->assertDontSee(route('participants.show', $this->binaanB), false);

        $this->actingAs($this->admin)
            ->get(route('participants.index', ['kategori_usaha' => 'Kerajinan']))
            ->assertOk()
            ->assertDontSee(route('participants.show', $this->binaanB), false);

        $this->actingAs($this->admin)
            ->get(route('participants.index', ['tahun_masuk' => 2026]))
            ->assertOk()
            ->assertSee('Binaan Milik A');

        // Pencarian nomor HP.
        $this->binaanA->update(['nomor_hp' => '081277776666']);
        $this->actingAs($this->admin)
            ->get(route('participants.index', ['q' => '081277776666']))
            ->assertOk()
            ->assertSee('Binaan Milik A')
            ->assertDontSee(route('participants.show', $this->binaanB), false);
    }

    public function test_filter_status_koperasi_memakai_relasi(): void
    {
        KoperasiMember::create([
            'nama' => 'Binaan Milik A', 'nomor_hp' => '0812', 'tanggal_bergabung' => '2026-09-01',
            'status' => 'Aktif', 'participant_id' => $this->binaanA->id,
        ]);

        $this->actingAs($this->admin)
            ->get(route('participants.index', ['status_koperasi' => 'anggota']))
            ->assertOk()
            ->assertSee('Binaan Milik A')
            ->assertDontSee(route('participants.show', $this->binaanB), false);

        $this->actingAs($this->admin)
            ->get(route('participants.index', ['status_koperasi' => 'bukan']))
            ->assertOk()
            ->assertSee('Binaan Milik B')
            ->assertDontSee(route('participants.show', $this->binaanA), false);
    }

    /** Export memakai filter yang SAMA dengan yang terlihat di layar. */
    public function test_export_peserta_mengikuti_filter_yang_aktif(): void
    {
        $respons = $this->actingAs($this->admin)
            ->get(route('participants.export', ['pendamping_id' => $this->profilA->id]));

        $respons->assertOk();
        $respons->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $isi = $respons->streamedContent();

        $this->assertStringContainsString('Binaan Milik A', $isi);
        $this->assertStringNotContainsString('Binaan Milik B', $isi);
        $this->assertStringContainsString('Nama Peserta', $isi);
        $this->assertStringContainsString('Status Koperasi', $isi);
    }

    public function test_export_peserta_tertutup_untuk_non_admin(): void
    {
        $this->actingAs($this->pendampingA)->get(route('participants.export'))->assertForbidden();
        $this->actingAs($this->user('pimpinan', 'pimpinan3@akun.test'))
            ->get(route('participants.export'))->assertForbidden();
    }
}
