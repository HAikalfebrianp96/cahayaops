<?php

namespace App\Http\Controllers;

use App\Models\BusinessUnit;
use App\Models\Customer;
use App\Models\HppCalculation;
use App\Models\KoperasiMember;
use App\Models\LegalitasRecord;
use App\Models\MemberSaving;
use App\Models\OmzetReport;
use App\Models\ParticipantMeasurement;
use App\Models\Participant;
use App\Models\Pendamping;
use App\Models\PendampinganRecord;
use App\Models\Pengeluaran;
use App\Models\PengeluaranCategory;
use App\Models\ProductService;
use App\Models\SalesTransaction;
use App\Models\TrainingRecord;
use App\Services\PenghapusanPermanenService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * ARSIP DATA — satu pintu untuk seluruh data yang di-Soft Delete.
 *
 * KENAPA TERPUSAT, bukan tab arsip di tiap halaman.
 *
 * Sebelas modul memakai Soft Delete. Memberi masing-masing menu arsipnya
 * sendiri akan menggandakan sidebar, dan yang lebih buruk: data yang dihapus
 * jadi tersebar di sebelas tempat, sehingga tidak ada satu pun halaman yang
 * bisa menjawab "apa saja yang terhapus minggu ini?".
 *
 * Halaman ini menjawabnya. Tiap modul jadi satu tab, dan tab yang ditampilkan
 * MENGIKUTI KEWENANGAN — bukan sekadar disembunyikan:
 *
 *   Admin        -> seluruh modul.
 *   Pendamping   -> hanya catatan milik binaannya sendiri (pendampingan,
 *                   pelatihan, omzet, HPP). TIDAK ada tab Peserta: Pendamping
 *                   memang tidak berwenang menghapus peserta, jadi tidak ada
 *                   yang bisa ia pulihkan di sana.
 *   Tim Koperasi -> hanya modul koperasi.
 *
 * Otorisasinya BERLAPIS. Daftar tab menentukan apa yang terlihat, query tiap
 * tab sudah ter-scope di level database, dan pemulihannya sendiri tetap
 * diperiksa ulang oleh policy modul masing-masing — halaman ini tidak punya
 * jalur restore sendiri sama sekali, ia hanya menunjuk ke route modulnya.
 */
class ArsipController extends Controller
{
    public function __construct(private readonly PenghapusanPermanenService $penghapusan)
    {
    }

    public function index(Request $request)
    {
        $user = Auth::user();

        $tersedia = $this->tabTersedia($user);

        abort_if(empty($tersedia), 403);

        // Tab yang diminta harus ada di daftar KEWENANGANNYA, bukan sekadar
        // ada di sistem — kalau tidak, ?tab=peserta dari akun Pendamping akan
        // menampilkan peserta seluruh organisasi.
        $tab = $request->string('tab')->toString();
        if (! array_key_exists($tab, $tersedia)) {
            $tab = array_key_first($tersedia);
        }

        return view('arsip.index', [
            'tabTersedia' => $tersedia,
            'tabAktif' => $tab,
            'judulTab' => $tersedia[$tab],
            'baris' => $this->baris($tab, $user, $request),
            'filters' => $request->only(['q']),
            'jumlahPerTab' => $this->jumlahPerTab($tersedia, $user),
            // Tombol Hapus Permanen HANYA dirender untuk Admin. Dihitung di
            // sini, bukan ditebak di Blade, supaya syaratnya sama persis
            // dengan yang diperiksa HapusPermanenController.
            'bolehHapusPermanen' => $user->isAdmin()
                && array_key_exists($tab, PenghapusanPermanenService::modulTersedia()),
            'kalimatKonfirmasi' => PenghapusanPermanenService::KONFIRMASI,
        ]);
    }

