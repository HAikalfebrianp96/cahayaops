<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Services\PenghapusanPermanenService;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

/**
 * HAPUS PERMANEN dari halaman Arsip Data.
 *
 * Satu pintu untuk seluruh modul, sejalan dengan ArsipController yang juga
 * satu pintu. Aturan pengamannya sendiri ada di PenghapusanPermanenService —
 * controller ini hanya mengurus otorisasi, konfirmasi, dan pencatatan.
 *
 * OTORISASI SENGAJA LEBIH KETAT DARIPADA ARSIP.
 * Halaman Arsip bisa dibuka Pendamping dan Tim Koperasi, dan keduanya boleh
 * memulihkan data di ranahnya masing-masing. Penghapusan permanen TIDAK
 * mengikuti itu: hanya Admin. Alasannya sederhana — memulihkan data yang
 * keliru dipulihkan masih bisa diperbaiki, sedangkan penghapusan permanen
 * tidak punya langkah perbaikan sama sekali.
 */
class HapusPermanenController extends Controller
{
    public function __construct(private readonly PenghapusanPermanenService $penghapusan)
    {
    }

    public function __invoke(Request $request, string $modul, int $id)
    {
        // Admin saja — bukan bolehKelolaKoperasi(), bukan pemilik data.
        abort_unless(Auth::user()->isAdmin(), 403);

        $peta = PenghapusanPermanenService::modulTersedia();

        abort_unless(array_key_exists($modul, $peta), 404);

        $kelas = $peta[$modul];

        // withTrashed() hanya untuk model yang memang memakai SoftDeletes —
        // tanpa itu data terarsip tidak akan pernah ketemu. Catatan uang
        // (penjualan, pengeluaran, simpanan) tidak memakai SoftDeletes sama
        // sekali: barisnya selalu ada, yang berubah statusnya.
        $model = in_array(SoftDeletes::class, class_uses_recursive($kelas), true)
            ? $kelas::withTrashed()->findOrFail($id)
            : $kelas::findOrFail($id);

        // Kalimat konfirmasi diperiksa PALING AWAL — sebelum apa pun
        // dijalankan, supaya salah ketik tidak menyisakan efek samping.
        $validated = $request->validate([
            'konfirmasi' => ['required', 'string'],
        ], [
            'konfirmasi.required' => 'Kalimat konfirmasi wajib diisi.',
        ]);

        if (trim($validated['konfirmasi']) !== PenghapusanPermanenService::KONFIRMASI) {
            return back()->withErrors([
                'konfirmasi' => 'Kalimat konfirmasi tidak sesuai. Ketik persis: "' . PenghapusanPermanenService::KONFIRMASI . '"',
            ]);
        }

        $alasanTertahan = $this->penghapusan->alasanTertahan($model);

        if ($alasanTertahan !== null) {
            // Bukan error teknis — ini keputusan yang disengaja, jadi
            // disampaikan sebagai penjelasan, bukan sebagai kegagalan sistem.
            return back()->withErrors(['hapus_permanen' => $alasanTertahan]);
        }

        // Ringkasan diambil SEBELUM dihapus. Setelah forceDelete tidak ada
        // apa pun yang tersisa untuk dirujuk, dan baris Activity Log inilah
        // satu-satunya bukti bahwa data ini pernah ada.
        $ringkasan = $this->penghapusan->ringkasan($model);

        $this->penghapusan->hapus($model);

        ActivityLog::record(
            action: 'permanently_deleted',
            modelType: class_basename($model),
            modelId: $id,
            description: 'DIHAPUS PERMANEN: ' . $ringkasan . '. Tindakan ini tidak dapat dibatalkan.',
        );

        return redirect()
            ->route('arsip.index', ['tab' => $modul])
            ->with('success', 'Data dihapus permanen: ' . $ringkasan);
    }
}
