<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\ShuPeriod;
use App\Models\KoperasiSetting;
use App\Services\ShuService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use App\Services\GambarCetak;

/**
 * SHU — SISA HASIL USAHA KOPERASI.
 *
 * Otorisasinya memakai bolehKelolaKoperasi() yang SUDAH dipakai seluruh modul
 * Koperasi (Admin + Tim Koperasi), bukan aturan baru. Yang khas hanya
 * pengesahan: menandai penetapan Final mengunci angkanya, jadi tindakan itu
 * dibatasi ke Admin — sejalan dengan pemisahan yang sama di modul SROI,
 * di mana menyusun dan menerbitkan bukan hal yang sama.
 */
class ShuController extends Controller
{
    public function __construct(private ShuService $shu)
    {
    }

    public function index()
    {
        $this->pastikanBolehKelola();

        return view('koperasi.shu.index', [
            'periods' => ShuPeriod::withCount('allocations')->orderByDesc('tahun')->paginate(15),
            'bisaSahkan' => Auth::user()->isAdmin(),
        ]);
    }

    public function show(ShuPeriod $shu)
    {
        $this->pastikanBolehKelola();

        $shu->load(['allocations.member', 'penetap']);

        return view('koperasi.shu.show', [
            'periode' => $shu,
            'peringatan' => $this->shu->peringatan($shu),
            'bisaKelola' => ! $shu->terkunci() && Auth::user()->bolehKelolaKoperasi(),
            'bisaSahkan' => ! $shu->terkunci() && Auth::user()->isAdmin(),
        ]);
    }

    /**
     * Export PDF penetapan SHU.
     *
     * Memakai header & footer PDF Koperasi yang SUDAH ADA — termasuk
     * identitas resmi, tanda tangan, dan stempel dari Pengaturan Koperasi.
     * Dokumen ini dibawa ke Rapat Anggota, jadi ia harus terlihat sama dengan
     * laporan koperasi lainnya, bukan seperti keluaran modul yang berdiri
     * sendiri.
     *
     * Seluruh angkanya dibaca dari baris penetapan dan alokasinya apa adanya
     * — termasuk dasar simpanan & transaksi yang sudah dibekukan saat
     * penetapan. Tidak ada yang dihitung ulang di sini; kalau dihitung ulang,
     * dokumen yang dicetak hari ini bisa berbeda dari yang dicetak kemarin
     * untuk penetapan yang sama.
     */
    public function exportPdf(ShuPeriod $shu)
    {
        $this->pastikanBolehKelola();

        return Pdf::loadView('koperasi.shu.pdf.shu', $this->dataPdf($shu))
            ->setPaper('a4', 'portrait')
            ->download('laporan-shu-' . $shu->tahun . '.pdf');
    }

    /**
     * Isi laporan PDF — dipisah dari pembuatan berkasnya.
     *
     * Alasannya sama dengan ImpactProgramController::dataPdf(): keluaran
     * dompdf terkompresi, jadi isi dokumennya tidak bisa diperiksa dari byte
     * mentahnya. Dokumen ini ditandatangani di Rapat Anggota — nominal per
     * anggota di dalamnya harus bisa diuji otomatis, bukan sekadar dipercaya.
     *
     * Sengaja public: dipakai test untuk merender Blade-nya langsung.
     */
    public function dataPdf(ShuPeriod $shu): array
    {
        $shu->loadMissing(['allocations.member', 'penetap']);

        $setting = KoperasiSetting::current();

        return [
            'periode' => $shu,
            'peringatan' => $this->shu->peringatan($shu),
            'setting' => $setting,
            // Ketiganya disusutkan lebih dulu; berkas aslinya tidak disentuh.
            'logoPath' => app(GambarCetak::class)->untukCetak(
                $setting->logo_koperasi
                    ? public_path('images/koperasi/' . $setting->logo_koperasi)
                    : public_path('images/logo-laporan.png')
            ),
            'ttdPath' => app(GambarCetak::class)->untukCetak(
                $setting->ttd_koperasi ? public_path('images/koperasi/' . $setting->ttd_koperasi) : null
            ),
            'stempelPath' => app(GambarCetak::class)->untukCetak(
                $setting->stempel_koperasi ? public_path('images/koperasi/' . $setting->stempel_koperasi) : null
            ),
            'periodeLabel' => 'Tahun Buku ' . $shu->tahun,
        ];
    }

