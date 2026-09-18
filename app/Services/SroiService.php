<?php

namespace App\Services;

use App\Models\ProgramInvestment;
use App\Models\ImpactOutcome;
use App\Models\ImpactProgram;
use App\Models\ParticipantMeasurement;
use App\Models\SroiCalculation;
use Illuminate\Support\Facades\Auth;

/**
 * ==========================================================================
 * MESIN SROI — Social Return on Investment
 * ==========================================================================
 *
 * ATURAN PERTAMA, DAN YANG PALING PENTING:
 *
 *   KELAS INI MENOLAK MENGELUARKAN ANGKA SROI KALAU DATANYA BELUM CUKUP.
 *
 * Data operasional CahayaOS berlimpah — 271 laporan omzet, ratusan catatan
 * pendampingan — dan dari situ sangat mudah menghasilkan sebuah rasio yang
 * terlihat meyakinkan. Justru karena mudah itulah aturan ini ada. Rasio SROI
 * dipakai melapor ke pemberi dana; angka yang tidak berdiri di atas investasi
 * tercatat, outcome terukur, dan proxy bersumber bukan sekadar tidak akurat,
 * ia menyesatkan orang yang memutuskan ke mana dana berikutnya pergi.
 *
 * Jadi periksaKesiapan() dijalankan lebih dulu, SELALU, dan hitung() menolak
 * berjalan kalau hasilnya belum siap. Yang ditampilkan bukan estimasi
 * melainkan daftar apa yang masih kurang.
 *
 * --------------------------------------------------------------------------
 * RUMUS — satu tempat, tidak disalin ke mana pun
 * --------------------------------------------------------------------------
 *
 *   nilai kotor     = jumlah perubahan  x  nilai proxy
 *   nilai bersih    = nilai kotor
 *                     x (1 - deadweight)
 *                     x attribution
 *                     x (1 - displacement)
 *
 *   Untuk manfaat >1 tahun, tahun ke-n dikalikan (1 - drop_off)^(n-1) lalu
 *   dijumlahkan. Tahun pertama TIDAK terkena drop-off — luruhnya manfaat baru
 *   mulai berlaku setelah tahun pertama berakhir.
 *
 *   nilai sosial bersih = total nilai dampak - total investasi
 *   rasio               = total nilai dampak / total investasi
 *
 * Rasio memakai TOTAL NILAI DAMPAK sebagai pembilang (bukan nilai sosial
 * bersih), sesuai praktik SROI yang lazim: hasilnya dibaca "Rp X nilai sosial
 * untuk setiap Rp 1 yang diinvestasikan". Rumusnya ditulis di sini dan
 * disertakan apa adanya di setiap hasil perhitungan, supaya pembaca laporan
 * bisa memeriksa ulang tanpa membuka kode.
 *
 * --------------------------------------------------------------------------
 * DARI MANA ANGKANYA
 * --------------------------------------------------------------------------
 *
 * Outcome 'otomatis' mengambil perubahannya dari PerbandinganService —
 * baseline vs endline peserta yang sudah dicatat pendamping saat bekerja
 * normal. Petugas TIDAK diminta menginput ulang apa pun. Outcome 'manual'
 * disediakan hanya untuk perubahan yang memang tidak terekam sistem (mis.
 * hasil survei kesejahteraan), dan angkanya wajib disertai bukti.
 */
class SroiService
{
    /** Faktor yang diubah pada skenario konservatif & optimistis. */
    private const GESERAN_SENSITIVITAS = 20.0;

    public function __construct(private PerbandinganService $perbandingan)
    {
    }

    // ======================================================================
    // A. KESIAPAN DATA — dijalankan sebelum apa pun dihitung
    // ======================================================================

    public const BELUM_ADA_DATA = 'Belum Ada Data';
    public const BELUM_LENGKAP = 'Data Belum Lengkap';
    public const SIAP = 'Siap Dihitung';

