<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\KoperasiMember;
use App\Models\MemberSaving;
use App\Services\PenghapusanPermanenService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * SIMPANAN ANGGOTA — koreksi yang MENINGGALKAN JEJAK.
 *
 * Ini uang anggota, jadi aturannya sengaja lebih ketat daripada modul lain di
 * CahayaOS. Untuk MENGOREKSI angka, tersedia dua jalur — dan keduanya
 * meninggalkan jejak:
 *
 *   1. PERBAIKI (update)  — selama entri belum dikunci. Nominal, tanggal,
 *      jenis, dan keterangannya boleh diubah, dan Activity Log mencatat nilai
 *      SEBELUM -> SESUDAH, jadi perubahannya tetap bisa ditelusuri.
 *
 *   2. BATALKAN (batalkan) — untuk entri yang sudah dikunci, atau kesalahan
 *      yang lebih baik dinyatakan terbuka. Barisnya diberi status
 *      "Dibatalkan" beserta alasan, waktu, dan pelakunya; ia berhenti
 *      dihitung ke saldo tapi tetap terlihat di riwayat. Operator boleh
 *      langsung mengisi angka yang benar, dan sistem membuat entri PENGGANTI
 *      yang menunjuk balik ke entri asal.
 *
 * Dengan begitu saldo selalu bisa diperbaiki tanpa satu pun transaksi hilang.
 *
 * Di luar kedua jalur itu ada jalur KETIGA yang bukan koreksi:
 *
 *   3. HAPUS PERMANEN (destroy) — barisnya benar-benar dibuang. Ini untuk
 *      entri percobaan yang sudah dibatalkan dan tidak punya nilai histori
 *      apa pun; ia hanya menumpuk di riwayat anggota dan mengaburkan angka
 *      yang sebenarnya. Syaratnya dijaga PenghapusanPermanenService: harus
 *      sudah dibatalkan lebih dulu, jadi menghapus selalu keputusan KEDUA
 *      yang diambil pada waktu berbeda — dan entri yang sudah jadi mata
 *      rantai koreksi entri lain tetap tidak bisa dihapus.
 *
 *      Yang TIDAK pernah jadi alasan menghapus: status anggotanya. Anggota
 *      nonaktif tetap memiliki simpanannya secara utuh.
 */
class MemberSavingController extends Controller
{
    public function store(Request $request, KoperasiMember $anggota)
    {
        Gate::authorize('createFor', [MemberSaving::class, $anggota]);

        $anggota->savings()->create($this->validated($request));

        return redirect()
            ->route('koperasi.anggota.show', $anggota)
            ->with('success', 'Simpanan berhasil dicatat.');
    }

    public function edit(MemberSaving $simpanan)
    {
        Gate::authorize('update', $simpanan);

        // Riwayat simpanan tetap harus bisa dikoreksi walau keanggotaannya
        // sudah diarsipkan — saat keanggotaan berakhir itulah saldonya paling
        // sering diperiksa ulang.
        $simpanan->load('koperasiMemberTermasukArsip.participant');

        return view('koperasi.simpanan.edit', [
            'simpanan' => $simpanan,
            'member' => $simpanan->koperasiMemberTermasukArsip,
            'jenisOptions' => MemberSaving::JENIS_OPTIONS,
        ]);
    }

    /**
     * Perbaikan di tempat. Policy menolaknya begitu entri terkunci atau sudah
     * dibatalkan, jadi jalur ini hanya berlaku untuk entri yang masih hidup.
     */
    public function update(Request $request, MemberSaving $simpanan)
    {
        Gate::authorize('update', $simpanan);

        $validated = $this->validated($request);
        $sebelum = $this->ringkas($simpanan);

        $simpanan->update($validated);

        // Log tambahan berisi nilai SEBELUM -> SESUDAH. Observer sudah mencatat
        // 'koperasi_saving_updated' secara umum; yang ini melengkapinya dengan
        // angka lamanya, satu-satunya cara membuktikan apa yang berubah.
        ActivityLog::record(
            action: 'koperasi_saving_updated',
            modelType: 'MemberSaving',
            modelId: $simpanan->id,
            description: 'Perbaikan simpanan ' . $simpanan->koperasiMember?->namaTampil()
                . ' — sebelum: ' . $sebelum . ' | sesudah: ' . $this->ringkas($simpanan->fresh()),
        );

        return redirect()
            ->route('koperasi.anggota.show', $simpanan->koperasi_member_id)
            ->with('success', 'Data simpanan berhasil diperbaiki. Perubahannya tercatat di Activity Log.');
    }

