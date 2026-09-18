<?php

namespace Tests\Feature;

use App\Models\BusinessUnit;
use App\Models\KoperasiMember;
use App\Models\MemberSaving;
use App\Models\OmzetReport;
use App\Models\Participant;
use App\Models\Pendamping;
use App\Models\TrainingRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SAPUAN OTORISASI ATAS SELURUH ROUTE BARU.
 *
 * Route yang baru ditambahkan adalah tempat paling klasik untuk lupa memasang
 * penjagaan — dan penjagaan yang lupa dipasang biasanya "fail-open": karena
 * tidak ada cabang yang menolak, siapa pun bisa masuk.
 *
 * Kelas ini memeriksa SATU PER SATU setiap route yang lahir di tahap ini,
 * dari sudut pandang pengguna yang TIDAK BERHAK, dengan URL langsung. Tidak
 * ada satu pun yang boleh menjawab 200/302 sukses.
 *
 * Sengaja ditulis sebagai daftar: setiap route baru berikutnya harus
 * didaftarkan di sini dengan sadar, bukan diam-diam kebagian akses.
 */
class OtorisasiRouteBaruTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $timKoperasi;
    private User $pendampingA;
    private User $pendampingB;
    private User $pimpinan;
    private Participant $binaanA;
    private Participant $binaanB;
    private TrainingRecord $pelatihanB;
    private OmzetReport $omzetB;
    private BusinessUnit $unit;
    private KoperasiMember $anggota;
    private MemberSaving $simpanan;

    protected function setUp(): void
    {
        parent::setUp();

        $profilA = Pendamping::create(['nama' => 'PD A', 'nomor_hp' => '0812', 'email' => 'a@pd.test', 'status' => 'Aktif']);
        $profilB = Pendamping::create(['nama' => 'PD B', 'nomor_hp' => '0813', 'email' => 'b@pd.test', 'status' => 'Aktif']);

        $this->admin = $this->user('admin', 'admin@sweep.test');
        $this->timKoperasi = $this->user(User::ROLE_TIM_KOPERASI, 'tk@sweep.test');
        $this->pendampingA = $this->user('pendamping', 'a@sweep.test', $profilA->id);
        $this->pendampingB = $this->user('pendamping', 'b@sweep.test', $profilB->id);
        $this->pimpinan = $this->user('pimpinan', 'pimpinan@sweep.test');

        $this->binaanA = $this->peserta('Binaan A', $profilA->id);
        $this->binaanB = $this->peserta('Binaan B', $profilB->id);

        $this->pelatihanB = $this->binaanB->trainingRecords()->create([
            'tanggal' => '2026-05-01', 'nama_pelatihan' => 'Pelatihan B', 'status' => 'Terdaftar',
        ]);

        $this->omzetB = $this->binaanB->omzetReports()->create([
            'periode_bulan' => 5, 'periode_tahun' => 2026, 'omzet' => 1000000,
        ]);

        $this->unit = BusinessUnit::create([
            'nama' => 'Unit Uji', 'kode' => 'UNIT-SWEEP', 'tipe' => 'Barang', 'status' => 'Aktif',
        ]);

        $this->anggota = KoperasiMember::create([
            'nama' => 'Anggota Uji', 'nomor_hp' => '0812', 'tanggal_bergabung' => '2026-02-01',
            'status' => 'Aktif',
        ]);

        $this->simpanan = $this->anggota->savings()->create([
            'jenis_simpanan' => 'Simpanan Pokok', 'tanggal' => '2026-02-01', 'nominal' => 100000,
        ]);
    }

    private function user(string $role, string $email, ?int $pendampingId = null): User
    {
        return User::create([
            'name' => 'User ' . $email, 'email' => $email, 'password' => 'password123',
            'role' => $role, 'pendamping_id' => $pendampingId, 'is_active' => true,
        ]);
    }

    private function peserta(string $nama, ?int $pendampingId): Participant
    {
        return Participant::create([
            'nama_peserta' => $nama, 'nomor_hp' => '0812', 'nama_usaha' => 'Usaha ' . $nama,
            'kategori_usaha' => 'Jasa', 'tanggal_mulai_program' => '2026-01-10',
            'status' => 'Aktif', 'pendamping_id' => $pendampingId,
        ]);
    }

    /**
     * Route modul CAHAYA PRENEUR yang menyentuh data milik Pendamping B.
     * Semuanya harus ditolak untuk Pendamping A.
     *
     * @return array<string, array{0:string, 1:string, 2:array}>
     */
    private function routePreneurMilikOrangLain(): array
    {
        return [
            'Detail peserta' => ['get', route('participants.show', $this->binaanB), []],
            'Edit peserta' => ['get', route('participants.edit', $this->binaanB), []],
            'Update peserta' => ['put', route('participants.update', $this->binaanB), [
                'nama_peserta' => 'X', 'nomor_hp' => '0812', 'nama_usaha' => 'X',
                'kategori_usaha' => 'Jasa', 'tanggal_mulai_program' => '2026-01-01', 'status' => 'Aktif',
            ]],
            'Hapus peserta' => ['delete', route('participants.destroy', $this->binaanB), []],
            'Tambah pendampingan' => ['post', route('pendampingan.store', $this->binaanB), [
                'tanggal' => '2026-01-01', 'jenis_kegiatan' => 'Monitoring', 'materi' => 'X',
            ]],
            'Edit pelatihan' => ['get', route('training.edit', $this->pelatihanB), []],
            'Update pelatihan' => ['put', route('training.update', $this->pelatihanB), [
                'tanggal' => '2026-01-01', 'nama_pelatihan' => 'X', 'status' => 'Terdaftar',
            ]],
            'Ubah status pelatihan' => ['put', route('training.status', $this->pelatihanB), ['status' => 'Selesai']],
            'Hapus pelatihan' => ['delete', route('training.destroy', $this->pelatihanB), []],
            'Tambah pelatihan' => ['post', route('training.store', $this->binaanB), [
                'tanggal' => '2026-01-01', 'nama_pelatihan' => 'X', 'status' => 'Terdaftar',
            ]],
            'Form catat omzet' => ['get', route('omzet.create', $this->binaanB), []],
            'Simpan omzet' => ['post', route('omzet.store', $this->binaanB), [
                'periode_bulan' => 1, 'periode_tahun' => 2026, 'omzet' => 1000,
            ]],
            'Edit omzet' => ['get', route('omzet.edit', $this->omzetB), []],
            'Update omzet' => ['put', route('omzet.update', $this->omzetB), ['omzet' => 1]],
            'Hapus omzet' => ['delete', route('omzet.destroy', $this->omzetB), []],
        ];
    }

    /**
     * Route modul KOPERASI yang seluruhnya tertutup untuk role non-koperasi.
     *
     * @return array<string, array{0:string, 1:string, 2:array}>
     */
    private function routeKoperasi(): array
    {
        return [
            'Daftar unit usaha' => ['get', route('koperasi.unit-usaha.index'), []],
            'Edit unit usaha' => ['get', route('koperasi.unit-usaha.edit', $this->unit), []],
            'Update unit usaha' => ['put', route('koperasi.unit-usaha.update', $this->unit), [
                'nama' => 'X', 'kode' => 'X', 'tipe' => 'Jasa', 'status' => 'Aktif',
            ]],
            'Hapus unit usaha' => ['delete', route('koperasi.unit-usaha.destroy', $this->unit), []],
            'Pilih peserta jadi anggota' => ['get', route('koperasi.anggota.pilih-peserta'), []],
            'Hapus anggota' => ['delete', route('koperasi.anggota.destroy', $this->anggota), []],
            'Edit simpanan' => ['get', route('koperasi.savings.edit', $this->simpanan), []],
            'Update simpanan' => ['put', route('koperasi.savings.update', $this->simpanan), [
                'jenis_simpanan' => 'Simpanan Pokok', 'tanggal' => '2026-01-01', 'nominal' => 1,
            ]],
            'Batalkan simpanan' => ['post', route('koperasi.savings.batalkan', $this->simpanan), [
                'alasan_koreksi' => 'X',
            ]],
            'Kunci simpanan' => ['post', route('koperasi.savings.kunci', $this->simpanan), []],
        ];
    }

    private function tembak(User $user, string $metode, string $url, array $data)
    {
        return $this->actingAs($user)->{$metode}($url, $data);
    }

    // =====================================================================
    // PENDAMPING — terkurung pada binaannya sendiri
    // =====================================================================

    public function test_pendamping_ditolak_di_seluruh_route_milik_binaan_lain(): void
    {
        foreach ($this->routePreneurMilikOrangLain() as $label => [$metode, $url, $data]) {
            $this->tembak($this->pendampingA, $metode, $url, $data)
                ->assertForbidden("Pendamping A seharusnya DITOLAK: $label");
        }
    }

    /** Route yang sama, tapi menyentuh binaannya SENDIRI — harus diterima. */
    public function test_pendamping_diterima_di_route_yang_sama_untuk_binaannya_sendiri(): void
    {
        $this->actingAs($this->pendampingA)->get(route('participants.show', $this->binaanA))->assertOk();
        $this->actingAs($this->pendampingA)->get(route('participants.edit', $this->binaanA))->assertOk();
        $this->actingAs($this->pendampingA)->get(route('omzet.create', $this->binaanA))->assertOk();

        $this->actingAs($this->pendampingA)
            ->post(route('training.store', $this->binaanA), [
                'tanggal' => '2026-01-01', 'nama_pelatihan' => 'Pelatihan Sendiri', 'status' => 'Terdaftar',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('training_records', [
            'participant_id' => $this->binaanA->id, 'nama_pelatihan' => 'Pelatihan Sendiri',
        ]);
    }

    public function test_pendamping_ditolak_di_seluruh_route_koperasi(): void
    {
        foreach ($this->routeKoperasi() as $label => [$metode, $url, $data]) {
            $this->tembak($this->pendampingA, $metode, $url, $data)
                ->assertForbidden("Pendamping seharusnya DITOLAK di modul koperasi: $label");
        }
    }

    // =====================================================================
    // PIMPINAN — read-only, tidak pernah mengubah apa pun
    // =====================================================================

    public function test_pimpinan_ditolak_di_seluruh_route_yang_mengubah_data(): void
    {
        $mengubah = [
            'Edit peserta' => ['get', route('participants.edit', $this->binaanA), []],
            'Update peserta' => ['put', route('participants.update', $this->binaanA), [
                'nama_peserta' => 'X', 'nomor_hp' => '0812', 'nama_usaha' => 'X',
                'kategori_usaha' => 'Jasa', 'tanggal_mulai_program' => '2026-01-01', 'status' => 'Aktif',
            ]],
            'Tambah peserta' => ['get', route('participants.create'), []],
            'Edit pelatihan' => ['get', route('training.edit', $this->pelatihanB), []],
            'Hapus pelatihan' => ['delete', route('training.destroy', $this->pelatihanB), []],
            'Form catat omzet' => ['get', route('omzet.create', $this->binaanA), []],
            'Edit omzet' => ['get', route('omzet.edit', $this->omzetB), []],
            'Hapus omzet' => ['delete', route('omzet.destroy', $this->omzetB), []],
        ];

        foreach ($mengubah as $label => [$metode, $url, $data]) {
            $this->tembak($this->pimpinan, $metode, $url, $data)
                ->assertForbidden("Pimpinan seharusnya read-only: $label");
        }

        foreach ($this->routeKoperasi() as $label => [$metode, $url, $data]) {
            $this->tembak($this->pimpinan, $metode, $url, $data)
                ->assertForbidden("Pimpinan seharusnya DITOLAK di modul koperasi: $label");
        }
    }

    /** Yang read-only TETAP terbuka untuk Pimpinan — tidak ikut tertutup. */
    public function test_pimpinan_tidak_kehilangan_akses_baca(): void
    {
        $this->actingAs($this->pimpinan)->get(route('participants.show', $this->binaanA))->assertOk();
        $this->actingAs($this->pimpinan)->get(route('omzet.index'))->assertOk();
        $this->actingAs($this->pimpinan)->get(route('omzet.show', $this->omzetB))->assertOk();
        $this->actingAs($this->pimpinan)->get(route('dashboard'))->assertOk();
    }

    // =====================================================================
    // TIM KOPERASI — modul koperasi terbuka, Cahaya Preneur tertutup
    // =====================================================================

    public function test_tim_koperasi_diterima_di_seluruh_route_koperasi_baru(): void
    {
        foreach ([
            route('koperasi.unit-usaha.index'),
            route('koperasi.unit-usaha.index'),
            route('koperasi.unit-usaha.edit', $this->unit),
            route('koperasi.anggota.pilih-peserta'),
            route('koperasi.anggota.index'),
            route('koperasi.savings.edit', $this->simpanan),
        ] as $url) {
            $this->actingAs($this->timKoperasi)->get($url)->assertOk("Tim Koperasi seharusnya boleh: $url");
        }
    }

    public function test_tim_koperasi_ditolak_di_seluruh_route_preneur_baru(): void
    {
        foreach ($this->routePreneurMilikOrangLain() as $label => [$metode, $url, $data]) {
            $this->tembak($this->timKoperasi, $metode, $url, $data)
                ->assertForbidden("Tim Koperasi seharusnya DITOLAK: $label");
        }
    }

    // =====================================================================
    // ADMIN — akses global, tidak ada yang ikut tertutup
    // =====================================================================

    public function test_admin_diterima_di_seluruh_route_baru(): void
    {
        foreach ([
            route('participants.index'),
            route('participants.edit', $this->binaanB),
            route('training.edit', $this->pelatihanB),
            route('omzet.create', $this->binaanB),
            route('omzet.edit', $this->omzetB),
            route('koperasi.unit-usaha.edit', $this->unit),
            route('koperasi.unit-usaha.index'),
            route('koperasi.anggota.pilih-peserta'),
            route('koperasi.savings.edit', $this->simpanan),
        ] as $url) {
            $this->actingAs($this->admin)->get($url)->assertOk("Admin kehilangan akses: $url");
        }
    }

    // =====================================================================
    // TAMU — seluruh route baru tetap butuh login
    // =====================================================================

    public function test_tamu_diarahkan_ke_login_di_seluruh_route_baru(): void
    {
        $semua = array_merge($this->routePreneurMilikOrangLain(), $this->routeKoperasi());

        foreach ($semua as $label => [$metode, $url, $data]) {
            $respons = $this->{$metode}($url, $data);

            $this->assertContains(
                $respons->getStatusCode(),
                [302, 419],
                "Tamu seharusnya tidak bisa menembus: $label (status {$respons->getStatusCode()})",
            );
        }

        // Dan tidak ada satu pun perubahan yang tembus.
        $this->assertSame('Binaan B', $this->binaanB->fresh()->nama_peserta);
        $this->assertSame('Pelatihan B', $this->pelatihanB->fresh()->nama_pelatihan);
        $this->assertSame('Unit Uji', $this->unit->fresh()->nama);
        $this->assertEqualsWithDelta(100000, (float) $this->simpanan->fresh()->nominal, 0.01);
    }
}
