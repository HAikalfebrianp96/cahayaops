<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\HppCalculation;
use App\Models\HppComponent;
use App\Models\Participant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class HppCalculationController extends Controller
{
    public function index(Participant $participant)
    {
        abort_unless(Auth::user()->canViewParticipant($participant), 403);

        return view('hpp.index', [
            'participant' => $participant,
            'calculations' => $participant->hppCalculations,
        ]);
    }

    public function create(Participant $participant)
    {
        abort_unless(Auth::user()->canAccessParticipant($participant), 403);

        return view('hpp.create', [
            'participant' => $participant,
            'kategoriOptions' => HppComponent::KATEGORI_OPTIONS,
        ]);
    }

    public function store(Request $request, Participant $participant)
    {
        abort_unless(Auth::user()->canAccessParticipant($participant), 403);

        $validated = $this->validasiPerhitungan($request);
        [$totalBiaya, $komponenToSave] = $this->rangkumKomponen($request);
        $angka = $this->hitungAngka($validated, $totalBiaya);

        $calculation = DB::transaction(function () use ($participant, $validated, $angka, $komponenToSave) {
            $calc = $participant->hppCalculations()->create([
                'nama_produk' => $validated['nama_produk'],
                'satuan_produksi' => $validated['satuan_produksi'],
                'jumlah_produksi' => $validated['jumlah_produksi'],
                'harga_jual' => (float) $validated['harga_jual'],
                'created_by' => Auth::id(),
                ...$angka,
            ]);

            foreach ($komponenToSave as $k) {
                $calc->components()->create($k);
            }

            return $calc;
        });

        return redirect()
            ->route('hpp.show', $calculation)
            ->with('success', 'Perhitungan HPP berhasil disimpan.');
    }

    public function edit(HppCalculation $hppCalculation)
    {
        $hppCalculation->loadMissing('participantTermasukArsip', 'components');

        abort_unless(
            Auth::user()->canManageParticipantRecord($hppCalculation->pesertaTermasukArsip()),
            403
        );

        return view('hpp.create', [
            'participant' => $hppCalculation->pesertaTermasukArsip(),
            'kategoriOptions' => HppComponent::KATEGORI_OPTIONS,
            'calculation' => $hppCalculation,
        ]);
    }

    /**
     * KOREKSI perhitungan HPP.
     *
     * Komponen lama DIGANTI seluruhnya, bukan ditambah — kalau tidak, baris
     * yang dihapus operator di form akan tetap tinggal di database dan
     * totalnya jadi lebih besar daripada yang terlihat di layar.
     *
     * Nilai SEBELUM dan SESUDAH ikut dicatat ke Activity Log. Tanpa itu,
     * koreksi diam-diam terhadap angka HPP tidak akan pernah bisa dijelaskan
     * saat harga jual produk dipertanyakan belakangan.
     */
    public function update(Request $request, HppCalculation $hppCalculation)
    {
        $hppCalculation->loadMissing('participantTermasukArsip');

        abort_unless(
            Auth::user()->canManageParticipantRecord($hppCalculation->pesertaTermasukArsip()),
            403
        );

        $validated = $this->validasiPerhitungan($request);
        [$totalBiaya, $komponenToSave] = $this->rangkumKomponen($request);
        $angka = $this->hitungAngka($validated, $totalBiaya);

        $sebelum = 'HPP/unit ' . rupiah($hppCalculation->hpp_per_unit)
            . ', harga jual ' . rupiah($hppCalculation->harga_jual);

        DB::transaction(function () use ($hppCalculation, $validated, $angka, $komponenToSave) {
            $hppCalculation->update([
                'nama_produk' => $validated['nama_produk'],
                'satuan_produksi' => $validated['satuan_produksi'],
                'jumlah_produksi' => $validated['jumlah_produksi'],
                'harga_jual' => (float) $validated['harga_jual'],
                ...$angka,
            ]);

            $hppCalculation->components()->delete();

            foreach ($komponenToSave as $k) {
                $hppCalculation->components()->create($k);
            }
        });

        $hppCalculation->refresh();

        ActivityLog::record(
            action: 'hpp_calculation_updated',
            modelType: 'HppCalculation',
            modelId: $hppCalculation->id,
            description: 'Perhitungan HPP "' . $hppCalculation->nama_produk . '" dikoreksi. '
                . 'Sebelum: ' . $sebelum . '. '
                . 'Sesudah: HPP/unit ' . rupiah($hppCalculation->hpp_per_unit)
                . ', harga jual ' . rupiah($hppCalculation->harga_jual) . '.',
        );

        return redirect()
            ->route('hpp.show', $hppCalculation)
            ->with('success', 'Perhitungan HPP diperbarui. Perubahannya tercatat di Activity Log.');
    }

    // ==========================================================
    // HITUNGAN BERSAMA — dipakai store() DAN update().
    //
    // Sengaja satu jalur: kalau keduanya menghitung sendiri-sendiri, cepat
    // atau lambat rumus HPP di jalur Simpan dan jalur Koreksi berbeda, dan
    // selisihnya hanya muncul pada data yang pernah dikoreksi.
    // ==========================================================

    /** @return array<string, mixed> */
    private function validasiPerhitungan(Request $request): array
    {
        return $request->validate([
            'nama_produk' => ['required', 'string', 'max:255'],
            'satuan_produksi' => ['required', 'string', 'max:100'],
            'jumlah_produksi' => ['required', 'integer', 'min:1'],
            'harga_jual' => ['required', 'numeric', 'min:0'],
            'komponen' => ['required', 'array', 'min:1'],
            'komponen.*.kategori' => ['nullable', Rule::in(HppComponent::KATEGORI_OPTIONS)],
            'komponen.*.nama_biaya' => ['nullable', 'string', 'max:255'],
            'komponen.*.qty' => ['nullable', 'numeric', 'min:0'],
            'komponen.*.satuan' => ['nullable', 'string', 'max:100'],
            'komponen.*.harga_satuan' => ['nullable', 'numeric', 'min:0'],
        ]);
    }

    /**
     * Baris kosong/belum lengkap DIABAIKAN diam-diam, sama seperti form
     * Penjualan — sisa baris yang belum sempat dihapus tidak boleh
     * menggagalkan seluruh form.
     *
     * @return array{0: float, 1: array<int, array<string, mixed>>}
     */
    private function rangkumKomponen(Request $request): array
    {
        $totalBiaya = 0;
        $komponenToSave = [];

        foreach ($request->input('komponen', []) as $row) {
            $nama = trim($row['nama_biaya'] ?? '');
            $qty = (float) ($row['qty'] ?? 0);

            if ($nama === '' || $qty <= 0) {
                continue;
            }

            $kategori = in_array($row['kategori'] ?? '', HppComponent::KATEGORI_OPTIONS, true)
                ? $row['kategori']
                : HppComponent::KATEGORI_OPTIONS[0];
            $hargaSatuan = (float) ($row['harga_satuan'] ?? 0);
            $total = round($qty * $hargaSatuan, 2);
            $totalBiaya += $total;

            $komponenToSave[] = [
                'kategori' => $kategori,
                'nama_biaya' => $nama,
                'qty' => $qty,
                'satuan' => $row['satuan'] ?? null,
                'harga_satuan' => $hargaSatuan,
                'total' => $total,
            ];
        }

        if (empty($komponenToSave)) {
            throw ValidationException::withMessages([
                'komponen' => 'Tambahkan minimal satu komponen biaya yang lengkap (nama biaya, qty, dan harga satuan wajib diisi).',
            ]);
        }

        return [$totalBiaya, $komponenToSave];
    }

    /**
     * Angka turunan — dihitung SERVER-SIDE. Angka dari JavaScript di form
     * hanya untuk pratinjau dan tidak pernah dipercaya.
     *
     * @return array<string, mixed>
     */
    private function hitungAngka(array $validated, float $totalBiaya): array
    {
        $jumlahProduksi = (int) $validated['jumlah_produksi'];
        $hargaJual = (float) $validated['harga_jual'];

        $hppPerUnit = $jumlahProduksi > 0 ? round($totalBiaya / $jumlahProduksi, 2) : 0;
        $labaPerUnit = round($hargaJual - $hppPerUnit, 2);

        return [
            'total_biaya_produksi' => $totalBiaya,
            'hpp_per_unit' => $hppPerUnit,
            'laba_per_unit' => $labaPerUnit,
            // Hindari division by zero — margin jadi null (tampil "-"), bukan Infinity.
            'margin_persen' => $hargaJual > 0 ? round(($labaPerUnit / $hargaJual) * 100, 2) : null,
            'estimasi_laba_batch' => round($labaPerUnit * $jumlahProduksi, 2),
        ];
    }

    public function destroy(HppCalculation $hppCalculation)
    {
        $hppCalculation->loadMissing('participantTermasukArsip');

        abort_unless(
            Auth::user()->canManageParticipantRecord($hppCalculation->pesertaTermasukArsip()),
            403
        );

        $participantId = $hppCalculation->participant_id;
        $hppCalculation->delete();

        return redirect()
            ->route('hpp.index', $participantId)
            ->with('success', 'Perhitungan HPP dihapus. Datanya tetap tersimpan dan bisa dipulihkan Admin.');
    }

    /**
     * Memulihkan perhitungan HPP dari Arsip. Route-nya memakai withTrashed().
     *
     * Otorisasinya memakai pesertaTermasukArsip() supaya Pendamping pemilik
     * binaan tetap dikenali walau pesertanya ikut diarsipkan.
     */
    public function restore(HppCalculation $hppCalculation)
    {
        abort_unless(
            Auth::user()->canManageParticipantRecord($hppCalculation->pesertaTermasukArsip()),
            403
        );

        $hppCalculation->restore();

        return back()->with('success', 'Perhitungan HPP berhasil dipulihkan.');
    }

    public function show(HppCalculation $hppCalculation)
    {
        // Perhitungan HPP adalah riwayat milik peserta. Saat pesertanya
        // diarsipkan, justru halaman inilah yang masih perlu dibaca.
        $participant = $hppCalculation->pesertaTermasukArsip();
        abort_unless(Auth::user()->canViewParticipant($participant), 403);

        $hppCalculation->load('components');

        return view('hpp.show', [
            'calculation' => $hppCalculation,
            'participant' => $participant,
            'komponenPerKategori' => $hppCalculation->components->groupBy('kategori'),
        ]);
    }
}
