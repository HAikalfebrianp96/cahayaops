<?php

namespace App\Http\Controllers;

use App\Models\Participant;
use App\Models\TrainingRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * RIWAYAT PELATIHAN PESERTA.
 *
 * Sebelumnya modul ini Admin-only dan update()-nya hanya bisa mengubah
 * `status` — kalau nama pelatihan atau tanggalnya salah ketik, satu-satunya
 * jalan adalah menghapus lalu membuat ulang. Sekarang seluruh isinya bisa
 * diperbaiki, dan Pendamping ikut boleh mengelola pelatihan binaannya.
 *
 * Hak akses lewat [TrainingRecordPolicy], diturunkan dari PESERTANYA:
 *   Admin      -> semua data pelatihan.
 *   Pendamping -> hanya pelatihan milik binaannya sendiri.
 *   Pimpinan   -> read-only.
 *
 * Ada DUA jalur perubahan yang sengaja dipisah:
 *   updateStatus() -> dropdown status di Detail Peserta, satu klik.
 *   update()       -> form Edit lengkap.
 * Kalau keduanya digabung, validasi form lengkap akan menolak submit dari
 * dropdown yang cuma mengirim `status`.
 */
class TrainingController extends Controller
{
    public function store(Request $request, Participant $participant)
    {
        Gate::authorize('createFor', [TrainingRecord::class, $participant]);

        $participant->trainingRecords()->create($this->validated($request));

        return redirect()
            ->route('participants.show', $participant)
            ->with('success', 'Data pelatihan berhasil ditambahkan.');
    }

    public function edit(TrainingRecord $trainingRecord)
    {
        Gate::authorize('update', $trainingRecord);

        $trainingRecord->load('participant');

        return view('training.edit', [
            'record' => $trainingRecord,
            // pesertaTermasukArsip(): halaman riwayat harus tetap terbaca
            // walau pesertanya sudah diarsipkan.
            'participant' => $trainingRecord->pesertaTermasukArsip(),
            'statusOptions' => TrainingRecord::STATUS_OPTIONS,
        ]);
    }

    public function update(Request $request, TrainingRecord $trainingRecord)
    {
        Gate::authorize('update', $trainingRecord);

        $trainingRecord->update($this->validated($request));

        return redirect()
            ->route('participants.show', $trainingRecord->participant_id)
            ->with('success', 'Data pelatihan berhasil diperbarui.');
    }

    /**
     * Ubah status saja — dipakai dropdown cepat di Detail Peserta.
     * Field lain sengaja TIDAK disentuh.
     */
    public function updateStatus(Request $request, TrainingRecord $trainingRecord)
    {
        Gate::authorize('update', $trainingRecord);

        $trainingRecord->update($request->validate([
            'status' => ['required', Rule::in(TrainingRecord::STATUS_OPTIONS)],
        ]));

        return redirect()
            ->route('participants.show', $trainingRecord->participant_id)
            ->with('success', 'Status pelatihan diperbarui.');
    }

    /** SOFT DELETE — barisnya tetap ada di database. */
    public function destroy(TrainingRecord $trainingRecord)
    {
        Gate::authorize('delete', $trainingRecord);

        $participantId = $trainingRecord->participant_id;
        $trainingRecord->delete();

        return redirect()
            ->route('participants.show', $participantId)
            ->with('success', 'Data pelatihan dihapus.');
    }

    /**
     * Memulihkan data pelatihan dari Arsip. Route-nya memakai withTrashed().
     * Tercatat sebagai 'training_restored' lewat Observer.
     */
    public function restore(TrainingRecord $trainingRecord)
    {
        Gate::authorize('restore', $trainingRecord);

        $trainingRecord->restore();

        return back()->with('success', 'Data pelatihan berhasil dipulihkan.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'tanggal' => ['required', 'date'],
            'nama_pelatihan' => ['required', 'string', 'max:255'],
            'penyelenggara' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(TrainingRecord::STATUS_OPTIONS)],
            'catatan' => ['nullable', 'string'],
        ]);
    }
}
