<?php

namespace App\Http\Controllers;

use App\Services\PenghapusanPermanenService;
use App\Models\FinancialProxy;
use App\Models\ImpactOutcome;
use App\Models\ImpactProgram;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * OUTCOME PROGRAM — perubahan yang diklaim, beserta faktor pengurangnya.
 *
 * Di sinilah kejujuran laporan dampak ditentukan, jadi validasinya lebih
 * ketat daripada modul lain: keempat faktor penyesuai wajib berada di 0-100,
 * dan outcome otomatis wajib menunjuk indikator yang benar-benar dikenal
 * sistem pengukuran. Indikator karangan akan menghasilkan nilai perubahan
 * NULL yang diam-diam dibaca sebagai nol.
 */
class ImpactOutcomeController extends Controller
{
    public function create(ImpactProgram $impact)
    {
        Gate::authorize('update', $impact);

        return view('impact.outcome-form', [
            'program' => $impact,
            'outcome' => null,
            'proxies' => FinancialProxy::aktif()->orderBy('nama')->get(),
            'indikatorPilihan' => ImpactOutcome::indikatorPilihan(),
        ]);
    }

    public function store(Request $request, ImpactProgram $impact)
    {
        Gate::authorize('update', $impact);

        $impact->outcomes()->create($this->validasi($request) + [
            'dicatat_oleh' => Auth::id(),
        ]);

        return redirect()
            ->route('impact.show', $impact)
            ->with('success', 'Outcome berhasil ditambahkan.');
    }

    public function edit(ImpactOutcome $outcome)
    {
        Gate::authorize('update', $outcome->program);

        return view('impact.outcome-form', [
            'program' => $outcome->program,
            'outcome' => $outcome,
            'proxies' => FinancialProxy::aktif()->orderBy('nama')->get(),
            'indikatorPilihan' => ImpactOutcome::indikatorPilihan(),
        ]);
    }

    public function update(Request $request, ImpactOutcome $outcome)
    {
        Gate::authorize('update', $outcome->program);

        $outcome->update($this->validasi($request));

        return redirect()
            ->route('impact.show', $outcome->program)
            ->with('success', 'Outcome berhasil diperbarui.');
    }

    public function destroy(ImpactOutcome $outcome)
    {
        Gate::authorize('update', $outcome->program);

        $program = $outcome->program;
        $penghapusan = app(PenghapusanPermanenService::class);
        $alasan = $penghapusan->alasanTertahan($outcome);

        if ($alasan !== null) {
            // Bukan kegagalan sistem: ini keputusan yang disengaja, jadi
            // disampaikan sebagai penjelasan beserta apa yang menahannya.
            return back()->with('error', $alasan);
        }

        $ringkasan = $penghapusan->hapusDenganCatatan($outcome);

        return redirect()
            ->route('impact.show', $program)
            ->with('success', 'Dihapus permanen: ' . $ringkasan . '. Data ini tidak dapat dipulihkan.');
    }