    /**
     * Apakah program ini boleh menghasilkan angka SROI?
     *
     * Mengembalikan status, daftar kekurangan dalam kalimat biasa, dan daftar
     * peringatan (hal yang tidak memblokir tapi wajib dilihat mata manusia
     * sebelum angkanya dipakai melapor).
     *
     * Perhatikan bedanya KEKURANGAN dan PERINGATAN. Kekurangan memblokir:
     * tanpa investasi tidak ada penyebut, tanpa proxy tidak ada nilai rupiah.
     * Peringatan tidak memblokir tapi menunda status ke "Perlu Review" —
     * mis. faktor penyesuai yang masih bawaan, atau outcome yang hanya
     * terukur pada segelintir peserta.
     */
    public function periksaKesiapan(ImpactProgram $program): array
    {
        $program->loadMissing(['investments', 'outcomes.proxy']);

        $kurang = [];
        $peringatan = [];

        // --- 1. Investasi: penyebut rasio. Tanpa ini tidak ada SROI. ---
        $totalInvestasi = (int) $program->investments->sum('nominal');

        if ($program->investments->isEmpty()) {
            $kurang[] = 'Investasi program belum dicatat. Tanpa nilai investasi, rasio SROI tidak punya penyebut.';
        } elseif ($totalInvestasi <= 0) {
            $kurang[] = 'Total investasi tercatat nol. Rasio tidak bisa dihitung dengan pembagi nol.';
        } elseif ($program->investments->where('jenis', ProgramInvestment::JENIS_IN_KIND)->isEmpty()) {
            $peringatan[] = 'Belum ada investasi in-kind yang dicatat. Kalau program memakai tenaga, tempat, '
                . 'atau barang sumbangan, mengabaikannya membuat rasio terlihat lebih baik daripada sebenarnya.';
        }

        // --- 2. Outcome: yang menghasilkan nilai dampak. ---
        if ($program->outcomes->isEmpty()) {
            $kurang[] = 'Belum ada outcome yang didefinisikan untuk program ini.';
        }

        // --- 3. Proxy finansial per outcome. ---
        $tanpaProxy = $program->outcomes->filter(fn (ImpactOutcome $o) => $o->proxy === null);

        if ($tanpaProxy->isNotEmpty()) {
            $kurang[] = 'Proxy finansial belum tersedia untuk outcome: '
                . $tanpaProxy->pluck('nama')->implode(', ') . '.';
        }

        // --- 4. Angka perubahan: ada isinya atau tidak. ---
        $rekap = $this->rekapPerubahan($program);

        foreach ($program->outcomes as $outcome) {
            $nilai = $this->nilaiPerubahan($outcome, $rekap);

            if ($nilai['jumlah'] === null) {
                $kurang[] = 'Outcome "' . $outcome->nama . '" belum punya angka perubahan — '
                    . ($outcome->otomatis()
                        ? 'belum ada peserta dengan baseline dan endline yang lengkap untuk indikator ini.'
                        : 'nilai perubahan manual belum diisi.');

                continue;
            }

            if ($outcome->penyesuaiMasihBawaan()) {
                $peringatan[] = 'Outcome "' . $outcome->nama . '" masih memakai faktor penyesuai bawaan '
                    . '(deadweight 0%, attribution 100%). Artinya seluruh perubahan diklaim milik program — '
                    . 'tinjau sebelum angkanya dipakai melapor.';
            }

            // Displacement yang belum ditentukan BUKAN displacement nol.
            // Selama belum diputuskan, angka 0 yang dipakai perhitungan
            // sebenarnya berarti "belum dipikirkan" — dan itu harus terbaca.
            if ($outcome->displacementBelumDitentukan()) {
                $peringatan[] = 'Outcome "' . $outcome->nama . '" belum menyatakan apakah displacement relevan. '
                    . 'Selama belum ditentukan, perhitungan memakai 0% — yang berarti tidak ada manfaat '
                    . 'yang sekadar berpindah dari pihak lain. Tandai relevan atau tidak relevan, '
                    . 'beserta alasannya.';
            }

            if ($outcome->attribution_persen < 100 && blank($outcome->attribution_catatan)) {
                $peringatan[] = 'Outcome "' . $outcome->nama . '" mengakui '
                    . rtrim(rtrim(number_format(100 - $outcome->attribution_persen, 2, ',', '.'), '0'), ',')
                    . '% perubahan milik pihak lain, tetapi belum menyebutkan pihak lain itu siapa. '
                    . 'Angka attribution yang tidak bisa ditelusuri tidak bisa diperiksa siapa pun.';
            }

            // ==========================================================
            // ATTRIBUTION YANG SANGAT RENDAH — kemungkinan terbalik.
            //
            // Kolom ini berarti "berapa persen perubahan yang MILIK program
            // ini" (bawaannya 100). Mudah sekali terbaca terbalik sebagai
            // "berapa persen milik pihak lain" — saya sendiri salah membacanya
            // saat menguji mesin ini, padahal sudah membaca kodenya.
            //
            // Kalau terbalik, nilai dampaknya meleset berkali lipat dan TIDAK
            // ADA tanda bahaya apa pun: angkanya tetap wajar, rasionya tetap
            // tercetak, laporannya tetap bisa ditandatangani.
            //
            // Ini PERINGATAN, bukan penolakan. Program yang memang hanya
            // menyumbang sebagian kecil perubahan itu sah — yang diminta cuma
            // memastikan arahnya memang disengaja. Ambang 50% dipilih sebagai
            // batas "program mengaku menyumbang kurang dari separuh", dan ia
            // TIDAK ikut mempengaruhi perhitungan sama sekali.
            if ($outcome->attribution_persen !== null && $outcome->attribution_persen <= 50) {
                $peringatan[] = 'Outcome "' . $outcome->nama . '" menyatakan hanya '
                    . rtrim(rtrim(number_format($outcome->attribution_persen, 2, ',', '.'), '0'), ',')
                    . '% perubahan yang milik program ini. Pastikan arahnya tidak terbalik — '
                    . 'kolom ini diisi porsi MILIK PROGRAM, bukan porsi milik pihak lain.';
            }

            // Proxy yang secara tegas menyebut indikator LAIN hampir pasti
            // salah pasang — dan salah pasang proxy menghasilkan nilai dampak
            // yang keliru tanpa satu pun tanda bahaya.
            if ($outcome->proxy && ! $outcome->proxy->cocokUntuk($outcome->indikator)) {
                $peringatan[] = 'Proxy "' . $outcome->proxy->nama . '" ditujukan untuk indikator '
                    . $outcome->proxy->indikator . ', sedangkan outcome "' . $outcome->nama
                    . '" memakai indikator ' . $outcome->indikator . '. Pastikan proxy-nya memang tepat.';
            }

            if ($outcome->otomatis() && $nilai['jumlah_peserta'] !== null && $nilai['jumlah_peserta'] < 5) {
                $peringatan[] = 'Outcome "' . $outcome->nama . '" hanya terukur pada '
                    . $nilai['jumlah_peserta'] . ' peserta. Angkanya sah, tapi belum mewakili program.';
            }
        }

        // --- 5. Cakupan baseline/endline program secara keseluruhan. ---
        if ($rekap['jumlah_peserta'] > 0 && $rekap['jumlah_siap_dibandingkan'] === 0) {
            $kurang[] = 'Belum ada satu pun peserta yang punya baseline sekaligus endline/monitoring. '
                . 'Tanpa itu, tidak ada perubahan yang bisa diukur.';
        } elseif ($rekap['jumlah_siap_dibandingkan'] > 0) {
            $cakupan = round($rekap['jumlah_siap_dibandingkan'] / max(1, $rekap['jumlah_peserta']) * 100);

            if ($cakupan < 50) {
                $peringatan[] = 'Baru ' . $cakupan . '% peserta program (' . $rekap['jumlah_siap_dibandingkan']
                    . ' dari ' . $rekap['jumlah_peserta'] . ') yang punya baseline & endline lengkap. '
                    . 'Hasilnya mewakili sebagian peserta saja.';
            }
        }

        $status = match (true) {
            $program->investments->isEmpty() && $program->outcomes->isEmpty() => self::BELUM_ADA_DATA,
            ! empty($kurang) => self::BELUM_LENGKAP,
            default => self::SIAP,
        };

        return [
            'status' => $status,
            'siap' => $status === self::SIAP,
            'kurang' => $kurang,
            'peringatan' => $peringatan,
            'total_investasi' => $totalInvestasi,
            'rekap_perubahan' => $rekap,
        ];
    }

