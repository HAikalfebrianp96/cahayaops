<?php

namespace App\Http\Controllers;

use App\Models\Participant;
use App\Models\Pendamping;
use App\Models\PendampinganRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * RIWAYAT PENDAMPINGAN — catatan kerja lapangan per peserta.
 *
 * Catatan lapangan wajar mengandung salah ketik (tanggal keliru, jenis
 * kegiatan tertukar), jadi sejak sekarang setiap catatan bisa DIUBAH dan
 * DIHAPUS — bukan dibiarkan salah atau diakali dengan membuat catatan baru.
 *
 * Hak akses ditentukan [PendampinganRecordPolicy] dan diturunkan dari
 * PESERTANYA:
 *   Admin      -> semua catatan.
 *   Pendamping -> hanya catatan milik binaannya sendiri.
 *   Pimpinan   -> tidak boleh mengubah apa pun (read-only di Detail Peserta).
 *
 * Penghapusan memakai Soft Delete, jadi catatan yang dibuang tetap ada di
 * database dan tetap bisa diaudit lewat Activity Log.
 */
class PendampinganController extends Controller
{
    public function store(Request $request, Participant $participant)
    {
        $user = Auth::user();
        Gate::authorize('createFor', [PendampinganRecord::class, $participant]);

        $validated = $this->validated($request);

        PendampinganRecord::create([
            'participant_id' => $participant->id,
            'pendamping_id' => $this->pendampingId($validated, $participant->pendamping_id),
            'tanggal' => $validated['tanggal'],
            'jenis_kegiatan' => $validated['jenis_kegiatan'],
            ...$this->isiSiklus($validated),
        ]);

        return redirect()
            ->route('participants.show', $participant)
            ->with('success', 'Catatan pendampingan berhasil ditambahkan.');
    }

    public function edit(PendampinganRecord $pendampinganRecord)
    {
        Gate::authorize('update', $pendampinganRecord);

        $pendampinganRecord->load('participantTermasukArsip');

        return view('pendampingan.edit', [
            'record' => $pendampinganRecord,
            // pesertaTermasukArsip(): halaman riwayat harus tetap terbaca
            // walau pesertanya sudah diarsipkan.
            'participant' => $pendampinganRecord->pesertaTermasukArsip(),
            'jenisKegiatanOptions' => PendampinganRecord::JENIS_KEGIATAN,
            'pendampings' => Pendamping::orderBy('nama')->get(),
            'bolehPilihPendamping' => Auth::user()->can('assignPendamping', PendampinganRecord::class),
        ]);
    }

    public function update(Request $request, PendampinganRecord $pendampinganRecord)
    {
        Gate::authorize('update', $pendampinganRecord);

        $validated = $this->validated($request);

        $pendampinganRecord->update([
            'pendamping_id' => $this->pendampingId($validated, $pendampinganRecord->pendamping_id),
            'tanggal' => $validated['tanggal'],
            'jenis_kegiatan' => $validated['jenis_kegiatan'],
            ...$this->isiSiklus($validated),
        ]);

        return redirect()
            ->route('participants.show', $pendampinganRecord->participant_id)
            ->with('success', 'Catatan pendampingan berhasil diperbarui.');
    }

    public function destroy(PendampinganRecord $pendampinganRecord)
    {
        Gate::authorize('delete', $pendampinganRecord);

        $participantId = $pendampinganRecord->participant_id;
        $pendampinganRecord->delete();

        return redirect()
            ->route('participants.show', $participantId)
            ->with('success', 'Catatan pendampingan dihapus.');
    }

    /**
     * Memulihkan catatan dari Arsip. Route-nya memakai withTrashed().
     *
     * Observer mencatatnya sebagai 'mentoring_restored' lewat hook restored()
     * — sistem Activity Log yang sama, bukan pencatatan kedua.
     */
    public function restore(PendampinganRecord $pendampinganRecord)
    {
        Gate::authorize('restore', $pendampinganRecord);

        $pendampinganRecord->restore();

        return back()->with('success', 'Catatan pendampingan berhasil dipulihkan.');
    }

