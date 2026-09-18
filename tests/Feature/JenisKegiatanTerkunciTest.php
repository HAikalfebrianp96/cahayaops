<?php

namespace Tests\Feature;

use App\Models\Participant;
use App\Models\Pendamping;
use App\Models\PendampinganRecord;
use App\Models\PendampingTarget;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * JENIS KEGIATAN — YANG DISARING HARUS SELALU ADA DI DAFTARNYA.
 *
 * =====================================================================
 * KEGAGALAN YANG DIJAGA
 * =====================================================================
 *
 * Lima tempat di aplikasi ini menyaring catatan berdasarkan jenisnya.
 * Selama nilainya ditulis sebagai string di masing-masing tempat, mengganti
 * satu nama di JENIS_KEGIATAN — misalnya "Kunjungan Usaha" jadi "Kunjungan" —
 * membuat kelimanya mengembalikan NOL.
 *
 * Tidak ada error, tidak ada halaman yang rusak. Angkanya sekadar menjadi
 * nol dan tetap terlihat masuk akal di layar: seorang pendamping yang rajin
 * berkunjung terbaca tidak pernah berkunjung sama sekali.
 *
 * Penyaringnya sekarang memakai konstanta, dan test ini memastikan konstanta
 * itu memang salah satu pilihan yang sah.
 */
class JenisKegiatanTerkunciTest extends TestCase
{
    use RefreshDatabase;

    private Pendamping $profil;
    private Participant $peserta;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->profil = Pendamping::create([
            'nama' => 'Pendamping Uji', 'nomor_hp' => '081200000001',
            'email' => 'p@jenis.test', 'status' => 'Aktif',
        ]);

        $this->admin = User::create([
            'name' => 'Admin Uji', 'email' => 'admin@jenis.test',
            'password' => 'password123', 'role' => 'admin', 'is_active' => true,
        ]);