    // ======================================================================
    // B. PERHITUNGAN
    // ======================================================================

    /**
     * Hitung SROI dan SIMPAN hasilnya sebagai baris baru.
     *
     * Melempar RuntimeException kalau datanya belum siap. Ini disengaja dan
     * bukan kekasaran API: satu-satunya cara sebuah angka SROI palsu bisa
     * lahir adalah lewat jalur yang "memaksa saja dulu", jadi jalur itu tidak
     * disediakan. Pemanggil yang tertib memanggil periksaKesiapan() lebih
     * dulu dan menampilkan daftar kekurangannya.
     */
    public function hitung(ImpactProgram $program): SroiCalculation
    {
        $kesiapan = $this->periksaKesiapan($program);

        if (! $kesiapan['siap']) {
            throw new \RuntimeException(
                'Data SROI belum lengkap: ' . implode(' ', $kesiapan['kurang'])
            );
        }

        $rekap = $kesiapan['rekap_perubahan'];
        $baris = [];
        $totalDampak = 0;

        foreach ($program->outcomes as $outcome) {
            $hasil = $this->hitungOutcome($outcome, $rekap);
            $baris[] = $hasil;
            $totalDampak += $hasil['nilai_bersih'];
        }

        $totalInvestasi = $kesiapan['total_investasi'];

        // Pembagi sudah dipastikan > 0 oleh periksaKesiapan(); pemeriksaan
        // ini tetap ada sebagai jaring terakhir, bukan sebagai duplikasi
        // aturan — pembagian dengan nol tidak boleh bisa terjadi lewat jalur
        // mana pun, termasuk yang belum terbayang hari ini.
        $rasio = $totalInvestasi > 0
            ? round($totalDampak / $totalInvestasi, 4)
            : null;

        return $program->calculations()->create([
            // Perhitungan BARU selalu lahir sebagai "Perlu Review" kalau ada
            // peringatan. Sistem tidak berwenang menyatakan sebuah angka
            // dampak layak dilaporkan — itu keputusan manusia, dan di sinilah
            // keputusan itu diminta secara eksplisit.
            'status' => empty($kesiapan['peringatan'])
                ? SroiCalculation::STATUS_TERHITUNG
                : SroiCalculation::STATUS_PERLU_REVIEW,
            'total_investasi' => $totalInvestasi,
            'total_nilai_dampak' => (int) round($totalDampak),
            'nilai_sosial_bersih' => (int) round($totalDampak - $totalInvestasi),
            'rasio' => $rasio,
            'rincian' => [
                'rumus' => 'nilai bersih = perubahan x proxy x (1 - deadweight) x attribution x (1 - displacement); '
                    . 'tahun ke-n dikalikan (1 - drop_off)^(n-1)',
                'outcomes' => $baris,
                'investasi' => $program->investments
                    ->map(fn ($i) => [
                        'kategori' => $i->kategori,
                        'uraian' => $i->uraian,
                        'jenis' => $i->jenis,
                        'nominal' => (int) $i->nominal,
                    ])->values()->all(),
                'peringatan' => $kesiapan['peringatan'],
                'cakupan_peserta' => [
                    'total' => $rekap['jumlah_peserta'],
                    'terukur' => $rekap['jumlah_siap_dibandingkan'],
                ],
            ],
            'sensitivitas' => $this->sensitivitas($program, $rekap, $totalInvestasi),
            'dihitung_oleh' => Auth::id(),
            'dihitung_at' => now(),
        ]);
    }

