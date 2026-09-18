<?php

namespace App\Http\Controllers;

use App\Models\Participant;
use App\Models\Pendamping;
use App\Services\FollowUpService;
use App\Services\KpiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * PRIORITAS PENDAMPINGAN — peserta mana yang harus dikerjakan lebih dulu.
 *
 * BEDANYA DENGAN HALAMAN FOLLOW UP. Follow Up mengelompokkan berdasarkan
 * JADWAL (jatuh tempo hari ini / terlambat / akan datang). Halaman ini
 * mengelompokkan berdasarkan KEADAAN PESERTA — omzet, legalitas,
 * kelengkapan data, dan lamanya tanpa aktivitas. Keduanya menjawab
 * pertanyaan berbeda dan sengaja tidak digabung.
 *
 * TIDAK ADA TABEL BARU dan tidak ada perhitungan yang diulang: seluruh
 * bahannya diambil dari [FollowUpService] dan [KpiService] yang sudah ada,
 * jadi status di sini tidak mungkin berbeda dari halaman lain.
 *
 * OTORISASI: Pendamping HANYA melihat binaannya sendiri (di-scope di level
 * query; filter ?pendamping_id dari URL diabaikan untuknya). Admin melihat
 * seluruh peserta dengan filter.
 */
class PrioritasPendampinganController extends Controller
{
    public function __construct(
        private FollowUpService $followUp,
        private KpiService $kpi,
    ) {
    }

    public function index(Request $request)
    {
        $user = Auth::user();

        abort_unless(
            $user->isAdmin() || ($user->isPendamping() && $user->pendamping_id),
            403
        );

        $query = Participant::with(['pendamping', 'legalitasRecords', 'koperasiMember']);

        if ($user->isAdmin()) {
            if ($request->filled('pendamping_id')) {
                $query->where('pendamping_id', $request->integer('pendamping_id'));
            }
        } else {
            $query->where('pendamping_id', $user->pendamping_id);
        }

        // Peserta yang sudah Alumni/Nonaktif tidak perlu diprioritaskan lagi.
        $peserta = $query->orderBy('nama_peserta')->get()->filter->masihBerjalan();

        $statusFollowUp = $this->followUp->untukBanyak($peserta);

        // Kedua penanda ini dibaca dari FollowUpService, bukan ditulis ulang
        // di sini. Daftar Follow Up memakai definisi yang sama persis, dan
        // "sudah lapor omzet bulan ini" yang berbeda arti di dua halaman
        // adalah jenis selisih yang tidak akan pernah ada yang menyadarinya.
        $idPeserta = $peserta->pluck('id');
        $sudahLaporBulanIni = $this->followUp->sudahLaporOmzetBulanIni($idPeserta);
        $punyaBaseline = $this->followUp->sudahPunyaBaseline($idPeserta);

        $dinilai = $peserta->map(fn (Participant $p) => $this->nilai(
            $p,
            $statusFollowUp[$p->id],
            $sudahLaporBulanIni,
            $punyaBaseline
        ));

        // ============================================================
        // EMPAT KELOMPOK, BUKAN TIGA
        //
        // "Jadwal Hari Ini" dipisahkan dari Prioritas Sedang. Sebelumnya
        // janji yang jatuh HARI INI tenggelam bersama janji tujuh hari ke
        // depan dan peserta yang datanya kurang lengkap — padahal hanya
        // satu dari ketiganya yang harus dikerjakan hari ini juga.
        //
        // Kunci 'tinggi'/'sedang'/'rendah' sengaja dipertahankan supaya
        // pemakai lama tidak ikut berubah; yang bertambah hanya satu kunci.
        // ============================================================
        $kelompok = [
            'tinggi' => $dinilai->where('tingkat', 'Tinggi')->sortByDesc('skor')->values(),
            'sedang' => $dinilai->where('tingkat', 'Sedang')->sortByDesc('skor')->values(),
            'hari_ini' => $dinilai->where('tingkat', 'Hari Ini')->sortByDesc('skor')->values(),
            'rendah' => $dinilai->where('tingkat', 'Rendah')->values(),
        ];

        return view('prioritas.index', [
            'kelompok' => $kelompok,
            'isAdmin' => $user->isAdmin(),
            'pendampings' => $user->isAdmin() ? Pendamping::orderBy('nama')->get() : collect(),
            'filters' => $request->only(['pendamping_id']),
            'ambangKeaktifan' => $this->kpi->ambangKeaktifan(),
            'jumlah' => [
                'tinggi' => $kelompok['tinggi']->count(),
                'sedang' => $kelompok['sedang']->count(),
                'hari_ini' => $kelompok['hari_ini']->count(),
                'rendah' => $kelompok['rendah']->count(),
            ],
        ]);
    }

