<?php

namespace Tests\Feature;

use App\Models\Participant;
use App\Models\Pendamping;
use App\Models\PendampinganRecord;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\FollowUpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TUGAS SAYA — alur kerja harian pendamping.
 *
 * ==========================================================================
 * YANG DIJAGA BERKAS INI
 * ==========================================================================
 *
 * Halaman ini menjawab satu pertanyaan: "apa yang harus saya kerjakan
 * sekarang?". Jawabannya salah dengan tiga cara berbeda, dan tidak satu pun
 * memunculkan error:
 *
 *   1. Peserta yang perlu dikerjakan TIDAK MUNCUL di daftar. Ini yang paling
 *      berbahaya — pendamping merasa pekerjaannya sudah beres.
 *   2. Peserta pendamping LAIN ikut muncul.
 *   3. Angka di Dashboard berbeda dari isi halamannya, sehingga tidak ada
 *      yang bisa dipercaya.
 *
 * Ambangnya TIDAK ditulis tetap di test ini: diambil dari FollowUpService,
 * sumber yang sama dengan aplikasinya. Kalau Admin mengubah kebijakan lewat
 * Pengaturan, test ini ikut menyesuaikan alih-alih gagal palsu.
 */
class TugasSayaPendampingTest extends TestCase
{
    use RefreshDatabase;

    private Pendamping $profilRosa;
    private Pendamping $profilLain;
    private User $rosa;
    private User $pendampingLain;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->profilRosa = Pendamping::create(['nama' => 'Rosa Linda', 'nomor_hp' => '081200000001', 'status' => 'Aktif']);
        $this->profilLain = Pendamping::create(['nama' => 'Pendamping Lain', 'nomor_hp' => '081200000002', 'status' => 'Aktif']);

