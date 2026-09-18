<?php

namespace App\Policies;

use App\Models\KoperasiMember;
use App\Models\MemberSaving;
use App\Models\User;

/**
 * OTORISASI SIMPANAN ANGGOTA.
 *
 * Siapa yang boleh sama dengan modul koperasi lainnya (Admin & Tim Koperasi).
 * Yang berbeda di sini adalah APA yang boleh, karena ini menyangkut uang
 * anggota:
 *
 *   update   -> HANYA selama entri belum dikunci dan belum dibatalkan.
 *               Setelah itu izinnya dicabut oleh keadaan datanya sendiri,
 *               bukan oleh role — Admin sekalipun tidak bisa mengubah entri
 *               terkunci diam-diam.
 *   batalkan -> membatalkan entri (barisnya TETAP ada) dan boleh diikuti
 *               entri pengganti. Inilah jalur koreksi yang berjejak.
 *
 * Tidak ada method delete: baris simpanan tidak pernah dihapus.
 */
class MemberSavingPolicy
{
    /** Mencatat simpanan baru untuk SATU anggota. */
    public function createFor(User $user, KoperasiMember $member): bool
    {
        return $user->bolehKelolaKoperasi();
    }

    public function update(User $user, MemberSaving $saving): bool
    {
        return $user->bolehKelolaKoperasi() && $saving->bolehDiubahLangsung();
    }

    /**
     * Entri yang sudah dibatalkan tidak bisa dibatalkan lagi — kalau boleh,
     * riwayat koreksinya jadi berlapis tanpa arti.
     */
    public function batalkan(User $user, MemberSaving $saving): bool
    {
        return $user->bolehKelolaKoperasi() && ! $saving->dibatalkan();
    }

    /**
     * PULIHKAN entri yang dibatalkan.
     *
     * ==================================================================
     * SATU-SATUNYA TEMPAT DI SISTEM INI YANG BISA MENGHITUNG UANG DUA KALI
     * ==================================================================
     *
     * Pembatalan simpanan boleh diikuti pembuatan entri PENGGANTI. Kalau
     * entri asal kemudian dipulihkan sementara penggantinya masih berlaku,
     * uang yang sama masuk dua kali ke saldo anggota — dan saldo simpanan
     * adalah angka yang dibawa anggota ke Rapat Anggota.
     *
     * Karena itu izinnya dicabut oleh KEADAAN DATA, bukan oleh role: selama
     * masih ada pengganti yang berlaku, Admin sekalipun tidak bisa
     * memulihkan entri ini. Jalan keluarnya jelas dan berjejak — batalkan
     * dulu penggantinya, baru pulihkan yang asal.
     */
    public function pulihkan(User $user, MemberSaving $saving): bool
    {
        return $user->bolehKelolaKoperasi()
            && $saving->dibatalkan()
            && ! $saving->punyaPenggantiBerlaku();
    }
}
