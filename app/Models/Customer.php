<?php

namespace App\Models;

use App\Models\Concerns\MencatatPenghapus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    /**
     * SoftDeletes: sales_transactions.customer_id menunjuk ke sini, jadi
     * menghapus permanen akan melubangi riwayat penjualan dan invoice yang
     * sudah terbit.
     */
    use HasFactory, MencatatPenghapus, SoftDeletes;

    protected $fillable = [
        'nama',
        'nomor_hp',
        'alamat',
        'email',
        'catatan',
        // Tautan ke keanggotaan koperasi — dipakai pembagian SHU jasa usaha.
        'koperasi_member_id',
    ];

    public function salesTransactions(): HasMany
    {
        return $this->hasMany(SalesTransaction::class);
    }

    /**
     * Anggota koperasi di balik pelanggan ini — NULLABLE, dan itu keadaan
     * yang normal.
     *
     * Sebagian besar pembeli koperasi memang bukan anggota. Tautan ini ada
     * untuk satu hal: jasa usaha dalam pembagian SHU dibagi menurut seberapa
     * banyak ANGGOTA berbelanja di koperasinya sendiri, dan tanpa tautan ini
     * nilai transaksinya tidak bisa dikenali sebagai milik anggota. Pelanggan
     * tanpa tautan tidak ikut dalam pembagian — bukan dianggap anggota
     * dengan transaksi nol.
     */
    public function koperasiMember(): BelongsTo
    {
        return $this->belongsTo(KoperasiMember::class, 'koperasi_member_id');
    }
}
