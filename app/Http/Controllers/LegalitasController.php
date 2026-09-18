<?php

namespace App\Http\Controllers;

use App\Services\PenghapusanPermanenService;
use App\Models\LegalitasRecord;
use App\Models\Participant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class LegalitasController extends Controller
{
    public function create(Participant $participant)
    {
        $user = Auth::user();
        abort_unless($user->canAccessParticipant($participant), 403);

        return view('legalitas.create', [
            'participant' => $participant,
            'statusOptions' => LegalitasRecord::STATUS_OPTIONS,
            'saranNama' => LegalitasRecord::JENIS_DOKUMEN, // cuma saran/autocomplete, bukan daftar tertutup
        ]);
    }

    public function store(Request $request, Participant $participant)
    {
        $user = Auth::user();
        abort_unless($user->canAccessParticipant($participant), 403);

        $validated = $this->validated($request);

        $record = new LegalitasRecord($validated);
        $record->participant_id = $participant->id;
        $this->handleUpload($request, $record);
        $record->save();

        return redirect()
            ->route('participants.show', $participant)
            ->with('success', $validated['jenis_dokumen'] . ' berhasil ditambahkan.');
    }

    public function edit(LegalitasRecord $legalitasRecord)
    {
        $user = Auth::user();
        abort_unless($user->canAccessParticipant($legalitasRecord->pesertaTermasukArsip()), 403);

        return view('legalitas.edit', [
            'participant' => $legalitasRecord->pesertaTermasukArsip(),
            'record' => $legalitasRecord,
            'statusOptions' => LegalitasRecord::STATUS_OPTIONS,
            'saranNama' => LegalitasRecord::JENIS_DOKUMEN,
        ]);
    }

    public function update(Request $request, LegalitasRecord $legalitasRecord)
    {
        $user = Auth::user();
        abort_unless($user->canAccessParticipant($legalitasRecord->pesertaTermasukArsip()), 403);

        $validated = $this->validated($request);

        $legalitasRecord->fill($validated);
        $this->handleUpload($request, $legalitasRecord);
        $legalitasRecord->save();

        return redirect()
            ->route('participants.show', $legalitasRecord->pesertaTermasukArsip())
            ->with('success', $validated['jenis_dokumen'] . ' berhasil diperbarui.');
    }

    /**
     * ARSIPKAN dokumen legalitas — bukan hapus.
     *
     * ==================================================================
     * BERKASNYA SENGAJA TIDAK IKUT DIHAPUS
     * ==================================================================
     *
     * Sebelumnya method ini menghapus barisnya DAN berkas unggahannya dari
     * penyimpanan sekaligus. NIB, sertifikat halal, PIRT, dan izin usaha
     * adalah dokumen yang pengurusannya memakan waktu berbulan-bulan dan
     * biaya nyata bagi peserta — dan salinan di sini sering satu-satunya
     * salinan digital yang mereka punya.
     *
     * Karena itu berkasnya dibiarkan di tempatnya: tanpa itu, "pulihkan"
     * hanya akan mengembalikan baris kosong tanpa dokumennya. Berkas baru
     * benar-benar dibuang saat Admin menghapusnya permanen dari Arsip Data.
     */
    public function destroy(LegalitasRecord $legalitasRecord)
    {
        $user = Auth::user();
        abort_unless($user->canAccessParticipant($legalitasRecord->pesertaTermasukArsip()), 403);

        $participant = $legalitasRecord->pesertaTermasukArsip();

        $penghapusan = app(PenghapusanPermanenService::class);
        $alasan = $penghapusan->alasanTertahan($legalitasRecord);

        if ($alasan !== null) {
            // Bukan kegagalan sistem: ini keputusan yang disengaja, jadi
            // disampaikan sebagai penjelasan beserta apa yang menahannya.
            return back()->with('error', $alasan);
        }

        $ringkasan = $penghapusan->hapusDenganCatatan($legalitasRecord);

        return redirect()
            ->route('participants.show', $participant)
            ->with('success', 'Dihapus permanen: ' . $ringkasan . '. Data ini tidak dapat dipulihkan.');
    }

    public function download(LegalitasRecord $legalitasRecord)
    {
        $user = Auth::user();
        abort_unless($user->canAccessParticipant($legalitasRecord->pesertaTermasukArsip()), 403);
        abort_unless($legalitasRecord->file_path && Storage::exists($legalitasRecord->file_path), 404);

        return Storage::download(
            $legalitasRecord->file_path,
            $legalitasRecord->file_asli ?: basename($legalitasRecord->file_path)
        );
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'jenis_dokumen' => ['required', 'string', 'max:255'],
            'nomor_dokumen' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(LegalitasRecord::STATUS_OPTIONS)],
            'tanggal_terbit' => ['nullable', 'date'],
            'tanggal_berlaku' => ['nullable', 'date'],
            'catatan' => ['nullable', 'string'],
            'file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ], [
            'jenis_dokumen.required' => 'Nama legalitas/sertifikasi wajib diisi.',
        ]);
    }

    private function handleUpload(Request $request, LegalitasRecord $record): void
    {
        if (! $request->hasFile('file')) {
            return;
        }

        if ($record->file_path) {
            Storage::delete($record->file_path);
        }

        $file = $request->file('file');
        $record->file_path = $file->store('legalitas/' . $record->participant_id);
        $record->file_asli = $file->getClientOriginalName();
    }
}
