<?php

namespace App\Http\Controllers;

use App\Exports\SimpleArrayExport;
use App\Models\BusinessUnit;
use App\Models\DanaHibahKeluar;
use App\Models\DanaHibahMasuk;
use App\Models\DanaSukarelaKeluar;
use App\Models\DanaSukarelaMasuk;
use App\Models\KoperasiMember;
use App\Models\KoperasiSetting;
use App\Models\MemberSaving;
use App\Models\Pengeluaran;
use App\Models\SalesTransaction;
use App\Services\GambarCetak;
use App\Services\LabaRugiService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;

class LaporanKoperasiController extends Controller
{
    public function index()
    {
        $this->authorizeAkses();

        return view('koperasi.laporan.index');
    }

    // ==========================================================
    // 1. LAPORAN RINGKASAN KOPERASI
    // ==========================================================

    public function ringkasan(Request $request, LabaRugiService $labaRugi)
    {
        $this->authorizeAkses();

        return view('koperasi.laporan.ringkasan', $this->dataRingkasan($request, $labaRugi));
    }

    public function ringkasanPdf(Request $request, LabaRugiService $labaRugi)
    {
        $this->authorizeAkses();

        $data = array_merge($this->dataRingkasan($request, $labaRugi), $this->identitasPdf());
        $pdf = Pdf::loadView('koperasi.laporan.pdf.ringkasan', $data)->setPaper('a4', 'portrait');

        return $pdf->download('laporan-ringkasan-koperasi-' . $data['periodeFile'] . '.pdf');
    }

    public function ringkasanExcel(Request $request, LabaRugiService $labaRugi)
    {
        $this->authorizeAkses();

        $d = $this->dataRingkasan($request, $labaRugi);
        $lr = $d['lr']['total'];

        $rows = [
            ['LAPORAN RINGKASAN KOPERASI CAHAYA'],
            ['Periode', $d['periodeLabel']],
            [],
            ['Total Anggota', $d['totalAnggota']],
            ['Anggota Aktif', $d['anggotaAktif']],
            ['Unit Usaha', $d['totalUnit']],
            [],
            ['LABA RUGI KOPERASI'],
            ['Penjualan (Rp)', $lr['penjualan']],
            ['HPP (Rp)', $lr['hpp']],
            ['Laba Kotor (Rp)', $lr['laba_kotor']],
            ['Fee Koperasi (Rp)', $lr['fee_koperasi']],
            ['Hak Mitra (Rp)', $lr['hak_mitra']],
            ['Pengeluaran (Rp)', $lr['pengeluaran_total']],
            ['Laba Bersih (Rp)', $lr['laba_bersih']],
            [],
            ['PERFORMA UNIT USAHA'],
            ['Unit', 'Penjualan (Rp)', 'Laba Bersih (Rp)'],
        ];
        foreach ($d['lr']['per_unit'] as $u) {
            $rows[] = [$u['nama'], $u['penjualan'], $u['laba_bersih']];
        }
        $rows[] = [];
        $rows[] = ['DANA ANGGOTA & DANA SOSIAL (bukan pendapatan usaha)'];
        $rows[] = ['Simpanan Pokok (Rp)', $d['totalSimpananPokok']];
        $rows[] = ['Simpanan Wajib (Rp)', $d['totalSimpananWajib']];
        $rows[] = ['Saldo Dana Sukarela (Rp)', $d['saldoDanaSosial']];
        $rows[] = ['Saldo Dana Hibah (Rp)', $d['saldoDanaHibah']];

        return Excel::download(new SimpleArrayExport($rows, 'Ringkasan Koperasi'), 'laporan-ringkasan-koperasi-' . $d['periodeFile'] . '.xlsx');
    }