    /**
     * Nilai satu outcome, lengkap dengan setiap langkah antaranya.
     *
     * Setiap langkah ikut disimpan (kotor, setelah deadweight, setelah
     * attribution, ...) bukan untuk kerapian melainkan supaya siapa pun yang
     * membaca laporan bisa menunjuk tepat di langkah mana angkanya berubah
     * dan kenapa. Hasil akhir tanpa langkah antaranya tidak bisa diperiksa.
     */
    public function hitungOutcome(ImpactOutcome $outcome, array $rekap, array $ubahPenyesuai = []): array
    {
        $nilai = $this->nilaiPerubahan($outcome, $rekap);
        $proxy = $outcome->proxy;

        $deadweight = $ubahPenyesuai['deadweight'] ?? (float) $outcome->deadweight_persen;
        $attribution = $ubahPenyesuai['attribution'] ?? (float) $outcome->attribution_persen;

        // displacementBerlaku() — BUKAN kolom mentahnya. Outcome yang sudah
        // dinyatakan tidak relevan terhadap displacement memakai 0, dan itu
        // sah karena keputusannya tercatat beserta alasannya.
        $displacement = $ubahPenyesuai['displacement'] ?? $outcome->displacementBerlaku();

        // Drop-off hanya berlaku untuk manfaat lintas tahun. Untuk durasi satu
        // tahun ia memang tidak pernah terpakai (tahun pertama tidak pernah
        // diluruhkan), jadi dinolkan di sini agar yang tercatat di rincian
        // laporan juga mengatakan demikian — bukan angka yang terlihat
        // berpengaruh padahal tidak.
        $dropOff = $outcome->dropOffRelevan() ? (float) $outcome->drop_off_persen : 0.0;

        $jumlah = $nilai['jumlah'] ?? 0;
        $nilaiProxy = $proxy?->nilai ?? 0;

        $kotor = $jumlah * $nilaiProxy;

        $setelahDeadweight = $kotor * (1 - $deadweight / 100);
        $setelahAttribution = $setelahDeadweight * ($attribution / 100);
        $setelahDisplacement = $setelahAttribution * (1 - $displacement / 100);

        // Manfaat lintas tahun: tahun pertama utuh, tahun berikutnya luruh.
        $bersih = 0.0;
        $perTahun = [];

        for ($tahun = 1; $tahun <= max(1, $outcome->durasi_tahun); $tahun++) {
            $nilaiTahun = $setelahDisplacement * ((1 - $dropOff / 100) ** ($tahun - 1));
            $perTahun[] = ['tahun' => $tahun, 'nilai' => (int) round($nilaiTahun)];
            $bersih += $nilaiTahun;
        }

        return [
            'outcome_id' => $outcome->id,
            'nama' => $outcome->nama,
            'sumber_nilai' => $outcome->sumber_nilai,
            'indikator' => $outcome->indikator,
            'indikator_label' => $outcome->indikatorLabel(),
            'jumlah_perubahan' => $jumlah,
            'jumlah_peserta_terukur' => $nilai['jumlah_peserta'],
            'proxy' => $proxy ? [
                'id' => $proxy->id,
                'nama' => $proxy->nama,
                // Nilai proxy DIBEKUKAN di sini. Master boleh diperbarui
                // besok; laporan ini tetap bisa menjelaskan angkanya.
                'nilai' => (int) $proxy->nilai,
                'satuan' => $proxy->satuan,
                'sumber' => $proxy->sumber,
                'tahun' => (int) $proxy->tahun,
            ] : null,
            'penyesuai' => [
                'deadweight_persen' => $deadweight,
                'deadweight_catatan' => $outcome->deadweight_catatan,
                'attribution_persen' => $attribution,
                'attribution_catatan' => $outcome->attribution_catatan,
                'displacement_persen' => $displacement,
                // Dibekukan bersama hasilnya: pembaca laporan dua tahun lagi
                // harus bisa membedakan "diperiksa, hasilnya nol" dari
                // "dinyatakan tidak relevan" tanpa membuka sistem.
                'displacement_relevan' => $outcome->displacement_relevan,
                'displacement_catatan' => $outcome->displacement_catatan,
                'drop_off_persen' => $dropOff,
                'drop_off_relevan' => $outcome->dropOffRelevan(),
                'durasi_tahun' => (int) $outcome->durasi_tahun,
            ],
            'langkah' => [
                'kotor' => (int) round($kotor),
                'setelah_deadweight' => (int) round($setelahDeadweight),
                'setelah_attribution' => (int) round($setelahAttribution),
                'setelah_displacement' => (int) round($setelahDisplacement),
            ],
            'per_tahun' => $perTahun,
            'nilai_bersih' => (int) round($bersih),
            'bukti' => $outcome->bukti,
        ];
    }

