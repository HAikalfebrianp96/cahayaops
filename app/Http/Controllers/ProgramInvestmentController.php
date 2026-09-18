<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\ImpactProgram;
use App\Models\ProgramInvestment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * INVESTASI PROGRAM — penyebut rasio SROI.
 *
 * CRUD penuh dengan soft delete, seperti seluruh catatan keuangan lain di
 * CahayaOS. Yang khas: setiap perubahan di sini langsung menggeser rasio
 * SROI, jadi perhitungan yang sudah terbit TIDAK ikut berubah — angkanya
 * sudah dibekukan di sroi_calculations. Program yang sudah Final terkunci
 * lewat ImpactPolicy::update, jadi investasinya pun tidak bisa disentuh.
 */
class ProgramInvestmentController extends Controller
{
    public function create(ImpactProgram $impact)
    {
        Gate::authorize('update', $impact);

        return view('impact.investasi-form', [
            'program' => $impact,
            'investasi' => null,
        ]);
    }

    public function store(Request $request, ImpactProgram $impact)
    {
        Gate::authorize('update', $impact);

        $investasi = $impact->investments()->create($this->validasi($request) + [
            'dicatat_oleh' => Auth::id(),
        ]);

        $this->simpanBukti($request, $investasi);

        return redirect()
            ->route('impact.show', $impact)
            ->with('success', 'Investasi program berhasil dicatat.');
    }

    public function edit(ProgramInvestment $investasi)
    {
        Gate::authorize('update', $investasi->program);

        return view('impact.investasi-form', [
            'program' => $investasi->program,
            'investasi' => $investasi,
        ]);
    }

    public function update(Request $request, ProgramInvestment $investasi)
    {
        Gate::authorize('update', $investasi->program);

        $investasi->update($this->validasi($request));

        $this->simpanBukti($request, $investasi);

        return redirect()
            ->route('impact.show', $investasi->program)
            ->with('success', 'Investasi program berhasil diperbarui.');
    }

    public function destroy(ProgramInvestment $investasi)
    {
        Gate::authorize('update', $investasi->program);

        $program = $investasi->program;
        $investasi->delete();

        return redirect()
            ->route('impact.show', $program)
            ->with('success', 'Investasi dipindahkan ke Arsip.');
    }

    public function restore(int $investasi)
    {
        $record = ProgramInvestment::withTrashed()->findOrFail($investasi);

        Gate::authorize('update', $record->program);

        $record->restore();

        return back()->with('success', 'Investasi berhasil dipulihkan.');
    }

    private function validasi(Request $request): array
    {
        $data = $request->validate([
            'kategori' => ['required', 'string', 'max:255'],
            'uraian' => ['required', 'string', 'max:255'],
            'tanggal' => ['required', 'date'],
            // Nominal 0 ditolak: pos investasi bernilai nol tidak menambah
            // informasi apa pun, dan hanya membuat daftar lebih panjang.
            'nominal' => ['required', 'integer', 'min:1'],
            'jenis' => ['required', Rule::in(ProgramInvestment::jenisYangSah())],
            'sumber_dana' => ['nullable', 'string', 'max:255'],
            'catatan' => ['nullable', 'string'],
            // Bukti OPSIONAL. Sebagian pos investasi memang tidak berkuitansi
            // — kontribusi in-kind berupa waktu relawan, misalnya. Mewajibkan
            // berkas hanya akan membuat pos itu tidak dicatat sama sekali,
            // dan penyebut rasio jadi lebih kecil daripada sebenarnya.
            'bukti' => [
                'nullable', 'file',
                'mimes:' . implode(',', ProgramInvestment::BUKTI_MIME),
                'max:' . ProgramInvestment::BUKTI_MAKS_KB,
            ],
        ], [
            'kategori.required' => 'Kategori investasi wajib diisi.',
            'uraian.required' => 'Uraian investasi wajib diisi.',
            'nominal.required' => 'Nominal investasi wajib diisi.',
            'nominal.min' => 'Nominal investasi harus lebih dari nol.',
            'bukti.mimes' => 'Bukti harus berupa foto (JPG, PNG, WEBP) atau PDF.',
            'bukti.max' => 'Ukuran bukti maksimal 5 MB.',
        ]);

        // Kunci 'bukti' berisi berkas, bukan kolom tabel — dibuang sebelum
        // dipakai mengisi model.
        unset($data['bukti']);

        return $data;
    }

    /**
     * Simpan berkas bukti, dengan aturan yang SAMA seperti bukti laporan
     * omzet (lihat MenyimpanBuktiOmzet):
     *
     *   - nama berkas di disk dibangkitkan sistem, tidak pernah dari pengguna
     *   - disimpan di disk PRIVAT, hanya keluar lewat route ber-otorisasi
     *   - request tanpa berkas berarti "tidak mengubah bukti", bukan "hapus"
     *   - berkas lama TIDAK dihapus saat diganti; jejaknya dicatat
     *
     * Yang terakhir penting justru di sini: investasi adalah penyebut rasio
     * SROI. Kalau kuitansinya bisa ditimpa tanpa sisa, seseorang bisa
     * memperbaiki nominal sekaligus menghapus dasar nominal sebelumnya.
     */
    private function simpanBukti(Request $request, ProgramInvestment $investasi): void
    {
        if (! $request->hasFile('bukti')) {
            return;
        }

        $lama = $investasi->bukti_path;
        $path = $request->file('bukti')->store('bukti-investasi/' . $investasi->impact_program_id);

        if ($path === false) {
            return;
        }

        $investasi->forceFill([
            'bukti_path' => $path,
            'bukti_asli' => $request->file('bukti')->getClientOriginalName(),
        ])->save();

        if ($lama) {
            ActivityLog::record(
                'impact_updated',
                'ProgramInvestment',
                $investasi->id,
                'Mengganti bukti investasi "' . $investasi->uraian
                    . '". Bukti sebelumnya tetap tersimpan di: ' . $lama,
            );
        }
    }

    /**
     * Unduh bukti investasi — satu-satunya jalan ke berkasnya.
     *
     * Izinnya memakai Gate 'view' pada PROGRAM-nya, sama dengan halaman yang
     * menampilkan tombolnya. Jadi Pimpinan (yang berhak membaca dampak) bisa
     * memeriksa kuitansinya, sedangkan Pendamping dan Tim Koperasi tidak.
     */
    public function bukti(ProgramInvestment $investasi)
    {
        Gate::authorize('view', $investasi->program);

        abort_unless($investasi->punyaBukti(), 404);
        abort_unless(Storage::exists($investasi->bukti_path), 404);

        return Storage::download($investasi->bukti_path, $investasi->namaBukti());
    }
}
