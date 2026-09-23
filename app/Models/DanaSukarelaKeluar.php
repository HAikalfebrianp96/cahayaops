<?php

namespace App\Models;

use App\Models\Concerns\BisaDibatalkan;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DanaSukarelaKeluar extends Model
{
    use BisaDibatalkan, HasFactory;

    protected $table = 'dana_sukarela_keluar';

    protected $fillable = ['tanggal', 'nominal', 'status', 'keperluan', 'keterangan', 'created_by', 'akun_kas_id'];

    protected $casts = [
        'tanggal' => 'date',
        'nominal' => 'decimal:2',
        'dibatalkan_at' => 'datetime',
    ];

    /** Laci atau rekening tempat uang catatan ini masuk/keluar (null = belum ditentukan). */
    public function akunKas(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(AkunKas::class);
    }
}
