<?php

namespace App\Http\Controllers;

use App\Services\PenghapusanPermanenService;
use App\Models\Pendamping;
use App\Models\PendampingTarget;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * TARGET KINERJA PENDAMPING — CRUD penuh, Admin saja.
 *
 * Pendamping SENGAJA tidak boleh membuat atau mengubah targetnya sendiri.
 * Kalau boleh, target berhenti menjadi alat ukur dan berubah jadi sesuatu
 * yang bisa disesuaikan agar terlihat tercapai. Ia hanya melihat targetnya
 * lewat halaman KPI.
 *
 * Penghapusan memakai Soft Delete: target adalah bagian dari riwayat
 * kebijakan program, dan menghapusnya permanen membuat KPI periode lalu
 * tidak bisa dijelaskan lagi. Target yang diarsipkan otomatis keluar dari
 * perhitungan KPI (global scope), jadi mengarsipkan = kembali ke target
 * default sistem.
 */
class PendampingTargetController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('viewAny', PendampingTarget::class);

        $query = PendampingTarget::with(['pendamping', 'pembuat', 'penghapus']);

        if ($request->filled('pendamping_id')) {
            $query->where('pendamping_id', $request->integer('pendamping_id'));
        }

        if ($request->filled('tahun')) {
            $query->where('tahun', $request->integer('tahun'));
        }

        if ($request->filled('periode')) {
            $query->where('periode', $request->string('periode'));
        }

        $targets = $query->orderByDesc('tahun')->orderByDesc('bulan')->paginate(15)->withQueryString();

        return view('target-pendamping.index', [
            'targets' => $targets,
            'pendampings' => Pendamping::orderBy('nama')->get(),
            'periodeOptions' => PendampingTarget::PERIODE_OPTIONS,
            'tahunOptions' => $this->tahunOptions(),
            'filters' => $request->only(['pendamping_id', 'tahun', 'periode']),
        ]);
    }

    public function create()
    {
        Gate::authorize('create', PendampingTarget::class);

        return view('target-pendamping.create', $this->dataForm());
    }

    public function store(Request $request)
    {
        Gate::authorize('create', PendampingTarget::class);

        $target = PendampingTarget::create(array_merge(
            $this->validated($request),
            ['created_by' => Auth::id()],
        ));

        return redirect()
            ->route('target-pendamping.index')
            ->with('success', 'Target ' . $target->pendamping->nama . ' untuk ' . $target->periodeLabel() . ' berhasil ditetapkan.');
    }

    public function edit(PendampingTarget $target)
    {
        Gate::authorize('update', $target);

        return view('target-pendamping.edit', $this->dataForm($target));
    }

    public function update(Request $request, PendampingTarget $target)
    {
        Gate::authorize('update', $target);

        $target->update($this->validated($request, $target));

        return redirect()
            ->route('target-pendamping.index')
            ->with('success', 'Target berhasil diperbarui.');
    }

    /** SOFT DELETE — target keluar dari perhitungan KPI, tapi barisnya tetap ada. */
    public function destroy(PendampingTarget $target)
    {
        Gate::authorize('delete', $target);

        $label = $target->pendamping->nama . ' — ' . $target->periodeLabel();
        $penghapusan = app(PenghapusanPermanenService::class);
        $alasan = $penghapusan->alasanTertahan($target);

        if ($alasan !== null) {
            // Bukan kegagalan sistem: ini keputusan yang disengaja, jadi
            // disampaikan sebagai penjelasan beserta apa yang menahannya.
            return back()->with('error', $alasan);
        }

        $ringkasan = $penghapusan->hapusDenganCatatan($target);

        return redirect()
            ->route('target-pendamping.index')
            ->with('success', 'Dihapus permanen: ' . $ringkasan . '. Data ini tidak dapat dipulihkan.');
    }

    private function dataForm(?PendampingTarget $target = null): array
    {
        $data = [
            'pendampings' => Pendamping::orderBy('nama')->get(),
            'periodeOptions' => PendampingTarget::PERIODE_OPTIONS,
            'indikator' => PendampingTarget::INDIKATOR,
            'tahunOptions' => $this->tahunOptions(),
        ];

        if ($target !== null) {
            $data['target'] = $target;
        }

        return $data;
    }

    private function validated(Request $request, ?PendampingTarget $target = null): array
    {
        $periode = $request->string('periode')->toString();

        $aturan = [
            'pendamping_id' => ['required', 'exists:pendampings,id'],
            'periode' => ['required', Rule::in(PendampingTarget::PERIODE_OPTIONS)],
            'tahun' => ['required', 'integer', 'min:2020', 'max:' . (now()->year + 5)],
            // Bulan WAJIB untuk Bulanan & Triwulan, dan HARUS kosong untuk
            // Tahunan — kalau tidak, satu target tahunan dengan bulan terisi
            // akan dianggap mencakup rentang yang salah oleh rentang().
            'bulan' => [
                Rule::requiredIf(fn () => in_array($periode, [
                    PendampingTarget::PERIODE_BULANAN,
                    PendampingTarget::PERIODE_TRIWULAN,
                ], true)),
                'nullable', 'integer', 'min:1', 'max:12',
            ],
            'catatan' => ['nullable', 'string'],
        ];

        // Seluruh indikator opsional. Target harus fleksibel: program boleh
        // menetapkan hanya sebagian, dan yang kosong tidak dihitung ke KPI.
        foreach (array_keys(PendampingTarget::INDIKATOR) as $kolom) {
            $aturan[$kolom] = ['nullable', 'integer', 'min:0', 'max:9999'];
        }

        $data = $request->validate($aturan, [
            'bulan.required' => 'Bulan wajib diisi untuk periode Bulanan dan Triwulan.',
        ]);

        // Tahunan tidak punya bulan. Dinormalkan di sini supaya nilai sisa
        // dari form tidak ikut tersimpan dan merusak rentang periodenya.
        if ($data['periode'] === PendampingTarget::PERIODE_TAHUNAN) {
            $data['bulan'] = null;
        }

        // Triwulan disimpan sebagai bulan AWAL triwulan (1/4/7/10), apa pun
        // bulan yang dipilih Admin — supaya rentang() selalu konsisten.
        if ($data['periode'] === PendampingTarget::PERIODE_TRIWULAN && $data['bulan']) {
            $data['bulan'] = ((int) ceil($data['bulan'] / 3) - 1) * 3 + 1;
        }

        $this->tolakDuplikat($data, $target);

        return $data;
    }

    /**
     * Satu pendamping hanya boleh punya SATU target per periode.
     *
     * Diperiksa di sini (bukan hanya mengandalkan unique index) supaya
     * pengguna mendapat pesan yang jelas, bukan error database mentah.
     * Target yang diarsipkan sengaja diabaikan — periodenya bebas ditetapkan
     * ulang.
     */
    private function tolakDuplikat(array $data, ?PendampingTarget $target): void
    {
        $bentrok = PendampingTarget::where('pendamping_id', $data['pendamping_id'])
            ->where('periode', $data['periode'])
            ->where('tahun', $data['tahun'])
            ->where('bulan', $data['bulan'])
            ->when($target, fn ($q) => $q->whereKeyNot($target->id))
            ->exists();

        if ($bentrok) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'periode' => 'Pendamping ini sudah punya target untuk periode tersebut. Perbaiki target yang ada, jangan membuat yang kedua.',
            ]);
        }
    }

    private function tahunOptions(): array
    {
        $sekarang = now()->year;

        return range($sekarang + 1, $sekarang - 3);
    }
}
