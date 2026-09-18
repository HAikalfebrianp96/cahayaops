<?php

namespace App\Http\Controllers;

use App\Models\BusinessUnit;
use App\Models\ProductService;
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

        $kode = 'QP' . now()->format('ymd') . strtoupper(Str::random(4));

        $produk = ProductService::create([
            'unit_id' => $unit->id,
            'kode' => $kode,
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
        $produk->delete();

        return redirect()
            ->route('koperasi.unit-usaha.show', $unitId)
            ->with('success', 'Produk/jasa "' . $produk->nama . '" dipindahkan ke arsip. Riwayat penjualannya tetap tersimpan.');
    }

    /** Membatalkan Soft Delete. Route-nya memakai withTrashed(). */
    public function restore(ProductService $produk)
    {
        abort_unless(Auth::user()->bolehKelolaKoperasi(), 403);

        $produk->restore();

        return redirect()
            ->route('koperasi.unit-usaha.show', $produk->unit_id)
            ->with('success', 'Produk/jasa "' . $produk->nama . '" berhasil dipulihkan.');
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
        return $request->validate([
            'kode' => [
                'required', 'string', 'max:50',
                Rule::unique('products_services', 'kode')->where('unit_id', $unitId)->ignore($produkId),
            ],
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
