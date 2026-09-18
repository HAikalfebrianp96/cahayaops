<?php

namespace App\Services;

use App\Models\Participant;
use App\Models\PendampinganRecord;
use App\Models\SystemSetting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * STATUS FOLLOW UP BINAAN — Hijau / Kuning / Merah.
 *
 * TIDAK ADA TABEL BARU. Statusnya DITURUNKAN dari data yang memang sudah
 * dicatat pendamping:
 *
 *   - tanggal aktivitas terakhir  -> pendampingan_records.tanggal
 *   - jenis & pelaksana terakhir  -> pendampingan_records.jenis_kegiatan / pendamping_id
 *   - jadwal tindak lanjut        -> pendampingan_records.tanggal_follow_up
 *
 * Menyimpannya sebagai kolom status di tabel peserta akan menciptakan sumber
 * kebenaran kedua yang harus diperbarui setiap kali catatan ditambah, diubah,
 * dihapus, ATAU dipulihkan — dan yang pasti akan basi begitu satu jalur lupa
 * memperbaruinya. Dihitung saat dibutuhkan, angkanya tidak pernah bisa salah.
 *
 * Seluruh perhitungan dilakukan dalam SATU query agregat untuk sekumpulan
 * peserta sekaligus (lihat aktivitasTerakhir()), bukan satu query per peserta.
 */
class FollowUpService
{
    public const HIJAU = 'Hijau';
    public const KUNING = 'Kuning';
    public const MERAH = 'Merah';

    /**
     * Ambang batas yang BERLAKU.
     *
     * Diambil dari SystemSetting kalau Admin sudah mengisinya; kalau belum,
     * jatuh ke nilai bawaan di config/cahayaos.php. Jadi kebijakan bisa
     * diubah lewat halaman Pengaturan tanpa menyentuh kode, dan sistem tetap
     * berjalan wajar sebelum ada yang mengisinya.
     *
     * @return array{kuning:int, merah:int}
     */
    public function ambang(): array
    {
        $setting = SystemSetting::current();

        $kuning = (int) ($setting->followup_kuning_hari ?: config('cahayaos.followup.kuning_hari', 30));
        $merah = (int) ($setting->followup_merah_hari ?: config('cahayaos.followup.merah_hari', 45));

        // Penjagaan urutan: ambang merah HARUS lebih besar dari kuning.
        // Kalau Admin keliru mengisi terbalik, statusnya akan kacau tanpa
        // pesan error apa pun — jadi nilainya dirapikan di sini.
        if ($merah <= $kuning) {
            $merah = $kuning + 15;
        }

        return ['kuning' => $kuning, 'merah' => $merah];
    }

    /**
     * Aktivitas pendampingan TERAKHIR untuk sekumpulan peserta.
     *
     * Satu query untuk semuanya. Mengambilnya per peserta akan menghasilkan
     * pola N+1 tepat di halaman yang paling sering dibuka (dashboard).
     *
     * @param  Collection<int>  $participantIds
     * @return Collection<int, PendampinganRecord> dikunci participant_id
     */
    public function aktivitasTerakhir(Collection $participantIds): Collection
    {
        if ($participantIds->isEmpty()) {
            return collect();
        }

        return PendampinganRecord::with('pendamping')
            ->whereIn('participant_id', $participantIds)
            ->orderBy('participant_id')
            ->orderByDesc('tanggal')
            ->orderByDesc('id')
            ->get()
            ->groupBy('participant_id')
            ->map(fn ($catatan) => $catatan->first());
    }

