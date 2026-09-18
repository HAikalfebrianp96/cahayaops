<?php

namespace App\Services;

use App\Models\TrainingRecord;
use App\Models\LegalitasRecord;
use App\Models\PendampinganRecord;
use App\Models\Participant;

/**
 * MONITORING PERKEMBANGAN PESERTA.
 *
 * Menjawab satu pertanyaan: "peserta ini sedang di mana, dan kenapa?"
 *
 * TIGA HAL YANG SENGAJA TIDAK DILAKUKAN:
 *
 * 1. TIDAK ADA SKOR TERSEMBUNYI. Setiap status selalu disertai daftar alasan
 *    dalam kalimat biasa. Angka yang tidak bisa dijelaskan ke pendamping di
 *    lapangan tidak berguna — ia hanya memindahkan tanggung jawab menilai
 *    dari manusia ke rumus yang tidak bisa dibantah.
 *
 * 2. TIDAK ADA KOLOM BARU. Seluruhnya dihitung dari relasi yang sudah ada.
 *    Menyimpan status sebagai kolom berarti ia harus diperbarui setiap kali
 *    salah satu dari enam sumbernya berubah, dan pasti akan basi.
 *
 * 3. TIDAK MENGHUKUM DATA YANG WAJAR KOSONG. Tidak semua UMKM butuh BPOM
 *    atau Sertifikat Halal. Legalitas dihitung dari dokumen yang MEMANG
 *    didaftarkan untuk peserta itu, bukan dari daftar wajib bikinan sistem —
 *    kalau tidak, warung kelontong akan selamanya terlihat "belum lengkap".
 */
class PerkembanganService
{
    public const BELUM_BERKEMBANG = 'Belum Berkembang';
    public const PERLU_PENDAMPINGAN = 'Perlu Pendampingan';
    public const STABIL = 'Stabil';
    public const BERKEMBANG = 'Berkembang';

    public function __construct(private FollowUpService $followUp)
    {
    }

    /**
     * Rapor lengkap satu peserta.
     *
     * Peserta HARUS sudah memuat relasi omzetReports, pendampinganRecords,
     * trainingRecords, legalitasRecords, dan koperasiMember — pemanggilnya
     * yang bertanggung jawab, supaya halaman daftar tidak menembak query
     * per baris.
     */
    public function rapor(Participant $peserta): array
    {
        $kelengkapan = $this->kelengkapanData($peserta);
        $kapasitas = $this->kapasitas($peserta);
        $usaha = $this->perkembanganUsaha($peserta);
        $legalitas = $this->legalitas($peserta);
        $koperasi = $this->statusKoperasi($peserta);

        $terakhir = $peserta->pendampinganRecords->sortByDesc('tanggal')->first();
        $followUp = $this->followUp->untukPeserta($peserta, $terakhir);

        [$status, $alasan] = $this->simpulkan($kelengkapan, $kapasitas, $usaha, $followUp);

        return [
            'status' => $status,
            'warna' => $this->warna($status),
            'alasan' => $alasan,
            'kelengkapan' => $kelengkapan,
            'kapasitas' => $kapasitas,
            'usaha' => $usaha,
            'legalitas' => $legalitas,
            'koperasi' => $koperasi,
            'follow_up' => $followUp,
        ];
    }

    // ==========================================================
    // A. KELENGKAPAN DATA
    // ==========================================================

    /**
     * Enam hal yang dibutuhkan pendamping untuk bisa bekerja di lapangan.
     *
     * Sengaja tidak memasukkan field opsional (email, NIK, tahun berdiri):
     * kelengkapan harus berarti "cukup untuk dikunjungi dan didampingi",
     * bukan "seluruh kolom terisi".
     */
    public function kelengkapanData(Participant $peserta): array
    {
        $wajib = [
            'Nama peserta' => filled($peserta->nama_peserta),
            'Nomor HP' => filled($peserta->nomor_hp),
            'Alamat' => filled($peserta->alamat),
            'Nama usaha' => filled($peserta->nama_usaha),
            'Jenis usaha' => filled($peserta->kategori_usaha),
            'Pendamping' => $peserta->pendamping_id !== null,
        ];

        $terisi = count(array_filter($wajib));
        $total = count($wajib);

        return [
            'rincian' => $wajib,
            'terisi' => $terisi,
            'total' => $total,
            'persen' => $total > 0 ? (int) round($terisi / $total * 100) : 0,
            'kurang' => array_keys(array_filter($wajib, fn ($ada) => ! $ada)),
            'lengkap' => $terisi === $total,
        ];
    }

