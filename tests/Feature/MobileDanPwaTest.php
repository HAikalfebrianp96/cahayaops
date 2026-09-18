<?php

namespace Tests\Feature;

use App\Models\Participant;
use App\Models\Pendamping;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * MOBILE FRIENDLY & PWA.
 *
 * Pendamping adalah pengguna yang paling sering membuka CahayaOS dari
 * ponsel, di lapangan. Dua hal yang dijaga kelas ini:
 *
 *   1. NAVIGASI TETAP ADA DI PONSEL. Sebelumnya sidebar memakai
 *      `hidden md:flex` — di layar kecil ia hilang sama sekali TANPA cara
 *      membukanya, sehingga pengguna hanya bisa berpindah lewat tautan yang
 *      kebetulan ada di halaman.
 *
 *   2. TABEL TIDAK MEMOTONG DATA. Setiap tabel harus berada dalam pembungkus
 *      yang bisa digeser mendatar; tanpa itu kolom terakhir terpotong di
 *      layar sempit dan tombol aksinya tidak bisa dijangkau.
 */
class MobileDanPwaTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $pendamping;

    protected function setUp(): void
    {
        parent::setUp();

        $profil = Pendamping::create(['nama' => 'Rosa Linda', 'nomor_hp' => '0812', 'email' => 'a@mob.test', 'status' => 'Aktif']);

        $this->admin = $this->user('admin', 'admin@mob.test');
        $this->pendamping = $this->user('pendamping', 'pd@mob.test', $profil->id);

        Participant::create([
            'nama_peserta' => 'Binaan Uji', 'nomor_hp' => '0812', 'alamat' => 'Jl. Uji',
            'nama_usaha' => 'Usaha Uji', 'kategori_usaha' => 'Jasa',
            'tanggal_mulai_program' => '2026-01-10', 'status' => 'Aktif',
            'pendamping_id' => $profil->id,
        ]);
    }

    private function user(string $role, string $email, ?int $pendampingId = null): User
    {
        return User::create([
            'name' => 'User ' . $email, 'email' => $email, 'password' => 'password123',
            'role' => $role, 'pendamping_id' => $pendampingId, 'is_active' => true,
        ]);
    }

    // =====================================================================
    // NAVIGASI DI PONSEL
    // =====================================================================

    /** INTI: sidebar bisa dibuka di layar kecil, tidak lagi hilang total. */
    public function test_sidebar_bisa_dibuka_di_layar_kecil(): void
    {
        $html = $this->actingAs($this->pendamping)->get(route('dashboard'))->getContent();

        // Tombol pembuka ada dan hanya muncul di layar kecil.
        $this->assertStringContainsString('bukaSidebar()', $html);
        $this->assertStringContainsString('aria-label="Buka menu"', $html);
        $this->assertStringContainsString('md:hidden', $html);

        // Sidebar-nya sendiri TIDAK lagi disembunyikan permanen.
        $this->assertStringContainsString('id="sidebar"', $html);
        $this->assertStringNotContainsString('hidden md:flex md:flex-col', $html);

        // Latar penutup + fungsi menutupnya tersedia.
        $this->assertStringContainsString('id="sidebarOverlay"', $html);
        $this->assertStringContainsString('tutupSidebar()', $html);
    }

    public function test_sidebar_tetap_terlihat_di_desktop(): void
    {
        $html = $this->actingAs($this->admin)->get(route('dashboard'))->getContent();

        // md:static + md:translate-x-0 = kembali menjadi kolom biasa di desktop.
        $this->assertStringContainsString('md:static', $html);
        $this->assertStringContainsString('md:translate-x-0', $html);
    }

    /** Menu tetap utuh — drawer hanya mengubah cara menampilkannya. */
    public function test_isi_menu_tidak_berubah_karena_drawer(): void
    {
        $html = $this->actingAs($this->pendamping)->get(route('dashboard'))->getContent();
        $nav = substr($html, strpos($html, '<nav'), strpos($html, '</nav>') - strpos($html, '<nav'));

        $this->assertStringContainsString('Binaan Saya', $nav);
        // Menu "Follow Up" kini bernama "Tugas Saya" untuk Pendamping —
        // route dan halamannya sama, hanya namanya yang berubah. Tujuan test
        // ini tidak berubah: memastikan drawer memuat menu yang sama dengan
        // sidebar desktop, bukan menu yang lebih sedikit.
        $this->assertStringContainsString('Tugas Saya', $nav);
    }

    // =====================================================================
    // TABEL AMAN DI LAYAR SEMPIT
    // =====================================================================

    /**
     * Setiap halaman kerja utama harus punya pembungkus geser mendatar
     * sebanyak tabelnya — kalau tidak, kolom aksi terpotong di ponsel.
     */
    public function test_tabel_halaman_utama_bisa_digeser_mendatar(): void
    {
        $halaman = [
            'Daftar Peserta' => [$this->admin, route('participants.index')],
            'Binaan Saya' => [$this->pendamping, route('binaan.index')],
            'Follow Up' => [$this->pendamping, route('follow-up.index')],
            'Laporan Pendamping' => [$this->admin, route('laporan.pendamping')],
            'Dashboard Pendamping' => [$this->pendamping, route('dashboard')],
        ];

        foreach ($halaman as $label => [$user, $url]) {
            $html = $this->actingAs($user)->get($url)->assertOk()->getContent();

            $jumlahTabel = substr_count($html, '<table');
            $jumlahPembungkus = substr_count($html, 'overflow-x-auto');

            if ($jumlahTabel === 0) {
                continue;
            }

            $this->assertGreaterThanOrEqual(
                $jumlahTabel,
                $jumlahPembungkus,
                "Ada tabel tanpa pembungkus geser mendatar di halaman: $label"
            );
        }
    }

    /** Form penting tetap satu kolom di ponsel, melebar di layar besar. */
    public function test_form_utama_responsif(): void
    {
        $peserta = Participant::first();

        foreach ([
            'Tambah Peserta' => route('participants.create'),
            'Edit Peserta' => route('participants.edit', $peserta),
        ] as $label => $url) {
            $html = $this->actingAs($this->pendamping)->get($url)->assertOk()->getContent();

            // grid-cols-1 + md:grid-cols-2 = satu kolom di ponsel.
            $this->assertStringContainsString('grid-cols-1', $html, "Form tidak responsif: $label");
            $this->assertStringContainsString('md:grid-cols-2', $html, "Form tidak responsif: $label");
        }
    }

    /** Halaman kerja pendamping terbuka tanpa error di semua rute utamanya. */
    public function test_halaman_kerja_pendamping_terbuka_semua(): void
    {
        $peserta = Participant::first();

        foreach ([
            route('dashboard'),
            route('binaan.index'),
            route('follow-up.index'),
            route('participants.show', $peserta),
            route('participants.create'),
            route('participants.edit', $peserta),
            route('omzet.create', $peserta),
            route('laporan.pendamping'),
        ] as $url) {
            $this->actingAs($this->pendamping)->get($url)->assertOk("Halaman gagal dibuka: $url");
        }
    }

    // =====================================================================
    // PWA
    // =====================================================================

    public function test_meta_pwa_lengkap_di_setiap_halaman(): void
    {
        $html = $this->actingAs($this->pendamping)->get(route('binaan.index'))->getContent();

        $this->assertStringContainsString('rel="manifest"', $html);
        $this->assertStringContainsString('name="theme-color"', $html);
        $this->assertStringContainsString('mobile-web-app-capable', $html);
        $this->assertStringContainsString('viewport', $html);
    }

    public function test_manifest_menunjuk_halaman_awal_yang_valid(): void
    {
        $manifest = json_decode(file_get_contents(public_path('manifest.webmanifest')), true);

        // start_url harus benar-benar route yang ada, bukan tautan mati.
        $this->assertSame('/dashboard', $manifest['start_url']);
        $this->actingAs($this->pendamping)->get($manifest['start_url'])->assertOk();

        // Begitu pula pintasannya.
        foreach ($manifest['shortcuts'] ?? [] as $pintasan) {
            $this->actingAs($this->pendamping)
                ->get($pintasan['url'])
                ->assertOk('Pintasan PWA menunjuk halaman yang tidak bisa dibuka: ' . $pintasan['url']);
        }
    }

    /**
     * Pendaftaran service worker tidak boleh menjatuhkan halaman di browser
     * yang tidak mendukungnya.
     */
    public function test_pendaftaran_service_worker_aman(): void
    {
        $html = $this->actingAs($this->admin)->get(route('dashboard'))->getContent();

        $this->assertStringContainsString("'serviceWorker' in navigator", $html);
        $this->assertStringContainsString('.catch(', $html);
    }
}
