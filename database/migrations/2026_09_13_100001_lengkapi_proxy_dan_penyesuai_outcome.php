<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MELENGKAPI MASTER PROXY & DOKUMENTASI FAKTOR PENYESUAI.
 *
 * Seluruhnya kolom TAMBAHAN yang nullable — tidak ada tabel, model, maupun
 * service baru, dan tidak satu pun baris existing tersentuh. Yang ditutup di
 * sini adalah tiga celah yang sama-sama bermuara pada satu hal: angka yang
 * tidak bisa dijelaskan asalnya.
 *
 * ================================================================
 * 1. PROXY PERLU MENYEBUT OUTCOME & INDIKATORNYA
 * ================================================================
 *
 * Master proxy sudah menyimpan nilai, sumber, dan tahun. Yang belum: untuk
 * outcome apa angka itu dimaksudkan. Begitu daftarnya berisi sepuluh proxy,
 * penyusun laporan harus menebak mana yang cocok untuk "peningkatan profit"
 * dan mana untuk "penambahan tenaga kerja" — dan proxy yang dipasangkan ke
 * outcome yang salah menghasilkan nilai dampak yang keliru tanpa satu pun
 * tanda bahaya.
 *
 * ================================================================
 * 2. DISPLACEMENT: "TIDAK RELEVAN" BUKAN "NOL"
 * ================================================================
 *
 * Sebelumnya displacement hanya bisa bernilai angka, dan 0 memuat tiga arti
 * yang sangat berbeda sekaligus:
 *
 *     "sudah diperiksa, memang tidak ada perpindahan manfaat"
 *     "tidak relevan untuk outcome jenis ini"
 *     "belum pernah dipikirkan"
 *
 * Yang ketiga adalah yang berbahaya, dan justru yang paling mungkin terjadi.
 * Kolom `displacement_relevan` memisahkan ketiganya: NULL berarti belum
 * ditentukan, false berarti sudah diputuskan tidak relevan (dengan alasan
 * tertulis), true berarti relevan dan persentasenya berlaku.
 *
 * ================================================================
 * 3. ATTRIBUTION PERLU MENYEBUT SIAPA LAGI YANG BERKONTRIBUSI
 * ================================================================
 *
 * Attribution 70% berarti 30% perubahan diakui milik pihak lain. Tanpa
 * mencatat pihak lain itu siapa — peserta sendiri, keluarga, program lain,
 * kondisi pasar — angka 70 tidak bisa diperiksa siapa pun, dan pada
 * praktiknya menjadi angka yang dipilih karena terasa pantas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financial_proxies', function (Blueprint $table) {
            // Outcome & indikator yang DILAYANI proxy ini. Nullable karena
            // proxy lama sudah ada sebelum kolom ini, dan karena sebuah proxy
            // memang bisa dipakai lintas outcome.
            $table->string('outcome')->nullable()->after('nama');
            $table->string('indikator')->nullable()->after('outcome');
        });

        Schema::table('impact_outcomes', function (Blueprint $table) {
            // NULL = belum ditentukan. Inilah keadaan yang harus bisa
            // dibedakan dari "sudah diperiksa dan hasilnya nol".
            $table->boolean('displacement_relevan')->nullable()->after('displacement_persen');
            $table->text('displacement_catatan')->nullable()->after('displacement_relevan');

            // Siapa lagi yang berkontribusi atas perubahan ini.
            $table->text('attribution_catatan')->nullable()->after('attribution_persen');

            // Dasar angka deadweight — dari mana asumsinya diambil.
            $table->text('deadweight_catatan')->nullable()->after('deadweight_persen');
        });
    }

    public function down(): void
    {
        Schema::table('financial_proxies', function (Blueprint $table) {
            $table->dropColumn(['outcome', 'indikator']);
        });

        Schema::table('impact_outcomes', function (Blueprint $table) {
            $table->dropColumn([
                'displacement_relevan',
                'displacement_catatan',
                'attribution_catatan',
                'deadweight_catatan',
            ]);
        });
    }
};
