<?php

namespace App\Services;

use App\Models\OmzetReport;
use App\Models\Participant;
use App\Models\Pendamping;
use App\Models\PendampingTarget;
use App\Models\PendampinganRecord;
use App\Models\SystemSetting;
use App\Models\TrainingRecord;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * KPI KINERJA PENDAMPING.
 *
 * EMPAT KEPUTUSAN YANG MEMBENTUK SELURUH ISI KELAS INI:
 *
 * 1. REALISASI TIDAK PERNAH DISIMPAN. Semua angka aktual dihitung saat
 *    dibutuhkan dari pendampingan_records, participants, omzet_reports, dan
 *    training_records. Menyimpannya sebagai kolom berarti ia harus
 *    diperbarui setiap kali satu catatan lapangan ditambah/diubah/dihapus/
 *    dipulihkan — dan pasti akan basi begitu satu jalur lupa.
 *
 * 2. INDIKATOR TANPA TARGET TIDAK DIHITUNG. Kalau program hanya menetapkan
 *    target kunjungan, KPI-nya adalah progres kunjungan — bukan rata-rata
 *    dengan nol-nol yang tidak pernah diminta. Ini juga yang membuat
 *    pembagian dengan nol tidak mungkin terjadi.
 *
 * 3. DATA TERHAPUS TIDAK IKUT DIHITUNG. Seluruh model sumber memakai Soft
 *    Delete dengan global scope, jadi baris yang diarsipkan otomatis keluar
 *    dari perhitungan tanpa filter tambahan.
 *
 * 4. SATU QUERY AGREGAT UNTUK SEMUA PENDAMPING. Halaman KPI menampilkan
 *    seluruh pendamping sekaligus; menghitung per orang akan menghasilkan
 *    pola N+1 tepat di halaman yang paling berat.
 */
class KpiService
{
    public const SANGAT_BAIK = 'Sangat Baik';
    public const BAIK = 'Baik';
    public const PERLU_PERHATIAN = 'Perlu Perhatian';
    public const RENDAH = 'Rendah';

    /**
     * Keadaan pendamping yang targetnya BELUM PERNAH diatur.
     *
     * =====================================================================
     * KENAPA INI BUKAN SALAH SATU DARI EMPAT STATUS DI ATAS
     * =====================================================================
     *
     * Ketika seorang pendamping belum punya target individual, sistem
     * memakai target bawaan sebagai pembanding. Itu wajar sebagai ANGKA
     * ACUAN — tapi tidak wajar sebagai PENILAIAN.
     *
     * Keadaan nyata yang ditemukan: satu-satunya pendamping di sistem ini
     * tidak pernah diberi target (pendamping_targets kosong), lalu dinilai
     * "Rendah" berlatar merah dengan KPI 25% dan "3 indikator belum
     * tercapai". Orang itu membuka halaman KPI Saya dan membaca dirinya
     * gagal — atas target yang tidak pernah disepakati dengan siapa pun.
     *
     * Angkanya tetap ditampilkan, karena berguna. Yang dilepas hanya
     * vonisnya.
     */
    public const TANPA_TARGET = 'Target Belum Diatur';

    /**
     * Target default yang berlaku.
     *
     * Diambil dari SystemSetting kalau Admin sudah mengisinya, kalau belum
     * jatuh ke config/cahayaos.php. Target INDIVIDUAL selalu menang atas ini.
     */
    public function targetDefault(): array
    {
        $s = SystemSetting::current();

        return [
            'target_jumlah_binaan' => (int) ($s->target_default_binaan ?: config('cahayaos.target_default.binaan')),
            'target_kunjungan' => (int) ($s->target_default_kunjungan ?: config('cahayaos.target_default.kunjungan')),
            'target_pendampingan' => (int) ($s->target_default_pendampingan ?: config('cahayaos.target_default.pendampingan')),
            'target_follow_up' => (int) ($s->target_default_follow_up ?: config('cahayaos.target_default.follow_up')),
        ];
    }

