<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesTransactionItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'sales_transaction_id',
        'product_service_id',
        'nama_snapshot',
        'jenis_snapshot',
        'qty',
        'harga_jual_snapshot',
        'harga_modal_snapshot',
        'diskon',
        'fee_rate',
        'fee_amount',
        'hak_mitra_amount',
        'catatan',
        'subtotal',
    ];

    protected $casts = [
        'harga_jual_snapshot' => 'decimal:2',
        'harga_modal_snapshot' => 'decimal:2',
        'diskon' => 'decimal:2',
        'fee_rate' => 'decimal:2',
        'fee_amount' => 'decimal:2',
        'hak_mitra_amount' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    public function salesTransaction(): BelongsTo
    {
        return $this->belongsTo(SalesTransaction::class);
    }

    public function productService(): BelongsTo
    {
        return $this->belongsTo(ProductService::class, 'product_service_id');
    }
}
