<?php

namespace Tests\Feature;

use App\Models\KoperasiMember;
use App\Models\MemberSaving;
use App\Models\ShuPeriod;
use App\Services\ShuService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SHU YANG DIBAGIKAN HARUS UTUH.
 *
 * =====================================================================
 * YANG DIJAGA
 * =====================================================================
 *
 * Pembagian SHU bersifat proporsional: setiap anggota menerima
 * pool * simpanannya / seluruh simpanan. Hasil bagi itu hampir tidak pernah
 * bulat, jadi setiap bagian harus dibulatkan.
 *
 * Kalau tiap bagian dibulatkan sendiri-sendiri, jumlah seluruh bagian TIDAK
 * sama dengan pool-nya. Selisihnya kecil — beberapa rupiah — tapi ini uang
 * anggota yang nominalnya ditetapkan Rapat Anggota. Jumlah yang dibagikan
 * harus sama persis dengan yang disahkan, bukan "kurang lebih".
 *
 * Test ini memakai angka yang memang sulit dibagi, karena pembagian yang
 * kebetulan bulat tidak membuktikan apa pun.
 */
class ShuPembulatanTest extends TestCase
{
    use RefreshDatabase;

    /** Buat anggota dengan simpanan pokok sebesar $nominal. */
    private function anggota(string $nama, int $nominal): KoperasiMember
    {
        $anggota = KoperasiMember::create([
            'nama' => $nama,
            'nomor_hp' => '08120000' . substr(md5($nama), 0, 4),
            'tanggal_bergabung' => now()->subYear()->toDateString(),
            'status' => 'Aktif',
        ]);

        MemberSaving::create([
            'koperasi_member_id' => $anggota->id,
            'jenis_simpanan' => 'Simpanan Pokok',
            'nominal' => $nominal,
            'tanggal' => now()->subMonths(6)->toDateString(),
        ]);

        return $anggota;
    }

    private function periode(int $totalShu): ShuPeriod
    {
        return ShuPeriod::create([
            'tahun' => now()->year,
            'tanggal_penetapan' => now()->toDateString(),
            'total_shu' => $totalShu,
            'status' => ShuPeriod::STATUS_DRAFT,
            'komponen' => [
                [
                    'nama' => 'Jasa Modal',
                    'persen' => 100,
                    'ke_anggota' => true,
                    'dasar' => ShuPeriod::DASAR_SIMPANAN,
                ],
            ],
        ]);
    }

    /**
     * Tiga anggota dengan simpanan sama, pool yang tidak habis dibagi tiga.
     *
     * Kasus paling sederhana yang bisa memperlihatkan sisa pembulatan.
     */
    public function test_pembagian_tiga_anggota_sama_rata_tetap_utuh(): void
    {
        foreach (['Ani', 'Budi', 'Cita'] as $nama) {
            $this->anggota($nama, 1000000);
        }

        $periode = $this->periode(1000000);

        app(ShuService::class)->susunAlokasi($periode);

        $this->assertSame(
            1000000,
            (int) $periode->allocations()->sum('total'),
            'Jumlah seluruh bagian anggota harus sama persis dengan SHU yang dibagikan.'
        );
    }

    /**
     * Simpanan yang ganjil-ganjil — bentuk yang paling mungkin terjadi
     * di koperasi sungguhan.
     */
    public function test_pembagian_dengan_simpanan_ganjil_tetap_utuh(): void
    {
        $this->anggota('Ani', 1234567);
        $this->anggota('Budi', 765433);
        $this->anggota('Cita', 999999);
        $this->anggota('Dedi', 1);

        $periode = $this->periode(7777777);

        app(ShuService::class)->susunAlokasi($periode);

        $this->assertSame(
            7777777,
            (int) $periode->allocations()->sum('total'),
            'Sisa pembulatan tidak boleh hilang dari total yang dibagikan.'
        );
    }

