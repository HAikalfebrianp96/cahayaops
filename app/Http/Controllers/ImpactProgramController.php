<?php

namespace App\Http\Controllers;

use App\Models\ImpactProgram;
use App\Models\Pendamping;
use App\Models\SroiCalculation;
use App\Services\SroiService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use App\Services\GambarCetak;

/**
 * PROGRAM DAMPAK — daftar, susun, hitung, setujui.
 *
 * Controller ini menampung seluruh siklus hidup satu program karena memang
 * satu alur: program disusun, diisi investasi & outcome (controller
 * tersendiri), dihitung, lalu disetujui. Memecahnya lagi hanya akan menyebar
 * pemeriksaan status Final ke banyak tempat.
 */
class ImpactProgramController extends Controller
{
    public function __construct(private SroiService $sroi)
    {
    }

    public function index()
    {
        Gate::authorize('viewAny', ImpactProgram::class);

        $programs = ImpactProgram::with(['pendamping', 'investments'])
            ->withCount('outcomes')
            ->orderByDesc('periode_selesai')
            ->paginate(15);

        // Jumlah peserta per program dihitung sekali di sini, bukan di dalam
        // perulangan kartu. peserta() mengembalikan query, jadi memanggilnya
        // dari view berarti satu query tambahan untuk setiap program yang
        // tampil — persis pola N+1 yang baru terasa setelah programnya banyak.
        $jumlahPeserta = $programs->getCollection()
            ->mapWithKeys(fn (ImpactProgram $p) => [$p->id => $p->peserta()->count()])
            ->all();

        return view('impact.index', [
            'programs' => $programs,
            'jumlahPeserta' => $jumlahPeserta,
            // Keadaan proxy dikirim ke daftar program karena DI SINILAH Admin
            // mendarat lebih dulu. Proxy yang belum ada tidak menghalangi
            // pembuatan program, tapi menghentikan langkah outcome — dan
            // mengetahuinya di awal jauh lebih murah daripada menemukannya
            // setelah program dan investasi sudah terlanjur disusun.
            'jumlahProxy' => \App\Models\FinancialProxy::aktif()->count(),
            'bisaKelola' => Auth::user()->can('manage', ImpactProgram::class),
        ]);
    }

    /**
     * Halaman kerja satu program: rantai Input -> Output -> Outcome -> Impact,
     * status kesiapan, dan hasil perhitungan terakhir.
     */
    public function show(ImpactProgram $impact)
    {
        Gate::authorize('view', $impact);

        $impact->load([
            'pendamping',
            'investments.pencatat',
            'outcomes.proxy',
            'calculations.penghitung',
            'calculations.penyetuju',
        ]);

        $kesiapan = $this->sroi->periksaKesiapan($impact);

        $pengukuran = $this->pengukuranPeserta($impact);

        return view('impact.show', [
            'program' => $impact,
            'kesiapan' => $kesiapan,
            'rantai' => $this->rantaiDampak($impact, $kesiapan),
            'terakhir' => $impact->calculations->first(),
            'pengukuran' => $pengukuran,
            'langkah' => $this->langkahPenyusunan($impact, $kesiapan, $pengukuran),
            'bisaKelola' => Auth::user()->can('update', $impact),
            'bisaSetujui' => Auth::user()->can('setujui', $impact),
        ]);
    }

    /**
     * KEADAAN BASELINE & ENDLINE peserta program ini.
     *
     * ==================================================================
     * KENAPA INI PERLU MUNCUL DI HALAMAN PROGRAM
     * ==================================================================
     *
     * Baseline dicatat per peserta, di modul Peserta — jauh dari sini. Tapi
     * di sinilah orang BARU MENYADARI bahwa baseline-nya kurang: pesan
     * "belum ada peserta dengan baseline dan endline yang lengkap" muncul
     * saat SROI menolak dihitung. Tanpa daftar dan tautannya, Admin harus
     * menebak sendiri peserta mana yang perlu dilengkapi, lalu mencarinya
     * satu per satu di modul lain.
     *
     * Daftarnya dibatasi 10 nama. Yang dibutuhkan di halaman ini adalah
     * titik masuk, bukan salinan daftar peserta.
     */
    private function pengukuranPeserta(ImpactProgram $program): array
    {
        $peserta = $program->peserta()->with('measurements')->get();

        $punyaBaseline = $peserta->filter(
            fn ($p) => $p->measurements->firstWhere('jenis', \App\Models\ParticipantMeasurement::BASELINE) !== null
        );

        $terukur = $punyaBaseline->filter(
            fn ($p) => $p->measurements->contains(
                fn ($m) => in_array($m->jenis, [
                    \App\Models\ParticipantMeasurement::MONITORING,
                    \App\Models\ParticipantMeasurement::ENDLINE,
                ], true)
            )
        );

        return [
            'total' => $peserta->count(),
            'punya_baseline' => $punyaBaseline->count(),
            'terukur' => $terukur->count(),
            'belum_baseline' => $peserta->diff($punyaBaseline)->take(10)->values(),
            'belum_pembanding' => $punyaBaseline->diff($terukur)->take(10)->values(),
        ];
    }

