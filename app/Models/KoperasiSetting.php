<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KoperasiSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'nama', 'subtitle', 'tagline',
        'logo_koperasi', 'logo_cahayaos', 'logo_ybm_pln',
        'ttd_koperasi', 'nama_penandatangan', 'jabatan_penandatangan', 'stempel_koperasi',
        'prefix_invoice', 'footer_invoice',
    ];

    /** Selalu ada SATU baris pengaturan saja (singleton). */
    public static function current(): self
    {
        return static::firstOrCreate(['id' => 1]);
    }

    private function urlAtauNull(?string $filename): ?string
    {
        return $filename ? asset('images/koperasi/' . $filename) : null;
    }

    public function logoKoperasiUrl(): ?string
    {
        return $this->urlAtauNull($this->logo_koperasi);
    }

    public function logoCahayaosUrl(): ?string
    {
        return $this->urlAtauNull($this->logo_cahayaos);
    }

    public function logoYbmPlnUrl(): ?string
    {
        return $this->urlAtauNull($this->logo_ybm_pln);
    }

    public function ttdKoperasiUrl(): ?string
    {
        return $this->urlAtauNull($this->ttd_koperasi);
    }

    public function stempelKoperasiUrl(): ?string
    {
        return $this->urlAtauNull($this->stempel_koperasi);
    }
}