    /**
     * Menilai satu peserta.
     *
     * Alasannya SELALU ikut dikembalikan. Daftar prioritas tanpa alasan
     * hanya memindahkan keputusan dari pendamping ke rumus yang tidak bisa
     * dibantah — padahal pendamping yang tahu keadaan sebenarnya di lapangan.
     */
    private function nilai(Participant $p, array $fu, $sudahLaporBulanIni, $punyaBaseline): array
    {
        $alasan = [];
        $skor = 0;
        $tinggi = false;

        // --- PRIORITAS TINGGI ---
        if ($fu['terakhir'] === null) {
            $alasan[] = 'Belum pernah mendapat pendampingan';
            $skor += 100;
            $tinggi = true;
        } elseif ($fu['status'] === FollowUpService::MERAH) {
            $alasan[] = 'Tanpa aktivitas ' . $fu['hari'] . ' hari';
            $skor += 60 + min($fu['hari'], 200);
            $tinggi = true;
        }

        if ($fu['terlambat_follow_up']) {
            $alasan[] = 'Follow up terlambat sejak ' . $fu['follow_up']->format('d-m-Y');
            $skor += 55;
            $tinggi = true;
        }

        // ============================================================
        // KEADAAN USAHA — kalimatnya dibaca dari FollowUpService.
        //
        // Dulu ditulis di sini, dan karena itu hanya bisa dibaca di halaman
        // ini. Halaman Tugas Saya sekarang menampilkan kalimat yang SAMA
        // PERSIS untuk peserta yang sama, karena keduanya memanggil satu
        // fungsi yang sama.
        //
        // Bobotnya tetap ditentukan di sini: yang tahu seberapa berat tiap
        // kondisi menggeser peringkat adalah halaman peringkat, bukan
        // service yang hanya menyebutkan keadaannya.
        //
        // Bobot dipilih berdasarkan KUNCI, bukan teksnya — supaya
        // memperbaiki kalimat di service tidak diam-diam mengubah peringkat.
        // ============================================================
        $dataBelumLengkap = false;

        foreach ($this->followUp->keadaanUsaha($p, $sudahLaporBulanIni->has($p->id)) as $keadaan) {
            $alasan[] = $keadaan['alasan'];

            match ($keadaan['kunci']) {
                'omzet' => [$skor += 25, $tinggi = true],
                'legalitas' => [$skor += 20, $tinggi = true],
                // Data yang belum lengkap tidak membuat seseorang mendesak;
                // bobotnya diterapkan bersama kelompok Sedang di bawah.
                'data' => [$dataBelumLengkap = true],
                default => null,
            };
        }

        // --- PRIORITAS SEDANG ---
        $sedang = false;

        if (! $tinggi && $fu['status'] === FollowUpService::KUNING) {
            $alasan[] = 'Mendekati batas: ' . $fu['hari'] . ' hari tanpa aktivitas';
            $skor += 30;
            $sedang = true;
        }

        // Janji yang jatuh HARI INI dibedakan dari janji minggu depan.
        //
        // terlambat_follow_up berarti tanggalnya SEBELUM hari ini, jadi janji
        // hari ini tidak pernah terhitung terlambat — sinyalnya sudah ada di
        // data, hanya belum pernah dipakai memisahkan kelompok.
        $jadwalHariIni = $fu['follow_up'] !== null
            && ! $fu['terlambat_follow_up']
            && $fu['follow_up']->startOfDay()->eq(now()->startOfDay());

        if ($jadwalHariIni) {
            $alasan[] = 'Follow up dijadwalkan hari ini';
            $skor += 45;
        } elseif ($fu['follow_up'] !== null && ! $fu['terlambat_follow_up']
            && $fu['follow_up']->startOfDay()->lte(now()->addDays(7)->startOfDay())) {
            $alasan[] = 'Follow up dijadwalkan ' . $fu['follow_up']->format('d-m-Y');
            $skor += 15;
            $sedang = true;
        }

        // Kalimatnya sudah ditambahkan di atas oleh keadaanUsaha();
        // yang tersisa di sini hanya bobot dan tingkatnya.
        if ($dataBelumLengkap) {
            $skor += 15;
            $sedang = true;
        }

        // Urutannya menentukan peserta masuk kelompok mana kalau memenuhi
        // beberapa kondisi sekaligus. Yang mendesak tetap menang: peserta
        // yang belum pernah didampingi TIDAK turun jadi sekadar "ada janji
        // hari ini" hanya karena janjinya kebetulan hari ini.
        $tingkat = match (true) {
            $tinggi => 'Tinggi',
            $jadwalHariIni => 'Hari Ini',
            $sedang => 'Sedang',
            default => 'Rendah',
        };

        if ($tingkat === 'Rendah') {
            $alasan[] = 'Aktif dan rutin diperbarui';
        }

        return [
            'peserta' => $p,
            'follow_up' => $fu,
            'keaktifan' => $this->kpi->keaktifanPeserta($fu['terakhir']),
            'tingkat' => $tingkat,
            'warna' => match ($tingkat) {
                'Tinggi' => 'bg-status-danger/10 text-status-danger',
                'Sedang' => 'bg-gold-light text-status-warning',
                'Hari Ini' => 'bg-status-info/10 text-status-info',
                default => 'bg-primary-light text-status-success',
            },
            'alasan' => $alasan,
            'skor' => $skor,
            // Tombol yang ditawarkan dibaca dari FollowUpService, sumber yang
            // SAMA dengan daftar Follow Up — supaya peserta yang sama tidak
            // menawarkan pilihan berbeda tergantung halaman mana yang dibuka.
            'tindakan' => $this->followUp->tindakan(
                $fu,
                $sudahLaporBulanIni->has($p->id),
                $punyaBaseline->has($p->id)
            ),
        ];
    }
}