    private function dataRingkasan(Request $request, LabaRugiService $labaRugi): array
    {
        $tahun = $request->input('tahun', 'default');
        $bulan = $request->input('bulan', 'default');
        $lr = $labaRugi->hitung($tahun, $bulan, null);

        return [
            'lr' => $lr,
            'totalAnggota' => KoperasiMember::count(),
            'anggotaAktif' => KoperasiMember::where('status', 'Aktif')->count(),
            'totalUnit' => BusinessUnit::count(),
            'totalSimpananPokok' => (float) MemberSaving::berlaku()->where('jenis_simpanan', 'Simpanan Pokok')->sum('nominal'),
            'totalSimpananWajib' => (float) MemberSaving::berlaku()->where('jenis_simpanan', 'Simpanan Wajib')->sum('nominal'),

            // Bagian dari dua angka di atas yang pemiliknya SUDAH TIDAK LAGI
            // menjadi anggota.
            //
            // Angkanya tidak dikurangi: mengarsipkan anggota tidak
            // mengembalikan uangnya, jadi koperasi memang masih memegang dana
            // itu dan laporan yang menghilangkannya justru salah ke arah
            // sebaliknya.
            //
            // Yang diperbaiki adalah keterangannya. Tanpa ini, satu halaman
            // yang sama bisa berbunyi "Total Anggota 1" berdampingan dengan
            // simpanan yang tak satu pun miliknya — dan halaman SHU menolak
            // membagi jasa modalnya dengan alasan yang tidak terlihat di sini.
            'simpananTerarsip' => (float) MemberSaving::berlaku()
                ->milikAnggotaTerarsip()
                ->whereIn('jenis_simpanan', ['Simpanan Pokok', 'Simpanan Wajib'])
                ->sum('nominal'),
            'saldoDanaSosial' => (float) DanaSukarelaMasuk::berlaku()->sum('nominal') - (float) DanaSukarelaKeluar::berlaku()->sum('nominal'),
            'saldoDanaHibah' => (float) DanaHibahMasuk::berlaku()->sum('nominal') - (float) DanaHibahKeluar::berlaku()->sum('nominal'),
            'filters' => ['tahun' => $tahun, 'bulan' => $bulan],
            'periodeLabel' => $this->periodeLabel($tahun, $bulan),
            'periodeFile' => $this->periodeFile($tahun, $bulan),
        ];
    }

    // ==========================================================
    // 2. LAPORAN LABA RUGI
    // ==========================================================

    public function labaRugi(Request $request, LabaRugiService $labaRugi)
    {
        $this->authorizeAkses();

        return view('koperasi.laporan.laba-rugi', $this->dataLabaRugi($request, $labaRugi));
    }

    public function labaRugiPdf(Request $request, LabaRugiService $labaRugi)
    {
        $this->authorizeAkses();

        $data = array_merge($this->dataLabaRugi($request, $labaRugi), $this->identitasPdf());
        $pdf = Pdf::loadView('koperasi.laporan.pdf.laba-rugi', $data)->setPaper('a4', 'portrait');
        $unitSlug = $data['unitLabel'] ? '-' . str($data['unitLabel'])->slug() : '';

        return $pdf->download('laporan-laba-rugi-koperasi' . $unitSlug . '-' . $data['periodeFile'] . '.pdf');
    }

    public function labaRugiExcel(Request $request, LabaRugiService $labaRugi)
    {
        $this->authorizeAkses();

        $d = $this->dataLabaRugi($request, $labaRugi);
        $t = $d['lr']['total'];

        $rows = [
            ['LAPORAN LABA RUGI KOPERASI CAHAYA'],
            ['Periode', $d['periodeLabel'] . ($d['unitLabel'] ? ' - ' . $d['unitLabel'] : '')],
            [],
            ['Unit', 'Penjualan (Rp)', 'HPP (Rp)', 'Fee (Rp)', 'Hak Mitra (Rp)', 'Pengeluaran (Rp)', 'Laba Bersih (Rp)'],
        ];
        foreach ($d['lr']['per_unit'] as $u) {
            $rows[] = [$u['nama'], $u['penjualan'], $u['hpp'], $u['fee_koperasi'], $u['hak_mitra'], $u['pengeluaran'], $u['laba_bersih']];
        }
        $rows[] = ['TOTAL KOPERASI', $t['penjualan'], $t['hpp'], $t['fee_koperasi'], $t['hak_mitra'], $t['pengeluaran_total'], $t['laba_bersih']];

        $unitSlug = $d['unitLabel'] ? '-' . str($d['unitLabel'])->slug() : '';

        return Excel::download(new SimpleArrayExport($rows, 'Laba Rugi'), 'laporan-laba-rugi-koperasi' . $unitSlug . '-' . $d['periodeFile'] . '.xlsx');
    }

    private function dataLabaRugi(Request $request, LabaRugiService $labaRugi): array
    {
        $tahun = $request->input('tahun', 'default');
        $bulan = $request->input('bulan', 'default');
        $unitId = $request->input('unit_id');

        $lr = $labaRugi->hitung($tahun, $bulan, $unitId);
        $units = BusinessUnit::orderBy('nama')->get();

        $unitLabel = null;
        if ($unitId === 'operasional') {
            $unitLabel = 'Operasional Koperasi';
        } elseif ($unitId) {
            $unitLabel = optional($units->firstWhere('id', (int) $unitId))->nama;
        }

        return [
            'lr' => $lr, 'units' => $units, 'unitLabel' => $unitLabel,
            'filters' => ['tahun' => $tahun, 'bulan' => $bulan, 'unit_id' => $unitId],
            'periodeLabel' => $this->periodeLabel($tahun, $bulan),
            'periodeFile' => $this->periodeFile($tahun, $bulan),
        ];
    }