    /** Ambang beban binaan per pendamping. */
    public function ambangBeban(): array
    {
        $s = SystemSetting::current();

        $ringan = (int) ($s->beban_ringan_maks ?: config('cahayaos.beban_pendamping.ringan_maks'));
        $normal = (int) ($s->beban_normal_maks ?: config('cahayaos.beban_pendamping.normal_maks'));
        $tinggi = (int) ($s->beban_tinggi_maks ?: config('cahayaos.beban_pendamping.tinggi_maks'));

        // Urutan dirapikan kalau Admin keliru mengisi terbalik — kalau tidak,
        // seluruh pendamping bisa jatuh ke kategori yang salah tanpa pesan.
        $normal = max($normal, $ringan + 1);
        $tinggi = max($tinggi, $normal + 1);

        return ['ringan' => $ringan, 'normal' => $normal, 'tinggi' => $tinggi];
    }

    /** Ambang keaktifan peserta (hari sejak aktivitas terakhir). */
    public function ambangKeaktifan(): array
    {
        $s = SystemSetting::current();

        $aktif = (int) ($s->peserta_aktif_hari ?: config('cahayaos.keaktifan_peserta.aktif_hari'));
        $perlu = (int) ($s->peserta_perlu_follow_up_hari ?: config('cahayaos.keaktifan_peserta.perlu_follow_up_hari'));

        if ($perlu <= $aktif) {
            $perlu = $aktif + 30;
        }

        return ['aktif' => $aktif, 'perlu_follow_up' => $perlu];
    }

    // ==========================================================
    // PERHITUNGAN KPI
    // ==========================================================

    /**
     * Rapor KPI untuk SEKUMPULAN pendamping pada satu periode.
     *
     * @param  Collection<int, Pendamping>  $pendampings
     * @return Collection<int, array> dikunci pendamping id
     */
    public function untukBanyak(Collection $pendampings, Carbon $awal, Carbon $akhir): Collection
    {
        if ($pendampings->isEmpty()) {
            return collect();
        }

        $ids = $pendampings->pluck('id');
        $realisasi = $this->realisasi($ids, $awal, $akhir);
        $default = $this->targetDefault();
        $ambangBeban = $this->ambangBeban();

        return $pendampings->mapWithKeys(function (Pendamping $p) use ($realisasi, $default, $ambangBeban, $awal) {
            $target = PendampingTarget::berlakuUntuk($p->id, $awal);
            $aktual = $realisasi[$p->id] ?? $this->realisasiKosong();

            return [$p->id => $this->hitung($p, $target, $aktual, $default, $ambangBeban)];
        });
    }

    /** Rapor KPI satu pendamping. */
    public function untukSatu(Pendamping $pendamping, Carbon $awal, Carbon $akhir): array
    {
        return $this->untukBanyak(collect([$pendamping]), $awal, $akhir)[$pendamping->id];
    }

