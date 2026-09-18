<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * Melengkapi SoftDeletes dengan SIAPA yang menghapus.
 *
 * `deleted_at` menjawab "kapan", trait ini menjawab "oleh siapa" — dua hal
 * yang sama-sama dibutuhkan Admin saat membuka Tab Terhapus dan memutuskan
 * apakah sebuah data layak dipulihkan.
 *
 * Cara pakai: `use SoftDeletes, MencatatPenghapus;` pada model yang tabelnya
 * punya kolom `deleted_by`. Tidak ada yang perlu dipanggil di controller —
 * pencatatannya menempel di event model, jadi berlaku juga untuk penghapusan
 * yang terjadi dari tinker, seeder, atau kode lain di masa depan.
 *
 * `deleted_by` SENGAJA tidak masuk $fillable di model manapun: nilainya hanya
 * boleh datang dari sesi yang sedang berjalan, tidak pernah dari form.
 */
trait MencatatPenghapus
{
    public static function bootMencatatPenghapus(): void
    {
        static::deleting(function ($model) {
            // Auth::id() null saat dijalankan dari konsol/seeder — itu wajar,
            // dan lebih jujur daripada mengarang pelaku.
            if (Auth::check()) {
                $model->deleted_by = Auth::id();
                $model->saveQuietly();
            }
        });

        static::restoring(function ($model) {
            // Setelah dipulihkan, jejak penghapusnya tidak lagi berlaku.
            // Riwayat lengkapnya tetap ada di Activity Log.
            $model->deleted_by = null;
        });
    }

    public function penghapus(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    /** Nama penghapus untuk ditampilkan di Tab Terhapus. */
    public function namaPenghapus(): string
    {
        return $this->penghapus?->name ?? 'Tidak tercatat';
    }
}
