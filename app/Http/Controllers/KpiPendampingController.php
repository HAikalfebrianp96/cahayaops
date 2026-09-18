<?php

namespace App\Http\Controllers;

use App\Models\Pendamping;
use App\Models\PendampingTarget;
use App\Services\KpiService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use App\Services\GambarCetak;

/**
 * KPI & KINERJA PENDAMPING.
 *
 * OTORISASI BERLAPIS — dan inilah bagian paling penting halaman ini:
 *
 *   Admin      -> seluruh pendamping, dengan filter.
 *   Pendamping -> HANYA dirinya sendiri. Filter ?pendamping_id dari URL
 *                 DIABAIKAN sepenuhnya untuknya; kalau dihormati, mengetik
 *                 id orang lain akan membuka kinerja pendamping lain.
 *   Lainnya    -> ditolak. Tim Koperasi tidak berurusan dengan kinerja
 *                 pendamping Cahaya Preneur, dan Pimpinan punya dashboard
 *                 sendiri.
 *
 * Seluruh angka dihitung [KpiService] dari data yang sudah ada — tidak ada
 * tabel realisasi, tidak ada data dummy.
 */
class KpiPendampingController extends Controller
{
    public function __construct(private KpiService $kpi)
    {
    }

    public function index(Request $request)
    {
        Gate::authorize('lihatKpi', PendampingTarget::class);

        $user = Auth::user();
        [$awal, $akhir, $labelPeriode] = $this->periode($request);

        $pendampings = $this->pendampingTerlihat($request, $user);
        $rapor = $this->kpi->untukBanyak($pendampings, $awal, $akhir);

        // Filter status kinerja diterapkan SESUDAH perhitungan — statusnya
        // memang baru ada setelah KPI dihitung, bukan kolom yang bisa
        // disaring di query.
        if ($request->filled('status')) {
            $status = $request->string('status')->toString();
            $rapor = $rapor->filter(fn ($r) => $r['status'] === $status);
        }

        // Berapa banyak baris yang dinilai memakai angka bawaan sistem.
        //
        // Dihitung dari rapor yang SAMA dengan yang ditampilkan, bukan lewat
        // query sendiri ke pendamping_targets — kalau dihitung terpisah,
        // angkanya bisa berbeda dari kolom "Sumber target" di tabel yang
        // persis ada di bawahnya.
        $pakaiDefault = $rapor->where('pakai_default', true)->count();

        return view('kpi-pendamping.index', [
            'rapor' => $rapor->sortByDesc(fn ($r) => $r['kpi'] ?? -1)->values(),
            'jumlahPakaiDefault' => $pakaiDefault,
            'jumlahDinilai' => $rapor->count(),
            'ringkasan' => $this->kpi->ringkasan($rapor),
            'ambangBeban' => $this->kpi->ambangBeban(),
            'targetDefault' => $this->kpi->targetDefault(),
            'labelPeriode' => $labelPeriode,
            'isAdmin' => $user->isAdmin(),
            'pendampings' => $user->isAdmin() ? Pendamping::orderBy('nama')->get() : collect(),
            'statusOptions' => [
                KpiService::SANGAT_BAIK, KpiService::BAIK,
                KpiService::PERLU_PERHATIAN, KpiService::RENDAH,
                // Ikut disaring: kalau tidak, pendamping yang targetnya belum
                // diatur tidak bisa dicari sama sekali — padahal justru
                // merekalah yang perlu ditindaklanjuti Admin.
                KpiService::TANPA_TARGET,
            ],
            'filters' => $request->only(['pendamping_id', 'tahun', 'bulan', 'periode', 'status', 'q']),
            'bulanOptions' => \App\Models\OmzetReport::BULAN,
            'tahunOptions' => range(now()->year + 1, now()->year - 3),
        ]);
    }

