<?php

namespace App\Models;

use App\Models\Concerns\MencatatPenghapus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * SATU OUTCOME — perubahan yang diklaim terjadi karena program.
 *
 * Empat faktor penyesuai di sini adalah inti kejujuran SROI. Tanpa keempatnya
 * setiap perubahan akan diklaim seratus persen milik program, padahal:
 *
 *   Deadweight    berapa persen perubahan itu TETAP terjadi tanpa program
 *   Attribution   berapa persen yang benar-benar milik program ini, bukan
 *                 pihak lain yang juga mendampingi peserta yang sama
 *   Displacement  berapa persen perubahan ini hanya memindahkan manfaat dari
 *                 pihak lain (pelanggan pindah dari warung sebelah)
 *   Drop-off      berapa persen manfaatnya luruh tiap tahun berikutnya
 *
 * Disimpan sebagai persen 0-100, sama dengan yang diketik petugas dan sama
 * dengan yang tertulis di laporan. Pembagian 100-nya dilakukan HANYA di
 * SroiService, supaya tidak ada dua tempat yang bisa salah membaginya.
 */
class ImpactOutcome extends Model
{
    use HasFactory, MencatatPenghapus, SoftDeletes;

    public const SUMBER_OTOMATIS = 'otomatis';
    public const SUMBER_MANUAL = 'manual';

    public const SUMBER_OPTIONS = [
        self::SUMBER_OTOMATIS => 'Otomatis — dari baseline & endline peserta',
        self::SUMBER_MANUAL => 'Manual — angka dari luar sistem',
    ];

    protected $fillable = [
        'impact_program_id',
        'nama',
        'deskripsi',
        'sumber_nilai',
        'indikator',
        'satuan',
        'nilai_perubahan_manual',
        'jumlah_penerima_manual',
        'financial_proxy_id',
        'deadweight_persen',
        'deadweight_catatan',
        'attribution_persen',
        'attribution_catatan',
        'displacement_persen',
        'displacement_relevan',
        'displacement_catatan',
        'drop_off_persen',
        'durasi_tahun',
        'bukti',
        'catatan',
        'dicatat_oleh',
    ];

    protected $casts = [
        'nilai_perubahan_manual' => 'integer',
        'jumlah_penerima_manual' => 'integer',
        'deadweight_persen' => 'float',
        'attribution_persen' => 'float',
        'displacement_persen' => 'float',
        'displacement_relevan' => 'boolean',
        'drop_off_persen' => 'float',
        'durasi_tahun' => 'integer',
    ];

    protected $attributes = [
        'sumber_nilai' => self::SUMBER_OTOMATIS,
        'deadweight_persen' => 0,
        'attribution_persen' => 100,
        'displacement_persen' => 0,
        'drop_off_persen' => 0,
        'durasi_tahun' => 1,
    ];

    public function program(): BelongsTo
    {
        return $this->belongsTo(ImpactProgram::class, 'impact_program_id');
    }

    public function proxy(): BelongsTo
    {
        return $this->belongsTo(FinancialProxy::class, 'financial_proxy_id');
    }

    public function pencatat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dicatat_oleh');
    }

    public function otomatis(): bool
    {
        return $this->sumber_nilai === self::SUMBER_OTOMATIS;
    }

    /** Label indikator dari master pengukuran — "Omzet per bulan", dst. */
    public function indikatorLabel(): ?string
    {
        if (! $this->indikator) {
            return null;
        }

        if (isset(ParticipantMeasurement::INDIKATOR[$this->indikator])) {
            return ParticipantMeasurement::INDIKATOR[$this->indikator]['label'];
        }

        return ParticipantMeasurement::INDIKATOR_BOOLEAN[$this->indikator] ?? $this->indikator;
    }

    /**
     * Indikator yang BOLEH dipilih untuk outcome otomatis.
     *
     * Indikator ber-arah 'netral' (HPP, biaya operasional) SENGAJA tidak ikut:
     * naik-turunnya tidak punya arti baik/buruk yang tunggal, jadi mengalikan
     * perubahannya dengan proxy finansial akan menghasilkan "nilai sosial"
     * yang tidak berarti apa-apa. Profit dan omzet tetap boleh, karena
     * arahnya jelas.
     */
    public static function indikatorPilihan(): array
    {
        $pilihan = [];

        foreach (ParticipantMeasurement::INDIKATOR as $kunci => $meta) {
            if (($meta['arah'] ?? 'netral') === 'netral') {
                continue;
            }

            $pilihan[$kunci] = $meta['label'] . ' (' . ($meta['satuan'] ?: 'satuan') . ')';
        }

        $pilihan['profit_bulanan'] = 'Profit bersih per bulan (Rp)';

        foreach (ParticipantMeasurement::INDIKATOR_BOOLEAN as $kunci => $label) {
            $pilihan[$kunci] = $label . ' (jumlah peserta yang tercapai)';
        }

        return $pilihan;
    }

    public static function sumberYangSah(): array
    {
        return array_keys(self::SUMBER_OPTIONS);
    }

    /**
     * Displacement yang BERLAKU untuk perhitungan.
     *
     * Mengembalikan 0 kalau outcome ini sudah dinyatakan tidak relevan
     * terhadap displacement — dan itu sah, karena keputusannya tercatat
     * beserta alasannya. Yang TIDAK sah adalah 0 yang muncul karena tidak
     * pernah dipikirkan; keadaan itu kini punya penandanya sendiri
     * (displacement_relevan = null) dan memicu peringatan di SroiService.
     */
    public function displacementBerlaku(): float
    {
        return $this->displacement_relevan === false
            ? 0.0
            : (float) $this->displacement_persen;
    }

    /** Sudah diputuskan tidak relevan, lengkap dengan alasannya. */
    public function displacementTidakRelevan(): bool
    {
        return $this->displacement_relevan === false;
    }

    /** Belum ditentukan sama sekali — bukan "nol", melainkan belum dipikirkan. */
    public function displacementBelumDitentukan(): bool
    {
        return $this->displacement_relevan === null;
    }

    /**
     * Drop-off hanya berarti untuk outcome yang manfaatnya lebih dari satu
     * tahun. Untuk durasi satu tahun ia tidak pernah terpakai sama sekali
     * (lihat SroiService: tahun pertama tidak diluruhkan), jadi menampilkan
     * angkanya hanya membuat orang mengira ia berpengaruh.
     */
    public function dropOffRelevan(): bool
    {
        return (int) $this->durasi_tahun > 1;
    }

    /**
     * Apakah seluruh faktor penyesuai masih bernilai bawaan?
     *
     * Bawaannya adalah asumsi paling menguntungkan (deadweight 0, attribution
     * 100), jadi outcome yang belum disentuh akan menghasilkan nilai dampak
     * setinggi mungkin. Halaman SROI menandainya supaya tidak lolos begitu
     * saja ke laporan sebagai "hasil perhitungan".
     */
    public function penyesuaiMasihBawaan(): bool
    {
        return (float) $this->deadweight_persen === 0.0
            && (float) $this->attribution_persen === 100.0
            && (float) $this->displacement_persen === 0.0;
    }
}
