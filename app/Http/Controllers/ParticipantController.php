<?php

namespace App\Http\Controllers;

use App\Services\PenghapusanPermanenService;
use App\Models\ActivityLog;
use App\Models\Participant;
use App\Models\Pendamping;
use App\Models\PendampinganRecord;
use App\Models\TrainingRecord;
use App\Services\FollowUpService;
use App\Services\PerkembanganService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * DATA PESERTA / BINAAN — master identitas Cahaya Preneur.
 *
 * Pembagian kewenangan (semuanya lewat [ParticipantPolicy], bukan sekadar
 * menyembunyikan tombol di Blade):
 *
 *   index    Admin saja. Pendamping punya daftarnya sendiri di "Binaan Saya"
 *            (BinaanSayaController) yang sudah ter-scope ke dirinya.
 *   create   Admin & Pendamping yang akunnya tertaut data Pendamping.
 *   show     Admin, Pendamping pemilik binaan, Pimpinan (read-only).
 *   edit     Admin & Pendamping pemilik binaan.
 *   destroy  Admin saja — Soft Delete, datanya TIDAK hilang.
 *   restore  Admin saja.
 *
 * Yang dijaga khusus: kolom `pendamping_id`. Hanya Admin yang boleh
 * menentukan/memindahkannya. Untuk Pendamping, nilainya SELALU dipaksa
 * menjadi dirinya sendiri — input form manapun diabaikan — supaya tidak ada
 * jalan untuk mengambil alih binaan orang lain atau melepas binaan sendiri
 * dari pengawasan.
 */
