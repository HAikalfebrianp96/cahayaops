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
 * DASHBOARD PENDAMPING (ringkasan lapangan) + TAB DETAIL PESERTA.
 *
 * Dua hal yang diuji:
 *
 *   1. Seluruh angka Dashboard Pendamping ter-scope ke binaannya sendiri —
 *      termasuk metrik baru (binaan baru, kunjungan usaha, perlu
 *      ditindaklanjuti). Satu saja yang lupa disaring berarti Pendamping
 *      melihat rekap binaan Pendamping lain.
 *
 *   2. Tab Detail Peserta hanya memindahkan VISIBILITAS, bukan menghapus isi
 *      dari halaman. Seluruh panel tetap dirender sehingga tidak ada data
 *      yang hilang saat JavaScript mati, saat halaman dicetak, atau saat
 *      dicari lewat Ctrl+F.
 */
class DashboardPendampingDanTabTest extends TestCase
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

        $this->profilA = Pendamping::create(['nama' => 'Rosa Linda', 'nomor_hp' => '0812', 'email' => 'a@dp.test', 'status' => 'Aktif']);
        $this->profilB = Pendamping::create(['nama' => 'Pendamping B', 'nomor_hp' => '0813', 'email' => 'b@dp.test', 'status' => 'Aktif']);

        $this->pendampingA = $this->user('pendamping', 'a@akun.test', $this->profilA->id);
        $this->admin = $this->user('admin', 'admin@dp.test');

        $this->binaanA = $this->peserta('Binaan Milik A', $this->profilA->id, '2026-01-10');
        $this->binaanB = $this->peserta('Binaan Milik B', $this->profilB->id, '2026-01-10');
    }

    private function user(string $role, string $email, ?int $pendampingId = null): User
    {
        return User::create([
            'name' => 'User ' . $email, 'email' => $email, 'password' => 'password123',
            'role' => $role, 'pendamping_id' => $pendampingId, 'is_active' => true,
        ]);
    }

    private function peserta(string $nama, ?int $pendampingId, string $mulai, string $status = 'Aktif'): Participant
    {
        return Participant::create([
            'nama_peserta' => $nama, 'nomor_hp' => '0812', 'nama_usaha' => 'Usaha ' . $nama,
            'kategori_usaha' => 'Jasa', 'tanggal_mulai_program' => $mulai,
            'status' => $status, 'pendamping_id' => $pendampingId,
        ]);
    }

    private function catatan(Participant $p, string $tanggal, string $jenis = 'Konsultasi'): PendampinganRecord
    {
        return PendampinganRecord::create([
            'participant_id' => $p->id, 'pendamping_id' => $p->pendamping_id,
            'tanggal' => $tanggal, 'jenis_kegiatan' => $jenis, 'materi' => 'Materi ' . $jenis,
        ]);
    }

    private function stats()
    {
        return $this->actingAs($this->pendampingA)->get(route('dashboard'))->viewData('stats');
    }

    // =====================================================================
    // A. RINGKASAN — seluruhnya ter-scope
    // =====================================================================

    public function test_binaan_baru_hanya_menghitung_yang_mulai_bulan_ini(): void
    {
        $this->peserta('Binaan Baru A', $this->profilA->id, now()->toDateString());
        // Milik Pendamping lain, bulan ini juga — TIDAK boleh ikut terhitung.
        $this->peserta('Binaan Baru B', $this->profilB->id, now()->toDateString());

        $this->assertSame(1, $this->stats()['binaan_baru']);
    }

    public function test_kunjungan_usaha_bulan_ini_terhitung_terpisah(): void
    {
        $this->catatan($this->binaanA, now()->toDateString(), 'Kunjungan Usaha');
        $this->catatan($this->binaanA, now()->toDateString(), 'Konsultasi');
        // Milik Pendamping lain — tidak boleh ikut.
        $this->catatan($this->binaanB, now()->toDateString(), 'Kunjungan Usaha');

        $stats = $this->stats();

        $this->assertSame(1, $stats['kunjungan_bulan_ini']);
        $this->assertSame(2, $stats['pendampingan_bulan_ini']);
    }

    public function test_kunjungan_bulan_lalu_tidak_ikut_terhitung(): void
    {
        $this->catatan($this->binaanA, now()->subMonths(2)->toDateString(), 'Kunjungan Usaha');

        $this->assertSame(0, $this->stats()['kunjungan_bulan_ini']);
    }

    // =====================================================================
    // D. PERLU DITINDAKLANJUTI — indikator dari data yang ADA
    // =====================================================================

    /** Binaan yang belum pernah didampingi masuk daftar. */
    public function test_binaan_belum_pernah_didampingi_perlu_ditindaklanjuti(): void
    {
        $this->actingAs($this->pendampingA)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Binaan Milik A')
            ->assertSee('Belum pernah didampingi');

        $this->assertSame(1, $this->stats()['perlu_ditindaklanjuti']);
    }

    /** Didampingi dalam 30 hari terakhir -> keluar dari daftar. */
    public function test_binaan_baru_didampingi_tidak_perlu_ditindaklanjuti(): void
    {
        $this->catatan($this->binaanA, now()->subDays(5)->toDateString());

        $this->assertSame(0, $this->stats()['perlu_ditindaklanjuti']);
    }

    /** Lebih dari 30 hari -> masuk daftar lagi. */
    public function test_binaan_lebih_30_hari_perlu_ditindaklanjuti(): void
    {
        $this->catatan($this->binaanA, now()->subDays(45)->toDateString());

        $this->assertSame(1, $this->stats()['perlu_ditindaklanjuti']);

        $this->actingAs($this->pendampingA)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Terakhir ' . now()->subDays(45)->format('d-m-Y'), false);
    }

    /** Binaan yang sudah Alumni/Nonaktif tidak perlu dikunjungi lagi. */
    public function test_binaan_tidak_berjalan_dikecualikan(): void
    {
        $this->binaanA->update(['status' => 'Alumni']);

        $this->assertSame(0, $this->stats()['perlu_ditindaklanjuti']);
    }

    /** INTI: daftar tindak lanjut tidak boleh memuat binaan Pendamping lain. */
    public function test_perlu_ditindaklanjuti_tidak_bocor_ke_binaan_lain(): void
    {
        $this->catatan($this->binaanA, now()->subDays(5)->toDateString());
        // Binaan B belum pernah didampingi, tapi bukan urusan Pendamping A.

        $this->actingAs($this->pendampingA)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Binaan Milik B');

        $this->assertSame(0, $this->stats()['perlu_ditindaklanjuti']);
    }

    // =====================================================================
    // B. DAFTAR BINAAN — kolom Pendampingan Terakhir
    // =====================================================================

    public function test_daftar_binaan_menampilkan_pendampingan_terakhir(): void
    {
        $this->catatan($this->binaanA, '2026-07-01');
        $this->catatan($this->binaanA, '2026-08-20');

        $this->actingAs($this->pendampingA)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Pendampingan Terakhir')
            // Yang tampil tanggal TERBARU, bukan yang pertama.
            ->assertSee('20-08-2026');
    }

    public function test_binaan_tanpa_pendampingan_ditandai_belum_pernah(): void
    {
        $this->actingAs($this->pendampingA)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Belum pernah');
    }

    // =====================================================================
    // C. AKTIVITAS TERBARU
    // =====================================================================

    public function test_aktivitas_terbaru_hanya_binaan_sendiri(): void
    {
        $this->catatan($this->binaanA, now()->toDateString(), 'Evaluasi');
        $this->catatan($this->binaanB, now()->toDateString(), 'Evaluasi');

        $this->actingAs($this->pendampingA)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Aktivitas Terbaru')
            ->assertSee('Binaan Milik A')
            ->assertDontSee('Binaan Milik B');
    }

    // =====================================================================
    // Admin & Pimpinan tidak ikut dialihkan
    // =====================================================================

    public function test_dashboard_admin_tidak_berubah(): void
    {
        $this->actingAs($this->admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('modul', 'semua');
    }

    // =====================================================================
    // TAB DETAIL PESERTA
    // =====================================================================

    public function test_detail_peserta_punya_seluruh_tab(): void
    {
        $respons = $this->actingAs($this->admin)->get(route('participants.show', $this->binaanA));

        $respons->assertOk();

        foreach (['Profil', 'Data Usaha', 'Omzet', 'Pendampingan', 'Pelatihan',
            'Legalitas', 'HPP', 'Koperasi', 'Riwayat Perubahan'] as $judul) {
            $respons->assertSee($judul);
        }

        foreach (['profil', 'usaha', 'omzet', 'pendampingan', 'pelatihan',
            'legalitas', 'hpp', 'koperasi', 'riwayat'] as $kunci) {
            $respons->assertSee('data-panel="' . $kunci . '"', false);
            $respons->assertSee('data-tab="' . $kunci . '"', false);
        }
    }

    /**
     * INTI: tab hanya memindahkan visibilitas. Seluruh isi tetap dirender ke
     * HTML, sehingga tidak ada data yang hilang saat JavaScript mati atau
     * halaman dicetak.
     */
    public function test_seluruh_isi_tab_tetap_dirender_ke_html(): void
    {
        $this->catatan($this->binaanA, '2026-08-01', 'Evaluasi');
        $this->binaanA->trainingRecords()->create([
            'tanggal' => '2026-05-10', 'nama_pelatihan' => 'Pelatihan Halal', 'status' => 'Selesai',
        ]);
        $this->binaanA->omzetReports()->create([
            'periode_bulan' => 6, 'periode_tahun' => 2026, 'omzet' => 5000000,
        ]);
        $this->binaanA->update(['produk' => 'Keripik pisang', 'alamat_usaha' => 'Pasar Panorama']);

        $respons = $this->actingAs($this->admin)->get(route('participants.show', $this->binaanA));

        $respons->assertOk()
            // Profil
            ->assertSee($this->binaanA->nomor_hp)
            // Data Usaha
            ->assertSee('Keripik pisang')
            ->assertSee('Pasar Panorama')
            // Pendampingan
            ->assertSee('Materi Evaluasi')
            // Pelatihan
            ->assertSee('Pelatihan Halal')
            // Omzet
            ->assertSee('Perkembangan Omzet')
            // Koperasi
            ->assertSee('Status Koperasi Cahaya');
    }

    /** Aksi di dalam tab tetap tersedia sesuai hak akses. */
    public function test_aksi_di_dalam_tab_tetap_sesuai_hak_akses(): void
    {
        $catatan = $this->catatan($this->binaanA, '2026-08-01');

        $this->actingAs($this->pendampingA)
            ->get(route('participants.show', $this->binaanA))
            ->assertOk()
            ->assertSee('Edit Data Peserta')
            ->assertSee(route('pendampingan.edit', $catatan), false)
            ->assertSee(route('omzet.create', $this->binaanA), false);

        // Pimpinan read-only: isinya terlihat, aksinya tidak.
        $this->actingAs($this->user('pimpinan', 'pimpinan@dp.test'))
            ->get(route('participants.show', $this->binaanA))
            ->assertOk()
            ->assertSee('Materi Konsultasi')
            ->assertDontSee('Edit Data Peserta')
            ->assertDontSee(route('pendampingan.edit', $catatan), false);
    }

    /** Tab Koperasi memuat tombol integrasi sesuai kewenangan. */
    public function test_tab_koperasi_menampilkan_tombol_sesuai_kewenangan(): void
    {
        $this->actingAs($this->admin)
            ->get(route('participants.show', $this->binaanA))
            ->assertOk()
            ->assertSee('Belum menjadi anggota koperasi')
            ->assertSee(route('koperasi.anggota.jadikan', $this->binaanA), false);

        // Pendamping bukan pengelola koperasi -> tombolnya tidak dirender.
        $this->actingAs($this->pendampingA)
            ->get(route('participants.show', $this->binaanA))
            ->assertOk()
            ->assertDontSee(route('koperasi.anggota.jadikan', $this->binaanA), false);
    }

    /** Setelah jadi anggota, tab Koperasi menunjuk ke data keanggotaannya. */
    public function test_tab_koperasi_menampilkan_keanggotaan_yang_sudah_ada(): void
    {
        $anggota = KoperasiMember::create([
            'nama' => 'Binaan Milik A', 'nomor_hp' => '0812',
            'tanggal_bergabung' => '2026-09-01', 'status' => 'Aktif',
            'participant_id' => $this->binaanA->id,
        ]);

        $this->actingAs($this->admin)
            ->get(route('participants.show', $this->binaanA))
            ->assertOk()
            ->assertSee($anggota->nomor_anggota)
            ->assertSee(route('koperasi.anggota.show', $anggota), false)
            ->assertDontSee(route('koperasi.anggota.jadikan', $this->binaanA), false);
    }
}
