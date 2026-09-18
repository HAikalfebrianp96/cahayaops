<?php

namespace Tests\Feature\Uat;

use App\Models\Participant;
use App\Models\Pendamping;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SKRIP YANG BENAR-BENAR DIKIRIM KE BROWSER.
 *
 * =====================================================================
 * KENAPA TEST INI ADA
 * =====================================================================
 *
 * Seluruh test lain memeriksa HTML sebagai teks: ada tulisan ini, tidak ada
 * tulisan itu. Tidak satu pun pernah memeriksa JAVASCRIPT-nya, padahal
 * halaman-halaman di CahayaOS memuat skrip inline untuk grafik, tab, dan
 * sidebar.
 *
 * Akibatnya ada satu kelas kekeliruan yang tidak mungkin tertangkap:
 *
 *   - salah ketik sintaksis membuat SELURUH blok skrip di halaman itu mati,
 *     dan halamannya tetap membalas HTTP 200 dengan tenang;
 *   - getElementById() ke id yang tidak pernah ada TIDAK memunculkan error
 *     apa pun — kodenya diam, fiturnya sekadar tidak jalan.
 *
 * Keduanya hanya terlihat kalau ada yang membuka halamannya di browser dan
 * kebetulan memperhatikan. Test ini memeriksanya tanpa browser: skripnya
 * diambil dari halaman yang sudah dirender, lalu diurai `node --check`.
 */
class SkripHalamanTest extends TestCase
{
    use RefreshDatabase;

    private array $akun = [];
    private Participant $peserta;
    private Pendamping $profil;

    protected function setUp(): void
    {
        parent::setUp();

        $this->profil = Pendamping::create([
            'nama' => 'Pendamping Uji', 'nomor_hp' => '081200000001',
            'email' => 'p@skrip.test', 'status' => 'Aktif',
        ]);

        foreach ([
            'admin' => [User::class, 'admin', null],
            'pimpinan' => [User::class, 'pimpinan', null],
            'pendamping' => [User::class, 'pendamping', $this->profil->id],
            'tim_koperasi' => [User::class, User::ROLE_TIM_KOPERASI, null],
        ] as $nama => [$kelas, $role, $pendampingId]) {
            $this->akun[$nama] = User::create([
                'name' => 'User ' . $nama,
                'email' => $nama . '@skrip.test',
                'password' => 'password123',
                'role' => $role,
                'pendamping_id' => $pendampingId,
                'is_active' => true,
            ]);
        }

        $this->peserta = Participant::create([
            'nama_peserta' => 'Peserta Uji', 'nomor_hp' => '081200000002',
            'alamat' => 'Jl. Uji', 'nama_usaha' => 'Usaha Uji',
            'kategori_usaha' => 'Jasa',
            'tanggal_mulai_program' => now()->subMonths(3)->toDateString(),
            'status' => 'Aktif', 'pendamping_id' => $this->profil->id,
        ]);

        // ============================================================
        // DATA OMZET WAJIB ADA — kalau tidak, test ini kehilangan giginya.
        //
        // Blok skrip grafik dibungkus @if (array_sum($omzetChart['data']) > 0).
        // Dengan database test yang kosong, syarat itu tidak pernah terpenuhi,
        // skripnya tidak ikut dirender, dan justru JAVASCRIPT PALING RUMIT di
        // seluruh aplikasi tidak pernah diperiksa.
        //
        // Ini terbukti saat test-nya diuji: sintaksis grafik dirusak dengan
        // sengaja dan test-nya tetap lulus.
        // ============================================================
        foreach (range(1, 6) as $bulan) {
            \App\Models\OmzetReport::create([
                'participant_id' => $this->peserta->id,
                'periode_bulan' => $bulan,
                'periode_tahun' => now()->year,
                'omzet' => 1000000 * $bulan,
                'hpp' => 400000 * $bulan,
            ]);
        }

        \App\Models\PendampinganRecord::create([
            'participant_id' => $this->peserta->id,
            'pendamping_id' => $this->profil->id,
            'tanggal' => now()->subDays(20)->toDateString(),
            'jenis_kegiatan' => 'Konsultasi',
            'materi' => 'Materi uji',
        ]);
    }

    /** @return list<string> */
    private function halaman(): array
    {
        return [
            route('dashboard', ['modul' => 'preneur']),
            route('dashboard', ['modul' => 'koperasi']),
            route('prioritas.index'),
            route('follow-up.index'),
            route('participants.show', $this->peserta),
            route('perkembangan.index', $this->peserta),
            route('kpi-pendamping.index'),
            route('kpi-pendamping.show', $this->profil),
            route('target-pendamping.index'),
            route('target-pendamping.create'),
            route('impact.index'),
            route('impact.proxy.index'),
            route('arsip.index'),
            route('laporan'),
            route('pengaturan.index'),
        ];
    }