class ParticipantController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('viewAny', Participant::class);

        // koperasiMember & penghapus ikut di-eager-load supaya kolom "Koperasi"
        // dan "Dihapus oleh" tidak menembak satu query per baris.
        $query = Participant::with(['pendamping', 'koperasiMember']);

        $this->terapkanFilter($query, $request);

        $participants = $query->orderBy('nama_peserta')->paginate(10)->withQueryString();

        return view('participants.index', array_merge($this->dataFilter($request), [
            'participants' => $participants,
        ]));
    }

    public function create()
    {
        Gate::authorize('create', Participant::class);

        return view('participants.create', $this->dataForm());
    }

    public function store(Request $request)
    {
        Gate::authorize('create', Participant::class);

        $participant = Participant::create($this->validated($request));

        return redirect()
            ->route('participants.show', $participant)
            ->with('success', 'Peserta baru berhasil ditambahkan.');
    }

    public function show(Participant $participant, PerkembanganService $perkembangan, FollowUpService $followUp)
    {
        $user = Auth::user();

        // Admin: semua. Pendamping: binaan sendiri. Pimpinan: semua (read-only).
        Gate::authorize('view', $participant);

        $participant->load([
            'pendamping',
            'omzetReports',
            'pendampinganRecords.pendamping',
            'trainingRecords',
            'legalitasRecords',
            'hppCalculations', // K9 — HPP Cahaya Preneur, dipakai di section "HPP Produk"
            'measurements',    // Baseline/monitoring/endline — ringkasannya di tab Perkembangan
            'koperasiMember.unitUsaha', // Status keanggotaan Koperasi Cahaya (opsional)
        ]);

        return view('participants.show', [
            'participant' => $participant,
            'jenisKegiatanOptions' => PendampinganRecord::JENIS_KEGIATAN,
            'statusPelatihanOptions' => TrainingRecord::STATUS_OPTIONS,
            'pendampings' => Pendamping::orderBy('nama')->get(),
            'timeline' => $participant->timeline(),
            'bolehUbahPeserta' => $user->can('update', $participant),
            'bolehHapusPeserta' => $user->can('delete', $participant),
            // Riwayat perubahan diambil dari Activity Log yang SUDAH ADA —
            // bukan tabel histori kedua. Dibatasi ke jejak peserta ini saja.
            'riwayatPerubahan' => $this->riwayatPerubahan($participant),
            // Rapor perkembangan dihitung dari relasi yang SUDAH di-load di
            // atas — tidak ada query tambahan, dan tidak ada kolom status
            // yang disimpan lalu berpotensi basi.
            'rapor' => $perkembangan->rapor($participant),
            // Status tindak lanjut ditampilkan di kepala halaman supaya
            // pendamping yang datang dari Tugas Saya langsung melihat KENAPA
            // peserta ini muncul di daftarnya — tanpa harus mengingatnya.
            //
            // Dihitung FollowUpService yang sama dengan halaman Tugas Saya,
            // jadi lencana di sini tidak mungkin bercerita lain.
            'statusTindakLanjut' => $followUp->untukPeserta(
                $participant,
                $participant->pendampinganRecords()->latest('tanggal')->first(),
            ),
        ]);
    }

    public function edit(Participant $participant)
    {
        Gate::authorize('update', $participant);

        return view('participants.edit', $this->dataForm($participant));
    }

    public function update(Request $request, Participant $participant)
    {
        Gate::authorize('update', $participant);

        $participant->update($this->validated($request, $participant));

        return redirect()
            ->route('participants.show', $participant)
            ->with('success', 'Data peserta berhasil diperbarui.');
    }

    /**
     * SOFT DELETE — peserta ditandai terhapus, TIDAK dihapus dari database.
     *
     * Seluruh relasinya (omzet, pendampingan, pelatihan, legalitas, HPP,
     * keanggotaan koperasi) tetap utuh dan kembali lengkap begitu dipulihkan.
     */
    public function destroy(Participant $participant)
    {
        Gate::authorize('delete', $participant);

        $penghapusan = app(PenghapusanPermanenService::class);
        $alasan = $penghapusan->alasanTertahan($participant);

        if ($alasan !== null) {
            // Bukan kegagalan sistem: ini keputusan yang disengaja, jadi
            // disampaikan sebagai penjelasan beserta apa yang menahannya.
            return back()->with('error', $alasan);
        }

        $ringkasan = $penghapusan->hapusDenganCatatan($participant);

        return redirect()
            ->route('participants.index')
            ->with('success', 'Dihapus permanen: ' . $ringkasan . '. Data ini tidak dapat dipulihkan.');
    }

    /**
     * Data yang dibutuhkan form Tambah/Edit.
     *
     * `bolehPilihPendamping` menentukan apakah field Pendamping dirender.
     * Ini SEMATA-MATA untuk tampilan — pengamannya ada di validated(), yang
     * membuang nilai pendamping_id dari request siapa pun yang tidak berhak.
     */
    private function dataForm(?Participant $participant = null): array
    {
        $data = [
            'pendampings' => Pendamping::orderBy('nama')->get(),
            'kategoriOptions' => Participant::KATEGORI_OPTIONS,
            // statusPilihan() menyertakan nilai status LAMA milik peserta ini
            // kalau ada, supaya membuka form Edit tidak diam-diam mengubah
            // statusnya cuma karena nilainya tidak ada di daftar baru.
            'statusOptions' => $participant?->statusPilihan() ?? Participant::STATUS_OPTIONS,
            'statusLegacy' => Participant::STATUS_LEGACY,
            'statusUsahaOptions' => Participant::STATUS_USAHA_OPTIONS,
            'bolehPilihPendamping' => Auth::user()->can('assignPendamping', Participant::class),
        ];

        if ($participant !== null) {
            $data['participant'] = $participant;
        }

        return $data;
    }

    private function validated(Request $request, ?Participant $participant = null): array
    {
        $user = Auth::user();

        $data = $request->validate([
            'nama_peserta' => ['required', 'string', 'max:255'],
            'nomor_identitas' => ['nullable', 'string', 'max:50'],
            'nomor_hp' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'alamat' => ['nullable', 'string'],
            'kecamatan' => ['nullable', 'string', 'max:100'],
            'kelurahan' => ['nullable', 'string', 'max:100'],
            'nama_usaha' => ['required', 'string', 'max:255'],
            'alamat_usaha' => ['nullable', 'string'],
            'kategori_usaha' => ['required', Rule::in(Participant::KATEGORI_OPTIONS)],
            'produk' => ['nullable', 'string', 'max:255'],
            'tahun_berdiri' => ['nullable', 'integer', 'min:1900', 'max:' . (now()->year + 1)],
            'status_usaha' => ['nullable', Rule::in(Participant::STATUS_USAHA_OPTIONS)],
            'tanggal_mulai_program' => ['required', 'date'],
            // statusYangSah() = pilihan baru + nilai lama yang masih dipakai
            // baris existing. Kalau dibatasi ke STATUS_OPTIONS saja, peserta
            // ber-status 'Graduasi'/'Tidak Aktif' tidak akan pernah bisa
            // disimpan lagi tanpa mengubah statusnya lebih dulu.
            'status' => ['required', Rule::in(Participant::statusYangSah())],
            'catatan_internal' => ['nullable', 'string'],
            'pendamping_id' => ['nullable', 'exists:pendampings,id'],
        ]);

        // ==========================================================
        // PENJAGAAN pendamping_id — inti keamanan modul ini.
        //
        // Admin  : bebas menentukan/mengosongkan/memindahkan.
        // Lainnya: nilai dari form DIBUANG, lalu diisi paksa.
        //          - Tambah : selalu pendamping yang sedang login.
        //          - Edit   : dikunci ke nilai yang sudah tersimpan, jadi
        //                     Pendamping tidak bisa melepas atau memindahkan
        //                     binaannya lewat request manual.
        // ==========================================================
        if ($user->can('assignPendamping', Participant::class)) {
            return $data;
        }

        $data['pendamping_id'] = $participant
            ? $participant->pendamping_id
            : $user->pendamping_id;

        return $data;
    }

    /**
     * EXPORT daftar peserta (CSV, terbuka di Excel).
     *
     * Memakai terapkanFilter() yang SAMA dengan halaman daftar, jadi isi file
     * selalu persis sama dengan yang sedang terlihat di layar — termasuk
     * pencarian, filter pendamping, status, jenis usaha, kecamatan, tahun
     * masuk, dan status koperasi.
     *
     * Kolomnya sengaja lebih lengkap daripada tabel di layar (tabel dibatasi
     * agar tetap terbaca; file export memang untuk diolah lebih lanjut).
     *
     * TIDAK mengubah export/PDF yang sudah ada di modul Laporan — itu
     * laporan dengan format bakunya sendiri, sedangkan ini export daftar
     * kerja sehari-hari.
     */
    public function export(Request $request)
    {
        Gate::authorize('viewAny', Participant::class);

        $query = Participant::with(['pendamping', 'koperasiMember']);

        self::terapkanFilter($query, $request);

        $peserta = $query->orderBy('nama_peserta')->get();

        $header = [
            'Nama Peserta', 'Nomor Identitas', 'Nomor HP', 'Email', 'Alamat',
            'Kecamatan', 'Kelurahan/Desa', 'Nama Usaha', 'Alamat Usaha',
            'Jenis Usaha', 'Produk', 'Tahun Berdiri', 'Status Usaha',
            'Pendamping', 'Status Peserta', 'Tanggal Mulai Program', 'Status Koperasi',
        ];

        $namaFile = 'peserta-cahaya-preneur-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($header, $peserta) {
            $handle = fopen('php://output', 'w');
            // BOM UTF-8 supaya Excel di Windows membaca huruf beraksen dengan
            // benar — tanpa ini "Ë†" dan sejenisnya muncul di nama peserta.
            fwrite($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($handle, $header);

            foreach ($peserta as $p) {
                fputcsv($handle, [
                    $p->nama_peserta,
                    $p->nomor_identitas ?: '-',
                    $p->nomor_hp,
                    $p->email ?: '-',
                    $p->alamat ?: '-',
                    $p->kecamatan ?: '-',
                    $p->kelurahan ?: '-',
                    $p->nama_usaha,
                    $p->alamat_usaha ?: '-',
                    $p->kategori_usaha,
                    $p->produk ?: '-',
                    $p->tahun_berdiri ?: '-',
                    $p->status_usaha ?: '-',
                    $p->pendamping->nama ?? '-',
                    $p->status,
                    $p->tanggal_mulai_program?->format('d-m-Y') ?? '-',
                    $p->koperasiMember ? 'Anggota (' . $p->koperasiMember->nomor_anggota . ')' : 'Bukan anggota',
                ]);
            }

            fclose($handle);
        }, $namaFile, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Filter daftar peserta.
     *
     * Dipisah ke method sendiri karena dipakai dua kali: sekali untuk
     * halaman daftar, sekali lagi untuk Export — dan keduanya HARUS
     * menyaring dengan cara yang persis sama. Kalau ditulis dua kali, cepat
     * atau lambat hasil export akan berbeda dari yang terlihat di layar.
     */
    public static function terapkanFilter($query, Request $request): void
    {
        if ($request->filled('q')) {
            $keyword = $request->string('q');
            $query->where(function ($sub) use ($keyword) {
                $sub->where('nama_peserta', 'like', "%{$keyword}%")
                    ->orWhere('nama_usaha', 'like', "%{$keyword}%")
                    ->orWhere('nomor_hp', 'like', "%{$keyword}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('pendamping_id')) {
            $query->where('pendamping_id', $request->integer('pendamping_id'));
        }

        if ($request->filled('kategori_usaha')) {
            $query->where('kategori_usaha', $request->string('kategori_usaha'));
        }

        if ($request->filled('kecamatan')) {
            $query->where('kecamatan', $request->string('kecamatan'));
        }

        if ($request->filled('tahun_masuk')) {
            $query->whereYear('tanggal_mulai_program', $request->integer('tahun_masuk'));
        }

        // Status koperasi — memakai relasi, BUKAN kolom salinan. Inilah
        // gunanya satu identitas: pertanyaan lintas modul bisa dijawab
        // tanpa menyalin data koperasi ke tabel peserta.
        if ($request->filled('status_koperasi')) {
            $request->string('status_koperasi')->toString() === 'anggota'
                ? $query->whereHas('koperasiMember')
                : $query->whereDoesntHave('koperasiMember');
        }
    }

    /** Pilihan dropdown filter + nilai filter yang sedang aktif. */
    private function dataFilter(Request $request): array
    {
        return [
            'pendampings' => Pendamping::orderBy('nama')->get(),
            'statusOptions' => Participant::statusYangSah(),
            'kategoriOptions' => Participant::KATEGORI_OPTIONS,
            'kecamatanOptions' => Participant::withTrashed()
                ->whereNotNull('kecamatan')
                ->where('kecamatan', '!=', '')
                ->distinct()
                ->orderBy('kecamatan')
                ->pluck('kecamatan'),
            // Tahun diambil di PHP, BUKAN dengan YEAR() di SQL: fungsi itu
            // hanya ada di MySQL, sementara test berjalan di SQLite. Daftar
            // pesertanya kecil, jadi menghitungnya di sini tidak mahal dan
            // dijamin berperilaku sama di kedua database.
            'tahunOptions' => Participant::withTrashed()
                ->whereNotNull('tanggal_mulai_program')
                ->pluck('tanggal_mulai_program')
                ->map(fn ($tanggal) => $tanggal?->year)
                ->filter()
                ->unique()
                ->sortDesc()
                ->values(),
            'filters' => $request->only([
                'q', 'status', 'pendamping_id', 'kategori_usaha',
                'kecamatan', 'tahun_masuk', 'status_koperasi',
            ]),
        ];
    }

    /**
     * Riwayat perubahan peserta — dibaca dari Activity Log yang SUDAH ADA.
     *
     * Sengaja TIDAK membuat tabel histori kedua: setiap perubahan peserta,
     * catatan pendampingan, pelatihan, dan omzet sudah dicatat Observer
     * lengkap dengan pelaku dan waktunya. Yang kurang selama ini hanyalah
     * cara membacanya per peserta.
     */
    private function riwayatPerubahan(Participant $participant): \Illuminate\Support\Collection
    {
        $idAnak = [
            'PendampinganRecord' => $participant->pendampinganRecords->pluck('id'),
            'TrainingRecord' => $participant->trainingRecords->pluck('id'),
            'OmzetReport' => $participant->omzetReports->pluck('id'),
        ];

        return ActivityLog::with('user')
            ->where(function ($q) use ($participant, $idAnak) {
                $q->where(function ($sub) use ($participant) {
                    $sub->where('model_type', 'Participant')->where('model_id', $participant->id);
                });

                foreach ($idAnak as $tipe => $ids) {
                    if ($ids->isNotEmpty()) {
                        $q->orWhere(function ($sub) use ($tipe, $ids) {
                            $sub->where('model_type', $tipe)->whereIn('model_id', $ids);
                        });
                    }
                }
            })
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();
    }
}
