<?php

namespace Tests\Feature;

use App\Models\Participant;
use App\Models\Pendamping;
use App\Models\PendampinganRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * RINGKASAN PERAN, BATAS AKSES, DAN ARSIP.
 *
 * =====================================================================
 * KENAPA TEST INI ADA
 * =====================================================================
 *
 * Ringkasan di Manajemen User dulu menyebut perannya satu per satu di
 * dalam kode. Ketika peran Tim Koperasi ditambahkan ke sistem, kartunya
 * tidak ikut muncul — Admin melihat tiga kartu untuk empat peran, dan
 * wajar menyimpulkan akun keempat belum ada padahal ada.
 *
 * Bug seperti itu tidak menimbulkan galat apa pun. Satu-satunya cara
 * menahannya adalah menguji bahwa SETIAP peran yang dikenal sistem punya
 * angkanya sendiri, tanpa menyebut nama perannya di test ini.
 *
 * =====================================================================
 * ARSIP: DIPERTAHANKAN, BUKAN DIRAPIKAN SAMPAI HILANG
 * =====================================================================
 *
 * Data CahayaOS adalah histori pemberdayaan — peserta yang sudah tidak
 * aktif tetap dibutuhkan untuk laporan, audit, dan perhitungan dampak.
 * Karena itu yang dijaga di sini bukan hanya "arsipnya bisa dibuka",
 * melainkan bahwa penghapusan tetap soft delete, datanya masih ada, dan
 * masih bisa dipulihkan.
 *
 * Yang juga dijaga: angka pada badge tab harus sama dengan jumlah baris
 * yang benar-benar boleh dilihat orang itu. Badge yang menghitung seluruh
 * organisasi sementara barisnya tersaring per binaan adalah kebocoran
 * informasi yang menyamar sebagai salah hitung biasa.
 */
