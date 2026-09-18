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
use Illuminate\Support\Facades\Route;

/**
 * DATA CONTOH UNTUK SELURUH MODUL + DAFTAR SEMUA ROUTE GET.
 *
 * ==========================================================================
 * KENAPA DIPISAH JADI TRAIT
 * ==========================================================================
 *
 * Isi berkas ini DIPINDAHKAN dari SmokeSeluruhHalamanTest — bukan disalin.
 * Alasannya: ada dua pertanyaan berbeda yang ingin dijawab dengan data dan
 * daftar halaman yang SAMA PERSIS.
 *
 *   1. Apakah setiap halaman bisa dibuka? (SmokeSeluruhHalamanTest)
 *   2. Apakah setiap halaman TETAP bisa dibuka setelah data masternya
 *      diarsipkan? (SmokeHalamanMasterTerarsipTest)
 *
 * Kalau keduanya punya salinan datanya sendiri, cepat atau lambat yang satu
 * ketinggalan modul baru yang sudah ditambahkan ke yang lain — dan yang
 * ketinggalan justru berhenti menguji tanpa pernah gagal.
 */
trait MenyiapkanDataSeluruhModul
{
    protected array $aktor = [];
    protected array $contoh = [];

    /**
     * Seluruh route GET yang bisa diuji, lengkap dengan parameternya.
     *
     * Route yang butuh berkas fisik (unduh bukti, unduh lampiran legalitas)
     * dan route yang mengunduh berkas biner besar SENGAJA dilewati — keduanya
     * sudah punya test sendiri yang menyiapkan berkasnya, dan di sini mereka
     * hanya akan menghasilkan 404 yang tidak menandakan apa pun.
     *
     * @return array<string, string>
     */
    protected function routeGet(): array
    {
        $dilewati = [
            'omzet.bukti',             // butuh berkas fisik — diuji di BuktiOmzetTest
            'legalitas.download',      // idem
            'pengeluaran.bukti',       // idem
            'koperasi.pengeluaran.bukti',
            'backup-database.download',
            'logout',
        ];

        $pengganti = [
            'participant' => $this->contoh['peserta']->id,
            'omzetReport' => $this->contoh['omzet']->id,
            'perkembangan' => $this->contoh['pengukuran']->id,
            'pendampinganRecord' => $this->contoh['pendampingan']->id,
            'trainingRecord' => $this->contoh['pelatihan']->id,
            'legalitasRecord' => $this->contoh['legalitas']->id,
            'hppCalculation' => $this->contoh['hpp']->id,
            'pendamping' => $this->contoh['pendamping']->id,
            'user' => $this->contoh['userLain']->id,
            'impact' => $this->contoh['program']->id,
            'proxy' => $this->contoh['proxy']->id,
            'outcome' => $this->contoh['outcome']->id,
            'investasi' => $this->contoh['investasi']->id,
            'anggota' => $this->contoh['anggota']->id,
            'simpanan' => $this->contoh['simpanan']->id,
            'unit' => $this->contoh['unit']->id,
            'produk' => $this->contoh['produk']->id,
            'pelanggan' => $this->contoh['pelanggan']->id,
            'transaksi' => $this->contoh['penjualan']->id ?? 1,
            'pengeluaran' => $this->contoh['pengeluaran']->id,
            'kategori' => $this->contoh['kategori']->id,
            'shu' => $this->contoh['shu']->id,
            'target' => 1,
            'modul' => 'laporan',
            'token' => $this->contoh['peserta']->report_token,
            'filename' => 'contoh.sql',
            'jenis' => 'masuk',
            'id' => 1,
            'perhitungan' => 1,
        ];

        $hasil = [];

        foreach (Route::getRoutes() as $route) {
            if (! in_array('GET', $route->methods(), true)) {
                continue;
            }

            $nama = $route->getName();

            if (! $nama || in_array($nama, $dilewati, true)) {
                continue;
            }

            $uri = $route->uri();
            $lengkap = true;

            foreach ($route->parameterNames() as $param) {
                if (! array_key_exists($param, $pengganti)) {
                    $lengkap = false;
                    break;
                }

                $uri = str_replace(['{' . $param . '}', '{' . $param . '?}'], (string) $pengganti[$param], $uri);
            }

            if ($lengkap) {
                $hasil[$nama] = '/' . ltrim($uri, '/');
            }
        }

        return $hasil;
    }