    /**
     * Menyusun rapor dari target + realisasi.
     *
     * INTI ATURAN: hanya indikator yang PUNYA target > 0 yang dihitung.
     * Indikator tanpa target dilewati sepenuhnya — bukan dihitung 0%, karena
     * "tidak ditargetkan" berbeda dari "ditargetkan tapi gagal".
     */
    private function hitung(
        Pendamping $pendamping,
        ?PendampingTarget $target,
        array $aktual,
        array $default,
        array $ambangBeban,
    ): array {
        // Target individual menang; kalau tidak ada, pakai default sistem.
        $pakaiDefault = $target === null || ! $target->punyaTarget();

        $nilaiTarget = [];
        foreach (array_keys(PendampingTarget::INDIKATOR) as $kolom) {
            $nilaiTarget[$kolom] = $pakaiDefault
                ? (int) ($default[$kolom] ?? 0)
                : (int) ($target->{$kolom} ?? 0);
        }

        $indikator = [];
        $totalProgress = 0;
        $jumlahDihitung = 0;

        foreach (PendampingTarget::INDIKATOR as $kolom => $label) {
            $angkaTarget = $nilaiTarget[$kolom];
            $angkaAktual = (int) ($aktual[$kolom] ?? 0);

            // Target kosong / nol -> TIDAK dihitung. Ini juga yang membuat
            // pembagian dengan nol tidak pernah mungkin terjadi.
            if ($angkaTarget <= 0) {
                $indikator[$kolom] = [
                    'label' => $label,
                    'target' => null,
                    'aktual' => $angkaAktual,
                    'progress' => null,
                    'dihitung' => false,
                ];

                continue;
            }

            // Dibatasi 100%: melampaui target itu bagus, tapi membiarkannya
            // 300% akan menutupi indikator lain yang tertinggal saat dirata-rata.
            $progress = (int) min(100, round($angkaAktual / $angkaTarget * 100));

            $indikator[$kolom] = [
                'label' => $label,
                'target' => $angkaTarget,
                'aktual' => $angkaAktual,
                'progress' => $progress,
                'dihitung' => true,
            ];

            $totalProgress += $progress;
            $jumlahDihitung++;
        }

        $kpi = $jumlahDihitung > 0 ? (int) round($totalProgress / $jumlahDihitung) : null;

        return [
            'pendamping' => $pendamping,
            'target' => $target,
            'pakai_default' => $pakaiDefault,
            'indikator' => $indikator,
            'jumlah_indikator' => $jumlahDihitung,
            'kpi' => $kpi,

            // Target bawaan menghasilkan ANGKA ACUAN, bukan penilaian.
            // Pendamping yang belum pernah diberi target tidak dinyatakan
            // "Rendah" — ia dinyatakan belum punya target.
            'status' => $kpi === null
                ? null
                : ($pakaiDefault ? self::TANPA_TARGET : $this->status($kpi)),
            'warna' => $kpi === null || $pakaiDefault
                ? 'bg-gray-100 text-text-secondary'
                : $this->warna($this->status($kpi)),

            // Indikator tanpa target tidak dianggap gagal.
            'belum_tercapai' => $pakaiDefault ? 0 : collect($indikator)
                ->filter(fn ($i) => $i['dihitung'] && $i['progress'] < 100)
                ->count(),
            'beban' => $this->beban((int) ($aktual['target_jumlah_binaan'] ?? 0), $ambangBeban),
        ];
    }

    /**
     * Jumlah binaan AKTIF per pendamping — dihitung sekali per permintaan.
     *
     * Sengaja menghitung SELURUH pendamping sekaligus, bukan hanya yang
     * sedang diminta: jumlahnya kecil, satu query menjawab semua pemanggilan
     * berikutnya, dan tidak ada kunci simpanan yang perlu dijaga tetap cocok.
     *
     * @return array<int, int> [pendamping_id => jumlah]
     */
    private function jumlahBinaan(): array
    {
        $kunci = 'cahayaos.kpi.jumlah_binaan';

        if (! app()->bound($kunci)) {
            app()->instance($kunci, Participant::query()
                ->whereNotNull('pendamping_id')
                ->selectRaw('pendamping_id, COUNT(*) as jumlah')
                ->groupBy('pendamping_id')
                ->pluck('jumlah', 'pendamping_id')
                ->map(fn ($j) => (int) $j)
                ->all());
        }

        return app($kunci);
    }