    /**
     * Ringkasan follow up satu peserta.
     *
     * @return array{
     *   status:string, warna:string, hari:?int, terakhir:?Carbon,
     *   jenis:?string, pendamping:?string, follow_up:?Carbon,
     *   terlambat_follow_up:bool, alasan:string
     * }
     */
    public function untukPeserta(Participant $peserta, ?PendampinganRecord $terakhir): array
    {
        $ambang = $this->ambang();

        // Belum pernah didampingi sama sekali. Ini BUKAN tebakan: tidak ada
        // satu pun catatan untuk peserta ini, dan itu memang keadaan yang
        // paling perlu ditindaklanjuti.
        if ($terakhir === null) {
            return [
                'status' => self::MERAH,
                'warna' => $this->warna(self::MERAH),
                'hari' => null,
                'terakhir' => null,
                'jenis' => null,
                'pendamping' => null,
                'follow_up' => null,
                'terlambat_follow_up' => false,
                'alasan' => 'Belum pernah mendapat pendampingan.',
            ];
        }

        // Dibatasi minimal 0.
        //
        // Validasi form sekarang menolak tanggal masa depan, tapi baris yang
        // TERLANJUR tersimpan sebelum itu — atau yang masuk lewat jalur lain —
        // akan menghasilkan selisih negatif, lalu tampil sebagai kalimat yang
        // tidak masuk akal: "Didampingi -365 hari lalu".
        //
        // Yang paling mendekati benar untuk baris semacam itu adalah
        // memperlakukannya seperti hari ini: 0 hari, status Hijau. Itu memang
        // tidak membetulkan tanggalnya — yang membetulkan hanya orang yang
        // mengoreksinya — tapi setidaknya layarnya tidak lagi berbohong.
        $hari = max(0, (int) $terakhir->tanggal->startOfDay()->diffInDays(now()->startOfDay()));

        $status = match (true) {
            $hari >= $ambang['merah'] => self::MERAH,
            $hari >= $ambang['kuning'] => self::KUNING,
            default => self::HIJAU,
        };

        $terlambatFollowUp = $terakhir->tanggal_follow_up !== null
            && $terakhir->tanggal_follow_up->startOfDay()->lt(now()->startOfDay());

        return [
            'status' => $status,
            'warna' => $this->warna($status),
            'hari' => $hari,
            'terakhir' => $terakhir->tanggal,
            'jenis' => $terakhir->jenis_kegiatan,
            'pendamping' => $terakhir->pendamping?->nama,
            'follow_up' => $terakhir->tanggal_follow_up,
            'terlambat_follow_up' => $terlambatFollowUp,
            'alasan' => $this->alasan($status, $hari, $ambang),
        ];
    }

    /**
     * Ringkasan follow up untuk BANYAK peserta sekaligus.
     *
     * @param  Collection<int, Participant>  $peserta
     * @return Collection<int, array> dikunci participant id
     */
    public function untukBanyak(Collection $peserta): Collection
    {
        $terakhir = $this->aktivitasTerakhir($peserta->pluck('id'));

        return $peserta->mapWithKeys(fn (Participant $p) => [
            $p->id => $this->untukPeserta($p, $terakhir->get($p->id)),
        ]);
    }