    public function create(Request $request)
    {
        $this->pastikanBolehKelola();

        $tahun = $request->integer('tahun') ?: now()->year;

        return view('koperasi.shu.form', [
            'periode' => null,
            'tahun' => $tahun,
            'usulanTotal' => $this->shu->usulanDariLabaRugi($tahun),
            'komponenAwal' => ShuPeriod::KOMPONEN_USULAN,
        ]);
    }

    public function store(Request $request)
    {
        $this->pastikanBolehKelola();

        $data = $this->validasi($request);

        // Unique index pada kolom `tahun` TIDAK mempedulikan deleted_at, jadi
        // penetapan yang pernah diarsipkan tetap memblokir tahunnya. Diperiksa
        // di sini — termasuk baris terarsip — supaya kegagalannya muncul
        // sebagai pesan yang bisa dibaca, bukan error database. Pola yang sama
        // dengan OmzetReport::untukPeriode.
        $lama = ShuPeriod::untukTahun($data['tahun']);

        if ($lama && ! $lama->trashed()) {
            return back()->withInput()->withErrors([
                'tahun' => 'Penetapan SHU tahun ini sudah ada. Gunakan Edit untuk memperbaikinya.',
            ]);
        }

        if ($lama) {
            $lama->restore();
            $lama->update($data + ['ditetapkan_oleh' => Auth::id()]);
            $periode = $lama;
        } else {
            $periode = ShuPeriod::create($data + ['ditetapkan_oleh' => Auth::id()]);
        }

        $this->shu->susunAlokasi($periode);

        return redirect()
            ->route('koperasi.shu.show', $periode)
            ->with('success', 'Penetapan SHU dibuat dan alokasi per anggota tersusun.');
    }

    public function edit(ShuPeriod $shu)
    {
        $this->pastikanBolehKelola();
        $this->pastikanBelumFinal($shu);

        return view('koperasi.shu.form', [
            'periode' => $shu,
            'tahun' => $shu->tahun,
            'usulanTotal' => $this->shu->usulanDariLabaRugi($shu->tahun),
            'komponenAwal' => $shu->komponen,
        ]);
    }

    public function update(Request $request, ShuPeriod $shu)
    {
        $this->pastikanBolehKelola();
        $this->pastikanBelumFinal($shu);

        $data = $this->validasi($request);

        // Tahun tidak boleh dipindahkan lewat Edit: alokasinya sudah dihitung
        // dari simpanan & transaksi tahun tersebut, dan memindahkan tahunnya
        // akan membuat dasar pembagian tidak lagi cocok dengan labelnya.
        unset($data['tahun']);

        $shu->update($data);
        $this->shu->susunAlokasi($shu);

        return redirect()
            ->route('koperasi.shu.show', $shu)
            ->with('success', 'Penetapan diperbarui dan alokasi disusun ulang.');
    }

    /** Susun ulang alokasi tanpa mengubah komponen — dipakai setelah data simpanan/transaksi diperbaiki. */
    public function susunUlang(ShuPeriod $shu)
    {
        $this->pastikanBolehKelola();
        $this->pastikanBelumFinal($shu);

        $jumlah = $this->shu->susunAlokasi($shu);

        return back()->with('success', 'Alokasi disusun ulang untuk ' . $jumlah . ' anggota.');
    }

