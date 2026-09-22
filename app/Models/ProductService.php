<?php

namespace App\Models;

use App\Models\Concerns\MencatatPenghapus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductService extends Model
{
    /**
     * SoftDeletes: produk/jasa dirujuk sales_transaction_items, jadi
     * menghapusnya permanen akan melubangi riwayat penjualan.
     */
    use HasFactory, MencatatPenghapus, SoftDeletes;

    protected $table = 'products_services';

    protected $fillable = [
        'unit_id',
        'kode',
        'nama',
        'jenis',
        'kategori',
        'deskripsi',
        'satuan',
        'harga_modal',
        'harga_jual',
        'status',
        'stock_tracking',
    ];

    protected $casts = [
        'harga_modal' => 'decimal:2',
        'harga_jual' => 'decimal:2',
        'stock_tracking' => 'boolean',
    ];

    /**
     * Awalan kode produk, DIAMBIL DARI DATA — bukan dari contoh di brief.
     *
     * =====================================================================
     * BUKTINYA
     * =====================================================================
     *
     * Audit seluruh produk menemukan tiga keluarga kode:
     *
     *   QP260826B3FF   1 produk, unit Sembako  -> artefak jalur cepat
     *                  (QP + tanggal + 4 huruf acak), bukan pola pilihan
     *                  manusia.
     *   CPA-B0201..03  3 produk, Cahaya Mart   -> DIKETIK PETUGAS,
     *                  18 September pukul 13:43-13:44.
     *   PRD-000001..   dibuat generator versi pertama mulai pukul 13:46.
     *
     * Jarak dua menit itu yang menentukan: CPA-B bukan kode warisan,
     * melainkan pola yang sedang dipakai petugas — dan form berhenti
     * menerimanya justru ketika kodenya dibuat otomatis.
     *
     * =====================================================================
     * LEBARNYA IKUT DATA
     * =====================================================================
     *
     * Nomor 0201, 0202, 0203 -> empat digit. Kode berikutnya CPA-B0204.
     *
     * Yang TIDAK bisa dipastikan dari tiga contoh: apakah "B02" tetap, atau
     * menandai sesuatu (rak, batch, kategori). Ketiganya berasal dari unit
     * dan kategori yang sama persis, jadi datanya tidak bisa membedakan.
     * Kalau kelak ternyata "B02" harus berubah per kategori, yang perlu
     * diubah cuma dua konstanta di bawah.
     */
    public const AWALAN_KODE = 'CPA-B';

    /** Lebar nomor urut, mengikuti 0201/0202/0203 pada data. */
    public const LEBAR_NOMOR = 4;

    /**
     * KODE PRODUK DIBUAT SISTEM, BUKAN DIKETIK PENGGUNA.
     *
     * =====================================================================
     * KENAPA DI MODEL, BUKAN DI CONTROLLER
     * =====================================================================
     *
     * Produk bisa lahir dari lebih dari satu jalur: form Tambah Produk, dan
     * jalur cepat "buat produk baru" di tengah pengisian transaksi
     * penjualan. Sebelumnya hanya jalur kedua yang membuatkan kodenya
     * sendiri; jalur pertama menyuruh petugas mengetiknya.
     *
     * Kalau pembuatan kode ditaruh di controller, setiap jalur baru menuntut
     * orang mengingat tempat ini — dan yang terlupa tidak menimbulkan error,
     * hanya produk tanpa kode yang menumpuk diam-diam.
     *
     * =====================================================================
     * BERURUT, TAPI DIHITUNG DARI KODE TERBESAR
     * =====================================================================
     *
     * Nomornya diambil dari kode PRD- tertinggi yang pernah ada, BUKAN dari
     * jumlah baris. Menghitung baris membuat kode terpakai ulang begitu ada
     * produk yang dihapus — dan kode yang pernah beredar di nota tidak boleh
     * menunjuk barang yang berbeda.
     *
     * withTrashed() ikut dihitung karena alasan yang sama: produk yang
     * diarsipkan masih dirujuk riwayat penjualan.
     *
     * Kode yang SUDAH ADA tidak pernah diubah — termasuk kode lama berpola
     * lain. Yang dibuatkan hanya yang kosong.
     */
    protected static function booted(): void
    {
        static::creating(function (self $produk) {
            if (blank($produk->kode)) {
                $produk->kode = static::kodeBerikutnya();
            }
        });
    }

    public static function kodeBerikutnya(): string
    {
        $terakhir = static::withTrashed()
            ->where('kode', 'like', self::AWALAN_KODE . '%')
            ->orderByDesc('kode')
            ->value('kode');

        $urutan = $terakhir === null
            ? 1
            : ((int) substr($terakhir, strlen(self::AWALAN_KODE))) + 1;

        return self::AWALAN_KODE . str_pad((string) $urutan, self::LEBAR_NOMOR, '0', STR_PAD_LEFT);
    }

    public const JENIS_OPTIONS = ['Produk', 'Jasa'];
    public const STATUS_OPTIONS = ['Aktif', 'Tidak Aktif'];

    // Cuma saran/autocomplete, TIDAK mengunci pilihan — admin boleh ketik kategori lain.
    public const KATEGORI_SARAN = [
        'Makanan',
        'Minuman',
        'Fashion',
        'Sablon',
        'Percetakan',
        'Jasa',
        'Lainnya',
    ];

    public function businessUnit(): BelongsTo
    {
        return $this->belongsTo(BusinessUnit::class, 'unit_id');
    }
}
