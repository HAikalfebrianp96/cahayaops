<?php

namespace App\Http\Controllers;

use App\Models\KoperasiSetting;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class PengaturanController extends Controller
{
    /** Tab yang boleh dipilih di switcher. Selain ini dianggap 'semua'. */
    private const TAB = ['semua', 'preneur', 'koperasi'];

    /**
     * Halaman Pengaturan gabungan — SATU halaman, tiga tab.
     *
     * Method ini HANYA menyiapkan data untuk DITAMPILKAN. Kedua sistem
     * pengaturan tetap terpisah sepenuhnya:
     *   - SystemSetting   (Cahaya Preneur) disimpan lewat update() di sini
     *   - KoperasiSetting (Koperasi)       disimpan lewat KoperasiSettingController
     * Tidak ada logic penyimpanan yang dicampur — di sini cuma dibaca.
     */
    public function index(Request $request)
    {
        // ADMIN SAJA. Halaman ini memuat konfigurasi Cahaya Preneur DAN
        // Koperasi, termasuk identitas resmi, tanda tangan, stempel, dan
        // prefix invoice.
        //
        // Sempat dibuka untuk Tim Koperasi (read-only bagian Koperasi), lalu
        // ditutup kembali sesuai keputusan hak akses terakhir: Pengaturan
        // bukan menu Tim Koperasi.
        //
        // Penyaringan panel di bawah SENGAJA dipertahankan sebagai lapis
        // kedua — kalau guard ini suatu saat dilonggarkan lagi, panel Cahaya
        // Preneur tetap tidak ikut terbuka dengan sendirinya.
        abort_unless(Auth::user()->isAdmin(), 403);

        $isAdmin = Auth::user()->isAdmin();

        $tab = in_array($request->query('tab'), self::TAB, true)
            ? $request->query('tab')
            : 'semua';

        // Non-Admin dikunci ke tab Koperasi. Tanpa ini, ?tab=preneur atau
        // ?tab=semua akan membuka panel Cahaya Preneur lewat URL — menu yang
        // disembunyikan saja tidak cukup.
        if (! $isAdmin) {
            $tab = 'koperasi';
        }

        return view('pengaturan.index', [
            'tab' => $tab,
            'bolehLihatPreneur' => $isAdmin,
            'bolehUbahKoperasi' => $isAdmin,
            'setting' => SystemSetting::current(),
            'koperasiSetting' => KoperasiSetting::current(),
            // Daftar user hanya dipakai panel Cahaya Preneur. Untuk non-Admin
            // panel itu tidak dirender, jadi datanya pun tidak perlu diambil —
            // daftar seluruh akun beserta email & role tidak dikirim ke sana.
            'users' => $isAdmin
                ? User::orderBy('role')->orderBy('name')->get()
                : collect(),
        ]);
    }

    /**
     * AMBANG HARUS MASUK AKAL TERHADAP NILAI YANG BENAR-BENAR BERLAKU.
     *
     * =================================================================
     * KENAPA aturan `gt:` DI ATAS BELUM CUKUP
     * =================================================================
     *
     * `gt:` membandingkan satu masukan dengan masukan LAIN DI FORM YANG SAMA.
     * Padahal yang dipakai sistem adalah campuran: nilai yang diisi Admin,
     * dan nilai bawaan config untuk kolom yang dikosongkan.
     *
     * Akibatnya satu bentuk kombinasi lolos begitu saja — mengisi batas BAWAH
     * sendirian dengan angka yang melewati batas ATAS bawaan:
     *
     *   "Perlu perhatian" = 50 hari, "terlambat" dikosongkan.
     *   Yang berlaku: kuning 50, merah 45 (bawaan). Terbalik.
     *
     * FollowUpService memeriksa merah lebih dulu, jadi setiap binaan >= 45
     * hari langsung MERAH dan status KUNING tidak pernah tercapai lagi —
     * keranjang "Perlu Ditindaklanjuti" kosong selamanya, tanpa pesan error
     * dan tanpa ada yang tahu sebabnya.
     *
     * Bentuk sebaliknya (mengisi batas ATAS terlalu rendah) sudah tertangkap
     * `gt:`; yang ditambal di sini khusus bentuk yang lolos itu.
     */
    private function pastikanAmbangMasukAkal(Request $request): void
    {
        // ============================================================
        // MENGGANTIKAN aturan bawaan `gt:` — yang menolak isian yang sah.
        //
        // Dulu tiap batas atas divalidasi dengan gt:<batas bawah>. Aturan itu
        // membandingkan dengan MASUKAN LAIN DI FORM, dan gagal begitu
        // pembandingnya dikosongkan.
        //
        // Akibatnya nyata: form-nya sendiri berbunyi "Kosongkan untuk
        // memakai nilai bawaan", tapi mengisi "terlambat" = 40 sambil
        // mengosongkan "perlu perhatian" DITOLAK — padahal 40 memang lebih
        // besar daripada 30 yang berlaku. Pesannya pun keliru: ia menuduh
        // angka yang sudah benar.
        //
        // Pemeriksaan di bawah membandingkan nilai YANG BERLAKU (terkirim
        // kalau ada, bawaan kalau dikosongkan), jadi ia menangkap kombinasi
        // yang benar-benar terbalik TANPA menolak pengisian sebagian.
        // ============================================================
        // [kolom bawah, kolom atas, config bawah, config atas, pesan]
        $pasangan = [
            [
                'followup_kuning_hari', 'followup_merah_hari',
                'cahayaos.followup.kuning_hari', 'cahayaos.followup.merah_hari',
                'Ambang "perlu perhatian" harus lebih kecil daripada ambang "terlambat" yang berlaku (:atas hari).',
            ],
            [
                'peserta_aktif_hari', 'peserta_perlu_follow_up_hari',
                'cahayaos.keaktifan_peserta.aktif_hari', 'cahayaos.keaktifan_peserta.perlu_follow_up_hari',
                'Batas "aktif" harus lebih kecil daripada batas "tidak aktif" yang berlaku (:atas hari).',
            ],
            [
                'beban_ringan_maks', 'beban_normal_maks',
                'cahayaos.beban_pendamping.ringan_maks', 'cahayaos.beban_pendamping.normal_maks',
                'Batas beban Ringan harus lebih kecil daripada batas Normal yang berlaku (:atas).',
            ],
            [
                'beban_normal_maks', 'beban_tinggi_maks',
                'cahayaos.beban_pendamping.normal_maks', 'cahayaos.beban_pendamping.tinggi_maks',
                'Batas beban Normal harus lebih kecil daripada batas Tinggi yang berlaku (:atas).',
            ],
        ];

        $galat = [];

        foreach ($pasangan as [$kolomBawah, $kolomAtas, $configBawah, $configAtas, $pesan]) {
            // Nilai yang BERLAKU: yang diisi kalau ada, kalau tidak yang bawaan.
            $bawah = $request->filled($kolomBawah)
                ? (int) $request->input($kolomBawah)
                : (int) config($configBawah);

            $atas = $request->filled($kolomAtas)
                ? (int) $request->input($kolomAtas)
                : (int) config($configAtas);

            if ($bawah >= $atas) {
                // Dilaporkan pada kolom yang BARU SAJA diisi Admin — di situlah
                // ia sedang menatap, dan di situ pula perbaikannya dilakukan.
                $kolomGalat = $request->filled($kolomBawah) ? $kolomBawah : $kolomAtas;

                $galat[$kolomGalat][] = str_replace(':atas', (string) $atas, $pesan);
            }
        }

        if ($galat !== []) {
            throw \Illuminate\Validation\ValidationException::withMessages($galat);
        }
    }

    public function update(Request $request)
    {
        abort_unless(Auth::user()->isAdmin(), 403);

        $validated = $request->validate([
            'nama_sistem' => ['required', 'string', 'max:255'],
            'nama_lembaga' => ['required', 'string', 'max:255'],
            'alamat' => ['nullable', 'string'],
            'kontak' => ['nullable', 'string', 'max:255'],
            'nama_program' => ['required', 'string', 'max:255'],
            'periode_program' => ['nullable', 'string', 'max:255'],
            'tahun_program' => ['nullable', 'string', 'max:10'],
            // Ambang follow up — kebijakan program yang boleh diubah Admin.
            // Dikosongkan = kembali memakai nilai bawaan di
            // config/cahayaos.php (lihat FollowUpService::ambang()).
            'followup_kuning_hari' => ['nullable', 'integer', 'min:1', 'max:365'],
            'followup_merah_hari' => ['nullable', 'integer', 'min:2', 'max:365'],
            // Target default — dipakai kalau pendamping belum punya target
            // khusus. Dikosongkan = kembali ke nilai bawaan config.
            'target_default_binaan' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'target_default_kunjungan' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'target_default_pendampingan' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'target_default_follow_up' => ['nullable', 'integer', 'min:0', 'max:9999'],
            // Ambang keaktifan PESERTA — terpisah dari ambang follow up
            // pendamping di atas; keduanya mengukur hal yang berbeda.
            'peserta_aktif_hari' => ['nullable', 'integer', 'min:1', 'max:365'],
            'peserta_perlu_follow_up_hari' => ['nullable', 'integer', 'min:2', 'max:730'],
            // Ambang beban binaan per pendamping.
            'beban_ringan_maks' => ['nullable', 'integer', 'min:1', 'max:999'],
            'beban_normal_maks' => ['nullable', 'integer', 'min:2', 'max:999'],
            'beban_tinggi_maks' => ['nullable', 'integer', 'min:3', 'max:999'],
            'logo' => ['nullable', 'file', 'mimes:jpg,jpeg,png', 'max:2048'],
            'logo_program' => ['nullable', 'image', 'max:2048'],
            'logo_ybm_pln' => ['nullable', 'image', 'max:2048'],
        ]);

        $this->pastikanAmbangMasukAkal($request);

        $setting = SystemSetting::current();
        $setting->fill($validated);

        if ($request->hasFile('logo')) {
            if ($setting->logo_path) {
                Storage::delete($setting->logo_path);
            }
            $file = $request->file('logo');
            $setting->logo_path = $file->store('logo');
            $setting->logo_asli = $file->getClientOriginalName();
        }

        // Logo Program & Logo YBM PLN — disimpan PUBLIK, dipakai untuk tampil
        // langsung di header (bukan lewat mass-assignment, karena kolom baru
        // ini mungkin belum ada di $fillable model).
        $folder = public_path('images/preneur');
        if (! is_dir($folder)) {
            mkdir($folder, 0755, true);
        }

        foreach (['logo_program', 'logo_ybm_pln'] as $field) {
            if ($request->hasFile($field)) {
                $file = $request->file($field);
                // Ekstensi dari ISI berkas (MIME hasil deteksi), BUKAN dari nama
                // kiriman klien. Folder ini berada di web root: gambar sah yang
                // dinamai "x.php" akan lolos aturan 'image' lalu tersimpan
                // sebagai skrip yang bisa dijalankan server.
                $filename = $field . '_' . time() . '.' . ($file->guessExtension() ?: 'png');
                $file->move($folder, $filename);
                $setting->{$field} = $filename;
            }
        }

        $setting->save();

        return redirect()
            ->route('pengaturan.index', ['tab' => 'preneur'])
            ->with('success', 'Pengaturan Cahaya Preneur berhasil disimpan.');
    }

    public function logo()
    {
        $setting = SystemSetting::current();
        abort_unless($setting->logo_path && Storage::exists($setting->logo_path), 404);

        return Storage::download($setting->logo_path, $setting->logo_asli ?: basename($setting->logo_path));
    }
}
