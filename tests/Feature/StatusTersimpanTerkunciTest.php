<?php

namespace Tests\Feature;

use App\Models\BusinessUnit;
use App\Models\KoperasiMember;
use App\Models\LegalitasRecord;
use App\Models\Participant;
use App\Models\ProductService;
use App\Models\ProgramInvestment;
use App\Models\TrainingRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * STATUS YANG DIPAKAI MENYARING TIDAK BOLEH BERUBAH TEKSNYA.
 *
 * =====================================================================
 * KEGAGALAN YANG DIJAGA
 * =====================================================================
 *
 * Sama bentuknya dengan jenis kegiatan, dan sama diamnya: ratusan baris di
 * database menyimpan teks status apa adanya. Begitu teks itu diganti di
 * kode, baris-baris lama tidak lagi cocok dengan penyaring mana pun —
 * hitungannya menjadi NOL, tanpa error dan tanpa halaman rusak.
 *
 * Yang paling terasa: "Sudah Ada" dipakai menyaring KPI legalitas, yang
 * MENILAI pendamping. Nol di sana terbaca sebagai "tidak ada satu pun
 * dokumen yang selesai", bukan sebagai kerusakan.
 *
 * =====================================================================
 * KENAPA TEKSNYA DITULIS ULANG DI SINI
 * =====================================================================
 *
 * Daftar STATUS_OPTIONS sekarang dibangun DARI konstantanya, jadi mengganti
 * sebuah konstanta mengubah daftar dan penyaringnya serempak — dan test
 * yang hanya memeriksa konsistensi internal akan tetap lulus dengan tenang.
 *
 * Teks di bawah sengaja diduplikasi supaya ada pihak kedua yang harus ikut
 * setuju sebelum nilai tersimpan boleh berubah. Kalau memang perlu diganti,
 * datanya harus dimigrasikan lebih dulu.
 */
class StatusTersimpanTerkunciTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_legalitas_tersimpan_tidak_berubah(): void
    {
        $this->assertSame('Belum Ada', LegalitasRecord::STATUS_BELUM_ADA);
        $this->assertSame('Proses', LegalitasRecord::STATUS_PROSES);
        $this->assertSame('Sudah Ada', LegalitasRecord::STATUS_SUDAH_ADA);
    }

    public function test_status_pelatihan_tersimpan_tidak_berubah(): void
    {
        $this->assertSame('Terdaftar', TrainingRecord::STATUS_TERDAFTAR);
        $this->assertSame('Mengikuti', TrainingRecord::STATUS_MENGIKUTI);
        $this->assertSame('Selesai', TrainingRecord::STATUS_SELESAI);
        $this->assertSame('Tidak Mengikuti', TrainingRecord::STATUS_TIDAK_MENGIKUTI);
    }

    public function test_jenis_investasi_tersimpan_tidak_berubah(): void
    {
        $this->assertSame('Tunai', ProgramInvestment::JENIS_TUNAI);
        $this->assertSame('In-kind', ProgramInvestment::JENIS_IN_KIND);
    }

    /**
     * Setiap konstanta harus benar-benar ada di daftar pilihannya.
     *
     * Menjaga arah sebaliknya: konstanta yang tidak pernah bisa tersimpan
     * karena bukan pilihan yang sah di form.
     */
    public function test_konstanta_status_ada_di_daftar_pilihan(): void
    {
        foreach ([
            LegalitasRecord::STATUS_BELUM_ADA,
            LegalitasRecord::STATUS_PROSES,
            LegalitasRecord::STATUS_SUDAH_ADA,
        ] as $status) {
            $this->assertContains($status, LegalitasRecord::STATUS_OPTIONS);
        }

        foreach ([
            TrainingRecord::STATUS_TERDAFTAR,
            TrainingRecord::STATUS_MENGIKUTI,
            TrainingRecord::STATUS_SELESAI,
            TrainingRecord::STATUS_TIDAK_MENGIKUTI,
        ] as $status) {
            $this->assertContains($status, TrainingRecord::STATUS_OPTIONS);
        }

        // JENIS_OPTIONS berbentuk nilai => label, jadi nilai tersimpannya ada
        // di KUNCI. Yang tampil ke pengguna ("In-kind (barang/jasa/waktu)")
        // boleh saja diperbaiki kapan pun; yang tidak boleh berubah kuncinya.
        $this->assertArrayHasKey(ProgramInvestment::JENIS_IN_KIND, ProgramInvestment::JENIS_OPTIONS);
        $this->assertArrayHasKey(ProgramInvestment::JENIS_TUNAI, ProgramInvestment::JENIS_OPTIONS);
    }

    /**
     * "Aktif" DIJAGA TANPA IKUT DI-REFACTOR.
     *
     * =================================================================
     * KENAPA HANYA DIJAGA, BUKAN DIGANTI KONSTANTA
     * =================================================================
     *
     * Teks "Aktif" dipakai menyaring di puluhan tempat, lintas enam model.
     * Menggantinya semua berarti menyentuh banyak berkas untuk risiko yang
     * jauh lebih kecil daripada kasus "Sudah Ada": "Aktif" adalah status
     * paling mendasar di sistem ini dan paling kecil kemungkinannya diganti.
     *
     * Perubahan besar yang nilainya kecil justru menambah risiko sendiri.
     * Jadi yang diambil hanya perlindungannya: kalau suatu saat ada yang
     * mengganti teks ini di salah satu daftar pilihan, test ini gagal lebih
     * dulu — tanpa perlu ada satu pun baris penyaring yang diubah hari ini.
     */
    public function test_status_aktif_tetap_bernama_aktif_di_seluruh_model(): void
    {
        foreach ([
            'Participant' => Participant::STATUS_BERJALAN,
            'KoperasiMember' => KoperasiMember::STATUS_OPTIONS,
            'BusinessUnit' => BusinessUnit::STATUS_OPTIONS,
            'ProductService' => ProductService::STATUS_OPTIONS,
        ] as $model => $pilihan) {
            $nilai = array_is_list($pilihan) ? $pilihan : array_keys($pilihan);

            $this->assertContains(
                'Aktif',
                $nilai,
                "{$model} tidak lagi mengenal status \"Aktif\" — puluhan penyaring "
                . 'di aplikasi ini menghitung dengan teks itu dan akan menjadi nol.'
            );
        }
    }

    /**
     * Jenis produk vs jasa juga dipakai menyaring (BusinessUnit).
     */
    public function test_jenis_produk_dan_jasa_tersimpan_tidak_berubah(): void
    {
        $this->assertContains('Produk', ProductService::JENIS_OPTIONS);
        $this->assertContains('Jasa', ProductService::JENIS_OPTIONS);
    }
}