    /**
     * REALISASI seluruh indikator, untuk sekumpulan pendamping sekaligus.
     *
     * Enam query agregat total — bukan enam query per pendamping.
     *
     * @param  Collection<int>  $pendampingIds
     */
    private function realisasi(Collection $pendampingIds, Carbon $awal, Carbon $akhir): array
    {
        $hasil = [];
        foreach ($pendampingIds as $id) {
            $hasil[$id] = $this->realisasiKosong();
        }

        // --- Jumlah binaan (keadaan saat ini, bukan dalam periode) ---
        //
        // Angkanya TIDAK bergantung pada periode, jadi hasilnya sama persis
        // untuk setiap bulan yang dihitung. Halaman KPI Pendamping memanggil
        // realisasi() tiga belas kali (dua belas bulan + periode berjalan),
        // dan sebelum disimpan sementara, query ini ikut berjalan tiga belas
        // kali untuk jawaban yang identik.
        //
        // Disimpan di container, bukan properti statis — container dibuat
        // ulang tiap permintaan dan tiap test, jadi hitungannya tidak pernah
        // bocor ke permintaan lain yang datanya sudah berubah.
        foreach ($this->jumlahBinaan() as $pendampingId => $jumlah) {
            if (array_key_exists($pendampingId, $hasil)) {
                $hasil[$pendampingId]['target_jumlah_binaan'] = $jumlah;
            }
        }

        // --- Aktivitas pendampingan dalam periode, dipecah per jenis ---
        PendampinganRecord::whereIn('pendamping_id', $pendampingIds)
            ->whereBetween('tanggal', [$awal->toDateString(), $akhir->toDateString()])
            ->selectRaw('pendamping_id, jenis_kegiatan, COUNT(*) as jumlah')
            ->groupBy('pendamping_id', 'jenis_kegiatan')
            ->get()
            ->each(function ($b) use (&$hasil) {
                $hasil[$b->pendamping_id]['target_pendampingan'] += (int) $b->jumlah;

                if ($b->jenis_kegiatan === PendampinganRecord::KUNJUNGAN_USAHA) {
                    $hasil[$b->pendamping_id]['target_kunjungan'] += (int) $b->jumlah;
                }
            });

        // --- Follow up: catatan yang PUNYA rencana tindak lanjut berjadwal ---
        PendampinganRecord::whereIn('pendamping_id', $pendampingIds)
            ->whereBetween('tanggal', [$awal->toDateString(), $akhir->toDateString()])
            ->whereNotNull('tanggal_follow_up')
            ->selectRaw('pendamping_id, COUNT(*) as jumlah')
            ->groupBy('pendamping_id')
            ->get()
            ->each(function ($b) use (&$hasil) {
                $hasil[$b->pendamping_id]['target_follow_up'] = (int) $b->jumlah;
            });

        // --- Peserta binaan yang laporan omzetnya masuk dalam periode ---
        // Dihitung per PESERTA UNIK, bukan per laporan: targetnya memang
        // "berapa peserta yang omzetnya terinput", bukan berapa barisnya.
        $binaan = Participant::whereIn('pendamping_id', $pendampingIds)
            ->select('id', 'pendamping_id')
            ->get()
            ->groupBy('pendamping_id')
            ->map(fn ($g) => $g->pluck('id'));

        foreach ($binaan as $pendampingId => $ids) {
            $hasil[$pendampingId]['target_input_omzet'] = OmzetReport::whereIn('participant_id', $ids)
                ->whereBetween('periode_tahun', [$awal->year, $akhir->year])
                ->get()
                ->filter(function ($r) use ($awal, $akhir) {
                    $periode = Carbon::create($r->periode_tahun, $r->periode_bulan, 1);

                    return $periode->betweenIncluded($awal->copy()->startOfMonth(), $akhir);
                })
                ->pluck('participant_id')
                ->unique()
                ->count();

            $hasil[$pendampingId]['target_pelatihan'] = TrainingRecord::whereIn('participant_id', $ids)
                ->whereBetween('tanggal', [$awal->toDateString(), $akhir->toDateString()])
                ->count();

            $hasil[$pendampingId]['target_legalitas'] = \App\Models\LegalitasRecord::whereIn('participant_id', $ids)
                ->where('status', \App\Models\LegalitasRecord::STATUS_SUDAH_ADA)
                ->count();

            // Perkembangan peserta = binaan yang masih berjalan DAN sudah
            // pernah didampingi. Sengaja sederhana dan bisa dijelaskan.
            $hasil[$pendampingId]['target_perkembangan_peserta'] = Participant::whereIn('id', $ids)
                ->whereIn('status', Participant::STATUS_BERJALAN)
                ->whereHas('pendampinganRecords')
                ->count();
        }

        return $hasil;
    }

    private function realisasiKosong(): array
    {
        return array_fill_keys(array_keys(PendampingTarget::INDIKATOR), 0);
    }

    // ==========================================================
    // STATUS & KATEGORI
    // ==========================================================

    public function status(int $kpi): string
    {
        return match (true) {
            $kpi >= 90 => self::SANGAT_BAIK,
            $kpi >= 75 => self::BAIK,
            $kpi >= 50 => self::PERLU_PERHATIAN,
            default => self::RENDAH,
        };
    }

    public function warna(string $status): string
    {
        return match ($status) {
            self::SANGAT_BAIK => 'bg-primary-light text-status-success',
            self::BAIK => 'bg-surface-bg text-status-info',
            self::PERLU_PERHATIAN => 'bg-gold-light text-status-warning',
            default => 'bg-status-danger/10 text-status-danger',
        };
    }

