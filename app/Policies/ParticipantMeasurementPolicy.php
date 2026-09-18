<?php

namespace App\Policies;

use App\Models\Participant;
use App\Models\ParticipantMeasurement;
use App\Models\User;

/**
 * OTORISASI PENGUKURAN PERKEMBANGAN (baseline / monitoring / endline).
 *
 * Aturannya TIDAK ditulis ulang di sini. Pengukuran adalah catatan yang
 * menempel pada peserta — bentuk yang sama persis dengan riwayat
 * pendampingan, pelatihan, dan laporan omzet — jadi izinnya diambil dari
 * helper yang SUDAH dipakai ketiganya, canManageParticipantRecord().
 *
 * Konsekuensinya disengaja: begitu Admin memindahkan binaan ke pendamping
 * lain, pendamping barulah yang berhak merapikan pengukurannya. Kalau
 * aturannya disalin ulang di sini, cepat atau lambat ia akan berbeda dari
 * tiga modul saudaranya — dan perbedaan itulah yang menjadi celah.
 *
 * Yang khas hanya penghapusan: sama seperti peserta, menghapus baseline
 * berdampak lintas modul (perbandingan, Impact, SROI semuanya berdiri di
 * atasnya), jadi tetap kewenangan Admin. Pendamping memperbaiki lewat Edit.
 */
class ParticipantMeasurementPolicy
{
    /** Melihat pengukuran peserta tertentu — termasuk Pimpinan (read-only). */
    public function view(User $user, ParticipantMeasurement $measurement): bool
    {
        $peserta = $measurement->pesertaTermasukArsip();

        return $peserta !== null && $user->canViewParticipant($peserta);
    }

    /** Mencatat pengukuran baru untuk satu peserta. */
    public function createFor(User $user, Participant $participant): bool
    {
        return $user->canAccessParticipant($participant);
    }

    public function update(User $user, ParticipantMeasurement $measurement): bool
    {
        return $user->canManageParticipantRecord($measurement->pesertaTermasukArsip());
    }

    /**
     * Menghapus / memulihkan — Admin saja.
     *
     * Seluruh klaim perubahan usaha berdiri di atas baris-baris ini; sekali
     * baseline hilang, perbandingan peserta itu tidak bisa disusun ulang dari
     * data lain mana pun.
     */
    public function delete(User $user, ParticipantMeasurement $measurement): bool
    {
        return $user->isAdmin();
    }

    public function restore(User $user, ParticipantMeasurement $measurement): bool
    {
        return $user->isAdmin();
    }
}