    /** Potongan pesan kesalahan supaya kegagalan bisa ditelusuri tanpa membuka log. */
    protected function ringkasKesalahan($respons): string
    {
        $isi = $respons->getContent();

        if (preg_match('/<title>(.*?)<\/title>/s', $isi, $m)) {
            return trim(strip_tags($m[1]));
        }

        return mb_substr(strip_tags($isi), 0, 200);
    }

    // ==================================================================
    // PENYIAPAN
    // ==================================================================

    protected function siapkanPengguna(): void
    {
        $profil = Pendamping::create([
            'nama' => 'Rosa Linda', 'nomor_hp' => '081200000001',
            'email' => 'rosa@uat.test', 'status' => 'Aktif',
        ]);

        $this->contoh['pendamping'] = $profil;

        // Dikunci dengan NILAI role-nya, bukan label tampilannya — data
        // provider mengirimkan nilai itulah yang dipakai mencari aktor.
        $this->aktor['admin'] = $this->buatUser('admin', 'admin@uat.test');
        $this->aktor['pimpinan'] = $this->buatUser('pimpinan', 'pimpinan@uat.test');
        $this->aktor['pendamping'] = $this->buatUser('pendamping', 'pendamping@uat.test', $profil->id);
        $this->aktor[User::ROLE_TIM_KOPERASI] = $this->buatUser(User::ROLE_TIM_KOPERASI, 'koperasi@uat.test');

        // Akun kedua untuk route Manajemen User — Admin tidak boleh memakai
        // dirinya sendiri sebagai sasaran (aturan "tidak menghapus akun sendiri").
        $this->contoh['userLain'] = $this->buatUser('pendamping', 'lain@uat.test');
    }

    protected function buatUser(string $role, string $email, ?int $pendampingId = null): User
    {
        return User::create([
            'name' => $email,
            'email' => $email,
            'password' => bcrypt('rahasia123'),
            'role' => $role,
            'pendamping_id' => $pendampingId,
            'is_active' => true,
        ]);
    }