    /**
     * Pembatalan + entri pengganti opsional.
     *
     * Keduanya dibungkus satu transaksi: kalau entri pengganti gagal disimpan,
     * pembatalannya ikut dibatalkan — supaya tidak pernah ada keadaan setengah
     * jadi di mana saldo anggota berkurang tanpa entri penggantinya.
     */
    public function batalkan(Request $request, MemberSaving $simpanan)
    {
        Gate::authorize('batalkan', $simpanan);

        $validated = $request->validate([
            'alasan_koreksi' => ['required', 'string', 'max:1000'],
            'buat_pengganti' => ['nullable', 'boolean'],
            'nominal_pengganti' => ['nullable', 'required_if:buat_pengganti,1', 'numeric', 'min:0'],
            'tanggal_pengganti' => ['nullable', 'required_if:buat_pengganti,1', 'date'],
        ], [
            'alasan_koreksi.required' => 'Alasan koreksi wajib diisi — inilah yang membuat pembatalan bisa dipertanggungjawabkan.',
            'nominal_pengganti.required_if' => 'Nominal pengganti wajib diisi kalau entri pengganti dibuat.',
            'tanggal_pengganti.required_if' => 'Tanggal pengganti wajib diisi kalau entri pengganti dibuat.',
        ]);

        $nominalLama = $this->ringkas($simpanan);

        DB::transaction(function () use ($request, $simpanan, $validated) {
            $simpanan->forceFill([
                'status' => MemberSaving::STATUS_DIBATALKAN,
                'alasan_koreksi' => $validated['alasan_koreksi'],
                'dibatalkan_at' => now(),
                'dibatalkan_oleh' => Auth::id(),
            ])->save();

            if ($request->boolean('buat_pengganti')) {
                $simpanan->koperasiMember->savings()->create([
                    'jenis_simpanan' => $simpanan->jenis_simpanan,
                    'tanggal' => $validated['tanggal_pengganti'],
                    'nominal' => $validated['nominal_pengganti'],
                    'keterangan' => $simpanan->keterangan,
                    'dikoreksi_dari_id' => $simpanan->id,
                    'alasan_koreksi' => $validated['alasan_koreksi'],
                ]);
            }
        });

        ActivityLog::record(
            action: 'koperasi_saving_corrected',
            modelType: 'MemberSaving',
            modelId: $simpanan->id,
            description: 'Simpanan dibatalkan (' . $nominalLama . ') milik '
                . ($simpanan->koperasiMember?->namaTampil() ?? '#' . $simpanan->koperasi_member_id)
                . '. Alasan: ' . $validated['alasan_koreksi']
                . ($request->boolean('buat_pengganti') ? ' — entri pengganti dibuat.' : ' — tanpa entri pengganti.'),
        );

        return redirect()
            ->route('koperasi.anggota.show', $simpanan->koperasi_member_id)
            ->with('success', 'Simpanan dibatalkan. Entrinya tetap tersimpan sebagai jejak koreksi.');
    }

