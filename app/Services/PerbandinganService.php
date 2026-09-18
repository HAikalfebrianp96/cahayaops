<?php

namespace App\Services;

use App\Models\Participant;
use App\Models\ParticipantMeasurement;
use Illuminate\Support\Collection;

/**
 * BASELINE vs ENDLINE — menjawab "berubah apa, sebanyak apa".
 *
 * Ini satu-satunya tempat di CahayaOS yang boleh menyatakan sebuah perubahan
 * terjadi. Modul Impact dan mesin SROI mengambil angkanya dari sini, bukan
 * menghitung ulang sendiri — supaya angka yang muncul di halaman peserta dan
 * angka yang masuk ke laporan dampak tidak mungkin berbeda.
 *
 * TIGA ATURAN YANG MENJAGA ANGKANYA TETAP JUJUR:
 *
 * 1. INDIKATOR TANPA PASANGAN DILEWATI. Kalau baseline punya angka tapi
 *    endline belum (atau sebaliknya), indikator itu tidak menghasilkan
 *    perubahan apa pun. Ia TIDAK dianggap nol. Peserta yang belum diukur
 *    ulang tidak boleh terlihat seolah usahanya anjlok ke nol.
 *
 * 2. TIDAK ADA SKOR GABUNGAN. Setiap indikator berdiri sendiri dengan angka
 *    sebelum, sesudah, selisih, dan persennya. Satu angka "skor perkembangan"
 *    akan terlihat meyakinkan justru pada saat datanya paling tipis.
 *
 * 3. PEMBAGI NOL TIDAK MENGHASILKAN PERSENTASE. Naik dari 0 ke 500.000 bukan
 *    "kenaikan tak hingga persen"; selisihnya dilaporkan, persennya NULL.
 */
class PerbandinganService
{
    /**
     * Rapor perbandingan satu peserta.
     *
     * Titik akhir yang dipakai: ENDLINE kalau ada, kalau belum ada memakai
     * MONITORING TERBARU. Ini disengaja — peserta yang masih berjalan tetap
     * bisa dilihat perkembangannya tanpa harus dipaksa "diakhiri" dulu.
     * Kunci sumber_akhir memberitahu pembacanya titik mana yang dipakai,
     * supaya monitoring di tengah jalan tidak pernah salah dibaca sebagai
     * hasil akhir program.
     */
    public function untukPeserta(Participant $peserta): array
    {
        $pengukuran = $peserta->relationLoaded('measurements')
            ? $peserta->measurements
            : $peserta->measurements()->get();

        $awal = $this->titikAwal($pengukuran);
        [$akhir, $sumberAkhir] = $this->titikAkhir($pengukuran);

        return [
            'baseline' => $awal,
            'akhir' => $akhir,
            'sumber_akhir' => $sumberAkhir,          // Endline | Monitoring | null
            'siap_dibandingkan' => $awal !== null && $akhir !== null,
            'perubahan' => $this->perubahan($awal, $akhir),
            'perubahan_status' => $this->perubahanBoolean($awal, $akhir),
            'jumlah_monitoring' => $pengukuran->where('jenis', ParticipantMeasurement::MONITORING)->count(),
            'alasan_belum_siap' => $this->alasanBelumSiap($awal, $akhir),
        ];
    }

    /** Baseline paling awal. Kalau peserta punya lebih dari satu, yang pertama yang berlaku. */
    public function titikAwal(Collection $pengukuran): ?ParticipantMeasurement
    {
        return $pengukuran
            ->where('jenis', ParticipantMeasurement::BASELINE)
            ->sortBy('tanggal')
            ->first();
    }

    /** @return array{0: ?ParticipantMeasurement, 1: ?string} */
    public function titikAkhir(Collection $pengukuran): array
    {
        $endline = $pengukuran
            ->where('jenis', ParticipantMeasurement::ENDLINE)
            ->sortByDesc('tanggal')
            ->first();

        if ($endline) {
            return [$endline, ParticipantMeasurement::ENDLINE];
        }

        $monitoring = $pengukuran
            ->where('jenis', ParticipantMeasurement::MONITORING)
            ->sortByDesc('tanggal')
            ->first();

        return $monitoring
            ? [$monitoring, ParticipantMeasurement::MONITORING]
            : [null, null];
    }

