<?php

namespace App\Models;

use App\Models\Concerns\MencatatPenghapus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BusinessUnit extends Model
{
    /**
     * SoftDeletes: unit usaha adalah data MASTER — produk/jasa, penjualan,
     * pengeluaran, dan keanggotaan menunjuk ke sini. Dihapus permanen berarti
     * memutus seluruh riwayat itu, jadi penghapusannya cukup ditandai.
     */
    use HasFactory, MencatatPenghapus, SoftDeletes;

    protected $fillable = [
        'nama',
        'kode',
        'jenis_usaha',
        'tipe',
        'deskripsi',
        'alamat',
        'nomor_kontak',
        'status',
        'tanggal_mulai',
        'catatan',
    ];

    protected $casts = [
        'tanggal_mulai' => 'date',
    ];

    public const TIPE_OPTIONS = ['Barang', 'Jasa', 'Campuran'];
    public const STATUS_OPTIONS = ['Aktif', 'Tidak Aktif'];

    public function productsServices(): HasMany
    {
        return $this->hasMany(ProductService::class, 'unit_id');
    }

    /** Anggota koperasi yang terdaftar di unit usaha ini. */
    public function koperasiMembers(): HasMany
    {
        return $this->hasMany(KoperasiMember::class, 'unit_usaha_id');
    }

    public function totalProdukJasa(): int
    {
        return $this->productsServices()->count();
    }

    public function totalProdukAktif(): int
    {
        return $this->productsServices()->where('jenis', 'Produk')->where('status', 'Aktif')->count();
    }

    public function totalJasaAktif(): int
    {
        return $this->productsServices()->where('jenis', 'Jasa')->where('status', 'Aktif')->count();
    }
}
