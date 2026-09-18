<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * BAGIAN SHU SATU ANGGOTA pada satu penetapan.
 *
 * dasar_simpanan dan dasar_transaksi DISIMPAN, bukan dihitung ulang saat
 * ditampilkan — dan itu justru yang penting. Saldo simpanan dan nilai
 * transaksi anggota bergerak setiap hari; kalau alokasinya dihitung dari data
 * hari ini, pembagian yang sudah disahkan Rapat Anggota akan berubah sendiri
 * beberapa minggu kemudian, dan tidak ada yang bisa menjelaskan selisihnya.
 *
 * Tidak memakai SoftDeletes: alokasi tidak pernah dihapus satu per satu.
 * Yang bisa diarsipkan adalah penetapannya, dan alokasi ikut terhapus
 * bersamanya lewat cascade.
 */
class ShuAllocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'shu_period_id',
        'koperasi_member_id',
        'dasar_simpanan',
        'dasar_transaksi',
        'jasa_modal',
        'jasa_usaha',
        'total',
    ];

    protected $casts = [
        'dasar_simpanan' => 'integer',
        'dasar_transaksi' => 'integer',
        'jasa_modal' => 'integer',
        'jasa_usaha' => 'integer',
        'total' => 'integer',
    ];

    public function period(): BelongsTo
    {
        return $this->belongsTo(ShuPeriod::class, 'shu_period_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(KoperasiMember::class, 'koperasi_member_id');
    }
}