    // ==========================================================
    // 3. LAPORAN PENJUALAN
    // ==========================================================

    public function penjualan(Request $request)
    {
        $this->authorizeAkses();

        return view('koperasi.laporan.penjualan', $this->dataPenjualan($request));
    }

    public function penjualanPdf(Request $request)
    {
        $this->authorizeAkses();

        $data = array_merge($this->dataPenjualan($request), $this->identitasPdf());
        $pdf = Pdf::loadView('koperasi.laporan.pdf.penjualan', $data)->setPaper('a4', 'landscape');

        return $pdf->download('laporan-penjualan-koperasi-' . $data['periodeFile'] . '.pdf');
    }

    public function penjualanExcel(Request $request)
    {
        $this->authorizeAkses();

        $d = $this->dataPenjualan($request);

        $rows = [
            ['LAPORAN PENJUALAN KOPERASI CAHAYA'],
            ['Periode', $d['periodeLabel']],
            [],
            ['Tanggal', 'Invoice', 'Unit', 'Customer', 'Total (Rp)', 'Dibayar (Rp)', 'Sisa (Rp)', 'Status', 'Model Usaha'],
        ];
        foreach ($d['rows'] as $r) {
            $rows[] = [$r['tanggal'], $r['invoice'], $r['unit'], $r['customer'], $r['total'], $r['dibayar'], $r['sisa'], $r['status'], $r['model_usaha']];
        }
        $rows[] = [];
        $rows[] = ['TOTAL', '', '', '', $d['totalPenjualan'], $d['totalDibayar'], $d['totalSisa'], '', ''];

        return Excel::download(new SimpleArrayExport($rows, 'Penjualan'), 'laporan-penjualan-koperasi-' . $d['periodeFile'] . '.xlsx');
    }

    private function dataPenjualan(Request $request): array
    {
        $tahun = $request->input('tahun', 'default');
        $bulan = $request->input('bulan', 'default');
        $unitId = $request->input('unit_id');
        $status = $request->input('status_pembayaran');

        $query = SalesTransaction::whereNotIn('status_transaksi', ['Draft', 'Dibatalkan'])
            // Arsip-inklusif: laporan penjualan periode lalu harus tetap
            // menyebut nama unit & pelanggannya walau keduanya sudah tidak
            // beroperasi. Laporan yang menampilkan "-" tidak bisa dipakai
            // rekonsiliasi.
            ->with(['businessUnitTermasukArsip', 'customerTermasukArsip', 'items', 'invoice']);

        $this->terapkanPeriode($query, $tahun, $bulan, 'tanggal');
        if ($unitId) {
            $query->where('unit_id', $unitId);
        }
        if ($status) {
            $query->where('status_pembayaran', $status);
        }

        $rows = $query->orderByDesc('tanggal')->get()->map(function (SalesTransaction $trx) {
            $adaTitipJual = $trx->items->contains(fn ($i) => (float) $i->fee_rate > 0 || (float) $i->hak_mitra_amount > 0);
            $adaMilikKoperasi = $trx->items->contains(fn ($i) => (float) $i->fee_rate <= 0 && (float) $i->hak_mitra_amount <= 0);
            $modelUsaha = $adaTitipJual && $adaMilikKoperasi ? 'Campuran' : ($adaTitipJual ? 'Titip Jual' : 'Milik Koperasi');

            $fee = (float) $trx->items->sum(fn ($i) => $i->fee_amount ?? ($i->subtotal * (float) $i->fee_rate / 100));
            $hakMitra = (float) $trx->items->sum(fn ($i) => (float) $i->hak_mitra_amount);

            return [
                'tanggal' => $trx->tanggal->format('d-m-Y'),
                'invoice' => $trx->invoice->nomor_invoice ?? '-',
                'unit' => $trx->businessUnitTermasukArsip?->nama ?? '-',
                'customer' => $trx->customer->nama ?? '-',
                'total' => (float) $trx->total,
                'dibayar' => (float) $trx->nominalDibayar(),
                'sisa' => (float) $trx->sisaPembayaran(),
                'status' => $trx->status_pembayaran,
                'model_usaha' => $modelUsaha,
                'fee' => $fee,
                'hak_mitra' => $hakMitra,
            ];
        });

        return [
            'rows' => $rows,
            'units' => BusinessUnit::orderBy('nama')->get(),
            'jumlahTransaksi' => $rows->count(),
            'totalPenjualan' => $rows->sum('total'),
            'totalDibayar' => $rows->sum('dibayar'),
            'totalSisa' => $rows->sum('sisa'),
            'totalFee' => $rows->sum('fee'),
            'totalHakMitra' => $rows->sum('hak_mitra'),
            'filters' => ['tahun' => $tahun, 'bulan' => $bulan, 'unit_id' => $unitId, 'status_pembayaran' => $status],
            'periodeLabel' => $this->periodeLabel($tahun, $bulan),
            'periodeFile' => $this->periodeFile($tahun, $bulan),
        ];
    }

