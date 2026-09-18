<?php

namespace App\Policies;

use App\Models\ImpactProgram;
use App\Models\User;

/**
 * OTORISASI MODUL DAMPAK & SROI.
 *
 * SATU policy untuk seluruh modul (program, investasi, outcome, proxy,
 * perhitungan), bukan lima policy terpisah. Alasannya: keempat entitas itu
 * adalah bagian dari satu keputusan yang sama — siapa yang boleh menyusun dan
 * menerbitkan klaim dampak lembaga. Memecahnya jadi lima berkas hanya membuka
 * peluang salah satunya kelak berbeda dari yang lain.
 *
 * PEMBAGIANNYA:
 *
 *   Admin     menyusun: program, investasi, outcome, proxy, dan menghitung.
 *   Pimpinan  membaca semuanya, DAN memberi persetujuan akhir.
 *   Pendamping & Tim Koperasi: tidak sama sekali.
 *
 * Kenapa Pendamping tidak ikut, padahal dialah yang mengumpulkan datanya?
 * Karena pekerjaannya memang sudah selesai di tempat lain: baseline, endline,
 * omzet, dan pendampingan yang ia catat itulah yang dibaca mesin SROI. Modul
 * ini tidak meminta data baru darinya — ia hanya menafsirkan data yang sudah
 * ada, dan penafsiran untuk keperluan pelaporan lembaga bukan kewenangan
 * lapangan.
 *
 * Kenapa PERSETUJUAN dipisah dari MENGHITUNG, dan justru diberikan ke
 * Pimpinan? Karena keduanya adalah pemeriksaan yang berbeda. Menghitung
 * berarti menjalankan rumus atas data yang ada; menyetujui berarti menyatakan
 * angka ini layak dibawa keluar atas nama lembaga. Kalau satu orang boleh
 * melakukan keduanya, tidak ada satu pun tahap di mana angka dampak dilihat
 * oleh mata kedua sebelum terbit.
 */
class ImpactPolicy
{
    /** Membuka modul Dampak & SROI sama sekali. */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isPimpinan();
    }

    public function view(User $user, ImpactProgram $program): bool
    {
        return $this->viewAny($user);
    }

    /** Menyusun: program, investasi, outcome, proxy. */
    public function manage(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Mengubah isi sebuah program.
     *
     * Program berstatus Final terkunci untuk SEMUA orang, Admin termasuk.
     * Angkanya sudah dipakai melapor keluar; kalau masih bisa diubah, laporan
     * yang sudah terkirim tidak lagi cocok dengan sistem dan tidak ada yang
     * bisa menjelaskan selisihnya. Revisi dilakukan dengan membuat program
     * baru, sehingga kedua versinya tetap bisa dibandingkan.
     */
    public function update(User $user, ImpactProgram $program): bool
    {
        return $this->manage($user) && ! $program->terkunci();
    }

    public function delete(User $user, ImpactProgram $program): bool
    {
        return $this->update($user, $program);
    }

    /** Menjalankan perhitungan SROI. */
    public function hitung(User $user, ImpactProgram $program): bool
    {
        return $this->update($user, $program);
    }

    /**
     * Menyetujui hasil perhitungan sebagai Final.
     *
     * Pimpinan ATAU Admin. Pimpinan-lah yang diharapkan melakukannya; Admin
     * tetap diizinkan karena pada lembaga sekecil ini Pimpinan bisa saja
     * belum punya akun aktif, dan angka yang sudah diperiksa tidak boleh
     * tertahan hanya karena itu. Siapa yang menyetujui selalu tercatat di
     * kolom disetujui_oleh, jadi jejaknya tetap jelas.
     */
    public function setujui(User $user, ImpactProgram $program): bool
    {
        return $user->isPimpinan() || $user->isAdmin();
    }
}
