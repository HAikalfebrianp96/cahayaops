<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Tindakan terhadap AKUN LOGIN (User) yang dipakai lebih dari satu tempat.
 *
 * Dibuat supaya tidak ada dua versi logic yang sama:
 *   - Manajemen User            -> reset password akun mana pun
 *   - Halaman Pendamping (lama) -> reset password akun Pendamping
 * Keduanya kini memanggil method yang SAMA di sini. Perilakunya dipertahankan
 * persis seperti implementasi lama di PendampingController: Str::password(12),
 * hashing lewat cast 'hashed' di model User, dan Activity Log yang TIDAK
 * PERNAH memuat passwordnya.
 */
class UserAccountService
{
    /** Panjang password sementara — nilai yang sudah dipakai sejak Tahap 2. */
    private const PANJANG_PASSWORD = 12;

    /**
     * Buat password sementara baru untuk sebuah akun.
     *
     * Password lama LANGSUNG tidak berlaku (ditimpa, bukan ditambah). Nilai
     * yang dikembalikan adalah password polos untuk ditampilkan SEKALI kepada
     * Admin agar bisa diteruskan ke pemiliknya — nilai itu tidak disimpan di
     * mana pun selain sebagai hash di kolom password.
     *
     * @param  string  $konteks  Keterangan singkat untuk Activity Log
     *                           (mis. 'Pendamping' / 'Manajemen User').
     */
    public function resetPassword(User $user, string $konteks): string
    {
        $passwordBaru = Str::password(self::PANJANG_PASSWORD);

        // Di-hash otomatis lewat cast 'hashed' di model User —
        // JANGAN bungkus Hash::make() lagi (akan jadi hash ganda).
        $user->password = $passwordBaru;
        $user->save();

        // HANYA mencatat BAHWA password di-reset. Password lama maupun baru
        // TIDAK PERNAH ikut tercatat di log manapun.
        ActivityLog::record(
            action: 'password_reset',
            modelType: 'User',
            modelId: $user->id,
            description: 'Reset password oleh Admin (' . $konteks . '): ' . $user->name,
        );

        return $passwordBaru;
    }
}