    /**
     * Halaman yang membalas 200, per peran.
     *
     * 403/404 dilewati dengan sengaja: itu penjagaan hak akses yang memang
     * diuji di tempat lain, bukan kegagalan.
     *
     * @return array<string, string> [penanda => html]
     */
    private function halamanTerbuka(): array
    {
        $hasil = [];

        foreach ($this->akun as $peran => $user) {
            foreach ($this->halaman() as $url) {
                $respons = $this->actingAs($user)->get($url);

                if ($respons->getStatusCode() === 200) {
                    $hasil["{$url} [{$peran}]"] = $respons->getContent();
                }
            }
        }

        $this->assertNotEmpty($hasil, 'Tidak ada halaman yang berhasil dirender.');

        return $hasil;
    }

    /** Skrip inline (tanpa src, bukan blok data) dari sebuah halaman. */
    private function skripInline(string $html): string
    {
        preg_match_all('/<script\b([^>]*)>(.*?)<\/script>/is', $html, $cocok, PREG_SET_ORDER);

        $js = '';

        foreach ($cocok as $skrip) {
            [$atribut, $isi] = [$skrip[1], $skrip[2]];

            if (str_contains($atribut, 'src=')) {
                continue;
            }

            // Blok non-JavaScript (mis. application/json) bukan urusan di sini.
            if (preg_match('/type\s*=\s*["\']([^"\']+)["\']/i', $atribut, $tipe)
                && ! str_contains(strtolower($tipe[1]), 'javascript')) {
                continue;
            }

            $js .= $isi . "\n;\n";
        }

        return $js;
    }

    public function test_skrip_inline_setiap_halaman_sah_secara_sintaksis(): void
    {
        $node = $this->cariNode();

        if ($node === null) {
            $this->markTestSkipped('node tidak tersedia di lingkungan ini.');
        }

        $rusak = [];
        $diperiksa = 0;

        foreach ($this->halamanTerbuka() as $penanda => $html) {
            $js = $this->skripInline($html);

            if (trim($js) === '') {
                continue;
            }

            $diperiksa++;
            $berkas = tempnam(sys_get_temp_dir(), 'cahayaos_') . '.js';
            file_put_contents($berkas, $js);

            $keluaran = [];
            $kode = 0;
            exec($node . ' --check ' . escapeshellarg($berkas) . ' 2>&1', $keluaran, $kode);

            @unlink($berkas);

            if ($kode !== 0) {
                $rusak[] = $penanda . ' => ' . implode(' | ', array_slice($keluaran, 0, 3));
            }
        }

        $this->assertGreaterThan(0, $diperiksa, 'Tidak ada skrip inline yang terperiksa.');

        $this->assertSame([], $rusak, "Skrip inline gagal diurai:\n" . implode("\n", $rusak));
    }

    /**
     * Setiap getElementById() harus menunjuk elemen yang benar-benar ada.
     *
     * =================================================================
     * ATURANNYA SENGAJA LONGGAR — DAN INI ALASANNYA
     * =================================================================
     *
     * Sebagian elemen memang dirender bersyarat. Form "catat-pendampingan",
     * misalnya, tidak ada di halaman peserta ketika yang membuka Pimpinan,
     * karena Pimpinan memang tidak mencatat pendampingan — dan skripnya
     * sudah menjaga dengan `if (form)`.
     *
     * Kalau id semacam itu ikut dianggap kesalahan, test ini akan berisik
     * sejak hari pertama, dan test yang berisik akan dimatikan orang.
     *
     * Karena itu sebuah id baru dianggap keliru kalau ia TIDAK PERNAH ADA
     * di satu pun render halaman tersebut oleh peran mana pun. Itu pola
     * salah ketik, bukan pola elemen bersyarat.
     */
    public function test_setiap_getelementbyid_menunjuk_elemen_yang_pernah_ada(): void
    {
        $terbuka = $this->halamanTerbuka();

        // Kumpulkan per URL, lepas dari perannya.
        $perUrl = [];

        foreach ($terbuka as $penanda => $html) {
            $url = trim(explode('[', $penanda)[0]);
            $perUrl[$url][] = $html;
        }

        $hilang = [];

        foreach ($perUrl as $url => $daftarHtml) {
            $dirujuk = [];

            foreach ($daftarHtml as $html) {
                preg_match_all('/getElementById\(\s*[\'"]([^\'"]+)[\'"]\s*\)/', $html, $cocok);
                $dirujuk = array_merge($dirujuk, $cocok[1]);
            }

            foreach (array_unique($dirujuk) as $id) {
                $pernahAda = false;

                foreach ($daftarHtml as $html) {
                    if (preg_match('/\bid\s*=\s*["\']' . preg_quote($id, '/') . '["\']/', $html)) {
                        $pernahAda = true;
                        break;
                    }
                }

                if (! $pernahAda) {
                    $hilang[] = "{$url} merujuk #{$id}, yang tidak ada di render mana pun";
                }
            }
        }

        $this->assertSame([], $hilang, implode("\n", $hilang));
    }

    /** Lokasi node, atau null kalau memang tidak ada. */
    private function cariNode(): ?string
    {
        $keluaran = [];
        $kode = 0;
        exec('node --version 2>&1', $keluaran, $kode);

        return $kode === 0 ? 'node' : null;
    }
}