    /**
     * Tab apa saja yang boleh dibuka user ini.
     *
     * Memakai helper otorisasi yang SUDAH ADA (isAdmin, isPendamping +
     * pendamping_id, bolehKelolaKoperasi) — tidak ada aturan izin baru yang
     * ditulis di sini dan bisa berbeda dari modulnya.
     *
     * @return array<string, string> [kunci tab => judul]
     */
    private function tabTersedia($user): array
    {
        $tab = [];

        if ($user->isAdmin()) {
            $tab['peserta'] = 'Peserta';
            $tab['pendamping'] = 'Pendamping';
            // Pengukuran perkembangan HANYA muncul untuk Admin — sejalan
            // dengan ParticipantMeasurementPolicy, yang memang membatasi
            // hapus & pulihkan ke Admin saja. Menampilkan tabnya ke
            // Pendamping hanya akan menghasilkan tombol Pulihkan yang 403.
            $tab['perkembangan'] = 'Perkembangan Usaha';
        }

        // Catatan milik peserta — Admin melihat semua, Pendamping hanya
        // catatan binaannya (di-scope di baris()).
        if ($user->isAdmin() || ($user->isPendamping() && $user->pendamping_id)) {
            $tab['pendampingan'] = 'Riwayat Pendampingan';
            $tab['pelatihan'] = 'Pelatihan';
            $tab['omzet'] = 'Laporan Omzet';
            $tab['hpp'] = 'Perhitungan HPP';
            $tab['legalitas'] = 'Dokumen Legalitas';
        }

        if ($user->bolehKelolaKoperasi()) {
            $tab['anggota'] = 'Anggota Koperasi';
            $tab['unit-usaha'] = 'Unit Usaha';
            $tab['produk'] = 'Produk & Jasa';
            $tab['pelanggan'] = 'Pelanggan';
            $tab['kategori'] = 'Kategori Pengeluaran';

            // CATATAN UANG YANG DIBATALKAN — bukan Soft Delete.
            //
            // Tiga modul ini tidak pernah menghapus barisnya; keduanya
            // berpindah status menjadi "Dibatalkan" dan berhenti dihitung.
            // Sebelumnya tidak ada satu pun halaman yang bisa menjawab "apa
            // saja yang dibatalkan bulan ini?" — pembatalannya hanya terlihat
            // kalau seseorang kebetulan membuka transaksinya satu per satu.
            $tab['penjualan-batal'] = 'Penjualan Dibatalkan';
            $tab['pengeluaran-batal'] = 'Pengeluaran Dibatalkan';
            $tab['simpanan-batal'] = 'Simpanan Dibatalkan';
        }

        return $tab;
    }