    /**
     * ALUR PENYUSUNAN — sebelas langkah, masing-masing dengan keadaannya.
     *
     * ==================================================================
     * KENAPA STEPPER, BUKAN SEKADAR DAFTAR KARTU
     * ==================================================================
     *
     * Menyusun laporan dampak punya urutan yang tidak bisa dibalik: outcome
     * tidak berarti tanpa proxy, proxy tidak berarti tanpa perubahan yang
     * terukur, dan perubahan tidak ada tanpa baseline. Halaman yang hanya
     * menampilkan kartu-kartu sejajar membiarkan Admin mengisi dari mana
     * saja, lalu menemukan urutannya salah setelah pekerjaan menumpuk.
     *
     * Setiap langkah membawa keadaannya sendiri — selesai, belum, atau
     * terkunci — dan tautan ke tempat mengerjakannya. Statusnya DITURUNKAN
     * dari data, bukan disimpan: langkah yang datanya dihapus otomatis
     * kembali menjadi "belum".
     */
    private function langkahPenyusunan(ImpactProgram $program, array $kesiapan, array $pengukuran): array
    {
        $adaInvestasi = $program->investments->isNotEmpty();
        $adaOutcome = $program->outcomes->isNotEmpty();
        $adaProxy = $adaOutcome && $program->outcomes->every(fn ($o) => $o->proxy !== null);
        $terakhir = $program->calculations->first();

        // Penyesuai dianggap "sudah ditinjau" ketika tidak ada satu pun
        // outcome yang masih memakai nilai bawaan maupun displacement yang
        // belum ditentukan — dua keadaan yang sama-sama berarti belum
        // dipikirkan, bukan sudah diputuskan.
        $penyesuaiDitinjau = $adaOutcome && $program->outcomes->every(
            fn ($o) => ! $o->penyesuaiMasihBawaan() && ! $o->displacementBelumDitentukan()
        );

        return [
            [
                'judul' => 'Program & Periode',
                'selesai' => true,
                'ket' => $program->periodeLabel(),
                'tautan' => route('impact.edit', $program),
                'aksi' => 'Ubah',
            ],
            [
                'judul' => 'Investment',
                'selesai' => $adaInvestasi,
                'ket' => $adaInvestasi
                    ? $program->investments->count() . ' pos · ' . rupiah($kesiapan['total_investasi'])
                    : 'Belum ada — rasio SROI tidak punya penyebut',
                'tautan' => route('impact.investasi.create', $program),
                'aksi' => 'Tambah',
            ],
            [
                'judul' => 'Output & Activity',
                'selesai' => true,
                'ket' => 'Otomatis dari pendampingan, pelatihan & legalitas yang sudah tercatat',
                'tautan' => null,
                'aksi' => null,
            ],
            [
                'judul' => 'Baseline & Endline Peserta',
                'selesai' => $pengukuran['terukur'] > 0,
                'ket' => $pengukuran['total'] === 0
                    ? 'Belum ada peserta pada periode program ini'
                    : $pengukuran['terukur'] . ' dari ' . $pengukuran['total'] . ' peserta terukur'
                        . ' · ' . $pengukuran['punya_baseline'] . ' punya baseline',
                'tautan' => null,
                'aksi' => null,
            ],
            [
                'judul' => 'Financial Proxy',
                'selesai' => \App\Models\FinancialProxy::aktif()->exists(),
                'ket' => \App\Models\FinancialProxy::aktif()->count() . ' proxy aktif tersedia',
                'tautan' => route('impact.proxy.index'),
                'aksi' => 'Kelola',
            ],
            [
                'judul' => 'Outcome & Indicator',
                'selesai' => $adaOutcome && $adaProxy,
                'ket' => ! $adaOutcome
                    ? 'Belum ada outcome'
                    : ($adaProxy
                        ? $program->outcomes->count() . ' outcome, semuanya berproxy'
                        : 'Ada outcome yang belum punya proxy finansial'),
                'tautan' => route('impact.outcome.create', $program),
                'aksi' => 'Tambah',
            ],
            [
                'judul' => 'Adjustment',
                'selesai' => $penyesuaiDitinjau,
                'ket' => ! $adaOutcome
                    ? 'Menunggu outcome'
                    : ($penyesuaiDitinjau
                        ? 'Deadweight, attribution & displacement sudah ditinjau'
                        : 'Masih ada penyesuai bawaan atau displacement yang belum ditentukan'),
                'tautan' => null,
                'aksi' => null,
            ],
            [
                'judul' => 'Hitung SROI',
                'selesai' => $terakhir !== null,
                'ket' => $kesiapan['siap']
                    ? ($terakhir ? 'Terakhir dihitung ' . $terakhir->dihitung_at->translatedFormat('d M Y') : 'Siap dihitung')
                    : $kesiapan['status'],
                'tautan' => null,
                'aksi' => null,
            ],
            [
                'judul' => 'Review & Approval',
                'selesai' => $terakhir?->sudahDisetujui() ?? false,
                'ket' => $terakhir === null
                    ? 'Menunggu perhitungan'
                    : ($terakhir->sudahDisetujui()
                        ? 'Disetujui ' . ($terakhir->penyetuju->name ?? '-')
                        : 'Status: ' . $terakhir->status),
                'tautan' => null,
                'aksi' => null,
            ],
            [
                'judul' => 'Laporan PDF',
                'selesai' => $terakhir?->sudahDisetujui() ?? false,
                'ket' => 'Dapat diunduh kapan saja — memuat status apa adanya',
                'tautan' => route('impact.export-pdf', $program),
                'aksi' => 'Unduh',
            ],
        ];
    }

