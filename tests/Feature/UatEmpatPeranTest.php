<?php

namespace Tests\Feature;

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
 * TEMUAN UAT EMPAT PERAN.
 *
 * =====================================================================
 * DUA HAL YANG DITEMUKAN SAAT MENELUSURI ALUR TIAP PERAN
 * =====================================================================
 *
 * 1. Kartu Koperasi di Dashboard Pimpinan menyebut anggota, penjualan,
 *    unit usaha, laba kotor, dan pengeluaran — tetapi TIDAK simpanan.
 *    Padahal simpanan pokok dan wajib adalah modal yang disetor anggota
 *    sendiri, dan salah satu ukuran kesehatan koperasi yang paling
 *    ditanyakan di Rapat Anggota. Pimpinan yang perlu tahu angka itu
 *    harus membuka halaman operasional yang bukan wilayahnya.
 *
 * 2. Delapan tabel dirender tanpa wadah geser, empat di antaranya
 *    berkolom lima sampai delapan — dua di halaman detail peserta, yang
 *    justru paling sering dibuka Pendamping dari ponsel di lapangan.
 *    Tabel yang lebih lebar dari layar menggeser SELURUH halaman ke
 *    samping, bukan cuma tabelnya.
 *
 * Enam puluh empat tabel lain sudah memakai pembungkus itu, jadi yang
 * kurang bukan konsepnya melainkan penerapannya di delapan tempat.
 */
class UatEmpatPeranTest extends TestCase
{
    use RefreshDatabase;

    private function buatUser(string $role, string $email, ?int $pendampingId = null): User
    {
        return User::create([
            'name' => 'Akun ' . $role,
            'email' => $email,
            'password' => bcrypt('rahasia123'),
            'role' => $role,
            'is_active' => true,
            'pendamping_id' => $pendampingId,
        ]);
    }

    // =================================================================
    // PIMPINAN MELIHAT SIMPANAN
    // =================================================================

    private function siapkanKoperasi(): void
    {
        $anggota = KoperasiMember::create([
            'nama' => 'Bu Ani', 'nomor_hp' => '0812',
            'tanggal_bergabung' => '2026-01-01', 'status' => 'Aktif',
        ]);

        MemberSaving::create([
            'koperasi_member_id' => $anggota->id,
            'jenis_simpanan' => 'Simpanan Pokok',
            'tanggal' => '2026-02-01',
            'nominal' => 250_000,
            'status' => MemberSaving::STATUS_TERCATAT,
        ]);
    }

    public function test_dashboard_pimpinan_menampilkan_simpanan_anggota(): void
    {
        $this->siapkanKoperasi();
        $pimpinan = $this->buatUser(User::ROLE_PIMPINAN, 'pimpinan@uat.test');

        $halaman = $this->actingAs($pimpinan)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewIs('dashboard-pimpinan');

        $this->assertSame(
            250_000.0,
            $halaman->viewData('koperasiRingkasan')['total_simpanan']
        );

        $halaman->assertSee('Simpanan Anggota');
    }

    /**
     * Angkanya memakai dasar yang sama dengan halaman Koperasi.
     *
     * Kalau Pimpinan dan Tim Koperasi membaca angka simpanan yang berbeda
     * untuk uang yang sama, tidak ada satu pun dari keduanya yang bisa
     * dipakai dalam rapat.
     */
    public function test_angka_simpanan_pimpinan_sama_dengan_halaman_koperasi(): void
    {
        $this->siapkanKoperasi();

        $pimpinan = $this->buatUser(User::ROLE_PIMPINAN, 'pimpinan@uat.test');
        $koperasi = $this->buatUser(User::ROLE_TIM_KOPERASI, 'koperasi@uat.test');

        $dariPimpinan = $this->actingAs($pimpinan)
            ->get(route('dashboard'))
            ->viewData('koperasiRingkasan')['total_simpanan'];

        $dariKoperasi = $this->actingAs($koperasi)
            ->get(route('dashboard'))
            ->viewData('stats')['total_simpanan'];

        $this->assertSame($dariKoperasi, $dariPimpinan);
    }

    /**
     * Simpanan milik anggota terarsip tetap diterangkan, juga ke Pimpinan.
     *
     * Angka yang berdiri tanpa penjelasan akan dibaca sebagai modal anggota
     * yang masih ada — padahal sebagian pemiliknya sudah tidak lagi menjadi
     * anggota, dan tidak menerima pembagian SHU.
     */
    public function test_pimpinan_diberi_tahu_simpanan_milik_anggota_terarsip(): void
    {
        $keluar = KoperasiMember::create([
            'nama' => 'Bu Sinta', 'nomor_hp' => '0813',
            'tanggal_bergabung' => '2026-01-01', 'status' => 'Aktif',
        ]);

        MemberSaving::create([
            'koperasi_member_id' => $keluar->id,
            'jenis_simpanan' => 'Simpanan Pokok',
            'tanggal' => '2026-02-01',
            'nominal' => 90_000,
            'status' => MemberSaving::STATUS_TERCATAT,
        ]);

        $keluar->delete();

        $pimpinan = $this->buatUser(User::ROLE_PIMPINAN, 'pimpinan@uat.test');

        $this->actingAs($pimpinan)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('sudah diarsipkan', false);
    }