    /**
     * Perubahan per indikator angka.
     *
     * Hanya indikator yang PUNYA ANGKA DI KEDUA TITIK yang muncul di hasil.
     * Yang lain tidak dilaporkan sebagai 0 — ia memang belum diukur, dan
     * daftar kekurangannya bisa dibaca dari selisih kunci terhadap
     * ParticipantMeasurement::INDIKATOR.
     */
    public function perubahan(?ParticipantMeasurement $awal, ?ParticipantMeasurement $akhir): array
    {
        if (! $awal || ! $akhir) {
            return [];
        }

        $hasil = [];

        foreach (ParticipantMeasurement::INDIKATOR as $kolom => $meta) {
            $sebelum = $awal->{$kolom};
            $sesudah = $akhir->{$kolom};

            // Aturan 1: tanpa pasangan, tidak ada perubahan yang bisa diklaim.
            if ($sebelum === null || $sesudah === null) {
                continue;
            }

            $selisih = (int) $sesudah - (int) $sebelum;

            $hasil[$kolom] = [
                'label' => $meta['label'],
                'satuan' => $meta['satuan'],
                'format' => $meta['format'],
                'arah' => $meta['arah'],
                'sebelum' => (int) $sebelum,
                'sesudah' => (int) $sesudah,
                'selisih' => $selisih,
                // Aturan 3: dari nol, persentase tidak punya arti.
                'persen' => (int) $sebelum === 0
                    ? null
                    : round($selisih / (int) $sebelum * 100, 1),
                'membaik' => $this->membaik($meta['arah'], $selisih),
            ];
        }

        // Profit ikut dibandingkan kalau komponennya lengkap di kedua titik —
        // ia turunan, jadi tidak bisa diambil dari daftar kolom di atas.
        $profitAwal = $awal->profitBulanan();
        $profitAkhir = $akhir->profitBulanan();

        if ($profitAwal !== null && $profitAkhir !== null) {
            $selisih = $profitAkhir - $profitAwal;

            $hasil['profit_bulanan'] = [
                'label' => 'Profit bersih per bulan',
                'satuan' => 'Rp',
                'format' => 'rupiah',
                'arah' => 'naik',
                'sebelum' => $profitAwal,
                'sesudah' => $profitAkhir,
                'selisih' => $selisih,
                'persen' => $profitAwal === 0 ? null : round($selisih / abs($profitAwal) * 100, 1),
                'membaik' => $selisih > 0,
            ];
        }

        return $hasil;
    }

    /** Indikator ya/tidak: "belum -> sudah" atau sebaliknya. */
    public function perubahanBoolean(?ParticipantMeasurement $awal, ?ParticipantMeasurement $akhir): array
    {
        if (! $awal || ! $akhir) {
            return [];
        }

        $hasil = [];

        foreach (ParticipantMeasurement::INDIKATOR_BOOLEAN as $kolom => $label) {
            $sebelum = $awal->{$kolom};
            $sesudah = $akhir->{$kolom};

            if ($sebelum === null || $sesudah === null) {
                continue;
            }

            $hasil[$kolom] = [
                'label' => $label,
                'sebelum' => (bool) $sebelum,
                'sesudah' => (bool) $sesudah,
                'berubah' => (bool) $sebelum !== (bool) $sesudah,
                'membaik' => ! $sebelum && $sesudah,
            ];
        }

        return $hasil;
    }

    /**
     * Apakah perubahan ini dianggap membaik?
     *
     * Indikator ber-arah netral (HPP, biaya operasional) SENGAJA selalu
     * mengembalikan null: naiknya biaya bisa berarti usaha membesar, bisa
     * juga berarti boros, dan sistem tidak punya cara membedakannya. Itu
     * penilaian pendamping, bukan penilaian rumus.
     */
    private function membaik(string $arah, int $selisih): ?bool
    {
        if ($selisih === 0) {
            return null;
        }

        return match ($arah) {
            'naik' => $selisih > 0,
            'turun' => $selisih < 0,
            default => null,
        };
    }