    // ==========================================================
    // B. PENGEMBANGAN KAPASITAS
    // ==========================================================

    public function kapasitas(Participant $peserta): array
    {
        $catatan = $peserta->pendampinganRecords;

        $hitung = fn (string $jenis) => $catatan->where('jenis_kegiatan', $jenis)->count();

        return [
            'pelatihan' => $peserta->trainingRecords->count(),
            'pelatihan_selesai' => $peserta->trainingRecords
                ->where('status', TrainingRecord::STATUS_SELESAI)->count(),
            'pendampingan' => $catatan->count(),
            'kunjungan' => $hitung(PendampinganRecord::KUNJUNGAN_USAHA),
            'konsultasi' => $hitung(PendampinganRecord::KONSULTASI),
            'evaluasi' => $hitung(PendampinganRecord::EVALUASI),
        ];
    }

    // ==========================================================
    // C. PERKEMBANGAN USAHA
    // ==========================================================

    /**
     * Pertumbuhan omzet dari DUA periode terakhir yang benar-benar dilaporkan.
     *
     * Sengaja membandingkan dua laporan berurutan, bukan "bulan ini vs bulan
     * lalu": peserta sering melewatkan satu bulan, dan membandingkan dengan
     * bulan kosong akan menghasilkan penurunan 100% yang tidak pernah terjadi.
     */
    public function perkembanganUsaha(Participant $peserta): array
    {
        $laporan = $peserta->omzetReports
            ->sortByDesc(fn ($r) => $r->periode_tahun * 100 + $r->periode_bulan)
            ->values();

        $terbaru = $laporan->first();
        $sebelumnya = $laporan->get(1);

        $pertumbuhan = null;
        if ($terbaru && $sebelumnya && (float) $sebelumnya->omzet > 0) {
            $pertumbuhan = round(
                ((float) $terbaru->omzet - (float) $sebelumnya->omzet) / (float) $sebelumnya->omzet * 100,
                1
            );
        }

        // "Belum update omzet" = tidak ada laporan untuk bulan berjalan.
        $bulanIni = $peserta->omzetReports
            ->firstWhere(fn ($r) => $r->periode_bulan === now()->month && $r->periode_tahun === now()->year);

        return [
            'jumlah_laporan' => $laporan->count(),
            'omzet_terbaru' => $terbaru ? (int) $terbaru->omzet : null,
            'periode_terbaru' => $terbaru?->periodeLabel(),
            'omzet_sebelumnya' => $sebelumnya ? (int) $sebelumnya->omzet : null,
            'pertumbuhan_persen' => $pertumbuhan,
            'sudah_lapor_bulan_ini' => $bulanIni !== null,
            'rata_rata' => $laporan->isNotEmpty() ? (int) $laporan->avg('omzet') : null,
        ];
    }

    // ==========================================================
    // D. LEGALITAS
    // ==========================================================

    /**
     * Hanya menghitung dokumen yang MEMANG didaftarkan untuk peserta ini.
     *
     * Tidak ada daftar wajib bikinan sistem: UMKM jasa tidak butuh PIRT,
     * warung kelontong tidak butuh BPOM. Menilai mereka dengan daftar yang
     * sama akan membuat statusnya selamanya merah tanpa ada yang salah.
     */
    public function legalitas(Participant $peserta): array
    {
        $dokumen = $peserta->legalitasRecords;

        $sudah = $dokumen->where('status', LegalitasRecord::STATUS_SUDAH_ADA);

        return [
            'terdaftar' => $dokumen->count(),
            'sudah_ada' => $sudah->count(),
            'belum' => $dokumen->count() - $sudah->count(),
            'daftar' => $dokumen->map(fn ($d) => [
                'jenis' => $d->jenis_dokumen,
                'status' => $d->status,
            ])->values()->all(),
            // Tidak ada dokumen terdaftar BUKAN berarti bermasalah — bisa jadi
            // memang belum relevan bagi usaha ini.
            'relevan' => $dokumen->isNotEmpty(),
        ];
    }

