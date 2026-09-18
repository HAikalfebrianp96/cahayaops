<?php

namespace App\Models;

use App\Models\Concerns\MencatatPenghapus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * SATU PENETAPAN SHU untuk satu tahun buku.
 *
 * Komponen pembagiannya disimpan PER PERIODE, bukan sebagai pengaturan global
 * — karena pembagian SHU ditetapkan Rapat Anggota dan bisa berbeda tiap
 * tahun. Penetapan tahun lalu harus tetap memperlihatkan persentase yang
 * berlaku waktu itu, meski aturannya sudah diubah untuk tahun ini.
 */
class ShuPeriod extends Model
{
    use HasFactory, MencatatPenghapus, SoftDeletes;

    public const SUMBER_LABA_RUGI = 'laba_rugi';
    public const SUMBER_MANUAL = 'manual';

    public const SUMBER_OPTIONS = [
        self::SUMBER_LABA_RUGI => 'Dari Laba Rugi koperasi tahun berjalan',
        self::SUMBER_MANUAL => 'Manual — angka keputusan rapat',
    ];

    public const STATUS_DRAFT = 'Draft';
    public const STATUS_FINAL = 'Final';

    public const DASAR_SIMPANAN = 'simpanan';
    public const DASAR_TRANSAKSI = 'transaksi';

    /**
     * Komponen USULAN untuk penetapan baru — bukan aturan yang dipaksakan.
     *
     * Dipakai hanya sebagai isian awal form; yang tersimpan adalah apa pun
     * yang akhirnya diketik petugas. Angka-angka di bawah adalah pola yang
     * lazim pada koperasi Indonesia, TAPI setiap koperasi menetapkannya
     * sendiri lewat Rapat Anggota — jadi ia hadir sebagai titik awal yang
     * menghemat pengetikan, bukan sebagai nilai yang dianggap benar.
     */
    public const KOMPONEN_USULAN = [
        ['nama' => 'Cadangan Koperasi', 'persen' => 25, 'ke_anggota' => false, 'dasar' => null],
        ['nama' => 'Jasa Modal (Simpanan Anggota)', 'persen' => 25, 'ke_anggota' => true, 'dasar' => self::DASAR_SIMPANAN],
        ['nama' => 'Jasa Usaha (Transaksi Anggota)', 'persen' => 25, 'ke_anggota' => true, 'dasar' => self::DASAR_TRANSAKSI],
        ['nama' => 'Dana Pengurus', 'persen' => 10, 'ke_anggota' => false, 'dasar' => null],
        ['nama' => 'Dana Pendidikan', 'persen' => 5, 'ke_anggota' => false, 'dasar' => null],
        ['nama' => 'Dana Sosial', 'persen' => 5, 'ke_anggota' => false, 'dasar' => null],
        ['nama' => 'Dana Pembangunan Daerah Kerja', 'persen' => 5, 'ke_anggota' => false, 'dasar' => null],
    ];

    protected $fillable = [
        'tahun',
        'tanggal_penetapan',
        'sumber',
        'total_shu',
        'komponen',
        'status',
        'catatan',
        'ditetapkan_oleh',
    ];

    protected $casts = [
        'tahun' => 'integer',
        'tanggal_penetapan' => 'date',
        'total_shu' => 'integer',
        'komponen' => 'array',
    ];

    protected $attributes = [
        'status' => self::STATUS_DRAFT,
        'sumber' => self::SUMBER_LABA_RUGI,
    ];

    public function allocations(): HasMany
    {
        return $this->hasMany(ShuAllocation::class)->orderByDesc('total');
    }

    public function penetap(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ditetapkan_oleh');
    }

    /**
     * Penetapan untuk satu tahun, TERMASUK yang sudah diarsipkan.
     *
     * Unique index pada kolom tahun tidak mempedulikan `deleted_at`, jadi
     * baris terarsip tetap memblokir tahun yang sama. Pola dan alasannya
     * sama persis dengan OmzetReport::untukPeriode — tanpa withTrashed(),
     * tahun yang penetapannya pernah dihapus tidak akan pernah bisa
     * ditetapkan lagi, dan kegagalannya muncul sebagai error database.
     */
    public static function untukTahun(int $tahun): ?self
    {
        return static::withTrashed()->where('tahun', $tahun)->first();
    }

    public function terkunci(): bool
    {
        return $this->status === self::STATUS_FINAL;
    }

