<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Exports\SimpleArrayExport;
use App\Models\AkunKas;
use App\Models\RekonsiliasiKas;
use App\Services\KasBankService;
use App\Services\PenghapusanPermanenService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

/**
 * KAS & BANK — TAHAP 1: daftar akun dan saldo awalnya.
 *
 * Hak akses sama dengan menu Keuangan (bolehKelolaKoperasi): Admin dan Tim
 * Koperasi. Saldo awal adalah angka uang, jadi setiap penetapan dan
 * perubahannya dicatat ke Activity Log lengkap dengan nilai sebelum ->
 * sesudah — tanpa itu, total kas bisa bergeser tanpa ada yang bisa
 * menjelaskan sebabnya.
 */
class AkunKasController extends Controller
{
    public function index(KasBankService $kas)
    {
        abort_unless(Auth::user()->bolehKelolaKoperasi(), 403);

        return view('koperasi.kas-bank.index', ['ringkasan' => $kas->ringkasan()]);
    }

    /**
     * BUKU MUTASI satu akun + riwayat hitung fisiknya.
     *
     * Periode bawaan: awal bulan ini sampai hari ini.
     */
    public function show(Request $request, AkunKas $akun, KasBankService $kas)
    {
        abort_unless(Auth::user()->bolehKelolaKoperasi(), 403);

        [$dari, $sampai] = $this->periode($request);

        return view('koperasi.kas-bank.show', [
            'akun' => $akun,
            'saldoKini' => $kas->saldo($akun),
            'mutasi' => $kas->bukuMutasi($akun, $dari, $sampai),
            'rekonsiliasi' => $akun->rekonsiliasi()->with('creator')->latest('tanggal')->latest('id')->limit(20)->get(),
        ]);
    }

    public function excel(Request $request, AkunKas $akun, KasBankService $kas)
    {
        abort_unless(Auth::user()->bolehKelolaKoperasi(), 403);

        [$dari, $sampai] = $this->periode($request);
        $m = $kas->bukuMutasi($akun, $dari, $sampai);

        $rows = [
            ['BUKU MUTASI ' . strtoupper($akun->nama)],
            ['Periode', Carbon::parse($m['dari_efektif'])->format('d-m-Y') . ' s/d ' . Carbon::parse($sampai)->format('d-m-Y')],
            [],
            ['Tanggal', 'Jenis', 'Uraian', 'Masuk (Rp)', 'Keluar (Rp)', 'Saldo (Rp)'],
            ['', '', 'Saldo awal periode', '', '', $m['saldo_pembuka']],
        ];
        foreach ($m['baris'] as $b) {
            $rows[] = [Carbon::parse($b['tanggal'])->format('d-m-Y'), $b['jenis'], $b['uraian'], $b['masuk'] ?: '', $b['keluar'] ?: '', $b['saldo']];
        }
        $rows[] = ['', '', 'TOTAL / SALDO AKHIR', $m['total_masuk'], $m['total_keluar'], $m['saldo_penutup']];

        return Excel::download(
            new SimpleArrayExport($rows, 'Buku Mutasi'),
            'buku-mutasi-' . \Illuminate\Support\Str::slug($akun->nama) . '-' . $m['dari_efektif'] . '-' . $sampai . '.xlsx'
        );
    }

