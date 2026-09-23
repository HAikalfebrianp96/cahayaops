<?php

namespace App\Http\Controllers;

use App\Models\BusinessUnit;
use App\Models\DanaHibahKeluar;
use App\Models\DanaHibahMasuk;
use App\Models\DanaSukarelaKeluar;
use App\Models\DanaSukarelaMasuk;
use App\Models\KoperasiMember;
use App\Models\LegalitasRecord;
use App\Models\MemberSaving;
use App\Models\OmzetReport;
use App\Models\Participant;
use App\Models\PendampinganRecord;
use App\Models\Pendamping;
use App\Models\Pengeluaran;
use App\Models\SalesTransaction;
use App\Models\TrainingRecord;
use App\Services\FollowUpService;
use App\Services\KpiService;
use App\Services\LabaRugiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function __construct(
        private LabaRugiService $labaRugiService,
        private FollowUpService $followUp,
        private KpiService $kpi,
    ) {
    }

    public function index(Request $request)
    {
        // Pimpinan dapat Dashboard yang SEPENUHNYA berbeda (read-only).
        // Dashboard Admin & Pendamping di bawah ini TIDAK diubah sama sekali.
        if (Auth::user()->isPimpinan()) {
            return $this->pimpinanDashboard($request);
        }

        // Pendamping yang akunnya tertaut data Pendamping mendapat Dashboard
        // SENDIRI yang seluruh angkanya ter-scope ke binaannya.
        //
        // Sebelum ini mereka memakai panel Cahaya Preneur milik Admin, yang
        // menghitung Participant::count(), Pendamping::count(), dan seluruh
        // omzet SEMUA peserta — artinya setiap Pendamping melihat rekap
        // binaan Pendamping lain. Panel Admin itu TIDAK diubah sama sekali;
        // yang berubah hanya siapa yang diarahkan ke sana.
        //
        // Pendamping TANPA tautan sengaja tetap jatuh ke alur lama: ia tidak
        // punya binaan untuk ditampilkan, dan halaman lamanya sudah menangani
        // keadaan itu dengan baik.
        // Diperiksa lewat RELASINYA, bukan kolom pendamping_id-nya.
        //
        // Kolom itu bisa tetap terisi sementara profil Pendamping-nya sudah
        // diarsipkan — dan panel di bawah membutuhkan profilnya, bukan
        // sekadar angka id. Alur Arsipkan Pendamping memang melepas tautan
        // akun lebih dulu, tapi pengaman itu ada di controller LAIN; halaman
        // pertama yang dilihat pengguna tidak boleh bergantung padanya.
        //
        if (Auth::user()->isPendamping() && Auth::user()->pendamping) {
            return $this->pendampingDashboard();
        }

        // Pendamping TANPA tautan dulu jatuh ke alur umum di bawah — yang
        // ternyata panel Cahaya Preneur milik Admin: rekap seluruh peserta,
        // pendampingan semua pendamping, dan ringkasan SROI. Akun seperti itu
        // tidak punya binaan, jadi yang ditampilkan cukup langkah berikutnya.
        if (Auth::user()->isPendamping()) {
            return view('dashboard-pendamping-belum-tertaut', ['modul' => 'preneur']);
        }

        $isAdmin = Auth::user()->isAdmin();

        // ==========================================================
        // SWITCHER MODUL — satu halaman Dashboard, tiga tampilan:
        //   'semua'    -> ringkasan gabungan (DEFAULT, netral)
        //   'preneur'  -> panel Cahaya Preneur   (?modul=preneur)
        //   'koperasi' -> panel Koperasi Cahaya  (?modul=koperasi)
        //
        // Tanpa query param, Admin mendarat di 'semua' — tidak ada modul yang
        // otomatis disorot, jadi Dashboard terasa netral.
        //
        // PENDAMPING SENGAJA DIKECUALIKAN: mereka tidak punya switcher sama
        // sekali dan memang cuma memegang satu modul, jadi defaultnya tetap
        // 'preneur' seperti sebelumnya. Kalau ikut diarahkan ke 'semua',
        // Dashboard mereka justru jadi kosong tanpa cara mengisinya kembali.
        // Nilai ?modul apa pun dari Pendamping juga diabaikan diam-diam
        // (bukan 403) — sama seperti perilaku sebelumnya.
        // ==========================================================
        // TIM KOPERASI mendapat Dashboard SENDIRI yang fokus keanggotaan &
        // integrasi dengan Cahaya Preneur. Sebelumnya mereka diarahkan ke
        // panel Koperasi milik Admin, yang memuat laba rugi dan arus dana —
        // angka di luar kewenangannya. Tidak ada switcher untuk mereka:
        // nilai ?modul apa pun diabaikan, sama seperti Pendamping.
        if (Auth::user()->isTimKoperasi()) {
            return $this->timKoperasiDashboard();
        }

        $diminta = $request->query('modul');
        $modul = $isAdmin
            ? (in_array($diminta, ['preneur', 'koperasi'], true) ? $diminta : 'semua')
            : 'preneur';

        if ($modul === 'semua') {
            return view('dashboard', [
                'modul' => $modul,
                'isAdmin' => $isAdmin,
                'ringkasanSemua' => $this->ringkasanSemuaKegiatan(),
                // Panel "semua" adalah tempat Admin MENDARAT tanpa memilih
                // apa pun. Kalau ringkasan dampak hanya ada di panel Cahaya
                // Preneur, Admin harus tahu lebih dulu bahwa ia harus pindah
                // tab untuk melihatnya — dan angka yang harus dicari dulu
                // adalah angka yang tidak pernah dilihat.
                'ringkasanDampak' => $this->ringkasanDampak(),
            ]);
        }

        if ($modul === 'koperasi') {
            return view('dashboard', array_merge(
                ['modul' => $modul, 'isAdmin' => $isAdmin],
                $this->koperasiData($request)
            ));
        }

        $totalPeserta = Participant::count();

        $stats = [
            'total_peserta' => $totalPeserta,
            'total_pendamping' => Pendamping::count(),
            'peserta_aktif' => Participant::where('status', 'Aktif')->count(),
            'omzet_bulan_ini' => (int) OmzetReport::where('periode_bulan', now()->month)
                ->where('periode_tahun', now()->year)
                ->sum('omzet'),
            // Data yang sudah ada di sistem (dipakai jg di halaman Monitoring
            // Pendampingan), cuma belum pernah ditampilkan di Dashboard Admin.
            'total_pendampingan' => PendampinganRecord::count(),
        ] + $this->ringkasanLabaDanPengukuran();

        // Ringkasan dampak dikirim bersama statistik lain, bukan lewat query
        // terpisah di Blade — supaya halaman tetap punya satu titik di mana
        // seluruh angkanya bisa ditelusuri.
        $ringkasanDampak = $this->ringkasanDampak();

        // Grafik omzet SUNGGUHAN per bulan (tahun berjalan) — SEBELUMNYA
        // di sini pakai angka dummy hardcoded, sudah diperbaiki.
        $omzetPerBulanRaw = OmzetReport::where('periode_tahun', now()->year)
            ->selectRaw('periode_bulan, SUM(omzet) as total')
            ->groupBy('periode_bulan')
            ->pluck('total', 'periode_bulan');

        $namaBulanChart = OmzetReport::BULAN;
        $omzetChart = [
            'labels' => array_values($namaBulanChart),
            'data' => collect(range(1, 12))->map(fn ($b) => (int) ($omzetPerBulanRaw[$b] ?? 0))->all(),
        ];

        $pesertaTerbaru = Participant::latest()
            ->take(5)
            ->get()
            ->map(fn (Participant $p) => [
                'nama' => $p->nama_peserta,
                'usaha' => $p->nama_usaha,
                'status' => $p->status,
            ])
            ->toArray();

        $sudahLaporIds = OmzetReport::where('periode_bulan', now()->month)
            ->where('periode_tahun', now()->year)
            ->distinct()
            ->pluck('participant_id');

        $jumlahSudahLapor = $sudahLaporIds->count();

        $programBulanIni = [
            'total_peserta' => $totalPeserta,
            'sudah_lapor' => $jumlahSudahLapor,
            'belum_lapor' => max($totalPeserta - $jumlahSudahLapor, 0),
            'total_omzet' => $stats['omzet_bulan_ini'],
            'rata_rata_omzet' => $jumlahSudahLapor > 0
                ? intdiv($stats['omzet_bulan_ini'], $jumlahSudahLapor)
                : 0,
        ];

        $sudahDidampingiIds = PendampinganRecord::distinct()->pluck('participant_id');
        $jumlahSudahDidampingi = $sudahDidampingiIds->count();

        $monitoringPerkembangan = [
            'peserta_nib' => LegalitasRecord::where('jenis_dokumen', 'NIB')->where('status', LegalitasRecord::STATUS_SUDAH_ADA)->count(),
            'peserta_pirt' => LegalitasRecord::where('jenis_dokumen', 'PIRT')->where('status', LegalitasRecord::STATUS_SUDAH_ADA)->count(),
            'peserta_halal' => LegalitasRecord::where('jenis_dokumen', 'Sertifikat Halal')->where('status', LegalitasRecord::STATUS_SUDAH_ADA)->count(),
            'total_kegiatan_pendampingan' => PendampinganRecord::count(),
            'sudah_didampingi' => $jumlahSudahDidampingi,
            'belum_didampingi' => max($totalPeserta - $jumlahSudahDidampingi, 0),
        ];

        // HPP Summary — data yang sudah ada (dari modul HPP Cahaya Preneur),
        // TIDAK ada perhitungan/rumus baru dibuat di sini.
        $hppSummary = [
            'total_perhitungan' => \App\Models\HppCalculation::count(),
            'terbaru' => \App\Models\HppCalculation::with('participant')->latest()->first(),
        ];

        $pendampinganTerbaru = PendampinganRecord::with(['participant', 'pendamping'])
            ->latest('tanggal')
            ->take(5)
            ->get();

        return view('dashboard', array_merge(
            ['modul' => $modul, 'isAdmin' => $isAdmin],
            compact(
                'stats',
                'omzetChart',
                'pesertaTerbaru',
                'programBulanIni',
                'monitoringPerkembangan',
                'hppSummary',
                'pendampinganTerbaru',
                // Ringkasan Dampak & SROI untuk panel Admin. Pendamping dan
                // Tim Koperasi TIDAK pernah sampai ke cabang ini, jadi mereka
                // tidak menerima variabelnya sama sekali — sejalan dengan
                // ImpactPolicy yang menutup modul ini untuk keduanya.
                'ringkasanDampak'
            ),
            [
                'alert' => $this->programAlert(),
                'kinerjaPendamping' => $this->ringkasanKpi(),
                'monitoringPendamping' => $this->monitoringPendamping(),
                'omzetPerbandingan' => $this->omzetPerbandingan(),
                'aktivitasTerbaru' => $this->aktivitasTerbaru(),
            ]
        ));
    }

    /**
     * PROGRAM ALERT — "Perlu Perhatian".
     *
     * Setiap alert bukan sekadar angka: ia membawa TAUTAN ke daftar data yang
     * dimaksud, memakai filter yang memang sudah ada di halaman tujuan. Alert
     * yang tidak bisa diklik hanya memberi tahu ada masalah tanpa jalan
     * mengerjakannya.
     *
     * Seluruhnya dihitung dari relasi yang ada — tidak ada kolom penanda baru.
     */
    /**
     * PROFIT BULAN INI + KELENGKAPAN PENGUKURAN.
     *
     * Dua angka yang sengaja dipasangkan dengan PEMBAGINYA, bukan berdiri
     * sendiri.
     *
     * Profit hanya dijumlahkan dari laporan yang HPP-nya benar-benar
     * dilaporkan. Menjumlahkan semuanya sambil menganggap HPP kosong = 0 akan
     * melahirkan "laba" sebesar omzet penuh untuk ratusan laporan lama —
     * angka yang salah, terlihat wajar, dan tampil tepat di layar pimpinan.
     * Karena itu jumlah laporan yang ikut terhitung ikut dikembalikan dan
     * ditampilkan bersamanya.
     *
     * Hal yang sama untuk pengukuran: "12 peserta terukur" baru berarti
     * sesuatu kalau terlihat itu dari berapa peserta.
     */
    private function ringkasanLabaDanPengukuran(): array
    {
        $bulanIni = OmzetReport::where('periode_bulan', now()->month)
            ->where('periode_tahun', now()->year)
            ->get(['omzet', 'hpp', 'biaya_operasional']);

        $berlaba = $bulanIni->filter(fn (OmzetReport $r) => $r->punyaDataLaba());

        // Peserta yang punya baseline, dan yang sudah punya pembanding —
        // dihitung dengan dua query agregat, bukan per peserta.
        $punyaBaseline = \App\Models\ParticipantMeasurement::where('jenis', \App\Models\ParticipantMeasurement::BASELINE)
            ->distinct()->count('participant_id');

        $punyaPembanding = \App\Models\ParticipantMeasurement::whereIn('jenis', [
                \App\Models\ParticipantMeasurement::MONITORING,
                \App\Models\ParticipantMeasurement::ENDLINE,
            ])
            ->whereIn('participant_id', \App\Models\ParticipantMeasurement::where('jenis', \App\Models\ParticipantMeasurement::BASELINE)->select('participant_id'))
            ->distinct()->count('participant_id');

        return [
            'laporan_bulan_ini' => $bulanIni->count(),
            'laporan_berlaba_bulan_ini' => $berlaba->count(),
            'hpp_bulan_ini' => (int) $berlaba->sum('hpp'),
            'profit_bulan_ini' => (int) $berlaba->sum(fn (OmzetReport $r) => $r->profitBersih()),
            'peserta_punya_baseline' => $punyaBaseline,
            'peserta_terukur' => $punyaPembanding,
        ];
    }

    /**
     * RINGKASAN DAMPAK & SROI untuk Dashboard.
     *
     * Dipakai Admin dan Pimpinan — dua peran yang memang berhak membacanya
     * (lihat ImpactPolicy::viewAny). Pendamping dan Tim Koperasi tidak
     * pernah sampai ke sini karena tidak pernah memanggil method ini.
     *
     * ==================================================================
     * YANG SENGAJA TIDAK DIKEMBALIKAN: ANGKA NOL
     * ==================================================================
     *
     * Program yang belum bisa dihitung TIDAK menghasilkan rasio 0. Kunci
     * 'terakhir' bernilai null, dan tampilan memakai itu untuk mencetak
     * "Belum Cukup Data". Nol berarti "sudah dihitung, hasilnya nol" —
     * pernyataan yang sangat berbeda, dan jauh lebih buruk kalau salah.
     */
    private function ringkasanDampak(): array
    {
        $program = \App\Models\ImpactProgram::query();

        // Hasil perhitungan TERAKHIR yang pernah dibuat, lintas program.
        // Statusnya ikut dibawa supaya rasionya tidak pernah tampil telanjang.
        $terakhir = \App\Models\SroiCalculation::with('program')
            ->orderByDesc('dihitung_at')
            ->first();

        $punyaBaseline = \App\Models\ParticipantMeasurement::where('jenis', \App\Models\ParticipantMeasurement::BASELINE)
            ->select('participant_id');

        return [
            'jumlah_program' => (clone $program)->count(),
            'jumlah_final' => (clone $program)->where('status', \App\Models\ImpactProgram::STATUS_FINAL)->count(),
            'total_investasi' => (int) \App\Models\ProgramInvestment::sum('nominal'),
            'jumlah_outcome' => \App\Models\ImpactOutcome::count(),
            // Proxy AKTIF saja — yang nonaktif tidak bisa dipakai menyusun
            // outcome, jadi menghitungnya akan melebih-lebihkan kesiapan.
            'jumlah_proxy' => \App\Models\FinancialProxy::aktif()->count(),
            'peserta_terukur' => \App\Models\ParticipantMeasurement::whereIn('jenis', [
                    \App\Models\ParticipantMeasurement::MONITORING,
                    \App\Models\ParticipantMeasurement::ENDLINE,
                ])
                ->whereIn('participant_id', $punyaBaseline)
                ->distinct()->count('participant_id'),
            'terakhir' => $terakhir,
        ];
    }

    private function programAlert(): array
    {
        $ambang = $this->followUp->ambang();
        $batasMerah = now()->subDays($ambang['merah'])->toDateString();

        // Peserta aktif yang belum melaporkan omzet bulan berjalan.
        $sudahLapor = OmzetReport::where('periode_bulan', now()->month)
            ->where('periode_tahun', now()->year)
            ->pluck('participant_id');

        $belumLaporOmzet = Participant::whereIn('status', Participant::STATUS_BERJALAN)
            ->whereNotIn('id', $sudahLapor)
            ->count();

        // Melewati ambang merah ATAU belum pernah didampingi sama sekali.
        $lamaTakDidampingi = Participant::whereIn('status', Participant::STATUS_BERJALAN)
            ->where(function ($q) use ($batasMerah) {
                $q->whereDoesntHave('pendampinganRecords')
                    ->orWhereDoesntHave('pendampinganRecords', fn ($r) => $r->whereDate('tanggal', '>=', $batasMerah));
            })
            ->count();

        // Data usaha belum cukup untuk kerja lapangan.
        $dataBelumLengkap = Participant::whereIn('status', Participant::STATUS_BERJALAN)
            ->where(function ($q) {
                $q->whereNull('alamat')->orWhere('alamat', '')
                    ->orWhereNull('nomor_hp')->orWhere('nomor_hp', '')
                    ->orWhereNull('nama_usaha')->orWhere('nama_usaha', '');
            })
            ->count();

        $tanpaPendamping = Participant::whereIn('status', Participant::STATUS_BERJALAN)
            ->whereNull('pendamping_id')
            ->count();

        $legalitasBelum = Participant::whereIn('status', Participant::STATUS_BERJALAN)
            ->whereHas('legalitasRecords', fn ($q) => $q->where('status', '!=', LegalitasRecord::STATUS_SUDAH_ADA))
            ->count();

        return array_values(array_filter([
            $belumLaporOmzet > 0 ? [
                'jumlah' => $belumLaporOmzet,
                'teks' => 'peserta belum memperbarui omzet bulan ini',
                'tautan' => route('omzet.index'),
                'warna' => 'text-status-warning',
            ] : null,
            $lamaTakDidampingi > 0 ? [
                'jumlah' => $lamaTakDidampingi,
                'teks' => 'peserta tanpa pendampingan lebih dari ' . $ambang['merah'] . ' hari',
                'tautan' => route('follow-up.index'),
                'warna' => 'text-status-danger',
            ] : null,
            $tanpaPendamping > 0 ? [
                'jumlah' => $tanpaPendamping,
                'teks' => 'peserta belum memiliki pendamping',
                'tautan' => route('participants.index', ['status' => 'Aktif']),
                'warna' => 'text-status-danger',
            ] : null,
            $dataBelumLengkap > 0 ? [
                'jumlah' => $dataBelumLengkap,
                'teks' => 'peserta datanya belum lengkap (kontak/alamat/usaha)',
                'tautan' => route('participants.index'),
                'warna' => 'text-status-warning',
            ] : null,
            $legalitasBelum > 0 ? [
                'jumlah' => $legalitasBelum,
                'teks' => 'peserta punya dokumen legalitas yang belum selesai',
                'tautan' => route('laporan.legalitas'),
                'warna' => 'text-status-info',
            ] : null,
        ]));
    }

    /**
     * RINGKASAN KPI PENDAMPING untuk Dashboard Admin.
     *
     * Memakai [KpiService] yang sama dengan halaman KPI — jadi angka di
     * Dashboard dan di halaman KPI TIDAK MUNGKIN berbeda. Kalau dihitung
     * ulang di sini dengan rumus sendiri, cepat atau lambat keduanya akan
     * bergeser dan tidak ada yang tahu mana yang benar.
     */
    private function ringkasanKpi(): array
    {
        $pendampings = Pendamping::orderBy('nama')->get();

        $rapor = $this->kpi->untukBanyak(
            $pendampings,
            now()->startOfMonth(),
            now()->endOfMonth(),
        );

        return $this->kpi->ringkasan($rapor);
    }

    /**
     * MONITORING PENDAMPING — beban kerja & keaktifan.
     *
     * Dihitung sekali dengan withCount, bukan satu query per pendamping.
     */
    private function monitoringPendamping(): array
    {
        $awalBulan = now()->startOfMonth()->toDateString();

        $pendampings = Pendamping::withCount([
            'participants',
            'participants as binaan_aktif_count' => fn ($q) => $q->whereIn('status', Participant::STATUS_BERJALAN),
        ])->orderBy('nama')->get();

        $aktivitasBulanIni = PendampinganRecord::whereDate('tanggal', '>=', $awalBulan)
            ->selectRaw('pendamping_id, COUNT(*) as jumlah')
            ->groupBy('pendamping_id')
            ->pluck('jumlah', 'pendamping_id');

        $baris = $pendampings->map(fn ($p) => [
            'pendamping' => $p,
            'binaan' => $p->participants_count,
            'binaan_aktif' => $p->binaan_aktif_count,
            'aktivitas' => (int) ($aktivitasBulanIni[$p->id] ?? 0),
        ])->sortByDesc('aktivitas')->values();

        return [
            'baris' => $baris,
            'paling_aktif' => $baris->firstWhere('aktivitas', '>', 0),
            'tanpa_aktivitas' => $baris->where('aktivitas', 0)->values(),
        ];
    }

    /**
     * Perbandingan omzet bulan ini vs bulan lalu + peserta yang naik/turun.
     *
     * Membandingkan PERIODE LAPORAN, bukan tanggal input — peserta melaporkan
     * omzet bulan lalu di bulan ini, dan memakai created_at akan memasukkannya
     * ke periode yang salah.
     */
    private function omzetPerbandingan(): array
    {
        $lalu = now()->subMonth();

        $ambil = fn (int $bulan, int $tahun) => OmzetReport::where('periode_bulan', $bulan)
            ->where('periode_tahun', $tahun)
            ->with('participant')
            ->get();

        $bulanIni = $ambil(now()->month, now()->year);
        $bulanLalu = $ambil($lalu->month, $lalu->year);

        $totalIni = (int) $bulanIni->sum('omzet');
        $totalLalu = (int) $bulanLalu->sum('omzet');

        $pertumbuhan = $totalLalu > 0
            ? round(($totalIni - $totalLalu) / $totalLalu * 100, 1)
            : null;

        // Perubahan per peserta — hanya yang punya DUA laporan berurutan,
        // supaya tidak ada yang terlihat "turun 100%" cuma karena melewatkan
        // satu bulan.
        $lookupLalu = $bulanLalu->keyBy('participant_id');

        $perubahan = $bulanIni
            ->filter(fn ($r) => $lookupLalu->has($r->participant_id)
                && (float) $lookupLalu[$r->participant_id]->omzet > 0)
            ->map(function ($r) use ($lookupLalu) {
                $sebelum = (float) $lookupLalu[$r->participant_id]->omzet;

                return [
                    'peserta' => $r->participant,
                    'sekarang' => (int) $r->omzet,
                    'sebelum' => (int) $sebelum,
                    'persen' => round(((float) $r->omzet - $sebelum) / $sebelum * 100, 1),
                ];
            })
            ->filter(fn ($b) => $b['peserta'] !== null)
            ->sortByDesc('persen')
            ->values();

        return [
            'total_bulan_ini' => $totalIni,
            'total_bulan_lalu' => $totalLalu,
            'pertumbuhan_persen' => $pertumbuhan,
            'naik' => $perubahan->where('persen', '>', 0)->take(5)->values(),
            'turun' => $perubahan->where('persen', '<', 0)->sortBy('persen')->take(5)->values(),
        ];
    }

    /**
     * ACTIVITY TERBARU — dari Activity Log yang SUDAH ADA.
     *
     * Disaring ke aksi yang bermakna bagi program saja; log keamanan (login,
     * ganti password) punya halamannya sendiri di Monitoring & Keamanan dan
     * tidak perlu memenuhi Dashboard.
     */
    private function aktivitasTerbaru()
    {
        return \App\Models\ActivityLog::with('user')
            ->whereIn('model_type', [
                'Participant', 'PendampinganRecord', 'TrainingRecord',
                'OmzetReport', 'KoperasiMember', 'Pendamping',
            ])
            ->latest()
            ->limit(10)
            ->get();
    }

    /**
     * DASHBOARD TIM KOPERASI — fokus keanggotaan & integrasi.
     *
     * SENGAJA TIDAK memuat laba rugi, arus dana sosial/hibah, maupun
     * ringkasan Cahaya Preneur: ketiganya di luar kewenangan Tim Koperasi.
     * Sebelumnya mereka memakai panel Koperasi milik Admin yang memuat
     * semua itu.
     *
     * Panel "Calon Anggota" adalah wujud integrasi yang diminta: peserta
     * Cahaya Preneur yang BELUM menjadi anggota ditampilkan sebagai calon,
     * lengkap dengan tombol mendaftarkan — tanpa satu pun data identitas
     * yang perlu diketik ulang. Keanggotaan tetap KEPUTUSAN Tim Koperasi,
     * bukan otomatis.
     */
    private function timKoperasiDashboard()
    {
        $anggota = KoperasiMember::with('participant')->get();

        $stats = [
            'total_anggota' => $anggota->count(),
            'anggota_aktif' => $anggota->where('status', 'Aktif')->count(),
            'anggota_baru' => $anggota
                ->filter(fn ($a) => $a->tanggal_bergabung && $a->tanggal_bergabung->isSameMonth(now()))
                ->count(),
            'dari_preneur' => $anggota->whereNotNull('participant_id')->count(),
            'total_unit' => BusinessUnit::count(),
            // berlaku(): entri simpanan yang dibatalkan tetap tersimpan
            // sebagai jejak koreksi, tapi tidak ikut menambah saldo.
            'total_simpanan' => (float) MemberSaving::berlaku()->sum('nominal'),

            // Bagian dari total di atas yang pemiliknya sudah tidak lagi
            // menjadi anggota. TIDAK dikurangi — menonaktifkan anggota tidak
            // mengembalikan uangnya — hanya diterangkan, supaya "Total Anggota"
            // dan "Total Simpanan" di kartu yang sama tidak saling
            // bertentangan tanpa penjelasan.
            'simpanan_anggota_tidak_aktif' => (float) MemberSaving::berlaku()
                ->milikAnggotaTidakAktif()
                ->sum('nominal'),
        ];

        // koperasiMemberTermasukArsip, bukan relasi biasa.
        //
        // Panel ini merekam SETORAN — baris riwayat, bukan daftar anggota
        // aktif. Nama penyetornya bagian dari fakta itu.
        //
        // Keadaan nyata di koperasi ini: keempat entri simpanannya milik
        // anggota yang statusnya tidak lagi aktif, jadi panelnya menampilkan empat
        // baris uang tanpa satu pun nama pemilik. Sejak halaman Arsip
        // dilepas, tidak ada lagi tempat untuk mencari tahu siapa mereka.
        $simpananTerbaru = MemberSaving::berlaku()
            ->with('koperasiMemberTermasukArsip.participant')
            ->latest('tanggal')
            ->limit(8)
            ->get();

        // CALON ANGGOTA — peserta Cahaya Preneur yang masih berjalan dan
        // belum punya keanggotaan. whereDoesntHave memakai relasi yang sudah
        // ada, jadi tidak ada daftar calon yang perlu dikelola terpisah.
        $calonAnggota = Participant::with('pendamping')
            ->whereDoesntHave('koperasiMember')
            ->whereIn('status', Participant::STATUS_BERJALAN)
            ->orderBy('nama_peserta')
            ->limit(10)
            ->get();

        $jumlahCalon = Participant::whereDoesntHave('koperasiMember')
            ->whereIn('status', Participant::STATUS_BERJALAN)
            ->count();

        return view('dashboard-koperasi', [
            // 'modul' ikut dikirim supaya penanda wilayah halaman tetap
            // konsisten dengan Dashboard lain — halaman ini memang berada di
            // ekosistem Koperasi, dan nilai ?modul apa pun tidak bisa
            // memindahkan Tim Koperasi keluar dari sini.
            'modul' => 'koperasi',
            'stats' => $stats,
            // Ringkasan Tugas Koperasi dihitung TugasKoperasiService — sumber
            // yang sama dengan halaman /koperasi/tugas, jadi angka di kedua
            // tempat tidak mungkin berbeda. Pelajaran dari Phase 1, waktu
            // Dashboard menghitung sendiri dan memberi jawaban lain.
            // ringkas(), BUKAN semua()['ringkas']: Dashboard cuma butuh angkanya.
            // semua() memuat seluruh transaksi & anggota beserta relasinya
            // hanya untuk dihitung — 18,5 MB pada volume lima tahun.
            'ringkasTugas' => app(\App\Services\TugasKoperasiService::class)->ringkas(),
            'simpananTerbaru' => $simpananTerbaru,
            'calonAnggota' => $calonAnggota,
            'jumlahCalon' => $jumlahCalon,
            'anggotaTerbaru' => $anggota->sortByDesc('created_at')->take(5),
        ]);
    }

    /**
     * DASHBOARD PENDAMPING — seluruhnya ter-scope ke binaan sendiri.
     *
     * SATU sumber query untuk semua angkanya: $binaanIds. Kalau tiap kartu
     * membangun querynya sendiri-sendiri, cepat atau lambat ada satu yang
     * lupa disaring dan bocor ke binaan Pendamping lain — persis cacat yang
     * diperbaiki di sini.
     */
    private function pendampingDashboard()
    {
        // Pemanggil sudah memastikan profilnya ada; diperiksa ulang di sini
        // supaya method ini tetap aman kalau kelak dipanggil dari tempat lain.
        abort_unless($pendamping = Auth::user()->pendamping, 403);

        // legalitasRecords ikut dimuat karena kelompokkan() di bawah kini
        // ikut membaca keadaan usaha tiap binaan. Tanpa ini, dashboard
        // pendamping menembak satu query legalitas per binaan.
        $binaan = $pendamping->participants()
            ->with(['koperasiMember', 'legalitasRecords'])
            ->orderBy('nama_peserta')
            ->get();

        $binaanIds = $binaan->pluck('id');

        $pendampinganBulanIni = PendampinganRecord::whereIn('participant_id', $binaanIds)
            ->whereMonth('tanggal', now()->month)
            ->whereYear('tanggal', now()->year)
            ->count();

        // Perlu follow up: catatan yang punya rencana tindak lanjut dan
        // tanggalnya sudah lewat atau jatuh dalam sepekan ke depan.
        $perluFollowUp = PendampinganRecord::with('participant')
            ->whereIn('participant_id', $binaanIds)
            ->perluFollowUp()
            ->orderBy('tanggal_follow_up')
            ->limit(10)
            ->get();

        // ==========================================================
        // DAFTAR PRIORITAS.
        //
        // Satu daftar kerja yang sudah diurutkan, bukan lima kartu terpisah
        // yang harus dibaca satu-satu. Alasannya ikut ditampilkan supaya
        // pendamping tahu KENAPA seseorang ada di atas — bukan sekadar
        // disodori urutan yang tidak bisa dibantah.
        //
        // Bobotnya sengaja sederhana dan terbaca: makin banyak hal yang
        // tertinggal, makin ke atas.
        // ==========================================================
        $statusFollowUp = $this->followUp->untukBanyak($binaan);

        // ==========================================================
        // RINGKASAN "TUGAS SAYA"
        //
        // Dihitung lewat kelompokkan() — persis fungsi yang dipakai halaman
        // Tugas Saya. Angka di kartu ini karena itu TIDAK MUNGKIN berbeda
        // dari isi halamannya; kalau dihitung ulang di sini dengan caranya
        // sendiri, keduanya akan menyimpang pada hari pertama ada yang
        // mengubah salah satu rumusnya.
        // ==========================================================
        $tugas = $this->followUp->kelompokkan($binaan->filter->masihBerjalan());

        $ringkasTugas = [
            'perlu_segera' => $tugas['terlambat']->count() + $tugas['belum_pernah']->count(),
            'perlu_perhatian' => $tugas['perlu_perhatian']->count(),
            'hari_ini' => $tugas['hari_ini']->count(),
            'normal' => $tugas['normal']->count(),
        ];

        $sudahLaporBulanIni = OmzetReport::whereIn('participant_id', $binaanIds)
            ->where('periode_bulan', now()->month)
            ->where('periode_tahun', now()->year)
            ->pluck('participant_id')
            ->flip();

        $prioritas = $binaan
            ->filter->masihBerjalan()
            ->map(function ($p) use ($statusFollowUp, $sudahLaporBulanIni) {
                $fu = $statusFollowUp[$p->id];
                $alasan = [];
                $skor = 0;

                if ($fu['terakhir'] === null) {
                    $alasan[] = 'Belum pernah didampingi';
                    $skor += 100;
                } elseif ($fu['status'] === FollowUpService::MERAH) {
                    $alasan[] = 'Tanpa pendampingan ' . $fu['hari'] . ' hari';
                    $skor += 60 + min($fu['hari'], 200);
                } elseif ($fu['status'] === FollowUpService::KUNING) {
                    $alasan[] = 'Mendekati batas: ' . $fu['hari'] . ' hari';
                    $skor += 30;
                }

                if ($fu['terlambat_follow_up']) {
                    $alasan[] = 'Follow up jatuh tempo ' . $fu['follow_up']->format('d-m-Y');
                    $skor += 50;
                }

                if (! $sudahLaporBulanIni->has($p->id)) {
                    $alasan[] = 'Omzet bulan ini belum diperbarui';
                    $skor += 20;
                }

                // Data belum lengkap — hanya yang benar-benar menghambat kerja
                // lapangan (kontak & alamat), bukan seluruh kolom opsional.
                $kurang = collect([
                    'Nomor HP' => filled($p->nomor_hp),
                    'Alamat' => filled($p->alamat),
                    'Nama usaha' => filled($p->nama_usaha),
                ])->filter(fn ($ada) => ! $ada)->keys();

                if ($kurang->isNotEmpty()) {
                    $alasan[] = 'Data belum lengkap: ' . $kurang->implode(', ');
                    $skor += 15;
                }

                return ['peserta' => $p, 'follow_up' => $fu, 'alasan' => $alasan, 'skor' => $skor];
            })
            ->filter(fn ($b) => $b['skor'] > 0)
            ->sortByDesc('skor')
            ->values();

        $riwayatTerbaru = PendampinganRecord::with(['participant', 'pendamping'])
            ->whereIn('participant_id', $binaanIds)
            ->latest('tanggal')
            ->limit(8)
            ->get();

        // Kunjungan lapangan bulan ini — memakai nilai jenis_kegiatan yang
        // SUDAH ADA di PendampinganRecord::JENIS_KEGIATAN, bukan penanda baru.
        $kunjunganBulanIni = PendampinganRecord::whereIn('participant_id', $binaanIds)
            ->where('jenis_kegiatan', PendampinganRecord::KUNJUNGAN_USAHA)
            ->whereMonth('tanggal', now()->month)
            ->whereYear('tanggal', now()->year)
            ->count();

        // ==========================================================
        // PENDAMPINGAN TERAKHIR per binaan.
        //
        // Satu query agregat untuk SEMUA binaan sekaligus, bukan satu query
        // per baris — daftar binaan bisa panjang, dan pola N+1 di dashboard
        // adalah tempat paling mudah untuk membuat halaman jadi lambat.
        // ==========================================================
        $pendampinganTerakhir = PendampinganRecord::whereIn('participant_id', $binaanIds)
            ->selectRaw('participant_id, MAX(tanggal) as terakhir')
            ->groupBy('participant_id')
            ->pluck('terakhir', 'participant_id');

        // ==========================================================
        // PERLU DITINDAKLANJUTI — indikator sederhana dari data yang ADA.
        //
        // Dua keadaan, tidak lebih:
        //   1. belum pernah didampingi sama sekali;
        //   2. pendampingan terakhirnya lebih dari 30 hari lalu.
        //
        // SENGAJA tidak dibuat sistem pengingat: tidak ada tabel jadwal, tidak
        // ada notifikasi, tidak ada asumsi tanggal yang tidak tercatat. Kalau
        // datanya memang belum ada, binaan itu masuk kategori (1) — yang
        // memang benar, bukan tebakan.
        //
        // Binaan yang statusnya sudah tidak berjalan (Alumni/Nonaktif)
        // dikecualikan: mereka memang tidak perlu dikunjungi lagi.
        // ==========================================================
        // Memakai ambang yang SAMA dengan halaman Tugas Saya, bukan angka 30
        // yang dulu ditulis tetap di sini. Admin bisa mengubah ambang lewat
        // Pengaturan, dan sebelumnya perubahan itu diikuti halaman Tugas Saya
        // tapi TIDAK oleh Dashboard — dua halaman memberi jawaban berbeda
        // untuk pertanyaan yang sama, tanpa ada yang terlihat salah.
        $batas = now()->subDays($this->followUp->ambang()['kuning'])->startOfDay();

        $perluDitindaklanjuti = $binaan
            ->filter(fn ($p) => $p->masihBerjalan())
            ->map(function ($p) use ($pendampinganTerakhir, $batas) {
                $terakhir = $pendampinganTerakhir[$p->id] ?? null;
                $tanggal = $terakhir ? \Illuminate\Support\Carbon::parse($terakhir) : null;

                return [
                    'peserta' => $p,
                    'terakhir' => $tanggal,
                    'hari' => $tanggal?->diffInDays(now()),
                ];
            })
            ->filter(fn ($baris) => $baris['terakhir'] === null || $baris['terakhir']->lt($batas))
            ->sortBy(fn ($baris) => $baris['terakhir']?->timestamp ?? 0)
            ->values();

        $stats = [
            'total_binaan' => $binaan->count(),
            'binaan_aktif' => $binaan->filter->masihBerjalan()->count(),
            // Binaan baru = yang mulai program pada bulan berjalan.
            'binaan_baru' => $binaan
                ->filter(fn ($p) => $p->tanggal_mulai_program
                    && $p->tanggal_mulai_program->isSameMonth(now()))
                ->count(),
            'pendampingan_bulan_ini' => $pendampinganBulanIni,
            'kunjungan_bulan_ini' => $kunjunganBulanIni,
            'perlu_follow_up' => $perluFollowUp->count(),
            'perlu_ditindaklanjuti' => $perluDitindaklanjuti->count(),
            'binaan_nonaktif' => $binaan->reject->masihBerjalan()->count(),
            'prioritas' => $prioritas->count(),
            'anggota_koperasi' => $binaan->filter(fn ($p) => $p->koperasiMember !== null)->count(),
            'omzet_bulan_ini' => (int) OmzetReport::whereIn('participant_id', $binaanIds)
                ->where('periode_bulan', now()->month)
                ->where('periode_tahun', now()->year)
                ->sum('omzet'),
        ];

        // KPI PRIBADI — memakai service yang sama dengan halaman KPI, jadi
        // angka di dashboard dan di "KPI Saya" selalu identik.
        $raporKpi = $this->kpi->untukSatu($pendamping, now()->startOfMonth(), now()->endOfMonth());

        return view('dashboard-pendamping', [
            'raporKpi' => $raporKpi,
            'pendamping' => $pendamping,
            'binaan' => $binaan,
            'stats' => $stats,
            'ringkasTugas' => $ringkasTugas,
            'perluFollowUp' => $perluFollowUp,
            'perluDitindaklanjuti' => $perluDitindaklanjuti,
            'pendampinganTerakhir' => $pendampinganTerakhir,
            'statusFollowUp' => $statusFollowUp,
            'prioritas' => $prioritas,
            'riwayatTerbaru' => $riwayatTerbaru,
        ]);
    }

    /**
     * RINGKASAN "SEMUA KEGIATAN" — tab netral default.
     *
     * SENGAJA cuma hitungan sederhana (COUNT) dari data yang sudah ada:
     * tidak ada rumus/agregasi uang baru, dan TIDAK memanggil LabaRugiService
     * supaya tab default tetap ringan dan tidak menduplikasi angka yang sudah
     * jadi tanggung jawab panel masing-masing modul.
     */
    private function ringkasanSemuaKegiatan(): array
    {
        return [
            'total_peserta' => Participant::count(),
            'peserta_aktif' => Participant::where('status', 'Aktif')->count(),
            'total_pendamping' => Pendamping::count(),
            'total_anggota' => KoperasiMember::count(),
            'anggota_aktif' => KoperasiMember::where('status', 'Aktif')->count(),
            'total_unit' => BusinessUnit::count(),
        ];
    }

    /**
     * DATA PANEL KOPERASI CAHAYA — DIPINDAHKAN apa adanya dari
     * KoperasiDashboardController@index (bukan disalin: controller itu
     * sekarang tinggal redirect ke sini). Tidak ada rumus/definisi yang
     * diubah — LabaRugiService::hitung() tetap jadi SATU sumber perhitungan
     * bersama halaman Keuangan & Laporan.
     *
     * Filter Tahun/Bulan/Unit memakai nilai & arti yang SAMA dengan halaman
     * Keuangan (LabaRugiController), dan keenam KPI membaca satu hasil
     * hitung() yang sama — tidak ada kartu yang memakai dataset lain.
     */
    private function koperasiData(Request $request): array
    {
        $tahun = (string) $request->input('tahun', 'default');
        $bulan = (string) $request->input('bulan', 'default');
        $unitId = $request->input('unit_id') ?: null;

        $stats = [
            'total_anggota' => KoperasiMember::count(),
            'anggota_aktif' => KoperasiMember::where('status', 'Aktif')->count(),
            // berlaku(): entri simpanan yang sudah DIBATALKAN tetap tersimpan
            // sebagai jejak koreksi, tapi tidak boleh ikut menambah saldo.
            'total_pokok' => (float) MemberSaving::berlaku()->where('jenis_simpanan', 'Simpanan Pokok')->sum('nominal'),
            'total_wajib' => (float) MemberSaving::berlaku()->where('jenis_simpanan', 'Simpanan Wajib')->sum('nominal'),
            'total_sukarela' => (float) MemberSaving::berlaku()->where('jenis_simpanan', 'Simpanan Sukarela')->sum('nominal'),
        ];

        $stats['total_simpanan'] = $stats['total_pokok'] + $stats['total_wajib'] + $stats['total_sukarela'];

        $stats['total_transaksi'] = SalesTransaction::count();
        $stats['total_penjualan'] = (float) SalesTransaction::sum('total');
        $stats['transaksi_bulan_ini'] = SalesTransaction::whereMonth('tanggal', now()->month)
            ->whereYear('tanggal', now()->year)
            ->count();
        $stats['penjualan_bulan_ini'] = (float) SalesTransaction::whereMonth('tanggal', now()->month)
            ->whereYear('tanggal', now()->year)
            ->sum('total');

        $stats['pengeluaran_bulan_ini'] = (float) Pengeluaran::aktif()
            ->whereMonth('tanggal', now()->month)
            ->whereYear('tanggal', now()->year)
            ->sum('nominal');

        $stats['saldo_dana_sukarela'] = (float) DanaSukarelaMasuk::berlaku()->sum('nominal') - (float) DanaSukarelaKeluar::berlaku()->sum('nominal');
        $stats['saldo_dana_hibah'] = (float) DanaHibahMasuk::berlaku()->sum('nominal') - (float) DanaHibahKeluar::berlaku()->sum('nominal');

        // ENAM KPI — semuanya dari SATU hasil LabaRugiService, sama dengan
        // halaman Keuangan. Default: Tahun Berjalan + Semua Bulan + Semua Unit.
        //
        // Nama kunci *_tahun_ini dipertahankan (dibaca test & view); dengan
        // filter terpasang isinya mengikuti periode yang dipilih.
        //
        // Hak Mitra dan Fee Koperasi SENGAJA tidak dijadikan KPI: hak mitra
        // dihitung koperasi di luar sistem, dan fee hanya ada pada transaksi
        // lama. Keduanya tetap tersimpan dan tetap ada di Keuangan/Laporan.
        $lr = $this->labaRugiService->hitung($tahun, $bulan, $unitId);
        $stats['penjualan_tahun_ini'] = $lr['total']['penjualan'];
        $stats['hpp_tahun_ini'] = $lr['total']['hpp'];
        $stats['laba_kotor_tahun_ini'] = $lr['total']['laba_kotor'];
        $stats['pengeluaran_tahun_ini'] = $lr['total']['pengeluaran_total'];
        $stats['piutang_aktif'] = $lr['total']['piutang'];
        // Bukan KPI: uang keluar yang biayanya baru masuk laba lewat HPP.
        $stats['pembelian_stok'] = $lr['total']['pembelian_stok'];
        $stats['laba_bersih_tahun_ini'] = $lr['total']['laba_bersih'];
        $stats['unit_terbaik'] = $lr['unit_terbaik'];
        $stats['ada_transaksi_tahun_ini'] = $lr['ada_transaksi'];
        $perUnit = collect($lr['per_unit'])->sortByDesc('laba_bersih')->values();

        // Produk Terlaris — filter Tahun/Bulan/Unit yang sama dengan KPI.
        $produkTerlaris = $this->labaRugiService->produkTerlaris($tahun, $bulan, $unitId);

        $filters = ['tahun' => $tahun, 'bulan' => $bulan, 'unit_id' => $unitId];
        $units = BusinessUnit::orderBy('nama')->get();

        // Status Pembayaran — hitungan sederhana dari kolom yang sudah ada.
        $statusPembayaran = [
            'Lunas' => SalesTransaction::where('status_pembayaran', 'Lunas')->count(),
            'DP' => SalesTransaction::where('status_pembayaran', 'DP')->count(),
            'Belum Dibayar' => SalesTransaction::where('status_pembayaran', 'Belum Dibayar')->count(),
        ];

        $penjualanTerbaru = SalesTransaction::with(['businessUnit', 'customer'])
            ->orderByDesc('tanggal')
            ->orderByDesc('id')
            ->take(5)
            ->get();

        $pengeluaranTerbaru = Pengeluaran::aktif()
            ->with('businessUnit')
            ->orderByDesc('tanggal')
            ->orderByDesc('id')
            ->take(5)
            ->get();

        return compact('stats', 'perUnit', 'produkTerlaris', 'statusPembayaran', 'penjualanTerbaru', 'pengeluaranTerbaru', 'filters', 'units');
    }

    /**
     * Dashboard Pimpinan — Executive Dashboard TERINTEGRASI (K7).
     * Menggabungkan Cahaya Preneur + Koperasi Cahaya, read-only.
     * Laba Bersih Koperasi WAJIB lewat LabaRugiService (satu sumber
     * kebenaran yang sama dengan Keuangan/Laporan K5/K6) — TIDAK ADA
     * rumus laba baru dibuat di sini.
     */
    private function pimpinanDashboard(Request $request)
    {
        $tahun = $request->filled('tahun') ? $request->integer('tahun') : now()->year;
        $bulan = $request->filled('bulan') ? $request->integer('bulan') : null;

        // ==========================================================
        // CAHAYA PRENEUR — logic & definisi Impact TIDAK DIUBAH SAMA SEKALI,
        // cuma dipindah posisi tampilannya di view (lihat GAP 2).
        // ==========================================================
        $totalPeserta = Participant::count();

        $ringkasan = [
            'total_peserta' => $totalPeserta,
            'peserta_aktif' => Participant::where('status', 'Aktif')->count(),
            'total_pendamping' => Pendamping::count(),
            'total_omzet' => (int) OmzetReport::where('periode_tahun', $tahun)
                ->when($bulan, fn ($q) => $q->where('periode_bulan', $bulan))
                ->sum('omzet'),
            'total_pendampingan' => PendampinganRecord::count(),
            'total_pelatihan' => TrainingRecord::count(),
        ];

        $omzetPerBulanRaw = OmzetReport::where('periode_tahun', $tahun)
            ->selectRaw('periode_bulan, SUM(omzet) as total')
            ->groupBy('periode_bulan')
            ->pluck('total', 'periode_bulan');

        $namaBulan = OmzetReport::BULAN;
        $omzetChart = [
            'labels' => array_values($namaBulan),
            'data' => collect(range(1, 12))->map(fn ($b) => (int) ($omzetPerBulanRaw[$b] ?? 0))->all(),
        ];

        if ($bulan) {
            $bulanPembanding = $bulan === 1 ? 12 : $bulan - 1;
            $tahunPembanding = $bulan === 1 ? $tahun - 1 : $tahun;
            $omzetPembanding = (int) OmzetReport::where('periode_tahun', $tahunPembanding)
                ->where('periode_bulan', $bulanPembanding)
                ->sum('omzet');
        } else {
            $tahunPembanding = $tahun - 1;
            $omzetPembanding = (int) OmzetReport::where('periode_tahun', $tahunPembanding)->sum('omzet');
        }

        $adaDataPembanding = $omzetPembanding > 0;
        $kenaikanOmzet = $ringkasan['total_omzet'] - $omzetPembanding;
        $persenKenaikan = $adaDataPembanding ? round(($kenaikanOmzet / $omzetPembanding) * 100, 1) : null;

        $sudahDidampingiCount = PendampinganRecord::distinct()->count('participant_id');
        $punyaLegalitasCount = LegalitasRecord::where('status', LegalitasRecord::STATUS_SUDAH_ADA)->distinct()->count('participant_id');
        $ikutPelatihanCount = TrainingRecord::distinct()->count('participant_id');

        $omzetMeningkatCount = Participant::with('omzetReports')
            ->get()
            ->reduce(function ($count, Participant $p) {
                $urutan = $p->omzetReports->sortByDesc(fn ($r) => $r->periode_tahun * 100 + $r->periode_bulan)->values();

                if ($urutan->count() < 2) {
                    return $count;
                }

                return $urutan[0]->omzet > $urutan[1]->omzet ? $count + 1 : $count;
            }, 0);

        $perkembangan = [
            'omzet_meningkat' => $omzetMeningkatCount,
            'sudah_didampingi' => $sudahDidampingiCount,
            'punya_legalitas' => $punyaLegalitasCount,
            'ikut_pelatihan' => $ikutPelatihanCount,
        ];

        // ==========================================================
        // DAFTAR "PERLU PERHATIAN" DIHAPUS — tidak dipakai siapa pun.
        //
        // Blok di sini dulu memuat SELURUH peserta aktif beserta lima
        // relasinya (omzet, pendampingan, pelatihan, legalitas, pendamping),
        // lalu menyusun daftar alasan per peserta. Hasilnya dikirim ke view
        // sebagai 'perluPerhatianDitampilkan' dan 'totalPerluPerhatian'.
        //
        // Keduanya TIDAK dirender di satu berkas Blade pun, tidak dipakai
        // kode lain, dan tidak diuji test mana pun — kemungkinan besar
        // bagian tampilannya pernah dihapus dan perhitungannya tertinggal.
        //
        // Biayanya terukur pada volume lima tahun pemakaian (200 peserta,
        // 12.000 baris omzet): Dashboard memakan 2,4 DETIK, dan hampir
        // seluruhnya dihabiskan di sini — untuk sesuatu yang tidak pernah
        // ditampilkan. Dashboard adalah halaman pertama yang dibuka SETIAP
        // pengguna, jadi ini biaya yang dibayar semua orang, setiap hari.
        //
        // Kalau bagian ini memang ingin dihidupkan kembali, tulis ulang
        // dengan withCount() untuk tiga relasi yang cuma diperiksa kosong
        // atau tidaknya — jangan memuat barisnya.
        // ==========================================================

        $impactProgram = [
            'total_dibina' => $totalPeserta,
            'omzet_meningkat' => $omzetMeningkatCount,
            'kenaikan_omzet' => $kenaikanOmzet,
            'ada_data_kenaikan' => $adaDataPembanding,
            'persen_kenaikan' => $persenKenaikan,
            'punya_legalitas' => $punyaLegalitasCount,
            'sudah_didampingi' => $sudahDidampingiCount,
            'ikut_pelatihan' => $ikutPelatihanCount,
        ];

        // ==========================================================
        // KOPERASI CAHAYA (BARU — K7) — Laba Bersih WAJIB dari
        // LabaRugiService, sama persis dgn Keuangan & Laporan K5/K6.
        // ==========================================================
        $lrTahun = (string) $tahun;
        $lrBulan = $bulan ? (string) $bulan : 'semua';
        $lr = $this->labaRugiService->hitung($lrTahun, $lrBulan, null);

        $koperasiRingkasan = [
            'total_anggota' => KoperasiMember::count(),
            'anggota_aktif' => KoperasiMember::where('status', 'Aktif')->count(),
            'total_unit' => BusinessUnit::count(),
            'total_penjualan' => $lr['total']['penjualan'],
            'laba_bersih' => $lr['total']['laba_bersih'],

            // Simpanan anggota ikut ditampilkan ke Pimpinan.
            //
            // Sebelumnya kartu Koperasi menyebut anggota, penjualan, unit,
            // dan laba — tapi tidak simpanan, padahal simpanan pokok dan
            // wajib adalah modal yang disetor anggota sendiri dan menjadi
            // salah satu ukuran kesehatan koperasi yang paling ditanyakan
            // di Rapat Anggota.
            'total_simpanan' => (float) MemberSaving::berlaku()->sum('nominal'),

            // Bagian yang pemiliknya sudah tidak lagi menjadi anggota —
            // dasar perhitungan yang SAMA dengan halaman Keuangan dan
            // Laporan Koperasi. Angkanya tidak dikurangi, hanya diterangkan.
            'simpanan_anggota_tidak_aktif' => (float) MemberSaving::berlaku()
                ->milikAnggotaTidakAktif()
                ->sum('nominal'),
        ];

        $kinerjaKoperasi = $lr['total'];
        $performaUnit = $lr['per_unit'];
        $unitTerbaikNama = $lr['unit_terbaik'];
        $unitTerbaikData = collect($performaUnit)->firstWhere('nama', $unitTerbaikNama);
        $adaAktivitasKoperasi = $lr['ada_transaksi'] || $lr['ada_pengeluaran'];

        // Unit dgn Laba Bersih negatif TAPI memang sudah ada aktivitas (bukan unit kosong)
        $unitPerluPerhatian = collect($performaUnit)
            ->filter(fn ($u) => $u['laba_bersih'] < 0 && ($u['penjualan'] > 0 || $u['pengeluaran'] > 0))
            ->values();

        // Grafik Penjualan Koperasi per bulan (trend, pola sama seperti grafik omzet Cahaya Preneur).
        //
        // Pengelompokan per bulan SENGAJA dilakukan di PHP, bukan lewat
        // selectRaw('MONTH(tanggal)'). MONTH() itu fungsi khusus MySQL —
        // satu-satunya SQL driver-specific yang tersisa di aplikasi ini — dan
        // bikin halaman ini error total di driver lain (mis. SQLite yang dipakai
        // environment test: "no such function: MONTH"). whereYear() di bawah
        // TETAP dipakai karena itu API Laravel yang sudah portabel antar driver.
        //
        // Hasil akhirnya sama persis: Collection dengan key = nomor bulan (1-12)
        // dan value = total penjualan bulan itu. Filter status & tahun tidak
        // berubah sama sekali, jadi angkanya identik dengan sebelumnya.
        $penjualanPerBulanRaw = SalesTransaction::whereNotIn('status_transaksi', ['Draft', 'Dibatalkan'])
            ->whereYear('tanggal', $tahun)
            ->get(['tanggal', 'total'])
            ->groupBy(fn (SalesTransaction $t) => (int) $t->tanggal->month)
            ->map(fn ($rows) => (int) $rows->sum('total'));

        $koperasiChart = [
            'labels' => array_values($namaBulan),
            'data' => collect(range(1, 12))->map(fn ($b) => (int) ($penjualanPerBulanRaw[$b] ?? 0))->all(),
        ];

        return view('dashboard-pimpinan', [
            'tahun' => $tahun,
            'bulan' => $bulan,
            'bulanOptions' => $namaBulan,
            'ringkasan' => $ringkasan,
            'omzetChart' => $omzetChart,
            'perkembangan' => $perkembangan,
            'totalPeserta' => $totalPeserta,
            // ==========================================================
            // MENUNGGU PERSETUJUAN PIMPINAN
            //
            // Menyetujui hasil SROI sebagai Final adalah SATU-SATUNYA
            // tindakan yang benar-benar milik Pimpinan — sisanya membaca.
            // Sampai sekarang tidak ada satu pun tempat yang memberitahunya
            // bahwa ada angka yang menunggu: ia harus membuka Dampak & SROI
            // lalu memeriksa sendiri program per program.
            //
            // Kewenangan yang tidak pernah diingatkan akan berakhir tidak
            // pernah dipakai, dan laporan dampak berhenti di "Terhitung"
            // tanpa ada yang tahu ia sedang menunggu siapa.
            //
            // Yang dihitung: perhitungan yang SUDAH selesai dihitung tapi
            // BELUM final. Bukan program yang datanya belum lengkap — itu
            // pekerjaan Admin, bukan keputusan Pimpinan.
            'menungguPersetujuan' => \App\Models\SroiCalculation::with('program')
                ->whereIn('status', [
                    \App\Models\SroiCalculation::STATUS_TERHITUNG,
                    \App\Models\SroiCalculation::STATUS_PERLU_REVIEW,
                ])
                ->latest('created_at')
                ->get(),
            'impactProgram' => $impactProgram,
            // Pimpinan adalah pembaca utama angka dampak — dialah yang
            // menyetujuinya sebagai Final. Ringkasannya memakai sumber yang
            // SAMA dengan dashboard Admin, jadi keduanya mustahil berbeda.
            'ringkasanDampak' => $this->ringkasanDampak(),
            // Koperasi (baru)
            'koperasiRingkasan' => $koperasiRingkasan,
            'kinerjaKoperasi' => $kinerjaKoperasi,
            'performaUnit' => $performaUnit,
            'unitTerbaikNama' => $unitTerbaikNama,
            'unitTerbaikData' => $unitTerbaikData,
            'adaAktivitasKoperasi' => $adaAktivitasKoperasi,
            'unitPerluPerhatian' => $unitPerluPerhatian,
            'koperasiChart' => $koperasiChart,
        ]);
    }
}
