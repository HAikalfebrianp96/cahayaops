<?php

namespace App\Models;

use App\Models\Concerns\MencatatPenghapus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Pendamping extends Model
{
    /**
     * SoftDeletes + MencatatPenghapus.
     *
     * Sebelumnya destroy() melakukan HARD DELETE. Karena FK dari participants
     * ber-nullOnDelete, itu membuat SELURUH binaan si Pendamping kehilangan
     * pemiliknya secara permanen — tidak ada cara mengembalikannya. Dengan
     * soft delete, penghapusan tidak lagi memicu FK sama sekali: tautan
     * binaannya tetap utuh dan ikut benar lagi begitu Pendamping dipulihkan.
     */
    use HasFactory, MencatatPenghapus, SoftDeletes;

    protected $fillable = [
        'nama',
        'nomor_hp',
        'email',
        'status',
    ];

    public const STATUS_OPTIONS = ['Aktif', 'Tidak Aktif'];

    public function participants(): HasMany
    {
        return $this->hasMany(Participant::class);
    }

    /**
     * Akun login CahayaOS milik pendamping ini (kalau ada).
     * NULL kalau pendamping belum punya akun — dipakai utk status
     * "Akun Login: Aktif / Belum Ada" di halaman daftar & edit.
     */
    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }
}
