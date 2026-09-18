<?php

namespace App\Http\Controllers;

use App\Models\LegalitasRecord;
use App\Models\OmzetReport;
use App\Models\Participant;
use App\Models\Pendamping;
use App\Models\PendampinganRecord;
use App\Models\TrainingRecord;
use App\Services\PerbandinganService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Services\GambarCetak;

class ReportController extends Controller
{
    public function index()
    {
        // Halaman ini cuma daftar tautan, TAPI menu bukan batas keamanan:
        // sebelumnya siapa pun yang sudah login bisa membukanya lewat URL
        // langsung, walaupun menunya tidak pernah ditampilkan untuknya.
        //
        // Aturannya disamakan PERSIS dengan gerbang di scopeParticipant()
        // yang dipakai seluruh sub-laporan: Admin, atau Pendamping yang
        // benar-benar tertaut ke data Pendamping. Pimpinan memang bukan
        // pengguna modul laporan Cahaya Preneur - sidebar-nya pun tidak
        // memuat menu ini.
        //
        // scopeParticipant() SENGAJA tidak diubah. Kesamaan kedua aturan
        // dijaga oleh test, supaya kalau salah satunya bergeser ketahuan.
        $user = Auth::user();

        abort_unless(
            $user->isAdmin() || ($user->isPendamping() && $user->pendamping_id),
            403
        );

        return view('laporan.index');
    }

    // ==========================================================
    // LAPORAN KINERJA PENDAMPING
    //
    // Satu-satunya kategori laporan yang belum ada: seluruh laporan lain
    // memandang program dari sisi PESERTA, sedangkan yang ini dari sisi
    // PENDAMPING — berapa binaan yang dipegang, dan berapa banyak yang
    // benar-benar dikerjakan dalam periode tertentu.
    //
    // OTORISASI: Admin melihat seluruh pendamping. Pendamping hanya melihat
    // BARISNYA SENDIRI — bukan supaya angkanya tersembunyi, tapi karena
    // membandingkan kinerja antar pendamping adalah kewenangan Admin.
    // ==========================================================

    public function pendamping(Request $request)
    {
        $data = $this->dataPendamping($request);

        return view('laporan.pendamping', $data);
    }

    public function pendampingExport(Request $request): StreamedResponse
    {
        $data = $this->dataPendamping($request);

        return $this->csvResponse('laporan-kinerja-pendamping.csv', [
            'Pendamping', 'Status', 'Total Binaan', 'Binaan Aktif',
            'Pendampingan', 'Kunjungan Usaha', 'Konsultasi', 'Evaluasi', 'Monitoring',
            'Follow Up Dijadwalkan',
        ], collect($data['baris'])->map(fn ($b) => [
            $b['pendamping']->nama,
            $b['pendamping']->status,
            $b['total_binaan'],
            $b['binaan_aktif'],
            $b['pendampingan'],
            $b['kunjungan'],
            $b['konsultasi'],
            $b['evaluasi'],
            $b['monitoring'],
            $b['follow_up'],
        ]));
    }

    public function pendampingExportPdf(Request $request)
    {
        $data = $this->dataPendamping($request);

        $filterRingkasan = $this->filterSummary([
            'pendamping_id' => 'Pendamping',
            'dari' => 'Dari Tanggal',
            'sampai' => 'Sampai Tanggal',
        ], $request, [
            'pendamping_id' => fn ($id) => optional(Pendamping::find($id))->nama,
        ]);

        // periodeLabel WAJIB — dipakai kop surat bersama di
        // laporan/pdf/_header.blade.php. Tanpa itu render PDF-nya gagal.
        $periodeLabel = ($request->filled('dari') || $request->filled('sampai'))
            ? trim(($request->input('dari') ?: '...') . ' s/d ' . ($request->input('sampai') ?: '...'))
            : 'Seluruh Periode';

        return $this->pdfResponse('laporan.pdf.pendamping', [
            'baris' => $data['baris'],
            'ringkasan' => $data['ringkasan'],
            'judulLaporan' => 'Laporan Kinerja Pendamping',
            'periodeLabel' => $periodeLabel,
            'filterRingkasan' => $filterRingkasan,
        ], 'laporan-kinerja-pendamping.pdf');
    }