class UxPeranDanArsipTest extends TestCase
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

    private function buatPendamping(string $nama = 'Rosa Linda', string $hp = '081200000001'): Pendamping
    {
        return Pendamping::create([
            'nama' => $nama,
            'nomor_hp' => $hp,
            'status' => 'Aktif',
        ]);
    }

    // =================================================================
    // A. RINGKASAN PERAN DI MANAJEMEN USER
    // =================================================================

    /**
     * Setiap peran yang dikenal sistem harus punya angkanya sendiri.
     *
     * Nama perannya sengaja TIDAK ditulis di sini: yang diuji justru bahwa
     * daftarnya bersumber pada ROLE_OPTIONS, sehingga peran yang
     * ditambahkan nanti ikut terbawa tanpa ada yang perlu mengingatnya.
     */
    public function test_ringkasan_memuat_setiap_peran_yang_dikenal_sistem(): void
    {
        $admin = $this->buatUser(User::ROLE_ADMIN, 'admin@uji.test');

        $ringkasan = $this->actingAs($admin)
            ->get(route('manajemen-user.index'))
            ->assertOk()
            ->viewData('ringkasan');

        $this->assertSame(
            array_keys(User::ROLE_OPTIONS),
            array_keys($ringkasan['peran']),
            'Setiap peran di ROLE_OPTIONS harus punya kartunya sendiri.'
        );
    }

    /** Angkanya berasal dari database, bukan ditulis tetap di kode. */
    public function test_jumlah_per_peran_mengikuti_isi_database(): void
    {
        $admin = $this->buatUser(User::ROLE_ADMIN, 'admin@uji.test');
        $this->buatUser(User::ROLE_PIMPINAN, 'pimpinan@uji.test');
        $this->buatUser(User::ROLE_TIM_KOPERASI, 'koperasi@uji.test');
        $this->buatUser(User::ROLE_TIM_KOPERASI, 'koperasi2@uji.test');

        $ringkasan = $this->actingAs($admin)
            ->get(route('manajemen-user.index'))
            ->viewData('ringkasan');

        $this->assertSame(1, $ringkasan['peran'][User::ROLE_ADMIN]['jumlah']);
        $this->assertSame(1, $ringkasan['peran'][User::ROLE_PIMPINAN]['jumlah']);
        $this->assertSame(2, $ringkasan['peran'][User::ROLE_TIM_KOPERASI]['jumlah']);
        $this->assertSame(4, $ringkasan['total']);

        // Peran tanpa satu pun akun tetap muncul sebagai 0 — supaya terlihat
        // bedanya antara "belum ada akunnya" dan "perannya tidak dikenal".
        $this->assertSame(0, $ringkasan['peran'][User::ROLE_PENDAMPING]['jumlah']);
    }

    /** Menambah user mengubah kartunya, tanpa ada yang perlu disunting. */
    public function test_kartu_ikut_berubah_ketika_user_bertambah(): void
    {
        $admin = $this->buatUser(User::ROLE_ADMIN, 'admin@uji.test');

        $sebelum = $this->actingAs($admin)
            ->get(route('manajemen-user.index'))
            ->viewData('ringkasan');

        $this->buatUser(User::ROLE_PIMPINAN, 'pimpinan@uji.test');

        $sesudah = $this->actingAs($admin)
            ->get(route('manajemen-user.index'))
            ->viewData('ringkasan');

        $this->assertSame(0, $sebelum['peran'][User::ROLE_PIMPINAN]['jumlah']);
        $this->assertSame(1, $sesudah['peran'][User::ROLE_PIMPINAN]['jumlah']);
        $this->assertSame($sebelum['total'] + 1, $sesudah['total']);
    }

    /**
     * KARTU setiap peran benar-benar dirender, bukan sekadar ada di data.
     *
     * Diperiksa lewat penanda data-kartu-peran, bukan lewat labelnya: label
     * "Tim Koperasi" juga muncul di dropdown filter dan di badge tiap baris,
     * sehingga mencari labelnya saja tetap lulus walaupun kartunya hilang —
     * persis keadaan yang dilaporkan dari UAT.
     */
    public function test_kartu_setiap_peran_benar_benar_dirender(): void
    {
        $admin = $this->buatUser(User::ROLE_ADMIN, 'admin@uji.test');

        $halaman = $this->actingAs($admin)->get(route('manajemen-user.index'))->assertOk();

        foreach (User::ROLE_OPTIONS as $kode => $label) {
            $halaman->assertSee('data-kartu-peran="' . $kode . '"', false);
            $halaman->assertSee($label);
        }
    }

    // =================================================================
    // B. BATAS AKSES ANTAR PERAN
    // =================================================================

    public function test_admin_bisa_membuka_seluruh_area_administrasi(): void
    {
        $admin = $this->buatUser(User::ROLE_ADMIN, 'admin@uji.test');

        foreach ([
            'manajemen-user.index',
            'arsip.index',
            'backup-database.index',
            'pengaturan.index',
        ] as $rute) {
            $this->actingAs($admin)->get(route($rute))->assertOk();
        }
    }

    /**
     * Pimpinan tidak punya satu pun tab Arsip, jadi halamannya menolak.
     *
     * Ini bukan menu yang disembunyikan: backendnya sendiri yang menolak,
     * sehingga mengetik URL-nya pun tidak menembus.
     */
    public function test_pimpinan_ditolak_di_area_administrasi(): void
    {
        $pimpinan = $this->buatUser(User::ROLE_PIMPINAN, 'pimpinan@uji.test');

        foreach ([
            'manajemen-user.index',
            'arsip.index',
            'backup-database.index',
            'pengaturan.index',
        ] as $rute) {
            $this->actingAs($pimpinan)->get(route($rute))->assertForbidden();
        }
    }

    public function test_pendamping_ditolak_di_area_administrasi(): void
    {
        $pendamping = $this->buatPendamping();
        $user = $this->buatUser(User::ROLE_PENDAMPING, 'rosa@uji.test', $pendamping->id);

        foreach ([
            'manajemen-user.index',
            'backup-database.index',
            'pengaturan.index',
            'participants.index',
        ] as $rute) {
            $this->actingAs($user)->get(route($rute))->assertForbidden();
        }
    }

    public function test_tim_koperasi_ditolak_di_konfigurasi_cahaya_preneur(): void
    {
        $user = $this->buatUser(User::ROLE_TIM_KOPERASI, 'koperasi@uji.test');

        foreach ([
            'manajemen-user.index',
            'pengaturan.index',
            'backup-database.index',
            'participants.index',
        ] as $rute) {
            $this->actingAs($user)->get(route($rute))->assertForbidden();
        }
    }

    /**
     * Sidebar Pendamping tidak boleh memuat pintu administrasi.
     *
     * Bukan soal keamanan — backendnya sudah menolak. Soal beban: menu yang
     * tidak pernah boleh diklik hanya menambah hal yang harus diabaikan
     * setiap hari.
     */
    public function test_sidebar_pendamping_tidak_memuat_menu_administrasi(): void
    {
        $pendamping = $this->buatPendamping();
        $user = $this->buatUser(User::ROLE_PENDAMPING, 'rosa@uji.test', $pendamping->id);

        $halaman = $this->actingAs($user)->get(route('dashboard'))->assertOk();

        foreach ([
            route('manajemen-user.index'),
            route('backup-database.index'),
            route('pengaturan.index'),
            route('pengaturan-sistem.index'),
        ] as $tautan) {
            $halaman->assertDontSee($tautan . '"', false);
        }
    }

    /**
     * Sidebar Pendamping tidak memuat Arsip Data.
     *
     * Arsipnya memang sudah tersaring ke binaannya sendiri dan tidak pernah
     * bisa menghapus permanen — jadi ini bukan soal keamanan, melainkan soal
     * beban. Sidebar Pendamping adalah daftar pekerjaan lapangan; mengelola
     * arsip bukan salah satunya.
     *
     * Route-nya SENGAJA tidak dicabut (diuji di bawah), jadi test ini menguji
     * MENU-nya, bukan izinnya.
     */
    public function test_sidebar_pendamping_tidak_memuat_arsip_data(): void
    {
        $pendamping = $this->buatPendamping();
        $user = $this->buatUser(User::ROLE_PENDAMPING, 'rosa@uji.test', $pendamping->id);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee(route('arsip.index') . '"', false);
    }

    /**
     * Menunya dilepas, izinnya TIDAK.
     *
     * Kalau keduanya dicabut sekaligus, Pendamping yang salah mengarsipkan
     * catatan binaannya tidak punya jalan apa pun — dan Admin pun tidak bisa
     * menunjukkan tautannya.
     */
    public function test_pendamping_masih_boleh_membuka_arsip_lewat_tautan(): void
    {
        $pendamping = $this->buatPendamping();
        $user = $this->buatUser(User::ROLE_PENDAMPING, 'rosa@uji.test', $pendamping->id);

        $this->actingAs($user)->get(route('arsip.index'))->assertOk();
    }

    /**
     * Istilah data bisnis dan istilah akun tidak boleh bercampur.
     *
     * Arsip Data memakai Arsipkan/Pulihkan; Manajemen User memakai
     * Nonaktifkan/Aktifkan Kembali. Kalau keduanya memakai kata yang sama,
     * Admin wajar mengira menonaktifkan akun ikut mengarsipkan datanya.
     */
    public function test_istilah_arsip_dan_akun_tidak_bercampur(): void
    {
        $admin = $this->buatUser(User::ROLE_ADMIN, 'admin@uji.test');

        // Arsipnya harus BERISI, kalau tidak tombol Pulihkan memang tidak
        // dirender dan assertion di bawah lulus/gagal karena alasan yang salah.
        $pendamping = $this->buatPendamping();
        $this->pesertaBinaan($pendamping, 'Bu Sinta')->delete();

        $this->actingAs($admin)
            ->get(route('arsip.index', ['tab' => 'peserta']))
            ->assertOk()
            ->assertSee('Pulihkan')
            ->assertDontSee('Nonaktifkan')
            ->assertDontSee('Aktifkan Kembali');

        $this->actingAs($admin)
            ->get(route('manajemen-user.index'))
            ->assertOk()
            ->assertSee('Nonaktifkan')
            ->assertDontSee('Arsipkan');
    }

    /**
     * Tombol di modul bisnis menyebut ARSIPKAN, bukan HAPUS.
     *
     * Peserta yang "dihapus" sebenarnya diarsipkan dan masih bisa dipulihkan.
     * Label "Hapus" membuat Pendamping ragu menekan tombol yang sebenarnya
     * aman, dan membuat Admin mengira datanya sudah hilang.
     */
    public function test_tombol_modul_bisnis_menyebut_arsipkan(): void
    {
        $admin = $this->buatUser(User::ROLE_ADMIN, 'admin@uji.test');
        $pendamping = $this->buatPendamping();
        $this->pesertaBinaan($pendamping, 'Bu Ani');

        // Diperiksa pada TOMBOLNYA, bukan sekadar "kata Arsipkan ada di
        // halaman": kata itu juga muncul di kalimat konfirmasi, sehingga
        // pencarian longgar tetap lulus walau tombolnya kembali berbunyi
        // "Hapus".
        $this->actingAs($admin)
            ->get(route('participants.index'))
            ->assertOk()
            ->assertSee('>Arsipkan<', false)
            ->assertDontSee('>Hapus<', false)
            ->assertDontSee('tab Terhapus');
    }

    /** Kontrol pembanding: Admin memang melihat pintu-pintu itu. */
    public function test_sidebar_admin_memuat_menu_administrasi(): void
    {
        $admin = $this->buatUser(User::ROLE_ADMIN, 'admin@uji.test');

        $halaman = $this->actingAs($admin)->get(route('dashboard'))->assertOk();

        foreach ([
            route('manajemen-user.index'),
            route('backup-database.index'),
            route('pengaturan.index'),
            route('arsip.index'),
        ] as $tautan) {
            $halaman->assertSee($tautan, false);
        }
    }

    // =================================================================
    // C. ARSIP
    // =================================================================

    private function pesertaBinaan(Pendamping $pendamping, string $nama): Participant
    {
        return Participant::create([
            'nama_peserta' => $nama,
            'nomor_hp' => '081234567890',
            'nama_usaha' => 'Usaha ' . $nama,
            'kategori_usaha' => 'Kuliner',
            'tanggal_mulai_program' => now()->subYear()->toDateString(),
            'status' => 'Aktif',
            'pendamping_id' => $pendamping->id,
        ]);
    }

    private function catatan(Participant $peserta, Pendamping $pendamping): PendampinganRecord
    {
        return PendampinganRecord::create([
            'participant_id' => $peserta->id,
            'pendamping_id' => $pendamping->id,
            'tanggal' => now()->subMonth()->toDateString(),
            'jenis_kegiatan' => 'Kunjungan',
            'materi' => 'Pembukuan sederhana',
            'catatan' => 'Sudah mulai mencatat penjualan harian.',
        ]);
    }

    /**
     * Menghapus peserta TIDAK menghilangkan datanya.
     *
     * Kalau suatu saat ini berubah jadi hard delete, histori pendampingan,
     * omzet, dan seluruh perhitungan dampak yang menyebut peserta itu ikut
     * kehilangan pijakannya — dan tidak ada cara mengembalikannya.
     */
    public function test_menghapus_peserta_adalah_soft_delete(): void
    {
        $pendamping = $this->buatPendamping();
        $peserta = $this->pesertaBinaan($pendamping, 'Bu Ani');

        $peserta->delete();

        $this->assertSoftDeleted('participants', ['id' => $peserta->id]);
        $this->assertNotNull(
            Participant::withTrashed()->find($peserta->id),
            'Barisnya harus tetap ada — ini histori pemberdayaan.'
        );
    }

    public function test_peserta_terarsip_tidak_muncul_di_daftar_aktif(): void
    {
        $admin = $this->buatUser(User::ROLE_ADMIN, 'admin@uji.test');
        $pendamping = $this->buatPendamping();
        $aktif = $this->pesertaBinaan($pendamping, 'Bu Ani');
        $diarsip = $this->pesertaBinaan($pendamping, 'Bu Sinta');

        $diarsip->delete();

        $this->actingAs($admin)->get(route('participants.index'))
            ->assertOk()
            ->assertSee($aktif->nama_peserta)
            ->assertDontSee($diarsip->nama_peserta);
    }

    public function test_peserta_terarsip_muncul_di_arsip_dan_bisa_dipulihkan(): void
    {
        $admin = $this->buatUser(User::ROLE_ADMIN, 'admin@uji.test');
        $pendamping = $this->buatPendamping();
        $peserta = $this->pesertaBinaan($pendamping, 'Bu Sinta');
        $peserta->delete();

        $this->actingAs($admin)->get(route('arsip.index', ['tab' => 'peserta']))
            ->assertOk()
            ->assertSee($peserta->nama_peserta);

        $this->actingAs($admin)->post(route('participants.restore', $peserta->id));

        $this->assertNull(Participant::withTrashed()->find($peserta->id)->deleted_at);

        $this->actingAs($admin)->get(route('participants.index'))
            ->assertSee($peserta->nama_peserta);
    }

    /** Pemulihan mengembalikan pesertanya TANPA menghapus catatan riwayatnya. */
    public function test_pemulihan_tidak_menghilangkan_histori(): void
    {
        $admin = $this->buatUser(User::ROLE_ADMIN, 'admin@uji.test');
        $pendamping = $this->buatPendamping();
        $peserta = $this->pesertaBinaan($pendamping, 'Bu Ani');
        $catatan = $this->catatan($peserta, $pendamping);

        $peserta->delete();
        $this->actingAs($admin)->post(route('participants.restore', $peserta->id));

        $this->assertDatabaseHas('pendampingan_records', [
            'id' => $catatan->id,
            'participant_id' => $peserta->id,
        ]);
    }

    /**
     * Pendamping hanya melihat arsip binaannya sendiri — termasuk angkanya.
     *
     * Badge yang menghitung seluruh organisasi sementara barisnya tersaring
     * per binaan bukan sekadar salah hitung: angkanya sendiri sudah
     * memberitahu berapa banyak catatan pendamping lain yang ada.
     */
    public function test_arsip_pendamping_hanya_binaannya_termasuk_angka_badge(): void
    {
        $rosa = $this->buatPendamping();
        $lain = $this->buatPendamping('Budi Santoso', '081200000002');
        $user = $this->buatUser(User::ROLE_PENDAMPING, 'rosa@uji.test', $rosa->id);

        $binaanRosa = $this->pesertaBinaan($rosa, 'Bu Ani');
        $binaanLain = $this->pesertaBinaan($lain, 'Bu Tuti');

        $this->catatan($binaanRosa, $rosa)->delete();
        $this->catatan($binaanLain, $lain)->delete();
        $this->catatan($binaanLain, $lain)->delete();

        $halaman = $this->actingAs($user)
            ->get(route('arsip.index', ['tab' => 'pendampingan']))
            ->assertOk();

        $this->assertSame(
            1,
            $halaman->viewData('jumlahPerTab')['pendampingan'],
            'Badge harus menghitung binaannya sendiri saja, bukan seluruh organisasi.'
        );

        $this->assertCount(
            1,
            $halaman->viewData('baris')->items(),
            'Barisnya pun hanya catatan binaannya sendiri.'
        );
    }

    /** Tab milik Admin tidak bisa dibuka Pendamping lewat URL. */
    public function test_pendamping_tidak_bisa_memaksa_tab_peserta_lewat_url(): void
    {
        $rosa = $this->buatPendamping();
        $user = $this->buatUser(User::ROLE_PENDAMPING, 'rosa@uji.test', $rosa->id);

        $halaman = $this->actingAs($user)
            ->get(route('arsip.index', ['tab' => 'peserta']))
            ->assertOk();

        $this->assertArrayNotHasKey('peserta', $halaman->viewData('tabTersedia'));
        $this->assertNotSame('peserta', $halaman->viewData('tabAktif'));
    }

    /** Hapus permanen tetap kewenangan Admin saja. */
    public function test_hapus_permanen_tidak_ditawarkan_ke_pendamping(): void
    {
        $rosa = $this->buatPendamping();
        $user = $this->buatUser(User::ROLE_PENDAMPING, 'rosa@uji.test', $rosa->id);

        $this->assertFalse(
            $this->actingAs($user)->get(route('arsip.index'))->viewData('bolehHapusPermanen')
        );
    }
}