    /**
     * Isi satu tab, sudah ter-scope ke kewenangan user.
     *
     * Semua dikembalikan sebagai bentuk yang SAMA supaya satu template tabel
     * bisa merender kesebelas modul tanpa percabangan di Blade:
     *   judul, keterangan, dihapus (Carbon), penghapus (string),
     *   rute restore + id.
     */
    private function baris(string $tab, $user, Request $request)
    {
        $q = $request->string('q')->toString();

        // Pendamping hanya boleh melihat catatan binaannya. ID-nya dihitung
        // sekali di sini lalu dipakai seluruh query di bawah — kalau tiap
        // query menyaring sendiri-sendiri, cepat atau lambat ada satu yang
        // terlewat dan bocor ke binaan Pendamping lain.
        $binaanIds = $user->isAdmin()
            ? null
            : Participant::withTrashed()->where('pendamping_id', $user->pendamping_id)->pluck('id');

        return match ($tab) {
            'peserta' => Participant::onlyTrashed()->with('penghapus')
                ->when($q, fn ($k) => $k->where('nama_peserta', 'like', "%{$q}%"))
                ->orderByDesc('deleted_at')->paginate(15)->withQueryString()
                ->through(fn ($p) => $this->format(
                    $p->nama_peserta, $p->nama_usaha, $p, 'participants.restore'
                )),

            'pendamping' => Pendamping::onlyTrashed()->with('penghapus')->withCount('participants')
                ->when($q, fn ($k) => $k->where('nama', 'like', "%{$q}%"))
                ->orderByDesc('deleted_at')->paginate(15)->withQueryString()
                ->through(fn ($p) => $this->format(
                    $p->nama, $p->participants_count . ' binaan tertaut', $p, 'pendampings.restore'
                )),

            'pendampingan' => PendampinganRecord::onlyTrashed()->with('penghapus')
                ->when($binaanIds !== null, fn ($k) => $k->whereIn('participant_id', $binaanIds))
                ->when($q, fn ($k) => $k->where('materi', 'like', "%{$q}%"))
                ->orderByDesc('deleted_at')->paginate(15)->withQueryString()
                ->through(fn ($r) => $this->format(
                    $r->tanggal->format('d-m-Y') . ' — ' . $r->jenis_kegiatan,
                    $this->namaPeserta($r) . ' · ' . \Illuminate\Support\Str::limit((string) $r->materi, 60),
                    $r, 'pendampingan.restore'
                )),

            'pelatihan' => TrainingRecord::onlyTrashed()->with('penghapus')
                ->when($binaanIds !== null, fn ($k) => $k->whereIn('participant_id', $binaanIds))
                ->when($q, fn ($k) => $k->where('nama_pelatihan', 'like', "%{$q}%"))
                ->orderByDesc('deleted_at')->paginate(15)->withQueryString()
                ->through(fn ($r) => $this->format(
                    $r->nama_pelatihan,
                    $this->namaPeserta($r) . ' · ' . $r->tanggal->format('d-m-Y') . ' · ' . $r->status,
                    $r, 'training.restore'
                )),

            'omzet' => OmzetReport::onlyTrashed()->with('penghapus')
                ->when($binaanIds !== null, fn ($k) => $k->whereIn('participant_id', $binaanIds))
                ->orderByDesc('deleted_at')->paginate(15)->withQueryString()
                ->through(fn ($r) => $this->format(
                    'Omzet ' . $r->periodeLabel(),
                    $this->namaPeserta($r) . ' · ' . rupiah($r->omzet),
                    $r, 'omzet.restore'
                )),

            'perkembangan' => ParticipantMeasurement::onlyTrashed()->with('penghapus')
                ->when($binaanIds !== null, fn ($k) => $k->whereIn('participant_id', $binaanIds))
                ->orderByDesc('deleted_at')->paginate(15)->withQueryString()
                ->through(fn ($r) => $this->format(
                    $r->jenis . ' ' . $r->tanggal->format('d-m-Y'),
                    $this->namaPeserta($r),
                    $r, 'perkembangan.restore'
                )),

            'hpp' => HppCalculation::onlyTrashed()->with('penghapus')
                ->when($binaanIds !== null, fn ($k) => $k->whereIn('participant_id', $binaanIds))
                ->when($q, fn ($k) => $k->where('nama_produk', 'like', "%{$q}%"))
                ->orderByDesc('deleted_at')->paginate(15)->withQueryString()
                ->through(fn ($r) => $this->format(
                    'HPP ' . $r->nama_produk,
                    $this->namaPeserta($r) . ' · HPP ' . rupiah($r->hpp_per_unit),
                    $r, 'hpp.restore'
                )),

            'legalitas' => LegalitasRecord::onlyTrashed()->with('penghapus')
                ->when($binaanIds !== null, fn ($k) => $k->whereIn('participant_id', $binaanIds))
                ->when($q, fn ($k) => $k->where('jenis_dokumen', 'like', "%{$q}%"))
                ->latest('deleted_at')->paginate(20)->withQueryString()
                ->through(fn ($l) => $this->format(
                    $l->jenis_dokumen,
                    $this->namaPeserta($l),
                    $l,
                    'legalitas.restore',
                )),

            'anggota' => KoperasiMember::onlyTrashed()->with(['penghapus', 'participant'])
                ->when($q, fn ($k) => $k->where('nama', 'like', "%{$q}%"))
                ->orderByDesc('deleted_at')->paginate(15)->withQueryString()
                ->through(fn ($m) => $this->format(
                    $m->namaTampil(), $m->nomor_anggota, $m, 'koperasi.anggota.restore'
                )),

            'unit-usaha' => BusinessUnit::onlyTrashed()->with('penghapus')
                ->when($q, fn ($k) => $k->where('nama', 'like', "%{$q}%"))
                ->orderByDesc('deleted_at')->paginate(15)->withQueryString()
                ->through(fn ($u) => $this->format(
                    $u->nama, 'Kode ' . $u->kode, $u, 'koperasi.unit-usaha.restore'
                )),

            'produk' => ProductService::onlyTrashed()->with('penghapus')
                ->when($q, fn ($k) => $k->where('nama', 'like', "%{$q}%"))
                ->orderByDesc('deleted_at')->paginate(15)->withQueryString()
                ->through(fn ($p) => $this->format(
                    $p->nama, 'Kode ' . $p->kode, $p, 'koperasi.produk.restore'
                )),

            'pelanggan' => Customer::onlyTrashed()->with('penghapus')
                ->when($q, fn ($k) => $k->where('nama', 'like', "%{$q}%"))
                ->orderByDesc('deleted_at')->paginate(15)->withQueryString()
                ->through(fn ($c) => $this->format(
                    $c->nama, $c->nomor_hp ?: 'Tanpa nomor HP', $c, 'koperasi.pelanggan.restore'
                )),

            'kategori' => PengeluaranCategory::onlyTrashed()->with('penghapus')
                ->when($q, fn ($k) => $k->where('nama', 'like', "%{$q}%"))
                ->orderByDesc('deleted_at')->paginate(15)->withQueryString()
                ->through(fn ($k) => $this->format(
                    $k->nama, 'Kategori pengeluaran', $k, 'koperasi.kategori-pengeluaran.restore'
                )),

            'penjualan-batal' => SalesTransaction::with(['pembatal', 'customer'])
                ->where('status_transaksi', SalesTransaction::STATUS_DIBATALKAN)
                ->when($q, fn ($k) => $k->where('nomor_transaksi', 'like', "%{$q}%"))
                ->orderByDesc('dibatalkan_at')->paginate(20)->withQueryString()
                ->through(fn ($t) => $this->formatBatal(
                    $t->nomor_transaksi . ' — ' . rupiah($t->total),
                    ($t->customer->nama ?? 'Tanpa pelanggan') . ' · ' . $t->tanggal->format('d-m-Y'),
                    $t->dibatalkan_at,
                    $t->pembatal?->name,
                    $t->alasan_pembatalan,
                    'koperasi.penjualan.pulihkan',
                    $t->getKey(),
                    $t,
                )),

            'pengeluaran-batal' => Pengeluaran::with(['pembatal', 'businessUnit'])
                ->where('status', Pengeluaran::STATUS_DIBATALKAN)
                ->when($q, fn ($k) => $k->where('kategori', 'like', "%{$q}%"))
                ->orderByDesc('dibatalkan_at')->paginate(20)->withQueryString()
                ->through(fn ($p) => $this->formatBatal(
                    $p->kategori . ' — ' . rupiah($p->nominal),
                    ($p->businessUnit->nama ?? 'Operasional') . ' · ' . $p->tanggal->format('d-m-Y'),
                    $p->dibatalkan_at,
                    $p->pembatal?->name,
                    $p->alasan_pembatalan,
                    'koperasi.pengeluaran.pulihkan',
                    $p->getKey(),
                    $p,
                )),

            'simpanan-batal' => MemberSaving::with(['pembatal', 'koperasiMember'])
                ->where('status', MemberSaving::STATUS_DIBATALKAN)
                ->orderByDesc('dibatalkan_at')->paginate(20)->withQueryString()
                ->through(fn ($m) => $this->formatBatal(
                    $m->jenis_simpanan . ' — ' . rupiah($m->nominal),
                    ($m->koperasiMember->nama ?? 'Anggota tidak diketahui') . ' · ' . $m->tanggal->format('d-m-Y'),
                    $m->dibatalkan_at,
                    $m->pembatal?->name,
                    $m->alasan_koreksi,
                    // Entri yang sudah punya pengganti berlaku TIDAK diberi
                    // jalur pulihkan — memulihkannya akan menghitung uang
                    // yang sama dua kali ke saldo anggota.
                    $m->punyaPenggantiBerlaku() ? null : 'koperasi.savings.pulihkan',
                    $m->getKey(),
                    $m,
                )),

            default => abort(404),
        };
    }

