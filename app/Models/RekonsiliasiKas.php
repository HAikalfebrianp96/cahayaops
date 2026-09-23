<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu kali hitung fisik: saldo menurut sistem (dibekukan) dibandingkan
 * dengan uang yang sungguh ada. Tidak diedit dan tidak mengubah saldo —
 * ia jejak pemeriksaan, bukan transaksi.
 */
class RekonsiliasiKas extends Model
{
    protected $table = 'rekonsiliasi_kas';

    protected $fillable = ['tanggal', 'saldo_sistem', 'saldo_fisik', 'selisih', 'catatan'];

    protected $casts = [
        'tanggal' => 'date',
        'saldo_sistem' => 'decimal:2',
        'saldo_fisik' => 'decimal:2',
        'selisih' => 'decimal:2',
    ];

    public function akunKas(): BelongsTo
    {
        return $this->belongsTo(AkunKas::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function cocok(): bool
    {
        return (float) $this->selisih == 0.0;
    }
}
