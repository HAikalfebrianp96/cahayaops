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

                // Dihitung dari NOMOR TERTINGGI bulan ini, bukan dari jumlah
                // baris.
                //
                // count()+1 aman hanya selama tidak ada yang pernah dihapus.
                // Sejak Hapus Permanen tersedia, syarat itu tidak berlaku:
                // menghapus satu transaksi di tengah membuat nomor berikutnya
                // menabrak nomor yang masih dipakai. Kolomnya ber-unique,
                // jadi akibatnya bukan data rusak melainkan penyimpanan
                // GAGAL — dengan galat database yang tidak bisa dimengerti
                // petugas.
                $terakhir = static::withoutGlobalScopes()
                    ->where('nomor_invoice', 'like', 'INV-' . $periode . '-%')
                    ->orderByDesc('nomor_invoice')
                    ->value('nomor_invoice');

                $urutan = $terakhir === null
                    ? 1
                    : ((int) substr($terakhir, strlen('INV-' . $periode . '-'))) + 1;
                $invoice->nomor_invoice = 'INV-' . $periode . '-' . str_pad((string) $urutan, 6, '0', STR_PAD_LEFT);
            }
        });
    }

    public function salesTransaction(): BelongsTo
    {
        return $this->belongsTo(SalesTransaction::class);
    }
}
