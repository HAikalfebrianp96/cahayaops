<?php

namespace Tests\Feature;

use App\Models\KoperasiMember;
use App\Models\MemberSaving;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * UANG YANG PEMILIKNYA SUDAH BUKAN ANGGOTA LAGI.
 *
 * =====================================================================
 * KEADAAN YANG DITEMUKAN
 * =====================================================================
 *
 * Mengarsipkan anggota koperasi TIDAK mengembalikan simpanannya. Barisnya
 * tetap berlaku, dan seluruh laporan menjumlahkannya lewat berlaku(), yang
 * hanya menyaring entri yang dibatalkan — bukan entri milik anggota yang
 * sudah diarsipkan.
 *
 * Akibatnya satu halaman bisa berbunyi "Total Anggota 1" berdampingan dengan
 * simpanan yang tak satu pun miliknya.
 *
 * Yang membuatnya lebih serius: ShuService SUDAH lama menyaring hal ini, dan
 * menolak membagi komponen jasa modal dengan alasan "anggota terarsip tidak
 * menerima pembagian SHU". Jadi dua halaman bicara tentang uang yang sama
 * dan sampai pada kesimpulan yang berbeda — satu menyebutnya simpanan
 * anggota, satu lagi menyatakan tidak ada pemiliknya.
 *
 * =====================================================================
 * KENAPA ANGKANYA TIDAK DIKURANGI
 * =====================================================================
 *
 * Karena mengurangi justru salah ke arah sebaliknya. Koperasi memang masih
 * MEMEGANG dana itu sampai dikembalikan; menghilangkannya dari laporan
 * membuat uang yang nyata lenyap dari pembukuan.
 *
 * Yang diperbaiki adalah keterangannya. Angkanya tetap, tapi pembacanya
 * diberi tahu berapa bagian yang pemiliknya sudah tidak lagi anggota.
 */
