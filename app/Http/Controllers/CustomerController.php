<?php

namespace App\Http\Controllers;

use App\Services\PenghapusanPermanenService;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * PELANGGAN KOPERASI CAHAYA.
 *
 * Sebelumnya modul ini hanya punya satu jalur: quick-add dari halaman
 * Penjualan. Pelanggan bisa DIBUAT tapi tidak pernah bisa dilihat,
 * diperbaiki, atau dibuang — sehingga salah ketik nama menempel permanen di
 * setiap invoice yang merujuknya.
 *
 * quickStore() SENGAJA dipertahankan apa adanya (halaman Penjualan
 * memanggilnya lewat AJAX dan mengharapkan JSON); yang ditambahkan adalah
 * halaman pengelolaannya.
 *
 * Otorisasi lewat [CustomerPolicy] -> User::bolehKelolaKoperasi().
 */
class CustomerController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('viewAny', Customer::class);

        $customers = Customer::withCount('salesTransactions')
            ->with('penghapus')
            ->when($request->filled('q'), function ($q) use ($request) {
                $keyword = $request->string('q');
                $q->where(function ($sub) use ($keyword) {
                    $sub->where('nama', 'like', "%{$keyword}%")
                        ->orWhere('nomor_hp', 'like', "%{$keyword}%");
                });
            })
            ->orderBy('nama')
            ->paginate(15)
            ->withQueryString();

        return view('koperasi.pelanggan.index', [
            'customers' => $customers,
            'filters' => $request->only(['q']),
        ]);
    }

    public function create()
    {
        Gate::authorize('create', Customer::class);

        return view('koperasi.pelanggan.create', ['anggotaPilihan' => $this->anggotaPilihan()]);
    }

    public function store(Request $request)
    {
        Gate::authorize('create', Customer::class);

        Customer::create($this->validated($request));

        return redirect()
            ->route('koperasi.pelanggan.index')
            ->with('success', 'Pelanggan baru berhasil ditambahkan.');
    }

    public function edit(Customer $pelanggan)
    {
        Gate::authorize('update', $pelanggan);

        return view('koperasi.pelanggan.edit', [
            'customer' => $pelanggan,
            'anggotaPilihan' => $this->anggotaPilihan(),
        ]);
    }

    public function update(Request $request, Customer $pelanggan)
    {
        Gate::authorize('update', $pelanggan);

        $pelanggan->update($this->validated($request));

        return redirect()
            ->route('koperasi.pelanggan.index')
            ->with('success', 'Data pelanggan berhasil diperbarui.');
    }

    /**
     * SOFT DELETE — riwayat penjualan & invoice yang merujuk pelanggan ini
     * tetap utuh.
     */
    public function destroy(Customer $pelanggan)
    {
        Gate::authorize('delete', $pelanggan);

        $penghapusan = app(PenghapusanPermanenService::class);
        $alasan = $penghapusan->alasanTertahan($pelanggan);

        if ($alasan !== null) {
            // Bukan kegagalan sistem: ini keputusan yang disengaja, jadi
            // disampaikan sebagai penjelasan beserta apa yang menahannya.
            return back()->with('error', $alasan);
        }

        $ringkasan = $penghapusan->hapusDenganCatatan($pelanggan);

        return redirect()
            ->route('koperasi.pelanggan.index')
            ->with('success', 'Dihapus permanen: ' . $ringkasan . '. Data ini tidak dapat dipulihkan.');
    }

    /**
     * Simpan customer baru LANGSUNG dari halaman Penjualan (AJAX), tanpa
     * meninggalkan halaman transaksi — hasilnya langsung terpilih.
     */
    public function quickStore(Request $request)
    {
        Gate::authorize('create', Customer::class);

        $validated = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'nomor_hp' => ['nullable', 'string', 'max:30'],
            'alamat' => ['nullable', 'string'],
        ]);

        $customer = Customer::create($validated);

        return response()->json([
            'id' => $customer->id,
            'nama' => $customer->nama,
            'nomor_hp' => $customer->nomor_hp,
        ]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'nomor_hp' => ['nullable', 'string', 'max:30'],
            'alamat' => ['nullable', 'string'],
            'email' => ['nullable', 'email', 'max:255'],
            'catatan' => ['nullable', 'string'],
            // Tautan ke anggota koperasi — boleh kosong, dan kosong adalah
            // keadaan yang normal: sebagian besar pembeli koperasi memang
            // bukan anggota. Tautan ini dipakai pembagian SHU jasa usaha.
            'koperasi_member_id' => ['nullable', 'exists:koperasi_members,id'],
        ]);
    }

    /**
     * Anggota koperasi yang bisa ditautkan ke pelanggan.
     *
     * Dipakai bersama oleh form tambah & ubah, jadi daftarnya tidak bisa
     * berbeda antara keduanya.
     */
    private function anggotaPilihan()
    {
        return \App\Models\KoperasiMember::orderBy('nama')->get(['id', 'nama', 'nomor_anggota']);
    }
}