    /** Kontrol pembanding: tanpa anggota terarsip, tidak ada keterangan. */
    public function test_tanpa_anggota_terarsip_pimpinan_tidak_melihat_keterangan(): void
    {
        $this->siapkanKoperasi();
        $pimpinan = $this->buatUser(User::ROLE_PIMPINAN, 'pimpinan@uat.test');

        $this->actingAs($pimpinan)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('sudah diarsipkan', false);
    }

    /** Pimpinan tetap read-only — menambah kartu tidak membuka pintu baru. */
    public function test_pimpinan_tetap_tidak_bisa_masuk_area_operasional(): void
    {
        $pimpinan = $this->buatUser(User::ROLE_PIMPINAN, 'pimpinan@uat.test');

        foreach ([
            'manajemen-user.index',
            'pengaturan.index',
            'arsip.index',
            'backup-database.index',
            'koperasi.anggota.index',
        ] as $rute) {
            $this->actingAs($pimpinan)->get(route($rute))->assertForbidden();
        }
    }

    // =================================================================
    // TABEL TIDAK MELUBER DI PONSEL
    // =================================================================

    /**
     * Setiap tabel yang dirender harus berada di dalam wadah ber-overflow.
     *
     * Diperiksa pada HTML yang benar-benar dirender, bukan pada berkas
     * Blade-nya: satu tabel bisa datang dari partial yang di-include, dan
     * memeriksa berkasnya satu per satu akan melewatkannya.
     */
    public function test_tabel_di_halaman_peserta_terbungkus_wadah_geser(): void
    {
        $pendamping = Pendamping::create([
            'nama' => 'Rosa Linda', 'nomor_hp' => '081200000001', 'status' => 'Aktif',
        ]);
        $user = $this->buatUser(User::ROLE_PENDAMPING, 'rosa@uat.test', $pendamping->id);

        $peserta = Participant::create([
            'nama_peserta' => 'Bu Ani',
            'nomor_hp' => '081234567890',
            'nama_usaha' => 'Keripik Bawang',
            'kategori_usaha' => 'Kuliner',
            'tanggal_mulai_program' => now()->subYear()->toDateString(),
            'status' => 'Aktif',
            'pendamping_id' => $pendamping->id,
        ]);

        // Tabel omzet dan pelatihan hanya dirender kalau datanya ADA.
        // Tanpa dua baris ini, halamannya tidak memuat satu tabel pun dan
        // assertion di bawah lulus tanpa memeriksa apa-apa.
        OmzetReport::create([
            'participant_id' => $peserta->id,
            'periode_bulan' => 8,
            'periode_tahun' => 2026,
            'jenis_periode' => 'Bulanan',
            'omzet' => 5_000_000,
        ]);

        TrainingRecord::create([
            'participant_id' => $peserta->id,
            'tanggal' => now()->subMonth()->toDateString(),
            'nama_pelatihan' => 'Pembukuan Sederhana',
            'penyelenggara' => 'YBM PLN',
            'status' => 'Selesai',
        ]);

        $html = $this->actingAs($user)
            ->get(route('participants.show', $peserta))
            ->assertOk()
            ->getContent();

        $this->assertTabelTerbungkus($html, 'halaman detail peserta');
    }

    public function test_tabel_di_halaman_keuangan_koperasi_terbungkus(): void
    {
        $koperasi = $this->buatUser(User::ROLE_TIM_KOPERASI, 'koperasi@uat.test');

        $html = $this->actingAs($koperasi)
            ->get(route('koperasi.keuangan.index'))
            ->assertOk()
            ->getContent();

        $this->assertTabelTerbungkus($html, 'halaman Keuangan koperasi');
    }

    /**
     * Setiap <table> harus didahului sebuah wadah ber-overflow yang masih
     * terbuka. Dihitung dengan menelusuri HTML sekali jalan.
     */
    private function assertTabelTerbungkus(string $html, string $namaHalaman): void
    {
        $potongan = preg_split('/(<div[^>]*>|<\/div>|<table)/i', $html, -1, PREG_SPLIT_DELIM_CAPTURE);

        $tumpukan = [];
        $tabel = 0;
        $telanjang = 0;

        foreach ($potongan as $bagian) {
            $kecil = strtolower($bagian);

            if (str_starts_with($kecil, '<div')) {
                $tumpukan[] = str_contains($kecil, 'overflow-x-auto') || str_contains($kecil, 'overflow-auto');
            } elseif ($kecil === '</div>') {
                array_pop($tumpukan);
            } elseif ($kecil === '<table') {
                $tabel++;

                if (! in_array(true, $tumpukan, true)) {
                    $telanjang++;
                }
            }
        }

        $this->assertGreaterThan(0, $tabel, "Tidak ada tabel sama sekali di {$namaHalaman} — assertion ini jadi tidak berarti.");

        $this->assertSame(
            0,
            $telanjang,
            "{$telanjang} dari {$tabel} tabel di {$namaHalaman} tidak terbungkus wadah geser — "
                . 'halaman akan bergeser ke samping di ponsel.'
        );
    }
}