    /** @return list<string> kalimat yang bisa langsung ditampilkan */
    private function alasanBelumSiap(?ParticipantMeasurement $awal, ?ParticipantMeasurement $akhir): array
    {
        $alasan = [];

        if (! $awal) {
            $alasan[] = 'Baseline belum dicatat — tanpa kondisi awal, perubahan tidak bisa dihitung.';
        }

        if (! $akhir) {
            $alasan[] = 'Belum ada monitoring maupun endline sebagai pembanding.';
        }

        return $alasan;
    }

    /**
     * Rekap perubahan SEKUMPULAN peserta — dipakai modul Impact.
     *
     * Mengembalikan, per indikator: berapa peserta yang bisa dibandingkan,
     * berapa yang membaik, dan TOTAL selisihnya. Total inilah yang nanti
     * dikalikan proxy finansial di mesin SROI.
     *
     * jumlah_peserta_terukur per indikator ikut dikembalikan dan WAJIB
     * ditampilkan bersama totalnya. Total kenaikan omzet dari 3 peserta dan
     * dari 300 peserta adalah dua klaim yang sangat berbeda, dan angkanya
     * sendiri tidak menunjukkan yang mana.
     *
     * @param  \Illuminate\Support\Collection<int, Participant>  $pesertaList
     */
    public function rekap(Collection $pesertaList): array
    {
        $rekap = [];
        $siap = 0;

        foreach ($pesertaList as $peserta) {
            $rapor = $this->untukPeserta($peserta);

            if (! $rapor['siap_dibandingkan']) {
                continue;
            }

            $siap++;

            foreach ($rapor['perubahan'] as $kolom => $p) {
                $rekap[$kolom] ??= [
                    'label' => $p['label'],
                    'satuan' => $p['satuan'],
                    'format' => $p['format'],
                    'jumlah_peserta_terukur' => 0,
                    'jumlah_membaik' => 0,
                    'total_selisih' => 0,
                    'total_sebelum' => 0,
                    'total_sesudah' => 0,
                ];

                $rekap[$kolom]['jumlah_peserta_terukur']++;
                $rekap[$kolom]['jumlah_membaik'] += $p['membaik'] === true ? 1 : 0;
                $rekap[$kolom]['total_selisih'] += $p['selisih'];
                $rekap[$kolom]['total_sebelum'] += $p['sebelum'];
                $rekap[$kolom]['total_sesudah'] += $p['sesudah'];
            }

            foreach ($rapor['perubahan_status'] as $kolom => $p) {
                $rekap[$kolom] ??= [
                    'label' => $p['label'],
                    'satuan' => 'peserta',
                    'format' => 'angka',
                    'jumlah_peserta_terukur' => 0,
                    'jumlah_membaik' => 0,
                    'total_selisih' => 0,
                    'total_sebelum' => 0,
                    'total_sesudah' => 0,
                ];

                $rekap[$kolom]['jumlah_peserta_terukur']++;
                $rekap[$kolom]['jumlah_membaik'] += $p['membaik'] ? 1 : 0;
                // Untuk indikator ya/tidak, "selisih" berarti jumlah peserta
                // yang statusnya berubah menjadi sudah — itulah satuan yang
                // dipakai proxy finansialnya nanti ("per UMKM berlegalitas").
                $rekap[$kolom]['total_selisih'] += $p['membaik'] ? 1 : 0;
                $rekap[$kolom]['total_sebelum'] += $p['sebelum'] ? 1 : 0;
                $rekap[$kolom]['total_sesudah'] += $p['sesudah'] ? 1 : 0;
            }
        }

        return [
            'jumlah_peserta' => $pesertaList->count(),
            'jumlah_siap_dibandingkan' => $siap,
            'indikator' => $rekap,
        ];
    }
}