    /**
     * HITUNG FISIK — cocokkan saldo sistem dengan uang yang sungguh ada.
     *
     * Saldo sistem dihitung ulang di sini (bukan dari form) lalu dibekukan
     * di barisnya. Selisih yang bukan nol WAJIB diberi catatan: selisih tanpa
     * keterangan tidak bisa ditindaklanjuti siapa pun.
     */
    public function rekonsiliasi(Request $request, AkunKas $akun, KasBankService $kas)
    {
        abort_unless(Auth::user()->bolehKelolaKoperasi(), 403);

        $validated = $request->validateWithBag('rekonsiliasi', [
            'tanggal' => ['required', 'date', 'before_or_equal:today'],
            'saldo_fisik' => ['required', 'numeric'],
            'catatan' => ['nullable', 'string', 'max:1000'],
        ], [
            'tanggal.before_or_equal' => 'Tanggal hitung fisik tidak boleh di masa depan.',
        ]);

        $saldoSistem = $kas->saldo($akun, Carbon::parse($validated['tanggal'])->toDateString());
        $selisih = round((float) $validated['saldo_fisik'] - $saldoSistem, 2);

        if ($selisih != 0.0 && blank($validated['catatan'] ?? null)) {
            throw ValidationException::withMessages([
                'catatan' => 'Ada selisih ' . rupiah($selisih) . '. Tuliskan dugaan penyebabnya supaya bisa ditelusuri.',
            ])->errorBag('rekonsiliasi');
        }

        $baris = new RekonsiliasiKas([
            'tanggal' => $validated['tanggal'],
            'saldo_sistem' => $saldoSistem,
            'saldo_fisik' => $validated['saldo_fisik'],
            'selisih' => $selisih,
            'catatan' => $validated['catatan'] ?? null,
        ]);
        $baris->akun_kas_id = $akun->id;
        $baris->created_by = Auth::id();
        $baris->save();

        ActivityLog::record(
            action: 'kas_reconciled',
            modelType: 'RekonsiliasiKas',
            modelId: $baris->id,
            description: 'Hitung fisik ' . $akun->nama . ' per ' . $baris->tanggal->format('d-m-Y')
                . ': sistem ' . rupiah($saldoSistem) . ', fisik ' . rupiah($baris->saldo_fisik)
                . ', selisih ' . rupiah($selisih) . '.'
                . ($baris->catatan ? ' Catatan: ' . $baris->catatan : ''),
        );

        return redirect()
            ->route('koperasi.kas-bank.show', $akun)
            ->with($selisih == 0.0 ? 'success' : 'warning', $selisih == 0.0
                ? 'Hitung fisik cocok dengan saldo sistem.'
                : 'Hitung fisik dicatat dengan selisih ' . rupiah($selisih) . '. Telusuri catatan uang di sekitar tanggal tersebut.');
    }

    /** @return array{0: string, 1: string} */
    private function periode(Request $request): array
    {
        $request->validate([
            'dari' => ['nullable', 'date'],
            'sampai' => ['nullable', 'date'],
        ]);

        $dari = $request->filled('dari') ? Carbon::parse($request->input('dari')) : now()->startOfMonth();
        $sampai = $request->filled('sampai') ? Carbon::parse($request->input('sampai')) : now();

        if ($sampai->lt($dari)) {
            [$dari, $sampai] = [$sampai, $dari];
        }

        return [$dari->toDateString(), $sampai->toDateString()];
    }

    public function create()
    {
        abort_unless(Auth::user()->bolehKelolaKoperasi(), 403);

        return view('koperasi.kas-bank.create');
    }

    public function store(Request $request)
    {
        abort_unless(Auth::user()->bolehKelolaKoperasi(), 403);

        $validated = $this->validated($request);

        $akun = new AkunKas($validated);
        $akun->status = AkunKas::STATUS_AKTIF;
        $akun->created_by = Auth::id();
        $akun->save();

        ActivityLog::record(
            action: 'created',
            modelType: 'AkunKas',
            modelId: $akun->id,
            description: 'Akun ' . $akun->jenis . ' "' . $akun->nama . '" dibuat. ' . $this->uraianSaldoAwal($akun),
        );

        return redirect()->route('koperasi.kas-bank.index')->with('success', 'Akun "' . $akun->nama . '" berhasil ditambahkan.');
    }

    public function edit(AkunKas $akun)
    {
        abort_unless(Auth::user()->bolehKelolaKoperasi(), 403);

        return view('koperasi.kas-bank.edit', compact('akun'));
    }

    public function update(Request $request, AkunKas $akun)
    {
        abort_unless(Auth::user()->bolehKelolaKoperasi(), 403);

        $validated = $this->validated($request, $akun);

        $sebelum = $this->uraianSaldoAwal($akun);
        $namaSebelum = $akun->nama;

        $akun->fill($validated);
        $saldoBerubah = $akun->isDirty(['saldo_awal', 'tanggal_saldo_awal']);
        $akun->save();

        $keterangan = 'Akun "' . $namaSebelum . '" diperbarui.';
        if ($namaSebelum !== $akun->nama) {
            $keterangan .= ' Nama: "' . $namaSebelum . '" -> "' . $akun->nama . '".';
        }
        if ($saldoBerubah) {
            $keterangan .= ' SALDO AWAL BERUBAH: ' . $sebelum . ' -> ' . $this->uraianSaldoAwal($akun);
        }

        ActivityLog::record(
            action: 'updated',
            modelType: 'AkunKas',
            modelId: $akun->id,
            description: $keterangan,
        );

        return redirect()->route('koperasi.kas-bank.index')->with('success', 'Akun "' . $akun->nama . '" berhasil diperbarui.');
    }

