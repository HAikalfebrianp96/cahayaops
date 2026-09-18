<?php

namespace App\Models;

use App\Models\Concerns\CatatanPeserta;
use App\Models\Concerns\MencatatPenghapus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class HppCalculation extends Model
{
    /**
     * SoftDeletes: perhitungan HPP adalah HISTORI dan sengaja tidak pernah
     * bisa diubah — koreksinya dengan membuat perhitungan baru. Yang belum
     * ada selama ini adalah cara membuang perhitungan yang salah input;
     * itulah yang ditambahkan, dan tetap tanpa menghilangkan barisnya.
     */
    use CatatanPeserta, HasFactory, MencatatPenghapus, SoftDeletes;

    protected $fillable = [
        'participant_id',
        'nama_produk',
        'satuan_produksi',
        'jumlah_produksi',
        'total_biaya_produksi',
        'hpp_per_unit',
        'harga_jual',
        'laba_per_unit',
        'margin_persen',
        'estimasi_laba_batch',
        'created_by',
    ];

    protected $casts = [
        'total_biaya_produksi' => 'decimal:2',
        'hpp_per_unit' => 'decimal:2',
        'harga_jual' => 'decimal:2',
        'laba_per_unit' => 'decimal:2',
        'margin_persen' => 'decimal:2',
        'estimasi_laba_batch' => 'decimal:2',
    ];

    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class);
    }

    public function components(): HasMany
    {
        return $this->hasMany(HppComponent::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Indikator sederhana utk pendampingan — BUKAN klaim bisnis, cuma
     * pembanding harga_jual vs hpp_per_unit apa adanya.
     */
    public function statusHarga(): string
    {
        $jual = (float) $this->harga_jual;
        $hpp = (float) $this->hpp_per_unit;

        if ($jual < $hpp) {
            return 'di_bawah';
        }

        if ($jual == $hpp) {
            return 'sama';
        }

        return 'di_atas';
    }

    // ==========================================================
    // ALAT BANTU PENDAMPINGAN (K10) — SEMUA dihitung langsung dari
    // hpp_per_unit/harga_jual yang SUDAH tersimpan (snapshot resmi).
    // TIDAK disimpan ke database — murni fungsi/simulasi sekali hitung.
    // ==========================================================

    /**
     * Contoh harga jual kalau target margin tertentu ingin dicapai.
     * Rumus: Harga = HPP / (1 - margin). Null kalau margin >= 100%
     * (matematis tidak masuk akal / division by zero).
     */
    public function hargaBerdasarkanMargin(float $targetMarginPersen): ?float
    {
        $marginDecimal = $targetMarginPersen / 100;

        if ($marginDecimal >= 1) {
            return null;
        }

        return round((float) $this->hpp_per_unit / (1 - $marginDecimal), 2);
    }

    /**
     * Simulasi skema reseller — harga reseller & retail BOLEH beda dari
     * data resmi (dipakai jg utk skenario contoh A/B/C). Aman dari
     * division by zero (margin jadi null kalau harga dasarnya 0).
     */
    public function simulasiReseller(float $hargaReseller, ?float $hargaRetail = null): array
    {
        $hpp = (float) $this->hpp_per_unit;
        $retail = $hargaRetail ?? (float) $this->harga_jual;

        $labaProdusen = round($hargaReseller - $hpp, 2);
        $marginProdusen = $hargaReseller > 0 ? round(($labaProdusen / $hargaReseller) * 100, 2) : null;

        $labaReseller = round($retail - $hargaReseller, 2);
        $marginReseller = $retail > 0 ? round(($labaReseller / $retail) * 100, 2) : null;

        return [
            'harga_reseller' => $hargaReseller,
            'harga_retail' => $retail,
            'laba_produsen' => $labaProdusen,
            'margin_produsen' => $marginProdusen,
            'laba_reseller' => $labaReseller,
            'margin_reseller' => $marginReseller,
        ];
    }

    /**
     * Catatan otomatis utk Pendamping — bahasa SENGAJA dijaga netral
     * ("secara perhitungan", "dapat dipertimbangkan"), TIDAK PERNAH klaim
     * "harga terbaik"/"pasti untung". Ambang "margin cukup tinggi" = 20%
     * (asumsi kerja sederhana, bukan standar baku — didokumentasikan di
     * laporan implementasi).
     */
    public function catatanPendamping(): string
    {
        $status = $this->statusHarga();
        $hpp = number_format((float) $this->hpp_per_unit, 0, ',', '.');
        $hargaJual = number_format((float) $this->harga_jual, 0, ',', '.');
        $laba = number_format((float) $this->laba_per_unit, 0, ',', '.');

        if ($status === 'di_bawah') {
            return "Perlu perhatian. Harga jual saat ini (Rp{$hargaJual}) berada di bawah HPP (Rp{$hpp}). Pendamping disarankan mengevaluasi biaya dan harga jual terlebih dahulu.";
        }

        if ($status === 'sama') {
            return "Usaha belum menghasilkan keuntungan pada harga jual saat ini (Rp{$hargaJual}, setara dengan HPP).";
        }

        $dasar = "Produk memiliki HPP Rp{$hpp} per unit dan harga jual Rp{$hargaJual}. Dengan harga tersebut, laba sekitar Rp{$laba} per unit.";
        $marginCukupTinggi = $this->margin_persen !== null && (float) $this->margin_persen >= 20;

        if ($marginCukupTinggi) {
            return $dasar . ' Secara perhitungan, masih terdapat ruang untuk menguji strategi reseller.';
        }

        return $dasar . ' Pendamping dapat mengevaluasi kembali HPP, harga jual, atau efisiensi biaya sebelum menggunakan strategi reseller.';
    }
}
