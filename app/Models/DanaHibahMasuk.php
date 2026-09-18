<?php

namespace App\Models;

use App\Models\Concerns\BisaDibatalkan;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DanaHibahMasuk extends Model
{
    use BisaDibatalkan, HasFactory;

    protected $table = 'dana_hibah_masuk';

    protected $fillable = ['tanggal', 'nominal', 'status', 'sumber', 'peruntukan', 'keterangan', 'created_by'];

    protected $casts = [
        'tanggal' => 'date',
        'nominal' => 'decimal:2',
        'dibatalkan_at' => 'datetime',
    ];
}