    // ======================================================================
    // C. ANALISIS SENSITIVITAS
    // ======================================================================

    /**
     * Tiga skenario: konservatif, dasar, optimistis.
     *
     * Gunanya bukan memberi tiga angka untuk dipilih yang paling bagus,
     * melainkan menunjukkan SEBERAPA RAPUH rasionya terhadap asumsi yang
     * dipilih sendiri oleh penyusun laporan. Rasio yang berubah dari 1,2 ke
     * 4,8 hanya karena attribution digeser 20 poin adalah rasio yang tidak
     * boleh dilaporkan sebagai angka tunggal — dan itu terlihat justru dari
     * tabel ini.
     *
     * Yang digeser adalah deadweight, attribution, dan displacement, masing-
     * masing 20 poin persen ke arah yang lebih hati-hati / lebih longgar.
     * Proxy TIDAK ikut digeser: nilainya datang dari sumber luar, dan
     * menggesernya berarti mengarang sumber yang tidak ada.
     */
    public function sensitivitas(ImpactProgram $program, array $rekap, int $totalInvestasi): array
    {
        $skenario = [
            'konservatif' => ['arah' => +1, 'label' => 'Konservatif — asumsi lebih hati-hati'],
            'dasar' => ['arah' => 0, 'label' => 'Dasar — asumsi yang dicatat'],
            'optimistis' => ['arah' => -1, 'label' => 'Optimistis — asumsi lebih longgar'],
        ];

        $hasil = [];

        foreach ($skenario as $kunci => $meta) {
            $total = 0;

            foreach ($program->outcomes as $outcome) {
                $geser = $meta['arah'] * self::GESERAN_SENSITIVITAS;

                $total += $this->hitungOutcome($outcome, $rekap, [
                    // Deadweight & displacement NAIK pada skenario konservatif
                    // (lebih banyak perubahan dianggap bukan milik program),
                    // attribution TURUN. Ketiganya searah, jadi geserannya
                    // berlawanan tanda.
                    'deadweight' => $this->batasi((float) $outcome->deadweight_persen + $geser),
                    'attribution' => $this->batasi((float) $outcome->attribution_persen - $geser),
                    'displacement' => $this->batasi((float) $outcome->displacement_persen + $geser),
                ])['nilai_bersih'];
            }

            $hasil[$kunci] = [
                'label' => $meta['label'],
                'geseran_persen' => $meta['arah'] * self::GESERAN_SENSITIVITAS,
                'total_nilai_dampak' => (int) round($total),
                // Investasi ikut dibawa per skenario meski nilainya sama di
                // ketiganya. Itu justru pesannya: yang bergeser hanyalah
                // asumsi, bukan uang yang benar-benar dikeluarkan.
                'total_investasi' => $totalInvestasi,
                'nilai_sosial_bersih' => (int) round($total - $totalInvestasi),
                'rasio' => $totalInvestasi > 0 ? round($total / $totalInvestasi, 4) : null,
            ];
        }

        return $hasil;
    }