    /** Nama peserta pemilik catatan — ikut melihat peserta yang diarsipkan. */
    private function namaPeserta($record): string
    {
        return $record->pesertaTermasukArsip()?->nama_peserta ?? 'Peserta tidak diketahui';
    }

    /** Bentuk seragam yang dirender template tabel arsip. */
    private function format(string $judul, ?string $keterangan, $model, string $rute): array
    {
        return [
            'judul' => $judul,
            'keterangan' => $keterangan,
            'dihapus' => $model->deleted_at,
            'penghapus' => $model->namaPenghapus(),
            'rute' => $rute,
            'id' => $model->getKey(),
            // null = boleh dihapus permanen. Kalau terisi, isinya adalah
            // KALIMAT yang menjelaskan penahannya — ditampilkan apa adanya
            // ke operator, bukan disembunyikan jadi tombol yang mati diam.
            'tertahan' => $this->penghapusan->alasanTertahan($model),
        ];
    }

    /**
     * Bentuk seragam untuk catatan yang DIBATALKAN (bukan Soft Delete).
     *
     * Dipetakan ke bentuk yang sama dengan format() supaya satu template
     * tabel tetap merender semuanya. Dua hal yang berbeda:
     *
     * - 'tertahan' selalu diisi. Transaksi penjualan, pengeluaran, dan
     *   simpanan TIDAK PERNAH bisa dihapus permanen dari halaman ini —
     *   ketiganya catatan uang, dan pembatalannya sudah cukup sebagai
     *   koreksi. Kalimatnya menjelaskan itu, bukan sekadar menyembunyikan
     *   tombolnya.
     * - 'rute' boleh null untuk baris yang memang tidak boleh dipulihkan.
     */
    private function formatBatal(
        string $judul,
        ?string $keterangan,
        $dibatalkanAt,
        ?string $pembatal,
        ?string $alasan,
        ?string $rute,
        int $id,
        $model = null,
    ): array {
        return [
            'judul' => $judul,
            'keterangan' => $keterangan . ($alasan ? ' · Alasan: ' . $alasan : ''),
            'dihapus' => $dibatalkanAt,
            'penghapus' => $pembatal ?? 'Tidak tercatat',
            'rute' => $rute,
            'id' => $id,
            // Dihitung sungguhan lewat service yang sama dengan yang
            // memeriksa saat tombolnya ditekan. Kalau dua-duanya tidak
            // memakai sumber yang sama, tombolnya bisa muncul untuk data
            // yang ternyata ditolak — atau sebaliknya.
            'tertahan' => $model ? $this->penghapusan->alasanTertahan($model) : null,
        ];
    }

