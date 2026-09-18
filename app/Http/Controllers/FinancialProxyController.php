<?php

namespace App\Http\Controllers;

use App\Models\FinancialProxy;
use App\Models\ImpactProgram;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/**
 * MASTER PROXY FINANSIAL.
 *
 * Modul kecil dengan tanggung jawab besar: setiap rupiah "nilai sosial" yang
 * pernah dilaporkan lembaga ini berasal dari salah satu baris di sini.
 *
 * Dua keputusan yang menempel pada modul ini:
 *
 * 1. TIDAK ADA DATA BAWAAN. Sistem lahir dengan daftar kosong. Angka contoh
 *    akan dipakai apa adanya, dan rasio yang berdiri di atas angka contoh
 *    adalah karangan yang terlihat resmi.
 *
 * 2. TIDAK ADA HARD DELETE. Proxy yang tidak lagi dipakai DINONAKTIFKAN
 *    (kolom aktif) atau diarsipkan, tidak dihapus — karena laporan lama
 *    masih menyebut namanya, dan pertanyaan "proxy apa yang kita pakai dua
 *    tahun lalu" harus selalu punya jawaban.
 */
class FinancialProxyController extends Controller
{
    public function index()
    {
        Gate::authorize('viewAny', ImpactProgram::class);

        return view('impact.proxy.index', [
            'proxies' => FinancialProxy::with('pembuat')->orderByDesc('aktif')->orderBy('nama')->paginate(20),
            'bisaKelola' => Auth::user()->can('manage', ImpactProgram::class),
        ]);
    }

    public function create()
    {
        Gate::authorize('manage', ImpactProgram::class);

        return view('impact.proxy.form', [
            'proxy' => null,
            'indikatorPilihan' => \App\Models\ImpactOutcome::indikatorPilihan(),
        ]);
    }

    public function store(Request $request)
    {
        Gate::authorize('manage', ImpactProgram::class);

        FinancialProxy::create($this->validasi($request) + ['dibuat_oleh' => Auth::id()]);

        return redirect()
            ->route('impact.proxy.index')
            ->with('success', 'Proxy finansial berhasil ditambahkan.');
    }

    public function edit(FinancialProxy $proxy)
    {
        Gate::authorize('manage', ImpactProgram::class);

        return view('impact.proxy.form', [
            'proxy' => $proxy,
            'indikatorPilihan' => \App\Models\ImpactOutcome::indikatorPilihan(),
        ]);
    }

    /**
     * Memperbarui proxy TIDAK mengubah laporan yang sudah terbit.
     *
     * Nilai yang dipakai setiap perhitungan sudah dibekukan di kolom rincian
     * milik sroi_calculations — pola yang sama dengan harga_modal_snapshot
     * pada transaksi penjualan. Jadi memperbaiki angka yang keliru di sini
     * aman dilakukan kapan saja; yang berubah hanya perhitungan BERIKUTNYA.
     */
    public function update(Request $request, FinancialProxy $proxy)
    {
        Gate::authorize('manage', ImpactProgram::class);

        $proxy->update($this->validasi($request));

        return redirect()
            ->route('impact.proxy.index')
            ->with('success', 'Proxy finansial berhasil diperbarui. Laporan SROI yang sudah terbit tidak berubah.');
    }

    /** Nonaktifkan / aktifkan — proxy tetap ada, hanya tidak lagi ditawarkan. */
    public function toggle(FinancialProxy $proxy)
    {
        Gate::authorize('manage', ImpactProgram::class);

        $proxy->update(['aktif' => ! $proxy->aktif]);

        return back()->with('success', 'Proxy "' . $proxy->nama . '" kini '
            . ($proxy->aktif ? 'aktif dan bisa dipilih.' : 'nonaktif dan tidak lagi ditawarkan di form outcome.'));
    }

    public function destroy(FinancialProxy $proxy)
    {
        Gate::authorize('manage', ImpactProgram::class);

        // Proxy yang masih dirujuk outcome TIDAK boleh diarsipkan: outcome-nya
        // akan kehilangan nilai rupiah dan perhitungan berikutnya diam-diam
        // memakai 0. Lebih baik menolak dengan alasan yang jelas.
        if ($proxy->outcomes()->exists()) {
            return back()->with('error',
                'Proxy ini masih dipakai outcome. Nonaktifkan saja agar tidak lagi ditawarkan, '
                . 'tanpa memutus outcome yang sudah memakainya.');
        }

        $proxy->delete();

        return redirect()
            ->route('impact.proxy.index')
            ->with('success', 'Proxy dipindahkan ke Arsip.');
    }

    public function restore(int $proxy)
    {
        Gate::authorize('manage', ImpactProgram::class);

        FinancialProxy::withTrashed()->findOrFail($proxy)->restore();

        return back()->with('success', 'Proxy berhasil dipulihkan.');
    }

    private function validasi(Request $request): array
    {
        return $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            // Outcome & indikator OPSIONAL: sebuah proxy memang bisa dipakai
            // lintas outcome, dan mewajibkannya akan memaksa Admin mengarang
            // keterkaitan yang belum tentu ada. Yang diisi dipakai menandai
            // ketidakcocokan saat proxy dipasang ke outcome.
            'outcome' => ['nullable', 'string', 'max:255'],
            'indikator' => ['nullable', 'string', 'max:255'],
            'satuan' => ['required', 'string', 'max:255'],
            'nilai' => ['required', 'integer', 'min:1'],
            // Sumber & tahun WAJIB. Proxy tanpa keduanya tidak bisa diperiksa
            // siapa pun, dan yang tidak bisa diperiksa tidak layak dipakai
            // melapor ke pemberi dana.
            'sumber' => ['required', 'string', 'max:255'],
            'tahun' => ['required', 'integer', 'min:2000', 'max:' . now()->year],
            'alasan' => ['nullable', 'string'],
            'catatan' => ['nullable', 'string'],
            'aktif' => ['nullable', 'boolean'],
        ], [
            'nama.required' => 'Nama proxy wajib diisi.',
            'satuan.required' => 'Satuan wajib diisi, mis. "per UMKM per tahun".',
            'nilai.min' => 'Nilai proxy harus lebih dari nol.',
            'sumber.required' => 'Sumber wajib diisi — proxy tanpa sumber tidak bisa dipertanggungjawabkan.',
            'tahun.required' => 'Tahun sumber wajib diisi.',
            'tahun.max' => 'Tahun sumber tidak boleh di masa depan.',
        ]);
    }
}
