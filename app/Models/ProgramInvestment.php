<?php

namespace App\Models;

use App\Models\Concerns\MencatatPenghapus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * SATU POS INVESTASI PROGRAM — penyebut rasio SROI.
 *
 * Ini satu-satunya angka di seluruh perhitungan yang kalau dicatat terlalu
 * kecil akan membuat hasilnya terlihat LEBIH BAIK. Karena itu in-kind
 * (sumbangan tenaga, tempat, barang, waktu relawan) diberi tempat sendiri
 * dan bukan dianggap "bukan biaya": mengabaikannya adalah cara paling halus
 * untuk melebih-lebihkan rasio tanpa memalsukan satu angka pun.
 */
class ProgramInvestment extends Model
{
    use HasFactory, MencatatPenghapus, SoftDeletes;

    public const JENIS_TUNAI = 'Tunai';
    public const JENIS_IN_KIND = 'In-kind';

    public const JENIS_OPTIONS = [
        self::JENIS_TUNAI => 'Tunai',
        self::JENIS_IN_KIND => 'In-kind (barang/jasa/waktu)',
    ];

    /**
     * Kategori BAWAAN, bukan daftar tertutup.
     *
     * Dipakai sebagai pilihan cepat di form; kolomnya string biasa sehingga
     * kategori di luar daftar ini tetap bisa dicatat. Memaksa daftar tertutup
     * akan membuat petugas memasukkan biaya nyata ke kategori yang salah
     * hanya supaya formnya mau tersimpan.
     */
    public const KATEGORI_UMUM = [
        'Pelatihan',
        'Pendampingan',
        'Bantuan Modal',
        'Bantuan Alat',
        'Operasional Program',
        'Transportasi',
        'Administrasi',
        'Lainnya',
    ];

    protected $fillable = [
        'impact_program_id',
        'kategori',
        'uraian',
        'tanggal',
        'nominal',
        'jenis',
        'sumber_dana',
        'catatan',
        // bukti_path & bukti_asli SENGAJA tidak fillable. Keduanya hanya
        // boleh diisi alur unggah yang sudah memvalidasi tipe & ukuran
        // berkas; membuatnya fillable berarti sebuah request bisa menunjuk
        // path apa pun di disk lewat mass assignment, lalu route unduh yang
        // ber-otorisasi akan menyajikannya.
        'dicatat_oleh',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'nominal' => 'integer',
    ];

    protected $attributes = [
        'jenis' => self::JENIS_TUNAI,
    ];

    /** Ekstensi bukti yang diterima — kuitansi, nota, bukti transfer. */
    public const BUKTI_MIME = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];

    /** Batas ukuran dalam kilobyte — sama dengan bukti omzet & legalitas. */
    public const BUKTI_MAKS_KB = 5120;

    public function punyaBukti(): bool
    {
        return filled($this->bukti_path);
    }

    public function namaBukti(): string
    {
        return $this->bukti_asli ?: basename((string) $this->bukti_path);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(ImpactProgram::class, 'impact_program_id');
    }

    public function pencatat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dicatat_oleh');
    }

    public static function jenisYangSah(): array
    {
        return array_keys(self::JENIS_OPTIONS);
    }
}