    /**
     * Data laporan kinerja pendamping.
     *
     * Dihitung dengan DUA query agregat (binaan & aktivitas), bukan satu
     * query per pendamping — laporan adalah tempat pola N+1 paling mudah
     * lolos tanpa terasa saat datanya masih sedikit.
     */
    private function dataPendamping(Request $request): array
    {
        $user = Auth::user();

        abort_unless(
            $user->isAdmin() || ($user->isPendamping() && $user->pendamping_id),
            403
        );

        $pendampingQuery = Pendamping::withCount([
            'participants',
            'participants as binaan_aktif_count' => fn ($q) => $q->whereIn('status', Participant::STATUS_BERJALAN),
        ]);

        // Pendamping hanya melihat barisnya sendiri; filter ?pendamping_id
        // dari URL DIABAIKAN untuknya, supaya mengetik id orang lain tidak
        // membuka kinerja pendamping lain.
        if ($user->isAdmin()) {
            if ($request->filled('pendamping_id')) {
                $pendampingQuery->whereKey($request->integer('pendamping_id'));
            }
        } else {
            $pendampingQuery->whereKey($user->pendamping_id);
        }

        $pendampings = $pendampingQuery->orderBy('nama')->get();

        // Aktivitas dalam periode, dipecah per jenis kegiatan.
        $aktivitasQuery = PendampinganRecord::query()
            ->whereIn('pendamping_id', $pendampings->pluck('id'));

        if ($request->filled('dari')) {
            $aktivitasQuery->whereDate('tanggal', '>=', $request->date('dari'));
        }
        if ($request->filled('sampai')) {
            $aktivitasQuery->whereDate('tanggal', '<=', $request->date('sampai'));
        }

        $aktivitas = (clone $aktivitasQuery)
            ->selectRaw('pendamping_id, jenis_kegiatan, COUNT(*) as jumlah')
            ->groupBy('pendamping_id', 'jenis_kegiatan')
            ->get()
            ->groupBy('pendamping_id');

        $followUp = (clone $aktivitasQuery)
            ->whereNotNull('tanggal_follow_up')
            ->selectRaw('pendamping_id, COUNT(*) as jumlah')
            ->groupBy('pendamping_id')
            ->pluck('jumlah', 'pendamping_id');

        $baris = $pendampings->map(function ($p) use ($aktivitas, $followUp) {
            $milik = $aktivitas->get($p->id, collect());
            $per = fn (string $jenis) => (int) ($milik->firstWhere('jenis_kegiatan', $jenis)->jumlah ?? 0);

            return [
                'pendamping' => $p,
                'total_binaan' => $p->participants_count,
                'binaan_aktif' => $p->binaan_aktif_count,
                'pendampingan' => (int) $milik->sum('jumlah'),
                'kunjungan' => $per(PendampinganRecord::KUNJUNGAN_USAHA),
                'konsultasi' => $per(PendampinganRecord::KONSULTASI),
                'evaluasi' => $per(PendampinganRecord::EVALUASI),
                'monitoring' => $per(PendampinganRecord::MONITORING),
                'follow_up' => (int) ($followUp[$p->id] ?? 0),
            ];
        })->sortByDesc('pendampingan')->values();

        return [
            'baris' => $baris,
            'pendampings' => $user->isAdmin() ? Pendamping::orderBy('nama')->get() : collect(),
            'isAdmin' => $user->isAdmin(),
            'filters' => $request->only(['pendamping_id', 'dari', 'sampai']),
            'ringkasan' => [
                'jumlah_pendamping' => $baris->count(),
                'total_binaan' => $baris->sum('total_binaan'),
                'total_aktivitas' => $baris->sum('pendampingan'),
                'tanpa_aktivitas' => $baris->where('pendampingan', 0)->count(),
            ],
        ];
    }

    // ==========================================================
    // 1. LAPORAN PESERTA
    // ==========================================================

    public function peserta(Request $request)
    {
        $query = $this->pesertaQuery($request);

        return view('laporan.peserta', [
            'participants' => $query->orderBy('nama_peserta')->paginate(15)->withQueryString(),
            'pendampings' => Pendamping::orderBy('nama')->get(),
            'statusOptions' => Participant::STATUS_OPTIONS,
            'kategoriOptions' => Participant::KATEGORI_OPTIONS,
            'filters' => $request->only(['pendamping_id', 'status', 'kategori_usaha']),
        ]);
    }

    public function pesertaExport(Request $request): StreamedResponse
    {
        $rows = $this->pesertaQuery($request)->orderBy('nama_peserta')->get();

        return $this->csvResponse('laporan-peserta.csv', [
            'Nama Peserta', 'Nama Usaha', 'Kategori Usaha', 'Pendamping', 'Status', 'Tanggal Masuk Program',
        ], $rows->map(fn (Participant $p) => [
            $p->nama_peserta,
            $p->nama_usaha,
            $p->kategori_usaha,
            $p->pendamping->nama ?? '-',
            $p->status,
            $p->tanggal_mulai_program->format('d-m-Y'),
        ]));
    }

    public function pesertaExportPdf(Request $request)
    {
        $rows = $this->pesertaQuery($request)->orderBy('nama_peserta')->get();

        $filterRingkasan = $this->filterSummary([
            'pendamping_id' => 'Pendamping',
            'status' => 'Status',
            'kategori_usaha' => 'Kategori',
        ], $request, [
            'pendamping_id' => fn ($id) => optional(Pendamping::find($id))->nama,
        ]);

        return $this->pdfResponse('laporan.pdf.peserta', [
            'rows' => $rows,
            'judulLaporan' => 'Laporan Peserta',
            'periodeLabel' => 'Seluruh Periode',
            'filterRingkasan' => $filterRingkasan,
        ], 'laporan-peserta.pdf');
    }