    /**
     * Detail kinerja satu pendamping + riwayat KPI bulanannya.
     *
     * Pendamping boleh membuka halaman ini HANYA untuk dirinya sendiri —
     * diperiksa terhadap pendamping_id akunnya, bukan sekadar role.
     */
    public function show(Request $request, Pendamping $pendamping)
    {
        Gate::authorize('lihatKpi', PendampingTarget::class);

        $user = Auth::user();

        abort_unless(
            $user->isAdmin() || $pendamping->id === $user->pendamping_id,
            403
        );

        [$awal, $akhir, $labelPeriode] = $this->periode($request);

        $pendamping->loadCount([
            'participants',
            'participants as binaan_aktif_count' => fn ($q) => $q->whereIn('status', \App\Models\Participant::STATUS_BERJALAN),
        ]);

        // RIWAYAT KINERJA — 12 bulan terakhir, dihitung ulang per bulan.
        // Sengaja tidak disimpan: kalau catatan lapangan bulan lalu dikoreksi,
        // riwayatnya ikut benar tanpa perlu proses perhitungan ulang apa pun.
        $riwayat = collect(range(11, 0))->map(function (int $mundur) use ($pendamping) {
            $bulan = now()->startOfMonth()->subMonths($mundur);
            $rapor = $this->kpi->untukSatu($pendamping, $bulan->copy()->startOfMonth(), $bulan->copy()->endOfMonth());

            return [
                'label' => $bulan->translatedFormat('M Y'),
                'bulan' => $bulan->copy(),
                'kpi' => $rapor['kpi'],
                'status' => $rapor['status'],
                'warna' => $rapor['warna'],
                'indikator' => $rapor['indikator'],
                'pakai_default' => $rapor['pakai_default'],
            ];
        });

        return view('kpi-pendamping.show', [
            'pendamping' => $pendamping,
            'rapor' => $this->kpi->untukSatu($pendamping, $awal, $akhir),
            'riwayat' => $riwayat,
            'labelPeriode' => $labelPeriode,
            'targets' => PendampingTarget::where('pendamping_id', $pendamping->id)
                ->orderByDesc('tahun')->orderByDesc('bulan')->get(),
            'isAdmin' => $user->isAdmin(),
            'grafik' => [
                'labels' => $riwayat->pluck('label')->all(),
                'kpi' => $riwayat->map(fn ($r) => $r['kpi'] ?? 0)->all(),
                'pendampingan' => $riwayat->map(fn ($r) => $r['indikator']['target_pendampingan']['aktual'] ?? 0)->all(),
                'follow_up' => $riwayat->map(fn ($r) => $r['indikator']['target_follow_up']['aktual'] ?? 0)->all(),
            ],
        ]);
    }