    /**
     * Banyak anggota memperbesar sisanya: makin banyak yang dibulatkan,
     * makin besar kemungkinan selisihnya menumpuk.
     */
    public function test_pembagian_banyak_anggota_tetap_utuh(): void
    {
        foreach (range(1, 17) as $i) {
            $this->anggota('Anggota ' . $i, 100000 + $i * 7777);
        }

        $periode = $this->periode(50000000);

        app(ShuService::class)->susunAlokasi($periode);

        $this->assertSame(
            50000000,
            (int) $periode->allocations()->sum('total'),
            'Sisa pembulatan dari 17 anggota tidak boleh hilang.'
        );
    }

    /**
     * Hasilnya harus SAMA setiap kali disusun ulang.
     *
     * Penetapan Draft memang disusun berkali-kali sampai angkanya benar.
     * Kalau penerima sisa rupiah berpindah-pindah tiap kali tombol ditekan,
     * angka yang dilihat pengurus berubah tanpa ada yang mengubah data.
     */
    public function test_penyusunan_ulang_memberi_hasil_yang_sama(): void
    {
        $this->anggota('Ani', 1234567);
        $this->anggota('Budi', 765433);
        $this->anggota('Cita', 999999);

        $periode = $this->periode(7777777);
        $layanan = app(ShuService::class);

        $layanan->susunAlokasi($periode);
        $pertama = $periode->allocations()->orderBy('koperasi_member_id')->pluck('total')->all();

        $layanan->susunAlokasi($periode);
        $kedua = $periode->allocations()->orderBy('koperasi_member_id')->pluck('total')->all();

        $this->assertSame($pertama, $kedua);
    }

    // =================================================================
    // NOMINAL KOMPONEN
    // =================================================================

    /**
     * Jumlah seluruh komponen harus sama dengan SHU yang dibagikan.
     *
     * Bug yang sama bentuknya, satu tingkat di atas alokasi anggota: tiap
     * komponen dulu dibulatkan sendiri, sehingga jumlahnya meleset — dan
     * pernah MELEBIHI total SHU yang benar-benar ada.
     */
    public function test_jumlah_nominal_komponen_sama_dengan_total_shu(): void
    {
        foreach ([10000001, 7777777, 12345679, 999999999, 1] as $total) {
            $periode = new ShuPeriod([
                'total_shu' => $total,
                'komponen' => ShuPeriod::KOMPONEN_USULAN,
            ]);

            $this->assertSame(
                $total,
                array_sum($periode->nominalKomponenSemua()),
                "Jumlah komponen harus sama persis dengan total SHU {$total}."
            );
        }
    }

    /**
     * Persentase yang belum 100% TIDAK boleh dipaksa menjadi 100%.
     *
     * Kalau Rapat Anggota baru menetapkan 95%, sisanya memang sengaja tidak
     * dialokasikan. Memaksanya penuh berarti membagikan uang yang tidak
     * diputuskan siapa pun — peringatannya sudah ada, sistem tidak boleh
     * diam-diam "membetulkan" keputusan itu.
     */
    public function test_persentase_kurang_dari_seratus_tidak_dipaksa_penuh(): void
    {
        $periode = new ShuPeriod([
            'total_shu' => 10000001,
            'komponen' => [
                ['nama' => 'Cadangan', 'persen' => 50, 'ke_anggota' => false, 'dasar' => null],
                ['nama' => 'Dana Sosial', 'persen' => 45, 'ke_anggota' => false, 'dasar' => null],
            ],
        ]);

        $this->assertSame(9500001, array_sum($periode->nominalKomponenSemua()));
    }

    /**
     * Tidak boleh ada yang menerima nominal negatif akibat penyesuaian sisa.
     */
    public function test_tidak_ada_bagian_negatif(): void
    {
        $this->anggota('Ani', 1);
        $this->anggota('Budi', 999999999);

        $periode = $this->periode(1000);

        app(ShuService::class)->susunAlokasi($periode);

        foreach ($periode->allocations()->get() as $alokasi) {
            $this->assertGreaterThanOrEqual(0, $alokasi->jasa_modal);
            $this->assertGreaterThanOrEqual(0, $alokasi->jasa_usaha);
            $this->assertGreaterThanOrEqual(0, $alokasi->total);
        }
    }
}