    /** Kategori beban binaan + warnanya. */
    public function beban(int $jumlahBinaan, ?array $ambang = null): array
    {
        $ambang ??= $this->ambangBeban();

        $kategori = match (true) {
            $jumlahBinaan <= $ambang['ringan'] => 'Ringan',
            $jumlahBinaan <= $ambang['normal'] => 'Normal',
            $jumlahBinaan <= $ambang['tinggi'] => 'Tinggi',
            default => 'Sangat Tinggi',
        };

        return [
            'jumlah' => $jumlahBinaan,
            'kategori' => $kategori,
            'warna' => match ($kategori) {
                'Ringan' => 'bg-surface-bg text-text-secondary',
                'Normal' => 'bg-primary-light text-status-success',
                'Tinggi' => 'bg-gold-light text-status-warning',
                default => 'bg-status-danger/10 text-status-danger',
            },
        ];
    }

    /**
     * KEAKTIFAN PESERTA — Aktif / Perlu Follow Up / Tidak Aktif.
     *
     * Terpisah dari status follow up pendamping ([FollowUpService]): yang itu
     * mengukur kinerja pendamping, yang ini kesehatan peserta.
     */
    public function keaktifanPeserta(?Carbon $aktivitasTerakhir): array
    {
        $ambang = $this->ambangKeaktifan();

        if ($aktivitasTerakhir === null) {
            return [
                'status' => 'Tidak Aktif',
                'hari' => null,
                'warna' => 'bg-status-danger/10 text-status-danger',
                'alasan' => 'Belum pernah ada aktivitas pendampingan.',
            ];
        }

        $hari = (int) $aktivitasTerakhir->startOfDay()->diffInDays(now()->startOfDay());

        $status = match (true) {
            $hari <= $ambang['aktif'] => 'Aktif',
            $hari <= $ambang['perlu_follow_up'] => 'Perlu Follow Up',
            default => 'Tidak Aktif',
        };

        return [
            'status' => $status,
            'hari' => $hari,
            'warna' => match ($status) {
                'Aktif' => 'bg-primary-light text-status-success',
                'Perlu Follow Up' => 'bg-gold-light text-status-warning',
                default => 'bg-status-danger/10 text-status-danger',
            },
            'alasan' => 'Aktivitas terakhir ' . $hari . ' hari lalu (ambang aktif '
                . $ambang['aktif'] . ' hari, tidak aktif di atas ' . $ambang['perlu_follow_up'] . ' hari).',
        ];
    }

    // ==========================================================
    // RINGKASAN untuk Dashboard Admin
    // ==========================================================

    /**
     * @param  Collection<int, array>  $rapor hasil untukBanyak()
     */
    public function ringkasan(Collection $rapor): array
    {
        $berkpi = $rapor->filter(fn ($r) => $r['kpi'] !== null);

        // Rata-rata HANYA dari pendamping yang benar-benar punya target.
        //
        // Merata-ratakan angka yang diukur terhadap target bawaan
        // menghasilkan satu angka yang terlihat resmi padahal tidak ada
        // seorang pun yang pernah menyepakati pembandingnya. Pada keadaan
        // sekarang — nol target tersimpan — angka itu muncul sebagai
        // "Rata-rata KPI 25%" dan dibaca sebagai penilaian.
        $bertarget = $berkpi->where('status', '!=', self::TANPA_TARGET);

        return [
            'total_pendamping' => $rapor->count(),
            'sangat_baik' => $berkpi->where('status', self::SANGAT_BAIK)->count(),
            'baik' => $berkpi->where('status', self::BAIK)->count(),
            'perlu_perhatian' => $berkpi->where('status', self::PERLU_PERHATIAN)->count(),
            'rendah' => $berkpi->where('status', self::RENDAH)->count(),
            'tanpa_target' => $berkpi->where('status', self::TANPA_TARGET)->count(),
            'rata_rata' => $bertarget->isNotEmpty() ? (int) round($bertarget->avg('kpi')) : null,
            'top' => $bertarget->sortByDesc('kpi')->take(5)->values(),
            'perlu_diperhatikan' => $berkpi
                ->filter(fn ($r) => in_array($r['status'], [self::PERLU_PERHATIAN, self::RENDAH], true))
                ->sortBy('kpi')
                ->values(),
        ];
    }
}
