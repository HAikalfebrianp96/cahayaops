<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pengeluaran extends Model
{
    use HasFactory;

    protected $table = 'pengeluaran';

    protected $fillable = [
        'tanggal', 'business_unit_id', 'kategori', 'pengeluaran_category_id', 'nominal', 'metode_pembayaran',
        'keterangan', 'bukti_path', 'bukti_asli', 'status', 'created_by', 'akun_kas_id',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'nominal' => 'decimal:2',
        'dibatalkan_at' => 'datetime',
    ];

    public const STATUS_AKTIF = 'Aktif';
    public const STATUS_DIBATALKAN = 'Dibatalkan';

    // DIPERTAHANKAN — dipakai sbg fallback saran kalau suatu saat text bebas
    // masih relevan di tempat lain. Sumber utama kategori SEKARANG dari
    // PengeluaranCategory (master), lihat KategoriPengeluaranController.
    const KATEGORI_SARAN = [
        'Pembelian Barang', 'Transportasi', 'Listrik', 'Air', 'Internet', 'Kemasan',
        'Peralatan', 'Sewa', 'Gaji/Honor', 'Promosi', 'Administrasi', 'Pemeliharaan',
        'Operasional', 'Lainnya',
    ];

    const METODE_OPTIONS = ['Cash', 'Transfer', 'QRIS', 'Lainnya'];

    public function businessUnit()
    {
        return $this->belongsTo(BusinessUnit::class);
    }

    /**
     * Unit usaha pembebanan, TERMASUK yang sudah diarsipkan — laporan
     * pengeluaran tahun lalu tetap harus menyebut unitnya.
     */
    public function businessUnitTermasukArsip(): BelongsTo
    {
        return $this->belongsTo(BusinessUnit::class, 'business_unit_id')->withTrashed();
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Kategori dari MASTER (kalau ada). Transaksi lama yang belum punya
     * pengeluaran_category_id akan return null di sini — pakai kolom
     * 'kategori' (text/snapshot) sbg fallback tampilan, JANGAN kolom ini.
     */
    public function kategoriPengeluaran(): BelongsTo
    {
        return $this->belongsTo(PengeluaranCategory::class, 'pengeluaran_category_id');
    }

    public function scopeAktif($query)
    {
        return $query->where('status', 'Aktif');
    }

    /**
     * Pengeluaran aktif yang MENGURANGI laba — semua kecuali pembelian stok.
     * Biaya stok sudah masuk Laba Rugi lewat HPP saat barangnya terjual;
     * lihat config cahayaos.pengeluaran.kategori_pembelian_stok.
     */
    public function scopeBebanUsaha($query)
    {
        return $query->aktif()->whereNotIn('kategori', self::kategoriPembelianStok());
    }

    /**
     * Pengeluaran aktif berupa pembelian stok — dicatat, tidak mengurangi laba.
     */
    public function scopePembelianStok($query)
    {
        return $query->aktif()->whereIn('kategori', self::kategoriPembelianStok());
    }

    public static function kategoriPembelianStok(): array
    {
        return (array) config('cahayaos.pengeluaran.kategori_pembelian_stok', []);
    }

    public function isPembelianStok(): bool
    {
        return in_array($this->kategori, self::kategoriPembelianStok(), true);
    }

    /**
     * Siapa yang membatalkan. Kolomnya SENGAJA tidak fillable — nilainya
     * hanya boleh datang dari sesi yang sedang berjalan, tidak dari form.
     */
    public function pembatal(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibatalkan_oleh');
    }

    public function dibatalkan(): bool
    {
        return $this->status === self::STATUS_DIBATALKAN;
    }

    /**
     * Label unit untuk ditampilkan — "Operasional Koperasi" kalau tidak
     * terikat unit usaha manapun (business_unit_id null).
     */
    public function labelUnit(): string
    {
        return $this->businessUnit->nama ?? 'Operasional Koperasi';
    }

    /** Laci atau rekening tempat uang catatan ini masuk/keluar (null = belum ditentukan). */
    public function akunKas(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(AkunKas::class);
    }
}