    private function pesertaQuery(Request $request)
    {
        $query = Participant::with('pendamping');
        $this->scopeParticipant($query);

        if ($request->filled('pendamping_id')) {
            $query->where('pendamping_id', $request->integer('pendamping_id'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }
        if ($request->filled('kategori_usaha')) {
            $query->where('kategori_usaha', $request->string('kategori_usaha'));
        }

        return $query;
    }

    // ==========================================================
    // LAPORAN PERKEMBANGAN USAHA (Baseline -> Endline)
    //
    // Satu-satunya laporan yang isinya SELISIH, bukan daftar kejadian. Karena
    // itu ia punya satu kewajiban yang tidak dimiliki laporan lain: menyebut
    // BERAPA PESERTA yang benar-benar bisa dibandingkan, di samping berapa
    // yang ada. Tanpa itu, total kenaikan omzet dari 3 peserta akan terbaca
    // sebagai prestasi seluruh program.
    //
    // Angkanya diambil dari PerbandinganService — sumber yang sama dengan
    // halaman peserta dan mesin SROI, supaya ketiganya tidak mungkin
    // menampilkan perubahan yang berbeda untuk usaha yang sama.
    // ==========================================================

    public function perkembangan(Request $request, PerbandinganService $perbandingan)
    {
        $peserta = $this->perkembanganQuery($request)->get();
        $rekap = $perbandingan->rekap($peserta);

        return view('laporan.perkembangan', [
            'rekap' => $rekap,
            'baris' => $this->barisPerkembangan($peserta, $perbandingan),
            'pendampings' => Pendamping::orderBy('nama')->get(),
            'filters' => $request->only(['pendamping_id', 'status']),
        ]);
    }

    public function perkembanganExport(Request $request, PerbandinganService $perbandingan): StreamedResponse
    {
        $baris = $this->barisPerkembangan($this->perkembanganQuery($request)->get(), $perbandingan);

        return $this->csvResponse('laporan-perkembangan.csv', [
            'Peserta', 'Usaha', 'Pendamping', 'Titik Akhir',
            'Omzet Awal', 'Omzet Akhir', 'Selisih Omzet',
            'Profit Awal', 'Profit Akhir', 'Selisih Profit',
            'Tenaga Kerja Awal', 'Tenaga Kerja Akhir',
            'Status',
        ], collect($baris)->map(fn (array $b) => [
            $b['nama'], $b['usaha'], $b['pendamping'], $b['sumber_akhir'] ?? '',
            // Sel KOSONG untuk indikator yang belum punya pasangan — bukan 0.
            // Sel kosong tidak ikut terhitung saat penerima file menjumlahkan
            // kolomnya; 0 ikut, dan membuat rata-ratanya salah.
            $b['omzet']['sebelum'] ?? '', $b['omzet']['sesudah'] ?? '', $b['omzet']['selisih'] ?? '',
            $b['profit']['sebelum'] ?? '', $b['profit']['sesudah'] ?? '', $b['profit']['selisih'] ?? '',
            $b['tenaga_kerja']['sebelum'] ?? '', $b['tenaga_kerja']['sesudah'] ?? '',
            $b['siap'] ? 'Terukur' : 'Belum bisa dibandingkan',
        ]));
    }

    private function perkembanganQuery(Request $request)
    {
        $query = Participant::with(['pendamping', 'measurements']);
        $this->scopeParticipant($query);

        if ($request->filled('pendamping_id')) {
            $query->where('pendamping_id', $request->integer('pendamping_id'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        return $query->orderBy('nama_peserta');
    }

    /**
     * Satu baris per peserta — TERMASUK peserta yang belum bisa dibandingkan.
     *
     * Peserta tanpa baseline sengaja tetap muncul dengan kolom kosong dan
     * status "Belum bisa dibandingkan". Menyembunyikannya akan membuat
     * laporan ini hanya memuat peserta yang datanya bagus, dan pembacanya
     * kehilangan satu-satunya petunjuk bahwa pendataan masih berlubang.
     */
    private function barisPerkembangan($peserta, PerbandinganService $perbandingan): array
    {
        return $peserta->map(function (Participant $p) use ($perbandingan) {
            $rapor = $perbandingan->untukPeserta($p);

            return [
                'peserta' => $p,
                'nama' => $p->nama_peserta,
                'usaha' => $p->nama_usaha,
                'pendamping' => $p->pendamping->nama ?? '-',
                'siap' => $rapor['siap_dibandingkan'],
                'sumber_akhir' => $rapor['sumber_akhir'],
                'omzet' => $rapor['perubahan']['omzet_bulanan'] ?? null,
                'profit' => $rapor['perubahan']['profit_bulanan'] ?? null,
                'tenaga_kerja' => $rapor['perubahan']['jumlah_tenaga_kerja'] ?? null,
                'legalitas' => $rapor['perubahan_status']['punya_legalitas'] ?? null,
            ];
        })->all();
    }

    // ==========================================================
    // 2. LAPORAN OMZET
    // ==========================================================

    public function omzet(Request $request)
    {
        $query = $this->omzetQuery($request);

        // ============================================================
        // AGREGAT DIHITUNG DI DATABASE, BUKAN DI MEMORI
        //
        // Daftarnya sudah ter-paginasi 15 baris; yang dulu mahal hanya
        // ringkasan di atasnya, yang memuat SELURUH laporan hasil filter
        // beserta relasinya semata-mata untuk dijumlahkan.
        //
        // Terukur pada volume lima tahun pemakaian (12.000 laporan):
        // halaman ini memakan 8,8 MB memori. Pada 271 laporan hari ini sama
        // sekali tidak terasa — dan justru laporan inilah yang dipakai
        // menyusun pertanggungjawaban ke pemberi dana, saat datanya sudah
        // paling banyak.
        // ============================================================
        $jumlahLaporan = (clone $query)->count();
        $totalOmzet = (int) (clone $query)->sum('omzet');

        // Agregat laba dihitung HANYA dari laporan yang HPP-nya dilaporkan,
        // dan jumlah laporannya ikut dikirim ke tampilan. Menjumlahkan
        // seluruhnya sambil menganggap HPP kosong = 0 akan melahirkan "laba"
        // sebesar omzet penuh untuk laporan lama — angka yang salah dan
        // terlihat wajar. Aturan yang sama dipakai Monitoring Omzet dan
        // Dashboard, supaya ketiganya tidak pernah menampilkan angka berbeda.
        // Hanya baris yang PUNYA hpp yang dimuat — sisanya tidak pernah ikut
        // dijumlahkan. punyaDataLaba() tetap dipakai sebagai penyaring akhir
        // supaya definisinya tetap satu, bukan diduplikasi jadi kondisi SQL
        // yang bisa menyimpang darinya.
        $berlaba = (clone $query)->whereNotNull('hpp')->get()
            ->filter(fn (OmzetReport $r) => $r->punyaDataLaba());

        return view('laporan.omzet', [
            'reports' => $query->orderByDesc('periode_tahun')->orderByDesc('periode_bulan')->orderByDesc('periode_urut')->paginate(15)->withQueryString(),
            'totalOmzet' => $totalOmzet,
            // Rata-rata dari NOL laporan bukan nol — ia tidak ada.
            //
            // "Rp 0" terbaca sebagai fakta: peserta ini rata-rata tidak
            // berpenghasilan. Padahal yang benar cuma "belum ada laporan pada
            // filter ini" — dan keadaan itu gampang sekali dicapai, cukup
            // menyaring bulan yang belum terisi.
            //
            // Mengikuti pola yang sudah dipakai KpiService::ringkasan():
            // null, lalu ditampilkan sebagai "—".
            'rataRata' => $jumlahLaporan > 0 ? intdiv($totalOmzet, $jumlahLaporan) : null,
            'jumlahLaporan' => $jumlahLaporan,
            'jumlahBerlaba' => $berlaba->count(),
            'totalHpp' => (int) $berlaba->sum('hpp'),
            'totalProfit' => (int) $berlaba->sum(fn (OmzetReport $r) => $r->profitBersih()),
            'pendampings' => Pendamping::orderBy('nama')->get(),
            'participants' => $this->pesertaListForFilter(),
            'bulanOptions' => OmzetReport::BULAN,
            'filters' => $request->only(['bulan', 'tahun', 'pendamping_id', 'participant_id']),
        ]);
    }

    public function omzetExport(Request $request): StreamedResponse
    {
        $rows = $this->omzetQuery($request)->orderByDesc('periode_tahun')->orderByDesc('periode_bulan')->orderByDesc('periode_urut')->get();

        return $this->csvResponse('laporan-omzet.csv', [
            'Peserta', 'Usaha', 'Periode', 'Mode', 'Mulai', 'Selesai',
            'Omzet', 'HPP', 'Biaya Operasional', 'Laba Kotor', 'Profit Bersih', 'Bukti', 'Catatan',
        ], $rows->map(fn (OmzetReport $r) => [
            $r->participantTermasukArsip?->nama_peserta ?? '-',
            $r->participantTermasukArsip?->nama_usaha ?? '-',
            $r->periodeLabel(),
            // Mode & rentang ikut diekspor supaya penerima file bisa
            // membedakan baris harian dari baris bulanan. Tanpa kolom ini,
            // 30 baris September terlihat seperti 30 bulan yang berbeda.
            $r->jenis_periode,
            $r->tanggal_mulai?->toDateString() ?? '',
            $r->tanggal_selesai?->toDateString() ?? '',
            $r->omzet,
            // Kolom laba dibiarkan KOSONG, bukan diisi 0, kalau HPP-nya belum
            // dilaporkan. Sel kosong di Excel berarti "belum ada data" dan
            // tidak ikut terhitung saat penerima file menjumlahkan kolomnya —
            // sedangkan 0 ikut terhitung dan membuat rata-ratanya salah.
            $r->hpp ?? '',
            $r->biaya_operasional ?? '',
            $r->labaKotor() ?? '',
            $r->profitBersih() ?? '',
            $r->punyaBukti() ? $r->namaBukti() : '',
            $r->catatan ?: '',
        ]));
    }

    public function omzetExportPdf(Request $request)
    {
        $rows = $this->omzetQuery($request)->orderByDesc('periode_tahun')->orderByDesc('periode_bulan')->orderByDesc('periode_urut')->get();

        $filterRingkasan = $this->filterSummary([
            'bulan' => 'Bulan',
            'tahun' => 'Tahun',
            'pendamping_id' => 'Pendamping',
            'participant_id' => 'Peserta',
        ], $request, [
            'bulan' => fn ($b) => OmzetReport::BULAN[(int) $b] ?? $b,
            'pendamping_id' => fn ($id) => optional(Pendamping::find($id))->nama,
            'participant_id' => fn ($id) => optional(Participant::find($id))->nama_peserta,
        ]);

        $periodeLabel = $request->filled('bulan') || $request->filled('tahun')
            ? trim((OmzetReport::BULAN[(int) $request->input('bulan')] ?? '') . ' ' . $request->input('tahun'))
            : 'Seluruh Periode';

        return $this->pdfResponse('laporan.pdf.omzet', [
            'rows' => $rows,
            'totalOmzet' => $rows->sum('omzet'),
            'rataRata' => $rows->count() > 0 ? intdiv($rows->sum('omzet'), $rows->count()) : null,
            'judulLaporan' => 'Laporan Omzet',
            'periodeLabel' => $periodeLabel ?: 'Seluruh Periode',
            'filterRingkasan' => $filterRingkasan,
        ], 'laporan-omzet.pdf');
    }

    /**
     * CATATAN ARSIP — berlaku untuk seluruh laporan peserta di berkas ini.
     *
     * Laporan membaca tabel catatannya LANGSUNG (omzet_reports,
     * pendampingan_records, dan seterusnya), bukan lewat daftar peserta
     * aktif. Artinya catatan milik peserta yang sudah diarsipkan — peserta
     * yang lulus program, misalnya — TETAP ikut terhitung, dan memang
     * seharusnya begitu: omzet yang mereka laporkan tahun ini bagian dari
     * capaian tahun ini.
     *
     * Yang keliru sebelumnya adalah namanya: relasi participant() biasa
     * berhenti menemukan peserta terarsip, jadi baris-baris itu tercetak
     * dengan "-" di kolom Peserta. Laporan untuk pemberi dana yang memuat
     * baris tanpa nama tidak bisa dipertanggungjawabkan, dan kesalahan itu
     * tidak menimbulkan error apa pun — hanya tanda hubung yang mudah
     * dikira memang tidak ada datanya.
     */
    private function omzetQuery(Request $request)
    {
        $query = OmzetReport::with('participantTermasukArsip.pendamping');
        $this->scopeParticipant($query, 'participant_id');

        if ($request->filled('bulan')) {
            $query->where('periode_bulan', $request->integer('bulan'));
        }
        if ($request->filled('tahun')) {
            $query->where('periode_tahun', $request->integer('tahun'));
        }
        if ($request->filled('pendamping_id')) {
            $pendampingId = $request->integer('pendamping_id');
            $query->whereHas('participant', fn ($q) => $q->where('pendamping_id', $pendampingId));
        }
        if ($request->filled('participant_id')) {
            $query->where('participant_id', $request->integer('participant_id'));
        }

        return $query;
    }

    // ==========================================================
    // 3. LAPORAN PENDAMPINGAN
    // ==========================================================

    public function pendampingan(Request $request)
    {
        $query = $this->pendampinganQuery($request);
        $total = (clone $query)->count();

        return view('laporan.pendampingan', [
            'records' => $query->orderByDesc('tanggal')->paginate(15)->withQueryString(),
            'totalKegiatan' => $total,
            'pendampings' => Pendamping::orderBy('nama')->get(),
            'participants' => $this->pesertaListForFilter(),
            'jenisOptions' => PendampinganRecord::JENIS_KEGIATAN,
            'filters' => $request->only(['pendamping_id', 'participant_id', 'jenis_kegiatan', 'dari', 'sampai']),
        ]);
    }

    public function pendampinganExport(Request $request): StreamedResponse
    {
        $rows = $this->pendampinganQuery($request)->orderByDesc('tanggal')->get();

        return $this->csvResponse('laporan-pendampingan.csv', [
            'Tanggal', 'Peserta', 'Pendamping', 'Jenis Kegiatan', 'Materi', 'Catatan', 'Kendala', 'Tindak Lanjut',
        ], $rows->map(fn (PendampinganRecord $r) => [
            $r->tanggal->format('d-m-Y'),
            $r->participantTermasukArsip?->nama_peserta ?? '-',
            $r->pendamping->nama ?? '-',
            $r->jenis_kegiatan,
            $r->materi,
            $r->catatan ?: '',
            $r->kendala ?: '',
            $r->tindak_lanjut ?: '',
        ]));
    }

    public function pendampinganExportPdf(Request $request)
    {
        $rows = $this->pendampinganQuery($request)->orderByDesc('tanggal')->get();

        $filterRingkasan = $this->filterSummary([
            'pendamping_id' => 'Pendamping',
            'participant_id' => 'Peserta',
            'jenis_kegiatan' => 'Jenis Kegiatan',
            'dari' => 'Dari Tanggal',
            'sampai' => 'Sampai Tanggal',
        ], $request, [
            'pendamping_id' => fn ($id) => optional(Pendamping::find($id))->nama,
            'participant_id' => fn ($id) => optional(Participant::find($id))->nama_peserta,
        ]);

        $periodeLabel = ($request->filled('dari') || $request->filled('sampai'))
            ? ($request->input('dari', '...') . ' s/d ' . $request->input('sampai', '...'))
            : 'Seluruh Periode';

        return $this->pdfResponse('laporan.pdf.pendampingan', [
            'rows' => $rows,
            'totalKegiatan' => $rows->count(),
            'judulLaporan' => 'Laporan Pendampingan',
            'periodeLabel' => $periodeLabel,
            'filterRingkasan' => $filterRingkasan,
        ], 'laporan-pendampingan.pdf');
    }

    private function pendampinganQuery(Request $request)
    {
        $query = PendampinganRecord::with(['participantTermasukArsip', 'pendamping']);
        $this->scopeParticipant($query, 'participant_id');

        if ($request->filled('pendamping_id')) {
            $query->where('pendamping_id', $request->integer('pendamping_id'));
        }
        if ($request->filled('participant_id')) {
            $query->where('participant_id', $request->integer('participant_id'));
        }
        if ($request->filled('jenis_kegiatan')) {
            $query->where('jenis_kegiatan', $request->string('jenis_kegiatan'));
        }
        if ($request->filled('dari')) {
            $query->whereDate('tanggal', '>=', $request->date('dari'));
        }
        if ($request->filled('sampai')) {
            $query->whereDate('tanggal', '<=', $request->date('sampai'));
        }

        return $query;
    }

    // ==========================================================
    // 4. LAPORAN PELATIHAN
    // ==========================================================

    public function pelatihan(Request $request)
    {
        $query = $this->pelatihanQuery($request);

        return view('laporan.pelatihan', [
            'records' => $query->orderByDesc('tanggal')->paginate(15)->withQueryString(),
            'participants' => $this->pesertaListForFilter(),
            'statusOptions' => TrainingRecord::STATUS_OPTIONS,
            'filters' => $request->only(['participant_id', 'status', 'dari', 'sampai']),
        ]);
    }

    public function pelatihanExport(Request $request): StreamedResponse
    {
        $rows = $this->pelatihanQuery($request)->orderByDesc('tanggal')->get();

        return $this->csvResponse('laporan-pelatihan.csv', [
            'Nama Pelatihan', 'Peserta', 'Tanggal', 'Penyelenggara', 'Status',
        ], $rows->map(fn (TrainingRecord $r) => [
            $r->nama_pelatihan,
            $r->participantTermasukArsip?->nama_peserta ?? '-',
            $r->tanggal->format('d-m-Y'),
            $r->penyelenggara ?: '',
            $r->status,
        ]));
    }

    public function pelatihanExportPdf(Request $request)
    {
        $rows = $this->pelatihanQuery($request)->orderByDesc('tanggal')->get();

        $filterRingkasan = $this->filterSummary([
            'participant_id' => 'Peserta',
            'status' => 'Status',
            'dari' => 'Dari Tanggal',
            'sampai' => 'Sampai Tanggal',
        ], $request, [
            'participant_id' => fn ($id) => optional(Participant::find($id))->nama_peserta,
        ]);

        $periodeLabel = ($request->filled('dari') || $request->filled('sampai'))
            ? ($request->input('dari', '...') . ' s/d ' . $request->input('sampai', '...'))
            : 'Seluruh Periode';

        return $this->pdfResponse('laporan.pdf.pelatihan', [
            'rows' => $rows,
            'judulLaporan' => 'Laporan Pelatihan',
            'periodeLabel' => $periodeLabel,
            'filterRingkasan' => $filterRingkasan,
        ], 'laporan-pelatihan.pdf');
    }

    private function pelatihanQuery(Request $request)
    {
        $query = TrainingRecord::with('participantTermasukArsip');
        $this->scopeParticipant($query, 'participant_id');

        if ($request->filled('participant_id')) {
            $query->where('participant_id', $request->integer('participant_id'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }
        if ($request->filled('dari')) {
            $query->whereDate('tanggal', '>=', $request->date('dari'));
        }
        if ($request->filled('sampai')) {
            $query->whereDate('tanggal', '<=', $request->date('sampai'));
        }

        return $query;
    }

    // ==========================================================
    // 5. LAPORAN LEGALITAS & SERTIFIKASI (DINAMIS)
    // ==========================================================

    public function legalitas(Request $request)
    {
        $query = $this->legalitasQuery($request);

        return view('laporan.legalitas', [
            'records' => $query->orderBy('jenis_dokumen')->paginate(15)->withQueryString(),
            'pendampings' => Pendamping::orderBy('nama')->get(),
            'participants' => $this->pesertaListForFilter(),
            'statusOptions' => LegalitasRecord::STATUS_OPTIONS,
            'jenisList' => LegalitasRecord::query()->select('jenis_dokumen')->distinct()->orderBy('jenis_dokumen')->pluck('jenis_dokumen'),
            'filters' => $request->only(['jenis_dokumen', 'status', 'pendamping_id', 'participant_id']),
        ]);
    }

    public function legalitasExport(Request $request): StreamedResponse
    {
        $rows = $this->legalitasQuery($request)->orderBy('jenis_dokumen')->get();

        return $this->csvResponse('laporan-legalitas.csv', [
            'Peserta', 'Usaha', 'Pendamping', 'Nama Legalitas/Sertifikasi', 'Nomor', 'Status', 'Tanggal Terbit', 'Tanggal Berlaku',
        ], $rows->map(fn (LegalitasRecord $r) => [
            $r->participantTermasukArsip?->nama_peserta ?? '-',
            $r->participantTermasukArsip?->nama_usaha ?? '-',
            $r->participantTermasukArsip?->pendamping->nama ?? '-',
            $r->jenis_dokumen,
            $r->nomor_dokumen ?: '',
            $r->status,
            optional($r->tanggal_terbit)->format('d-m-Y'),
            optional($r->tanggal_berlaku)->format('d-m-Y'),
        ]));
    }

    public function legalitasExportPdf(Request $request)
    {
        $rows = $this->legalitasQuery($request)->orderBy('jenis_dokumen')->get();

        $filterRingkasan = $this->filterSummary([
            'jenis_dokumen' => 'Jenis',
            'status' => 'Status',
            'pendamping_id' => 'Pendamping',
            'participant_id' => 'Peserta',
        ], $request, [
            'pendamping_id' => fn ($id) => optional(Pendamping::find($id))->nama,
            'participant_id' => fn ($id) => optional(Participant::find($id))->nama_peserta,
        ]);

        return $this->pdfResponse('laporan.pdf.legalitas', [
            'rows' => $rows,
            'judulLaporan' => 'Laporan Legalitas & Sertifikasi',
            'periodeLabel' => 'Seluruh Periode',
            'filterRingkasan' => $filterRingkasan,
        ], 'laporan-legalitas.pdf');
    }

    private function legalitasQuery(Request $request)
    {
        $query = LegalitasRecord::with('participantTermasukArsip.pendamping');
        $this->scopeParticipant($query, 'participant_id');

        if ($request->filled('jenis_dokumen')) {
            $query->where('jenis_dokumen', $request->string('jenis_dokumen'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }
        if ($request->filled('pendamping_id')) {
            $pendampingId = $request->integer('pendamping_id');
            $query->whereHas('participant', fn ($q) => $q->where('pendamping_id', $pendampingId));
        }
        if ($request->filled('participant_id')) {
            $query->where('participant_id', $request->integer('participant_id'));
        }

        return $query;
    }

    // ==========================================================
    // 6. REKAP PROGRAM — khusus Admin
    // ==========================================================

    public function rekap()
    {
        abort_unless(Auth::user()->isAdmin(), 403);

        $totalOmzet = (int) OmzetReport::sum('omzet');
        $jumlahLaporOmzet = OmzetReport::count();

        $legalitasPerJenis = LegalitasRecord::query()
            ->selectRaw('jenis_dokumen, count(*) as jumlah')
            ->groupBy('jenis_dokumen')
            ->orderByDesc('jumlah')
            ->get();

        return view('laporan.rekap', [
            'totalPeserta' => Participant::count(),
            'totalPendamping' => Pendamping::count(),
            'totalOmzet' => $totalOmzet,
            'rataRataOmzet' => $jumlahLaporOmzet > 0 ? intdiv($totalOmzet, $jumlahLaporOmzet) : null,
            'totalPendampingan' => PendampinganRecord::count(),
            'totalPelatihan' => TrainingRecord::count(),
            'legalitasPerJenis' => $legalitasPerJenis,
        ]);
    }

    public function rekapExportPdf()
    {
        abort_unless(Auth::user()->isAdmin(), 403);

        $totalOmzet = (int) OmzetReport::sum('omzet');
        $jumlahLaporOmzet = OmzetReport::count();

        $legalitasPerJenis = LegalitasRecord::query()
            ->selectRaw('jenis_dokumen, count(*) as jumlah')
            ->groupBy('jenis_dokumen')
            ->orderByDesc('jumlah')
            ->get();

        return $this->pdfResponse('laporan.pdf.rekap', [
            'totalPeserta' => Participant::count(),
            'totalPendamping' => Pendamping::count(),
            'totalOmzet' => $totalOmzet,
            'rataRataOmzet' => $jumlahLaporOmzet > 0 ? intdiv($totalOmzet, $jumlahLaporOmzet) : null,
            'totalPendampingan' => PendampinganRecord::count(),
            'totalPelatihan' => TrainingRecord::count(),
            'legalitasPerJenis' => $legalitasPerJenis,
            'judulLaporan' => 'Rekap Program Cahaya Preneur',
            'periodeLabel' => 'Sepanjang Program',
            'filterRingkasan' => '',
        ], 'rekap-program.pdf');
    }

    // ==========================================================
    // HELPER
    // ==========================================================

    private function scopeParticipant($query, ?string $participantColumn = null): void
    {
        $user = Auth::user();

        if ($user->isAdmin()) {
            return;
        }

        if (! $user->isPendamping() || ! $user->pendamping_id) {
            abort(403);
        }

        if ($participantColumn === null) {
            $query->where('pendamping_id', $user->pendamping_id);

            return;
        }

        $pendampingId = $user->pendamping_id;
        $query->whereHas('participant', fn ($q) => $q->where('pendamping_id', $pendampingId));
    }

    private function pesertaListForFilter()
    {
        $query = Participant::query();
        $this->scopeParticipant($query);

        return $query->orderBy('nama_peserta')->get();
    }

    private function csvResponse(string $filename, array $header, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($header, $rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $header);
            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Render Blade jadi PDF (ukuran A4) dan langsung download.
     * Logo laporan pakai file KHUSUS (YBM PLN + Cahaya Preneur), TERPISAH
     * dari logo-cahayaos.png (yang dipakai di sidebar/platform).
     */
    /**
     * Bangun object Pdf (belum di-download) — dipakai bareng oleh pdfResponse()
     * (satu laporan) dan downloadAllPdf() (gabung semua jadi ZIP).
     */
    private function buildPdf(string $view, array $data)
    {
        // Disusutkan lebih dulu — berkas aslinya tidak disentuh.
        // Logo laporan tercatat 2535 x 461 px untuk dicetak setinggi 44 px.
        $data['logoPath'] = app(GambarCetak::class)->untukCetak(public_path('images/logo-laporan.png'));
        $data['tanggalCetak'] = now()->translatedFormat('d F Y H:i') . ' WIB';

        return Pdf::loadView($view, $data)->setPaper('a4', 'portrait');
    }

    private function pdfResponse(string $view, array $data, string $filename)
    {
        return $this->buildPdf($view, $data)->download($filename);
    }

    /**
     * Download SEMUA laporan sekaligus — 1 file .zip berisi 6 PDF terpisah.
     * Pakai query TANPA filter (seluruh data), tapi scopeParticipant() tetap
     * berlaku jadi Pendamping cuma dapat data binaannya. Rekap Program cuma
     * disertakan kalau yang download itu Admin.
     */
    public function downloadAllPdf()
    {
        $req = new Request();
        $pdfs = [];

        $pesertaRows = $this->pesertaQuery($req)->orderBy('nama_peserta')->get();
        $pdfs['laporan-peserta.pdf'] = $this->buildPdf('laporan.pdf.peserta', [
            'rows' => $pesertaRows,
            'judulLaporan' => 'Laporan Peserta',
            'periodeLabel' => 'Seluruh Periode',
            'filterRingkasan' => '',
        ]);

        $omzetRows = $this->omzetQuery($req)->orderByDesc('periode_tahun')->orderByDesc('periode_bulan')->orderByDesc('periode_urut')->get();
        $pdfs['laporan-omzet.pdf'] = $this->buildPdf('laporan.pdf.omzet', [
            'rows' => $omzetRows,
            'totalOmzet' => $omzetRows->sum('omzet'),
            'rataRata' => $omzetRows->count() > 0 ? intdiv($omzetRows->sum('omzet'), $omzetRows->count()) : null,
            'judulLaporan' => 'Laporan Omzet',
            'periodeLabel' => 'Seluruh Periode',
            'filterRingkasan' => '',
        ]);

        $pendampinganRows = $this->pendampinganQuery($req)->orderByDesc('tanggal')->get();
        $pdfs['laporan-pendampingan.pdf'] = $this->buildPdf('laporan.pdf.pendampingan', [
            'rows' => $pendampinganRows,
            'totalKegiatan' => $pendampinganRows->count(),
            'judulLaporan' => 'Laporan Pendampingan',
            'periodeLabel' => 'Seluruh Periode',
            'filterRingkasan' => '',
        ]);

        $pelatihanRows = $this->pelatihanQuery($req)->orderByDesc('tanggal')->get();
        $pdfs['laporan-pelatihan.pdf'] = $this->buildPdf('laporan.pdf.pelatihan', [
            'rows' => $pelatihanRows,
            'judulLaporan' => 'Laporan Pelatihan',
            'periodeLabel' => 'Seluruh Periode',
            'filterRingkasan' => '',
        ]);

        $legalitasRows = $this->legalitasQuery($req)->orderBy('jenis_dokumen')->get();
        $pdfs['laporan-legalitas.pdf'] = $this->buildPdf('laporan.pdf.legalitas', [
            'rows' => $legalitasRows,
            'judulLaporan' => 'Laporan Legalitas & Sertifikasi',
            'periodeLabel' => 'Seluruh Periode',
            'filterRingkasan' => '',
        ]);

        if (Auth::user()->isAdmin()) {
            $totalOmzet = (int) OmzetReport::sum('omzet');
            $jumlahLaporOmzet = OmzetReport::count();
            $legalitasPerJenis = LegalitasRecord::query()
                ->selectRaw('jenis_dokumen, count(*) as jumlah')
                ->groupBy('jenis_dokumen')
                ->orderByDesc('jumlah')
                ->get();

            $pdfs['rekap-program.pdf'] = $this->buildPdf('laporan.pdf.rekap', [
                'totalPeserta' => Participant::count(),
                'totalPendamping' => Pendamping::count(),
                'totalOmzet' => $totalOmzet,
                'rataRataOmzet' => $jumlahLaporOmzet > 0 ? intdiv($totalOmzet, $jumlahLaporOmzet) : null,
                'totalPendampingan' => PendampinganRecord::count(),
                'totalPelatihan' => TrainingRecord::count(),
                'legalitasPerJenis' => $legalitasPerJenis,
                'judulLaporan' => 'Rekap Program Cahaya Preneur',
                'periodeLabel' => 'Sepanjang Program',
                'filterRingkasan' => '',
            ]);
        }

        $zipPath = storage_path('app/laporan-lengkap-' . now()->format('YmdHis') . '-' . uniqid() . '.zip');

        $zip = new \ZipArchive();
        $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        foreach ($pdfs as $filename => $pdf) {
            $zip->addFromString($filename, $pdf->output());
        }
        $zip->close();

        return response()
            ->download($zipPath, 'Laporan-Cahaya-Preneur-' . now()->format('Y-m-d') . '.zip')
            ->deleteFileAfterSend(true);
    }

    private function filterSummary(array $labels, Request $request, array $resolve = []): string
    {
        $parts = [];

        foreach ($labels as $key => $label) {
            if (! $request->filled($key)) {
                continue;
            }

            $value = $request->input($key);
            $display = isset($resolve[$key]) ? $resolve[$key]($value) : $value;

            if ($display) {
                $parts[] = $label . ': ' . $display;
            }
        }

        return implode(' · ', $parts);
    }
}