    /**
     * Nonaktifkan/Aktifkan — cara yang benar untuk akun yang tidak dipakai
     * lagi (rekening ditutup, dsb.). Barisnya tetap ada supaya riwayat uang
     * yang pernah lewat akun ini masih bisa ditelusuri.
     */
    public function toggleStatus(AkunKas $akun)
    {
        abort_unless(Auth::user()->bolehKelolaKoperasi(), 403);

        $akun->update([
            'status' => $akun->isAktif() ? AkunKas::STATUS_TIDAK_AKTIF : AkunKas::STATUS_AKTIF,
        ]);

        ActivityLog::record(
            action: 'updated',
            modelType: 'AkunKas',
            modelId: $akun->id,
            description: 'Akun "' . $akun->nama . '" sekarang ' . strtolower($akun->status) . '.',
        );

        return redirect()
            ->route('koperasi.kas-bank.index')
            ->with('success', 'Akun "' . $akun->nama . '" sekarang ' . strtolower($akun->status) . '.');
    }

    /**
     * Hapus permanen — untuk akun yang memang SALAH dibuat. Penjaganya ada
     * di PenghapusanPermanenService, sama seperti modul lain.
     */
    public function destroy(AkunKas $akun, PenghapusanPermanenService $penghapusan)
    {
        abort_unless(Auth::user()->bolehKelolaKoperasi(), 403);

        $alasan = $penghapusan->alasanTertahan($akun);

        if ($alasan !== null) {
            return back()->with('error', $alasan);
        }

        $ringkasan = $penghapusan->hapusDenganCatatan($akun);

        return redirect()
            ->route('koperasi.kas-bank.index')
            ->with('success', 'Dihapus permanen: ' . $ringkasan . '. Data ini tidak dapat dipulihkan.');
    }

    private function validated(Request $request, ?AkunKas $akun = null): array
    {
        $validated = $request->validate([
            'nama' => ['required', 'string', 'max:255', Rule::unique('akun_kas', 'nama')->ignore($akun?->id)],
            'jenis' => ['required', Rule::in(AkunKas::JENIS_OPTIONS)],
            'nomor_rekening' => ['nullable', 'string', 'max:50'],
            'atas_nama' => ['nullable', 'string', 'max:255'],
            'saldo_awal' => ['nullable', 'numeric', 'min:0'],
            // Saldo awal selalu berlaku PER TANGGAL tertentu. Nominal tanpa
            // tanggal tidak bisa dipakai sebagai titik awal perhitungan kas.
            'tanggal_saldo_awal' => [
                'nullable', 'date', 'before_or_equal:today',
                Rule::requiredIf(fn () => (float) $request->input('saldo_awal') > 0),
            ],
            'keterangan' => ['nullable', 'string', 'max:1000'],
        ], [
            'tanggal_saldo_awal.required' => 'Tanggal saldo awal wajib diisi bila saldo awal lebih dari Rp0.',
            'tanggal_saldo_awal.before_or_equal' => 'Tanggal saldo awal tidak boleh di masa depan.',
        ]);

        $validated['nama'] = trim($validated['nama']);
        $validated['saldo_awal'] = (float) ($validated['saldo_awal'] ?? 0);

        // Nomor rekening hanya berarti untuk akun Bank.
        if ($validated['jenis'] === AkunKas::JENIS_KAS) {
            $validated['nomor_rekening'] = null;
            $validated['atas_nama'] = null;
        }

        return $validated;
    }

    private function uraianSaldoAwal(AkunKas $akun): string
    {
        return $akun->saldoAwalDitetapkan()
            ? 'Saldo awal ' . rupiah($akun->saldo_awal) . ' per ' . $akun->tanggal_saldo_awal->format('d-m-Y') . '.'
            : 'Saldo awal belum ditetapkan.';
    }
}