    // ==========================================================
    // 4. LAPORAN PENGELUARAN
    // ==========================================================

    public function pengeluaran(Request $request)
    {
        $this->authorizeAkses();

        return view('koperasi.laporan.pengeluaran', $this->dataPengeluaran($request));
    }

    public function pengeluaranPdf(Request $request)
    {
        $this->authorizeAkses();

        $data = array_merge($this->dataPengeluaran($request), $this->identitasPdf());
        $pdf = Pdf::loadView('koperasi.laporan.pdf.pengeluaran', $data)->setPaper('a4', 'portrait');

        return $pdf->download('laporan-pengeluaran-koperasi-' . $data['periodeFile'] . '.pdf');
    }

    public function pengeluaranExcel(Request $request)
    {
        $this->authorizeAkses();

        $d = $this->dataPengeluaran($request);

        $rows = [
            ['LAPORAN PENGELUARAN KOPERASI CAHAYA'],
            ['Periode', $d['periodeLabel']],
            [],
            ['Total Pengeluaran Aktif (Rp)', $d['totalAktif']],
            [],
            ['PER UNIT'],
        ];
        foreach ($d['perUnit'] as $u) {
            $rows[] = [$u['label'], $u['total']];
        }
        $rows[] = [];
        $rows[] = ['PER KATEGORI'];
        foreach ($d['perKategori'] as $k) {
            $rows[] = [$k['kategori'], $k['total']];
        }
        $rows[] = [];
        $rows[] = ['RINCIAN'];
        $rows[] = ['Tanggal', 'Unit', 'Kategori', 'Keterangan', 'Metode', 'Nominal (Rp)', 'Status'];
        foreach ($d['rows'] as $r) {
            $rows[] = [$r->tanggal->format('d-m-Y'), $r->labelUnit(), $r->kategori, $r->keterangan ?: '-', $r->metode_pembayaran, (float) $r->nominal, $r->status];
        }

        return Excel::download(new SimpleArrayExport($rows, 'Pengeluaran'), 'laporan-pengeluaran-koperasi-' . $d['periodeFile'] . '.xlsx');
    }

    private function dataPengeluaran(Request $request): array
    {
        $tahun = $request->input('tahun', 'default');
        $bulan = $request->input('bulan', 'default');
        $unitId = $request->input('unit_id');
        $kategori = $request->input('kategori');
        $statusFilter = $request->input('status', 'Aktif');

        $query = Pengeluaran::with('businessUnitTermasukArsip');
        if ($statusFilter && $statusFilter !== 'semua') {
            $query->where('status', $statusFilter);
        }
        $this->terapkanPeriode($query, $tahun, $bulan, 'tanggal');
        if ($unitId) {
            $unitId === 'operasional' ? $query->whereNull('business_unit_id') : $query->where('business_unit_id', $unitId);
        }
        if ($kategori) {
            $query->where('kategori', $kategori);
        }

        $rows = $query->orderByDesc('tanggal')->get();
        $totalAktif = $rows->where('status', 'Aktif')->sum('nominal');

        $perUnit = $rows->where('status', 'Aktif')->groupBy(fn ($p) => $p->business_unit_id ?? 'operasional')
            ->map(fn ($g, $key) => ['label' => $key === 'operasional' ? 'Operasional Koperasi' : ($g->first()->businessUnitTermasukArsip?->nama ?? '-'), 'total' => $g->sum('nominal')]);

        $perKategori = $rows->where('status', 'Aktif')->groupBy('kategori')
            ->map(fn ($g, $key) => ['kategori' => $key, 'total' => $g->sum('nominal')])
            ->sortByDesc('total');

        return [
            'rows' => $rows,
            'units' => BusinessUnit::orderBy('nama')->get(),
            'kategoriList' => Pengeluaran::query()->select('kategori')->distinct()->orderBy('kategori')->pluck('kategori'),
            'totalAktif' => $totalAktif,
            'perUnit' => $perUnit,
            'perKategori' => $perKategori,
            'filters' => ['tahun' => $tahun, 'bulan' => $bulan, 'unit_id' => $unitId, 'kategori' => $kategori, 'status' => $statusFilter],
            'periodeLabel' => $this->periodeLabel($tahun, $bulan),
            'periodeFile' => $this->periodeFile($tahun, $bulan),
        ];
    }

    // ==========================================================
    // 5. LAPORAN UNIT USAHA
    // ==========================================================

