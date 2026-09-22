<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SalesTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'unit_id',
        'customer_id',
        'tanggal',
        'status_transaksi',
        'subtotal',
        'diskon',
        'ongkir',
        'ongkir_ditanggung',
        'total',
        'status_pembayaran',
        'dp_nominal', // dipakai sbg "nominal dibayar" utk semua status (Lunas/DP/Belum Dibayar)
        'metode_pembayaran',
        'metode_pengiriman',
        'nama_ekspedisi',
        'nomor_resi',
        'alamat_pengiriman',
        'hp_penerima',
        'catatan_pengiriman',
        'catatan',
        'created_by',
        // nomor_transaksi SENGAJA tidak fillable — selalu digenerate otomatis.
    ];

    protected $casts = [
        'dibatalkan_at' => 'datetime',
        'tanggal' => 'date',
        'subtotal' => 'decimal:2',
        'diskon' => 'decimal:2',
        'ongkir' => 'decimal:2',
        'total' => 'decimal:2',
        'dp_nominal' => 'decimal:2',
    ];

    public const STATUS_TRANSAKSI_OPTIONS = ['Draft', 'Aktif', 'Selesai', 'Dibatalkan'];

    /**
     * Status 'Dibatalkan' SUDAH ADA di daftar di atas sejak awal, dan seluruh
     * laporan (laba rugi, penjualan, unit usaha, dashboard) SUDAH
     * mengecualikannya lewat whereNotIn('status_transaksi', ['Draft',
     * 'Dibatalkan']). Yang selama ini tidak ada hanyalah CARA menyetelnya —
     * itulah yang ditambahkan lewat SalesTransactionController::batalkan().
     * Jadi tidak ada satu pun query laporan yang perlu diubah.
     */
    public const STATUS_DIBATALKAN = 'Dibatalkan';

    /** Siapa yang membatalkan transaksi ini — null kalau masih berlaku. */
    public function pembatal(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibatalkan_oleh');
    }
    public const STATUS_PEMBAYARAN_OPTIONS = ['Belum Dibayar', 'DP', 'Lunas'];

    // COD SEKARANG di sini (bukan di pengiriman lagi).
    public const METODE_PEMBAYARAN_OPTIONS = ['Cash', 'Transfer', 'QRIS', 'COD', 'Lainnya'];

    // COD DIHAPUS dari sini — transaksi LAMA yg masih tersimpan 'COD' di kolom ini
    // TETAP AMAN ditampilkan apa adanya (kolom VARCHAR biasa, bukan ENUM DB),
    // cuma tidak lagi jadi pilihan baru di form.
    public const METODE_PENGIRIMAN_OPTIONS = ['Ambil di Tempat', 'Diantar / Kurir', 'Ekspedisi'];

    public const ONGKIR_DITANGGUNG_OPTIONS = ['Pelanggan', 'Koperasi'];

    protected static function booted(): void
    {
        static::creating(function (SalesTransaction $trx) {
            if (empty($trx->nomor_transaksi)) {
                $periode = now()->format('Ym');

                // Dihitung dari NOMOR TERTINGGI bulan ini, bukan dari jumlah
                // baris.
                //
                // count()+1 aman hanya selama tidak ada yang pernah dihapus.
                // Sejak Hapus Permanen tersedia, syarat itu tidak berlaku:
                // menghapus satu transaksi di tengah membuat nomor berikutnya
                // menabrak nomor yang masih dipakai. Kolomnya ber-unique,
                // jadi akibatnya bukan data rusak melainkan penyimpanan
                // GAGAL — dengan galat database yang tidak bisa dimengerti
                // petugas.
                $terakhir = static::withoutGlobalScopes()
                    ->where('nomor_transaksi', 'like', 'TRX-' . $periode . '-%')
                    ->orderByDesc('nomor_transaksi')
                    ->value('nomor_transaksi');

                $urutan = $terakhir === null
                    ? 1
                    : ((int) substr($terakhir, strlen('TRX-' . $periode . '-'))) + 1;
                $trx->nomor_transaksi = 'TRX-' . $periode . '-' . str_pad((string) $urutan, 6, '0', STR_PAD_LEFT);
            }
        });

        // ==========================================================
        // PENJAGAAN: status "Lunas" WAJIB berarti terbayar penuh.
        //
        // Kalau dp_nominal (nominal dibayar) tersimpan lebih kecil dari
        // total padahal statusnya "Lunas", sisaPembayaran() ikut > 0 dan
        // INVOICE menampilkan dua hal yang bertentangan sekaligus: badge
        // "LUNAS" berdampingan dengan "Sisa Pembayaran Rp sekian". Itu
        // pernah benar-benar terjadi pada data lama (lihat migration
        // 2026_08_31_141500_perbaiki_dp_nominal_transaksi_lunas).
        //
        // Jalur input lewat form sebenarnya sudah benar
        // (SalesTransactionController::hitungDibayar), tapi penjagaan di
        // sini menutup SEMUA jalur lain yang lewat Eloquent — impor,
        // seeder, factory, atau update langsung di kode.
        //
        // SENGAJA hanya menaikkan yang KURANG (dp_nominal < total):
        // pembayaran berlebih tidak diutak-atik supaya catatannya utuh,
        // dan aturannya sama persis dengan migration perbaikan data
        // supaya tidak ada dua definisi "lunas" yang berbeda.
        //
        // CATATAN: hook Eloquent TIDAK berlaku untuk query builder
        // (DB::table()->update()). Itu memang disengaja — migration yang
        // memperbaiki data harus tetap bisa bekerja tanpa dihalangi.
        // ==========================================================
        static::saving(function (SalesTransaction $trx) {
            if ($trx->status_pembayaran !== 'Lunas') {
                return;
            }

            if ((float) $trx->dp_nominal < (float) $trx->total) {
                $trx->dp_nominal = $trx->total;
            }
        });
    }

    public function businessUnit(): BelongsTo
    {
        return $this->belongsTo(BusinessUnit::class, 'unit_id');
    }

    /**
     * Unit usaha pemilik transaksi, TERMASUK yang sudah diarsipkan.
     *
     * ==================================================================
     * KENAPA RELASI KEDUA, BUKAN MENGUBAH businessUnit()
     * ==================================================================
     *
     * BusinessUnit memakai SoftDeletes, jadi relasi biasa di atas berhenti
     * menemukan unitnya begitu unit itu diarsipkan — padahal transaksinya
     * tetap milik unit tersebut. Riwayat penjualan tidak ikut pindah hanya
     * karena unitnya berhenti beroperasi.
     *
     * Akibatnya nyata: mengarsipkan satu unit usaha membuat halaman Edit
     * SELURUH transaksi lamanya gagal dibuka. Itulah yang terjadi pada
     * Cahaya Sembako, dan gejalanya muncul jauh dari penyebabnya —
     * pengarsipan berhasil tanpa peringatan apa pun, error-nya baru
     * ketahuan berhari-hari kemudian saat ada yang membuka transaksi lama.
     *
     * businessUnit() SENGAJA dibiarkan apa adanya: daftar dan laporan yang
     * memakainya memang seharusnya berhenti menampilkan unit terarsip.
     * Yang butuh melihat ke belakang hanya halaman riwayat — dan halaman itu
     * memakai relasi ini, persis seperti pesertaTermasukArsip() pada catatan
     * peserta.
     */
    public function businessUnitTermasukArsip(): BelongsTo
    {
        return $this->belongsTo(BusinessUnit::class, 'unit_id')->withTrashed();
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Pelanggan transaksi, TERMASUK yang sudah diarsipkan.
     *
     * Invoice yang sudah dicetak menyebut nama pembelinya. Kalau pelanggan
     * itu kemudian diarsipkan, lembar yang sama tidak boleh berubah menjadi
     * "-" — tagihan tanpa nama pembeli bukan dokumen yang bisa dipakai.
     */
    public function customerTermasukArsip(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id')->withTrashed();
    }

    public function items(): HasMany
    {
        return $this->hasMany(SalesTransactionItem::class);
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    /** Jumlah yang sudah dibayar — berlaku utk semua status pembayaran. */
    public function nominalDibayar(): float
    {
        return (float) $this->dp_nominal;
    }

    public function sisaPembayaran(): float
    {
        return max(0, (float) $this->total - $this->nominalDibayar());
    }

    /** INTERNAL/ADMIN SAJA — jangan tampilkan ke customer di invoice. */
    public function totalHpp(): float
    {
        // SETIAP item punya modal, tanpa kecuali.
        //
        // Dulu item yang punya fee atau hak mitra dianggap "Titip Jual" dan
        // modalnya dikeluarkan dari sini. Akibatnya dua transaksi dengan
        // barang dan harga yang sama menghasilkan angka berbeda hanya karena
        // salah satunya diberi hak mitra — dan selisihnya tidak muncul di
        // mana pun sebagai penjelasan.
        return (float) $this->items
            ->sum(fn ($item) => $item->qty * (float) $item->harga_modal_snapshot);
    }

    /** Total nilai penjualan item yang punya hak mitra. */
    public function totalPenjualanAdaHakMitra(): float
    {
        return (float) $this->items
            ->filter(fn ($item) => (float) $item->hak_mitra_amount > 0)
            ->sum('subtotal');
    }

    public function totalFeeKoperasi(): float
    {
        return (float) $this->items->sum('fee_amount');
    }

    public function totalHakMitra(): float
    {
        return (float) $this->items->sum('hak_mitra_amount');
    }

    /**
     * "Estimasi Profit" (BUKAN Laba Bersih — Pengeluaran belum diperhitungkan).
     *
     *     Total Penjualan - Total Modal/HPP
     *
     * Hak Mitra TIDAK dikurangkan di sini. Kalau modal item itu memang sudah
     * mewakili biaya yang sama, mengurangkannya lagi membuat satu biaya
     * terhitung dua kali dan profitnya lebih kecil dari kenyataan.
     *
     * Logikanya sengaja dibuat sama persis dengan LabaRugiService.
     */
    public function estimasiProfit(): float
    {
        return (float) $this->items->sum('subtotal') - $this->totalHpp();
    }

    public function totalQty(): int
    {
        return (int) $this->items->sum('qty');
    }
}
