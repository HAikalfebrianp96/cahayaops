<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HppComponent extends Model
{
    use HasFactory;

    protected $fillable = [
        'hpp_calculation_id',
        'kategori',
        'nama_biaya',
        'qty',
        'satuan',
        'harga_satuan',
        'total',
    ];

    protected $casts = [
        'qty' => 'decimal:2',
        'harga_satuan' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public const KATEGORI_OPTIONS = [
        'Bahan Baku',
        'Kemasan',
        'Tenaga Kerja',
        'Biaya Produksi',
        'Biaya Lainnya',
    ];

    public function hppCalculation(): BelongsTo
    {
        return $this->belongsTo(HppCalculation::class);
    }
}