    public function create()
    {
        Gate::authorize('manage', ImpactProgram::class);

        return view('impact.form', [
            'program' => null,
            'pendampings' => Pendamping::orderBy('nama')->get(),
        ]);
    }

    public function store(Request $request)
    {
        Gate::authorize('manage', ImpactProgram::class);

        $program = ImpactProgram::create($this->validasi($request) + [
            'dibuat_oleh' => Auth::id(),
        ]);

        return redirect()
            ->route('impact.show', $program)
            ->with('success', 'Program dampak berhasil dibuat. Lanjutkan dengan mencatat investasi dan outcome.');
    }

    public function edit(ImpactProgram $impact)
    {
        Gate::authorize('update', $impact);

        return view('impact.form', [
            'program' => $impact,
            'pendampings' => Pendamping::orderBy('nama')->get(),
        ]);
    }

    public function update(Request $request, ImpactProgram $impact)
    {
        Gate::authorize('update', $impact);

        $impact->update($this->validasi($request));

        return redirect()
            ->route('impact.show', $impact)
            ->with('success', 'Program dampak berhasil diperbarui.');
    }

    public function destroy(ImpactProgram $impact)
    {
        Gate::authorize('delete', $impact);

        $impact->delete();

        return redirect()
            ->route('impact.index')
            ->with('success', 'Program dipindahkan ke Arsip dan bisa dipulihkan.');
    }

    public function restore(int $impact)
    {
        $program = ImpactProgram::withTrashed()->findOrFail($impact);

        Gate::authorize('manage', ImpactProgram::class);

        $program->restore();

        return back()->with('success', 'Program berhasil dipulihkan.');
    }