class SimpananAnggotaTerarsipTest extends TestCase
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

    private function anggota(string $nama): KoperasiMember
    {
        return KoperasiMember::create([
            'nama' => $nama,
            'nomor_hp' => '0812',
            'tanggal_bergabung' => '2026-01-01',
            'status' => 'Aktif',
        ]);
    }

    private function simpanan(KoperasiMember $anggota, string $jenis, int $nominal): MemberSaving
    {
        return MemberSaving::create([
            'koperasi_member_id' => $anggota->id,
            'jenis_simpanan' => $jenis,
            'tanggal' => '2026-02-01',
            'nominal' => $nominal,
            'status' => MemberSaving::STATUS_TERCATAT,
        ]);
    }

    // =================================================================
    // SATU DEFINISI, DIPAKAI BERSAMA
    // =================================================================

    public function test_scope_menemukan_simpanan_milik_anggota_terarsip(): void
    {
        $tetap = $this->anggota('Bu Ani');
        $keluar = $this->anggota('Bu Sinta');

        $this->simpanan($tetap, 'Simpanan Pokok', 50_000);
        $this->simpanan($keluar, 'Simpanan Pokok', 70_000);

        $keluar->delete();

        $this->assertSame(
            70_000.0,
            (float) MemberSaving::berlaku()->milikAnggotaTerarsip()->sum('nominal')
        );

        // Totalnya sendiri TIDAK berubah — uangnya masih dipegang koperasi.
        $this->assertSame(
            120_000.0,
            (float) MemberSaving::berlaku()->sum('nominal')
        );
    }

    /** Entri yang dibatalkan tidak ikut, walau pemiliknya terarsip. */
    public function test_entri_dibatalkan_tidak_ikut_terhitung(): void
    {
        $keluar = $this->anggota('Bu Sinta');

        $this->simpanan($keluar, 'Simpanan Pokok', 70_000)
            ->update(['status' => MemberSaving::STATUS_DIBATALKAN]);

        $keluar->delete();

        $this->assertSame(
            0.0,
            (float) MemberSaving::berlaku()->milikAnggotaTerarsip()->sum('nominal')
        );
    }

    /** Anggota yang masih ada tidak boleh ikut terhitung sebagai terarsip. */
    public function test_anggota_aktif_tidak_ikut_terhitung(): void
    {
        $tetap = $this->anggota('Bu Ani');
        $this->simpanan($tetap, 'Simpanan Wajib', 25_000);

        $this->assertSame(
            0.0,
            (float) MemberSaving::berlaku()->milikAnggotaTerarsip()->sum('nominal')
        );
    }

    // =================================================================
    // KETERANGAN MUNCUL DI HALAMAN YANG MENAMPILKAN ANGKANYA
    // =================================================================

    private function siapkanKeadaanNyata(): void
    {
        // Meniru keadaan produksi: yang tersisa sebagai anggota tidak memegang
        // simpanan apa pun, sementara seluruh simpanan milik anggota terarsip.
        $this->anggota('Bu Ani');

        $keluar = $this->anggota('Bu Sinta');
        $this->simpanan($keluar, 'Simpanan Pokok', 50_000);
        $this->simpanan($keluar, 'Simpanan Wajib', 10_000);
        $keluar->delete();
    }

    public function test_dashboard_koperasi_menerangkan_simpanan_terarsip(): void
    {
        $this->siapkanKeadaanNyata();

        $halaman = $this->actingAs($this->timKoperasi)
            ->get(route('dashboard'))
            ->assertOk();

        $this->assertSame(
            60_000.0,
            $halaman->viewData('stats')['simpanan_terarsip']
        );

        $halaman->assertSee('sudah diarsipkan', false);
    }

    public function test_laporan_koperasi_menerangkan_simpanan_terarsip(): void
    {
        $this->siapkanKeadaanNyata();

        $halaman = $this->actingAs($this->timKoperasi)
            ->get(route('koperasi.laporan.ringkasan'))
            ->assertOk();

        $this->assertSame(60_000.0, $halaman->viewData('simpananTerarsip'));

        // Angkanya sendiri TIDAK dikurangi.
        $this->assertSame(50_000.0, $halaman->viewData('totalSimpananPokok'));
        $this->assertSame(10_000.0, $halaman->viewData('totalSimpananWajib'));

        $halaman->assertSee('sudah diarsipkan', false);
    }

    public function test_halaman_keuangan_menerangkan_simpanan_terarsip(): void
    {
        $this->siapkanKeadaanNyata();

        $halaman = $this->actingAs($this->timKoperasi)
            ->get(route('koperasi.keuangan.index'))
            ->assertOk();

        $this->assertSame(60_000.0, $halaman->viewData('simpananTerarsip'));
        $halaman->assertSee('sudah diarsipkan', false);
    }

    /**
     * Kontrol pembanding: tanpa anggota terarsip, tidak ada keterangan apa pun.
     *
     * Tanpa test ini, keterangan yang SELALU muncul juga akan lulus seluruh
     * assertion di atas — dan halaman koperasi yang sehat jadi memuat
     * peringatan yang tidak ada dasarnya.
     */
    public function test_tanpa_anggota_terarsip_tidak_ada_keterangan(): void
    {
        $tetap = $this->anggota('Bu Ani');
        $this->simpanan($tetap, 'Simpanan Pokok', 50_000);

        foreach ([
            route('dashboard'),
            route('koperasi.laporan.ringkasan'),
            route('koperasi.keuangan.index'),
        ] as $url) {
            $this->actingAs($this->timKoperasi)
                ->get($url)
                ->assertOk()
                ->assertDontSee('sudah diarsipkan', false);
        }
    }

    // =================================================================
    // SHU DAN LAPORAN MEMAKAI DASAR YANG SAMA
    // =================================================================

    /**
     * Angka yang diterangkan di laporan harus SAMA dengan angka yang dipakai
     * SHU untuk menolak pembagian jasa modal.
     *
     * Kalau keduanya dihitung terpisah, cepat atau lambat salah satunya
     * diperbarui sendirian dan dua halaman kembali berbeda pendapat tentang
     * uang yang sama — persis keadaan yang membuat perbaikan ini dibutuhkan.
     */
    public function test_laporan_dan_shu_memakai_angka_yang_sama(): void
    {
        $this->siapkanKeadaanNyata();

        $dariLaporan = $this->actingAs($this->timKoperasi)
            ->get(route('koperasi.laporan.ringkasan'))
            ->viewData('simpananTerarsip');

        // Dasar yang sama persis dengan yang dipakai ShuService untuk
        // memperingatkan bahwa jasa modal tidak dapat dibagikan.
        $dariShu = (float) MemberSaving::berlaku()
            ->milikAnggotaTerarsip()
            ->whereIn('jenis_simpanan', ['Simpanan Pokok', 'Simpanan Wajib'])
            ->sum('nominal');

        $this->assertSame($dariShu, $dariLaporan);
    }
}
