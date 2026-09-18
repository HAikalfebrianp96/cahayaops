<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\MenentukanPeriodeOmzet;
use App\Http\Controllers\Concerns\MenyimpanBuktiOmzet;
use App\Models\OmzetReport;
use App\Models\Participant;
use App\Models\Pendamping;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class OmzetMonitoringController extends Controller
{
    use MenentukanPeriodeOmzet, MenyimpanBuktiOmzet;

    public function index(Request $request)
    {
        $user = Auth::user();

        // Halaman ini dirancang untuk tiga role saja. Sebelumnya di sini
        // TIDAK ADA penjagaan sama sekali: role di luar Pendamping tidak
        // masuk cabang manapun di bawah, jadi otomatis melihat SELURUH
        // omzet semua peserta (fail-open). Aksesnya sekarang disebut
        // eksplisit supaya role baru harus didaftarkan dengan sadar,
        // bukan diam-diam kebagian akses penuh.
        abort_unless(
            $user->isAdmin() || $user->isPimpinan() || $user->isPendamping(),
            403
        );

        $baseQuery = OmzetReport::with(['participant.pendamping']);

        // Pendamping HANYA boleh melihat laporan peserta binaannya sendiri.
        // Admin & Pimpinan tetap melihat semua (dibedakan lagi soal edit/update
        // di method edit()/update() — di sini keduanya sama-sama boleh LIHAT).
        if ($user->isPendamping()) {
            if ($user->pendamping_id) {
                $baseQuery->whereHas('participant', fn ($q) => $q->where('pendamping_id', $user->pendamping_id));
            } else {
                // Akun Pendamping yang belum terhubung ke data Pendamping manapun —
                // JANGAN tampilkan apapun (jangan sampai malah kebuka yg pendamping_id-nya kosong).
                $baseQuery->whereRaw('1 = 0');
            }
        }

        if ($request->filled('bulan')) {
            $baseQuery->where('periode_bulan', $request->integer('bulan'));
        }
        if ($request->filled('tahun')) {
            $baseQuery->where('periode_tahun', $request->integer('tahun'));
        }
        if ($request->filled('pendamping_id')) {
            $pendampingId = $request->integer('pendamping_id');
            $baseQuery->whereHas('participant', fn ($q) => $q->where('pendamping_id', $pendampingId));
        }
        if ($request->filled('q')) {
            $keyword = $request->string('q');
            $baseQuery->whereHas('participant', function ($q) use ($keyword) {
                $q->where('nama_peserta', 'like', "%{$keyword}%")
                    ->orWhere('nama_usaha', 'like', "%{$keyword}%");
            });
        }

        // ============================================================
        // RINGKASAN DIHITUNG DI DATABASE, BUKAN DI MEMORI
        //
        // Sebelumnya baris ini memuat SELURUH laporan yang cocok dengan
        // filter — lengkap dengan relasi participant.pendamping — hanya
        // untuk dijumlahkan. Daftarnya sendiri sudah ter-paginasi 10 baris,
        // jadi yang mahal semata-mata ringkasannya.
        //
        // Terukur pada volume lima tahun pemakaian (12.000 laporan omzet):
        // halaman ini memakan 7,6 MB memori dan lebih dari satu detik.
        // Pada data hari ini (271 laporan) sama sekali tidak terasa.
        //
        // Yang dimuat sekarang hanya laporan yang PUNYA data laba, karena
        // hanya baris itulah yang benar-benar perlu dihitung di PHP —
        // labaKotor() & profitBersih() sengaja mengembalikan null saat HPP
        // kosong, dan aturan itu tidak bisa diterjemahkan ke SUM() begitu
        // saja tanpa mengubah artinya.
        // ============================================================
        $jumlahLapor = (clone $baseQuery)->count();
        $totalOmzet = (int) (clone $baseQuery)->sum('omzet');

        $summary = [
            'total_omzet' => $totalOmzet,
            'jumlah_lapor' => $jumlahLapor,
            // Rata-rata dari NOL laporan bukan nol — ia tidak ada.
            //
            // "Rp 0" terbaca sebagai fakta: peserta ini rata-rata tidak
            // berpenghasilan. Padahal yang benar cuma "belum ada laporan pada
            // filter ini" — dan keadaan itu gampang sekali dicapai, cukup
            // menyaring bulan yang belum terisi.
            //
            // Mengikuti pola yang sudah dipakai KpiService::ringkasan():
            // null, lalu ditampilkan sebagai "—".
            'rata_rata' => $jumlahLapor > 0 ? intdiv($totalOmzet, $jumlahLapor) : null,
            'tertinggi' => (clone $baseQuery)->orderByDesc('omzet')->first(),

            // ============================================================
            // AGREGAT LABA — HANYA dari laporan yang HPP-nya dilaporkan.
            //
            // Menjumlahkan seluruh laporan lalu menganggap yang HPP-nya
            // kosong bernilai 0 akan melahirkan "laba" sebesar omzet penuh
            // untuk 271 laporan lama — angka yang salah dan langsung tampil
            // di layar pimpinan. Jadi yang dijumlahkan hanya laporan
            // berkomponen biaya, dan jumlahnya ikut ditampilkan supaya
            // pembacanya tahu ini sebagian, bukan keseluruhan.
            // ============================================================
        ] + $this->ringkasanLaba(
            // Hanya baris berdata laba yang dimuat — sisanya tidak pernah
            // ikut dijumlahkan, jadi memuatnya cuma memakan memori.
            (clone $baseQuery)->whereNotNull('hpp')->get()
        );

        $reports = $baseQuery
            ->orderByDesc('periode_tahun')
            ->orderByDesc('periode_bulan')
            // periode_urut ikut diurutkan supaya 30 baris harian dalam satu
            // bulan tampil berurutan, bukan acak menurut waktu penyimpanan.
            ->orderByDesc('periode_urut')
            ->paginate(10)
            ->withQueryString();

        return view('omzet.index', [
            'reports' => $reports,
            'summary' => $summary,
            'pendampings' => Pendamping::orderBy('nama')->get(),
            'bulanOptions' => OmzetReport::BULAN,
            'filters' => $request->only(['bulan', 'tahun', 'pendamping_id', 'q']),
        ]);
    }

    public function show(OmzetReport $omzetReport)
    {
        $omzetReport->loadMissing('participant.pendamping');

        // Admin: semua. Pendamping: binaan sendiri. Pimpinan: semua (read-only).
        Gate::authorize('view', $omzetReport);

        return view('omzet.show', ['report' => $omzetReport]);
    }

    public function edit(OmzetReport $omzetReport)
    {
        $omzetReport->loadMissing('participant');

        // Admin + Pendamping (binaan sendiri) SAJA — Pimpinan otomatis tertolak
        // karena canAccessParticipant() memang tidak mengizinkan Pimpinan.
        Gate::authorize('update', $omzetReport);

        return view('omzet.edit', ['report' => $omzetReport]);
    }

    public function update(Request $request, OmzetReport $omzetReport)
    {
        $omzetReport->loadMissing('participant');

        Gate::authorize('update', $omzetReport);

        $validated = $request->validate([
            'omzet' => ['required', 'integer', 'min:0'],
            // HPP & biaya operasional OPSIONAL. Banyak peserta belum
            // menghitung harga pokoknya; memaksa isi hanya akan
            // menghasilkan angka asal supaya form bisa tersimpan.
            'hpp' => ['nullable', 'integer', 'min:0', 'lte:omzet'],
            'biaya_operasional' => ['nullable', 'integer', 'min:0'],
            'catatan' => ['nullable', 'string'],
        ] + $this->aturanBukti(), $this->pesanValidasi() + $this->pesanBukti());

        // PERIODE TIDAK IKUT DIUBAH di sini, dan itu disengaja: memindahkan
        // sebuah laporan ke periode lain bisa menabrak unique index atau
        // mencampur mode pencatatan dalam satu bulan. Memperbaiki periode
        // dilakukan dengan menghapus lalu mencatat ulang, sehingga kedua
        // pemeriksaan itu tetap dijalankan.
        unset($validated['bukti']);
        $omzetReport->update($validated);

        $this->simpanBukti($request, $omzetReport);

        return redirect()
            ->route('omzet.index')
            ->with('success', 'Laporan omzet berhasil diperbarui.');
    }

    // =====================================================================
    // INPUT INTERNAL — perkembangan usaha yang dicatat petugas
    //
    // Selama ini laporan omzet HANYA bisa masuk lewat form publik bertoken
    // yang diisi peserta sendiri. Di lapangan itu sering tidak terjadi:
    // pesertanya menyebutkan angkanya saat kunjungan, dan Pendamping yang
    // mencatat. Jalur ini melengkapi, BUKAN menggantikan — form publik
    // tetap berjalan apa adanya.
    // =====================================================================

    public function create(Participant $participant)
    {
        Gate::authorize('createFor', [OmzetReport::class, $participant]);

        return view('omzet.create', [
            'participant' => $participant,
            'bulanOptions' => OmzetReport::BULAN,
            'jenisOptions' => OmzetReport::JENIS_OPTIONS,
        ]);
    }

    public function store(Request $request, Participant $participant)
    {
        Gate::authorize('createFor', [OmzetReport::class, $participant]);

        $validated = $request->validate(
            $this->aturanPeriode($request) + [
                'omzet' => ['required', 'integer', 'min:0'],
                // HPP & biaya operasional OPSIONAL. Banyak peserta belum
                // menghitung harga pokoknya; memaksa isi hanya akan
                // menghasilkan angka asal supaya form bisa tersimpan.
                'hpp' => ['nullable', 'integer', 'min:0', 'lte:omzet'],
                'biaya_operasional' => ['nullable', 'integer', 'min:0'],
                'catatan' => ['nullable', 'string'],
            ] + $this->aturanBukti(),
            $this->pesanValidasi() + $this->pesanPeriode() + $this->pesanBukti(),
        );

        $periode = $this->periodeDari($validated);

        // PENJAGA ANTI DOUBLE COUNTING — satu bulan, satu mode pencatatan.
        // Tanpa ini, peserta bisa punya laporan harian DAN bulanan untuk
        // September, lalu setiap penjumlahan omzet September menghitungnya
        // dua kali — dengan hasil yang tetap terlihat masuk akal.
        $modeTerpakai = OmzetReport::modeBulan($participant->id, $periode['periode_bulan'], $periode['periode_tahun']);

        if ($modeTerpakai !== null && $modeTerpakai !== $periode['jenis_periode']) {
            return back()->withInput()->withErrors([
                'jenis_periode' => OmzetReport::pesanModeBentrok($modeTerpakai, $periode['periode_bulan']),
            ]);
        }

        // Satu peserta + satu periode = satu laporan. Aturan itu dijaga unique
        // index di database, jadi di sini kita PERIKSA lebih dulu — termasuk
        // baris yang sudah di-soft-delete, yang tetap memblokir index tapi
        // tidak terlihat query biasa. Kalau ada, baris lamanya dipulihkan &
        // diperbarui; peserta tidak pernah kehilangan periode yang pernah
        // dihapus, dan tidak ada baris kembar yang lahir.
        $existing = OmzetReport::untukPeriode(
            $participant->id,
            $periode['periode_bulan'],
            $periode['periode_tahun'],
            $periode['jenis_periode'],
            $periode['periode_urut'],
        );

        if ($existing && ! $existing->trashed()) {
            return back()
                ->withInput()
                ->withErrors(['periode_bulan' => 'Laporan omzet periode ini sudah ada. Gunakan Edit untuk memperbaikinya.']);
        }

        $isi = [
            'omzet' => $validated['omzet'],
            'hpp' => $validated['hpp'] ?? null,
            'biaya_operasional' => $validated['biaya_operasional'] ?? null,
            'catatan' => $validated['catatan'] ?? null,
            'submitted_at' => now(),
        ];

        if ($existing) {
            $existing->restore();
            $existing->update($isi);
            $laporan = $existing;
        } else {
            $laporan = OmzetReport::create($isi + $periode + [
                'participant_id' => $participant->id,
            ]);
        }

        $this->simpanBukti($request, $laporan);

        return redirect()
            ->route('participants.show', $participant)
            ->with('success', 'Laporan omzet berhasil dicatat.');
    }

    /**
     * Unduh bukti laporan — SATU-SATUNYA jalan ke berkasnya.
     *
     * Berkas bukti ada di disk privat (storage/app/private), tidak punya URL
     * publik, dan hanya bisa keluar lewat method ini. Izinnya memakai Gate
     * 'view' yang sama dengan halaman detail laporan: Admin, Pendamping
     * pemilik binaan, dan Pimpinan (read-only). Kalau otorisasinya dibuat
     * sendiri di sini, cepat atau lambat ia akan berbeda dari izin halaman
     * yang menampilkan tombolnya.
     */
    public function bukti(OmzetReport $omzetReport)
    {
        $omzetReport->loadMissing('participant.pendamping');

        Gate::authorize('view', $omzetReport);

        abort_unless($omzetReport->punyaBukti(), 404);

        // Baris bisa saja menunjuk berkas yang sudah tidak ada di disk (mis.
        // dipindahkan manual saat migrasi server). 404 lebih jujur daripada
        // kegagalan penyimpanan yang muncul sebagai error 500.
        abort_unless(Storage::exists($omzetReport->bukti_path), 404);

        return Storage::download($omzetReport->bukti_path, $omzetReport->namaBukti());
    }

    /** SOFT DELETE — barisnya tetap ada, periodenya bisa diisi ulang. */
    public function destroy(OmzetReport $omzetReport)
    {
        $omzetReport->loadMissing('participant');

        Gate::authorize('delete', $omzetReport);

        $participantId = $omzetReport->participant_id;
        $omzetReport->delete();

        return redirect()
            ->route('participants.show', $participantId)
            ->with('success', 'Laporan omzet dihapus. Periode ini bisa diisi ulang kalau perlu.');
    }

    /**
     * Memulihkan laporan omzet dari Arsip. Route-nya memakai withTrashed().
     *
     * PERHATIAN unique index (participant_id, periode_bulan, periode_tahun):
     * index itu tidak mempedulikan `deleted_at`, jadi selama baris ini masih
     * diarsipkan TIDAK MUNGKIN ada baris lain untuk periode yang sama —
     * pemulihannya karena itu selalu aman. Alur store() memang sudah
     * memulihkan baris lama alih-alih menyisipkan yang baru.
     */
    public function restore(OmzetReport $omzetReport)
    {
        Gate::authorize('restore', $omzetReport);

        $omzetReport->restore();

        return back()->with('success', 'Laporan omzet berhasil dipulihkan.');
    }

    /**
     * Bagian laba dari ringkasan — dipisah supaya alasannya punya tempat.
     *
     * Hanya laporan yang HPP-nya benar-benar dilaporkan yang ikut dijumlahkan
     * (scope denganLaba()). `jumlah_berlaba` dan `jumlah_lapor` sengaja
     * ditampilkan berdampingan di layar: selama keduanya belum sama,
     * angka profit di kartu ini memang baru mewakili sebagian peserta, dan
     * pembacanya berhak tahu itu tanpa harus menebak.
     *
     * @param  \Illuminate\Support\Collection<int, OmzetReport>  $laporan
     */
    private function ringkasanLaba($laporan): array
    {
        $berlaba = $laporan->filter(fn (OmzetReport $r) => $r->punyaDataLaba());

        return [
            'jumlah_berlaba' => $berlaba->count(),
            'omzet_berlaba' => (int) $berlaba->sum('omzet'),
            'total_hpp' => (int) $berlaba->sum('hpp'),
            'total_biaya_operasional' => (int) $berlaba->sum('biaya_operasional'),
            'total_laba_kotor' => (int) $berlaba->sum(fn (OmzetReport $r) => $r->labaKotor()),
            'total_profit' => (int) $berlaba->sum(fn (OmzetReport $r) => $r->profitBersih()),
        ];
    }

    private function pesanValidasi(): array
    {
        return [
            'omzet.required' => 'Omzet wajib diisi.',
            'omzet.integer' => 'Omzet harus berupa angka.',
            'omzet.min' => 'Omzet tidak boleh negatif.',
            'hpp.integer' => 'HPP harus berupa angka.',
            'hpp.min' => 'HPP tidak boleh negatif.',
            'hpp.lte' => 'HPP tidak boleh lebih besar dari omzet.',
            'biaya_operasional.integer' => 'Biaya operasional harus berupa angka.',
            'biaya_operasional.min' => 'Biaya operasional tidak boleh negatif.',
        ];
    }
}