    /**
     * Data contoh untuk SETIAP modul.
     *
     * Halaman detail yang datanya kosong sering lolos begitu saja — justru
     * baris pertama yang membongkar relasi yang belum di-load dan pemanggilan
     * method pada null. Jadi setiap modul diberi minimal satu baris nyata.
     */
    protected function siapkanDataContoh(): void
    {
        $peserta = Participant::create([
            'nama_peserta' => 'Bu Ani',
            'nomor_hp' => '081234567890',
            'nama_usaha' => 'Keripik Bawang',
            'kategori_usaha' => 'Kuliner',
            'tanggal_mulai_program' => now()->subYear()->toDateString(),
            'status' => 'Aktif',
            'pendamping_id' => $this->contoh['pendamping']->id,
        ])->fresh();

        $this->contoh['peserta'] = $peserta;

        $this->contoh['omzet'] = OmzetReport::create([
            'participant_id' => $peserta->id,
            'periode_bulan' => now()->month, 'periode_tahun' => now()->year,
            'omzet' => 5_000_000, 'hpp' => 3_000_000, 'biaya_operasional' => 500_000,
            'submitted_at' => now(),
        ]);

        $this->contoh['pengukuran'] = $peserta->measurements()->create([
            'jenis' => ParticipantMeasurement::BASELINE,
            'tanggal' => now()->subMonths(11)->toDateString(),
            'omzet_bulanan' => 3_000_000,
        ]);

        $peserta->measurements()->create([
            'jenis' => ParticipantMeasurement::ENDLINE,
            'tanggal' => now()->subDays(10)->toDateString(),
            'omzet_bulanan' => 5_000_000,
        ]);

        $this->contoh['pendampingan'] = PendampinganRecord::create([
            'participant_id' => $peserta->id,
            'pendamping_id' => $this->contoh['pendamping']->id,
            'tanggal' => now()->subMonth()->toDateString(),
            'jenis_kegiatan' => array_key_first(PendampinganRecord::JENIS_KEGIATAN),
            'materi' => 'Pembukuan sederhana',
        ]);

        $this->contoh['pelatihan'] = TrainingRecord::create([
            'participant_id' => $peserta->id,
            'nama_pelatihan' => 'Pelatihan Digital Marketing',
            'tanggal' => now()->subMonths(2)->toDateString(),
            'status' => 'Selesai',
        ]);

        $this->contoh['legalitas'] = LegalitasRecord::create([
            'participant_id' => $peserta->id,
            'jenis_dokumen' => 'NIB',
            'status' => 'Sudah Ada',
        ]);

        $this->contoh['hpp'] = HppCalculation::create([
            'participant_id' => $peserta->id,
            'nama_produk' => 'Keripik Bawang 250gr',
            'satuan_produksi' => 'bungkus',
            'jumlah_produksi' => 100,
            'total_biaya_produksi' => 800_000,
            'hpp_per_unit' => 8_000,
            'harga_jual' => 12_000,
            'laba_per_unit' => 4_000,
            'margin_persen' => 33.33,
            'estimasi_laba_batch' => 400_000,
        ]);

        // ---------- Koperasi ----------
        $this->contoh['anggota'] = KoperasiMember::create([
            'nama' => 'Bu Ani', 'nomor_hp' => '081234567890',
            'tanggal_bergabung' => now()->subMonths(6)->toDateString(),
            'status' => 'Aktif', 'participant_id' => $peserta->id,
        ]);

        $this->contoh['simpanan'] = MemberSaving::create([
            'koperasi_member_id' => $this->contoh['anggota']->id,
            'jenis_simpanan' => 'Simpanan Pokok',
            'tanggal' => now()->subMonths(6)->toDateString(),
            'nominal' => 100_000,
            'status' => MemberSaving::STATUS_TERCATAT,
        ]);

        $this->contoh['unit'] = BusinessUnit::create([
            'nama' => 'Cahaya Mart', 'kode' => 'CM', 'tipe' => 'Retail', 'status' => 'Aktif',
        ]);

        $this->contoh['produk'] = ProductService::create([
            'unit_id' => $this->contoh['unit']->id,
            'kode' => 'PRD-001', 'nama' => 'Beras 5kg', 'jenis' => 'Produk',
            'harga_modal' => 55_000, 'harga_jual' => 62_000,
            'status' => 'Aktif', 'stock_tracking' => false,
        ]);

        $this->contoh['pelanggan'] = Customer::create(['nama' => 'Warung Bu Sri', 'nomor_hp' => '081299999999']);

        $this->contoh['kategori'] = PengeluaranCategory::firstOrCreate(
            ['nama' => 'Operasional'],
            ['status' => 'Aktif']
        );

        $this->contoh['pengeluaran'] = Pengeluaran::create([
            'tanggal' => now()->subWeek()->toDateString(),
            'kategori' => 'Operasional',
            'pengeluaran_category_id' => $this->contoh['kategori']->id,
            'keterangan' => 'Listrik toko',
            'nominal' => 250_000,
            'status' => 'Aktif',
        ]);

        $this->contoh['shu'] = ShuPeriod::create([
            'tahun' => now()->year,
            'tanggal_penetapan' => now()->toDateString(),
            'sumber' => ShuPeriod::SUMBER_MANUAL,
            'total_shu' => 4_000_000,
            'komponen' => ShuPeriod::KOMPONEN_USULAN,
        ]);

        app(ShuService::class)->susunAlokasi($this->contoh['shu']);

        // ---------- Dampak & SROI ----------
        $this->contoh['program'] = ImpactProgram::create([
            'nama' => 'Pemberdayaan UMKM ' . now()->year,
            'periode_mulai' => now()->startOfYear()->toDateString(),
            'periode_selesai' => now()->endOfYear()->toDateString(),
            'status' => ImpactProgram::STATUS_DRAFT,
        ]);

        $this->contoh['proxy'] = FinancialProxy::create([
            'nama' => 'Nilai kenaikan pendapatan UMKM',
            'satuan' => 'per rupiah kenaikan omzet',
            'nilai' => 1,
            'sumber' => 'Dokumen UAT',
            'tahun' => now()->year,
        ]);

        $this->contoh['investasi'] = $this->contoh['program']->investments()->create([
            'kategori' => 'Pelatihan', 'uraian' => 'Pelatihan pembukuan',
            'tanggal' => now()->subMonths(6)->toDateString(),
            'nominal' => 10_000_000, 'jenis' => 'Tunai',
        ]);

        $this->contoh['outcome'] = $this->contoh['program']->outcomes()->create([
            'nama' => 'Pendapatan usaha meningkat',
            'sumber_nilai' => ImpactOutcome::SUMBER_OTOMATIS,
            'indikator' => 'omzet_bulanan',
            'financial_proxy_id' => $this->contoh['proxy']->id,
            'deadweight_persen' => 20, 'attribution_persen' => 75,
            'displacement_persen' => 5, 'drop_off_persen' => 10,
            'durasi_tahun' => 2,
        ]);
    }
}