    /**
     * Export PDF laporan Dampak & SROI.
     *
     * Memakai infrastruktur PDF yang SUDAH ADA (dompdf + pola buildPdf milik
     * ReportController), bukan mesin baru — logo, ukuran kertas, dan format
     * tanggalnya jadi sama dengan seluruh laporan Cahaya Preneur lain.
     *
     * ==================================================================
     * PENJAGA: TIDAK ADA RASIO DI ATAS DATA YANG BELUM CUKUP
     * ==================================================================
     *
     * `$hasil` sengaja dikirim NULL kalau program ini belum pernah dihitung,
     * dan template memakai itu untuk mencetak status "belum cukup data"
     * beserta daftar kekurangannya alih-alih angka apa pun.
     *
     * Ini bukan pengulangan penjagaan di SroiService, melainkan lapisan yang
     * berbeda: service menolak MENGHITUNG tanpa data lengkap, sedangkan di
     * sini yang dijaga adalah apa yang TERCETAK. Dokumen ini dibawa ke
     * pemberi dana; angka yang tercetak di atas kop resmi akan dibaca sebagai
     * angka resmi, berapa pun banyaknya catatan kaki di sekelilingnya.
     */
    public function exportPdf(ImpactProgram $impact)
    {
        // Otorisasi baca yang SAMA dengan halaman programnya — Admin &
        // Pimpinan. Pendamping dan Tim Koperasi tertolak di sini juga, bukan
        // hanya di halaman yang menampilkan tombolnya.
        Gate::authorize('view', $impact);

        $impact->load([
            'pendamping',
            'investments',
            'outcomes.proxy',
            'calculations.penghitung',
            'calculations.penyetuju',
        ]);

        return Pdf::loadView('impact.pdf.sroi', $this->dataPdf($impact))
            ->setPaper('a4', 'portrait')
            ->download('laporan-sroi-' . \Illuminate\Support\Str::slug($impact->nama) . '.pdf');
    }

    /**
     * Isi laporan PDF — dipisah dari pembuatan berkasnya, dan itu ada gunanya.
     *
     * Keluaran dompdf adalah PDF terkompresi: teks di dalamnya tidak bisa
     * dibaca dari byte mentahnya. Kalau penyusunan isi menyatu dengan
     * pembuatan berkas, satu-satunya yang bisa diuji otomatis adalah "berkasnya
     * jadi" — dan berkas yang jadi tapi memuat rasio palsu justru jauh lebih
     * berbahaya daripada berkas yang gagal dibuat. Dengan dipisah, isi
     * laporannya bisa diperiksa sungguhan, termasuk penjaga "belum cukup data".
     *
     * Sengaja public: dipakai test untuk merender Blade-nya langsung.
     */
    public function dataPdf(ImpactProgram $impact): array
    {
        $kesiapan = $this->sroi->periksaKesiapan($impact);

        return [
            'program' => $impact,
            'kesiapan' => $kesiapan,
            'rantai' => $this->rantaiDampak($impact, $kesiapan),
            // NULL = belum pernah dihitung -> template tidak mencetak rasio.
            'hasil' => $impact->calculations()->first(),
            // Disusutkan lebih dulu — berkas aslinya tidak disentuh.
            // Logo laporan tercatat 2535 x 461 px untuk dicetak setinggi 44 px.
            'logoPath' => app(GambarCetak::class)->untukCetak(public_path('images/logo-laporan.png')),
            'judulLaporan' => 'Laporan Dampak & SROI',
            'periodeLabel' => $impact->periodeLabel(),
            'filterRingkasan' => 'Program: ' . $impact->nama,
            'tanggalCetak' => now()->translatedFormat('d F Y H:i') . ' WIB',
        ];
    }

    // ==================================================================
    // PERHITUNGAN & PERSETUJUAN
    // ==================================================================