    /**
     * Kelompok kerja untuk halaman "Follow Up Saya".
     *
     * Keranjang yang saling lepas — satu peserta hanya muncul di SATU tempat,
     * supaya daftarnya bisa dikerjakan dari atas ke bawah tanpa mengerjakan
     * orang yang sama dua kali.
     *
     *   belum_pernah    -> belum pernah didampingi sama sekali
     *   terlambat       -> jadwalnya lewat, atau sudah melampaui ambang merah
     *   hari_ini        -> dijadwalkan hari ini
     *   perlu_perhatian -> melewati ambang kuning, belum punya jadwal
     *   akan_datang     -> dijadwalkan di kemudian hari
     *   normal          -> tidak perlu tindakan; HANYA dihitung, tidak
     *                      ditampilkan sebagai daftar
     *
     * Sejak keranjang `normal` ada, setiap peserta pasti masuk salah satunya.
     * Sebelumnya sebagian peserta tidak cocok ke mana pun dan lenyap dari
     * halaman tanpa ada yang menyadarinya.
     *
     * @param  Collection<int, Participant>  $peserta
     */
    public function kelompokkan(Collection $peserta): array
    {
        $idPeserta = $peserta->pluck('id');
        $terakhir = $this->aktivitasTerakhir($idPeserta);
        $hariIni = now()->startOfDay();

        // Dua penanda untuk SELURUH daftar sekaligus. Dibaca di sini, bukan
        // di dalam perulangan, supaya menambah tombol tindakan tidak berarti
        // menambah dua query untuk setiap peserta yang tampil.
        $sudahLaporOmzet = $this->sudahLaporOmzetBulanIni($idPeserta);
        $punyaBaseline = $this->sudahPunyaBaseline($idPeserta);

        $kelompok = [
            'belum_pernah' => collect(),
            'terlambat' => collect(),
            'hari_ini' => collect(),
            'perlu_perhatian' => collect(),
            'akan_datang' => collect(),
            // Bukan pekerjaan — hanya dihitung, supaya pendamping tahu
            // sisanya memang sudah beres dan bukan sekadar tidak terdaftar.
            'normal' => collect(),
        ];

        foreach ($peserta as $p) {
            $catatan = $terakhir->get($p->id);
            $ringkas = $this->untukPeserta($p, $catatan);
            $baris = [
                'peserta' => $p,
                'ringkas' => $ringkas,
                'catatan' => $catatan,
                // Tombol yang ditawarkan ikut dibawa barisnya, supaya daftar
                // Follow Up dan daftar Prioritas menawarkan hal yang sama
                // untuk peserta yang sama.
                'tindakan' => $this->tindakan(
                    $ringkas,
                    $sudahLaporOmzet->has($p->id),
                    $punyaBaseline->has($p->id)
                ),
                // Alasan soal KEADAAN USAHA, di samping alasan soal jadwal
                // yang sudah ada di $ringkas['alasan']. Keduanya dibawa
                // bersama supaya kartu tugas memberi gambaran utuh sekali
                // baca, bukan separuh di sini dan separuh di halaman lain.
                'keadaan_usaha' => $this->keadaanUsaha($p, $sudahLaporOmzet->has($p->id)),
            ];

            $jadwal = $ringkas['follow_up'];

            $kunci = match (true) {
                $catatan === null => 'belum_pernah',
                $jadwal !== null && $jadwal->startOfDay()->lt($hariIni) => 'terlambat',
                $jadwal !== null && $jadwal->startOfDay()->eq($hariIni) => 'hari_ini',
                $jadwal !== null => 'akan_datang',
                // Tanpa jadwal tapi sudah melewati ambang merah tetap harus
                // dikerjakan — kalau tidak, binaan yang terlupakan justru
                // tidak pernah muncul di daftar manapun.
                $ringkas['status'] === self::MERAH => 'terlambat',
                // ==================================================
                // KUNING TANPA JADWAL — sebelumnya HILANG dari halaman.
                //
                // Peserta yang sudah melewati ambang kuning tapi tidak punya
                // tanggal tindak lanjut tidak cocok dengan satu pun keranjang
                // di atas, jadi ia jatuh ke `default => null` dan tidak
                // pernah ditampilkan di mana pun. Ia baru muncul setelah
                // terlambat cukup lama untuk menjadi merah.
                //
                // Justru di rentang inilah tindakan paling murah: menyapa
                // peserta yang mulai lama tak tersentuh, sebelum ia benar-
                // benar terbengkalai.
                // ==================================================
                $ringkas['status'] === self::KUNING => 'perlu_perhatian',
                default => 'normal',
            };

            $kelompok[$kunci]->push($baris);
        }

        // Yang paling lama terbengkalai naik ke atas.
        $kelompok['terlambat'] = $kelompok['terlambat']
            ->sortByDesc(fn ($b) => $b['ringkas']['hari'] ?? PHP_INT_MAX)->values();
        $kelompok['akan_datang'] = $kelompok['akan_datang']
            ->sortBy(fn ($b) => $b['ringkas']['follow_up']?->timestamp ?? PHP_INT_MAX)->values();
        $kelompok['belum_pernah'] = $kelompok['belum_pernah']
            ->sortBy(fn ($b) => $b['peserta']->nama_peserta)->values();
        $kelompok['hari_ini'] = $kelompok['hari_ini']->values();
        $kelompok['perlu_perhatian'] = $kelompok['perlu_perhatian']
            ->sortByDesc(fn ($b) => $b['ringkas']['hari'] ?? 0)->values();
        $kelompok['normal'] = $kelompok['normal']
            ->sortBy(fn ($b) => $b['peserta']->nama_peserta)->values();

        return $kelompok;
    }