    public function unitUsaha(Request $request, LabaRugiService $labaRugi)
    {
        $this->authorizeAkses();

        return view('koperasi.laporan.unit-usaha', $this->dataUnitUsaha($request, $labaRugi));
    }

    public function unitUsahaPdf(Request $request, LabaRugiService $labaRugi)
    {
        $this->authorizeAkses();

        $data = array_merge($this->dataUnitUsaha($request, $labaRugi), $this->identitasPdf());

        // Laporan ini selalu tentang SATU unit usaha. Tanpa unit terpilih tidak
        // ada yang bisa dicetak — dan sebelumnya kondisi itu menabrak
        // $unit->nama di view sehingga menghasilkan HTTP 500. Halaman HTML-nya
        // sudah menampilkan pesan "pilih unit dulu"; di sini disamakan.
        if (! $data['unit']) {
            return back()->withErrors([
                'unit' => 'Pilih unit usaha terlebih dahulu sebelum mengunduh PDF.',
            ]);
        }

        $pdf = Pdf::loadView('koperasi.laporan.pdf.unit-usaha', $data)->setPaper('a4', 'portrait');
        $slug = str($data['unit']->nama)->slug();

        return $pdf->download('laporan-unit-usaha-' . $slug . '-' . $data['periodeFile'] . '.pdf');
    }

    public function unitUsahaExcel(Request $request, LabaRugiService $labaRugi)
    {
        $this->authorizeAkses();

        $d = $this->dataUnitUsaha($request, $labaRugi);
        $slug = $d['unit'] ? str($d['unit']->nama)->slug() : 'unit';

        $rows = [
            ['LAPORAN UNIT USAHA — ' . strtoupper($d['unit']->nama ?? '-')],
            ['Periode', $d['periodeLabel']],
            [],
        ];
        if ($d['ringkasanUnit']) {
            $r = $d['ringkasanUnit'];
            $rows[] = ['Penjualan (Rp)', $r['penjualan']];
            $rows[] = ['HPP (Rp)', $r['hpp']];
            $rows[] = ['Fee (Rp)', $r['fee_koperasi']];
            $rows[] = ['Hak Mitra (Rp)', $r['hak_mitra']];
            $rows[] = ['Pengeluaran (Rp)', $r['pengeluaran']];
            $rows[] = ['Laba Bersih (Rp)', $r['laba_bersih']];
        }
        $rows[] = [];
        $rows[] = ['DAFTAR TRANSAKSI'];
        $rows[] = ['Tanggal', 'Customer', 'Total (Rp)'];
        foreach ($d['transaksi'] as $t) {
            $rows[] = [$t->tanggal->format('d-m-Y'), $t->customer->nama ?? '-', (float) $t->total];
        }
        $rows[] = [];
        $rows[] = ['DAFTAR PENGELUARAN'];
        $rows[] = ['Tanggal', 'Kategori', 'Nominal (Rp)'];
        foreach ($d['pengeluaran'] as $p) {
            $rows[] = [$p->tanggal->format('d-m-Y'), $p->kategori, (float) $p->nominal];
        }

        return Excel::download(new SimpleArrayExport($rows, 'Unit Usaha'), 'laporan-unit-usaha-' . $slug . '-' . $d['periodeFile'] . '.xlsx');
    }

    private function dataUnitUsaha(Request $request, LabaRugiService $labaRugi): array
    {
        $tahun = $request->input('tahun', 'default');
        $bulan = $request->input('bulan', 'default');
        $units = BusinessUnit::orderBy('nama')->get();
        $unitId = $request->input('unit_id', optional($units->first())->id);

        $unit = $units->firstWhere('id', (int) $unitId);
        $lr = $labaRugi->hitung($tahun, $bulan, (string) $unitId);
        $ringkasanUnit = $unit ? ($lr['per_unit'][$unit->id] ?? null) : null;

        $transaksiQuery = SalesTransaction::whereNotIn('status_transaksi', ['Draft', 'Dibatalkan'])->where('unit_id', $unitId);
        $this->terapkanPeriode($transaksiQuery, $tahun, $bulan, 'tanggal');
        $transaksi = $transaksiQuery->with('customer')->orderByDesc('tanggal')->limit(50)->get();

        $pengeluaranQuery = Pengeluaran::aktif()->where('business_unit_id', $unitId);
        $this->terapkanPeriode($pengeluaranQuery, $tahun, $bulan, 'tanggal');
        $pengeluaran = $pengeluaranQuery->orderByDesc('tanggal')->limit(50)->get();

        return [
            'units' => $units, 'unit' => $unit, 'ringkasanUnit' => $ringkasanUnit,
            'transaksi' => $transaksi, 'pengeluaran' => $pengeluaran,
            'filters' => ['tahun' => $tahun, 'bulan' => $bulan, 'unit_id' => $unitId],
            'periodeLabel' => $this->periodeLabel($tahun, $bulan),
            'periodeFile' => $this->periodeFile($tahun, $bulan),
        ];
    }

