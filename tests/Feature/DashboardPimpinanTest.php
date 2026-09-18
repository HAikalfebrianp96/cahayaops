<?php

namespace Tests\Feature;

use App\Models\BusinessUnit;
use App\Models\Participant;
use App\Models\Pendamping;
use App\Models\SalesTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Dashboard Pimpinan — entry point dua ekosistem.
 *
 * Sidebar Pimpinan sengaja disederhanakan jadi satu titik masuk saja, dan
 * pemilihan modul dipindah ke dua kartu di Dashboard. Yang diuji di sini
 * adalah HASIL RENDER-nya, bukan sekadar rolenya.
 *
 * Dua hal yang paling dijaga kelas ini:
 *
 * 1. Menyembunyikan menu BUKAN mencabut akses. Route, controller, dan
 *    otorisasi Penjualan Koperasi tetap utuh — diuji secara eksplisit.
 * 2. Setiap tautan di Dashboard benar-benar bisa dibuka Pimpinan. Sebelum
 *    dirapikan, dashboard menautkan koperasi.keuangan.index yang Admin-only,
 *    jadi Pimpinan mendapat 403 dari dashboardnya sendiri.
 */
class DashboardPimpinanTest extends TestCase
{
    use RefreshDatabase;

    private function pimpinan(string $email = 'pimpinan@dash.test'): User
    {
        return User::create([
            'name' => 'Pimpinan Uji',
            'email' => $email,
            'password' => 'password123',
            'role' => User::ROLE_PIMPINAN,
        ]);
    }

    /** Data secukupnya supaya KPI tidak semuanya nol. */
    private function isiData(): void
    {
        $profil = Pendamping::create([
            'nama' => 'Pendamping Dash',
            'nomor_hp' => '081200000000',
            'email' => 'pendamping.dash@uji.test',
            'status' => 'Aktif',
        ]);

        Participant::create([
            'nama_peserta' => 'Peserta Dash',
            'nomor_hp' => '0816',
            'nama_usaha' => 'Usaha Dash',
            'kategori_usaha' => 'Makanan & Minuman',
            'tanggal_mulai_program' => now()->toDateString(),
            'status' => 'Aktif',
            'pendamping_id' => $profil->id,
        ]);
    }

    /** Transaksi Koperasi — membuat $adaAktivitasKoperasi bernilai true. */
    private function isiTransaksiKoperasi(): void
    {
        $unit = BusinessUnit::create([
            'nama' => 'Unit Uji Dashboard',
            'kode' => 'UUD-01',
            'tipe' => 'Barang',
            'status' => 'Aktif',
        ]);

        SalesTransaction::create([
            'nomor_transaksi' => 'TRX-DASH-000001',
            'unit_id' => $unit->id,
            'tanggal' => now()->toDateString(),
            'subtotal' => 500000,
            'total' => 500000,
            'status_pembayaran' => 'Lunas',
        ]);
    }

    private function nav(User $user): string
    {
        $html = $this->actingAs($user)->get(route('dashboard'))->getContent();
        $awal = strpos($html, '<nav');

        return substr($html, $awal, strpos($html, '</nav>') - $awal);
    }

    // =====================================================================
    // Otorisasi — TIDAK berubah
    // =====================================================================

    public function test_pimpinan_bisa_membuka_dashboard(): void
    {
        $this->actingAs($this->pimpinan())->get(route('dashboard'))->assertOk();
    }

