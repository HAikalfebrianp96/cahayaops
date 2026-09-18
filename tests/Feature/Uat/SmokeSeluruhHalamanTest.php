<?php

namespace Tests\Feature\Uat;

use App\Models\BusinessUnit;
use App\Models\Customer;
use App\Models\FinancialProxy;
use App\Models\HppCalculation;
use App\Models\ImpactOutcome;
use App\Models\ImpactProgram;
use App\Models\KoperasiMember;
use App\Models\LegalitasRecord;
use App\Models\MemberSaving;
use App\Models\OmzetReport;
use App\Models\Participant;
use App\Models\ParticipantMeasurement;
use App\Models\Pendamping;
use App\Models\PendampinganRecord;
use App\Models\Pengeluaran;
use App\Models\PengeluaranCategory;
use App\Models\ProductService;
use App\Models\ShuPeriod;
use App\Models\TrainingRecord;
use App\Models\User;
use App\Services\ShuService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * UAT — SETIAP HALAMAN DIBUKA SUNGGUHAN, SEBAGAI SETIAP ROLE.
 *
 * Test lain di CahayaOS menguji aturan: siapa boleh apa, angka mana benar.
 * Yang ini menguji hal yang berbeda dan tidak kalah penting: apakah
 * halamannya BISA DIBUKA sama sekali.
 *
 * ==========================================================================
 * KENAPA TEST SEPERTI INI ADA
 * ==========================================================================
 *
 * Variabel yang lupa dikirim ke view, relasi yang belum di-load, atau
 * pemanggilan method pada null TIDAK pernah muncul di test unit maupun di
 * pemeriksaan statis. Semuanya baru meledak saat halamannya benar-benar
 * dirender — artinya, saat pengguna membukanya. Satu halaman yang jarang
 * dikunjungi bisa rusak berbulan-bulan tanpa ada yang tahu.
 *
 * Karena itu setiap route GET dibuka sungguhan di sini, dengan data yang
 * benar-benar ada di database, sebagai setiap role yang berhak membukanya.
 * Yang dinyatakan lulus hanya dua kemungkinan: halamannya terbuka (2xx),
 * atau ditolak dengan sopan (403 / 404 / redirect). Yang TIDAK boleh terjadi
 * adalah 500 — dan itulah satu-satunya yang dicari test ini.
 */
class SmokeSeluruhHalamanTest extends TestCase
{
    use MenyiapkanDataSeluruhModul;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->siapkanPengguna();
        $this->siapkanDataContoh();
    }

    public static function daftarRole(): array
    {
        return [
            'Admin' => ['admin'],
            'Pimpinan' => ['pimpinan'],
            'Pendamping' => ['pendamping'],
            'Tim Koperasi' => [User::ROLE_TIM_KOPERASI],
        ];
    }

    /**
     * @dataProvider daftarRole
     */
    public function test_tidak_ada_halaman_yang_meledak_untuk_role(string $role): void
    {
        $gagal = [];

        foreach ($this->routeGet() as $nama => $url) {
            $respons = $this->actingAs($this->aktor[$role])->get($url);
            $status = $respons->getStatusCode();

            // 2xx = terbuka. 3xx = diarahkan (mis. ke login/halaman lain).
            // 403/404 = ditolak dengan sopan, dan itu memang jawaban yang
            // benar untuk role yang tidak berhak. 5xx = halamannya rusak.
            if ($status >= 500) {
                $gagal[] = $nama . ' (' . $url . ') -> ' . $status
                    . ' :: ' . $this->ringkasKesalahan($respons);
            }
        }

        $this->assertSame([], $gagal, "Halaman berikut gagal dirender untuk role {$role}:\n" . implode("\n", $gagal));
    }

    /**
     * Penjaga agar smoke test di atas tidak lulus secara hampa.
     *
     * routeGet() melewati route yang parameternya tidak dikenali. Kalau suatu
     * saat sebuah parameter baru tidak ditambahkan ke daftar pengganti,
     * route-route itu diam-diam berhenti diuji — dan smoke test tetap hijau
     * sambil memeriksa makin sedikit halaman. Ambang ini membuat penyusutan
     * itu terlihat.
     */
    public function test_smoke_benar_benar_menguji_mayoritas_halaman(): void
    {
        $diuji = count($this->routeGet());

        $totalGet = collect(Route::getRoutes())
            ->filter(fn ($r) => in_array('GET', $r->methods(), true) && $r->getName())
            ->count();

        $this->assertGreaterThanOrEqual(
            80,
            $diuji,
            'Smoke test hanya menjangkau ' . $diuji . ' halaman — terlalu sedikit untuk disebut menyeluruh.'
        );

        $this->assertGreaterThanOrEqual(
            0.85,
            $diuji / $totalGet,
            'Smoke test hanya menjangkau ' . $diuji . ' dari ' . $totalGet
                . ' route GET. Tambahkan parameter yang belum dikenali ke daftar pengganti.'
        );
    }

    public function test_halaman_publik_terbuka_tanpa_login(): void
    {
        // Tiga pintu yang memang dirancang tanpa login. Kalau salah satunya
        // rusak, peserta dan anggota di lapangan kehilangan satu-satunya cara
        // mereka berinteraksi dengan sistem.
        $this->get(route('login'))->assertOk();
        $this->get(route('anggota-akses.login'))->assertOk();
        $this->get(route('omzet.public.show', $this->contoh['peserta']->report_token))->assertOk();
    }

    public function test_token_omzet_karangan_menghasilkan_404_bukan_error(): void
    {
        $this->get(route('omzet.public.show', 'token-yang-tidak-pernah-ada'))->assertNotFound();
    }
}
