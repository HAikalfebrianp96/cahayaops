<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\DanaHibahKeluar;
use App\Models\DanaHibahMasuk;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DanaHibahController extends Controller
{
    public function storeMasuk(Request $request)
    {
        abort_unless(Auth::user()->isAdmin(), 403);

        $validated = $request->validate([
            'tanggal' => ['required', 'date'],
            'nominal' => ['required', 'numeric', 'min:0'],
            'sumber' => ['required', 'string', 'max:255'],
            'peruntukan' => ['nullable', 'string', 'max:255'],
            'keterangan' => ['nullable', 'string'],
        ]);
        $validated['created_by'] = Auth::id();

        DanaHibahMasuk::create($validated);

        return redirect()->route('koperasi.keuangan.index')->with('success', 'Pemasukan Dana Hibah berhasil dicatat.');
    }

    public function storeKeluar(Request $request)
    {
        abort_unless(Auth::user()->isAdmin(), 403);

        $validated = $request->validate([
            'tanggal' => ['required', 'date'],
            'nominal' => ['required', 'numeric', 'min:0'],
            'keperluan' => ['required', 'string', 'max:255'],
            'keterangan' => ['nullable', 'string'],
        ]);
        $validated['created_by'] = Auth::id();

        DanaHibahKeluar::create($validated);

        return redirect()->route('koperasi.keuangan.index')->with('success', 'Penggunaan Dana Hibah berhasil dicatat.');
    }

    /**
     * PEMBATALAN ARUS DANA — bukan penghapusan.
     *
     * Dana Sosial & Hibah adalah titipan pihak ketiga: saldonya harus selalu
     * bisa ditelusuri dari catatan masuk dan keluarnya. Menghapus satu baris
     * membuat saldo berubah tanpa ada yang bisa menjelaskan sebabnya.
     *
     * Sama seperti simpanan dan penjualan: baris tetap ada, statusnya jadi
     * "Dibatalkan", berhenti dihitung, dan alasannya tersimpan.
     */
    public function batalkan(Request $request, string $jenis, int $id)
    {
        abort_unless(Auth::user()->isAdmin(), 403);

        $model = $this->modelUntuk($jenis);
        $baris = $model::findOrFail($id);

        abort_if($baris->status === 'Dibatalkan', 403, 'Catatan ini sudah dibatalkan.');

        $validated = $request->validate([
            'alasan_pembatalan' => ['required', 'string', 'max:1000'],
        ], [
            'alasan_pembatalan.required' => 'Alasan pembatalan wajib diisi.',
        ]);

        $baris->forceFill([
            'status' => 'Dibatalkan',
            'alasan_pembatalan' => $validated['alasan_pembatalan'],
            'dibatalkan_at' => now(),
            'dibatalkan_oleh' => Auth::id(),
        ])->save();

        ActivityLog::record(
            action: 'dana_hibah_cancelled',
            modelType: class_basename($baris),
            modelId: $baris->id,
            description: 'Catatan dana dibatalkan (Rp' . number_format((float) $baris->nominal, 0, ',', '.')
                . '). Alasan: ' . $validated['alasan_pembatalan'],
        );

        return redirect()
            ->route('koperasi.keuangan.index')
            ->with('success', 'Catatan dana dibatalkan. Barisnya tetap tersimpan sebagai jejak.');
    }

    /** Memperbaiki catatan yang masih berlaku (belum dibatalkan). */
    public function perbarui(Request $request, string $jenis, int $id)
    {
        abort_unless(Auth::user()->isAdmin(), 403);

        $model = $this->modelUntuk($jenis);
        $baris = $model::findOrFail($id);

        abort_if($baris->status === 'Dibatalkan', 403, 'Catatan yang sudah dibatalkan tidak bisa diubah.');

        $sebelum = 'Rp' . number_format((float) $baris->nominal, 0, ',', '.');

        $baris->update($request->validate([
            'tanggal' => ['required', 'date'],
            'nominal' => ['required', 'numeric', 'min:0'],
            'keterangan' => ['nullable', 'string'],
        ]));

        ActivityLog::record(
            action: 'dana_hibah_updated',
            modelType: class_basename($baris),
            modelId: $baris->id,
            description: 'Catatan dana diperbaiki — sebelum: ' . $sebelum
                . ' | sesudah: Rp' . number_format((float) $baris->fresh()->nominal, 0, ',', '.'),
        );

        return redirect()
            ->route('koperasi.keuangan.index')
            ->with('success', 'Catatan dana berhasil diperbaiki. Perubahannya tercatat di Activity Log.');
    }

    /**
     * Memetakan nama jenis di URL ke modelnya.
     *
     * Daftar putih EKSPLISIT, bukan menyusun nama kelas dari input —
     * menyusunnya dari input berarti mengizinkan pengguna menyebut kelas
     * apa pun di aplikasi.
     */
    private function modelUntuk(string $jenis): string
    {
        return match ($jenis) {
            'masuk' => \App\Models\DanaHibahMasuk::class,
            'keluar' => \App\Models\DanaHibahKeluar::class,
            default => abort(404),
        };
    }
}