    /**
     * Jumlah data terhapus per tab, untuk lencana di tab.
     *
     * Sengaja hanya COUNT — murah, dan cukup untuk memberi tahu tab mana yang
     * perlu diperiksa tanpa harus membukanya satu per satu.
     */
    private function jumlahPerTab(array $tersedia, $user): array
    {
        $binaanIds = $user->isAdmin()
            ? null
            : Participant::withTrashed()->where('pendamping_id', $user->pendamping_id)->pluck('id');

        $scoped = fn ($kelas) => $kelas::onlyTrashed()
            ->when($binaanIds !== null, fn ($k) => $k->whereIn('participant_id', $binaanIds))
            ->count();

        $peta = [
            'peserta' => fn () => Participant::onlyTrashed()->count(),
            'pendamping' => fn () => Pendamping::onlyTrashed()->count(),
            'pendampingan' => fn () => $scoped(PendampinganRecord::class),
            'pelatihan' => fn () => $scoped(TrainingRecord::class),
            'omzet' => fn () => $scoped(OmzetReport::class),
            'hpp' => fn () => $scoped(HppCalculation::class),
            'perkembangan' => fn () => $scoped(ParticipantMeasurement::class),
            'legalitas' => fn () => $scoped(LegalitasRecord::class),
            'anggota' => fn () => KoperasiMember::onlyTrashed()->count(),
            'unit-usaha' => fn () => BusinessUnit::onlyTrashed()->count(),
            'produk' => fn () => ProductService::onlyTrashed()->count(),
            'pelanggan' => fn () => Customer::onlyTrashed()->count(),
            'kategori' => fn () => PengeluaranCategory::onlyTrashed()->count(),
            'penjualan-batal' => fn () => SalesTransaction::where('status_transaksi', SalesTransaction::STATUS_DIBATALKAN)->count(),
            'pengeluaran-batal' => fn () => Pengeluaran::where('status', Pengeluaran::STATUS_DIBATALKAN)->count(),
            'simpanan-batal' => fn () => MemberSaving::where('status', MemberSaving::STATUS_DIBATALKAN)->count(),
        ];

        $hasil = [];
        foreach (array_keys($tersedia) as $kunci) {
            $hasil[$kunci] = $peta[$kunci]();
        }

        return $hasil;
    }
}
