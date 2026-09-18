<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Cuma isi kalau memang masih kosong — tidak menimpa kalau user sudah isi sendiri.
        DB::table('koperasi_settings')
            ->whereNull('nama_penandatangan')
            ->orWhere('nama_penandatangan', '')
            ->update(['nama_penandatangan' => 'Rosa Linda']);

        DB::table('koperasi_settings')
            ->whereNull('jabatan_penandatangan')
            ->orWhere('jabatan_penandatangan', '')
            ->update(['jabatan_penandatangan' => 'Direktur']);
    }

    public function down(): void
    {
        // Tidak perlu dikembalikan — ini cuma nilai default awal.
    }
};
