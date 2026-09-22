<?php

namespace App\Http\Controllers;

use App\Services\PenghapusanPermanenService;
use App\Models\BusinessUnit;
use App\Models\ProductService;
use App\Models\SalesTransactionItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProductServiceController extends Controller
{
    public function create(BusinessUnit $unit)
    {
        abort_unless(Auth::user()->bolehKelolaKoperasi(), 403);

        return view('koperasi.produk.create', [
            'unit' => $unit,
            'jenisOptions' => ProductService::JENIS_OPTIONS,
            'statusOptions' => ProductService::STATUS_OPTIONS,
            'kategoriSaran' => ProductService::KATEGORI_SARAN,
        ]);
    }

    public function store(Request $request, BusinessUnit $unit)
    {
        abort_unless(Auth::user()->bolehKelolaKoperasi(), 403);

        $validated = $this->validated($request, $unit->id);
        $validated['unit_id'] = $unit->id;

        ProductService::create($validated);

        return redirect()
            ->route('koperasi.unit-usaha.show', $unit)
            ->with('success', 'Produk/jasa baru berhasil ditambahkan.');
    }

    /**
     * Tambah produk baru LANGSUNG dari halaman Penjualan (AJAX), tanpa
     * meninggalkan halaman transaksi. Kode dibuat otomatis, minimal input.
     */
    public function quickStore(Request $request, BusinessUnit $unit)
    {
        abort_unless(Auth::user()->bolehKelolaKoperasi(), 403);

        $validated = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'jenis' => ['required', Rule::in(ProductService::JENIS_OPTIONS)],
            'satuan' => ['nullable', 'string', 'max:50'],
            'harga_jual' => ['required', 'numeric', 'min:0'],
            'harga_modal' => ['required', 'numeric', 'min:0'],
        ]);

        // Kodenya TIDAK dibuat di sini lagi.
        //
        // Dulu jalur cepat ini membuat kodenya sendiri (QP + tanggal + acak)
        // sementara form Tambah Produk menyuruh petugas mengetiknya — dua
        // jalur, dua pola kode, untuk data yang sama. Sekarang keduanya
        // lewat ProductService::booted().
        $produk = ProductService::create([
            'unit_id' => $unit->id,
            'nama' => $validated['nama'],
            'jenis' => $validated['jenis'],
            'satuan' => $validated['satuan'] ?? null,
            'harga_jual' => $validated['harga_jual'],
            'harga_modal' => $validated['harga_modal'],
            'status' => 'Aktif',
        ]);

        return response()->json([
            'id' => $produk->id,
            'nama' => $produk->nama,
            'jenis' => $produk->jenis,
            'satuan' => $produk->satuan ?: '-',
            'harga_jual' => (float) $produk->harga_jual,
            'harga_modal' => (float) $produk->harga_modal,
            // Produknya TETAP tersimpan — ini pengingat, bukan penolakan.
            'peringatan' => SalesTransactionItem::peringatanHpp(
                $produk->nama, (float) $produk->harga_modal, (float) $produk->harga_jual,
            ),
        ]);
    }

    /**
     * SOFT DELETE — produk/jasa dirujuk sales_transaction_items, jadi
     * menghapusnya permanen akan melubangi riwayat penjualan. Untuk produk
     * yang sekadar berhenti dijual, cara yang benar tetap mengubah statusnya
     * lewat Edit: ia tetap muncul di laporan lama, hanya tidak dipakai
     * transaksi baru.
     */
    public function destroy(ProductService $produk)
    {
        abort_unless(Auth::user()->bolehKelolaKoperasi(), 403);

        $unitId = $produk->unit_id;
        $penghapusan = app(PenghapusanPermanenService::class);
        $alasan = $penghapusan->alasanTertahan($produk);

        if ($alasan !== null) {
            // Bukan kegagalan sistem: ini keputusan yang disengaja, jadi
            // disampaikan sebagai penjelasan beserta apa yang menahannya.
            return back()->with('error', $alasan);
        }

        $ringkasan = $penghapusan->hapusDenganCatatan($produk);

        return redirect()
            ->route('koperasi.unit-usaha.show', $unitId)
            ->with('success', 'Dihapus permanen: ' . $ringkasan . '. Data ini tidak dapat dipulihkan.');
    }

    public function edit(ProductService $produk)
    {
        abort_unless(Auth::user()->bolehKelolaKoperasi(), 403);

        return view('koperasi.produk.edit', [
            'produk' => $produk,
            'unit' => $produk->businessUnit,
            'jenisOptions' => ProductService::JENIS_OPTIONS,
            'statusOptions' => ProductService::STATUS_OPTIONS,
            'kategoriSaran' => ProductService::KATEGORI_SARAN,
        ]);
    }

    public function update(Request $request, ProductService $produk)
    {
        abort_unless(Auth::user()->bolehKelolaKoperasi(), 403);

        $produk->update($this->validated($request, $produk->unit_id, $produk->id));

        return redirect()
            ->route('koperasi.unit-usaha.show', $produk->unit_id)
            ->with('success', 'Produk/jasa berhasil diperbarui.');
    }

    private function validated(Request $request, int $unitId, ?int $produkId = null): array
    {
        // 'kode' SENGAJA tidak ada di sini.
        //
        // Kode dibuat sistem (ProductService::booted) dan tidak pernah
        // berasal dari request. Kalau ia tetap divalidasi dari input, satu
        // permintaan buatan tangan bisa menyisipkan kode pilihan sendiri —
        // termasuk kode yang sudah beredar di nota untuk barang lain.
        //
        // Pada Edit, kode juga tidak ikut berubah: ia identitas produk.
        return $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'jenis' => ['required', Rule::in(ProductService::JENIS_OPTIONS)],
            'kategori' => ['nullable', 'string', 'max:255'],
            'deskripsi' => ['nullable', 'string'],
            'satuan' => ['nullable', 'string', 'max:50'],
            'harga_modal' => ['required', 'numeric', 'min:0'],
            'harga_jual' => ['required', 'numeric', 'min:0'],
            'status' => ['required', Rule::in(ProductService::STATUS_OPTIONS)],
            'stock_tracking' => ['sometimes', 'boolean'],
        ]);
    }
}
