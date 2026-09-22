<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\DanaSukarelaKeluar;
use App\Models\DanaSukarelaMasuk;
use App\Services\PenghapusanPermanenService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DanaSukarelaController extends Controller
{
    public function storeMasuk(Request $request)
    {
        abort_unless(Auth::user()->isAdmin(), 403);

        $validated = $request->validate([
            'tanggal' => ['required', 'date'],
            'nominal' => ['required', 'numeric', 'min:0'],
            'sumber' => ['required', 'string', 'max:255'],
            'keterangan' => ['nullable', 'string'],
        ]);
        $validated['created_by'] = Auth::id();

        DanaSukarelaMasuk::create($validated);

        return redirect()->route('koperasi.keuangan.index')->with('success', 'Pemasukan Dana Sukarela berhasil dicatat.');
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

        DanaSukarelaKeluar::create($validated);

        return redirect()->route('koperasi.keuangan.index')->with('success', 'Penggunaan Dana Sukarela berhasil dicatat.');
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
            action: 'dana_sukarela_cancelled',
            modelType: class_basename($baris),
            modelId: $baris->id,
            description: 'Catatan dana dibatalkan (Rp' . number_format((float) $baris->nominal, 0, ',', '.')
                . '). Alasan: ' . $validated['alasan_pembatalan'],
        );

        return redirect()
            ->route('koperasi.keuangan.index')
            ->with('success', 'Catatan dana dibatalkan. Barisnya tetap tersimpan sebagai jejak.');
    }

    /**
     * Form perbaikan satu baris arus dana.
     *
     * Rute perbarui() sudah ada sejak lama tapi TIDAK PERNAH punya halaman
     * yang memanggilnya — jadi secara praktis catatan dana tidak bisa
     * diperbaiki sama sekali, hanya dibatalkan. Halaman ini yang menutup
     * jaraknya.
     */
    public function edit(string $jenis, int $id)
    {
        abort_unless(Auth::user()->isAdmin(), 403);

        $model = $this->modelUntuk($jenis);
        $baris = $model::findOrFail($id);

        abort_if($baris->dibatalkan(), 403, 'Catatan yang sudah dibatalkan tidak bisa diubah.');

        return view('koperasi.keuangan.dana-edit', [
            'baris' => $baris,
            'jenis' => $jenis,
            'judul' => 'Dana Sukarela — ' . ($jenis === 'masuk' ? 'Pemasukan' : 'Penggunaan'),
            'kolom' => $this->kolomDeskriptif($jenis),
            'aksiPerbarui' => route('koperasi.dana-sukarela.perbarui', ['jenis' => $jenis, 'id' => $id]),
            'aksiBatalkan' => route('koperasi.dana-sukarela.batalkan', ['jenis' => $jenis, 'id' => $id]),
        ]);
    }

    /**
     * Kolom keterangan yang BERBEDA antar jenis arus dana, beserta labelnya.
     *
     * Satu tempat saja: dipakai form editnya untuk menggambar isian, dan
     * dipakai perbarui() untuk menyusun aturan validasinya. Kalau keduanya
     * punya daftar sendiri-sendiri, suatu saat yang satu akan menggambar
     * isian yang tidak pernah divalidasi yang lain.
     *
     * @return array<string, array{label: string, wajib: bool}>
     */
    private function kolomDeskriptif(string $jenis): array
    {
        return match ($jenis) {
            'masuk' => ['sumber' => ['label' => 'Sumber Dana', 'wajib' => true]],
            'keluar' => ['keperluan' => ['label' => 'Keperluan', 'wajib' => true]],
            default => abort(404),
        };
    }

    /** Memperbaiki catatan yang masih berlaku (belum dibatalkan). */
    public function perbarui(Request $request, string $jenis, int $id)
    {
        abort_unless(Auth::user()->isAdmin(), 403);

        $model = $this->modelUntuk($jenis);
        $baris = $model::findOrFail($id);

        abort_if($baris->status === 'Dibatalkan', 403, 'Catatan yang sudah dibatalkan tidak bisa diubah.');

        $sebelum = 'Rp' . number_format((float) $baris->nominal, 0, ',', '.');

        $aturan = [
            'tanggal' => ['required', 'date'],
            'nominal' => ['required', 'numeric', 'min:0'],
            'keterangan' => ['nullable', 'string'],
        ];

        // Sumber/keperluan/peruntukan ikut bisa diperbaiki. Tanpa ini, salah
        // ketik nama penyumbang hanya bisa dibereskan dengan membatalkan
        // seluruh catatannya — pembatalan untuk sesuatu yang bukan kesalahan
        // angka.
        foreach ($this->kolomDeskriptif($jenis) as $nama => $spek) {
            $aturan[$nama] = [$spek['wajib'] ? 'required' : 'nullable', 'string', 'max:255'];
        }

        $baris->update($request->validate($aturan));

        ActivityLog::record(
            action: 'dana_sukarela_updated',
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
     * HAPUS PERMANEN satu baris arus dana.
     *
     * Pembatalan (batalkan) adalah jalur koreksi dan tetap yang utama:
     * barisnya tinggal sebagai jejak. Yang ini jalur lain untuk keperluan
     * lain — baris percobaan yang sudah dibatalkan dan tidak punya nilai
     * histori, yang hanya menumpuk di riwayat.
     *
     * Penjagaannya ada di PenghapusanPermanenService: baris yang MASIH
     * BERLAKU ditolak. Jadi menghapus selalu keputusan kedua, diambil pada
     * waktu berbeda dari pembatalannya, dan alasan pembatalan itu sudah
     * lebih dulu tercatat di Activity Log sebelum barisnya lenyap.
     *
     * Saldo dana tidak disimpan di kolom mana pun — selalu dijumlahkan dari
     * baris masuk dan keluar. Begitu barisnya hilang, saldonya menyesuaikan
     * sendiri.
     */
    public function destroy(string $jenis, int $id, PenghapusanPermanenService $penghapusan)
    {
        abort_unless(Auth::user()->isAdmin(), 403);

        $model = $this->modelUntuk($jenis);
        $baris = $model::findOrFail($id);

        $alasan = $penghapusan->alasanTertahan($baris);

        if ($alasan !== null) {
            return back()->withErrors(['hapus' => $alasan]);
        }

        // Diambil SEBELUM dihapus; sesudahnya tidak ada yang tersisa untuk
        // dirujuk.
        $kelasBaris = class_basename($baris);
        $ringkasan = ($jenis === 'masuk' ? 'Pemasukan' : 'Penggunaan')
            . ' Rp' . number_format((float) $baris->nominal, 0, ',', '.')
            . ' tgl ' . optional($baris->tanggal)->format('d-m-Y');

        DB::transaction(fn () => $penghapusan->hapus($baris));

        ActivityLog::record(
            action: 'dana_sukarela_permanently_deleted',
            modelType: $kelasBaris,
            modelId: $id,
            description: 'DANA SUKARELA DIHAPUS PERMANEN: ' . $ringkasan
                . '. Saldo dana ikut menyesuaikan. Tindakan ini tidak dapat dibatalkan.',
        );

        return redirect()
            ->route('koperasi.keuangan.index')
            ->with('success', 'Dana Sukarela — ' . $ringkasan . ' dihapus permanen.');
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
            'masuk' => \App\Models\DanaSukarelaMasuk::class,
            'keluar' => \App\Models\DanaSukarelaKeluar::class,
            default => abort(404),
        };
    }
}
