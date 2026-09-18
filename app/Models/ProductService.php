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