    // ==========================================================
    // 6. LAPORAN KEANGGOTAAN
    // ==========================================================

    public function keanggotaan()
    {
        $this->authorizeAkses();

        return view('koperasi.laporan.keanggotaan', $this->dataKeanggotaan());
    }

    public function keanggotaanPdf()
    {
        $this->authorizeAkses();

        $data = array_merge($this->dataKeanggotaan(), $this->identitasPdf());
        $pdf = Pdf::loadView('koperasi.laporan.pdf.keanggotaan', $data)->setPaper('a4', 'portrait');

        return $pdf->download('laporan-keanggotaan-koperasi-' . now()->format('Y-m-d') . '.pdf');
    }

    public function keanggotaanExcel()
    {
        $this->authorizeAkses();

        $d = $this->dataKeanggotaan();

        $rows = [
            ['LAPORAN KEANGGOTAAN KOPERASI CAHAYA'],
            [$d['periodeLabel']],
            [],
            ['Total Anggota', $d['totalAnggota']],
            ['Aktif', $d['anggotaAktif']],
            ['Tidak Aktif', $d['anggotaTidakAktif']],
            [],
            ['Nama', 'Nomor Anggota', 'Tanggal Bergabung', 'Status'],
        ];
        foreach ($d['anggota'] as $a) {
            $rows[] = [$a->namaTampil(), $a->nomor_anggota ?? '-', optional($a->tanggal_bergabung)->format('d-m-Y') ?? '-', $a->status];
        }

        return Excel::download(new SimpleArrayExport($rows, 'Keanggotaan'), 'laporan-keanggotaan-koperasi-' . now()->format('Y-m-d') . '.xlsx');
    }

    private function dataKeanggotaan(): array
    {
        // with('participant'): untuk anggota yang berasal dari peserta Cahaya
        // Preneur, nama yang dilaporkan diambil dari data peserta lewat
        // namaTampil() — jadi memperbaiki ejaan nama di Data Peserta langsung
        // benar juga di laporan, tanpa ada dua versi nama yang berbeda.
        $anggota = KoperasiMember::with('participant')->orderBy('nama')->get();

        return [
            'anggota' => $anggota,
            'totalAnggota' => $anggota->count(),
            'anggotaAktif' => $anggota->where('status', 'Aktif')->count(),
            'anggotaTidakAktif' => $anggota->where('status', '!=', 'Aktif')->count(),
            'periodeLabel' => 'Sampai dengan ' . now()->translatedFormat('d F Y'),
        ];
    }

    // ==========================================================
    // 7. LAPORAN SIMPANAN
    // ==========================================================

    public function simpanan(Request $request)
    {
        $this->authorizeAkses();

        return view('koperasi.laporan.simpanan', $this->dataSimpanan($request));
    }

    public function simpananPdf(Request $request)
    {
        $this->authorizeAkses();

        $data = array_merge($this->dataSimpanan($request), $this->identitasPdf());
        $pdf = Pdf::loadView('koperasi.laporan.pdf.simpanan', $data)->setPaper('a4', 'portrait');

        return $pdf->download('laporan-simpanan-koperasi-' . $data['periodeFile'] . '.pdf');
    }

    public function simpananExcel(Request $request)
    {
        $this->authorizeAkses();

        $d = $this->dataSimpanan($request);

        $rows = [
            ['LAPORAN SIMPANAN KOPERASI CAHAYA — Dana Anggota, bukan pendapatan usaha'],
            ['Periode', $d['periodeLabel']],
            [],
            ['Total Simpanan Pokok (Rp)', $d['totalPokok']],
            ['Total Simpanan Wajib (Rp)', $d['totalWajib']],
            [],
            ['SIMPANAN POKOK'],
            ['Tanggal', 'Nominal (Rp)'],
        ];
        foreach ($d['rowsPokok'] as $r) {
            $rows[] = [$r->tanggal->format('d-m-Y'), (float) $r->nominal];
        }
        $rows[] = [];
        $rows[] = ['SIMPANAN WAJIB'];
        $rows[] = ['Tanggal', 'Nominal (Rp)'];
        foreach ($d['rowsWajib'] as $r) {
            $rows[] = [$r->tanggal->format('d-m-Y'), (float) $r->nominal];
        }

        return Excel::download(new SimpleArrayExport($rows, 'Simpanan'), 'laporan-simpanan-koperasi-' . $d['periodeFile'] . '.xlsx');
    }

