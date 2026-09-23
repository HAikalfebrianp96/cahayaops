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

    /**
     * Mulai kelipatan ini, HPP dianggap "jauh" di atas harga jual — lebih
     * mungkin salah ketik digit daripada keputusan menjual rugi. Diatur di
     * config/cahayaos.php ('hpp.jauh_kali'); tidak pernah kurang dari 1.
     */
    public static function ambangHppJauh(): float
    {
        return max(1.0, (float) config('cahayaos.hpp.jauh_kali', 10));
    }

    /**
     * PERINGATAN HPP TIDAK WAJAR — memperingatkan, TIDAK menolak.
     *
     * Pernah terjadi: HPP Rp9.500 terketik Rp9.500.000 pada 35 paper bag,
     * dan satu baris itu membuat Laba Rugi koperasi minus Rp332 juta. Validasi
     * lama hanya "angka, minimal 0", jadi tidak ada apa pun yang menahannya.
     *
     * Sengaja tidak menjadi galat validasi: menjual di bawah modal kadang
     * memang keputusan bisnis (cuci gudang, barang rusak, promosi). Yang
     * dicegah adalah salah ketik, bukan keputusannya.
     *
     * HPP Rp0 SELALU VALID dan tidak pernah diperingatkan: barang bisa memang
     * diperoleh gratis (kasus nyata: Kerupuk Tuiri, TRX-202609-000188 &
     * -000190). Rp0 tetap masuk HPP Terjual apa adanya; sistem tidak boleh
     * menggantinya dengan angka lain. Keterangannya cukup di Catatan item.
     *
     * Aturan yang sama dipakai form (JavaScript) dan controller — kalimatnya
     * dibentuk di sini supaya keduanya tidak berbeda.
     */
    public static function peringatanHpp(string $nama, float $hpp, float $hargaJual): ?string
    {
        if ($hpp <= $hargaJual) {
            return null;
        }

        $pesan = 'Perhatian: HPP ' . rupiah($hpp) . ' lebih besar dari harga jual '
            . rupiah($hargaJual) . ' (' . $nama . '). Periksa kembali nilai modal.';

        if ($hargaJual > 0 && $hpp >= $hargaJual * self::ambangHppJauh()) {
            $pesan .= ' HPP ini ' . number_format($hpp / $hargaJual, 0, ',', '.')
                . '× harga jual — kemungkinan salah ketik digit.';
        }

        return $pesan;
    }

    public function salesTransaction(): BelongsTo
    {
        return $this->belongsTo(SalesTransaction::class);
    }

    public function productService(): BelongsTo
    {
        return $this->belongsTo(ProductService::class, 'product_service_id');
    }
}
