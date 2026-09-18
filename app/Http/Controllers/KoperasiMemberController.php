<?php

namespace App\Http\Controllers;

use App\Services\PenghapusanPermanenService;
use App\Models\BusinessUnit;
use App\Models\KoperasiMember;
use App\Models\MemberSaving;
use App\Models\Participant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * KEANGGOTAAN KOPERASI CAHAYA.
 *
 * PRINSIP UTAMA: TIDAK ADA DUPLIKASI IDENTITAS.
 *
 * Seorang pelaku UMKM yang sudah terdaftar sebagai peserta Cahaya Preneur
 * TIDAK diketik ulang saat masuk koperasi. Tim Koperasi memilihnya dari
 * database peserta, dan barisnya di sini hanya menyimpan data KHUSUS
 * koperasi (nomor anggota, tanggal bergabung, status, unit usaha,
 * keterangan) plus `participant_id` sebagai penunjuk ke identitas aslinya.
 *
 * Dua jalur penambahan, keduanya sah:
 *
 *   1. Pilih dari Database Peserta  -> participant_id TERISI, identitas
 *      diambil dari [Participant]. Nama/HP/alamat di baris ini TIDAK
 *      diminta lagi dari operator.
 *   2. Input Anggota Baru           -> participant_id NULL, untuk orang yang
 *      memang bukan peserta Cahaya Preneur.
 *
 * Otorisasi lewat [KoperasiMemberPolicy] -> User::bolehKelolaKoperasi()
 * (Admin & Tim Koperasi). Tim Koperasi TETAP tidak bisa membuka modul
 * Cahaya Preneur maupun Pengaturan Sistem.
 */
