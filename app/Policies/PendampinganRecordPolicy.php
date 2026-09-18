<?php

namespace App\Policies;

use App\Models\Participant;
use App\Models\PendampinganRecord;
use App\Models\User;

/**
 * OTORISASI RIWAYAT PENDAMPINGAN.
 *
 * Hak atas satu catatan diturunkan dari PESERTANYA, bukan dari siapa yang
 * dulu menuliskannya:
 *
 *   Admin      -> semua catatan (ubah & hapus).
 *   Pendamping -> hanya catatan milik binaannya sendiri.
 *   Pimpinan   -> tidak sama sekali (read-only lewat Detail Peserta).
 */
class PendampinganRecordPolicy
{
    /** Monitoring Pendampingan (seluruh catatan lintas peserta) — Admin saja. */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    /** Menambah catatan pada SATU peserta — dicek terhadap pesertanya. */
    public function createFor(User $user, Participant $participant): bool
    {
        return $user->canAccessParticipant($participant);
    }

    public function update(User $user, PendampinganRecord $record): bool
    {
        return $user->canManagePendampinganRecord($record);
    }

    public function delete(User $user, PendampinganRecord $record): bool
    {
        return $user->canManagePendampinganRecord($record);
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
    public function restore(User $user, PendampinganRecord $record): bool
    {
        return $user->canManageParticipantRecord($record->pesertaTermasukArsip());
    }

    /** Mengganti Pendamping pelaksana pada catatan — Admin saja. */
    public function assignPendamping(User $user, ?PendampinganRecord $record = null): bool
    {
        return $user->canAssignPendamping();
    }
}