    /** Export CSV — memakai filter & scope yang SAMA dengan halaman daftar. */
    public function export(Request $request)
    {
        Gate::authorize('lihatKpi', PendampingTarget::class);

        $user = Auth::user();
        [$awal, $akhir, $labelPeriode] = $this->periode($request);

        $rapor = $this->kpi->untukBanyak($this->pendampingTerlihat($request, $user), $awal, $akhir);

        if ($request->filled('status')) {
            $status = $request->string('status')->toString();
            $rapor = $rapor->filter(fn ($r) => $r['status'] === $status);
        }

        $namaFile = 'laporan-kpi-pendamping-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($rapor, $labelPeriode) {
            $handle = fopen('php://output', 'w');
            // BOM UTF-8 supaya Excel di Windows membaca huruf beraksen benar.
            fwrite($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($handle, ['LAPORAN KINERJA PENDAMPING CAHAYA PRENEUR']);
            fputcsv($handle, ['Periode', $labelPeriode]);
            fputcsv($handle, ['Dicetak', now()->translatedFormat('d F Y H:i')]);
            fputcsv($handle, []);

            $header = ['Pendamping', 'Status Pendamping', 'Sumber Target', 'Beban Binaan'];
            foreach (PendampingTarget::INDIKATOR as $label) {
                $header[] = $label . ' (Aktual)';
                $header[] = $label . ' (Target)';
            }
            $header[] = 'KPI (%)';
            $header[] = 'Status Kinerja';
            fputcsv($handle, $header);

            foreach ($rapor as $r) {
                $baris = [
                    $r['pendamping']->nama,
                    $r['pendamping']->status,
                    $r['pakai_default'] ? 'Target Default' : 'Target Individual',
                    $r['beban']['kategori'],
                ];

                foreach (PendampingTarget::INDIKATOR as $kolom => $label) {
                    $baris[] = $r['indikator'][$kolom]['aktual'];
                    $baris[] = $r['indikator'][$kolom]['target'] ?? '-';
                }

                $baris[] = $r['kpi'] ?? '-';
                $baris[] = $r['status'] ?? 'Belum ada target';
                fputcsv($handle, $baris);
            }

            fclose($handle);
        }, $namaFile, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Export PDF — memakai kop laporan bersama Cahaya Preneur. */
    public function exportPdf(Request $request)
    {
        Gate::authorize('lihatKpi', PendampingTarget::class);

        $user = Auth::user();
        [$awal, $akhir, $labelPeriode] = $this->periode($request);

        $rapor = $this->kpi->untukBanyak($this->pendampingTerlihat($request, $user), $awal, $akhir);

        if ($request->filled('status')) {
            $status = $request->string('status')->toString();
            $rapor = $rapor->filter(fn ($r) => $r['status'] === $status);
        }

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('kpi-pendamping.pdf', [
            'rapor' => $rapor->sortByDesc(fn ($r) => $r['kpi'] ?? -1)->values(),
            'ringkasan' => $this->kpi->ringkasan($rapor),
            'judulLaporan' => 'LAPORAN KINERJA PENDAMPING CAHAYA PRENEUR',
            'periodeLabel' => $labelPeriode,
            'tanggalCetak' => now()->translatedFormat('d F Y H:i'),
            'dicetakOleh' => $user->name,
            // Disusutkan lebih dulu — berkas aslinya tidak disentuh.
            // Logo laporan tercatat 2535 x 461 px untuk dicetak setinggi 44 px.
            'logoPath' => app(GambarCetak::class)->untukCetak(public_path('images/logo-laporan.png')),
            'filterRingkasan' => $request->filled('status') ? 'Status: ' . $request->string('status') : null,
        ])->setPaper('a4', 'landscape');

        return $pdf->download('laporan-kpi-pendamping-' . now()->format('Y-m-d') . '.pdf');
    }

    // ==========================================================
    // HELPER
    // ==========================================================

    /**
     * Pendamping yang BOLEH dilihat pengguna ini.
     *
     * SATU tempat penentuan scope, dipakai halaman daftar dan kedua export —
     * kalau ditulis tiga kali, cepat atau lambat salah satunya lupa disaring
     * dan isi export berbeda dari yang terlihat di layar.
     */
    private function pendampingTerlihat(Request $request, $user)
    {
        $query = Pendamping::query();

        if ($user->isAdmin()) {
            if ($request->filled('pendamping_id')) {
                $query->whereKey($request->integer('pendamping_id'));
            }

            if ($request->filled('q')) {
                $query->where('nama', 'like', '%' . $request->string('q') . '%');
            }
        } else {
            // Filter dari URL DIABAIKAN untuk non-Admin.
            $query->whereKey($user->pendamping_id);
        }

        return $query->orderBy('nama')->get();
    }

    /**
     * Rentang periode yang diminta.
     *
     * @return array{0:Carbon, 1:Carbon, 2:string}
     */
    private function periode(Request $request): array
    {
        $tahun = $request->filled('tahun') ? $request->integer('tahun') : now()->year;
        $periode = $request->string('periode')->toString() ?: PendampingTarget::PERIODE_BULANAN;

        if ($periode === PendampingTarget::PERIODE_TAHUNAN) {
            return [
                Carbon::create($tahun, 1, 1)->startOfDay(),
                Carbon::create($tahun, 12, 31)->endOfDay(),
                'Tahun ' . $tahun,
            ];
        }

        $bulan = $request->filled('bulan') ? $request->integer('bulan') : now()->month;
        $bulan = max(1, min(12, $bulan));

        if ($periode === PendampingTarget::PERIODE_TRIWULAN) {
            $awalTriwulan = ((int) ceil($bulan / 3) - 1) * 3 + 1;
            $awal = Carbon::create($tahun, $awalTriwulan, 1)->startOfDay();

            return [
                $awal,
                $awal->copy()->addMonths(2)->endOfMonth(),
                'Triwulan ' . ceil($bulan / 3) . ' ' . $tahun,
            ];
        }

        $awal = Carbon::create($tahun, $bulan, 1)->startOfDay();

        return [
            $awal,
            $awal->copy()->endOfMonth(),
            \App\Models\OmzetReport::namaBulan($bulan) . ' ' . $tahun,
        ];
    }
}