    public function warna(string $status): string
    {
        return match ($status) {
            self::HIJAU => 'bg-primary-light text-status-success',
            self::KUNING => 'bg-gold-light text-status-warning',
            default => 'bg-status-danger/10 text-status-danger',
        };
    }

    /**
     * KEADAAN USAHA SATU PESERTA — apa yang kurang, selain soal jadwal.
     *
     * =================================================================
     * KENAPA SATU TEMPAT
     * =================================================================
     *
     * Kalimat-kalimat ini dulu ditulis di dalam PrioritasPendampinganController
     * dan hanya bisa dibaca di halaman Prioritas. Halaman Tugas Saya —
     * tempat pendamping sebenarnya bekerja tiap pagi — hanya menampilkan
     * alasan soal waktu, jadi "omzet bulan ini belum diperbarui" tidak
     * pernah sampai ke orang yang bisa mengerjakannya.
     *
     * Sekarang keduanya membaca dari sini. Yang dikembalikan BERPASANGAN
     * dengan kuncinya, bukan sekadar kalimat, supaya Prioritas tetap bisa
     * memberi bobot berbeda untuk tiap kondisi tanpa mencocokkan teks.
     *
     * =================================================================
     * YANG SENGAJA TIDAK MASUK
     * =================================================================
     *
     * Baseline yang belum dicatat TIDAK dihitung sebagai keadaan usaha di
     * sini. Ia sudah disuarakan tombol "Catat Kondisi Awal", dan
     * memasukkannya ke daftar ini akan ikut menggeser peringkat di halaman
     * Prioritas — padahal tidak ada yang meminta peringkatnya berubah.
     *
     * @return list<array{kunci:string, alasan:string}>
     */
    public function keadaanUsaha(Participant $peserta, bool $omzetBulanIniSudahDilaporkan): array
    {
        $keadaan = [];

        if (! $omzetBulanIniSudahDilaporkan) {
            $keadaan[] = [
                'kunci' => 'omzet',
                'alasan' => 'Omzet bulan ini belum diperbarui',
            ];
        }

        // Legalitas bermasalah = ada dokumen yang DIDAFTARKAN tapi belum
        // selesai. Peserta tanpa dokumen terdaftar TIDAK dihitung bermasalah:
        // tidak semua UMKM membutuhkan legalitas yang sama.
        $tertunda = $peserta->legalitasRecords
            ->where('status', '!=', \App\Models\LegalitasRecord::STATUS_SUDAH_ADA)
            ->count();

        if ($tertunda > 0) {
            $keadaan[] = [
                'kunci' => 'legalitas',
                'alasan' => $tertunda . ' dokumen legalitas belum selesai',
            ];
        }

        $kurang = collect([
            'Nomor HP' => filled($peserta->nomor_hp),
            'Alamat' => filled($peserta->alamat),
            'Nama usaha' => filled($peserta->nama_usaha),
            'Jenis usaha' => filled($peserta->kategori_usaha),
        ])->filter(fn ($ada) => ! $ada)->keys();

        if ($kurang->isNotEmpty()) {
            $keadaan[] = [
                'kunci' => 'data',
                'alasan' => 'Data belum lengkap: ' . $kurang->implode(', '),
            ];
        }

        return $keadaan;
    }

    /**
     * Peserta mana yang SUDAH melaporkan omzet bulan ini.
     *
     * Satu query untuk seluruh daftar, bukan satu per baris. Dikembalikan
     * sebagai kunci yang sudah dibalik supaya pemeriksaannya ->has($id).
     */
    public function sudahLaporOmzetBulanIni(Collection $participantIds): Collection
    {
        return \App\Models\OmzetReport::whereIn('participant_id', $participantIds)
            ->where('periode_bulan', now()->month)
            ->where('periode_tahun', now()->year)
            ->pluck('participant_id')
            ->flip();
    }

    /**
     * Peserta mana yang SUDAH punya kondisi awal (baseline).
     *
     * Tanpa baseline, perubahan usaha peserta tidak akan pernah bisa
     * dihitung — dan itu baru ketahuan di akhir program, saat mencatatnya
     * sudah terlambat.
     */
    public function sudahPunyaBaseline(Collection $participantIds): Collection
    {
        return \App\Models\ParticipantMeasurement::whereIn('participant_id', $participantIds)
            ->where('jenis', \App\Models\ParticipantMeasurement::BASELINE)
            ->pluck('participant_id')
            ->flip();
    }

