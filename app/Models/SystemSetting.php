<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    protected $fillable = [
        'nama_sistem',
        'nama_lembaga',
        'logo_path',
        'logo_asli',
        'alamat',
        'kontak',
        'nama_program',
        'periode_program',
        'tahun_program',
        // Ambang batas follow up — kebijakan program yang boleh diubah Admin
        // lewat halaman Pengaturan, bukan aturan yang tertanam di kode.
        'followup_kuning_hari',
        'followup_merah_hari',
        // Target default pendamping + ambang keaktifan peserta & beban.
        'target_default_binaan',
        'target_default_kunjungan',
        'target_default_pendampingan',
        'target_default_follow_up',
        'peserta_aktif_hari',
        'peserta_perlu_follow_up_hari',
        'beban_ringan_maks',
        'beban_normal_maks',
        'beban_tinggi_maks',
    ];

    /**
     * Kunci penyimpanan sementara di container aplikasi.
     *
     * Container-lah yang dipakai, BUKAN properti statis: container dibuat
     * ulang untuk setiap permintaan HTTP dan untuk setiap test, jadi
     * simpanannya tidak pernah bocor antar permintaan maupun antar test.
     * Properti statis akan bertahan sepanjang proses dan membuat test
     * membaca baris pengaturan milik test sebelumnya.
     */
    private const KUNCI_SIMPANAN = 'cahayaos.system_setting';

    /**
     * Ambil baris pengaturan (selalu cuma 1 baris). Kalau belum ada,
     * otomatis dibuat dengan nilai default.
     *
     * ======================================================================
     * DISIMPAN SEMENTARA SELAMA SATU PERMINTAAN
     * ======================================================================
     *
     * Baris ini cuma SATU dan hampir tidak pernah berubah, tapi dibaca
     * berkali-kali dalam satu halaman: FollowUpService memanggilnya sekali
     * untuk SETIAP binaan, dan KpiService sekali untuk SETIAP bulan.
     *
     * Terukur sebelum perbaikan ini: halaman Prioritas menembak query yang
     * sama 40 kali, KPI Pendamping 27 kali, dan Tugas Saya 21 kali — seluruhnya
     * untuk satu baris yang isinya persis sama.
     *
     * Jumlahnya ikut tumbuh seiring jumlah binaan, jadi pada data hari ini
     * (21 peserta) masih terasa cepat dan tidak kelihatan. Yang melambat
     * belakangan justru halaman yang dipakai menyusun laporan.
     *
     * firstOrCreate() juga sengaja dipertahankan supaya perilakunya tidak
     * berubah: sistem yang belum punya baris pengaturan tetap membuatnya
     * sendiri, bukan meledak.
     */
    public static function current(): self
    {
        if (! app()->bound(self::KUNCI_SIMPANAN)) {
            app()->instance(self::KUNCI_SIMPANAN, static::query()->firstOrCreate([]));
        }

        return app(self::KUNCI_SIMPANAN);
    }

    /**
     * Simpanan dibuang begitu pengaturannya berubah.
     *
     * Tanpa ini, Admin yang mengubah ambang follow up lewat halaman
     * Pengaturan akan melihat angka LAMA pada halaman yang sama setelah
     * menyimpan — kekeliruan yang tampak seperti "perubahannya tidak
     * tersimpan", padahal tersimpan.
     */
    protected static function booted(): void
    {
        $lupakan = fn () => app()->forgetInstance(self::KUNCI_SIMPANAN);

        static::saved($lupakan);
        static::deleted($lupakan);
    }
}
