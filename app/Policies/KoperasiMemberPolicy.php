<?php

namespace App\Policies;

use App\Models\KoperasiMember;
use App\Models\User;

/**
 * OTORISASI KEANGGOTAAN KOPERASI CAHAYA.
 *
 * Satu-satunya sumber kebenaran tetap User::bolehKelolaKoperasi() — Admin
 * dan Tim Koperasi. Helper itu SENGAJA terpisah dari isAdmin(): kalau
 * isAdmin() yang diperluas, Manajemen User, Backup, Restore, dan Pengaturan
 * Sistem ikut terbuka untuk Tim Koperasi, karena keempatnya memakai guard
 * yang sama.
 */
class KoperasiMemberPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->bolehKelolaKoperasi();
    }

    public function view(User $user, KoperasiMember $member): bool
    {
        return $user->bolehKelolaKoperasi();
    }

    public function create(User $user): bool
    {
        return $user->bolehKelolaKoperasi();
    }

    public function update(User $user, KoperasiMember $member): bool
    {
        return $user->bolehKelolaKoperasi();
    }

    public function delete(User $user, KoperasiMember $member): bool
    {
        return $user->bolehKelolaKoperasi();
    }

    public function restore(User $user, KoperasiMember $member): bool
    {
        return $user->bolehKelolaKoperasi();
    }

    /**
     * Menelusuri database peserta Cahaya Preneur UNTUK MEMILIH calon anggota.
     *
     * Ini BUKAN akses ke modul Cahaya Preneur: Tim Koperasi tetap ditolak di
     * /participants dan tidak bisa mengubah apa pun milik peserta. Yang
     * dibuka hanya pencarian nama + data dasar, supaya identitas tidak perlu
     * diketik ulang dan tidak lahir baris identitas kedua.
     */
    public function pilihDariPeserta(User $user): bool
    {
        return $user->bolehKelolaKoperasi();
    }
}
