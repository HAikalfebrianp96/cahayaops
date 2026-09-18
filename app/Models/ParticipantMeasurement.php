<?php

namespace App\Models;

use App\Models\Concerns\CatatanPeserta;
use App\Models\Concerns\MencatatPenghapus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * SATU PENGUKURAN PERKEMBANGAN USAHA pada satu titik waktu.
 *
 * Tiga jenis memakai bentuk data yang sama persis — itulah syarat supaya
 * keduanya bisa dibandingkan sama sekali:
 *
 *   Baseline    kondisi SEBELUM didampingi. Titik nol program.
 *   Monitoring  kondisi di tengah jalan. Boleh berkali-kali.
 *   Endline     kondisi SETELAH program/periode selesai.
 *
 * Model ini sengaja TIDAK menyimpan angka perubahan. Perubahan adalah selisih
 * dua baris, dan selisih yang disimpan akan basi begitu salah satu barisnya
 * diperbaiki — sementara memperbaiki data lapangan yang salah catat adalah
 * kejadian biasa, bukan pengecualian.
 */
class ParticipantMeasurement extends Model
{
    use CatatanPeserta, HasFactory, MencatatPenghapus, SoftDeletes;

    public const BASELINE = 'Baseline';
    public const MONITORING = 'Monitoring';
    public const ENDLINE = 'Endline';

    public const JENIS_OPTIONS = [
        self::BASELINE => 'Baseline (kondisi awal)',
        self::MONITORING => 'Monitoring (kondisi berjalan)',
        self::ENDLINE => 'Endline (kondisi akhir)',
    ];

    public const PASAR_OPTIONS = [
        'Lingkungan' => 'Lingkungan sekitar',
        'Kecamatan' => 'Kecamatan',
        'Kota' => 'Kota/Kabupaten',
        'Provinsi' => 'Provinsi',
        'Nasional' => 'Nasional / luar provinsi',
    ];

    /**
     * INDIKATOR YANG DIBANDINGKAN — satu sumber kebenaran.
     *
     * Dipakai bersama oleh tampilan perbandingan, modul Impact, dan mesin
     * SROI. Menambah indikator baru cukup di sini (setelah kolomnya ada):
     * ketiganya ikut mengenalinya tanpa disentuh.
     *
     * 'arah' menyatakan arah yang DIANGGAP membaik. Tanpa itu, sistem tidak
     * bisa membedakan "biaya operasional turun" (bagus) dari "pelanggan
     * turun" (buruk), dan akan melaporkan keduanya sebagai perubahan yang
     * setara.
     */
    public const INDIKATOR = [
        'omzet_bulanan' => ['label' => 'Omzet per bulan', 'satuan' => 'Rp', 'arah' => 'naik', 'format' => 'rupiah'],
        'hpp_bulanan' => ['label' => 'HPP per bulan', 'satuan' => 'Rp', 'arah' => 'netral', 'format' => 'rupiah'],
        'biaya_operasional_bulanan' => ['label' => 'Biaya operasional per bulan', 'satuan' => 'Rp', 'arah' => 'netral', 'format' => 'rupiah'],
        'jumlah_tenaga_kerja' => ['label' => 'Tenaga kerja', 'satuan' => 'orang', 'arah' => 'naik', 'format' => 'angka'],
        'jumlah_produk' => ['label' => 'Jenis produk', 'satuan' => 'produk', 'arah' => 'naik', 'format' => 'angka'],
        'jumlah_pelanggan' => ['label' => 'Pelanggan rutin', 'satuan' => 'orang', 'arah' => 'naik', 'format' => 'angka'],
        'kapasitas_produksi' => ['label' => 'Kapasitas produksi', 'satuan' => '', 'arah' => 'naik', 'format' => 'angka'],
    ];

    /** Indikator ya/tidak — dibandingkan sebagai "belum -> sudah", bukan selisih angka. */
    public const INDIKATOR_BOOLEAN = [
        'punya_legalitas' => 'Legalitas usaha',
        'punya_pembukuan' => 'Pembukuan usaha',
        'punya_rekening_usaha' => 'Rekening usaha terpisah',
        'jualan_online' => 'Penjualan online',
    ];

    protected $fillable = [
        'participant_id',
        'jenis',
        'tanggal',
        'omzet_bulanan',
        'hpp_bulanan',
        'biaya_operasional_bulanan',
        'jumlah_tenaga_kerja',
        'jumlah_produk',
        'jumlah_pelanggan',
        'kapasitas_produksi',
        'satuan_produksi',
        'jangkauan_pasar',
        'punya_legalitas',
        'punya_pembukuan',
        'punya_rekening_usaha',
        'jualan_online',
        'catatan',
        'sumber_data',
        'dicatat_oleh',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'omzet_bulanan' => 'integer',
        'hpp_bulanan' => 'integer',
        'biaya_operasional_bulanan' => 'integer',
        'jumlah_tenaga_kerja' => 'integer',
        'jumlah_produk' => 'integer',
        'jumlah_pelanggan' => 'integer',
        'kapasitas_produksi' => 'integer',
        'punya_legalitas' => 'boolean',
        'punya_pembukuan' => 'boolean',
        'punya_rekening_usaha' => 'boolean',
        'jualan_online' => 'boolean',
    ];

    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class);
    }

    public function pencatat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dicatat_oleh');
    }

    public static function jenisYangSah(): array
    {
        return array_keys(self::JENIS_OPTIONS);
    }

    public function jenisLabel(): string
    {
        return self::JENIS_OPTIONS[$this->jenis] ?? $this->jenis;
    }

    /**
     * Profit bersih bulanan pada saat pengukuran — aturan yang SAMA PERSIS
     * dengan [OmzetReport]: HPP kosong berarti belum terdata (NULL), biaya
     * operasional kosong berarti nihil (0).
     *
     * Disamakan dengan sengaja. Kalau dua modul menghitung "profit" dengan
     * dua cara berbeda, angka di halaman Perkembangan dan di halaman Omzet
     * akan berbeda untuk usaha yang sama, dan tidak ada yang tahu mana yang
     * benar.
     */
    public function profitBulanan(): ?int
    {
        if ($this->omzet_bulanan === null || $this->hpp_bulanan === null) {
            return null;
        }

        return (int) $this->omzet_bulanan
            - (int) $this->hpp_bulanan
            - (int) ($this->biaya_operasional_bulanan ?? 0);
    }

    /** Ada isinya sama sekali? Dipakai untuk menandai pengukuran kosong. */
    public function adaIsinya(): bool
    {
        foreach (array_keys(self::INDIKATOR) as $kolom) {
            if ($this->{$kolom} !== null) {
                return true;
            }
        }

        foreach (array_keys(self::INDIKATOR_BOOLEAN) as $kolom) {
            if ($this->{$kolom} !== null) {
                return true;
            }
        }

        return $this->jangkauan_pasar !== null;
    }

    public function scopeJenis($query, string $jenis)
    {
        return $query->where('jenis', $jenis);
    }
}
