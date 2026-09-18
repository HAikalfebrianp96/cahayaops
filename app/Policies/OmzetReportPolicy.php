<?php

namespace App\Policies;

use App\Models\OmzetReport;
use App\Models\Participant;
use App\Models\User;

/**
 * OTORISASI LAPORAN OMZET (perkembangan usaha peserta).
 *
 * Perhatikan bedanya view dan update — ini sudah jadi perilaku modul ini
 * sejak awal dan TIDAK diubah, hanya dipindahkan ke policy supaya seragam:
 *
 *   view   -> Admin, Pendamping pemilik binaan, DAN Pimpinan (read-only).
 *   create/update/delete -> Admin & Pendamping pemilik binaan saja.
 *            Pimpinan tertolak karena canAccessParticipant() memang tidak
 *            pernah mengizinkan Pimpinan.
 */
class OmzetReportPolicy
{
    public function view(User $user, OmzetReport $report): bool
    {
        return $report->participant !== null
            && $user->canViewParticipant($report->participant);
    }

    /** Menambahkan laporan omzet atas nama SATU peserta. */
    public function createFor(User $user, Participant $participant): bool
    {
        return $user->canAccessParticipant($participant);
    }

    public function update(User $user, OmzetReport $report): bool
    {
        return $user->canManageParticipantRecord($report->participant);
    }

    public function delete(User $user, OmzetReport $report): bool
    {
        return $user->canManageParticipantRecord($report->participant);
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
    public function restore(User $user, OmzetReport $report): bool
    {
        return $user->canManageParticipantRecord($report->pesertaTermasukArsip());
    }
}