    /**
     * Mengunci entri supaya tidak bisa diedit lagi di tempat.
     * Sesudah dikunci, satu-satunya jalan koreksi adalah pembatalan —
     * yang selalu meninggalkan jejak.
     */
    /**
     * PULIHKAN entri simpanan yang dibatalkan.
     *
     * Perlindungan terhadap penghitungan ganda ada di MemberSavingPolicy —
     * ditaruh di policy, bukan di sini, supaya aturannya ikut berlaku di
     * mana pun izin ini diperiksa (termasuk saat Blade memutuskan apakah
     * tombolnya ditampilkan), bukan hanya di jalur HTTP ini.
     */
    public function pulihkan(MemberSaving $simpanan)
    {
        Gate::authorize('pulihkan', $simpanan);

        $alasanLama = $simpanan->alasan_koreksi;

        $simpanan->forceFill([
            'status' => MemberSaving::STATUS_TERCATAT,
            'alasan_koreksi' => null,
            'dibatalkan_at' => null,
            'dibatalkan_oleh' => null,
        ])->save();

        ActivityLog::record(
            action: 'member_saving_restored',
            modelType: 'MemberSaving',
            modelId: $simpanan->id,
            description: 'Simpanan ' . $simpanan->jenis_simpanan . ' ' . rupiah($simpanan->nominal)
                . ' milik ' . ($simpanan->koperasiMember->nama ?? 'anggota') . ' dipulihkan dan kembali dihitung ke saldo.'
                . ($alasanLama ? ' Alasan pembatalan sebelumnya: ' . $alasanLama : ''),
        );

        return redirect()
            ->route('koperasi.anggota.show', $simpanan->koperasi_member_id)
            ->with('success', 'Entri simpanan dipulihkan dan kembali dihitung ke total simpanan anggota.');
    }

    public function kunci(MemberSaving $simpanan)
    {
        Gate::authorize('update', $simpanan);

        $simpanan->forceFill(['dikunci_at' => now()])->save();

        return redirect()
            ->route('koperasi.anggota.show', $simpanan->koperasi_member_id)
            ->with('success', 'Entri simpanan dikunci. Koreksi berikutnya harus lewat Batalkan agar berjejak.');
    }

    /**
     * HAPUS PERMANEN satu entri simpanan.
     *
     * Saldo anggota TIDAK disimpan di kolom mana pun: ia selalu dijumlahkan
     * dari entri-entri ini. Jadi begitu barisnya hilang, saldonya ikut
     * turun dengan sendirinya — tidak ada angka lain yang perlu disesuaikan,
     * dan karena itu pula tidak ada angka yang bisa tertinggal salah.
     */
    public function destroy(MemberSaving $simpanan, PenghapusanPermanenService $penghapusan)
    {
        Gate::authorize('hapusPermanen', $simpanan);

        $alasan = $penghapusan->alasanTertahan($simpanan);

        if ($alasan !== null) {
            return back()->withErrors(['hapus' => $alasan]);
        }

        // Diambil SEBELUM dihapus; sesudahnya tidak ada yang tersisa untuk
        // dirujuk. Tidak ada data rahasia di sini.
        $id = $simpanan->id;
        $anggotaId = $simpanan->koperasi_member_id;
        $ringkasan = $this->ringkas($simpanan);

        DB::transaction(fn () => $penghapusan->hapus($simpanan));

        ActivityLog::record(
            action: 'saving_permanently_deleted',
            modelType: 'MemberSaving',
            modelId: $id,
            description: 'SIMPANAN DIHAPUS PERMANEN: ' . $ringkasan
                . '. Saldo anggota ikut menyesuaikan. Tindakan ini tidak dapat dibatalkan.',
        );

        return redirect()
            ->route('koperasi.anggota.show', $anggotaId)
            ->with('success', 'Entri simpanan ' . $ringkasan . ' dihapus permanen.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'jenis_simpanan' => ['required', Rule::in(MemberSaving::JENIS_OPTIONS)],
            'tanggal' => ['required', 'date'],
            'nominal' => ['required', 'numeric', 'min:0'],
            'keterangan' => ['nullable', 'string'],
        ]);
    }

    /** Ringkasan satu baris untuk Activity Log. */
    private function ringkas(MemberSaving $simpanan): string
    {
        return $simpanan->jenis_simpanan
            . ' Rp' . number_format((float) $simpanan->nominal, 0, ',', '.')
            . ' tgl ' . optional($simpanan->tanggal)->format('d-m-Y');
    }
}