    private function dataSimpanan(Request $request): array
    {
        $tahun = $request->input('tahun', 'default');
        $bulan = $request->input('bulan', 'default');

        // berlaku(): laporan simpanan hanya memuat entri yang sah. Entri yang
        // dibatalkan tetap ada di riwayat anggota, tapi tidak ikut ditotal di
        // sini supaya angka laporan sama dengan saldo sebenarnya.
        $pokokQuery = MemberSaving::berlaku()->where('jenis_simpanan', 'Simpanan Pokok');
        $wajibQuery = MemberSaving::berlaku()->where('jenis_simpanan', 'Simpanan Wajib');
        $this->terapkanPeriode($pokokQuery, $tahun, $bulan, 'tanggal');
        $this->terapkanPeriode($wajibQuery, $tahun, $bulan, 'tanggal');

        $rowsPokok = $pokokQuery->orderByDesc('tanggal')->get();
        $rowsWajib = $wajibQuery->orderByDesc('tanggal')->get();

        return [
            'rowsPokok' => $rowsPokok, 'rowsWajib' => $rowsWajib,
            'totalPokok' => $rowsPokok->sum('nominal'), 'totalWajib' => $rowsWajib->sum('nominal'),
            'filters' => ['tahun' => $tahun, 'bulan' => $bulan],
            'periodeLabel' => $this->periodeLabel($tahun, $bulan),
            'periodeFile' => $this->periodeFile($tahun, $bulan),
        ];
    }

    // ==========================================================
    // 8. LAPORAN DANA SOSIAL
    // ==========================================================

    public function danaSosial(Request $request)
    {
        $this->authorizeAkses();

        return view('koperasi.laporan.dana-sosial', $this->dataDanaSosial($request));
    }

    public function danaSosialPdf(Request $request)
    {
        $this->authorizeAkses();

        $data = array_merge($this->dataDanaSosial($request), $this->identitasPdf());
        $pdf = Pdf::loadView('koperasi.laporan.pdf.dana-sosial', $data)->setPaper('a4', 'portrait');

        return $pdf->download('laporan-dana-sosial-koperasi-' . $data['periodeFile'] . '.pdf');
    }

    public function danaSosialExcel(Request $request)
    {
        $this->authorizeAkses();

        $d = $this->dataDanaSosial($request);

        $rows = [
            ['LAPORAN DANA SOSIAL KOPERASI CAHAYA — tidak dihitung sebagai Laba Bersih'],
            ['Periode', $d['periodeLabel']],
            [],
            ['DANA SUKARELA'],
            ['Dana Masuk (Rp)', $d['totalMasukSukarela']],
            ['Penggunaan (Rp)', $d['totalKeluarSukarela']],
            ['Saldo (Rp)', $d['totalMasukSukarela'] - $d['totalKeluarSukarela']],
            [],
            ['Tanggal', 'Sumber', 'Nominal Masuk (Rp)'],
        ];
        foreach ($d['sukarelaMasuk'] as $r) {
            $rows[] = [$r->tanggal->format('d-m-Y'), $r->sumber, (float) $r->nominal];
        }
        $rows[] = [];
        $rows[] = ['Tanggal', 'Keperluan', 'Nominal Keluar (Rp)'];
        foreach ($d['sukarelaKeluar'] as $r) {
            $rows[] = [$r->tanggal->format('d-m-Y'), $r->keperluan, (float) $r->nominal];
        }
        $rows[] = [];
        $rows[] = ['DANA HIBAH'];
        $rows[] = ['Dana Masuk (Rp)', $d['totalMasukHibah']];
        $rows[] = ['Penggunaan (Rp)', $d['totalKeluarHibah']];
        $rows[] = ['Saldo (Rp)', $d['totalMasukHibah'] - $d['totalKeluarHibah']];
        $rows[] = [];
        $rows[] = ['Tanggal', 'Sumber', 'Nominal Masuk (Rp)'];
        foreach ($d['hibahMasuk'] as $r) {
            $rows[] = [$r->tanggal->format('d-m-Y'), $r->sumber, (float) $r->nominal];
        }
        $rows[] = [];
        $rows[] = ['Tanggal', 'Keperluan', 'Nominal Keluar (Rp)'];
        foreach ($d['hibahKeluar'] as $r) {
            $rows[] = [$r->tanggal->format('d-m-Y'), $r->keperluan, (float) $r->nominal];
        }

        return Excel::download(new SimpleArrayExport($rows, 'Dana Sosial'), 'laporan-dana-sosial-koperasi-' . $d['periodeFile'] . '.xlsx');
    }