    /**
     * Monitoring Pendampingan — khusus Admin, melihat SEMUA catatan pendampingan.
     */
    public function index(Request $request)
    {
        Gate::authorize('viewAny', PendampinganRecord::class);

        // Daftar riwayat memuat ratusan catatan, termasuk milik peserta yang
        // sudah diarsipkan. Relasi biasa membuat baris-baris itu kehilangan
        // nama pemiliknya — dan halamannya berhenti terbuka sama sekali.
        $query = PendampinganRecord::with(['participantTermasukArsip', 'pendamping']);

        if ($request->filled('pendamping_id')) {
            $query->where('pendamping_id', $request->integer('pendamping_id'));
        }

        if ($request->filled('participant_id')) {
            $query->where('participant_id', $request->integer('participant_id'));
        }

        if ($request->filled('jenis_kegiatan')) {
            $query->where('jenis_kegiatan', $request->string('jenis_kegiatan'));
        }

        if ($request->filled('dari')) {
            $query->whereDate('tanggal', '>=', $request->date('dari'));
        }

        if ($request->filled('sampai')) {
            $query->whereDate('tanggal', '<=', $request->date('sampai'));
        }

        $records = $query->orderByDesc('tanggal')->paginate(15)->withQueryString();

        return view('pendampingan.index', [
            'records' => $records,
            'pendampings' => Pendamping::orderBy('nama')->get(),
            'participants' => Participant::orderBy('nama_peserta')->get(),
            'jenisOptions' => PendampinganRecord::JENIS_KEGIATAN,
            'filters' => $request->only(['pendamping_id', 'participant_id', 'jenis_kegiatan', 'dari', 'sampai']),
        ]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            // ============================================================
            // TIDAK BOLEH BERTANGGAL MASA DEPAN.
            //
            // Pendampingan adalah kegiatan yang SUDAH terjadi. Salah ketik
            // tahun — 2027 alih-alih 2026 — dulu diterima begitu saja, dan
            // akibatnya sunyi sekaligus berat:
            //
            //   * catatan itu menjadi "aktivitas terakhir", sehingga
            //     pesertanya terbaca baru saja didampingi;
            //   * ia terkunci di status Normal dan HILANG dari daftar tindak
            //     lanjut sampai tanggal itu benar-benar lewat;
            //   * layarnya menampilkan "Didampingi -365 hari lalu".
            //
            // Mencatat MUNDUR tetap boleh — pendamping lazim mengisi catatan
            // minggu lalu pada hari Jumat.
            //
            // Catatan: tanggal_follow_up di bawah SENGAJA tidak dibatasi; itu
            // rencana, dan rencana memang berada di masa depan.
            // ============================================================
            'tanggal' => ['required', 'date', 'before_or_equal:today'],
            'jenis_kegiatan' => ['required', Rule::in(PendampinganRecord::JENIS_KEGIATAN)],
            'materi' => ['required', 'string'],
            'catatan' => ['nullable', 'string'],
            // SIKLUS TINDAK LANJUT.
            // `kendala` = Permasalahan dan `tindak_lanjut` = Rencana Tindak
            // Lanjut; keduanya kolom LAMA yang dipakai ulang, bukan kolom
            // kembar bernama lain.
            'kendala' => ['nullable', 'string'],
            'tindakan' => ['nullable', 'string'],
            'hasil' => ['nullable', 'string'],
            'tindak_lanjut' => ['nullable', 'string'],
            'tanggal_follow_up' => ['nullable', 'date'],
            'pendamping_id' => ['nullable', 'exists:pendampings,id'],
        ], [
            // Menjelaskan, bukan sekadar menolak: yang paling sering terjadi
            // adalah salah ketik tahun, dan pesannya harus mengarahkan ke situ.
            'tanggal.before_or_equal' => 'Tanggal pendampingan tidak boleh di masa depan. '
                . 'Periksa kembali tahunnya — mencatat mundur tetap boleh.',
        ]);
    }

    /**
     * Isi materi + seluruh tahap siklus tindak lanjut.
     *
     * Dikumpulkan di satu tempat supaya store() dan update() TIDAK PERNAH
     * menyimpan kumpulan field yang berbeda — kalau ditulis dua kali, satu
     * field baru pasti terlupa di salah satunya.
     */
    private function isiSiklus(array $validated): array
    {
        return [
            'materi' => $validated['materi'],
            'catatan' => $validated['catatan'] ?? null,
            'kendala' => $validated['kendala'] ?? null,
            'tindakan' => $validated['tindakan'] ?? null,
            'hasil' => $validated['hasil'] ?? null,
            'tindak_lanjut' => $validated['tindak_lanjut'] ?? null,
            'tanggal_follow_up' => $validated['tanggal_follow_up'] ?? null,
        ];
    }

    /**
     * Siapa Pendamping pelaksana yang dicatat.
     *
     * Kalau yang login Pendamping, nilainya SELALU dipaksa jadi dirinya
     * sendiri (mengabaikan input form manapun) — supaya tidak bisa
     * mengatasnamakan pendamping lain, baik saat menambah maupun saat
     * mengedit catatan lama.
     *
     * $bawaan dipakai kalau field-nya memang TIDAK ADA di request: saat
     * menambah memakai pendamping peserta, saat mengedit mempertahankan nilai
     * lama. Kalau field-nya ada tapi dikosongkan, itu pilihan sadar Admin
     * ("— Tidak ditentukan —") dan dihormati apa adanya.
     */
    private function pendampingId(array $validated, ?int $bawaan): ?int
    {
        $user = Auth::user();

        if ($user->isPendamping()) {
            return $user->pendamping_id;
        }

        return array_key_exists('pendamping_id', $validated)
            ? $validated['pendamping_id']
            : $bawaan;
    }
}