    /** Persen selalu berada di 0-100; geseran tidak boleh melewatinya. */
    private function batasi(float $persen): float
    {
        return max(0.0, min(100.0, $persen));
    }

    // ======================================================================
    // D. SUMBER ANGKA PERUBAHAN
    // ======================================================================

    /**
     * Rekap baseline vs endline SELURUH peserta program.
     *
     * Diambil dari PerbandinganService — bukan dihitung ulang di sini. Kalau
     * modul Impact punya salinan rumusnya sendiri, halaman peserta dan
     * laporan dampak akan menampilkan perubahan yang berbeda untuk usaha yang
     * sama, dan tidak ada cara memutuskan mana yang benar.
     */
    public function rekapPerubahan(ImpactProgram $program): array
    {
        $peserta = $program->peserta()->with('measurements')->get();

        return $this->perbandingan->rekap($peserta);
    }

    /**
     * Angka perubahan satu outcome.
     *
     * @return array{jumlah: int|null, jumlah_peserta: int|null}
     *
     * `jumlah` NULL berarti BELUM ADA ANGKA — bukan nol. Perbedaan itu yang
     * membuat periksaKesiapan() bisa memblokir perhitungan alih-alih diam-
     * diam menghasilkan nilai dampak Rp 0 yang terlihat seperti hasil hitung
     * sungguhan.
     */
    public function nilaiPerubahan(ImpactOutcome $outcome, array $rekap): array
    {
        if (! $outcome->otomatis()) {
            return [
                'jumlah' => $outcome->nilai_perubahan_manual,
                'jumlah_peserta' => $outcome->jumlah_penerima_manual,
            ];
        }

        $indikator = $rekap['indikator'][$outcome->indikator] ?? null;

        if ($indikator === null) {
            return ['jumlah' => null, 'jumlah_peserta' => null];
        }

        $jumlah = $indikator['total_selisih'];

        // Indikator keuangan dicatat PER BULAN (lihat ParticipantMeasurement).
        // Proxy finansial hampir selalu dinyatakan per tahun, jadi angkanya
        // disetahunkan lebih dulu. Tanpa langkah ini, nilai dampak jadi 12
        // kali lebih kecil daripada seharusnya — kesalahan yang tidak terlihat
        // karena hasilnya tetap berupa angka yang masuk akal.
        if ($this->indikatorBulanan($outcome->indikator)) {
            $jumlah *= 12;
        }

        return [
            'jumlah' => $jumlah,
            'jumlah_peserta' => $indikator['jumlah_peserta_terukur'],
        ];
    }

    /** Indikator yang nilainya dicatat per bulan dan perlu disetahunkan. */
    private function indikatorBulanan(?string $indikator): bool
    {
        if ($indikator === null) {
            return false;
        }

        if ($indikator === 'profit_bulanan') {
            return true;
        }

        return str_ends_with($indikator, '_bulanan')
            && isset(ParticipantMeasurement::INDIKATOR[$indikator]);
    }
}