    /**
     * Komponen yang dibagikan ke anggota, menurut dasar tertentu.
     *
     * KUNCINYA SENGAJA DIPERTAHANKAN (tidak dipakaikan array_values):
     * pemanggilnya perlu tahu posisi asli tiap komponen untuk mengambil
     * nominalnya dari nominalKomponenSemua(), yang dikunci posisi.
     */
    public function komponenAnggota(string $dasar): array
    {
        return array_filter(
            $this->komponen ?? [],
            fn ($k) => ! empty($k['ke_anggota']) && ($k['dasar'] ?? null) === $dasar
        );
    }

    /** Total persen seluruh komponen — dipakai memperingatkan kalau bukan 100. */
    public function totalPersen(): float
    {
        return round(array_sum(array_column($this->komponen ?? [], 'persen')), 2);
    }

    /**
     * NOMINAL SELURUH KOMPONEN — dihitung sekaligus supaya tidak ada rupiah
     * yang tercecer atau terbit dari ketiadaan.
     *
     * =================================================================
     * KENAPA TIDAK SATU-SATU
     * =================================================================
     *
     * Dulu tiap komponen dihitung sendiri: round(total_shu * persen / 100).
     * Karena tiap hasil dibulatkan terpisah, jumlahnya meleset dari total —
     * dan melesetnya ke dua arah:
     *
     *   total_shu     10.000.001 -> jumlah komponen     10.000.000  (-1)
     *   total_shu    999.999.999 -> jumlah komponen  1.000.000.000  (+1)
     *
     * Yang kedua berarti pembagian melebihi SHU yang benar-benar ada.
     *
     * =================================================================
     * YANG DIPAKAI SEBAGAI SASARAN
     * =================================================================
     *
     * Sasarannya BUKAN selalu total_shu, melainkan total_shu x totalPersen.
     * Persentase komponen ditetapkan Rapat Anggota dan tidak selalu berjumlah
     * 100% — kalau baru 95%, sisanya memang sengaja tidak dialokasikan, dan
     * memaksanya menjadi 100% berarti membagikan uang yang tidak diputuskan
     * siapa pun. Peringatan soal itu sudah ada di ShuService::peringatan().
     *
     * Saat persentasenya tepat 100%, sasarannya sama persis dengan total_shu.
     *
     * @return array<int, int> nominal rupiah, dikunci posisi komponen
     */
    public function nominalKomponenSemua(): array
    {
        $komponen = $this->komponen ?? [];

        if ($komponen === []) {
            return [];
        }

        $persen = array_map(fn ($k) => (float) ($k['persen'] ?? 0), $komponen);
        $totalPersen = array_sum($persen);

        $sasaran = (int) round(((int) $this->total_shu) * $totalPersen / 100);

        if ($sasaran <= 0 || $totalPersen <= 0) {
            return array_map(fn () => 0, $komponen);
        }

        // Metode sisa terbesar: dibulatkan ke bawah dulu supaya tidak pernah
        // melebihi sasaran, lalu sisanya dibagikan satu rupiah per komponen
        // mulai dari yang pecahannya terbesar.
        $hasil = [];
        $pecahan = [];
        $terbagi = 0;

        foreach ($persen as $i => $p) {
            $tepat = $sasaran * $p / $totalPersen;
            $bulat = (int) floor($tepat);

            $hasil[$i] = $bulat;
            $pecahan[$i] = $tepat - $bulat;
            $terbagi += $bulat;
        }

        $sisa = $sasaran - $terbagi;

        arsort($pecahan);

        foreach (array_keys($pecahan) as $i) {
            if ($sisa <= 0) {
                break;
            }

            $hasil[$i]++;
            $sisa--;
        }

        ksort($hasil);

        return $hasil;
    }

    /**
     * Nominal satu komponen menurut posisinya.
     *
     * Memakai posisi, bukan isi komponennya: dua komponen bisa saja persis
     * sama isinya, dan mencocokkan berdasarkan isi akan mengembalikan nominal
     * yang keliru untuk salah satunya.
     */
    public function nominalKomponenKe(int $posisi): int
    {
        return $this->nominalKomponenSemua()[$posisi] ?? 0;
    }

    /** Bagian SHU yang benar-benar sampai ke anggota. */
    public function totalKeAnggota(): int
    {
        return (int) $this->allocations()->sum('total');
    }
}