class KoperasiMemberController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('viewAny', KoperasiMember::class);

        $members = KoperasiMember::with(['participant', 'unitUsaha', 'penghapus'])
            ->orderBy('nama')
            ->paginate(15)
            ->withQueryString();

        return view('koperasi.anggota.index', [
            'members' => $members,
        ]);
    }

    /**
     * Langkah 1 — memilih CARA menambah anggota.
     *
     * `?dari=peserta&participant_id=…` datang dari halaman pencarian peserta.
     * `?dari=baru` untuk orang yang bukan peserta Cahaya Preneur.
     */
    public function create(Request $request)
    {
        Gate::authorize('create', KoperasiMember::class);

        $dari = $request->string('dari')->toString();
        $participant = null;

        if ($request->filled('participant_id')) {
            $participant = Participant::findOrFail($request->integer('participant_id'));

            // Peserta yang SUDAH menjadi anggota tidak boleh didaftarkan lagi —
            // itulah duplikasi yang justru ingin dicegah.
            if ($participant->isAnggotaKoperasi()) {
                return redirect()
                    ->route('koperasi.anggota.pilih-peserta')
                    ->with('error', $participant->nama_peserta . ' sudah terdaftar sebagai anggota koperasi.');
            }

            $dari = 'peserta';
        }

        return view('koperasi.anggota.create', [
            'dari' => in_array($dari, ['peserta', 'baru'], true) ? $dari : null,
            'participant' => $participant,
            'statusOptions' => KoperasiMember::STATUS_OPTIONS,
            'sumberOptions' => KoperasiMember::SUMBER_OPTIONS,
            'unitUsahaOptions' => BusinessUnit::orderBy('nama')->get(),
        ]);
    }

    /**
     * Pencarian peserta Cahaya Preneur sebagai CALON ANGGOTA.
     *
     * Ini bukan pintu belakang ke modul Cahaya Preneur: hanya membaca, tidak
     * bisa mengubah apa pun, dan peserta yang sudah jadi anggota ditandai
     * supaya tidak bisa dipilih dua kali.
     */
    public function pilihPeserta(Request $request)
    {
        Gate::authorize('pilihDariPeserta', KoperasiMember::class);

        $query = Participant::with(['koperasiMember', 'pendamping']);

        if ($request->filled('q')) {
            $keyword = $request->string('q');
            $query->where(function ($sub) use ($keyword) {
                $sub->where('nama_peserta', 'like', "%{$keyword}%")
                    ->orWhere('nama_usaha', 'like', "%{$keyword}%")
                    ->orWhere('nomor_hp', 'like', "%{$keyword}%");
            });
        }

        if ($request->boolean('belum_anggota')) {
            $query->whereDoesntHave('koperasiMember');
        }

        return view('koperasi.anggota.pilih-peserta', [
            'participants' => $query->orderBy('nama_peserta')->paginate(10)->withQueryString(),
            'filters' => $request->only(['q', 'belum_anggota']),
        ]);
    }

    public function store(Request $request)
    {
        Gate::authorize('create', KoperasiMember::class);

        $validated = $this->validated($request);
        $simpanan = $this->validatedSimpananAwal($request);

        $member = DB::transaction(function () use ($validated, $simpanan) {
            $member = KoperasiMember::create($validated);

            // Simpanan Pokok & Wajib awal dicatat sebagai baris
            // member_savings biasa — memakai struktur yang SUDAH ADA, jadi
            // saldonya konsisten dengan riwayat simpanan dan laporan koperasi.
            foreach ($simpanan as $jenis => $nominal) {
                $member->savings()->create([
                    'jenis_simpanan' => $jenis,
                    'tanggal' => $member->tanggal_bergabung,
                    'nominal' => $nominal,
                    'keterangan' => 'Setoran awal saat pendaftaran anggota.',
                ]);
            }

            return $member;
        });

        return redirect()
            ->route('koperasi.anggota.show', $member)
            ->with('success', 'Anggota koperasi baru berhasil ditambahkan.');
    }

    public function show(KoperasiMember $anggota)
    {
        Gate::authorize('view', $anggota);

        $anggota->load(['participant.pendamping', 'savings', 'unitUsaha']);

        // Rumus yang SAMA dengan halaman Cek Simpanan milik anggota.
        $ringkasanSimpanan = $anggota->ringkasanSimpanan();

        return view('koperasi.anggota.show', [
            'member' => $anggota,
            'ringkasanSimpanan' => $ringkasanSimpanan,
            'jenisOptions' => MemberSaving::JENIS_OPTIONS,
        ]);
    }

    public function edit(KoperasiMember $anggota)
    {
        Gate::authorize('update', $anggota);

        $anggota->load('participant');

        return view('koperasi.anggota.edit', [
            'member' => $anggota,
            'statusOptions' => KoperasiMember::STATUS_OPTIONS,
            'sumberOptions' => KoperasiMember::SUMBER_OPTIONS,
            'unitUsahaOptions' => BusinessUnit::orderBy('nama')->get(),
            // Hanya peserta yang belum jadi anggota + peserta yang sedang
            // ditautkan ke baris ini. Peserta milik anggota lain tidak muncul,
            // supaya satu orang tidak bisa punya dua keanggotaan.
            'participants' => Participant::where(function ($q) use ($anggota) {
                $q->whereDoesntHave('koperasiMember');

                if ($anggota->participant_id !== null) {
                    $q->orWhere('id', $anggota->participant_id);
                }
            })->orderBy('nama_peserta')->get(),
        ]);
    }

    public function update(Request $request, KoperasiMember $anggota)
    {
        Gate::authorize('update', $anggota);

        $anggota->update($this->validated($request, $anggota));

        return redirect()
            ->route('koperasi.anggota.show', $anggota)
            ->with('success', 'Data anggota berhasil diperbarui.');
    }

    /**
     * SOFT DELETE — keanggotaan ditandai terhapus, riwayat simpanannya TIDAK
     * ikut hilang dan identitas pesertanya sama sekali tidak tersentuh.
     */
    public function destroy(KoperasiMember $anggota)
    {
        Gate::authorize('delete', $anggota);

        $nama = $anggota->namaTampil();
        $penghapusan = app(PenghapusanPermanenService::class);
        $alasan = $penghapusan->alasanTertahan($anggota);

        if ($alasan !== null) {
            // Bukan kegagalan sistem: ini keputusan yang disengaja, jadi
            // disampaikan sebagai penjelasan beserta apa yang menahannya.
            return back()->with('error', $alasan);
        }

        $ringkasan = $penghapusan->hapusDenganCatatan($anggota);

        return redirect()
            ->route('koperasi.anggota.index')
            ->with('success', 'Dihapus permanen: ' . $ringkasan . '. Data ini tidak dapat dipulihkan.');
    }

    /**
     * Generate PIN baru (6 digit acak) utk akses "Cek Simpanan Saya".
     * PIN plaintext CUMA ditampilkan sekali di halaman berikutnya lewat
     * flash session — tidak pernah disimpan di database dlm bentuk asli.
     */
    public function generatePin(KoperasiMember $anggota)
    {
        Gate::authorize('update', $anggota);

        $pinBaru = (string) random_int(100000, 999999);
        $anggota->setPin($pinBaru);

        return redirect()
            ->route('koperasi.anggota.show', $anggota)
            ->with('pin_baru', $pinBaru)
            ->with('success', 'PIN baru berhasil dibuat. Catat/sampaikan ke anggota SEKARANG — PIN ini tidak akan ditampilkan lagi.');
    }

    /**
     * "Jadikan Anggota Koperasi" — dipanggil dari halaman Detail Peserta.
     *
     * Jalan pintas untuk kasus yang paling sering terjadi: peserta yang
     * sedang dibuka memang mau didaftarkan. Alih-alih menyuruh operator
     * pindah modul lalu mencari nama yang sama, halaman ini langsung membuka
     * form pendaftaran dengan pesertanya sudah terpilih.
     *
     * Ini BUKAN jalur pendaftaran kedua: ia hanya meneruskan ke create()
     * yang sama, jadi seluruh aturan anti-duplikasinya berlaku persis sama.
     */
    public function jadikanAnggota(Participant $participant)
    {
        Gate::authorize('create', KoperasiMember::class);

        return redirect()->route('koperasi.anggota.create', ['participant_id' => $participant->id]);
    }

    private function validated(Request $request, ?KoperasiMember $anggota = null): array
    {
        $data = $request->validate([
            'nama' => ['required_without:participant_id', 'nullable', 'string', 'max:255'],
            'nomor_hp' => ['required_without:participant_id', 'nullable', 'string', 'max:30'],
            'alamat' => ['nullable', 'string'],
            'tanggal_bergabung' => ['required', 'date'],
            'status' => ['required', Rule::in(KoperasiMember::STATUS_OPTIONS)],
            'keterangan' => ['nullable', 'string'],
            'unit_usaha_id' => ['nullable', 'exists:business_units,id'],
            'sumber_anggota' => ['nullable', Rule::in(KoperasiMember::SUMBER_OPTIONS)],
            // ANTI-DUPLIKASI di level backend: satu peserta hanya boleh punya
            // satu keanggotaan. Arsip (soft delete) sengaja diabaikan supaya
            // anggota yang pernah dihapus bisa didaftarkan lagi.
            'participant_id' => [
                'nullable',
                'exists:participants,id',
                Rule::unique('koperasi_members', 'participant_id')
                    ->whereNull('deleted_at')
                    ->ignore($anggota?->id),
            ],
        ], [
            'nama.required_without' => 'Nama wajib diisi kalau anggota bukan berasal dari database peserta.',
            'nomor_hp.required_without' => 'Nomor HP wajib diisi kalau anggota bukan berasal dari database peserta.',
            'participant_id.unique' => 'Peserta ini sudah terdaftar sebagai anggota koperasi. Satu orang hanya boleh punya satu keanggotaan.',
        ]);

        // Anggota yang berasal dari peserta: identitasnya TIDAK diketik ulang.
        // Salinan nama/HP/alamat di baris ini hanya diisi sekali sebagai
        // cadangan tampilan; yang selalu dipakai untuk menampilkan adalah
        // accessor namaTampil()/nomorHpTampil() yang membaca dari peserta.
        if (! empty($data['participant_id'])) {
            $participant = Participant::find($data['participant_id']);

            if ($participant) {
                $data['nama'] = $participant->nama_peserta;
                $data['nomor_hp'] = $participant->nomor_hp;
                $data['alamat'] = $participant->alamat;
            }

            // Asal-usul diisi otomatis HANYA saat baris ini belum punya
            // catatan sumber. Kalau sudah ada, nilainya dipertahankan —
            // seorang pendaftar mandiri yang belakangan ditautkan ke data
            // peserta tetap tercatat sebagai pendaftar mandiri, karena itulah
            // yang benar-benar terjadi.
            if (empty($data['sumber_anggota']) && ! $anggota?->sumber_anggota) {
                $data['sumber_anggota'] = KoperasiMember::SUMBER_PESERTA;
            }
        }

        return $data;
    }

    /**
     * Simpanan Pokok & Wajib yang diisi saat pendaftaran (opsional).
     * Dikembalikan sebagai [jenis simpanan => nominal], hanya yang > 0.
     */
    private function validatedSimpananAwal(Request $request): array
    {
        $data = $request->validate([
            'simpanan_pokok' => ['nullable', 'numeric', 'min:0'],
            'simpanan_wajib' => ['nullable', 'numeric', 'min:0'],
        ]);

        return array_filter([
            'Simpanan Pokok' => (float) ($data['simpanan_pokok'] ?? 0),
            'Simpanan Wajib' => (float) ($data['simpanan_wajib'] ?? 0),
        ], fn ($nominal) => $nominal > 0);
    }
}
