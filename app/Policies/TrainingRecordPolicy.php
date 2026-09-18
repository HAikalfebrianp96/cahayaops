<?php

namespace App\Policies;

use App\Models\Participant;
use App\Models\TrainingRecord;
use App\Models\User;

/**
 * OTORISASI RIWAYAT PELATIHAN.
 *
 * Polanya SENGAJA dibuat identik dengan [PendampinganRecordPolicy]: hak atas
 * satu catatan diturunkan dari PESERTANYA, bukan dari siapa yang dulu
 * menuliskannya. Dua modul yang bentuk datanya sama (catatan kegiatan milik
 * satu peserta) tidak boleh punya dua aturan izin yang berbeda — itu sumber
 * kebingungan dan celah.
 *
 *   Admin      -> semua data pelatihan.
 *   Pendamping -> hanya pelatihan milik binaannya sendiri.
 *   Pimpinan   -> read-only lewat Detail Peserta, tidak boleh mengubah.
 */
class TrainingRecordPolicy
{
    /** Menambah data pelatihan pada SATU peserta — dicek terhadap pesertanya. */
    public function createFor(User $user, Participant $participant): bool
    {
        return $user->canAccessParticipant($participant);
    }

    public function update(User $user, TrainingRecord $record): bool
    {
        return $user->canManageParticipantRecord($record->participant);
    }

    public function delete(User $user, TrainingRecord $record): bool
    {
        return $user->canManageParticipantRecord($record->participant);
    }

    /**
     * Memulihkan catatan dari Arsip.
     *
     * Hak yang SAMA dengan menghapusnya — kalau seseorang berwenang membuang
     * sebuah catatan, ia juga berwenang membatalkan keputusannya.
     *
     * Bedanya satu: pesertanya dicari lewat pesertaTermasukArsip(), yang ikut
     * melihat peserta terhapus. Tanpa itu, catatan milik peserta yang ikut
     * diarsipkan jadi terlihat tak bertuan, dan Pendamping pemiliknya sendiri
     * malah ditolak memulihkan catatannya.
     */
    public function restore(User $user, TrainingRecord $record): bool
    {
        return $user->canManageParticipantRecord($record->pesertaTermasukArsip());
    }
}
