<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\KoperasiMember;
use App\Models\Participant;
use App\Models\Pendamping;
use App\Models\SalesTransaction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * PERIKSA KEWAJARAN DATA — HANYA MEMBACA, TIDAK PERNAH MENGUBAH.
 *
 * =========================================================================
 * KENAPA PERINTAH INI ADA
 * =========================================================================
 *
 * Seluruh test memeriksa apakah KODE-nya benar. Tidak satu pun memeriksa
 * apakah DATANYA masuk akal — dan keduanya persoalan yang berbeda.
 *
 * Kode yang sempurna tetap bisa menyimpan peserta yang mulai programnya
 * tahun depan, pelanggan bernama "dsvbdfbfdb" sisa uji coba, atau laporan
 * omzet yang HPP-nya melebihi omzetnya sendiri. Semua itu lolos validasi
 * pada zamannya, atau masuk lewat jalur yang aturannya belum seketat
 * sekarang.
 *
 * Perintah ini dipakai sebelum dipakai sungguhan, dan sesekali sesudahnya.
 *
 * =========================================================================
 * YANG TIDAK DILAKUKANNYA
 * =========================================================================
 *
 * TIDAK menghapus, TIDAK memperbaiki, TIDAK menandai apa pun. Setiap
 * temuan hanya ditampilkan supaya manusia yang memutuskan — sebagian
 * "kejanggalan" ternyata wajar begitu dilihat isinya, dan menghapusnya
 * otomatis akan membuang data sungguhan.
 *
 * Contoh nyata: dua peserta dengan nomor HP sama terlihat seperti data
 * ganda, padahal keduanya orang berbeda dengan usaha berbeda yang memang
 * berbagi satu telepon. Satu-satunya cara tahu adalah dengan melihatnya.
 */
class PeriksaKewajaranData extends Command
{
    protected $signature = 'cahayaos:periksa-data';

    protected $description = 'Memeriksa kewajaran data (hanya membaca): relasi menggantung, angka janggal, sisa data uji coba.';

    /** @var list<string> */
    private array $temuan = [];

    public function handle(): int
    {
        $this->info('PEMERIKSAAN KEWAJARAN DATA — tidak ada data yang diubah.');
        $this->newLine();

        $this->relasiMenggantung();
        $this->angkaJanggal();
        $this->sisaUjiCoba();
        $this->kemungkinanGanda();
        $this->berkasUnggahan();

        $this->newLine();

        if ($this->temuan === []) {
            $this->info('Tidak ada yang perlu diperiksa.');

            return self::SUCCESS;
        }

        $this->warn('Perlu diperiksa manusia (' . count($this->temuan) . '):');

        foreach ($this->temuan as $t) {
            $this->line('  - ' . $t);
        }

        $this->newLine();
        $this->line('Tidak semuanya berarti salah. Lihat isinya sebelum memutuskan apa pun.');

        // Sengaja tetap SUCCESS: ini laporan untuk dibaca, bukan gerbang yang
        // menjatuhkan proses. Menjadikannya gagal akan membuat orang
        // mematikannya, dan laporan yang dimatikan tidak menolong siapa pun.
        return self::SUCCESS;
    }

