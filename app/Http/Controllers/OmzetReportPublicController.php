<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\MenentukanPeriodeOmzet;
use App\Http\Controllers\Concerns\MenyimpanBuktiOmzet;
use App\Models\OmzetReport;
use App\Models\Participant;
use Illuminate\Http\Request;

/**
 * FORM LAPORAN OMZET PUBLIK — diisi peserta sendiri, tanpa akun.
 *
 * Satu-satunya endpoint tulis di CahayaOS yang bisa dijangkau tanpa login,
 * jadi seluruh aturannya diambil dari trait yang SAMA dengan jalur internal
 * (MenentukanPeriodeOmzet, MenyimpanBuktiOmzet) — bukan disalin. Aturan yang
 * disalin cepat atau lambat berbeda, dan yang longgar hampir pasti yang di
 * sini.
 */
class OmzetReportPublicController extends Controller
{
    use MenentukanPeriodeOmzet, MenyimpanBuktiOmzet;

    /**
     * Tampilkan form laporan omzet berdasarkan token unik peserta.
     * Tidak memerlukan login. Kalau token tidak ditemukan -> 404,
     * jadi peserta tidak bisa menebak-nebak/melihat data peserta lain.
     */
    public function show(string $token)
    {
        $participant = Participant::where('report_token', $token)->firstOrFail();

        return view('omzet.public-form', [
            'participant' => $participant,
            'bulanOptions' => OmzetReport::BULAN,
            'jenisOptions' => OmzetReport::JENIS_OPTIONS,
            'tahunOptions' => $this->tahunOptions(),
        ]);
    }

    public function store(Request $request, string $token)
    {
        $participant = Participant::where('report_token', $token)->firstOrFail();

        $validated = $request->validate(
            $this->aturanPeriode($request) + [
                'omzet' => ['required', 'integer', 'min:0'],
                // HPP & biaya operasional OPSIONAL — sama seperti jalur internal.
                // Form ini diisi peserta sendiri lewat ponsel, sering sambil
                // melayani pembeli; mewajibkan dua angka tambahan adalah cara
                // paling cepat membuat laporan omzet berhenti masuk sama sekali.
                'hpp' => ['nullable', 'integer', 'min:0', 'lte:omzet'],
                'biaya_operasional' => ['nullable', 'integer', 'min:0'],
                'catatan' => ['nullable', 'string', 'max:2000'],
            ] + $this->aturanBukti(),
            [
                'omzet.required' => 'Omzet wajib diisi.',
                'omzet.integer' => 'Omzet harus berupa angka.',
                'omzet.min' => 'Omzet tidak boleh negatif.',
                'hpp.integer' => 'Modal/HPP harus berupa angka.',
                'hpp.min' => 'Modal/HPP tidak boleh negatif.',
                'hpp.lte' => 'Modal/HPP tidak boleh lebih besar dari omzet.',
                'biaya_operasional.integer' => 'Biaya operasional harus berupa angka.',
                'biaya_operasional.min' => 'Biaya operasional tidak boleh negatif.',
            ] + $this->pesanPeriode() + $this->pesanBukti(),
        );

        $periode = $this->periodeDari($validated);

        // PENJAGA ANTI DOUBLE COUNTING — satu bulan, satu cara pencatatan.
        //
        // Kalau peserta mengirim laporan harian untuk September lalu minggu
        // depan mengirim laporan bulanan September, kedua-duanya akan
        // terjumlah dan omzet bulan itu tercatat dua kali. Pesannya sengaja
        // menyebutkan jalan keluarnya, karena yang membacanya adalah peserta
        // di lapangan — bukan petugas yang paham struktur datanya.
        $modeTerpakai = OmzetReport::modeBulan(
            $participant->id,
            $periode['periode_bulan'],
            $periode['periode_tahun'],
        );

        if ($modeTerpakai !== null && $modeTerpakai !== $periode['jenis_periode']) {
            return back()->withInput()->withErrors([
                'jenis_periode' => OmzetReport::pesanModeBentrok($modeTerpakai, $periode['periode_bulan']),
            ]);
        }

        // Cek periode memakai untukPeriode(), yang ikut melihat baris
        // TER-SOFT-DELETE. Ini penting: unique index di database tidak
        // mempedulikan `deleted_at`, jadi laporan periode yang pernah dihapus
        // Admin tetap memblokir index. Tanpa withTrashed(), pengecekan di sini
        // akan lolos lalu insert-nya gagal dengan error database di depan
        // peserta. Dengan ini, baris lamanya dipulihkan & diperbarui.
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
                ->withErrors([
                    'periode_bulan' => 'Laporan untuk periode ini sudah pernah dikirim sebelumnya. Kalau ada yang perlu diperbaiki, silakan hubungi pendamping/Admin YBM.',
                ]);
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
            ->route('omzet.public.show', $token)
            ->with('success', 'Terima kasih! Laporan omzet Anda berhasil dikirim.');
    }

    private function tahunOptions(): array
    {
        $tahunSekarang = now()->year;

        return range($tahunSekarang - 1, $tahunSekarang);
    }
}
