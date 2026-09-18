<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;

/**
 * OTORISASI PELANGGAN KOPERASI.
 *
 * Sumbernya sama dengan seluruh modul koperasi lain:
 * User::bolehKelolaKoperasi() — Admin & Tim Koperasi.
 */
class CustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->bolehKelolaKoperasi();
    }

    public function create(User $user): bool
    {
        return $user->bolehKelolaKoperasi();
    }

    public function update(User $user, Customer $customer): bool
    {
        return $user->bolehKelolaKoperasi();
    }

    public function delete(User $user, Customer $customer): bool
    {
        return $user->bolehKelolaKoperasi();
    }

    public function restore(User $user, Customer $customer): bool
    {
        return $user->bolehKelolaKoperasi();
    }
}
