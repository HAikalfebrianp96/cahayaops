<?php

namespace App\Policies;

use App\Models\PendampingTarget;
use App\Models\User;

/**
 * OTORISASI TARGET PENDAMPING.
 *
 * Target adalah KEBIJAKAN PROGRAM, bukan catatan lapangan. Karena itu
 * pembagiannya tegas dan berbeda dari modul lain di CahayaOS:
 *
 *   Admin      -> menetapkan, mengubah, mengarsipkan, memulihkan.
 *   Pendamping -> HANYA MELIHAT targetnya sendiri. Tidak boleh membuat
 *                 maupun mengubah — kalau boleh, target berhenti menjadi
 *                 alat ukur dan berubah jadi sesuatu yang bisa disesuaikan
 *                 sendiri agar terlihat tercapai.
 *   Lainnya    -> tidak sama sekali. Tim Koperasi tidak berurusan dengan
 *                 kinerja pendamping Cahaya Preneur.
 */
class PendampingTargetPolicy
{
    /** Daftar seluruh target — Admin saja. */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Melihat SATU target.
     *
     * Pendamping boleh, TAPI hanya miliknya sendiri — dan kepemilikannya
     * diperiksa terhadap `pendamping_id` barisnya, bukan sekadar role.
     */
    public function view(User $user, PendampingTarget $target): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->isPendamping()
            && $user->pendamping_id !== null
            && $target->pendamping_id === $user->pendamping_id;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, PendampingTarget $target): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, PendampingTarget $target): bool
    {
        return $user->isAdmin();
    }

    public function restore(User $user, PendampingTarget $target): bool
    {
        return $user->isAdmin();
    }

    /**
     * Membuka halaman KPI & Kinerja Pendamping.
     *
     * Admin melihat seluruh pendamping; Pendamping yang akunnya tertaut
     * hanya melihat dirinya sendiri (di-scope di controller, bukan di sini).
     */
    public function lihatKpi(User $user): bool
    {
        return $user->isAdmin()
            || ($user->isPendamping() && $user->pendamping_id !== null);
    }
}