    private function validasi(Request $request): array
    {
        $persen = ['required', 'numeric', 'min:0', 'max:100'];

        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'deskripsi' => ['nullable', 'string'],
            'sumber_nilai' => ['required', Rule::in(ImpactOutcome::sumberYangSah())],

            // Indikator wajib HANYA untuk outcome otomatis, dan harus salah
            // satu yang benar-benar dikenal sistem pengukuran — indikator
            // karangan menghasilkan perubahan NULL yang diam-diam terbaca nol.
            'indikator' => [
                Rule::requiredIf(fn () => $request->input('sumber_nilai') === ImpactOutcome::SUMBER_OTOMATIS),
                'nullable',
                Rule::in(array_keys(ImpactOutcome::indikatorPilihan())),
            ],

            // Sebaliknya untuk outcome manual: angkanya wajib, karena tidak
            // ada sumber lain yang bisa mengisinya.
            'nilai_perubahan_manual' => [
                Rule::requiredIf(fn () => $request->input('sumber_nilai') === ImpactOutcome::SUMBER_MANUAL),
                'nullable', 'integer',
            ],
            'jumlah_penerima_manual' => ['nullable', 'integer', 'min:0'],

            'satuan' => ['nullable', 'string', 'max:255'],
            'financial_proxy_id' => ['nullable', 'exists:financial_proxies,id'],

            'deadweight_persen' => $persen,
            'deadweight_catatan' => ['nullable', 'string'],

            'attribution_persen' => $persen,
            // WAJIB begitu attribution di bawah 100%. Mengakui sebagian
            // perubahan milik pihak lain tanpa menyebut pihak lain itu siapa
            // membuat angkanya tidak bisa diperiksa siapa pun — dan angka
            // yang tidak bisa diperiksa adalah angka yang dipilih karena
            // terasa pantas.
            'attribution_catatan' => [
                Rule::requiredIf(fn () => (float) $request->input('attribution_persen', 100) < 100),
                'nullable', 'string',
            ],

            'displacement_persen' => $persen,
            // Tiga keadaan, bukan dua: '1' relevan, '0' tidak relevan,
            // kosong berarti belum ditentukan. Yang terakhir sengaja
            // dibiarkan mungkin — memaksa memilih hanya akan menghasilkan
            // pilihan asal, dan SroiService memperingatkannya.
            'displacement_relevan' => ['nullable', 'boolean'],
            'displacement_catatan' => [
                // Menyatakan displacement TIDAK relevan adalah keputusan,
                // dan keputusan tanpa alasan tidak bisa ditinjau ulang.
                Rule::requiredIf(fn () => $request->input('displacement_relevan') === '0'),
                'nullable', 'string',
            ],

            'drop_off_persen' => $persen,
            'durasi_tahun' => ['required', 'integer', 'min:1', 'max:10'],

            // Bukti WAJIB untuk outcome manual: angka yang tidak berasal dari
            // data operasional sistem harus bisa ditelusuri ke suatu tempat,
            // atau ia tidak layak masuk laporan dampak sama sekali.
            'bukti' => [
                Rule::requiredIf(fn () => $request->input('sumber_nilai') === ImpactOutcome::SUMBER_MANUAL),
                'nullable', 'string',
            ],
            'catatan' => ['nullable', 'string'],
        ], [
            'nama.required' => 'Nama outcome wajib diisi.',
            'indikator.required' => 'Outcome otomatis harus menunjuk satu indikator pengukuran.',
            'indikator.in' => 'Indikator tidak dikenali sistem pengukuran.',
            'nilai_perubahan_manual.required' => 'Outcome manual harus mencantumkan nilai perubahannya.',
            'bukti.required' => 'Outcome manual wajib menyertakan bukti/sumber angkanya.',
            'attribution_catatan.required' => 'Sebutkan pihak lain yang ikut berkontribusi — '
                . 'mis. usaha peserta sendiri, keluarga, program lain, atau kondisi pasar.',
            'displacement_catatan.required' => 'Jelaskan kenapa displacement tidak relevan untuk outcome ini.',
            'deadweight_persen.max' => 'Deadweight harus antara 0 dan 100 persen.',
            'attribution_persen.max' => 'Attribution harus antara 0 dan 100 persen.',
            'displacement_persen.max' => 'Displacement harus antara 0 dan 100 persen.',
            'drop_off_persen.max' => 'Drop-off harus antara 0 dan 100 persen.',
        ]);

        // Kolom yang tidak berlaku untuk mode yang dipilih DIKOSONGKAN, bukan
        // dibiarkan menyimpan sisa isian mode sebelumnya. Outcome yang diubah
        // dari manual ke otomatis tapi masih menyimpan nilai_perubahan_manual
        // akan terlihat punya dua sumber angka yang saling bertentangan.
        if ($data['sumber_nilai'] === ImpactOutcome::SUMBER_OTOMATIS) {
            $data['nilai_perubahan_manual'] = null;
            $data['jumlah_penerima_manual'] = null;
        } else {
            $data['indikator'] = null;
        }

        // Drop-off hanya berarti untuk manfaat lintas tahun. Menyimpannya
        // untuk durasi satu tahun akan menampilkan angka yang terlihat
        // berpengaruh padahal tidak pernah terpakai sama sekali.
        if ((int) $data['durasi_tahun'] <= 1) {
            $data['drop_off_persen'] = 0;
        }

        // Displacement dinormalkan ke boolean sungguhan (atau null) LEBIH
        // DULU. Aturan validasi 'boolean' hanya MEMERIKSA nilainya, ia tidak
        // meng-cast: yang sampai ke sini masih string '0' atau '1' dari
        // peramban. Membandingkannya dengan === false akan selalu meleset,
        // dan persentase displacement yang seharusnya dinolkan tetap
        // tersimpan — muncul kembali begitu outcome-nya kelak ditandai
        // relevan, tanpa ada yang pernah memutuskannya.
        $data['displacement_relevan'] = match (true) {
            ! array_key_exists('displacement_relevan', $data) => null,
            $data['displacement_relevan'] === null => null,
            $data['displacement_relevan'] === '' => null,
            default => filter_var($data['displacement_relevan'], FILTER_VALIDATE_BOOLEAN),
        };

        if ($data['displacement_relevan'] === false) {
            $data['displacement_persen'] = 0;
        }

        return $data;
    }
}