    private function dataDanaSosial(Request $request): array
    {
        $tahun = $request->input('tahun', 'default');
        $bulan = $request->input('bulan', 'default');

        $sukarelaMasukQ = DanaSukarelaMasuk::query();
        $sukarelaKeluarQ = DanaSukarelaKeluar::query();
        $hibahMasukQ = DanaHibahMasuk::query();
        $hibahKeluarQ = DanaHibahKeluar::query();

        foreach ([$sukarelaMasukQ, $sukarelaKeluarQ, $hibahMasukQ, $hibahKeluarQ] as $q) {
            $this->terapkanPeriode($q, $tahun, $bulan, 'tanggal');
        }

        $sukarelaMasuk = $sukarelaMasukQ->orderByDesc('tanggal')->get();
        $sukarelaKeluar = $sukarelaKeluarQ->orderByDesc('tanggal')->get();
        $hibahMasuk = $hibahMasukQ->orderByDesc('tanggal')->get();
        $hibahKeluar = $hibahKeluarQ->orderByDesc('tanggal')->get();

        return [
            'sukarelaMasuk' => $sukarelaMasuk, 'sukarelaKeluar' => $sukarelaKeluar,
            'hibahMasuk' => $hibahMasuk, 'hibahKeluar' => $hibahKeluar,
            'totalMasukSukarela' => $sukarelaMasuk->sum('nominal'), 'totalKeluarSukarela' => $sukarelaKeluar->sum('nominal'),
            'totalMasukHibah' => $hibahMasuk->sum('nominal'), 'totalKeluarHibah' => $hibahKeluar->sum('nominal'),
            'filters' => ['tahun' => $tahun, 'bulan' => $bulan],
            'periodeLabel' => $this->periodeLabel($tahun, $bulan),
            'periodeFile' => $this->periodeFile($tahun, $bulan),
        ];
    }

    // ==========================================================
    // HELPER
    // ==========================================================

    /**
     * Laporan Koperasi - Admin & Tim Koperasi (pengelola), plus Pimpinan yang
     * memang sudah boleh sejak awal. Satu tempat untuk 25 method.
     */
    private function authorizeAkses(): void
    {
        $user = Auth::user();

        abort_unless($user->bolehKelolaKoperasi() || $user->isPimpinan(), 403);
    }

    private function terapkanPeriode($query, string $tahun, string $bulan, string $kolom): void
    {
        if ($tahun === 'default') {
            $query->whereYear($kolom, now()->year);
        } elseif ($tahun !== 'semua') {
            $query->whereYear($kolom, (int) $tahun);
        }
        if ($bulan !== 'default' && $bulan !== 'semua') {
            $query->whereMonth($kolom, (int) $bulan);
        }
    }

    private function periodeLabel(string $tahun, string $bulan): string
    {
        $namaBulan = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        $labelTahun = $tahun === 'default' ? (string) now()->year : ($tahun === 'semua' ? 'Semua Tahun' : $tahun);
        $labelBulan = ($bulan === 'default' || $bulan === 'semua') ? 'Semua Bulan' : $namaBulan[(int) $bulan];

        return $labelBulan . ' ' . $labelTahun;
    }

    private function periodeFile(string $tahun, string $bulan): string
    {
        $labelTahun = $tahun === 'default' ? (string) now()->year : ($tahun === 'semua' ? 'semua' : $tahun);
        $labelBulan = ($bulan === 'default' || $bulan === 'semua') ? 'semua' : str_pad($bulan, 2, '0', STR_PAD_LEFT);

        return $labelTahun . '-' . $labelBulan;
    }

    /**
     * Logo, tanda tangan, dan stempel untuk ditanam ke PDF.
     *
     * Ketiganya dilewatkan GambarCetak lebih dulu. Berkas aslinya diunggah
     * apa adanya dari pemindai — tanda tangan tercatat 6000 x 3375 px untuk
     * dicetak setinggi 40 px — sehingga setiap laporan koperasi membawa
     * ~1,6 MB gambar yang tidak pernah terlihat bedanya.
     *
     * Yang dipakai di sini SALINAN kecilnya. Berkas aslinya tidak disentuh,
     * dan kalau penyusutannya gagal, path aslinya yang dipakai — laporan
     * tetap terbit.
     */
    private function identitasPdf(): array
    {
        $setting = KoperasiSetting::current();
        $cetak = app(GambarCetak::class);

        return [
            'setting' => $setting,
            'logoPath' => $cetak->untukCetak(
                $setting->logo_koperasi
                    ? public_path('images/koperasi/' . $setting->logo_koperasi)
                    : public_path('images/logo-laporan.png')
            ),
            'ttdPath' => $cetak->untukCetak(
                $setting->ttd_koperasi ? public_path('images/koperasi/' . $setting->ttd_koperasi) : null
            ),
            'stempelPath' => $cetak->untukCetak(
                $setting->stempel_koperasi ? public_path('images/koperasi/' . $setting->stempel_koperasi) : null
            ),
        ];
    }
}
