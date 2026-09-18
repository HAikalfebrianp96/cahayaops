<?php

namespace App\Http\Controllers;

use App\Models\PengeluaranCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class KategoriPengeluaranController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(Auth::user()->bolehKelolaKoperasi(), 403);

        $arsip = $request->boolean('arsip');

        $kategori = PengeluaranCategory::withCount('pengeluaran')
            ->when($arsip, fn ($q) => $q->onlyTrashed())
            ->orderBy('nama')
            ->get();

        return view('koperasi.pengaturan.kategori-pengeluaran.index', [
            'kategori' => $kategori,
            'arsip' => $arsip,
            'jumlahTerhapus' => PengeluaranCategory::onlyTrashed()->count(),
        ]);
    }

    public function create()
    {
        abort_unless(Auth::user()->bolehKelolaKoperasi(), 403);

        return view('koperasi.pengaturan.kategori-pengeluaran.create');
    }

    public function store(Request $request)
    {
        abort_unless(Auth::user()->bolehKelolaKoperasi(), 403);

        $validated = $request->validate([
            'nama' => ['required', 'string', 'max:255', Rule::unique('pengeluaran_categories', 'nama')],
        ]);

        PengeluaranCategory::create([
            'nama' => trim($validated['nama']),
            'status' => 'Aktif',
        ]);

        return redirect()->route('koperasi.kategori-pengeluaran.index')->with('success', 'Kategori berhasil ditambahkan.');
    }

    public function edit(PengeluaranCategory $kategori)
    {
        abort_unless(Auth::user()->bolehKelolaKoperasi(), 403);

        return view('koperasi.pengaturan.kategori-pengeluaran.edit', compact('kategori'));
    }

    public function update(Request $request, PengeluaranCategory $kategori)
    {
        abort_unless(Auth::user()->bolehKelolaKoperasi(), 403);

        $validated = $request->validate([
            'nama' => ['required', 'string', 'max:255', Rule::unique('pengeluaran_categories', 'nama')->ignore($kategori->id)],
        ]);

        // Mengganti NAMA di master TIDAK mengubah snapshot 'kategori' (text)
        // di transaksi-transaksi LAMA yang sudah pernah pakai kategori ini —
        // histori laporan periode lalu tetap konsisten dgn nama yang berlaku
        // saat transaksi itu dibuat.
        $kategori->update(['nama' => trim($validated['nama'])]);

        return redirect()->route('koperasi.kategori-pengeluaran.index')->with('success', 'Kategori berhasil diperbarui. Transaksi lama yang sudah tercatat TIDAK ikut berubah.');
    }

    /**
     * SOFT DELETE.
     *
     * Berbeda dari toggleStatus(): menonaktifkan dipakai untuk kategori yang
     * masih sah tapi tidak dipakai lagi (tetap tampil di laporan lama),
     * sedangkan penghapusan untuk kategori yang memang SALAH dibuat.
     * Keduanya sama-sama tidak menghilangkan barisnya, jadi pengeluaran yang
     * sudah terlanjur memakainya tidak pernah kehilangan kategorinya.
     */
    public function destroy(PengeluaranCategory $kategori)
    {
        abort_unless(Auth::user()->bolehKelolaKoperasi(), 403);

        $kategori->delete();

        return redirect()
            ->route('koperasi.kategori-pengeluaran.index')
            ->with('success', 'Kategori "' . $kategori->nama . '" dipindahkan ke arsip. Pengeluaran yang memakainya tidak terpengaruh.');
    }

    /** Membatalkan Soft Delete. Route-nya memakai withTrashed(). */
    public function restore(PengeluaranCategory $kategori)
    {
        abort_unless(Auth::user()->bolehKelolaKoperasi(), 403);

        $kategori->restore();

        return redirect()
            ->route('koperasi.kategori-pengeluaran.index')
            ->with('success', 'Kategori "' . $kategori->nama . '" berhasil dipulihkan.');
    }

    /**
     * Nonaktifkan/Aktifkan — BUKAN hapus. Kategori yang sudah pernah dipakai
     * transaksi tidak boleh hilang dari sistem (histori harus tetap utuh).
     */
    public function toggleStatus(PengeluaranCategory $kategori)
    {
        abort_unless(Auth::user()->bolehKelolaKoperasi(), 403);

        $kategori->update([
            'status' => $kategori->status === 'Aktif' ? 'Tidak Aktif' : 'Aktif',
        ]);

        return redirect()
            ->route('koperasi.kategori-pengeluaran.index')
            ->with('success', 'Kategori "' . $kategori->nama . '" sekarang ' . strtolower($kategori->status) . '.');
    }
}
