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
 * hapusPermanen -> membuang barisnya untuk selamanya. Ini BUKAN jalur
 *                  koreksi — jalur koreksi tetap batalkan(). Yang ini ada
 *                  karena entri percobaan yang sudah dibatalkan tidak punya
 *                  nilai histori apa pun, hanya menumpuk di riwayat anggota.
 *                  Syaratnya diperiksa di PenghapusanPermanenService:
 *                  harus sudah dibatalkan, dan tidak sedang jadi mata rantai
 *                  koreksi entri lain.
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

    /**
     * HAPUS PERMANEN satu entri simpanan.
     *
     * Rolenya sama dengan tindakan koperasi lainnya. Yang membatasi bukan
     * role melainkan KEADAAN DATA — dan keadaan itu diperiksa di
     * PenghapusanPermanenService, bukan diulang di sini, supaya syaratnya
     * hanya punya satu tempat tinggal.
     *
     * Satu hal yang TIDAK menahannya, dan itu disengaja: status anggotanya.
     * Anggota yang dinonaktifkan tetap punya simpanan yang harus utuh —
     * simpanan tidak pernah dihapus karena status anggotanya berubah.
     */
    public function hapusPermanen(User $user, MemberSaving $saving): bool
    {
        return $user->bolehKelolaKoperasi();
    }
}
