<?php

namespace App\Models;

use App\Models\Concerns\BisaDibatalkan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * SATU KALI UANG DITERIMA dari sebuah transaksi penjualan — DP, cicilan,
 * atau pelunasan — lengkap dengan tanggal dan akun tempat uangnya masuk.
 *
 * Tidak pernah diedit: koreksi dilakukan dengan membatalkan baris ini
 * (beserta alasannya) lalu mencatat pembayaran baru, sama seperti koreksi
 * simpanan anggota.
 *
 * SalesTransaction::dp_nominal adalah RINGKASANNYA — jumlah pembayaran yang
 * berlaku — dan hanya diperbarui lewat PembayaranPenjualanService.
 */
class PembayaranPenjualan extends Model
{
    use BisaDibatalkan;

    protected $table = 'pembayaran_penjualan';

    protected $fillable = [
        'sales_transaction_id',
        'tanggal',
        'nominal',
        'akun_kas_id',
        'metode',
        'catatan',
        // status, alasan_pembatalan, dibatalkan_*, created_by SENGAJA tidak
        // fillable — hanya diisi oleh service dari sesi yang sedang berjalan.
    ];

    protected $casts = [
        'tanggal' => 'date',
        'nominal' => 'decimal:2',
        'dibatalkan_at' => 'datetime',
    ];

    public function transaksi(): BelongsTo
    {
        return $this->belongsTo(SalesTransaction::class, 'sales_transaction_id');
    }

    public function akunKas(): BelongsTo
    {
        return $this->belongsTo(AkunKas::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Pembayaran yang benar-benar menambah uang di kas/bank: masih berlaku
     * DAN transaksinya tidak dibatalkan.
     *
     * Transaksi yang dibatalkan tidak mengubah baris pembayarannya — cukup
     * berhenti dihitung di sini. Begitu transaksinya dipulihkan, uangnya
     * otomatis terhitung lagi tanpa ada yang perlu disinkronkan.
     *
     * Transaksi Draft TETAP dihitung: laporan penjualan memang mengabaikan
     * Draft, tapi DP yang sudah diterima untuk pesanan Draft adalah uang
     * yang sungguh ada di laci.
     */
    public function scopeMasukKas(Builder $query): Builder
    {
        return $query->berlaku()->whereHas(
            'transaksi',
            fn ($q) => $q->where('status_transaksi', '!=', SalesTransaction::STATUS_DIBATALKAN)
        );
    }
}