    /**
     * BERKAS UNGGAHAN — dua arah, keduanya bermasalah dengan cara berbeda.
     *
     * =================================================================
     *
     * BARIS MENUNJUK BERKAS YANG HILANG
     *   Dokumennya lenyap, tapi barisnya tetap dihitung sistem. Dokumen
     *   legalitas berstatus "Sudah Ada" tetap menaikkan KPI walau
     *   berkasnya tidak bisa dibuka siapa pun.
     *
     * BERKAS TIDAK DITUNJUK BARIS MANA PUN
     *   Sampah yang menumpuk — dan isinya bukan sampah biasa: dokumen
     *   legalitas memuat identitas usaha peserta. Berkas yang seharusnya
     *   sudah ikut terhapus tapi masih tersimpan adalah persoalan
     *   kerahasiaan, bukan sekadar ruang disk.
     */
    private function berkasUnggahan(): void
    {
        $this->line('<options=bold>BERKAS UNGGAHAN</>');

        $dipakai = [];
        $hilang = 0;

        foreach ([
            'legalitas_records' => 'file_path',
            'omzet_reports' => 'bukti_path',
            'pengeluaran' => 'bukti_path',
            'program_investments' => 'bukti_path',
            'system_settings' => 'logo_path',
        ] as $tabel => $kolom) {
            if (! Schema::hasTable($tabel) || ! Schema::hasColumn($tabel, $kolom)) {
                continue;
            }

            foreach (DB::table($tabel)->whereNotNull($kolom)->where($kolom, '!=', '')->get() as $baris) {
                $path = $baris->{$kolom};
                $dipakai[] = $path;

                if (! Storage::exists($path)) {
                    $hilang++;
                    $this->line(sprintf('        %s id=%s -> %s', $tabel, $baris->id, $path));
                }
            }
        }

        $this->catat(
            'Baris menunjuk berkas yang hilang',
            $hilang,
            'dokumennya tidak bisa dibuka, tapi barisnya tetap dihitung'
        );

        $yatim = 0;

        foreach (['legalitas', 'bukti-omzet', 'bukti', 'logo'] as $folder) {
            if (! Storage::exists($folder)) {
                continue;
            }

            foreach (array_diff(Storage::allFiles($folder), $dipakai) as $f) {
                $yatim++;
                $this->line(sprintf('        %s (%s KB)', $f, number_format(Storage::size($f) / 1024, 1)));
            }
        }

        $this->catat(
            'Berkas tidak ditunjuk baris mana pun',
            $yatim,
            'sebagian bisa memuat dokumen pribadi peserta'
        );

        $this->newLine();
    }

    private function catat(string $judul, int $jumlah, string $saran = ''): void
    {
        if ($jumlah === 0) {
            $this->line(sprintf('  %-50s %s', $judul, 'bersih'));

            return;
        }

        $this->line(sprintf('  <fg=yellow>%-50s %d  perlu dilihat</>', $judul, $jumlah));

        $this->temuan[] = $judul . ($saran !== '' ? ' — ' . $saran : '');
    }

    private function relasiMenggantung(): void
    {
        $this->line('<options=bold>RELASI MENGGANTUNG</>');

        $this->catat(
            'Peserta tanpa pendamping',
            Participant::whereNull('pendamping_id')->count(),
            'binaannya tidak muncul di daftar kerja siapa pun'
        );

        $this->catat(
            'Peserta menunjuk pendamping terarsip',
            Participant::whereNotNull('pendamping_id')
                ->whereHas('pendamping', fn ($q) => $q->onlyTrashed())->count(),
            'pindahkan ke pendamping aktif'
        );

        foreach ([
            'Catatan pendampingan' => ['pendampingan_records', 'participant_id', 'participants'],
            'Laporan omzet' => ['omzet_reports', 'participant_id', 'participants'],
            'Catatan pelatihan' => ['training_records', 'participant_id', 'participants'],
            'Dokumen legalitas' => ['legalitas_records', 'participant_id', 'participants'],
        ] as $label => [$tabel, $kolom, $induk]) {
            $this->catat(
                $label . ' tanpa peserta',
                DB::table($tabel)
                    ->leftJoin($induk, $induk . '.id', '=', $tabel . '.' . $kolom)
                    ->whereNull($induk . '.id')->count(),
                'baris yatim'
            );
        }

        $this->newLine();
    }

