<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\BusinessUnit;
use App\Models\Pengeluaran;
use App\Models\PengeluaranCategory;
use App\Services\PenghapusanPermanenService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class PengeluaranController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(Auth::user()->bolehKelolaKoperasi(), 403);

        $query = Pengeluaran::with('businessUnit')->orderByDesc('tanggal')->orderByDesc('id');
        $this->applyFilters($query, $request);

        $rows = $query->paginate(20)->withQueryString();

        // Ringkasan periode yang sama (SEMUA data yg cocok filter, bukan cuma 1 halaman), status Aktif saja.
        $summaryQuery = Pengeluaran::aktif();
        $this->applyFilters($summaryQuery, $request);
        $totalPeriode = (float) (clone $summaryQuery)->sum('nominal');

        // Dipecah dengan aturan yang SAMA dengan LabaRugiService: pembelian
        // stok tetap uang keluar, tapi tidak mengurangi laba (biayanya masuk
        // HPP saat barang terjual). Jumlah keduanya = $totalPeriode.
        $operasionalQuery = Pengeluaran::bebanUsaha();
        $this->applyFilters($operasionalQuery, $request);
        $totalOperasional = (float) $operasionalQuery->sum('nominal');

        $pembelianStokQuery = Pengeluaran::pembelianStok();
        $this->applyFilters($pembelianStokQuery, $request);
        $totalPembelianStok = (float) $pembelianStokQuery->sum('nominal');

        $breakdown = (clone $summaryQuery)
            ->selectRaw('business_unit_id, SUM(nominal) as total')
            ->groupBy('business_unit_id')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->business_unit_id ?? 'operasional' => (float) $row->total]);

        return view('koperasi.pengeluaran.index', [
            'rows' => $rows,
            'units' => BusinessUnit::orderBy('nama')->get(),
            // Filter kategori SEKARANG ambil dari nilai kategori yang BENAR-BENAR
            // pernah dipakai di data (gabungan master baru + histori lama) — bukan
            // daftar saran statis lagi, supaya filter selalu bisa cocok persis
            // dengan teks yang tersimpan di kolom 'kategori'.
            'kategoriSaran' => Pengeluaran::query()->select('kategori')->distinct()->orderBy('kategori')->pluck('kategori'),
            'metodeOptions' => Pengeluaran::METODE_OPTIONS,
            'totalPeriode' => $totalPeriode,
            'totalOperasional' => $totalOperasional,
            'totalPembelianStok' => $totalPembelianStok,
            'breakdown' => $breakdown,
            'filters' => $request->only(['tahun', 'bulan', 'unit_id', 'kategori', 'metode_pembayaran']),
        ]);
    }

    public function create()
    {
        abort_unless(Auth::user()->bolehKelolaKoperasi(), 403);

        return view('koperasi.pengeluaran.create', [
            'units' => BusinessUnit::orderBy('nama')->get(),
            'kategoriOptions' => PengeluaranCategory::aktif()->orderBy('nama')->get(),
            'metodeOptions' => Pengeluaran::METODE_OPTIONS,
        ]);
    }

    public function store(Request $request)
    {
        abort_unless(Auth::user()->bolehKelolaKoperasi(), 403);

        $validated = $this->validated($request, false);

        // SNAPSHOT GANDA: simpan relasi (pengeluaran_category_id) SEKALIGUS
        // teks nama kategori saat ini (kolom 'kategori') — supaya semua kode
        // lama yang baca kolom 'kategori' (Laporan, dll) tetap benar tanpa diubah.
        $kategori = PengeluaranCategory::findOrFail($validated['pengeluaran_category_id']);
        $validated['kategori'] = $kategori->nama;

        $validated['created_by'] = Auth::id();
        $this->handleBukti($request, $validated);

        Pengeluaran::create($validated);

        return redirect()->route('koperasi.pengeluaran.index')->with('success', 'Pengeluaran berhasil dicatat.');
    }

    public function show(Pengeluaran $pengeluaran)
    {
        abort_unless(Auth::user()->bolehKelolaKoperasi(), 403);

        return view('koperasi.pengeluaran.show', compact('pengeluaran'));
    }

    public function edit(Pengeluaran $pengeluaran)
    {
        abort_unless(Auth::user()->bolehKelolaKoperasi(), 403);

        $kategoriOptions = PengeluaranCategory::aktif()->orderBy('nama')->get();

        // Kalau kategori transaksi ini sudah TIDAK AKTIF (atau bukan bagian
        // dari daftar aktif krn alasan lain), tetap sisipkan sbg pilihan
        // supaya histori tidak "hilang" dari tampilan dropdown saat Edit.
        if ($pengeluaran->pengeluaran_category_id && ! $kategoriOptions->contains('id', $pengeluaran->pengeluaran_category_id)) {
            $kategoriLama = PengeluaranCategory::find($pengeluaran->pengeluaran_category_id);
            if ($kategoriLama) {
                $kategoriOptions->push($kategoriLama);
            }
        }

        return view('koperasi.pengeluaran.edit', [
            'pengeluaran' => $pengeluaran,
            'units' => BusinessUnit::orderBy('nama')->get(),
            'kategoriOptions' => $kategoriOptions,
            'metodeOptions' => Pengeluaran::METODE_OPTIONS,
        ]);
    }

    public function update(Request $request, Pengeluaran $pengeluaran)
    {
        abort_unless(Auth::user()->bolehKelolaKoperasi(), 403);

        $validated = $this->validated($request, true);

        if (! empty($validated['pengeluaran_category_id'])) {
            $kategori = PengeluaranCategory::findOrFail($validated['pengeluaran_category_id']);
            $validated['kategori'] = $kategori->nama; // snapshot ikut ter-update kalau kategori diganti
        } else {
            // Tidak pilih kategori (data lama tanpa master) — JANGAN timpa
            // kolom 'kategori' (text) yang sudah ada, biarkan seperti semula.
            unset($validated['pengeluaran_category_id'], $validated['kategori']);
        }

        $this->handleBukti($request, $validated, $pengeluaran);

        $pengeluaran->update($validated);

        return redirect()->route('koperasi.pengeluaran.show', $pengeluaran)->with('success', 'Pengeluaran berhasil diperbarui.');
    }

    /**
     * Batalkan pengeluaran — SOFT CANCEL, bukan hapus. Histori tetap ada,
     * tapi tidak dihitung lagi di total & ringkasan.
     */
    /**
     * BATALKAN pengeluaran.
     *
     * Sebelumnya method ini hanya mengubah satu kolom status, tanpa alasan,
     * tanpa pelaku, tanpa waktu, dan tanpa Activity Log — sehingga sebuah
     * pengeluaran bisa berhenti dihitung di laba rugi tanpa meninggalkan
     * satu pun keterangan yang bisa ditelusuri saat rekonsiliasi.
     *
     * Sekarang jejaknya disamakan dengan Penjualan dan Simpanan, supaya
     * operator tidak perlu mempelajari tiga perilaku berbeda untuk satu hal
     * yang sama.
     */
    public function cancel(Request $request, Pengeluaran $pengeluaran)
    {
        abort_unless(Auth::user()->bolehKelolaKoperasi(), 403);

        abort_if(
            $pengeluaran->dibatalkan(),
            403,
            'Pengeluaran ini sudah dibatalkan.'
        );

        $validated = $request->validate([
            'alasan_pembatalan' => ['required', 'string', 'max:1000'],
        ], [
            'alasan_pembatalan.required' => 'Alasan pembatalan wajib diisi — inilah yang membuat pembatalan bisa dipertanggungjawabkan.',
        ]);

        $pengeluaran->forceFill([
            'status' => Pengeluaran::STATUS_DIBATALKAN,
            'alasan_pembatalan' => $validated['alasan_pembatalan'],
            'dibatalkan_at' => now(),
            'dibatalkan_oleh' => Auth::id(),
        ])->save();

        ActivityLog::record(
            action: 'pengeluaran_cancelled',
            modelType: 'Pengeluaran',
            modelId: $pengeluaran->id,
            description: 'Pengeluaran ' . rupiah($pengeluaran->nominal) . ' (' . $pengeluaran->kategori . ') dibatalkan. Alasan: ' . $validated['alasan_pembatalan'],
        );

        return redirect()->route('koperasi.pengeluaran.show', $pengeluaran)
            ->with('success', 'Pengeluaran dibatalkan. Datanya tetap tersimpan dan berhenti dihitung di laba rugi.');
    }

    /**
     * PULIHKAN pengeluaran yang dibatalkan.
     *
     * Sama seperti pemulihan transaksi penjualan: status adalah SATU nilai,
     * bukan penanda yang ditambahkan, jadi memulihkan tidak mungkin membuat
     * satu pengeluaran terhitung dua kali di laba rugi. abort_if di bawah
     * memastikan pengeluaran yang memang aktif tidak bisa tertimpa diam-diam.
     */
    public function pulihkan(Pengeluaran $pengeluaran)
    {
        abort_unless(Auth::user()->bolehKelolaKoperasi(), 403);

        abort_unless(
            $pengeluaran->dibatalkan(),
            403,
            'Pengeluaran ini tidak sedang dibatalkan, jadi tidak ada yang perlu dipulihkan.'
        );

        $alasanLama = $pengeluaran->alasan_pembatalan;

        $pengeluaran->forceFill([
            'status' => Pengeluaran::STATUS_AKTIF,
            'alasan_pembatalan' => null,
            'dibatalkan_at' => null,
            'dibatalkan_oleh' => null,
        ])->save();

        ActivityLog::record(
            action: 'pengeluaran_restored',
            modelType: 'Pengeluaran',
            modelId: $pengeluaran->id,
            description: 'Pengeluaran ' . rupiah($pengeluaran->nominal) . ' dipulihkan dan kembali dihitung di laba rugi.'
                . ($alasanLama ? ' Alasan pembatalan sebelumnya: ' . $alasanLama : ''),
        );

        return redirect()->route('koperasi.pengeluaran.show', $pengeluaran)
            ->with('success', 'Pengeluaran dipulihkan dan kembali dihitung di laba rugi.');
    }

    /**
     * Tampilkan bukti (gambar/PDF) langsung di browser, bukan paksa download.
     */
    public function bukti(Pengeluaran $pengeluaran)
    {
        abort_unless(Auth::user()->bolehKelolaKoperasi(), 403);
        abort_unless($pengeluaran->bukti_path && Storage::exists($pengeluaran->bukti_path), 404);

        return Storage::response($pengeluaran->bukti_path);
    }

    private function applyFilters($query, Request $request): void
    {
        $tahun = $request->input('tahun', 'default');
        $bulan = $request->input('bulan', 'default');

        if ($tahun === 'default') {
            $query->whereYear('tanggal', now()->year);
        } elseif ($tahun !== 'semua') {
            $query->whereYear('tanggal', (int) $tahun);
        }

        if ($bulan === 'default') {
            $query->whereMonth('tanggal', now()->month);
        } elseif ($bulan !== 'semua') {
            $query->whereMonth('tanggal', (int) $bulan);
        }

        if ($request->filled('unit_id')) {
            if ($request->input('unit_id') === 'operasional') {
                $query->whereNull('business_unit_id');
            } else {
                $query->where('business_unit_id', $request->integer('unit_id'));
            }
        }

        if ($request->filled('kategori')) {
            $query->where('kategori', $request->string('kategori'));
        }

        if ($request->filled('metode_pembayaran')) {
            $query->where('metode_pembayaran', $request->string('metode_pembayaran'));
        }
    }

    /**
     * HAPUS PERMANEN satu pengeluaran.
     *
     * =====================================================================
     * HARUS DIBATALKAN DULU
     * =====================================================================
     *
     * Penjaganya (PenghapusanPermanenService) menolak pengeluaran yang masih
     * aktif: "Batalkan dulu (beserta alasannya), baru bisa dihapus permanen."
     *
     * Urutan itu disengaja dan sama dengan transaksi penjualan. Batalkan
     * menuntut ALASAN, dan alasan itu tercatat di Activity Log — tanpa
     * langkah itu, satu-satunya keterangan tentang uang yang hilang dari
     * laporan adalah kata "dihapus".
     *
     * Berkas bukti (bukti_path) ikut dibuang oleh layanan itu, sehingga
     * struk belanjanya tidak tertinggal di disk tanpa satu pun baris yang
     * menunjuknya.
     */
    public function destroy(Pengeluaran $pengeluaran, PenghapusanPermanenService $penghapusan)
    {
        abort_unless(Auth::user()->bolehKelolaKoperasi(), 403);

        $alasan = $penghapusan->alasanTertahan($pengeluaran);

        if ($alasan !== null) {
            return back()->withErrors(['hapus' => $alasan]);
        }

        // Diambil SEBELUM dihapus; sesudahnya tidak ada yang tersisa untuk
        // dirujuk. Tidak ada data rahasia di sini.
        $id = $pengeluaran->id;
        $ringkasan = $pengeluaran->kategori . ' ' . rupiah($pengeluaran->nominal)
            . ' (' . $pengeluaran->tanggal->format('d-m-Y') . ')';

        DB::transaction(function () use ($pengeluaran, $penghapusan) {
            $penghapusan->hapus($pengeluaran);
        });

        ActivityLog::record(
            action: 'expense_permanently_deleted',
            modelType: 'Pengeluaran',
            modelId: $id,
            description: 'PENGELUARAN DIHAPUS PERMANEN: ' . $ringkasan
                . '. Tindakan ini tidak dapat dibatalkan.',
        );

        return redirect()
            ->route('koperasi.pengeluaran.index')
            ->with('success', 'Pengeluaran ' . $ringkasan . ' dihapus permanen.');
    }

    private function validated(Request $request, bool $isUpdate = false): array
    {
        // CREATE: kategori WAJIB, harus ID valid & berstatus Aktif (cegah ID
        // palsu/nonaktif dari request manual, sesuai instruksi keamanan).
        // UPDATE: boleh kosong (data lama tanpa master tetap bisa diedit),
        // tapi kalau diisi, ID-nya tetap harus valid (statusnya BOLEH nonaktif
        // — supaya kategori lama yg sudah dinonaktifkan tetap bisa disimpan
        // ulang tanpa dipaksa ganti kategori baru).
        $kategoriRule = $isUpdate
            ? ['nullable', Rule::exists('pengeluaran_categories', 'id')]
            : ['required', Rule::exists('pengeluaran_categories', 'id')->where('status', 'Aktif')];

        return $request->validate([
            'tanggal' => ['required', 'date'],
            'business_unit_id' => ['nullable', 'exists:business_units,id'], // kosong = Operasional Koperasi
            'pengeluaran_category_id' => $kategoriRule,
            'nominal' => ['required', 'numeric', 'min:0.01'],
            'metode_pembayaran' => ['required', 'in:Cash,Transfer,QRIS,Lainnya'],
            'keterangan' => ['nullable', 'string'],
            'bukti' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:4096'],
        ]);
    }

    private function handleBukti(Request $request, array &$validated, ?Pengeluaran $existing = null): void
    {
        unset($validated['bukti']);

        if (! $request->hasFile('bukti')) {
            return;
        }

        if ($existing && $existing->bukti_path) {
            Storage::delete($existing->bukti_path);
        }

        $file = $request->file('bukti');
        $validated['bukti_path'] = $file->store('bukti-pengeluaran');
        $validated['bukti_asli'] = $file->getClientOriginalName();
    }
}