        $this->rosa = $this->user('pendamping', 'rosa@tugas.test', $this->profilRosa->id);
        $this->pendampingLain = $this->user('pendamping', 'lain@tugas.test', $this->profilLain->id);
        $this->admin = $this->user('admin', 'admin@tugas.test');
    }

    private function user(string $role, string $email, ?int $pendampingId = null): User
    {
        return User::create([
            'name' => $email, 'email' => $email, 'password' => bcrypt('rahasia123'),
            'role' => $role, 'pendamping_id' => $pendampingId, 'is_active' => true,
        ]);
    }

    private function peserta(string $nama, ?int $pendampingId, string $usaha = 'Warung'): Participant
    {
        return Participant::create([
            'nama_peserta' => $nama, 'nomor_hp' => '081299900001',
            'nama_usaha' => $usaha, 'kategori_usaha' => 'Kuliner',
            'tanggal_mulai_program' => now()->subYear()->toDateString(),
            'status' => 'Aktif', 'pendamping_id' => $pendampingId,
        ]);
    }

    private function catatan(Participant $p, int $hariLalu, ?string $followUp = null): PendampinganRecord
    {
        return $p->pendampinganRecords()->create([
            'tanggal' => now()->subDays($hariLalu)->toDateString(),
            'jenis_kegiatan' => 'Kunjungan Usaha',
            'materi' => 'Pembukuan sederhana',
            'pendamping_id' => $p->pendamping_id,
            'tanggal_follow_up' => $followUp,
        ]);
    }

    private function ambang(): array
    {
        return app(FollowUpService::class)->ambang();
    }

    // ==================================================================
    // A. OTORISASI — pendamping hanya melihat binaannya sendiri
    // ==================================================================

    public function test_pendamping_hanya_melihat_binaannya_sendiri(): void
    {
        $milikRosa = $this->peserta('Binaan Rosa', $this->profilRosa->id);
        $milikLain = $this->peserta('Binaan Orang Lain', $this->profilLain->id);
        $this->catatan($milikRosa, $this->ambang()['merah'] + 10);
        $this->catatan($milikLain, $this->ambang()['merah'] + 10);

        $this->actingAs($this->rosa)->get(route('follow-up.index'))
            ->assertOk()
            ->assertSee('Binaan Rosa')
            ->assertDontSee('Binaan Orang Lain');
    }

    public function test_pendamping_tidak_bisa_mengintip_lewat_filter_url(): void
    {
        // Filter ?pendamping_id sengaja DIABAIKAN untuk non-Admin. Tanpa itu,
        // mengetik id orang lain di alamat akan membuka seluruh binaannya.
        $milikLain = $this->peserta('Binaan Orang Lain', $this->profilLain->id);
        $this->catatan($milikLain, $this->ambang()['merah'] + 10);

        $this->actingAs($this->rosa)
            ->get(route('follow-up.index', ['pendamping_id' => $this->profilLain->id]))
            ->assertOk()
            ->assertDontSee('Binaan Orang Lain');
    }

    public function test_admin_tetap_melihat_seluruh_pendamping(): void
    {
        $this->catatan($this->peserta('Binaan Rosa', $this->profilRosa->id), $this->ambang()['merah'] + 10);
        $this->catatan($this->peserta('Binaan Orang Lain', $this->profilLain->id), $this->ambang()['merah'] + 10);

        $this->actingAs($this->admin)->get(route('follow-up.index'))
            ->assertOk()
            ->assertSee('Binaan Rosa')
            ->assertSee('Binaan Orang Lain')
            // Tampilan Admin TIDAK diubah — ia memantau, bukan mengerjakan.
            ->assertSee('Monitoring Follow Up');
    }

    public function test_tim_koperasi_ditolak(): void
    {
        $tk = $this->user(User::ROLE_TIM_KOPERASI, 'tk@tugas.test');

        $this->actingAs($tk)->get(route('follow-up.index'))->assertForbidden();
    }

    // ==================================================================
    // B. PRIORITAS — empat keadaan, dihitung dari ambang yang berlaku
    // ==================================================================

    public function test_peserta_terlambat_muncul_di_perlu_segera(): void
    {
        $p = $this->peserta('Titi Rahmawati', $this->profilRosa->id, 'Keripik Pisang');
        $this->catatan($p, $this->ambang()['merah'] + 5);

        $this->actingAs($this->rosa)->get(route('follow-up.index'))
            ->assertOk()
            ->assertSee('Perlu Segera')
            ->assertSee('Titi Rahmawati')
            ->assertSee('Keripik Pisang');
    }

    public function test_peserta_belum_pernah_didampingi_ikut_perlu_segera(): void
    {
        // Sengaja TIDAK dikategorikan "belum ada data": tidak adanya catatan
        // sama sekali justru keadaan yang paling perlu dikerjakan, bukan
        // keadaan yang tidak diketahui.
        $this->peserta('Peserta Baru', $this->profilRosa->id);

        $this->actingAs($this->rosa)->get(route('follow-up.index'))
            ->assertOk()
            ->assertSee('Peserta Baru')
            ->assertSee('Belum pernah didampingi');
    }

    public function test_peserta_di_rentang_kuning_muncul_di_perlu_ditindaklanjuti(): void
    {
        // Sebelum keranjang ini ada, peserta di rentang kuning TANPA jadwal
        // tidak cocok ke keranjang mana pun dan lenyap dari halaman — baru
        // muncul setelah terlambat cukup lama untuk menjadi merah.
        $ambang = $this->ambang();
        $p = $this->peserta('Peserta Kuning', $this->profilRosa->id);
        $this->catatan($p, $ambang['kuning'] + 1);

        $this->actingAs($this->rosa)->get(route('follow-up.index'))
            ->assertOk()
            ->assertSee('Perlu Ditindaklanjuti')
            ->assertSee('Peserta Kuning');
    }

    public function test_peserta_normal_tidak_muncul_sebagai_tugas(): void
    {
        $p = $this->peserta('Peserta Normal', $this->profilRosa->id);
        $this->catatan($p, 3);

        $isi = $this->actingAs($this->rosa)->get(route('follow-up.index'))
            ->assertOk()
            ->assertSee('Kondisi Normal')
            ->getContent();

        // Namanya tidak dicetak sebagai kartu tugas — hanya ikut dihitung.
        $this->assertStringNotContainsString('Peserta Normal', $isi);
    }

    public function test_jadwal_hari_ini_muncul_di_bagiannya_sendiri(): void
    {
        $p = $this->peserta('Peserta Jadwal', $this->profilRosa->id);
        $this->catatan($p, 5, now()->toDateString());

        $this->actingAs($this->rosa)->get(route('follow-up.index'))
            ->assertOk()
            ->assertSee('Jadwal Hari Ini')
            ->assertSee('Peserta Jadwal');
    }

    public function test_setiap_binaan_masuk_tepat_satu_keranjang(): void
    {
        // Penjaga terpenting: tidak boleh ada peserta yang jatuh di antara
        // keranjang dan hilang dari halaman tanpa ada yang menyadarinya.
        $ambang = $this->ambang();

        $this->catatan($this->peserta('A', $this->profilRosa->id), $ambang['merah'] + 5);
        $this->catatan($this->peserta('B', $this->profilRosa->id), $ambang['kuning'] + 1);
        $this->catatan($this->peserta('C', $this->profilRosa->id), 2);
        $this->catatan($this->peserta('D', $this->profilRosa->id), 5, now()->toDateString());
        $this->catatan($this->peserta('E', $this->profilRosa->id), 5, now()->addDays(3)->toDateString());
        $this->peserta('F', $this->profilRosa->id);

        $peserta = Participant::where('pendamping_id', $this->profilRosa->id)->get()->filter->masihBerjalan();
        $kelompok = app(FollowUpService::class)->kelompokkan($peserta);

        $total = collect($kelompok)->sum(fn ($c) => $c->count());

        $this->assertSame(6, $peserta->count());
        $this->assertSame(6, $total, 'Ada binaan yang tidak masuk keranjang mana pun.');
    }

    public function test_ambang_mengikuti_pengaturan_admin(): void
    {
        // Kebijakan bisa diubah lewat Pengaturan tanpa menyentuh kode.
        // Kalau halaman ini memakai angka yang ditulis tetap, perubahan itu
        // diabaikan diam-diam.
        $setting = SystemSetting::current();
        $setting->followup_kuning_hari = 10;
        $setting->followup_merah_hari = 20;
        $setting->save();

        $p = $this->peserta('Peserta Ambang', $this->profilRosa->id);
        $this->catatan($p, 25);

        $this->actingAs($this->rosa)->get(route('follow-up.index'))
            ->assertOk()
            ->assertSee('Peserta Ambang');

        $this->assertSame(['kuning' => 10, 'merah' => 20], app(FollowUpService::class)->ambang());
    }

    // ==================================================================
    // C. DASHBOARD mencerminkan halaman yang sama
    // ==================================================================

    public function test_angka_dashboard_sama_dengan_isi_tugas_saya(): void
    {
        $ambang = $this->ambang();
        $this->catatan($this->peserta('Terlambat A', $this->profilRosa->id), $ambang['merah'] + 5);
        $this->catatan($this->peserta('Terlambat B', $this->profilRosa->id), $ambang['merah'] + 9);
        $this->catatan($this->peserta('Normal A', $this->profilRosa->id), 2);

        $peserta = Participant::where('pendamping_id', $this->profilRosa->id)->get()->filter->masihBerjalan();
        $kelompok = app(FollowUpService::class)->kelompokkan($peserta);

        $this->actingAs($this->rosa)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Tugas Saya')
            ->assertViewHas('ringkasTugas', fn ($r) => $r['perlu_segera'] === $kelompok['terlambat']->count() + $kelompok['belum_pernah']->count()
                && $r['normal'] === $kelompok['normal']->count());
    }

    // ==================================================================
    // D. SATU INPUT, DATA LAIN MENGIKUTI
    // ==================================================================

    public function test_mencatat_pendampingan_memindahkan_peserta_keluar_dari_perlu_segera(): void
    {
        $p = $this->peserta('Titi Rahmawati', $this->profilRosa->id);
        $this->catatan($p, $this->ambang()['merah'] + 5);

        $this->actingAs($this->rosa)->get(route('follow-up.index'))
            ->assertOk()->assertSee('Titi Rahmawati');

        // Satu input lewat form yang SUDAH ADA di Detail Peserta.
        $this->actingAs($this->rosa)->post(route('pendampingan.store', $p), [
            'tanggal' => now()->toDateString(),
            'jenis_kegiatan' => 'Kunjungan Usaha',
            'materi' => 'Diskusi pemasaran',
        ])->assertSessionHasNoErrors();

        // Tanpa input kedua, ia keluar dari daftar tugas.
        $isi = $this->actingAs($this->rosa)->get(route('follow-up.index'))->assertOk()->getContent();
        $this->assertStringNotContainsString('Titi Rahmawati', $isi);
    }

    public function test_menjadwalkan_tindak_lanjut_memunculkannya_pada_tanggalnya(): void
    {
        $p = $this->peserta('Peserta Jadwal', $this->profilRosa->id);

        $this->actingAs($this->rosa)->post(route('pendampingan.store', $p), [
            'tanggal' => now()->toDateString(),
            'jenis_kegiatan' => 'Konsultasi',
            'materi' => 'Perlu ditinjau lagi',
            'tindak_lanjut' => 'Cek perkembangan penjualan',
            'tanggal_follow_up' => now()->toDateString(),
        ])->assertSessionHasNoErrors();

        $this->actingAs($this->rosa)->get(route('follow-up.index'))
            ->assertOk()
            ->assertSee('Jadwal Hari Ini')
            ->assertSee('Peserta Jadwal');
    }

    public function test_aktivitas_terakhir_berubah_setelah_mencatat(): void
    {
        $p = $this->peserta('Peserta Catat', $this->profilRosa->id);
        $this->catatan($p, 60);

        $this->actingAs($this->rosa)->post(route('pendampingan.store', $p), [
            'tanggal' => now()->toDateString(),
            'jenis_kegiatan' => 'Evaluasi',
            'materi' => 'Evaluasi bulanan',
        ]);

        $ringkas = app(FollowUpService::class)->untukPeserta(
            $p->fresh(),
            $p->pendampinganRecords()->latest('tanggal')->first(),
        );

        $this->assertSame(FollowUpService::HIJAU, $ringkas['status']);
        $this->assertSame(0, $ringkas['hari']);
    }

    // ==================================================================
    // E. DETAIL PESERTA — alasan terbaca di kepala halaman
    // ==================================================================

    public function test_detail_peserta_menampilkan_status_tindak_lanjut(): void
    {
        $p = $this->peserta('Titi Rahmawati', $this->profilRosa->id);
        $this->catatan($p, $this->ambang()['merah'] + 5);

        $this->actingAs($this->rosa)->get(route('participants.show', $p))
            ->assertOk()
            ->assertSee(FollowUpService::MERAH)
            // Kalimat alasannya berasal dari FollowUpService, bukan ditulis
            // ulang di tampilan — jadi yang diperiksa di sini pun potongan
            // kalimat aslinya.
            ->assertSee('tanpa pendampingan', false);
    }

    public function test_tautan_hubungi_memakai_nomor_yang_tersimpan(): void
    {
        $p = $this->peserta('Titi Rahmawati', $this->profilRosa->id);
        $this->catatan($p, $this->ambang()['merah'] + 5);

        $this->actingAs($this->rosa)->get(route('follow-up.index'))
            ->assertOk()
            ->assertSee('https://wa.me/6281299900001', false);
    }

    public function test_peserta_tanpa_nomor_tidak_diberi_tautan_palsu(): void
    {
        $p = Participant::create([
            'nama_peserta' => 'Tanpa Nomor', 'nomor_hp' => '-',
            'nama_usaha' => 'Warung', 'kategori_usaha' => 'Kuliner',
            'tanggal_mulai_program' => now()->subYear()->toDateString(),
            'status' => 'Aktif', 'pendamping_id' => $this->profilRosa->id,
        ]);
        $this->catatan($p, $this->ambang()['merah'] + 5);

        $this->actingAs($this->rosa)->get(route('follow-up.index'))
            ->assertOk()
            ->assertSee('Nomor WhatsApp belum tersedia');
    }
}
