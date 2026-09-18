<?php

namespace App\Models\Concerns;

use App\Models\Participant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Untuk catatan yang MENEMPEL pada satu peserta — riwayat pendampingan,
 * pelatihan, laporan omzet, perhitungan HPP.
 *
 * Menyelesaikan satu masalah spesifik di halaman Arsip.
 *
 * `Participant` memakai SoftDeletes, jadi global scope Eloquent membuat
 * relasi `participant()` mengembalikan NULL begitu pesertanya ikut
 * diarsipkan. Untuk tampilan biasa itu benar. Tapi di halaman Arsip justru
 * merugikan: catatan milik peserta terhapus jadi seolah tak bertuan, dan
 * pemeriksaan kepemilikan (canManageParticipantRecord) otomatis menolak
 * Pendamping pemiliknya sendiri.
 *
 * Trait ini menyediakan jalur yang SENGAJA ikut melihat peserta terhapus,
 * khusus untuk konteks arsip. Relasi participant() yang normal TIDAK diubah
 * sama sekali — halaman lain tetap berperilaku persis seperti sebelumnya.
 */
trait CatatanPeserta
{
    /**
     * Peserta pemilik catatan ini, TERMASUK yang sudah diarsipkan.
     *
     * Hasilnya di-cache di properti supaya memanggilnya berkali-kali dalam
     * satu request (policy + tampilan) tidak menembak query berulang.
     */
    public function pesertaTermasukArsip(): ?Participant
    {
        if ($this->participant_id === null) {
            return null;
        }

        // Diteruskan ke RELASI sungguhan di bawah, bukan relasi karangan yang
        // dipasang lewat setRelation().
        //
        // Versi sebelumnya menyimpan hasilnya dengan nama 'pesertaArsip' —
        // nama yang tidak punya method relasi pasangannya. Begitu Eloquent
        // perlu menelusuri ulang relasi model itu (saat menyimpan, memuat
        // ulang, atau lewat observer), ia mencari method bernama pesertaArsip
        // dan gagal dengan "Call to undefined relationship".
        //
        // Memakai relasi sungguhan membuat hasilnya tetap ter-cache seperti
        // sebelumnya, sekaligus bisa di-eager-load — dan hanya ada satu
        // definisi yang menentukan artinya.
        return $this->participantTermasukArsip;
    }

    /**
     * Bentuk RELASI dari hal yang sama, supaya bisa di-eager-load.
     *
     * pesertaTermasukArsip() di atas adalah sebuah method: hasilnya benar,
     * tapi memanggilnya di dalam perulangan halaman daftar menembak satu
     * query per baris. Halaman Riwayat Pendampingan memuat ratusan catatan,
     * jadi bentuk relasi ini yang dipakai di sana lewat with().
     *
     * Penamaannya mengikuti businessUnitTermasukArsip() pada SalesTransaction
     * — satu pola untuk satu masalah, supaya mudah dikenali saat ditemui di
     * modul lain.
     */
    public function participantTermasukArsip(): BelongsTo
    {
        return $this->belongsTo(Participant::class, 'participant_id')->withTrashed();
    }
}