        $this->peserta = Participant::create([
            'nama_peserta' => 'Peserta Uji', 'nomor_hp' => '081200000002',
            'alamat' => 'Jl. Uji', 'nama_usaha' => 'Usaha Uji',
            'kategori_usaha' => 'Jasa',
            'tanggal_mulai_program' => now()->subMonths(6)->toDateString(),
            'status' => 'Aktif', 'pendamping_id' => $this->profil->id,
        ]);
    }

    private function catat(string $jenis): void
    {
        PendampinganRecord::create([
            'participant_id' => $this->peserta->id,
            'pendamping_id' => $this->profil->id,
            'tanggal' => now()->toDateString(),
            'jenis_kegiatan' => $jenis,
            'materi' => 'Materi uji',
        ]);
    }

    /**
     * Setiap konstanta yang dipakai menyaring harus ada di JENIS_KEGIATAN.
     *
     * Kalau ada yang mengganti nilainya di satu tempat saja, test ini gagal
     * di sini — bukan berbulan-bulan kemudian lewat angka nol yang aneh.
     */
    public function test_konstanta_penyaring_ada_di_daftar_jenis_kegiatan(): void
    {
        foreach ([
            PendampinganRecord::ASESMEN,
            PendampinganRecord::KUNJUNGAN_USAHA,
            PendampinganRecord::KONSULTASI,
            PendampinganRecord::PENDAMPINGAN,
            PendampinganRecord::MONITORING,
            PendampinganRecord::EVALUASI,
            PendampinganRecord::LAINNYA,
        ] as $jenis) {
            $this->assertContains(
                $jenis,
                PendampinganRecord::JENIS_KEGIATAN,
                "Jenis \"{$jenis}\" dipakai menyaring tapi bukan pilihan yang sah."
            );
        }
    }

    /**
     * Daftarnya tidak boleh punya nilai yang tidak dinamai.
     *
     * Jenis yang hanya ada sebagai string di daftar — tanpa konstanta —
     * tidak bisa disaring dengan aman di tempat lain.
     */
    public function test_setiap_jenis_di_daftar_punya_konstanta(): void
    {
        $berkonstanta = [
            PendampinganRecord::ASESMEN,
            PendampinganRecord::KUNJUNGAN_USAHA,
            PendampinganRecord::KONSULTASI,
            PendampinganRecord::PENDAMPINGAN,
            PendampinganRecord::MONITORING,
            PendampinganRecord::EVALUASI,
            PendampinganRecord::LAINNYA,
        ];

        $this->assertSame(
            [],
            array_diff(PendampinganRecord::JENIS_KEGIATAN, $berkonstanta),
            'Ada jenis kegiatan di daftar yang belum punya konstanta.'
        );
    }

    // =================================================================
    // PERILAKU PERHITUNGAN — DIKUNCI SUPAYA MENJADI KEPUTUSAN, BUKAN
    // KEBETULAN
    // =================================================================

    /**
     * KPI "Kunjungan Usaha" HANYA menghitung kunjungan usaha.
     *
     * Ini penyaring yang paling mudah rusak diam-diam, dan paling terasa
     * akibatnya: ia menilai kerja lapangan seseorang.
     */
    public function test_kpi_kunjungan_hanya_menghitung_kunjungan_usaha(): void
    {
        $this->catat(PendampinganRecord::KUNJUNGAN_USAHA);
        $this->catat(PendampinganRecord::KUNJUNGAN_USAHA);
        $this->catat(PendampinganRecord::KONSULTASI);
        $this->catat(PendampinganRecord::LAINNYA);

        $rapor = $this->rapor();

        $this->assertSame(2, $rapor['indikator']['target_kunjungan']['aktual']);
    }

    /**
     * KPI "Pendampingan" menghitung SELURUH catatan, termasuk 'Lainnya'.
     *
     * Perilaku ini sebelumnya tidak tertulis di mana pun — dan config yang
     * kini dihapus justru menjanjikan sebaliknya. Dikunci di sini supaya
     * menjadi keputusan yang terbaca, bukan kebetulan.
     *
     * Kalau suatu saat 'Lainnya' memang perlu dikecualikan, test ini yang
     * akan gagal lebih dulu — dan itu memang harus, karena angka KPI seluruh
     * pendamping ikut berubah.
     */
    public function test_kpi_pendampingan_menghitung_semua_jenis_termasuk_lainnya(): void
    {
        $this->catat(PendampinganRecord::PENDAMPINGAN);
        $this->catat(PendampinganRecord::KUNJUNGAN_USAHA);
        $this->catat(PendampinganRecord::LAINNYA);

        $rapor = $this->rapor();

        $this->assertSame(
            3,
            $rapor['indikator']['target_pendampingan']['aktual'],
            "Seluruh catatan dihitung, apa pun jenisnya — termasuk 'Lainnya'."
        );
    }

    private function rapor(): array
    {
        PendampingTarget::create([
            'pendamping_id' => $this->profil->id,
            'periode' => PendampingTarget::PERIODE_BULANAN,
            'tahun' => now()->year,
            'bulan' => now()->month,
            'target_pendampingan' => 10,
            'target_kunjungan' => 10,
        ]);

        return $this->actingAs($this->admin)
            ->get(route('kpi-pendamping.show', $this->profil))
            ->assertOk()
            ->viewData('rapor');
    }

    /**
     * NILAI TERSIMPANNYA TIDAK BOLEH BERUBAH.
     *
     * =================================================================
     * KENAPA TEKSNYA DITULIS ULANG DI SINI
     * =================================================================
     *
     * Test-test di atas hanya menjaga konsistensi INTERNAL: penyaring dan
     * daftar pilihan selalu menunjuk nilai yang sama. Tapi JENIS_KEGIATAN
     * sekarang dibangun DARI konstantanya, jadi mengganti nama sebuah
     * konstanta akan mengubah keduanya serempak — dan seluruh test di atas
     * tetap lulus dengan tenang.
     *
     * Yang tidak ikut berubah: ratusan baris di database yang sudah
     * menyimpan teks lamanya. Begitu konstantanya diganti, baris-baris itu
     * tidak lagi cocok dengan penyaring mana pun, dan KPI kunjungan seluruh
     * pendamping menjadi nol tanpa satu pun error.
     *
     * Karena itu teksnya sengaja ditulis ulang apa adanya di sini. Test ini
     * memang "menduplikasi" nilainya, dan itulah gunanya: ia menjadi pihak
     * kedua yang harus ikut setuju sebelum nilai tersimpan boleh berubah.
     *
     * Kalau memang perlu diganti, datanya harus dimigrasikan lebih dulu.
     */
    public function test_nilai_tersimpan_jenis_kegiatan_tidak_boleh_berubah(): void
    {
        $this->assertSame('Asesmen', PendampinganRecord::ASESMEN);
        $this->assertSame('Kunjungan Usaha', PendampinganRecord::KUNJUNGAN_USAHA);
        $this->assertSame('Konsultasi', PendampinganRecord::KONSULTASI);
        $this->assertSame('Pendampingan', PendampinganRecord::PENDAMPINGAN);
        $this->assertSame('Monitoring', PendampinganRecord::MONITORING);
        $this->assertSame('Evaluasi', PendampinganRecord::EVALUASI);
        $this->assertSame('Lainnya', PendampinganRecord::LAINNYA);
    }

    /**
     * Config 'aktivitas_lapangan' memang sudah tidak ada.
     *
     * Ia tidak pernah dibaca satu baris kode pun, tapi keterangannya
     * menjanjikan penyaring yang tidak ada. Test ini mencegahnya kembali
     * tanpa ada yang benar-benar memakainya.
     */
    public function test_config_aktivitas_lapangan_yang_menyesatkan_tidak_kembali(): void
    {
        $this->assertNull(
            config('cahayaos.aktivitas_lapangan'),
            "Kalau daftar ini dihidupkan lagi, pastikan ADA kode yang benar-benar membacanya."
        );
    }
}