    /**
     * Jalankan perhitungan SROI.
     *
     * Kalau datanya belum cukup, SroiService melempar dan yang ditampilkan
     * adalah daftar kekurangannya — BUKAN angka perkiraan. Ini sengaja
     * dibiarkan gagal dengan keras daripada menghasilkan rasio yang tidak
     * bisa dipertanggungjawabkan.
     */
    public function hitung(ImpactProgram $impact)
    {
        Gate::authorize('hitung', $impact);

        try {
            $hasil = $this->sroi->hitung($impact);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        \App\Models\ActivityLog::record(
            'sroi_calculated',
            'ImpactProgram',
            $impact->id,
            'Menghitung SROI program "' . $impact->nama . '" — rasio '
                . ($hasil->rasio === null ? 'tidak tersedia' : number_format($hasil->rasio, 4, ',', '.')),
        );

        return redirect()
            ->route('impact.show', $impact)
            ->with('success', 'SROI berhasil dihitung. Status: ' . $hasil->status . '.');
    }

    /**
     * Setujui hasil perhitungan sebagai Final.
     *
     * Dua hal terjadi sekaligus, dan keduanya tidak bisa dibatalkan lewat
     * antarmuka: perhitungannya ditandai Final, dan programnya ikut terkunci
     * dari perubahan. Itulah arti "sudah dilaporkan".
     */
    public function setujui(Request $request, ImpactProgram $impact, SroiCalculation $perhitungan)
    {
        Gate::authorize('setujui', $impact);

        abort_unless($perhitungan->impact_program_id === $impact->id, 404);

        $data = $request->validate([
            'catatan_review' => ['nullable', 'string', 'max:2000'],
        ]);

        $perhitungan->update([
            'status' => SroiCalculation::STATUS_FINAL,
            'disetujui_oleh' => Auth::id(),
            'disetujui_at' => now(),
            'catatan_review' => $data['catatan_review'] ?? null,
        ]);

        $impact->update(['status' => ImpactProgram::STATUS_FINAL]);

        \App\Models\ActivityLog::record(
            'impact_updated',
            'ImpactProgram',
            $impact->id,
            'Menyetujui hasil SROI program "' . $impact->nama . '" sebagai Final',
        );

        return back()->with('success', 'Hasil SROI disetujui sebagai Final. Program ini kini terkunci dari perubahan.');
    }

    // ==================================================================

    /**
     * RANTAI DAMPAK — Input, Activity, Output, Outcome.
     *
     * Seluruhnya DITURUNKAN dari data operasional yang sudah ada: investasi
     * yang dicatat, jumlah pendampingan & pelatihan yang benar-benar terjadi,
     * dan perubahan baseline-endline. Tidak ada satu pun angka di sini yang
     * diminta diinput ulang oleh petugas — itu memang inti perancangannya:
     * petugas lapangan bekerja seperti biasa, sistem yang menyusun dampaknya.
     */
    private function rantaiDampak(ImpactProgram $program, array $kesiapan): array
    {
        $pesertaIds = $program->peserta()->pluck('id');

        return [
            'input' => [
                'Total investasi' => rupiah($kesiapan['total_investasi']),
                'Pos investasi tercatat' => $program->investments->count() . ' pos',
                'Investasi in-kind' => rupiah((int) $program->investments
                    ->where('jenis', \App\Models\ProgramInvestment::JENIS_IN_KIND)->sum('nominal')),
            ],
            'activity' => [
                'Pendampingan' => \App\Models\PendampinganRecord::whereIn('participant_id', $pesertaIds)
                    ->whereBetween('tanggal', [$program->periode_mulai, $program->periode_selesai])
                    ->count() . ' kegiatan',
                'Pelatihan' => \App\Models\TrainingRecord::whereIn('participant_id', $pesertaIds)
                    ->whereBetween('tanggal', [$program->periode_mulai, $program->periode_selesai])
                    ->count() . ' kegiatan',
            ],
            'output' => [
                'Peserta dalam program' => $pesertaIds->count() . ' peserta',
                'Peserta terukur (baseline + endline)' => $kesiapan['rekap_perubahan']['jumlah_siap_dibandingkan'] . ' peserta',
                'Legalitas terbit dalam periode' => \App\Models\LegalitasRecord::whereIn('participant_id', $pesertaIds)
                    ->count() . ' dokumen',
            ],
            // Outcome sengaja dibiarkan sebagai rekap indikator apa adanya —
            // penilaian "berhasil / tidak" bukan urusan tabel ringkasan.
            'outcome' => $kesiapan['rekap_perubahan']['indikator'],
        ];
    }

    private function validasi(Request $request): array
    {
        return $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'periode_mulai' => ['required', 'date'],
            'periode_selesai' => ['required', 'date', 'after_or_equal:periode_mulai'],
            'deskripsi' => ['nullable', 'string'],
            'pendamping_id' => ['nullable', 'exists:pendampings,id'],
            'status' => ['required', Rule::in(ImpactProgram::statusYangSah())],
            'catatan' => ['nullable', 'string'],
        ], [
            'nama.required' => 'Nama program wajib diisi.',
            'periode_mulai.required' => 'Periode mulai wajib diisi.',
            'periode_selesai.after_or_equal' => 'Periode selesai tidak boleh sebelum periode mulai.',
        ]);
    }
}
