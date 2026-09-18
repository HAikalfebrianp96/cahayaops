<?php

namespace App\Models;

use App\Models\Concerns\MencatatPenghapus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PengeluaranCategory extends Model
{
    /**
     * SoftDeletes: kategori dirujuk tabel pengeluaran. Menonaktifkan
     * (status) TETAP cara yang benar untuk kategori yang sekadar tidak
     * dipakai lagi — penghapusan disediakan untuk kategori yang memang
     * salah dibuat.
     */
    use HasFactory, MencatatPenghapus, SoftDeletes;

    protected $table = 'pengeluaran_categories';

    protected $fillable = ['nama', 'status'];

    public const STATUS_OPTIONS = ['Aktif', 'Tidak Aktif'];

    public function pengeluaran(): HasMany
    {
        return $this->hasMany(Pengeluaran::class, 'pengeluaran_category_id');
    }

    public function scopeAktif($query)
    {
        return $query->where('status', 'Aktif');
    }
}
