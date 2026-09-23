<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * KAS & BANK — TAHAP 2: RIWAYAT PEMBAYARAN PENJUALAN.
 *
 * Sebelumnya uang yang diterima dari penjualan hanya tercatat sebagai SATU
 * angka (sales_transactions.dp_nominal) yang ditimpa setiap kali transaksi
 * diedit. Pelunasan DP tidak punya tanggalnya sendiri, cicilan tidak bisa
 * dibedakan, dan tidak diketahui uangnya masuk ke laci atau ke rekening.
 *
 * Satu baris di sini = satu kali uang diterima. dp_nominal TETAP ADA sebagai
 * ringkasan (jumlah pembayaran yang berlaku), supaya invoice, laba rugi,
 * laporan, dan SHU yang membacanya tidak perlu diubah.
 *
 * ==========================================================================
 * DATA LAMA
 * ==========================================================================
 *
 * Setiap transaksi lama dengan dp_nominal > 0 dibuatkan SATU baris
 * pembayaran bertanggal sama dengan transaksinya — satu-satunya tanggal yang
 * memang diketahui. Akunnya ditebak dari metode pembayaran: Transfer/QRIS ke
 * akun Bank aktif pertama, selain itu ke akun Kas aktif pertama.
 *
 * Nominalnya DIBATASI setinggi total transaksi. Pembayaran yang tercatat
 * lebih besar dari total (mis. total Rp19.000 tercatat dibayar Rp20.000)
 * adalah uang yang diserahkan pelanggan, bukan uang yang tinggal di
 * koperasi — selisihnya kembalian. Selisih itu disebut di catatan
 * pembayarannya, lalu dp_nominal transaksi tersebut diturunkan ke total.
 *
 * Aman dijalankan ulang: transaksi yang sudah punya baris pembayaran
 * dilewati.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pembayaran_penjualan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_transaction_id')->constrained('sales_transactions')->cascadeOnDelete();
            $table->date('tanggal');
            $table->decimal('nominal', 15, 2);
            $table->foreignId('akun_kas_id')->nullable()->constrained('akun_kas')->nullOnDelete();
            $table->string('metode')->nullable(); // Cash | Transfer | QRIS | COD | Lainnya
            $table->text('catatan')->nullable();

            // Pola pembatalan yang sama dengan Dana Sosial & Hibah (BisaDibatalkan).
            $table->string('status')->default('Tercatat');
            $table->text('alasan_pembatalan')->nullable();
            $table->timestamp('dibatalkan_at')->nullable();
            $table->foreignId('dibatalkan_oleh')->nullable()->constrained('users')->nullOnDelete();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['sales_transaction_id', 'status']);
            $table->index(['akun_kas_id', 'tanggal']);
        });

        $this->pindahkanPembayaranLama();
    }

    private function pindahkanPembayaranLama(): void
    {
        $akunKas = DB::table('akun_kas')->where('jenis', 'Kas')->where('status', 'Aktif')->orderBy('id')->value('id');
        $akunBank = DB::table('akun_kas')->where('jenis', 'Bank')->where('status', 'Aktif')->orderBy('id')->value('id');

        DB::table('sales_transactions')
            ->where('dp_nominal', '>', 0)
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('pembayaran_penjualan')
                    ->whereColumn('pembayaran_penjualan.sales_transaction_id', 'sales_transactions.id');
            })
            ->orderBy('id')
            ->chunkById(200, function ($transaksi) use ($akunKas, $akunBank) {
                foreach ($transaksi as $trx) {
                    $dibayar = (float) $trx->dp_nominal;
                    $total = (float) $trx->total;
                    $nominal = min($dibayar, $total);

                    if ($nominal <= 0) {
                        continue;
                    }

                    $catatan = 'Dipindahkan dari catatan transaksi lama (Kas & Bank Tahap 2).';
                    if ($dibayar > $total) {
                        $catatan .= ' Tercatat dibayar Rp' . number_format($dibayar, 0, ',', '.')
                            . '; selisih Rp' . number_format($dibayar - $total, 0, ',', '.')
                            . ' dianggap kembalian.';
                    }

                    DB::table('pembayaran_penjualan')->insert([
                        'sales_transaction_id' => $trx->id,
                        'tanggal' => $trx->tanggal,
                        'nominal' => $nominal,
                        'akun_kas_id' => in_array($trx->metode_pembayaran, ['Transfer', 'QRIS'], true) ? $akunBank : $akunKas,
                        'metode' => $trx->metode_pembayaran,
                        'catatan' => $catatan,
                        'status' => 'Tercatat',
                        'created_by' => $trx->created_by,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    if ($dibayar > $total) {
                        DB::table('sales_transactions')->where('id', $trx->id)->update(['dp_nominal' => $total]);
                    }
                }
            });
    }

    /**
     * Tabelnya dibuang. Penurunan dp_nominal pada transaksi lebih bayar
     * SENGAJA tidak dikembalikan — nilai lamanya tercatat di kolom catatan
     * pembayaran yang ikut terbuang, jadi kembalikan dari backup database
     * yang dibuat sebelum migration ini bila memang diperlukan.
     */
    public function down(): void
    {
        Schema::dropIfExists('pembayaran_penjualan');
    }
};
