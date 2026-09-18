<?php

namespace App\Policies;

use App\Models\BusinessUnit;
use App\Models\User;

/**
 * OTORISASI UNIT USAHA KOPERASI.
 *
 * Sumber kebenarannya tetap User::bolehKelolaKoperasi() — Admin & Tim
 * Koperasi — sama seperti [KoperasiMemberPolicy]. Helper itu SENGAJA terpisah
 * dari isAdmin(): memperluas isAdmin() akan ikut membuka Manajemen User,
 * Backup, Restore, dan Pengaturan Sistem, karena keempatnya memakai guard
 * yang sama.
 */
class BusinessUnitPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->bolehKelolaKoperasi();
    }

    public function view(User $user, BusinessUnit $unit): bool
    {
        return $user->bolehKelolaKoperasi();
    }

    public function create(User $user): bool
    {
        return $user->bolehKelolaKoperasi();
    }

    public function update(User $user, BusinessUnit $unit): bool
    {
        return $user->bolehKelolaKoperasi();
    }

    public function delete(User $user, BusinessUnit $unit): bool
    {
        return $user->bolehKelolaKoperasi();
    }

    public function restore(User $user, BusinessUnit $unit): bool
    {
        return $user->bolehKelolaKoperasi();
    }
}