    // ==========================================================
    // E. STATUS KOPERASI
    // ==========================================================

    /**
     * SENGAJA tidak ikut menentukan status perkembangan Cahaya Preneur.
     *
     * Keanggotaan koperasi bersifat opsional. Peserta yang memilih tidak
     * menjadi anggota tidak sedang gagal berkembang — kedua sistem
     * terintegrasi tapi tetap berdiri sendiri.
     */
    public function statusKoperasi(Participant $peserta): array
    {
        $anggota = $peserta->koperasiMember;

        return [
            'anggota' => $anggota !== null,
            'nomor_anggota' => $anggota?->nomor_anggota,
            'status' => $anggota?->status,
            'tanggal_bergabung' => $anggota?->tanggal_bergabung,
            'label' => $anggota
                ? 'Anggota koperasi (' . $anggota->status . ')'
                : 'Belum menjadi anggota',
        ];
    }

    // ==========================================================
    // KESIMPULAN — transparan, selalu disertai alasan
    // ==========================================================

    private function simpulkan(array $kelengkapan, array $kapasitas, array $usaha, array $followUp): array
    {
        $alasan = [];

        // --- hal yang membuat peserta perlu diperhatikan ---
        if ($followUp['status'] === FollowUpService::MERAH) {
            $alasan[] = $followUp['alasan'];
        }

        if (! $kelengkapan['lengkap']) {
            $alasan[] = 'Data belum lengkap: ' . implode(', ', $kelengkapan['kurang']) . '.';
        }

        if ($usaha['jumlah_laporan'] === 0) {
            $alasan[] = 'Belum pernah melaporkan omzet.';
        } elseif (! $usaha['sudah_lapor_bulan_ini']) {
            $alasan[] = 'Belum memperbarui omzet bulan ini.';
        }

        if ($kapasitas['pendampingan'] === 0) {
            $alasan[] = 'Belum ada catatan pendampingan.';
        }

        if ($usaha['pertumbuhan_persen'] !== null && $usaha['pertumbuhan_persen'] < 0) {
            $alasan[] = 'Omzet turun ' . abs($usaha['pertumbuhan_persen']) . '% dari periode sebelumnya.';
        }

        // --- penetapan status ---
        // Urutannya sengaja dari yang paling perlu ditindaklanjuti.
        $status = match (true) {
            // Nyaris tidak ada jejak sama sekali.
            $kapasitas['pendampingan'] === 0 && $usaha['jumlah_laporan'] === 0
                => self::BELUM_BERKEMBANG,

            // Ada yang perlu dikerjakan pendamping.
            $followUp['status'] === FollowUpService::MERAH
                || ! $kelengkapan['lengkap']
                || ($usaha['pertumbuhan_persen'] !== null && $usaha['pertumbuhan_persen'] < 0)
                => self::PERLU_PENDAMPINGAN,

            // Tumbuh, dan pendampingannya berjalan.
            $usaha['pertumbuhan_persen'] !== null && $usaha['pertumbuhan_persen'] > 0
                => self::BERKEMBANG,

            default => self::STABIL,
        };

        if ($status === self::BERKEMBANG) {
            array_unshift($alasan, 'Omzet naik ' . $usaha['pertumbuhan_persen'] . '% dari periode sebelumnya.');
        }

        if (empty($alasan)) {
            $alasan[] = 'Data lengkap, pendampingan berjalan, dan omzet terlaporkan.';
        }

        return [$status, $alasan];
    }

    public function warna(string $status): string
    {
        return match ($status) {
            self::BERKEMBANG => 'bg-primary-light text-status-success',
            self::STABIL => 'bg-surface-bg text-status-info',
            self::PERLU_PENDAMPINGAN => 'bg-gold-light text-status-warning',
            default => 'bg-gray-100 text-text-secondary',
        };
    }
}
