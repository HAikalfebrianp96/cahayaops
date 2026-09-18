<?php

namespace App\Http\Controllers;

use App\Services\PenghapusanPermanenService;
use App\Models\BusinessUnit;
use App\Models\ProductService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * UNIT USAHA KOPERASI CAHAYA — data MASTER.
 *
 * Otorisasi lewat [BusinessUnitPolicy] -> User::bolehKelolaKoperasi()
 * (Admin & Tim Koperasi).
 *
 * Penghapusan memakai Soft Delete karena produk/jasa, penjualan, pengeluaran,
 * dan keanggotaan menunjuk ke tabel ini — dihapus permanen berarti memutus
 * seluruh riwayat itu sekaligus. Untuk unit yang sekadar berhenti beroperasi,
 * cara yang benar tetap mengubah statusnya menjadi "Tidak Aktif": datanya
 * tetap tampil di laporan, hanya tidak dipakai transaksi baru.
 */
class BusinessUnitController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('viewAny', BusinessUnit::class);

        $units = BusinessUnit::withCount('productsServices')
            ->orderBy('nama')
            ->get();

        return view('koperasi.unit-usaha.index', [
            'units' => $units,
        ]);
    }

    public function create()
    {
        Gate::authorize('create', BusinessUnit::class);

        return view('koperasi.unit-usaha.create', [
            'tipeOptions' => BusinessUnit::TIPE_OPTIONS,
            'statusOptions' => BusinessUnit::STATUS_OPTIONS,
        ]);
    }

    public function store(Request $request)
    {
        Gate::authorize('create', BusinessUnit::class);

        BusinessUnit::create($this->validated($request));

        return redirect()
            ->route('koperasi.unit-usaha.index')
            ->with('success', 'Unit usaha baru berhasil ditambahkan.');
    }

    public function show(BusinessUnit $unit)
    {
        Gate::authorize('view', $unit);

        $unit->load(['productsServices' => fn ($q) => $q->orderBy('nama')]);

        return view('koperasi.unit-usaha.show', [
            'unit' => $unit,
            'jenisOptions' => ProductService::JENIS_OPTIONS,
            'statusOptionsProduk' => ProductService::STATUS_OPTIONS,
            'kategoriSaran' => ProductService::KATEGORI_SARAN,
        ]);
    }

    public function edit(BusinessUnit $unit)
    {
        Gate::authorize('update', $unit);

        return view('koperasi.unit-usaha.edit', [
            'unit' => $unit,
            'tipeOptions' => BusinessUnit::TIPE_OPTIONS,
            'statusOptions' => BusinessUnit::STATUS_OPTIONS,
        ]);
    }

    public function update(Request $request, BusinessUnit $unit)
    {
        Gate::authorize('update', $unit);

        $unit->update($this->validated($request, $unit->id));

        return redirect()
            ->route('koperasi.unit-usaha.index')
            ->with('success', 'Data unit usaha berhasil diperbarui.');
    }

    /** SOFT DELETE — barisnya tetap ada, seluruh relasinya utuh. */
    public function destroy(BusinessUnit $unit)
    {
        Gate::authorize('delete', $unit);

        $penghapusan = app(PenghapusanPermanenService::class);
        $alasan = $penghapusan->alasanTertahan($unit);

        if ($alasan !== null) {
            // Bukan kegagalan sistem: ini keputusan yang disengaja, jadi
            // disampaikan sebagai penjelasan beserta apa yang menahannya.
            return back()->with('error', $alasan);
        }

        $ringkasan = $penghapusan->hapusDenganCatatan($unit);

        return redirect()
            ->route('koperasi.unit-usaha.index')
            ->with('success', 'Dihapus permanen: ' . $ringkasan . '. Data ini tidak dapat dipulihkan.');
    }

    private function validated(Request $request, ?int $unitId = null): array
    {
        return $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            // Unique BIASA — SENGAJA tanpa withoutTrashed().
            //
            // `business_units.kode` punya unique index di database yang tidak
            // mempedulikan `deleted_at`, jadi kode milik unit yang sudah
            // diarsipkan TETAP terpakai di level database. Kalau validasi di
            // sini dilonggarkan dengan withoutTrashed(), form akan lolos lalu
            // insert-nya gagal dengan error database mentah di depan pengguna.
            // Aturan di sini dibuat persis sama dengan aturan indexnya, dan
            // pesannya menjelaskan apa yang sebenarnya terjadi.
            'kode' => [
                'required', 'string', 'max:50',
                Rule::unique('business_units', 'kode')->ignore($unitId),
            ],
            'jenis_usaha' => ['nullable', 'string', 'max:255'],
            'tipe' => ['required', Rule::in(BusinessUnit::TIPE_OPTIONS)],
            'deskripsi' => ['nullable', 'string'],
            'alamat' => ['nullable', 'string'],
            'nomor_kontak' => ['nullable', 'string', 'max:30'],
            'status' => ['required', Rule::in(BusinessUnit::STATUS_OPTIONS)],
            'tanggal_mulai' => ['nullable', 'date'],
            'catatan' => ['nullable', 'string'],
        ], [
            'kode.unique' => 'Kode ini sudah dipakai unit usaha lain. Kalau unit pemiliknya sudah diarsipkan, pulihkan dulu unit itu atau pakai kode yang berbeda.',
        ]);
    }
}