    /**
     * TINDAKAN YANG PANTAS DITAWARKAN UNTUK SATU PESERTA.
     *
     * =================================================================
     * KENAPA ADA DI SINI, BUKAN DI MASING-MASING VIEW
     * =================================================================
     *
     * Daftar Prioritas dan daftar Follow Up menjawab pertanyaan yang sama
     * ("siapa yang harus saya tindak lanjuti?"), lalu sebelumnya menawarkan
     * tombol yang berbeda-beda karena masing-masing menulis tombolnya
     * sendiri. Pendamping yang mengerjakan peserta yang sama dari dua
     * halaman itu menemukan dua pilihan tindakan yang berbeda.
     *
     * Ditaruh satu tempat supaya keduanya tidak mungkin berbeda lagi.
     *
     * =================================================================
     * YANG TIDAK DILAKUKAN DI SINI
     * =================================================================
     *
     * Tidak ada tombol "Catat Follow Up" tersendiri. Jadwal follow up
     * memang diisi DI DALAM form catatan pendampingan (kolom
     * tanggal_follow_up), jadi tombol terpisah akan menjadi jalur input
     * kedua untuk data yang sama — persis yang harus dihindari.
     *
     * Fungsi ini juga TIDAK ikut menentukan prioritas. Ia hanya membaca
     * keadaan yang sudah dihitung di tempat lain, lalu menyebut apa yang
     * bisa dikerjakan. Menambah tindakan di sini tidak akan menggeser
     * peserta mana pun ke kelompok yang berbeda.
     *
     * @param  array  $ringkas  hasil untukPeserta() / untukBanyak()
     * @return list<array{label:string, rute:string, fragmen:string, utama:bool}>
     */
    public function tindakan(array $ringkas, bool $omzetBulanIniSudahDilaporkan, bool $punyaBaseline): array
    {
        $tindakan = [];

        // Pekerjaan intinya. Selalu ditawarkan, dan selalu didahulukan —
        // form inilah yang sekaligus menyimpan jadwal follow up berikutnya.
        $tindakan[] = [
            'label' => $ringkas['terakhir'] === null ? 'Catat Pendampingan Pertama' : 'Catat Pendampingan',
            'rute' => 'participants.show',
            // Dipakai #catat-pendampingan, bukan #pendampingan.
            //
            // Keduanya sah — halaman peserta mengenali keduanya. Bedanya:
            // #pendampingan berhenti di tab Pendampingan, sedangkan
            // #catat-pendampingan sekaligus membentangkan form pencatatannya
            // dan menggulir ke sana.
            //
            // Untuk tombol yang bunyinya "Catat Pendampingan", berhenti di
            // tab berarti pendamping masih harus mencari formnya sendiri.
            'fragmen' => '#catat-pendampingan',
            'utama' => true,
        ];

        if (! $omzetBulanIniSudahDilaporkan) {
            $tindakan[] = [
                'label' => 'Update Omzet',
                'rute' => 'omzet.create',
                'fragmen' => '',
                'utama' => false,
            ];
        }

        // Tanpa kondisi awal, perubahan usaha peserta ini tidak akan pernah
        // bisa dihitung — dan itu baru ketahuan di akhir program, saat sudah
        // terlambat mencatatnya.
        if (! $punyaBaseline) {
            $tindakan[] = [
                'label' => 'Catat Kondisi Awal',
                'rute' => 'perkembangan.create',
                'fragmen' => '',
                'utama' => false,
            ];
        }

        return $tindakan;
    }

    private function alasan(string $status, int $hari, array $ambang): string
    {
        return match ($status) {
            self::HIJAU => 'Didampingi ' . $hari . ' hari lalu, masih dalam periode normal.',
            self::KUNING => 'Sudah ' . $hari . ' hari sejak pendampingan terakhir (ambang perhatian ' . $ambang['kuning'] . ' hari).',
            default => 'Sudah ' . $hari . ' hari tanpa pendampingan (ambang terlambat ' . $ambang['merah'] . ' hari).',
        };
    }
}