    private function angkaJanggal(): void
    {
        $this->line('<options=bold>ANGKA YANG TIDAK MASUK AKAL</>');

        $this->catat(
            'Omzet dengan HPP melebihi omzet',
            DB::table('omzet_reports')->whereNotNull('hpp')->whereColumn('hpp', '>', 'omzet')->count(),
            'laba kotornya negatif'
        );

        $this->catat(
            'Omzet bernilai negatif',
            DB::table('omzet_reports')->where('omzet', '<', 0)->count()
        );

        $this->catat(
            'Peserta mulai program di masa depan',
            Participant::whereDate('tanggal_mulai_program', '>', now())->count(),
            'periksa tahunnya'
        );

        $this->catat(
            'Catatan pendampingan bertanggal masa depan',
            DB::table('pendampingan_records')->whereDate('tanggal', '>', now())->count(),
            'pesertanya akan hilang dari daftar tindak lanjut sampai tanggal itu lewat'
        );

        $this->catat(
            'Simpanan bertanggal masa depan',
            DB::table('member_savings')->whereDate('tanggal', '>', now())->count()
        );

        $this->catat(
            'Penjualan yang totalnya tidak sama dengan itemnya',
            SalesTransaction::with('items')->get()
                ->filter(fn ($t) => abs((float) $t->items->sum('subtotal') - (float) $t->subtotal) > 0.01)
                ->count(),
            'kemungkinan item diubah tanpa menghitung ulang total'
        );

        $this->newLine();
    }

    /**
     * Nama yang tidak menyerupai nama orang atau usaha.
     *
     * Ini HEURISTIK, bukan kepastian — ia bisa melewatkan sisa uji coba yang
     * kebetulan terlihat wajar, dan bisa pula menandai nama sungguhan yang
     * tidak lazim. Karena itu keluarannya ditampilkan apa adanya untuk
     * dilihat, bukan dipakai menghapus apa pun.
     */
    private function tidakLazim(string $nama): bool
    {
        $bersih = preg_replace('/[^a-z]/', '', mb_strtolower($nama));

        if (mb_strlen($bersih) < 4) {
            return false;
        }

        if (! preg_match('/[aiueo]/', $bersih)) {
            return true;
        }

        $huruf = count_chars($bersih, 1);

        // Satu huruf mendominasi, atau perbendaharaan hurufnya sangat sempit
        // untuk panjangnya — pola khas ketikan asal.
        return max($huruf) / mb_strlen($bersih) > 0.4
            || count($huruf) / mb_strlen($bersih) < 0.5;
    }

    private function sisaUjiCoba(): void
    {
        $this->line('<options=bold>KEMUNGKINAN SISA UJI COBA</>');

        foreach ([
            'Peserta' => [Participant::class, 'nama_peserta'],
            'Anggota koperasi' => [KoperasiMember::class, 'nama'],
            'Pendamping' => [Pendamping::class, 'nama'],
            'Pelanggan' => [Customer::class, 'nama'],
        ] as $label => [$kelas, $kolom]) {
            $aneh = $kelas::withTrashed()->get()
                ->filter(fn ($m) => $this->tidakLazim((string) $m->{$kolom}));

            $this->catat(
                $label . ' bernama tidak lazim',
                $aneh->count(),
                'lihat dulu, sebagian bisa jadi nama sungguhan'
            );

            foreach ($aneh as $m) {
                $this->line(sprintf(
                    '        id=%-4s %s%s',
                    $m->id,
                    $m->{$kolom},
                    $m->trashed() ? '  [terarsip]' : ''
                ));
            }
        }

        $this->newLine();
    }

    private function kemungkinanGanda(): void
    {
        $this->line('<options=bold>KEMUNGKINAN DATA GANDA</>');

        $ganda = DB::table('participants')
            ->selectRaw('nomor_hp, COUNT(*) as jumlah')
            ->whereNull('deleted_at')
            ->whereNotNull('nomor_hp')->where('nomor_hp', '!=', '')
            ->groupBy('nomor_hp')->having('jumlah', '>', 1)->get();

        $this->catat(
            'Peserta berbagi nomor HP',
            $ganda->count(),
            'sering kali WAJAR — satu telepon dipakai sekeluarga'
        );

        foreach ($ganda as $g) {
            $nama = Participant::where('nomor_hp', $g->nomor_hp)->pluck('nama_peserta')->implode(', ');
            $this->line('        ' . $g->nomor_hp . ' — ' . $nama);
        }

        $this->newLine();
    }
}
