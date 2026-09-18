<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * HASIL PERHITUNGAN SROI YANG DIBEKUKAN.
 *
 * Satu baris = satu kali sistem menghitung, lengkap dengan SELURUH asumsi
 * yang dipakainya saat itu (kolom `rincian`). Baris ini TIDAK pernah dihitung
 * ulang. Kalau datanya berubah, yang dibuat adalah baris BARU — sehingga
 * pertanyaan "angka apa yang kita laporkan Maret lalu, dan dari mana" selalu
 * punya jawaban.
 *
 * Ini alasan yang sama dengan snapshot harga di transaksi penjualan: master
 * boleh berubah, histori tidak.
 *
 * TIDAK memakai SoftDeletes, dan itu disengaja. Perhitungan yang keliru
 * diperbaiki dengan MENGHITUNG ULANG (lahir baris baru yang lebih baru),
 * bukan dengan menghapus jejak angka yang pernah dipakai melapor.
 */
class SroiCalculation extends Model
{
    use HasFactory;

    public const STATUS_TERHITUNG = 'Terhitung';
    public const STATUS_PERLU_REVIEW = 'Perlu Review';
    public const STATUS_FINAL = 'Final';

    public const STATUS_OPTIONS = [
        self::STATUS_TERHITUNG => 'Terhitung',
        self::STATUS_PERLU_REVIEW => 'Perlu Review',
        self::STATUS_FINAL => 'Final / Disetujui',
    ];

    protected $fillable = [
        'impact_program_id',
        'status',
        'total_investasi',
        'total_nilai_dampak',
        'nilai_sosial_bersih',
        'rasio',
        'rincian',
        'sensitivitas',
        'dihitung_oleh',
        'dihitung_at',
        'disetujui_oleh',
        'disetujui_at',
        'catatan_review',
    ];

    protected $casts = [
        'total_investasi' => 'integer',
        'total_nilai_dampak' => 'integer',
        'nilai_sosial_bersih' => 'integer',
        'rasio' => 'float',
        'rincian' => 'array',
        'sensitivitas' => 'array',
        'dihitung_at' => 'datetime',
        'disetujui_at' => 'datetime',
    ];

    public function program(): BelongsTo
    {
        return $this->belongsTo(ImpactProgram::class, 'impact_program_id');
    }

    public function penghitung(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dihitung_oleh');
    }

    public function penyetuju(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disetujui_oleh');
    }

    /** "Rp 3,21 nilai sosial per Rp 1 investasi" — kalimat, bukan angka telanjang. */
    public function rasioKalimat(): string
    {
        if ($this->rasio === null) {
            return 'Rasio tidak dapat dihitung.';
        }

        return 'Rp ' . number_format($this->rasio, 2, ',', '.') . ' nilai sosial untuk setiap Rp 1 investasi.';
    }

    public function sudahDisetujui(): bool
    {
        return $this->status === self::STATUS_FINAL && $this->disetujui_at !== null;
    }
}