    /**
     * Sahkan penetapan sebagai Final.
     *
     * Setelah ini angkanya tidak bisa diubah lagi, termasuk alokasinya —
     * karena pembagian yang sudah disahkan Rapat Anggota tidak boleh bergeser
     * ketika data simpanan atau transaksi diperbaiki kemudian.
     */
    public function sahkan(ShuPeriod $shu)
    {
        abort_unless(Auth::user()->isAdmin(), 403);
        $this->pastikanBelumFinal($shu);

        $shu->update(['status' => ShuPeriod::STATUS_FINAL]);

        ActivityLog::record(
            'shu_finalized',
            'ShuPeriod',
            $shu->id,
            'Mengesahkan penetapan SHU tahun ' . $shu->tahun . ' sebesar ' . rupiah($shu->total_shu),
        );

        return back()->with('success', 'Penetapan SHU tahun ' . $shu->tahun . ' disahkan dan kini terkunci.');
    }

    public function destroy(ShuPeriod $shu)
    {
        $this->pastikanBolehKelola();
        $this->pastikanBelumFinal($shu);

        $shu->delete();

        return redirect()
            ->route('koperasi.shu.index')
            ->with('success', 'Penetapan dipindahkan ke Arsip dan bisa dipulihkan.');
    }

    public function restore(int $shu)
    {
        $this->pastikanBolehKelola();

        ShuPeriod::withTrashed()->findOrFail($shu)->restore();

        return back()->with('success', 'Penetapan SHU berhasil dipulihkan.');
    }

    // ==================================================================

    private function pastikanBolehKelola(): void
    {
        abort_unless(Auth::user()->bolehKelolaKoperasi(), 403);
    }

    private function pastikanBelumFinal(ShuPeriod $shu): void
    {
        abort_if($shu->terkunci(), 403, 'Penetapan SHU yang sudah disahkan tidak dapat diubah.');
    }

    private function validasi(Request $request): array
    {
        $data = $request->validate([
            'tahun' => ['required', 'integer', 'min:2000', 'max:' . (now()->year + 1)],
            'tanggal_penetapan' => ['required', 'date'],
            'sumber' => ['required', Rule::in(array_keys(ShuPeriod::SUMBER_OPTIONS))],
            'total_shu' => ['required', 'integer', 'min:0'],
            'catatan' => ['nullable', 'string'],

            // Komponen datang sebagai baris dinamis. Persentase divalidasi per
            // baris; TOTAL-nya sengaja TIDAK diwajibkan 100 — pembagian SHU
            // adalah keputusan Rapat Anggota, dan sistem tidak berwenang
            // menolaknya. Yang dilakukan sistem adalah memperingatkan
            // (lihat ShuService::peringatan) supaya yang mengesahkan melihat
            // apa yang sedang ia sahkan.
            'komponen' => ['required', 'array', 'min:1'],
            'komponen.*.nama' => ['required', 'string', 'max:255'],
            'komponen.*.persen' => ['required', 'numeric', 'min:0', 'max:100'],
            'komponen.*.ke_anggota' => ['nullable', 'boolean'],
            'komponen.*.dasar' => ['nullable', Rule::in([ShuPeriod::DASAR_SIMPANAN, ShuPeriod::DASAR_TRANSAKSI])],
        ], [
            'tahun.required' => 'Tahun buku wajib diisi.',
            'total_shu.required' => 'Total SHU wajib diisi.',
            'komponen.required' => 'Minimal satu komponen pembagian harus diisi.',
            'komponen.*.nama.required' => 'Nama komponen wajib diisi.',
            'komponen.*.persen.max' => 'Persentase komponen tidak boleh lebih dari 100.',
        ]);

        $data['komponen'] = array_map(fn ($k) => [
            'nama' => $k['nama'],
            'persen' => (float) $k['persen'],
            'ke_anggota' => (bool) ($k['ke_anggota'] ?? false),
            // Dasar hanya berarti untuk komponen yang dibagikan ke anggota.
            // Menyimpannya pada komponen lain akan membuat pool jasa modal
            // ikut menghitung dana cadangan.
            'dasar' => ($k['ke_anggota'] ?? false) ? ($k['dasar'] ?? null) : null,
        ], $data['komponen']);

        return $data;
    }
}
