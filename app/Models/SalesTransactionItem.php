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

    /**
     * KETERANGAN HPP Rp0 — HPP Rp0 tetap VALID (lihat peringatanHpp()); ini
     * hanya pengingat agar alasannya tercatat. Tidak pernah mengubah data.
     *
     * - Catatan item sudah diisi (mis. "Barang diperoleh gratis") -> diam.
     * - Produk master punya harga modal > 0 tapi snapshot Rp0 -> 'peringatan':
     *   untuk produk biasa ini tidak lazim, periksa bila bukan barang gratis.
     * - Selain itu -> 'info': wajar untuk barang gratis, sarankan mengisi
     *   Catatan item supaya pembaca laporan tahu alasannya.
     *
     * @return array{tingkat: 'info'|'peringatan', pesan: string}|null
     */
    public function keteranganHppNol(): ?array
    {
        if ((float) $this->harga_modal_snapshot != 0.0 || (float) $this->harga_jual_snapshot <= 0 || filled($this->catatan)) {
            return null;
        }

        $modalMaster = (float) ($this->productService?->harga_modal ?? 0);
        if ($modalMaster > 0) {
            return [
                'tingkat' => 'peringatan',
                'pesan' => 'HPP Rp0, padahal harga modal di master produk ' . rupiah($modalMaster)
                    . '. Wajar bila barangnya gratis — bila tidak, periksa kembali nilai modal.',
            ];
        }

        return [
            'tingkat' => 'info',
            'pesan' => 'HPP Rp0 — wajar bila barang diperoleh gratis. Tambahkan alasannya di Catatan item agar jelas.',
        ];
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
