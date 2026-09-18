<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * PEMBATALAN BERJEJAK untuk catatan keuangan.
 *
 * Dipakai catatan uang yang TIDAK BOLEH dihapus tapi tetap harus bisa
 * dikoreksi: arus Dana Sosial dan Dana Hibah. Polanya sama persis dengan
 * koreksi simpanan (lihat [MemberSaving]) supaya operator tidak perlu
 * mempelajari dua mekanisme berbeda untuk hal yang sama:
 *
 *   barisnya TETAP ADA -> statusnya jadi "Dibatalkan" -> berhenti dihitung
 *   ke saldo & laporan -> alasan, waktu, dan pelakunya ikut tersimpan.
 *
 * SATU aturan penting yang menyertainya: scope berlaku() WAJIB dipakai di
 * SETIAP perhitungan saldo. Kalau satu saja terlewat, catatan yang sudah
 * dibatalkan tetap terhitung dan angka saldonya salah — dan justru
 * pembatalan itulah yang jadi sumber kesalahannya.
 */
trait BisaDibatalkan
{
    public const STATUS_TERCATAT = 'Tercatat';
    public const STATUS_DIBATALKAN = 'Dibatalkan';

    /**
     * Catatan yang masih sah dan ikut menentukan saldo.
     *
     * Ditulis sebagai "bukan Dibatalkan", bukan "sama dengan Tercatat",
     * supaya status lain yang mungkin ditambahkan kelak tetap dihitung
     * secara bawaan — kelalaian yang aman.
     */
    public function scopeBerlaku(Builder $query): Builder
    {
        return $query->where('status', '!=', self::STATUS_DIBATALKAN);
    }

    public function dibatalkan(): bool
    {
        return $this->status === self::STATUS_DIBATALKAN;
    }

    public function pembatal(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibatalkan_oleh');
    }
}
