<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Dashboard gabungan — SATU halaman (route 'dashboard') dengan switcher
 * internal ?modul=koperasi. Test ini menjaga supaya:
 *  - panel yang tampil sesuai modul yang dipilih,
 *  - switcher HANYA muncul untuk Admin,
 *  - link lama /koperasi/dashboard tidak jadi 404 (redirect),
 *  - Dashboard Pimpinan yang terpisah tidak ikut kena imbas.
 */
class DashboardSwitcherTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role, string $email): User
    {
        return User::create([
            'name' => 'User ' . $role,
            'email' => $email,
            'password' => 'password123',
            'role' => $role,
        ]);
    }

    /**
     * Tanpa query param, Admin mendarat di tab NETRAL "Semua Kegiatan" —
     * tidak ada modul yang otomatis tersorot.
     */
    public function test_admin_tanpa_query_param_dapat_tab_semua_kegiatan(): void
    {
        $admin = $this->user('admin', 'admin-dash@example.com');

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Semua Kegiatan');
        // Pusat monitoring: Keuangan Koperasi + ringkasan kedua modul.
        $response->assertSeeInOrder(['Keuangan Koperasi', 'Total Penjualan', 'HPP Terjual', 'Laba Kotor',
            'Pengeluaran Operasional', 'Piutang Aktif', 'Laba Bersih']);
        $response->assertSee('Peserta Aktif');
        $response->assertSee('Peserta Berkembang');
        $response->assertSee('Anggota');
        $response->assertSee('Produk');
        $response->assertSee('Produk Terlaris');
        $response->assertSee('Unit Usaha');
        // Hak Mitra & Fee bukan KPI utama.
        $response->assertDontSee('Hak Mitra');

        // TIDAK memuat statistik khas salah satu modul.
        $response->assertDontSee('Fee Titip Jual');
        $response->assertDontSee('Status Legalitas');
        // Tidak ada subtitle modul — kalau ada, satu modul jadi terlihat terpilih.
        $response->assertDontSee('Cahaya Preneur — Program Ekonomi YBM PLN UP3 Bengkulu');
        $response->assertDontSee('Koperasi Cahaya — Usaha Bersama, Maju Bersama');
    }

    /** Panel Cahaya Preneur muncul HANYA kalau tabnya sengaja dipilih. */
    public function test_admin_dengan_modul_preneur_dapat_panel_cahaya_preneur(): void
    {
        $admin = $this->user('admin', 'admin-dash@example.com');

        $response = $this->actingAs($admin)->get(route('dashboard', ['modul' => 'preneur']));

        $response->assertOk();
        $response->assertSee('Cahaya Preneur — Program Ekonomi YBM PLN UP3 Bengkulu');
        $response->assertSee('Total Peserta');
        $response->assertSee('Status Legalitas');
        $response->assertDontSee('Fee Titip Jual');
    }

    /**
     * Ambil HANYA blok switcher. Tidak bisa pakai strpos di seluruh <main>:
     * baris identitas ("CahayaOS — Sistem Terpadu Cahaya Preneur & Koperasi
     * Cahaya") memuat nama kedua modul dan letaknya di ATAS switcher.
     */
    private function switcher(array $query = []): string
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin-dash@example.com'],
            ['name' => 'User admin', 'password' => 'password123', 'role' => 'admin']
        );

        $html = $this->actingAs($admin)->get(route('dashboard', $query))->getContent();
        preg_match('/<div class="inline-flex[^"]*">(.*?)<\/div>/s', $html, $m);

        $this->assertNotEmpty($m, 'Blok switcher tidak ditemukan');

        return $m[1];
    }

    /** Ketiga tab ada, dan PERSIS SATU yang aktif di tiap keadaan. */
    public function test_switcher_punya_tiga_tab_dan_satu_aktif(): void
    {
        foreach ([[], ['modul' => 'preneur'], ['modul' => 'koperasi']] as $query) {
            $switcher = $this->switcher($query);

            foreach (['Semua Kegiatan', 'Cahaya Preneur', 'Koperasi Cahaya'] as $label) {
                $this->assertStringContainsString($label, $switcher, "Tab '{$label}' tidak ada");
            }

            $this->assertSame(
                1,
                substr_count($switcher, 'bg-cahaya-primary text-white'),
                'Harus persis satu tab yang aktif'
            );
        }
    }

    /** Urutan tab WAJIB: Semua Kegiatan | Cahaya Preneur | Koperasi Cahaya. */
    public function test_urutan_tab_switcher(): void
    {
        $switcher = $this->switcher();

        $posisi = -1;
        foreach (['Semua Kegiatan', 'Cahaya Preneur', 'Koperasi Cahaya'] as $label) {
            $p = strpos($switcher, $label);
            $this->assertNotFalse($p, "Tab '{$label}' tidak ditemukan");
            $this->assertGreaterThan($posisi, $p, "Tab '{$label}' salah urutan");
            $posisi = $p;
        }
    }

    public function test_admin_dengan_modul_koperasi_dapat_panel_koperasi(): void
    {
        $admin = $this->user('admin', 'admin-dash@example.com');

        $response = $this->actingAs($admin)->get(route('dashboard', ['modul' => 'koperasi']));

        $response->assertOk();
        $response->assertSee('Koperasi Cahaya — Usaha Bersama, Maju Bersama');

        // Keterangan periode PINDAH dari subtitle header ke dalam panel,
        // menempel pada angka yang memang dibatasi periode itu.
        $response->assertDontSee('Koperasi Cahaya — Periode:');
        $response->assertSee('Ringkasan Laba-Rugi');
        $response->assertSee('Periode: Tahun ' . now()->year . ', semua bulan');
        // Enam KPI utama. Fee Titip Jual & Hak Mitra sengaja BUKAN KPI lagi
        // (hak mitra dihitung koperasi di luar sistem).
        $response->assertSeeInOrder(['Total Penjualan', 'HPP Terjual', 'Laba Kotor', 'Pengeluaran', 'Piutang Aktif', 'Laba Bersih']);
        $response->assertDontSee('Fee Titip Jual');
        $response->assertSee('Performa Unit Usaha');
        $response->assertSee('Simpanan Anggota');
        // Isi khas panel Cahaya Preneur TIDAK ikut ter-render.
        $response->assertDontSee('Status Legalitas');
    }

    public function test_switcher_hanya_muncul_untuk_admin(): void
    {
        $admin = $this->user('admin', 'admin-dash@example.com');
        $html = $this->actingAs($admin)->get(route('dashboard'))->getContent();
        $main = substr($html, strpos($html, '<main'));

        $this->assertStringContainsString('dashboard?modul=koperasi', $main, 'Admin harus punya tombol switcher ke Koperasi');
    }

    public function test_pendamping_tidak_punya_switcher_dan_tidak_bisa_paksa_modul_koperasi(): void
    {
        $pendamping = $this->user('pendamping', 'pendamping-dash@example.com');

        $html = $this->actingAs($pendamping)->get(route('dashboard'))->getContent();
        $main = substr($html, strpos($html, '<main'));
        $this->assertStringNotContainsString('modul=koperasi', $main, 'Pendamping tidak boleh melihat switcher Koperasi');

        // Memaksa lewat URL pun tetap dapat panel Cahaya Preneur, bukan data Koperasi.
        $paksa = $this->actingAs($pendamping)->get(route('dashboard', ['modul' => 'koperasi']));
        $paksa->assertOk();
        $paksa->assertSee('Cahaya Preneur — Program Ekonomi YBM PLN UP3 Bengkulu');
        $paksa->assertDontSee('Fee Titip Jual');
    }

    public function test_route_koperasi_dashboard_lama_redirect_bukan_404(): void
    {
        $admin = $this->user('admin', 'admin-dash@example.com');

        $response = $this->actingAs($admin)->get(route('koperasi.dashboard'));

        $response->assertRedirect(route('dashboard', ['modul' => 'koperasi']));
    }

    public function test_non_admin_tetap_ditolak_di_route_koperasi_dashboard(): void
    {
        $pendamping = $this->user('pendamping', 'pendamping-dash@example.com');

        $response = $this->actingAs($pendamping)->get(route('koperasi.dashboard'));

        $response->assertForbidden();
    }

    /**
     * Pimpinan punya Dashboard SENDIRI (view 'dashboard-pimpinan') dan TIDAK
     * ikut jalur switcher — cabang isPimpinan() dicek paling awal di index().
     */
    public function test_dashboard_pimpinan_tidak_terpengaruh(): void
    {
        $pimpinan = $this->user('pimpinan', 'pimpinan-dash@example.com');

        $response = $this->actingAs($pimpinan)->get(route('dashboard'));

        $response->assertOk();
        $response->assertViewIs('dashboard-pimpinan');
        // Switcher modul TIDAK ada di Dashboard Pimpinan.
        $response->assertDontSee('dashboard?modul=koperasi');
    }

    /**
     * Grafik Penjualan Koperasi di Dashboard Pimpinan dulu pakai
     * selectRaw('MONTH(tanggal)') yang khusus MySQL dan bikin halaman ini
     * error di driver lain. Test ini menjaga supaya pengelompokan per bulan
     * tetap portabel — sekaligus memastikan angkanya benar-benar terhitung,
     * bukan cuma "tidak error".
     */
    public function test_grafik_penjualan_koperasi_per_bulan_portabel_dan_benar(): void
    {
        $pimpinan = $this->user('pimpinan', 'pimpinan-dash@example.com');

        $unit = \App\Models\BusinessUnit::create([
            'nama' => 'Unit Uji',
            'kode' => 'UJI-01',
            'tipe' => 'Barang',
            'status' => 'Aktif',
        ]);

        // 2 transaksi di bulan yang SAMA (harus dijumlahkan jadi satu titik),
        // 1 di bulan lain, dan 1 "Dibatalkan" yang HARUS diabaikan.
        // Tiap transaksi punya item — tren memakai definisi yang SAMA dengan
        // KPI Total Penjualan (SUM subtotal item, LabaRugiService).
        $buat = function (string $tanggal, int $total, string $status) use ($unit) {
            $trx = \App\Models\SalesTransaction::create([
                'unit_id' => $unit->id,
                'tanggal' => $tanggal,
                'status_transaksi' => $status,
                'subtotal' => $total,
                'diskon' => 0,
                'ongkir' => 0,
                'total' => $total,
                'status_pembayaran' => 'Lunas',
                'dp_nominal' => $total,
            ]);
            $trx->items()->create([
                'product_service_id' => null, 'nama_snapshot' => 'Produk Uji', 'jenis_snapshot' => 'Produk',
                'qty' => 1, 'harga_jual_snapshot' => $total, 'harga_modal_snapshot' => 0,
                'diskon' => 0, 'subtotal' => $total,
            ]);

            return $trx;
        };

        $tahun = now()->year;
        $buat("{$tahun}-03-05", 100000, 'Selesai');
        $buat("{$tahun}-03-20", 50000, 'Selesai');
        $buat("{$tahun}-07-11", 70000, 'Aktif');
        $buat("{$tahun}-07-12", 999999, 'Dibatalkan');

        $response = $this->actingAs($pimpinan)->get(route('dashboard'));

        $response->assertOk();

        $data = $response->viewData('koperasiChart')['data'];

        $this->assertCount(12, $data);
        $this->assertSame(150000, $data[2], 'Maret harus 100.000 + 50.000');
        $this->assertSame(70000, $data[6], 'Juli harus 70.000 saja (yang Dibatalkan diabaikan)');
        $this->assertSame(0, $data[0], 'Januari tidak ada transaksi');

        // Jumlah tren setahun = Total Penjualan setahun (satu definisi).
        $total = app(\App\Services\LabaRugiService::class)->hitung((string) $tahun, 'semua')['total']['penjualan'];
        $this->assertEquals($total, array_sum($data));
    }

    /**
     * Dashboard murni halaman RINGKASAN — tidak boleh ada tombol aksi apa pun
     * di dalamnya (dulu ada "+ Tambah Peserta", "Catat Pendampingan",
     * "Input Omzet", dan padanan Koperasinya).
     */
    public function test_dashboard_tidak_punya_tombol_aksi(): void
    {
        $admin = $this->user('admin', 'admin-dash@example.com');

        foreach ([[], ['modul' => 'koperasi']] as $q) {
            $html = $this->actingAs($admin)->get(route('dashboard', $q))->getContent();
            $main = substr($html, strpos($html, '<main'));

            foreach ([
                '+ Tambah Peserta', 'Catat Pendampingan', 'Input Omzet',
                '+ Tambah Penjualan', 'Catat Pengeluaran',
            ] as $tombol) {
                $this->assertStringNotContainsString($tombol, $main, "Tombol aksi '{$tombol}' masih ada di Dashboard");
            }

            // Filter periode panel Koperasi (form GET, hanya membaca) bukan
            // tombol aksi. Selain form itu, tidak boleh ada <button> maupun
            // <form> di area konten Dashboard.
            $this->assertStringNotContainsString('method="POST"', $main);
            $main = preg_replace('#<form method="GET".*?</form>#s', '', $main);
            $this->assertStringNotContainsString('<button', $main);
            $this->assertStringNotContainsString('<form', $main);
        }
    }

    /**
     * Klik label accordion harus mendarat di halaman daftar modulnya, dan di
     * sana accordion-nya terbuka dengan submenu yang tepat ter-highlight.
     */
    public function test_label_accordion_mendarat_di_daftar_modul_dengan_state_benar(): void
    {
        $admin = $this->user('admin', 'admin-dash@example.com');

        $kasus = [
            ['participants.index', 'toggle-cp', 'toggle-kop', 'Peserta'],
            ['koperasi.anggota.index', 'toggle-kop', 'toggle-cp', 'Anggota'],
        ];

        foreach ($kasus as [$rute, $terbuka, $tertutup, $submenu]) {
            $response = $this->actingAs($admin)->get(route($rute));
            $response->assertOk();

            $html = $response->getContent();
            $nav = substr($html, strpos($html, '<nav'), strpos($html, '</nav>') - strpos($html, '<nav'));

            $this->assertStringContainsString('id="' . $terbuka . '"', $nav);
            $this->assertMatchesRegularExpression(
                '/id="' . $terbuka . '"[^>]*aria-expanded="true"/',
                $nav,
                "Accordion {$terbuka} harus terbuka di halaman {$rute}"
            );
            $this->assertMatchesRegularExpression(
                '/id="' . $tertutup . '"[^>]*aria-expanded="false"/',
                $nav,
                "Accordion {$tertutup} harus tertutup di halaman {$rute}"
            );

            // Persis SATU item menyala, dan itu submenu-nya (bukan label header).
            $this->assertSame(1, substr_count($nav, 'bg-primary-light text-cahaya-primary'),
                "Harus persis satu item menyala di {$rute}");
        }
    }

    /**
     * Favicon wajib ada di layout utama maupun halaman login, dan HARUS
     * menunjuk versi kecil hasil generate (2,4 KB) — bukan logo asli
     * 1,08 MB yang akan diunduh ulang di setiap halaman.
     */
    public function test_favicon_terpasang(): void
    {
        $tag = 'rel="icon" type="image/png" href="' . asset('images/favicon-cahayaos.png') . '"';

        // Ketiga berkas ikon ada dan tetap ringan.
        foreach ([
            'images/favicon-cahayaos.png' => 20 * 1024,
            'apple-touch-icon.png' => 40 * 1024,
            'favicon.ico' => 60 * 1024,
        ] as $berkas => $batas) {
            $this->assertFileExists(public_path($berkas));
            $this->assertLessThan($batas, filesize(public_path($berkas)), "{$berkas} kebesaran");
        }

        // favicon.ico WAJIB benar-benar berformat ICO (dulu file 0 byte bawaan
        // Laravel). Magic bytes: 00 00 01 00 = reserved 0, type 1 (icon).
        $ico = file_get_contents(public_path('favicon.ico'));
        $kepala = unpack('vreserved/vtype/vjumlah', substr($ico, 0, 6));
        $this->assertSame(0, $kepala['reserved']);
        $this->assertSame(1, $kepala['type'], 'favicon.ico harus bertipe icon');
        $this->assertSame(3, $kepala['jumlah'], 'favicon.ico harus memuat 3 ukuran (16/32/48)');

        // apple-touch-icon harus PNG 180x180 dan OPAQUE (iOS menolak alfa).
        $apple = getimagesize(public_path('apple-touch-icon.png'));
        $this->assertSame('image/png', $apple['mime']);
        $this->assertSame([180, 180], [$apple[0], $apple[1]]);

        // Halaman login dicek DULU sebagai tamu — kalau sudah actingAs(),
        // middleware 'guest' akan mengalihkan /login ke dashboard.
        $apel = 'rel="apple-touch-icon" sizes="180x180"';

        $login = $this->get(route('login'));
        $login->assertSee($tag, false);
        $login->assertSee($apel, false);

        $admin = $this->user('admin', 'admin-dash@example.com');
        $dash = $this->actingAs($admin)->get(route('dashboard'));
        $dash->assertSee($tag, false);
        $dash->assertSee($apel, false);
    }

    /**
     * Breadcrumb topbar: Dashboard netral ("CahayaOS" saja) karena gabungan
     * dua modul; halaman lain TETAP menyebut modulnya masing-masing.
     */
    public function test_breadcrumb_dashboard_netral_halaman_lain_tetap(): void
    {
        $admin = $this->user('admin', 'admin-dash@example.com');

        $ambil = function (string $url) use ($admin) {
            $html = $this->actingAs($admin)->get($url)->getContent();
            $awal = strpos($html, '<header');
            $potong = substr($html, $awal, strpos($html, '</header>') - $awal);
            preg_match('/CahayaOS[^<]*/', $potong, $m);

            return trim(preg_replace('/\s+/', ' ', html_entity_decode($m[0] ?? '')));
        };

        // Dashboard — kedua tab sama-sama netral.
        $this->assertSame('CahayaOS', $ambil(route('dashboard')));
        $this->assertSame('CahayaOS', $ambil(route('dashboard', ['modul' => 'koperasi'])));

        // Halaman modul TIDAK berubah — tetap menyebut modulnya.
        $this->assertSame('CahayaOS · Cahaya Preneur', $ambil(route('participants.index')));
        $this->assertStringStartsWith('CahayaOS · ', $ambil(route('koperasi.anggota.index')));
        $this->assertNotSame('CahayaOS', $ambil(route('koperasi.anggota.index')));

        // Halaman di section SISTEM berkonteks "Sistem" — BUKAN "Cahaya Preneur".
        // Dulu ketiganya salah berlabel karena logikanya biner (bukan Koperasi
        // = dianggap Cahaya Preneur).
        foreach ([
            'pengaturan-sistem.index',
            'manajemen-user.index',
            'backup-database.index',
        ] as $rute) {
            $this->assertSame('CahayaOS · Sistem', $ambil(route($rute)), "Breadcrumb salah di {$rute}");
        }

        // Halaman akun pribadi — bukan milik modul manapun.
        $this->assertSame('CahayaOS · Akun', $ambil(route('account.password.edit')));
    }

    /**
     * Baris identitas sistem SELALU tampil — tidak bergantung tab manapun —
     * dan berada DI ATAS subtitle modul yang berubah-ubah.
     */
    public function test_identitas_sistem_selalu_tampil_di_atas_subtitle_modul(): void
    {
        $identitas = 'CahayaOS — Sistem Terpadu Cahaya Preneur & Koperasi Cahaya';

        $kasus = [
            [['modul' => 'preneur'], 'Cahaya Preneur — Program Ekonomi YBM PLN UP3 Bengkulu'],
            [['modul' => 'koperasi'], 'Koperasi Cahaya — Usaha Bersama, Maju Bersama'],
        ];

        $admin = $this->user('admin', 'admin-dash@example.com');

        // Di tab netral "Semua Kegiatan" baris identitas TETAP tampil,
        // walau di situ memang tidak ada subtitle modul di bawahnya.
        $this->actingAs($admin)->get(route('dashboard'))->assertSee($identitas);

        foreach ($kasus as [$query, $subtitleModul]) {
            $html = $this->actingAs($admin)->get(route('dashboard', $query))->getContent();
            $main = substr($html, strpos($html, '<main'));

            $posIdentitas = strpos($main, htmlspecialchars($identitas, ENT_QUOTES));
            $posSubtitle = strpos($main, $subtitleModul);

            $this->assertNotFalse($posIdentitas, 'Baris identitas sistem tidak muncul');
            $this->assertNotFalse($posSubtitle, 'Subtitle modul tidak muncul');
            $this->assertLessThan(
                $posSubtitle,
                $posIdentitas,
                'Baris identitas harus DI ATAS subtitle modul'
            );
        }

        // Juga tampil untuk Pendamping (yang tidak punya switcher sama sekali).
        $pendamping = $this->user('pendamping', 'pendamping-dash@example.com');
        $this->actingAs($pendamping)->get(route('dashboard'))->assertSee($identitas);
    }

    public function test_urutan_section_sidebar_admin(): void
    {
        $admin = $this->user('admin', 'admin-dash@example.com');
        $html = $this->actingAs($admin)->get(route('dashboard'))->getContent();
        $nav = substr($html, strpos($html, '<nav'), strpos($html, '</nav>') - strpos($html, '<nav'));

        $urutan = ['Dashboard', 'Kegiatan', 'Cahaya Preneur', 'Koperasi Cahaya', 'Sistem', 'Manajemen User', 'Backup Database'];
        $posisi = -1;
        foreach ($urutan as $label) {
            $p = strpos($nav, $label);
            $this->assertNotFalse($p, "'{$label}' tidak ditemukan di sidebar");
            $this->assertGreaterThan($posisi, $p, "'{$label}' muncul di urutan yang salah");
            $posisi = $p;
        }

        // Sub-teks identitas modul SUDAH DIHAPUS — label kembali teks tunggal.
        $this->assertStringNotContainsString('Usaha Bersama, Maju Bersama', $nav);

        // LABEL accordion menuju halaman daftar modul, BUKAN Dashboard.
        $this->assertStringContainsString(route('participants.index'), $nav);
        $this->assertStringContainsString(route('koperasi.anggota.index'), $nav);
        $this->assertStringNotContainsString('dashboard?modul=koperasi', $nav,
            'Sidebar tidak boleh lagi memakai dashboard?modul=koperasi sebagai target klik');
    }

    public function test_state_aktif_sidebar_saat_panel_koperasi_dibuka(): void
    {
        $admin = $this->user('admin', 'admin-dash@example.com');
        $html = $this->actingAs($admin)->get(route('dashboard', ['modul' => 'koperasi']))->getContent();
        $nav = substr($html, strpos($html, '<nav'), strpos($html, '</nav>') - strpos($html, '<nav'));

        // Grup Koperasi terbuka otomatis (konteks), grup Cahaya Preneur tertutup.
        $this->assertStringContainsString('id="toggle-kop" aria-controls="group-kop" aria-expanded="true"', $nav);
        $this->assertStringContainsString('id="toggle-cp" aria-controls="group-cp" aria-expanded="false"', $nav);
        // Persis SATU item menyala, yaitu link "Dashboard" — karena Dashboard
        // kini SATU-SATUNYA jalan masuk ke halaman ini (label accordion tidak
        // lagi menuju Dashboard, jadi tidak ikut menyala).
        $this->assertSame(1, substr_count($nav, 'bg-primary-light text-cahaya-primary'));
    }
}
