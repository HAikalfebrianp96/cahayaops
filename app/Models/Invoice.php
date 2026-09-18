<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'sales_transaction_id',
        'tanggal_invoice',
        'catatan',
        // nomor_invoice SENGAJA tidak fillable — selalu digenerate otomatis.
    ];

    protected $casts = [
        'tanggal_invoice' => 'date',
    ];

    protected static function booted(): void
    {
        static::creating(function (Invoice $invoice) {
            if (empty($invoice->nomor_invoice)) {
                $periode = now()->format('Ym');
                $urutan = static::whereYear('created_at', now()->year)
                    ->whereMonth('created_at', now()->month)
                    ->count() + 1;
                $invoice->nomor_invoice = 'INV-' . $periode . '-' . str_pad((string) $urutan, 6, '0', STR_PAD_LEFT);
            }
        });
    }

    public function salesTransaction(): BelongsTo
    {
        return $this->belongsTo(SalesTransaction::class);
    }
}