    public function test_tamu_tetap_diarahkan_ke_login(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    /** Admin tetap mendapat dashboard bertab miliknya, bukan versi Pimpinan. */
    public function test_admin_tidak_ikut_mendapat_dashboard_pimpinan(): void
    {
        $admin = User::create([
            'name' => 'Admin Uji',
            'email' => 'admin@dash.test',
            'password' => 'password123',
            'role' => User::ROLE_ADMIN,
        ]);

        $this->actingAs($admin)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Dashboard Pimpinan');
    }

    // =====================================================================
    // Sidebar — disederhanakan, TAPI bukan pencabutan akses
    // =====================================================================

    public function test_sidebar_pimpinan_hanya_berisi_dashboard(): void
    {
        $nav = $this->nav($this->pimpinan());

        $this->assertStringContainsString('Dashboard Pimpinan', $nav);
        $this->assertStringNotContainsString('Penjualan Koperasi', $nav);
        $this->assertStringNotContainsString('href="' . route('koperasi.penjualan.index') . '"', $nav);
    }

    /** Modul operasional lain juga tidak bocor ke sidebar Pimpinan. */
    public function test_sidebar_pimpinan_tidak_memuat_menu_operasional_lain(): void
    {
        $nav = $this->nav($this->pimpinan());

        foreach ([
            'koperasi.anggota.index', 'koperasi.unit-usaha.index', 'koperasi.pengeluaran.index',
            'koperasi.keuangan.index', 'koperasi.laporan.index', 'participants.index',
            'pendampings.index', 'omzet.index', 'laporan',
        ] as $rute) {
            $this->assertStringNotContainsString('href="' . route($rute) . '"', $nav);
        }
    }

    /**
     * INTI: menu disembunyikan, fitur TIDAK dihapus. Route-nya masih terdaftar
     * dan otorisasi backend-nya tidak berubah sedikit pun — Pimpinan yang
     * mengetik URL-nya tetap dilayani seperti sebelumnya.
     */
    public function test_route_penjualan_koperasi_tetap_ada_dan_tetap_bisa_diakses(): void
    {
        foreach ([
            'koperasi.penjualan.index', 'koperasi.penjualan.create', 'koperasi.penjualan.store',
        ] as $rute) {
            $this->assertTrue(Route::has($rute), "Route $rute hilang — fitur tidak boleh dihapus.");
        }

        $this->actingAs($this->pimpinan())
            ->get(route('koperasi.penjualan.index'))
            ->assertOk();
    }

    /** Otorisasi yang menolak Pimpinan juga tidak ikut dilonggarkan. */
    public function test_halaman_admin_only_tetap_menolak_pimpinan(): void
    {
        $pimpinan = $this->pimpinan();

        foreach (['koperasi.dashboard', 'koperasi.keuangan.index', 'participants.index', 'laporan'] as $rute) {
            $this->actingAs($pimpinan)->get(route($rute))->assertForbidden();
        }
    }

    // =====================================================================
    // Struktur: dua ekosistem sebagai dua pintu
    // =====================================================================

    public function test_kedua_ekosistem_tampil(): void
    {
        $this->isiData();

        $respons = $this->actingAs($this->pimpinan())->get(route('dashboard'));

        $respons->assertOk();
        $respons->assertSee('Dashboard Pimpinan');
        $respons->assertSee('Ringkasan kinerja ekosistem CahayaOS');
        $respons->assertSee('Cahaya Preneur');
        $respons->assertSee('Program pemberdayaan dan pengembangan UMKM.');
        $respons->assertSee('Koperasi Cahaya');
        $respons->assertSee('Ekosistem usaha dan pengelolaan Koperasi Cahaya.');
    }

    /** Cahaya Preneur harus muncul LEBIH DULU daripada Koperasi Cahaya. */
    public function test_urutan_cahaya_preneur_lalu_koperasi(): void
    {
        $html = $this->actingAs($this->pimpinan())->get(route('dashboard'))->getContent();

        $posPreneur = strpos($html, 'Program pemberdayaan dan pengembangan UMKM.');
        $posKoperasi = strpos($html, 'Ekosistem usaha dan pengelolaan Koperasi Cahaya.');

        $this->assertNotFalse($posPreneur);
        $this->assertNotFalse($posKoperasi);
        $this->assertLessThan($posKoperasi, $posPreneur);
    }

    /**
     * Kedua kartu harus setara: berdampingan di desktop, menumpuk di mobile,
     * sama tinggi, dan CTA-nya sejajar di dasar kartu.
     */
    public function test_kedua_kartu_berdampingan_dan_sama_tinggi(): void
    {
        $html = $this->actingAs($this->pimpinan())->get(route('dashboard'))->getContent();

        // Satu grid: 1 kolom di mobile, 2 kolom mulai md.
        $this->assertStringContainsString('grid-cols-1 md:grid-cols-2', $html);
        $this->assertStringContainsString('items-stretch', $html);

        // Dua kartu, keduanya h-full + flex-col, CTA dikunci mt-auto.
        $this->assertSame(2, substr_count($html, 'h-full bg-white rounded-xl'));
        $this->assertSame(2, substr_count($html, 'p-6 flex flex-col'));
        $this->assertSame(2, substr_count($html, 'mt-auto'));
    }

    public function test_kpi_existing_tetap_muncul(): void
    {
        $this->isiData();

        $respons = $this->actingAs($this->pimpinan())->get(route('dashboard'));

        foreach ([
            // Cahaya Preneur
            'Total Peserta', 'Peserta Aktif', 'Total Omzet',
            'Pendamping', 'Kegiatan Pendampingan', 'Omzet Meningkat', 'Kenaikan omzet terukur',
            // Koperasi Cahaya
            'Total Anggota', 'Anggota Aktif', 'Total Penjualan',
            'Unit Usaha', 'Laba Kotor', 'Pengeluaran',
        ] as $label) {
            $respons->assertSee($label);
        }
    }

    public function test_laba_bersih_koperasi_tampil_saat_ada_transaksi(): void
    {
        $this->isiTransaksiKoperasi();

        $this->actingAs($this->pimpinan())->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Laba bersih')
            ->assertDontSee('Belum ada transaksi pada periode ini.');
    }

    public function test_filter_periode_tetap_ada_dan_berlaku_untuk_kedua_ekosistem(): void
    {
        $pimpinan = $this->pimpinan();

        $this->actingAs($pimpinan)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Tahun')
            ->assertSee('Bulan')
            ->assertSee('Terapkan')
            ->assertSee('Reset');

        // Satu form, satu action — kedua kartu ikut periode yang sama.
        $this->actingAs($pimpinan)->get(route('dashboard', ['tahun' => now()->year, 'bulan' => 3]))
            ->assertOk()
            ->assertSee('Cahaya Preneur')
            ->assertSee('Koperasi Cahaya');
    }

    // =====================================================================
    // Dashboard = summary + navigation, bukan halaman laporan
    // =====================================================================

    /**
     * Rincian operasional tidak ditampilkan di Dashboard. Transaksi Koperasi
     * SENGAJA diisi lebih dulu — tanpa itu blok rincian memang tidak pernah
     * dirender dan test ini tidak membuktikan apa pun.
     */
    public function test_dashboard_tidak_menampilkan_rincian_operasional(): void
    {
        $this->isiData();
        $this->isiTransaksiKoperasi();

        $respons = $this->actingAs($this->pimpinan())->get(route('dashboard'));

        $respons->assertOk();
        // Bukti periodenya memang punya aktivitas.
        $respons->assertDontSee('Belum ada transaksi pada periode ini.');

        // Tapi rinciannya tidak ikut ditampilkan.
        $respons->assertDontSee('Performa Unit');
        $respons->assertDontSee('Unit Usaha Terbaik');
        $respons->assertDontSee('<table', false);
    }

    /**
     * Section yang dipindahkan keluar dari Dashboard benar-benar tidak ada.
     *
     * CATATAN soal SROI. Test ini dulu memakai kata "SROI" begitu saja
     * sebagai penanda section "Dampak Program" yang dihapus dari Dashboard —
     * aman waktu itu, karena tidak ada apa pun di CahayaOS yang menyebut
     * kata itu. Sekarang ada: modul Dampak & SROI, dan Pimpinan punya menu
     * ke sana di sidebar karena persetujuan hasil SROI memang kewenangannya.
     *
     * Yang dijaga test ini tidak berubah: Dashboard Pimpinan TIDAK memuat
     * section dampak/SROI di badannya. Jadi yang diperiksa kini isi
     * section-nya (rasio, nilai sosial, sensitivitas) — bukan kata "SROI"
     * yang kini sah muncul sebagai label menu.
     */
    public function test_section_operasional_lama_sudah_tidak_ada(): void
    {
        $this->isiData();
        $this->isiTransaksiKoperasi();

        $respons = $this->actingAs($this->pimpinan())->get(route('dashboard'));

        $respons->assertDontSee('Perlu Perhatian');

        // Dulu ada baris assertDontSee('Dampak Program') di sini. Baris itu
        // DIHAPUS, bukan diperbaiki, karena ternyata tidak pernah menjaga apa
        // pun: ia lolos semata-mata karena kata "Dampak Program" kebetulan
        // belum pernah muncul di mana pun.
        //
        // Dashboard Pimpinan memang MENAMPILKAN ringkasan Dampak Program
        // dengan sengaja — lihat @includeWhen di dashboard-pimpinan.blade.php
        // beserta alasannya. Menahan kata itu berarti menahan sesuatu yang
        // justru harus ada.
        //
        // Yang benar-benar ingin dipastikan test ini — section operasional
        // lama yang penuh angka mentah SROI tidak lagi ada — tetap dijaga
        // oleh keempat baris di bawah.
        $respons->assertDontSee('Rasio SROI');
        $respons->assertDontSee('Nilai Sosial Bersih');
        $respons->assertDontSee('Analisis Sensitivitas');
        $respons->assertDontSee('Trend');
        $respons->assertDontSee('<canvas', false);
    }

    /**
     * Pintu ke modul Dampak & SROI ADA untuk Pimpinan.
     *
     * Sisi lain dari test di atas, dan sengaja ditulis terpisah supaya
     * keduanya tidak bisa saling menutupi: menyetujui hasil SROI sebagai
     * Final adalah kewenangan Pimpinan, dan kewenangan yang tidak punya
     * pintu di sidebar tidak akan pernah dipakai.
     */
    public function test_pimpinan_punya_tautan_ke_modul_dampak_dan_sroi(): void
    {
        $respons = $this->actingAs($this->pimpinan())->get(route('dashboard'));

        $respons->assertOk();
        $respons->assertSee(route('impact.index'), false);
    }

    // =====================================================================
    // Tautan — tidak boleh ada yang buntu
    // =====================================================================

    public function test_tautan_detail_memakai_route_existing_yang_boleh_dibuka(): void
    {
        $respons = $this->actingAs($this->pimpinan())->get(route('dashboard'));

        // Dua CTA "Lihat Detail" menunjuk halaman yang memang boleh dibuka.
        $respons->assertSee('href="' . route('omzet.index') . '"', false);
        $respons->assertSee('href="' . route('koperasi.laporan.index') . '"', false);

        // Dan TIDAK menunjuk halaman Admin-only yang akan menghasilkan 403.
        $respons->assertDontSee('href="' . route('koperasi.keuangan.index') . '"', false);
        $respons->assertDontSee('href="' . route('koperasi.dashboard') . '"', false);
        $respons->assertDontSee('href="' . route('laporan') . '"', false);
        $respons->assertDontSee('href="' . route('participants.index') . '"', false);
    }

    /**
     * Pintasan tambahan di kartu Koperasi. Menu sidebar-nya memang sudah
     * dilepas, jadi ini satu-satunya jalur klik menuju Penjualan Koperasi -
     * dan wajib memakai route EXISTING, bukan jalur baru.
     */
    public function test_kartu_koperasi_punya_pintasan_penjualan_koperasi(): void
    {
        $respons = $this->actingAs($this->pimpinan())->get(route('dashboard'));

        $respons->assertOk();
        $respons->assertSee('Penjualan Koperasi');
        $respons->assertSee('href="' . route('koperasi.penjualan.index') . '"', false);

        // "Lihat Detail" yang lama TIDAK boleh ikut hilang - pintasan ini
        // menambah, bukan menggantikan.
        $respons->assertSee('href="' . route('koperasi.laporan.index') . '"', false);
        $respons->assertSee('Lihat Detail');
    }

    /** Pintasannya harus benar-benar bisa dibuka, bukan sekadar tampil. */
    public function test_pintasan_penjualan_koperasi_benar_benar_terbuka(): void
    {
        $this->actingAs($this->pimpinan())
            ->get(route('koperasi.penjualan.index'))
            ->assertOk();
    }

    /**
     * Pintasan ada di KARTU, bukan di sidebar. Sidebar Pimpinan tetap satu
     * item saja - menambah pintasan tidak boleh diam-diam mengembalikan
     * menunya.
     */
    public function test_pintasan_tidak_mengembalikan_menu_ke_sidebar(): void
    {
        $nav = $this->nav($this->pimpinan());

        $this->assertStringNotContainsString('Penjualan Koperasi', $nav);
        $this->assertStringNotContainsString('href="' . route('koperasi.penjualan.index') . '"', $nav);
    }

    /**
     * Pengaman menyeluruh: setiap tautan di halaman Dashboard Pimpinan
     * ditelusuri satu per satu dan tidak boleh ada yang menolak Pimpinan.
     */
    public function test_tidak_ada_satu_pun_tautan_buntu_di_dashboard(): void
    {
        $this->isiData();
        $this->isiTransaksiKoperasi();
        $pimpinan = $this->pimpinan();

        $html = $this->actingAs($pimpinan)->get(route('dashboard'))->getContent();

        // HANYA elemen <a> — <link rel="icon"> di <head> bukan tautan navigasi
        // dan berkas statis memang tidak dilayani oleh kernel HTTP saat test.
        preg_match_all('/<a\s[^>]*href="(http:\/\/localhost[^"#]*)"/', $html, $cocok);
        $tautan = array_values(array_unique($cocok[1]));

        $this->assertNotEmpty($tautan, 'Halaman harus punya tautan untuk diperiksa.');

        $buntu = [];
        foreach ($tautan as $url) {
            $status = $this->actingAs($pimpinan)->get($url)->getStatusCode();

            if ($status >= 400) {
                $buntu[] = $url . ' (' . $status . ')';
            }
        }

        $this->assertSame([], $buntu, 'Ada tautan yang menolak Pimpinan: ' . implode(', ', $buntu));
    }

    // =====================================================================
    // Logo
    // =====================================================================

    /**
     * Logo CahayaOS punya latar putih yang menyatu di file JPEG-nya, jadi
     * container-nya HARUS putih — kalau diberi warna, latar logo terlihat
     * sebagai kotak yang menempel. Juga wajib object-contain supaya
     * proporsinya tidak gepeng.
     */
    public function test_logo_tampil_bersih_tanpa_container_berwarna(): void
    {
        $html = $this->actingAs($this->pimpinan())->get(route('dashboard'))->getContent();

        // Logo muncul dua kali: sidebar (layout) lalu header konten. Yang
        // diuji di sini yang TERAKHIR — milik header Dashboard Pimpinan.
        $posLogo = strrpos($html, 'logo-cahayaos.jpg');
        $this->assertNotFalse($posLogo, 'Logo header tidak ditemukan.');
        $blokHeader = substr($html, max(0, $posLogo - 400), 800);

        $this->assertStringContainsString('object-contain', $blokHeader);
        $this->assertStringContainsString('bg-white', $blokHeader);
        $this->assertStringNotContainsString('bg-koperasi-primary', $blokHeader);
    }

    /**
     * Logo dipasang POLOS - tanpa pembungkus berbentuk apa pun.
     *
     * Dulu logo dibungkus lingkaran putih di atas latar halaman abu muda,
     * sehingga yang terlihat justru cakram putih mengambang: persis kesan
     * "gambar ditempel". Karena header sekarang berupa kartu putih,
     * pembungkusnya tidak diperlukan lagi. Test ini menjaga agar tidak
     * diam-diam dikembalikan.
     */
    public function test_logo_tidak_dibungkus_container_berbentuk(): void
    {
        $html = $this->actingAs($this->pimpinan())->get(route('dashboard'))->getContent();

        $posLogo = strrpos($html, 'logo-cahayaos.jpg');
        $this->assertNotFalse($posLogo);

        // Ambil elemen pembungkus LANGSUNG si logo - bukan kartu header,
        // yang memang wajar punya latar dan border sendiri.
        $sebelum = substr($html, 0, $posLogo);
        $pembungkus = substr($sebelum, strrpos($sebelum, '<div'));

        // Pembungkusnya hanya baris flex biasa: tanpa bentuk, tanpa latar,
        // tanpa garis - jadi logo benar-benar menyatu dengan kartu putih.
        $this->assertStringNotContainsString('rounded', $pembungkus);
        $this->assertStringNotContainsString('bg-', $pembungkus);
        $this->assertStringNotContainsString('border', $pembungkus);
        $this->assertStringNotContainsString('p-1', $pembungkus);
    }

    /**
     * Header dan filter adalah SATU kepala halaman, bukan dua kartu bertumpuk,
     * dan filternya tetap tunggal serta berada di atas kedua kartu ekosistem.
     */
    public function test_header_dan_filter_menyatu_di_atas_kedua_kartu(): void
    {
        $html = $this->actingAs($this->pimpinan())->get(route('dashboard'))->getContent();

        $posJudul = strpos($html, 'Dashboard Pimpinan</h2>');
        $posForm = strpos($html, '<form method="GET"');
        $posKartu = strpos($html, 'grid-cols-1 md:grid-cols-2');

        $this->assertNotFalse($posJudul);
        $this->assertNotFalse($posForm);
        $this->assertNotFalse($posKartu);

        // Urutan: judul -> filter -> dua kartu.
        $this->assertLessThan($posForm, $posJudul);
        $this->assertLessThan($posKartu, $posForm);

        // Satu filter saja - tidak ada filter terpisah per ekosistem.
        $this->assertSame(1, substr_count($html, '<form method="GET"'));
    }
}
