<?php

namespace App\Http\Controllers;

use App\Models\Participant;
use App\Models\ParticipantMeasurement;
use App\Services\PerbandinganService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * PENGUKURAN PERKEMBANGAN USAHA — baseline, monitoring, endline.
 *
 * Menempel pada peserta, persis seperti PendampinganController dan
 * TrainingController: route-nya bersarang di bawah peserta untuk membuat,
 * lalu berdiri sendiri untuk ubah/hapus/pulihkan. Polanya sengaja disamakan
 * supaya pendamping tidak perlu mempelajari alur kerja baru untuk hal yang
 * bentuknya sama.
 */
class ParticipantMeasurementController extends Controller
{
    public function __construct(private PerbandinganService $perbandingan)
    {
    }

    /** Riwayat pengukuran + perbandingan baseline/endline satu peserta. */
    public function index(Participant $participant)
    {
        // Pimpinan ikut boleh (read-only) — canViewParticipant yang mengatur.
        abort_unless(Auth::user()->canViewParticipant($participant), 403);

        $participant->load(['measurements.pencatat', 'pendamping']);

        return view('perkembangan.index', [
            'participant' => $participant,
            'rapor' => $this->perbandingan->untukPeserta($participant),
            'bisaKelola' => Auth::user()->canAccessParticipant($participant),
        ]);
    }

    public function create(Participant $participant)
    {
        Gate::authorize('createFor', [ParticipantMeasurement::class, $participant]);

        return view('perkembangan.form', [
            'participant' => $participant,
            'measurement' => null,
            // Jenis yang DISARANKAN, bukan yang dipaksakan: peserta yang belum
            // punya baseline hampir selalu sedang mencatat baseline. Petugas
            // tetap bebas memilih yang lain — sistem tidak tahu lebih banyak
            // daripada orang yang sedang berdiri di depan usahanya.
            'jenisDisarankan' => $participant->measurements()->jenis(ParticipantMeasurement::BASELINE)->exists()
                ? ParticipantMeasurement::MONITORING
                : ParticipantMeasurement::BASELINE,
        ]);
    }

    public function store(Request $request, Participant $participant)
    {
        Gate::authorize('createFor', [ParticipantMeasurement::class, $participant]);

        $data = $this->validasi($request);

        $measurement = $participant->measurements()->create($data + [
            'dicatat_oleh' => Auth::id(),
        ]);

        return redirect()
            ->route('perkembangan.index', $participant)
            ->with('success', $measurement->jenis . ' berhasil dicatat.');
    }

    public function edit(ParticipantMeasurement $perkembangan)
    {
        Gate::authorize('update', $perkembangan);

        return view('perkembangan.form', [
            'participant' => $perkembangan->pesertaTermasukArsip(),
            'measurement' => $perkembangan,
            'jenisDisarankan' => $perkembangan->jenis,
        ]);
    }

    public function update(Request $request, ParticipantMeasurement $perkembangan)
    {
        Gate::authorize('update', $perkembangan);

        $perkembangan->update($this->validasi($request));

        return redirect()
            ->route('perkembangan.index', $perkembangan->participant_id)
            ->with('success', 'Pengukuran berhasil diperbarui.');
    }

    /** SOFT DELETE — barisnya tetap ada dan bisa dipulihkan dari Arsip. */
    public function destroy(ParticipantMeasurement $perkembangan)
    {
        Gate::authorize('delete', $perkembangan);

        $participantId = $perkembangan->participant_id;
        $perkembangan->delete();

        return redirect()
            ->route('perkembangan.index', $participantId)
            ->with('success', 'Pengukuran dipindahkan ke Arsip dan bisa dipulihkan.');
    }

    public function restore(int $perkembangan)
    {
        $record = ParticipantMeasurement::withTrashed()->findOrFail($perkembangan);

        Gate::authorize('restore', $record);

        $record->restore();

        return back()->with('success', 'Pengukuran berhasil dipulihkan.');
    }

    /**
     * Aturan validasi — SAMA untuk tambah dan ubah.
     *
     * Seluruh kolom ukuran `nullable`, dan itu bukan kelonggaran: pendataan
     * lapangan jarang lengkap dalam satu kunjungan, dan memaksa isi hanya
     * akan menghasilkan angka asal supaya form bisa tersimpan. Angka asal
     * lebih berbahaya daripada kolom kosong, karena ia ikut masuk ke
     * perbandingan dan perhitungan dampak tanpa bisa dibedakan dari data
     * sungguhan.
     *
     * Yang TETAP dijaga ketat: jenis harus salah satu dari tiga yang sah, dan
     * tidak ada angka negatif.
     */
    private function validasi(Request $request): array
    {
        $angka = ['nullable', 'integer', 'min:0'];

        return $request->validate([
            'jenis' => ['required', Rule::in(ParticipantMeasurement::jenisYangSah())],
            'tanggal' => ['required', 'date', 'before_or_equal:today'],

            'omzet_bulanan' => $angka,
            'hpp_bulanan' => $angka,
            'biaya_operasional_bulanan' => $angka,
            'jumlah_tenaga_kerja' => $angka,
            'jumlah_produk' => $angka,
            'jumlah_pelanggan' => $angka,
            'kapasitas_produksi' => $angka,
            'satuan_produksi' => ['nullable', 'string', 'max:255'],

            'jangkauan_pasar' => ['nullable', Rule::in(array_keys(ParticipantMeasurement::PASAR_OPTIONS))],
            'punya_legalitas' => ['nullable', 'boolean'],
            'punya_pembukuan' => ['nullable', 'boolean'],
            'punya_rekening_usaha' => ['nullable', 'boolean'],
            'jualan_online' => ['nullable', 'boolean'],

            'catatan' => ['nullable', 'string'],
            'sumber_data' => ['nullable', 'string'],
        ], [
            'jenis.required' => 'Jenis pengukuran wajib dipilih.',
            'jenis.in' => 'Jenis pengukuran tidak dikenali.',
            'tanggal.required' => 'Tanggal pengukuran wajib diisi.',
            'tanggal.before_or_equal' => 'Tanggal pengukuran tidak boleh di masa depan.',
        ]);
    }
}
