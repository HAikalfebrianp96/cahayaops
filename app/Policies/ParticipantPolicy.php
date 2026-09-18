<?php

namespace App\Policies;

use App\Models\Participant;
use App\Models\User;

/**
 * OTORISASI PESERTA / BINAAN — di level backend, bukan sekadar menyembunyikan
 * tombol.
 *
 * Aturannya TIDAK ditulis ulang di sini: setiap method mendelegasikan ke
 * helper di [User] yang sudah lebih dulu dipakai controller & Blade. Jadi
 * tombol yang tampil di layar dan izin yang diperiksa backend SELALU berasal
 * dari satu sumber kebenaran yang sama, dan tidak mungkin berbeda.
 *
 * Ringkasnya:
 *   Admin      -> seluruh peserta, termasuk hapus, pulihkan, dan menentukan
 *                 siapa Pendampingnya.
 *   Pendamping -> HANYA peserta yang menjadi binaannya sendiri: lihat, buat,
 *                 ubah. Tidak boleh menghapus, tidak boleh memindahkan binaan.
 *   Pimpinan   -> read-only, boleh melihat peserta manapun.
 */
class ParticipantPolicy
{
    /** Daftar peserta global (Data Peserta Cahaya Preneur) — Admin saja.
     *  Pendamping punya daftarnya sendiri di menu "Binaan Saya". */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, Participant $participant): bool
    {
        return $user->canViewParticipant($participant);
    }

    public function create(User $user): bool
    {
        return $user->canCreateParticipant();
    }

    public function update(User $user, Participant $participant): bool
    {
        return $user->canAccessParticipant($participant);
    }

    public function delete(User $user, Participant $participant): bool
    {
        return $user->canDeleteParticipant();
    }

    public function restore(User $user, Participant $participant): bool
    {
        return $user->canDeleteParticipant();
    }

    /** Menentukan / memindahkan Pendamping pada data peserta — Admin saja. */
    public function assignPendamping(User $user, ?Participant $participant = null): bool
    {
        return $user->canAssignPendamping();
    }
}
