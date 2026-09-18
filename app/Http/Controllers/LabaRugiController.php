<?php

namespace App\Http\Controllers;

use App\Models\BusinessUnit;
use App\Models\DanaHibahKeluar;
use App\Models\DanaHibahMasuk;
use App\Models\DanaSukarelaKeluar;
use App\Models\DanaSukarelaMasuk;
use App\Models\MemberSaving;
use App\Models\Pengeluaran;
use App\Models\SalesTransaction;
use App\Services\LabaRugiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LabaRugiController extends Controller
{
    public function index(Request $request, LabaRugiService $labaRugi)
    {
        abort_unless(Auth::user()->bolehKelolaKoperasi(), 403);

        $tahun = $request->input('tahun', 'default');
        $bulan = $request->input('bulan', 'default');
        $unitId = $request->input('unit_id');

        // ==========================================================
        // BUKU 1 — KEUANGAN USAHA (Laba Rugi, satu sumber lewat LabaRugiService)
        // ==========================================================
        $lr = $labaRugi->hitung($tahun, $bulan, $unitId);

        $units = BusinessUnit::orderBy('nama')->get();

        // Rekap Penjualan (jumlah transaksi saja — angka lain sudah dari $lr)
        $transaksiQuery = SalesTransaction::whereNotIn('status_transaksi', ['Draft', 'Dibatalkan']);
        if ($tahun === 'default') {
            $transaksiQuery->whereYear('tanggal', now()->year);
        } elseif ($tahun !== 'semua') {
            $transaksiQuery->whereYear('tanggal', (int) $tahun);
        }
        if ($bulan !== 'default' && $bulan !== 'semua') {
            $transaksiQuery->whereMonth('tanggal', (int) $bulan);
        }
        if ($unitId && $unitId !== 'operasional') {
            $transaksiQuery->where('unit_id', $unitId);
        }
        $jumlahTransaksi = $transaksiQuery->count();

        // Rekap Pengeluaran per kategori
        $pengeluaranKategoriQuery = Pengeluaran::aktif();
        if ($tahun === 'default') {
            $pengeluaranKategoriQuery->whereYear('tanggal', now()->year);
        } elseif ($tahun !== 'semua') {
            $pengeluaranKategoriQuery->whereYear('tanggal', (int) $tahun);
        }
        if ($bulan !== 'default' && $bulan !== 'semua') {
            $pengeluaranKategoriQuery->whereMonth('tanggal', (int) $bulan);
        }
        $rekapKategori = $pengeluaranKategoriQuery
            ->selectRaw('kategori, SUM(nominal) as total')
            ->groupBy('kategori')
            ->orderByDesc('total')
            ->get();

        // ==========================================================
        // BUKU 3 — DANA SOSIAL (Dana Sukarela + Dana Hibah) — read-only di sini,
        // penulisannya tetap lewat DanaSukarelaController & DanaHibahController.
        // ==========================================================
        $totalMasuk = (float) DanaSukarelaMasuk::berlaku()->sum('nominal');
        $totalKeluar = (float) DanaSukarelaKeluar::berlaku()->sum('nominal');
        $saldo = $totalMasuk - $totalKeluar;

        $riwayatMasuk = DanaSukarelaMasuk::berlaku()->orderByDesc('tanggal')->orderByDesc('id')->limit(20)->get();
        $riwayatKeluar = DanaSukarelaKeluar::berlaku()->orderByDesc('tanggal')->orderByDesc('id')->limit(20)->get();

        $totalHibahMasuk = (float) DanaHibahMasuk::berlaku()->sum('nominal');
        $totalHibahKeluar = (float) DanaHibahKeluar::berlaku()->sum('nominal');
        $saldoHibah = $totalHibahMasuk - $totalHibahKeluar;

        $riwayatHibahMasuk = DanaHibahMasuk::berlaku()->orderByDesc('tanggal')->orderByDesc('id')->limit(20)->get();
        $riwayatHibahKeluar = DanaHibahKeluar::berlaku()->orderByDesc('tanggal')->orderByDesc('id')->limit(20)->get();

        // ==========================================================
        // BUKU 2 — DANA ANGGOTA (Simpanan Pokok + Wajib) — read-only.
        // ==========================================================
        // berlaku(): entri yang sudah dibatalkan tidak ikut dihitung.
        $totalSimpananPokok = (float) MemberSaving::berlaku()->where('jenis_simpanan', 'Simpanan Pokok')->sum('nominal');
        $totalSimpananWajib = (float) MemberSaving::berlaku()->where('jenis_simpanan', 'Simpanan Wajib')->sum('nominal');

        // Bagian dari dua angka di atas yang pemiliknya sudah tidak lagi
        // menjadi anggota. Angkanya TIDAK dikurangi — mengarsipkan anggota
        // tidak mengembalikan uangnya — yang ditambahkan hanya keterangannya.
        $simpananTerarsip = (float) MemberSaving::berlaku()
            ->milikAnggotaTerarsip()
            ->whereIn('jenis_simpanan', ['Simpanan Pokok', 'Simpanan Wajib'])
            ->sum('nominal');

        return view('koperasi.keuangan.index', [
            'lr' => $lr,
            'units' => $units,
            'jumlahTransaksi' => $jumlahTransaksi,
            'rekapKategori' => $rekapKategori,
            'totalMasuk' => $totalMasuk, 'totalKeluar' => $totalKeluar, 'saldo' => $saldo,
            'riwayatMasuk' => $riwayatMasuk, 'riwayatKeluar' => $riwayatKeluar,
            'totalHibahMasuk' => $totalHibahMasuk, 'totalHibahKeluar' => $totalHibahKeluar, 'saldoHibah' => $saldoHibah,
            'riwayatHibahMasuk' => $riwayatHibahMasuk, 'riwayatHibahKeluar' => $riwayatHibahKeluar,
            'totalSimpananPokok' => $totalSimpananPokok, 'totalSimpananWajib' => $totalSimpananWajib,
            'simpananTerarsip' => $simpananTerarsip,
            'filters' => $request->only(['tahun', 'bulan', 'unit_id']),
        ]);
    }
}
