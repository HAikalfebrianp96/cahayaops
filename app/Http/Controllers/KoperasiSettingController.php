<?php

namespace App\Http\Controllers;

use App\Models\KoperasiSetting;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;

class KoperasiSettingController extends Controller
{
    private const FILE_FIELDS = ['logo_koperasi', 'logo_cahayaos', 'logo_ybm_pln', 'ttd_koperasi', 'stempel_koperasi'];

    /**
     * ALIAS ke halaman Pengaturan gabungan.
     *
     * Tampilan Pengaturan Koperasi sekarang jadi salah satu TAB di
     * /pengaturan (lihat resources/views/pengaturan/panel-koperasi.blade.php).
     * Route ini dipertahankan supaya bookmark/link lama ke
     * /koperasi/pengaturan tidak jadi 404.
     *
     * Guard Admin dipertahankan persis seperti sebelumnya.
     */
    public function index()
    {
        // TETAP Admin-only - lihat catatan di update().
        //
        // Route ini bukan halaman: ia redirect ke pengaturan.index?tab=koperasi,
        // dan halaman itu Admin-only SERTA merender panel Cahaya Preneur juga.
        // Membukanya untuk Tim Koperasi berarti membocorkan konfigurasi
        // ekosistem yang bukan urusan mereka, sedangkan membiarkannya terbuka
        // di sini hanya menghasilkan 403 satu langkah kemudian (tautan buntu).
        // Jadi ditutup rapat sampai ada halaman Koperasi read-only tersendiri.
        abort_unless(Auth::user()->isAdmin(), 403);

        return redirect()->route('pengaturan.index', ['tab' => 'koperasi']);
    }

    public function update(Request $request)
    {
        abort_unless(Auth::user()->isAdmin(), 403);

        $validated = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'subtitle' => ['required', 'string', 'max:255'],
            'tagline' => ['nullable', 'string', 'max:255'],
            'nama_penandatangan' => ['nullable', 'string', 'max:255'],
            'jabatan_penandatangan' => ['nullable', 'string', 'max:255'],
            'prefix_invoice' => ['required', 'string', 'max:20'],
            'footer_invoice' => ['nullable', 'string'],
            'logo_koperasi' => ['nullable', 'image', 'max:2048'],
            'logo_cahayaos' => ['nullable', 'image', 'max:2048'],
            'logo_ybm_pln' => ['nullable', 'image', 'max:2048'],
            'ttd_koperasi' => ['nullable', 'image', 'max:2048'],
            'stempel_koperasi' => ['nullable', 'image', 'max:2048'],
        ]);

        $setting = KoperasiSetting::current();
        $folder = public_path('images/koperasi');
        if (! is_dir($folder)) {
            mkdir($folder, 0755, true);
        }

        foreach (self::FILE_FIELDS as $field) {
            /** @var UploadedFile|null $file */
            $file = $request->file($field);
            if ($file) {
                // Ekstensi dari ISI berkas (MIME hasil deteksi), BUKAN dari nama
                // kiriman klien. Folder ini berada di web root: gambar sah yang
                // dinamai "x.php" akan lolos aturan 'image' lalu tersimpan
                // sebagai skrip yang bisa dijalankan server.
                $filename = $field . '_' . time() . '.' . ($file->guessExtension() ?: 'png');
                $file->move($folder, $filename);
                $validated[$field] = $filename;
            } else {
                unset($validated[$field]); // tidak diganti kalau tidak upload baru
            }
        }

        $setting->update($validated);

        // Kembali ke TAB Koperasi di halaman Pengaturan gabungan, supaya
        // pengguna mendarat tepat di tempat dia menekan Simpan.
        return redirect()
            ->route('pengaturan.index', ['tab' => 'koperasi'])
            ->with('success', 'Pengaturan Koperasi Cahaya berhasil disimpan.');
    }
}
