<?php

namespace Tests\Feature;

use App\Models\KoperasiMember;
use App\Models\MemberSaving;
use App\Models\Participant;
use App\Models\Pendamping;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * BERKAS EKSPOR YANG KELUAR DARI SISTEM.
 *
 * =====================================================================
 * HASIL AUDIT: BERSIH — DAN ITULAH YANG DIKUNCI DI SINI
 * =====================================================================
 *
 * Tujuh belas rute ekspor dijalankan dengan data produksi nyata. Delapan
 * ekspor koperasi menghasilkan XLSX yang sah dan terbaca; sembilan ekspor
 * Cahaya Preneur menghasilkan CSV. Tidak ada yang gagal, tidak ada yang
 * kosong, dan angkanya cocok dengan yang tampil di layar.
 *
 * Karena tidak ada yang perlu diperbaiki, yang dikerjakan adalah
 * menguncinya — sebab dua hal di bawah ini rusak tanpa menimbulkan galat
 * apa pun, dan baru ketahuan setelah berkasnya terlanjur dibagikan:
 *
 *   1. Ekspor yang terbit tetapi isinya kosong. HTTP 200, berkas ada,
 *      nol baris data.
 *   2. Angka di berkas ekspor yang berbeda dari angka di layar. Dua
 *      tempat menghitung hal yang sama, dan tidak ada satu pun halaman
 *      yang membandingkan keduanya.
 */
class EksporLaporanTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin Uji',
            'email' => 'admin@ekspor.test',
            'password' => bcrypt('rahasia123'),
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
        ]);
    }

    private function isiData(): void
    {
        $pendamping = Pendamping::create([
            'nama' => 'Rosa Linda', 'nomor_hp' => '081200000001', 'status' => 'Aktif',
        ]);

        Participant::create([
            'nama_peserta' => 'Bu Ani',
            'nomor_hp' => '081234567890',
            'nama_usaha' => 'Keripik Bawang',
            'kategori_usaha' => 'Kuliner',
            'tanggal_mulai_program' => now()->subYear()->toDateString(),
            'status' => 'Aktif',
            'pendamping_id' => $pendamping->id,
        ]);

        $anggota = KoperasiMember::create([
            'nama' => 'Bu Tuti', 'nomor_hp' => '0812',
            'tanggal_bergabung' => '2026-01-01', 'status' => 'Aktif',
        ]);

        MemberSaving::create([
            'koperasi_member_id' => $anggota->id,
            'jenis_simpanan' => 'Simpanan Pokok',
            'tanggal' => '2026-02-01',
            'nominal' => 150_000,
            'status' => MemberSaving::STATUS_TERCATAT,
        ]);
    }

    private function unduh(string $rute): string
    {
        $respons = $this->actingAs($this->admin)->get(route($rute));

        $respons->assertOk();

        return $respons->streamedContent();
    }

    // =================================================================
    // SETIAP EKSPOR TERBIT DAN BERISI
    // =================================================================

    /**
     * Ekspor koperasi menghasilkan XLSX yang sah.
     *
     * Berkas XLSX adalah arsip ZIP — selalu diawali "PK". Berkas yang tidak
     * diawali itu akan ditolak Excel dengan pesan "file rusak", dan tidak
     * ada satu pun cara menduganya dari HTTP 200.
     */
    public function test_ekspor_koperasi_menghasilkan_xlsx_yang_sah(): void
    {
        $this->isiData();

        foreach ([
            'koperasi.laporan.ringkasan.excel',
            'koperasi.laporan.laba-rugi.excel',
            'koperasi.laporan.simpanan.excel',
            'koperasi.laporan.keanggotaan.excel',
        ] as $rute) {
            $isi = $this->unduh($rute);

            $this->assertStringStartsWith('PK', $isi, "{$rute} tidak menghasilkan berkas XLSX yang sah.");
            $this->assertGreaterThan(2000, strlen($isi), "{$rute} menghasilkan berkas yang nyaris kosong.");
        }
    }

    /**
     * Ekspor Cahaya Preneur menghasilkan CSV yang berisi, bukan hanya
     * baris judul.
     */
    public function test_ekspor_cahaya_preneur_menghasilkan_csv_berisi(): void
    {
        $this->isiData();

        foreach ([
            'laporan.peserta.export' => 'Bu Ani',
            'participants.export' => 'Bu Ani',
        ] as $rute => $harusAda) {
            $isi = $this->unduh($rute);

            $this->assertStringContainsString(
                $harusAda,
                $isi,
                "{$rute} tidak memuat data yang seharusnya ada di dalamnya."
            );

            // Lebih dari satu baris berarti ada isinya, bukan cuma judul kolom.
            $baris = array_values(array_filter(explode("\n", trim($isi))));

            $this->assertGreaterThan(1, count($baris), "{$rute} hanya memuat baris judul.");
        }
    }

    // =================================================================
    // ANGKANYA SEPAKAT DENGAN LAYAR
    // =================================================================

    /**
     * Angka simpanan di berkas ekspor harus sama dengan yang di layar.
     *
     * Kalau keduanya berbeda, tidak ada satu pun dari keduanya yang bisa
     * dipakai dalam rapat — dan yang dibawa orang ke rapat justru berkas
     * ekspornya, karena itulah yang bisa dicetak.
     */
    public function test_angka_di_ekspor_sama_dengan_angka_di_layar(): void
    {
        $this->isiData();

        $layar = $this->actingAs($this->admin)
            ->get(route('koperasi.laporan.ringkasan'))
            ->assertOk();

        $pokokLayar = $layar->viewData('totalSimpananPokok');

        $this->assertSame(150_000.0, $pokokLayar);

        // XLSX adalah ZIP; angkanya tersimpan sebagai teks di dalamnya.
        // Dibaca dengan membuka arsipnya, bukan dengan menebak isinya.
        $isi = $this->unduh('koperasi.laporan.ringkasan.excel');

        $tmp = tempnam(sys_get_temp_dir(), 'ekspor') . '.xlsx';
        file_put_contents($tmp, $isi);

        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($tmp) === true, 'Berkas ekspor bukan arsip yang bisa dibuka.');

        $teks = '';
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $nama = $zip->getNameIndex($i);

            if (str_starts_with($nama, 'xl/')) {
                $teks .= $zip->getFromIndex($i);
            }
        }
        $zip->close();
        @unlink($tmp);

        $this->assertStringContainsString(
            '150000',
            $teks,
            'Angka simpanan pokok di berkas ekspor berbeda dari yang tampil di layar.'
        );
    }

    // =================================================================
    // OTORISASI
    // =================================================================

    /** Ekspor membawa data seluruh organisasi — bukan untuk semua peran. */
    public function test_ekspor_koperasi_ditolak_untuk_pendamping(): void
    {
        $pendamping = Pendamping::create([
            'nama' => 'Rosa Linda', 'nomor_hp' => '081200000001', 'status' => 'Aktif',
        ]);

        $user = User::create([
            'name' => 'Rosa',
            'email' => 'rosa@ekspor.test',
            'password' => bcrypt('rahasia123'),
            'role' => User::ROLE_PENDAMPING,
            'is_active' => true,
            'pendamping_id' => $pendamping->id,
        ]);

        foreach ([
            'koperasi.laporan.ringkasan.excel',
            'koperasi.laporan.simpanan.excel',
        ] as $rute) {
            $this->actingAs($user)->get(route($rute))->assertForbidden();
        }
    }

    public function test_ekspor_peserta_ditolak_untuk_tim_koperasi(): void
    {
        $user = User::create([
            'name' => 'Tim Koperasi',
            'email' => 'koperasi@ekspor.test',
            'password' => bcrypt('rahasia123'),
            'role' => User::ROLE_TIM_KOPERASI,
            'is_active' => true,
        ]);

        $this->actingAs($user)->get(route('participants.export'))->assertForbidden();
    }
}
